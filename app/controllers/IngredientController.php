<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Api;
use App\Middleware\AuthMiddleware;
use App\Models\Ingredient;

class IngredientController
{
    private Ingredient $model;

    public function __construct()
    {
        $this->model = new Ingredient();
    }

    public function handle(string $method, ?int $id): void
    {
        AuthMiddleware::requireAuth(true);
        $userId = (int) AuthMiddleware::currentUserId();
        $body   = Api::body();

        match (true) {
            $method === 'GET' && $id === null => Api::json([
                'success' => true,
                'data'    => $this->model->allByUser($userId, (string) ($_GET['search'] ?? '')),
            ]),
            $method === 'POST' && $id === null => $this->store($userId, $body),
            $method === 'PUT' && $id !== null  => $this->update($userId, $id, $body),
            $method === 'DELETE' && $id !== null => $this->destroy($userId, $id),
            default => Api::json(['success' => false, 'error' => 'Méthode non autorisée'], 405),
        };
    }

    private function store(int $userId, array $body): void
    {
        if (empty($body['name'])) {
            Api::json(['success' => false, 'error' => 'Le nom est requis.'], 422);
        }
        $id = $this->model->create($userId, $body);
        Api::json(['success' => true, 'message' => 'Ingrédient créé.', 'data' => ['id' => $id]], 201);
    }

    private function update(int $userId, int $id, array $body): void
    {
        if (!$this->model->findByIdAndUser($id, $userId)) {
            Api::json(['success' => false, 'error' => 'Ingrédient introuvable.'], 404);
        }
        $ok = $this->model->update($id, $userId, $body);
        Api::json(['success' => $ok, 'message' => $ok ? 'Ingrédient mis à jour.' : 'Échec de la mise à jour.']);
    }

    private function destroy(int $userId, int $id): void
    {
        if (!$this->model->findByIdAndUser($id, $userId)) {
            Api::json(['success' => false, 'error' => 'Ingrédient introuvable.'], 404);
        }
        $ok = $this->model->delete($id, $userId);
        Api::json(['success' => $ok, 'message' => $ok ? 'Ingrédient supprimé.' : 'Échec de la suppression.']);
    }
}
