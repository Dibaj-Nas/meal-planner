<?php
namespace App\Controllers;

use App\Middleware\AuthMiddleware;
use App\Models\Ingredient;
use App\Models\Meal;
use App\Services\MealGeneratorService;
use App\Services\NutritionService;
/**
 * MealController - getion des ingrédients, recettes et menus
 * 
 * toutes les routes de ce contrôleur nécessitnet une authentification
 * les réponses sont en JSON (API consommée par app.js via fetch)
 */

class MealController
{
    private Ingredient           $ingredientModel;
    private Meal                 $mealModel;
    private MealGeneratorService $generatorService;

    public function __construct()
    {
        /* Toutes les actions de ce contrôleur exigent une connexion */
        AuthMiddleware::requireAuth(true);

        $this->ingredientModel  = new Ingredient();
        $this->mealModel        = new Meal();
        $this->generatorService = new MealGeneratorService();
    }

    // ingrèdients

    /** GET /api/ingredients — Liste les ingrédients de l'utilisateur. */
    public function getIngredients(): void
    {
        $userId = AuthMiddleware::currentUserId();
        $search = $_GET['search'] ?? '';

        $ingredients = $this->ingredientModel->allByUser($userId, $search);

        $this->json(['success' => true, 'data' => $ingredients]);
    }

    /** POST /api/ingredients — Crée un ingrédient. */
    public function storeIngredient(): void
    {
        $userId = AuthMiddleware::currentUserId();
        $data   = $this->getJsonBody();

        /* Validation */
        if (empty($data['name'])) {
            $this->json(['success' => false, 'error' => 'Le nom est requis.'], 422);
            return;
        }

        $id = $this->ingredientModel->create($userId, $data);

        $this->json([
            'success' => true,
            'message' => 'Ingrédient créé.',
            'data'    => ['id' => $id],
        ], 201);
    }

    /** PUT /api/ingredients/:id — Met à jour un ingrédient. */
    public function updateIngredient(array $params): void
    {
        $userId = AuthMiddleware::currentUserId();
        $id     = (int) ($params['id'] ?? 0);
        $data   = $this->getJsonBody();

        if (!$this->ingredientModel->findByIdAndUser($id, $userId)) {
            $this->json(['success' => false, 'error' => 'Ingrédient introuvable.'], 404);
            return;
        }

        $ok = $this->ingredientModel->update($id, $userId, $data);

        $this->json([
            'success' => $ok,
            'message' => $ok ? 'Ingrédient mis à jour.' : 'Échec de la mise à jour.',
        ]);
    }

    /** DELETE /api/ingredients/:id — Supprime un ingrédient. */
    public function destroyIngredient(array $params): void
    {
        $userId = AuthMiddleware::currentUserId();
        $id     = (int) ($params['id'] ?? 0);

        if (!$this->ingredientModel->findByIdAndUser($id, $userId)) {
            $this->json(['success' => false, 'error' => 'Ingrédient introuvable.'], 404);
            return;
        }

        $ok = $this->ingredientModel->delete($id, $userId);

        $this->json([
            'success' => $ok,
            'message' => $ok ? 'Ingrédient supprimé.' : 'Échec de la suppression.',
        ]);
    }

    // recettes

    /** GET /api/recipes — Liste les recettes (avec filtres optionnels). */
    public function getRecipes(): void
    {
        $userId   = AuthMiddleware::currentUserId();
        $mealType = $_GET['meal_type'] ?? '';
        $dietary  = $_GET['dietary']   ?? '';

        $recipes = $this->mealModel->allRecipesByUser($userId, $mealType, $dietary);

        $this->json(['success' => true, 'data' => $recipes]);
    }

    /** POST /api/recipes — Crée une recette. */
    public function storeRecipe(): void
    {
        $userId = AuthMiddleware::currentUserId();
        $data   = $this->getJsonBody();

        if (empty($data['name'])) {
            $this->json(['success' => false, 'error' => 'Le nom de la recette est requis.'], 422);
            return;
        }

        /* Normalise la liste des ingrédients */
        if (isset($data['ingredients']) && is_array($data['ingredients'])) {
            $data['ingredients_list'] = implode(', ', $data['ingredients']);
        }

        $id = $this->mealModel->createRecipe($userId, $data);

        $this->json([
            'success' => true,
            'message' => 'Recette créée.',
            'data'    => ['id' => $id],
        ], 201);
    }

