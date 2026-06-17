<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Api;
use App\Middleware\AuthMiddleware;
use App\Models\Ingredient;

/**
 * IngredientController — API REST des ingrédients
 * ===============================================
 *
 * Le contrôleur fait le lien entre la requête HTTP et le modèle :
 *   1. il vérifie que l'utilisateur est connecté ;
 *   2. il choisit l'action selon la méthode HTTP (GET/POST/PUT/DELETE) ;
 *   3. il renvoie une réponse JSON.
 *
 * Correspondance méthode HTTP ↔ action (style REST) :
 *   GET    /ingredients      → liste
 *   POST   /ingredients      → création
 *   PUT    /ingredients/{id} → modification
 *   DELETE /ingredients/{id} → suppression
 */
class IngredientController
{
    /** Modèle d'accès aux données des ingrédients. */
    private Ingredient $model;

    public function __construct()
    {
        $this->model = new Ingredient();
    }

    /**
     * Point d'entrée appelé par le routeur (api.php).
     *
     * @param string   $method Méthode HTTP (GET, POST, PUT, DELETE).
     * @param int|null $id     Identifiant dans l'URL, ou null s'il n'y en a pas.
     */
    public function handle(string $method, ?int $id): void
    {
        // true = mode API : renvoie un JSON 401 si non connecté (pas de redirection).
        AuthMiddleware::requireAuth(true);
        $userId = (int) AuthMiddleware::currentUserId();
        $body   = Api::body();   // données envoyées par le client

        // match(true) = aiguillage : on exécute le 1er cas dont la condition est vraie.
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

    /** Crée un ingrédient (POST). Le nom est obligatoire (sinon erreur 422). */
    private function store(int $userId, array $body): void
    {
        if (empty($body['name'])) {
            // 422 = "Unprocessable Entity" : la donnée envoyée est invalide.
            Api::json(['success' => false, 'error' => 'Le nom est requis.'], 422);
        }
        $id = $this->model->create($userId, $body);
        // 201 = "Created" : une ressource a bien été créée.
        Api::json(['success' => true, 'message' => 'Ingrédient créé.', 'data' => ['id' => $id]], 201);
    }

    /** Modifie un ingrédient (PUT). On vérifie d'abord qu'il appartient à l'utilisateur. */
    private function update(int $userId, int $id, array $body): void
    {
        if (!$this->model->findByIdAndUser($id, $userId)) {
            Api::json(['success' => false, 'error' => 'Ingrédient introuvable.'], 404);
        }
        $ok = $this->model->update($id, $userId, $body);
        Api::json(['success' => $ok, 'message' => $ok ? 'Ingrédient mis à jour.' : 'Échec de la mise à jour.']);
    }

    /** Supprime un ingrédient (DELETE). Même contrôle de propriété que update(). */
    private function destroy(int $userId, int $id): void
    {
        if (!$this->model->findByIdAndUser($id, $userId)) {
            Api::json(['success' => false, 'error' => 'Ingrédient introuvable.'], 404);
        }
        $ok = $this->model->delete($id, $userId);
        Api::json(['success' => $ok, 'message' => $ok ? 'Ingrédient supprimé.' : 'Échec de la suppression.']);
    }
}
