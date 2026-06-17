<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Api;
use App\Middleware\AuthMiddleware;
use App\Models\Ingredient;
use App\Models\Meal;
use App\Services\NutritionService;

/**
 * RecipeController — API REST des recettes
 * ========================================
 *
 * Gère la liste, la création et la suppression des recettes d'un utilisateur.
 * Particularité : si l'utilisateur ne fournit pas le coût/les calories, on les
 * ESTIME automatiquement à partir des ingrédients (voir enrichFromIngredients).
 *
 * Routes :
 *   GET    /recipes          → liste (filtrable par meal_type et dietary)
 *   POST   /recipes          → création
 *   DELETE /recipes/{id}     → suppression
 */
class RecipeController
{
    /** Modèle "Meal" : il gère à la fois les recettes et les repas. */
    private Meal $model;

    public function __construct()
    {
        $this->model = new Meal();
    }

    public function handle(string $method, ?int $id): void
    {
        AuthMiddleware::requireAuth(true);
        $userId = (int) AuthMiddleware::currentUserId();
        $body   = Api::body();

        match (true) {
            $method === 'GET' && $id === null => Api::json([
                'success' => true,
                'data'    => $this->model->allRecipesByUser(
                    $userId,
                    (string) ($_GET['meal_type'] ?? ''),
                    (string) ($_GET['dietary'] ?? '')
                ),
            ]),
            $method === 'POST' && $id === null => $this->store($userId, $body),
            $method === 'DELETE' && $id !== null => $this->destroy($userId, $id),
            default => Api::json(['success' => false, 'error' => 'Méthode non autorisée'], 405),
        };
    }

    /** Crée une recette (POST), en estimant les valeurs manquantes si besoin. */
    private function store(int $userId, array $body): void
    {
        if (empty($body['name'])) {
            Api::json(['success' => false, 'error' => 'Le nom de la recette est requis.'], 422);
        }

        // Si le coût n'est pas fourni, on le calcule depuis les ingrédients.
        if (!isset($body['estimated_cost'])) {
            $body = $this->enrichFromIngredients($userId, $body);
        }

        $id = $this->model->createRecipe($userId, $body);
        Api::json(['success' => true, 'message' => 'Recette créée.', 'data' => ['id' => $id]], 201);
    }

    private function destroy(int $userId, int $id): void
    {
        if (!$this->model->findRecipeByIdAndUser($id, $userId)) {
            Api::json(['success' => false, 'error' => 'Recette introuvable.'], 404);
        }
        $ok = $this->model->deleteRecipe($id, $userId);
        Api::json(['success' => $ok, 'message' => $ok ? 'Recette supprimée.' : 'Échec de la suppression.']);
    }

    /**
     * Complète une recette avec des estimations (coût, calories, protéines)
     * calculées à partir de ses ingrédients.
     *
     * Étapes : on transforme la liste d'ingrédients (texte) en objets nutritionnels
     * en les recherchant dans la bibliothèque de l'utilisateur, puis on délègue
     * le calcul à NutritionService.
     */
    private function enrichFromIngredients(int $userId, array $body): array
    {
        // Les ingrédients peuvent arriver en tableau OU en texte "tomate, riz, …".
        $names = $body['ingredients'] ?? [];
        if (is_string($names)) {
            // explode → découpe sur les virgules ; trim → enlève les espaces ;
            // array_filter → retire les entrées vides.
            $names = array_filter(array_map('trim', explode(',', $names)));
        }
        $ingredientModel = new Ingredient();
        $all = $ingredientModel->allByUser($userId);
        $objects = [];
        // Pour chaque nom saisi, on cherche l'ingrédient correspondant en base.
        foreach ($names as $name) {
            foreach ($all as $ing) {
                // stripos = recherche insensible à la casse ("Tomate" ~ "tomate").
                if (stripos($ing['name'], $name) !== false) {
                    $objects[] = [
                        'price_per_unit'     => $ing['price'],
                        'calories_per_100g'  => $ing['calories'],
                        'protein_per_100g'   => $ing['protein'],
                    ];
                    break;
                }
            }
        }
        $est = NutritionService::estimateFromIngredients($objects);
        return array_merge($body, $est, ['ingredients' => $names]);
    }
}
