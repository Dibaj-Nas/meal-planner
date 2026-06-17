<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Api;
use App\Core\Security;
use App\Middleware\AuthMiddleware;
use App\Models\User;

/**
 * AccountController — API de l'espace compte
 * ==========================================
 *
 * Gère, via Ajax, tout ce qui concerne le compte de l'utilisateur connecté :
 *   - profil (lecture / mise à jour)
 *   - mot de passe (changement)
 *   - paramètres (préférences alimentaires, budget…)
 *   - favoris (liste / ajout / suppression)
 *
 * Toutes les actions qui MODIFIENT des données vérifient d'abord le jeton CSRF.
 */
class AccountController
{
    /** Modèle utilisateur (toutes les opérations passent par lui). */
    private User $user;

    public function __construct()
    {
        $this->user = new User();
    }

    /**
     * Aiguillage principal : choisit l'action selon la méthode HTTP et la
     * sous-route ($action), par exemple GET "profile" ou PUT "password".
     *
     * @param string      $method Méthode HTTP.
     * @param string|null $action Sous-route (profile, password, settings, favorites).
     * @param int|null    $id     Identifiant éventuel (ex : recette à retirer des favoris).
     */
    public function handle(string $method, ?string $action, ?int $id): void
    {
        AuthMiddleware::requireAuth(true);   // mode API : 401 JSON si non connecté
        $userId = (int) AuthMiddleware::currentUserId();
        $body   = Api::body();

        match (true) {
            $method === 'GET'  && $action === 'profile'   => $this->getProfile($userId),
            $method === 'PUT'  && $action === 'profile'   => $this->updateProfile($userId, $body),
            $method === 'PUT'  && $action === 'password'  => $this->changePassword($userId, $body),
            $method === 'GET'  && $action === 'settings'  => $this->getSettings($userId),
            $method === 'PUT'  && $action === 'settings'  => $this->saveSettings($userId, $body),
            $method === 'GET'  && $action === 'favorites' => $this->getFavorites($userId),
            $method === 'POST' && $action === 'favorites'  => $this->addFavorite($userId, $body),
            $method === 'DELETE' && $action === 'favorites' && $id !== null
                => $this->removeFavorite($userId, $id),
            default => Api::json(['success' => false, 'error' => 'Route compte introuvable'], 404),
        };
    }

    /** Renvoie les informations de profil de l'utilisateur. */
    private function getProfile(int $userId): void
    {
        $profile = $this->user->getProfile($userId);
        if (!$profile) {
            Api::json(['success' => false, 'error' => 'Profil introuvable'], 404);
        }
        Api::json(['success' => true, 'data' => $profile]);
    }

    /** Met à jour prénom/nom/e-mail, puis synchronise la session. */
    private function updateProfile(int $userId, array $body): void
    {
        // verifyCsrf : on s'assure que la requête vient bien de notre formulaire.
        if (!Api::verifyCsrf($body)) {
            Api::json(['success' => false, 'error' => 'Token de sécurité invalide.'], 403);
        }

        $firstname = trim((string) ($body['firstname'] ?? ''));
        $lastname  = trim((string) ($body['lastname'] ?? ''));
        $email     = trim((string) ($body['email'] ?? ''));

        if ($firstname === '' || $lastname === '' || $email === '') {
            Api::json(['success' => false, 'error' => 'Tous les champs sont obligatoires.'], 422);
        }
        if (!Security::isValidEmail($email)) {
            Api::json(['success' => false, 'error' => 'Adresse e-mail invalide.'], 422);
        }

        $r = $this->user->updateProfile($userId, compact('firstname', 'lastname', 'email'));
        // Si la BDD est à jour, on met aussi à jour la session pour que l'en-tête
        // (nom affiché) reflète immédiatement le changement sans reconnexion.
        if ($r['success']) {
            $_SESSION['user']['firstname'] = $firstname;
            $_SESSION['user']['lastname']  = $lastname;
            $_SESSION['user']['email']     = $email;
        }
        Api::json($r, $r['success'] ? 200 : 422);
    }

    /** Change le mot de passe après plusieurs validations (CSRF, correspondance, robustesse). */
    private function changePassword(int $userId, array $body): void
    {
        if (!Api::verifyCsrf($body)) {
            Api::json(['success' => false, 'error' => 'Token de sécurité invalide.'], 403);
        }

        $current = (string) ($body['current_password'] ?? '');
        $new     = (string) ($body['new_password'] ?? '');
        $confirm = (string) ($body['confirm_password'] ?? '');

        if ($current === '' || $new === '' || $confirm === '') {
            Api::json(['success' => false, 'error' => 'Tous les champs mot de passe sont obligatoires.'], 422);
        }
        if ($new !== $confirm) {
            Api::json(['success' => false, 'error' => 'Les nouveaux mots de passe ne correspondent pas.'], 422);
        }
        if (!Security::isStrongPassword($new)) {
            Api::json(['success' => false, 'error' => 'Mot de passe trop faible (8 car. min, 1 majuscule, 1 chiffre, 1 symbole).'], 422);
        }

        $r = $this->user->changePassword($userId, $current, $new);
        Api::json($r, $r['success'] ? 200 : 422);
    }

    /** Renvoie les préférences enregistrées (régime, budget, nb de personnes). */
    private function getSettings(int $userId): void
    {
        Api::json(['success' => true, 'data' => $this->user->getUserSettings($userId)]);
    }

    /** Enregistre les préférences par défaut de l'utilisateur. */
    private function saveSettings(int $userId, array $body): void
    {
        if (!Api::verifyCsrf($body)) {
            Api::json(['success' => false, 'error' => 'Token de sécurité invalide.'], 403);
        }

        $r = $this->user->saveUserSettings($userId, [
            'dietary_pref'    => $body['dietary_pref'] ?? 'Tous',
            'default_budget'  => (float) ($body['default_budget'] ?? 100),
            'default_persons' => (int) ($body['default_persons'] ?? 2),
        ]);
        Api::json($r, $r['success'] ? 200 : 422);
    }

    /** Liste les recettes favorites, avec filtre optionnel par type de repas. */
    private function getFavorites(int $userId): void
    {
        $favorites = $this->user->getFavorites($userId);
        $type = $_GET['meal_type'] ?? '';

        if ($type !== '') {
            // array_filter garde seulement le bon type ; array_values réindexe
            // le tableau (0,1,2…) pour un JSON propre côté client.
            $favorites = array_values(array_filter(
                $favorites,
                fn($r) => ($r['meal_type'] ?? '') === $type
            ));
        }

        Api::json(['success' => true, 'data' => $favorites]);
    }

    /** Ajoute une recette aux favoris (POST). */
    private function addFavorite(int $userId, array $body): void
    {
        $recipeId = (int) ($body['recipe_id'] ?? 0);
        if ($recipeId <= 0) {
            Api::json(['success' => false, 'error' => 'ID de recette invalide.'], 422);
        }
        $r = $this->user->addFavorite($userId, $recipeId);
        Api::json($r, 201);
    }

    /** Retire une recette des favoris (DELETE). */
    private function removeFavorite(int $userId, int $recipeId): void
    {
        $r = $this->user->removeFavorite($userId, $recipeId);
        Api::json($r, $r['success'] ? 200 : 404);
    }
}