    /** DELETE /api/recipes/:id — Supprime une recette. */
    public function destroyRecipe(array $params): void
    {
        $userId = AuthMiddleware::currentUserId();
        $id     = (int) ($params['id'] ?? 0);

        if (!$this->mealModel->findRecipeByIdAndUser($id, $userId)) {
            $this->json(['success' => false, 'error' => 'Recette introuvable.'], 404);
            return;
        }

        $ok = $this->mealModel->deleteRecipe($id, $userId);

        $this->json([
            'success' => $ok,
            'message' => $ok ? 'Recette supprimée.' : 'Échec de la suppression.',
        ]);
    }

    // menus hebdomadaires
    /** GET /api/menus — Liste les menus de l'utilisateur. */
    public function getMenus(): void
    {
        $userId = AuthMiddleware::currentUserId();
        $menus  = $this->mealModel->allMenusByUser($userId);

        $this->json(['success' => true, 'data' => $menus]);
    }

    /** GET /api/menus/:id — Retourne un menu complet avec ses repas. */
    public function getMenu(array $params): void
    {
        $userId = AuthMiddleware::currentUserId();
        $menuId = (int) ($params['id'] ?? 0);

        $menu = $this->mealModel->findMenuWithMeals($menuId, $userId);

        if (!$menu) {
            $this->json(['success' => false, 'error' => 'Menu introuvable.'], 404);
            return;
        }

        /* Enrichit avec le rapport nutritionnel */
        $report = NutritionService::weeklyReport(
            $menu['days'],
            (int)   $menu['persons'],
            (float) $menu['budget']
        );
        $menu['nutrition'] = $report;

        $this->json(['success' => true, 'data' => $menu]);
    }

    /**
     * POST /api/menus/generate — Génère un menu côté serveur.
     *
     * Body JSON attendu :
     *   { "budget": 60, "persons": 2, "dietary": "vegetarian" }
     */
    public function generateMenu(): void
    {
        $userId = AuthMiddleware::currentUserId();
        $params = $this->getJsonBody();

        try {
            $menu = $this->generatorService->generate($userId, $params);

            /* Calcule et ajoute le rapport nutritionnel */
            $menu['nutrition'] = NutritionService::weeklyReport(
                $menu['days'],
                $menu['persons'],
                $menu['budget']
            );

            $this->json(['success' => true, 'data' => $menu]);

        } catch (\RuntimeException $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    /**
     * POST /api/menus — Sauvegarde un menu généré en base.
     *
     * Body JSON : le menu complet retourné par generateMenu()
     */
    public function saveMenu(): void
    {
        $userId   = AuthMiddleware::currentUserId();
        $menuData = $this->getJsonBody();

        if (empty($menuData['days'])) {
            $this->json(['success' => false, 'error' => 'Données de menu invalides.'], 422);
            return;
        }

        $menuId = $this->mealModel->saveMenu($userId, $menuData);

        $this->json([
            'success' => true,
            'message' => 'Menu sauvegardé.',
            'data'    => ['id' => $menuId],
        ], 201);
    }

    /** DELETE /api/menus/:id — Supprime un menu. */
    public function destroyMenu(array $params): void
    {
        $userId = AuthMiddleware::currentUserId();
        $menuId = (int) ($params['id'] ?? 0);

        $ok = $this->mealModel->deleteMenu($menuId, $userId);

        $this->json([
            'success' => $ok,
            'message' => $ok ? 'Menu supprimé.' : 'Menu introuvable ou déjà supprimé.',
        ], $ok ? 200 : 404);
    }

    // helpers

    // envoie une réponse JSON avec le bon code HTTP
    private function json(array $data, int $code = 200): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Décode le corps de la requête JSON (ou utilise $_POST en fallback).
    private function getJsonBody(): array
    {
        $raw = file_get_contents('php://input');

        if ($raw) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return $_POST;
    }

}