<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Api;
use App\Core\Security;
use App\Middleware\AuthMiddleware;
use App\Models\User;
use App\Services\MailService;

class AuthController
{
    private User $userModel;

    public function __construct()
    {
        $this->userModel = new User();
    }

    public function handle(string $method, string $action): void
    {
        match (true) {
            $method === 'POST' && $action === 'login'    => $this->login(),
            $method === 'POST' && $action === 'register' => $this->register(),
            $method === 'POST' && $action === 'logout'   => $this->logout(),
            $method === 'GET'  && $action === 'me'       => $this->me(),
            default => Api::json(['success' => false, 'error' => 'Route auth introuvable'], 404),
        };
    }

    private function login(): void
    {
        $body = Api::body();
        if (!Api::verifyCsrf($body)) {
            Api::json(['success' => false, 'error' => 'Token de sécurité invalide.'], 403);
        }

        $email    = trim((string) ($body['email'] ?? ''));
        $password = (string) ($body['password'] ?? '');

        if (!Security::isValidEmail($email) || $password === '') {
            Api::json(['success' => false, 'error' => 'E-mail ou mot de passe manquant.'], 422);
        }

        $user = $this->userModel->findByEmail($email);
        $hash = $user['password'] ?? '$2y$12$invalidhashfortimingattack00000000000000000000000';
        $valid = $user && Security::verifyPassword($password, $hash);

        if (!$valid) {
            Api::json(['success' => false, 'error' => 'E-mail ou mot de passe incorrect.'], 401);
        }

        AuthMiddleware::login($user);
        Api::json([
            'success' => true,
            'message' => 'Connexion réussie.',
            'data'    => ['user' => $this->publicUser($user)],
        ]);
    }

    private function register(): void
    {
        $body = Api::body();
        if (!Api::verifyCsrf($body)) {
            Api::json(['success' => false, 'error' => 'Token de sécurité invalide.'], 403);
        }

        $firstname = trim((string) ($body['firstname'] ?? ''));
        $lastname  = trim((string) ($body['lastname'] ?? ''));
        $email     = trim((string) ($body['email'] ?? ''));
        $password  = (string) ($body['password'] ?? '');
        $confirm   = (string) ($body['confirm-password'] ?? $body['confirm_password'] ?? $password);

        $error = $this->validateRegistration($firstname, $lastname, $email, $password, $confirm);
        if ($error) {
            Api::json(['success' => false, 'error' => $error], 422);
        }

        try {
            $userId = $this->userModel->create(compact('firstname', 'lastname', 'email', 'password'));
        } catch (\Throwable $e) {
            $msg = APP_DEBUG ? $e->getMessage() : 'Impossible de créer le compte. Vérifiez que la base de données est configurée.';
            Api::json(['success' => false, 'error' => $msg], 500);
        }

        if (!$userId) {
            Api::json(['success' => false, 'error' => 'Cette adresse e-mail est déjà utilisée.'], 409);
        }

        // ── Envoi des e-mails (bienvenue + vérification d'adresse) ──
        // On génère un jeton aléatoire, on le stocke (haché) en base, puis on
        // l'envoie par e-mail. Si l'envoi échoue, le compte reste créé : on
        // n'interrompt pas l'inscription pour un problème de messagerie.
        try {
            $token = Security::generateToken();              // jeton en clair (URL)
            $this->userModel->saveVerificationToken($userId, $token);
            MailService::sendWelcome($email, $firstname);
            MailService::sendVerification($email, $firstname, $token);
        } catch (\Throwable $e) {
            error_log('[AuthController::register] Envoi e-mail échoué : ' . $e->getMessage());
        }

        $user = $this->userModel->findById($userId);
        Api::json([
            'success' => true,
            'message' => 'Compte créé avec succès. Vérifiez votre boîte mail pour confirmer votre adresse.',
            'data'    => ['user' => $this->publicUser($user)],
        ], 201);
    }

    private function logout(): void
    {
        AuthMiddleware::logout();
        Api::json(['success' => true, 'message' => 'Déconnexion réussie.']);
    }

    private function me(): void
    {
        AuthMiddleware::requireAuth(true);
        $user = AuthMiddleware::currentUser();
        Api::json(['success' => true, 'data' => ['user' => $user]]);
    }

    private function publicUser(?array $user): array
    {
        return [
            'id'        => (int) ($user['id'] ?? 0),
            'firstname' => $user['firstname'] ?? '',
            'lastname'  => $user['lastname'] ?? '',
            'email'     => $user['email'] ?? '',
        ];
    }

    private function validateRegistration(
        string $firstname, string $lastname,
        string $email, string $password, string $confirm
    ): ?string {
        if ($firstname === '') return 'Le prénom est requis.';
        if ($lastname === '')  return 'Le nom est requis.';
        if (!Security::isValidEmail($email)) return 'Adresse e-mail invalide.';
        if (!Security::isStrongPassword($password)) {
            return 'Mot de passe trop faible (8 car. min, 1 majuscule, 1 chiffre, 1 caractère spécial).';
        }
        if ($password !== $confirm) return 'Les mots de passe ne correspondent pas.';
        return null;
    }
}
