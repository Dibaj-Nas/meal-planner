<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Api;
use App\Middleware\AuthMiddleware;
use App\Models\Ingredient;
use App\Models\Meal;
use App\Services\NutritionService;

class RecipeController
{
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

    private function store(int $userId, array $body): void
    {
        if (empty($body['name'])) {
            Api::json(['success' => false, 'error' => 'Le nom de la recette est requis.'], 422);
        }

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

    private function enrichFromIngredients(int $userId, array $body): array
    {
        $names = $body['ingredients'] ?? [];
        if (is_string($names)) {
            $names = array_filter(array_map('trim', explode(',', $names)));
        }
        $ingredientModel = new Ingredient();
        $all = $ingredientModel->allByUser($userId);
        $objects = [];
        foreach ($names as $name) {
            foreach ($all as $ing) {
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
