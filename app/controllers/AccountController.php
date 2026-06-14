<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Api;
use App\Core\Security;
use App\Middleware\AuthMiddleware;
use App\Models\User;

class AccountController
{
    private User $user;

    public function __construct()
    {
        $this->user = new User();
    }

    public function handle(string $method, ?string $action, ?int $id): void
    {
        AuthMiddleware::requireAuth(true);
        $userId = (int) AuthMiddleware::currentUserId();
        $body   = Api::body();

        match (true) {
            $method === 'GET'  && $action === 'profile'   => $this->getProfile($userId),
            $method === 'PUT'  && $action === 'profile'   => $this->updateProfile($userId, $body),
            $method === 'PUT'  && $action === 'password'  => $this->changePassword($userId, $body),
            $method === 'GET'  && $action === 'settings'  => $this->getSettings($userId),
            $method === 'PUT'  && $action === 'settings'  => $this->saveSettings($userId, $body),
            $method === 'GET'  && $action === 'favorites' => $this->getFavorites($userId),
            $method === 'DELETE' && $action === 'favorites' && $id !== null
                => $this->removeFavorite($userId, $id),
            default => Api::json(['success' => false, 'error' => 'Route compte introuvable'], 404),
        };
    }

    private function getProfile(int $userId): void
    {
        $profile = $this->user->getProfile($userId);
        if (!$profile) {
            Api::json(['success' => false, 'error' => 'Profil introuvable'], 404);
        }
        Api::json(['success' => true, 'data' => $profile]);
    }

    private function updateProfile(int $userId, array $body): void
    {
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
        if ($r['success']) {
            $_SESSION['user']['firstname'] = $firstname;
            $_SESSION['user']['lastname']  = $lastname;
            $_SESSION['user']['email']     = $email;
        }
        Api::json($r, $r['success'] ? 200 : 422);
    }

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

    private function getSettings(int $userId): void
    {
        Api::json(['success' => true, 'data' => $this->user->getUserSettings($userId)]);
    }

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

    private function getFavorites(int $userId): void
    {
        $favorites = $this->user->getFavorites($userId);
        $type = $_GET['meal_type'] ?? '';

        if ($type !== '') {
            $favorites = array_values(array_filter(
                $favorites,
                fn($r) => ($r['meal_type'] ?? '') === $type
            ));
        }

        Api::json(['success' => true, 'data' => $favorites]);
    }

    private function removeFavorite(int $userId, int $recipeId): void
    {
        $r = $this->user->removeFavorite($userId, $recipeId);
        Api::json($r, $r['success'] ? 200 : 404);
    }
}
