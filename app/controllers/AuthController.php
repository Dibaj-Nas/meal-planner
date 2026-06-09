<?php
namespace app\controllers;

use app\core\Security;
// use App\Middleware\AuthMiddleware; = TO DO 
// use App\Models\User; = TO DO

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;

class AuthController
{
    private User $userModel;

    public function __construct()
    {
        $this->userModel = new User();
    }

    // affichage des pages
    public function showLogin(): void
    {
        AuthMiddleware::requireGuest('/');
        require_once ROOT_PATH . '/login.php';
    }

    public function showRegister(): void
    {
        AuthMiddleware::requireGuest('/');
        require_once ROOT_PATH . '/register.php';
    }

    public function showForgotPassword(): void
    {
        AuthMiddleware::requireGuest('/');
        // require_once ROOT_PATH . '/forgot-password.php'; // à créer
        echo '<p>Formulaire mot de passe oublié (page à créer).</p>';
    }

    public function showResetPassword(array $params = []): void
    {
        $token = $params['token'] ?? ($_GET['token'] ?? '');
        $user  = $token ? $this->userModel->findByResetToken($token) : null;

        if (!$user) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Lien invalide ou expiré.'];
            header('Location: /login');
            exit;
        }
        // require_once ROOT_PATH . '/reset-password.php'; // à créer
        echo '<p>Formulaire reset — token : ' . htmlspecialchars($token) . '</p>';
    }


    // Traitements POST

    /**
     * Traite la connexion (POST /login).
     * Répond en JSON si la requête est AJAX, sinon redirige.
     */
    public function login(): void
    {
        $isAjax = $this->isAjax();

        if (!$this->verifyCsrf()) {
            $this->respond(false, 'Token de sécurité invalide.', 403, $isAjax, '/login');
            return;
        }

        $email    = trim($_POST['email']    ?? '');
        $password =      $_POST['password'] ?? '';

        if (!Security::isValidEmail($email) || empty($password)) {
            $this->respond(false, 'E-mail ou mot de passe manquant.', 422, $isAjax, '/login');
            return;
        }

        $user = $this->userModel->findByEmail($email);

        /* Même délai si l'utilisateur n'existe pas → protection timing */
        $hash    = $user['password_hash'] ?? '$2y$10$invalidhashfortimingattack';
        $isValid = Security::verifyPassword($password, $hash);

        if (!$user || !$isValid) {
            $this->respond(false, 'E-mail ou mot de passe incorrect.', 401, $isAjax, '/login');
            return;
        }

        AuthMiddleware::login($user);

        $redirect = $_SESSION['redirect_after_login'] ?? '/';
        unset($_SESSION['redirect_after_login']);

        $this->respond(true, 'Connexion réussie.', 200, $isAjax, $redirect, [
            'user' => [
                'id'        => $user['id'],
                'firstname' => $user['firstname'],
                'lastname'  => $user['lastname'],
                'email'     => $user['email'],
            ],
        ]);
    }

    /**
     * Traite l'inscription (POST /register).
     */
    public function register(): void
    {
        $isAjax = $this->isAjax();

        if (!$this->verifyCsrf()) {
            $this->respond(false, 'Token de sécurité invalide.', 403, $isAjax, '/register');
            return;
        }

        $firstname = trim($_POST['firstname']        ?? '');
        $lastname  = trim($_POST['lastname']         ?? '');
        $email     = trim($_POST['email']            ?? '');
        $password  =      $_POST['password']         ?? '';
        $confirm   =      $_POST['confirm-password'] ?? '';

        $error = $this->validateRegistration($firstname, $lastname, $email, $password, $confirm);
        if ($error) {
            $this->respond(false, $error, 422, $isAjax, '/register');
            return;
        }

        $userId = $this->userModel->create([
            'firstname' => $firstname,
            'lastname'  => $lastname,
            'email'     => $email,
            'password'  => $password,
        ]);

        if (!$userId) {
            $this->respond(false, 'Cette adresse e-mail est déjà utilisée.', 409, $isAjax, '/register');
            return;
        }

        /* E-mail de bienvenue via PHPMailer */
        $this->sendWelcomeEmail($email, $firstname);

        $this->respond(true, 'Compte créé avec succès !', 201, $isAjax, '/login');
    }

    /** Déconnecte l'utilisateur (POST /logout). */
    public function logout(): void
    {
        AuthMiddleware::logout();
        header('Location: /login');
        exit;
    }

    /**
     * Envoie un lien de réinitialisation (POST /forgot-password).
     * Utilise PHPMailer.
     */
    public function forgotPassword(): void
    {
        $isAjax = $this->isAjax();

        if (!$this->verifyCsrf()) {
            $this->respond(false, 'Token de sécurité invalide.', 403, $isAjax);
            return;
        }

        $email = trim($_POST['email'] ?? '');

        if (!Security::isValidEmail($email)) {
            $this->respond(false, 'Adresse e-mail invalide.', 422, $isAjax);
            return;
        }

        $user = $this->userModel->findByEmail($email);

        /* Toujours répondre "succès" → ne révèle pas si l'e-mail existe */
        if ($user) {
            $token = Security::generateToken();
            $this->userModel->saveResetToken($user['id'], $token);
            $this->sendPasswordResetEmail($user['email'], $user['firstname'], $token);
        }

        $this->respond(
            true,
            'Si un compte existe pour cet e-mail, un lien de réinitialisation a été envoyé.',
            200,
            $isAjax
        );
    }

    /** Réinitialise le mot de passe (POST /reset-password). */
    public function resetPassword(): void
    {
        $isAjax = $this->isAjax();

        if (!$this->verifyCsrf()) {
            $this->respond(false, 'Token de sécurité invalide.', 403, $isAjax);
            return;
        }

        $token    = trim($_POST['token']            ?? '');
        $password =      $_POST['password']         ?? '';
        $confirm  =      $_POST['confirm-password'] ?? '';
        $user     = $token ? $this->userModel->findByResetToken($token) : null;

        if (!$user) {
            $this->respond(false, 'Lien invalide ou expiré.', 400, $isAjax, '/login');
            return;
        }

        if ($password !== $confirm) {
            $this->respond(false, 'Les mots de passe ne correspondent pas.', 422, $isAjax);
            return;
        }

        if (!Security::isStrongPassword($password)) {
            $this->respond(false, 'Mot de passe trop faible (8 car. min, 1 majuscule, 1 chiffre).', 422, $isAjax);
            return;
        }

        $this->userModel->updatePassword($user['id'], $password);
        $this->respond(true, 'Mot de passe réinitialisé. Connectez-vous.', 200, $isAjax, '/login');
    }

    // PHPMailer
    /** créer et configure une instance PHPMailer.
     * phpmailer est disponible grâce au composer autoload chargé dans config.php.
     */
    private function createMailer(): PHPMailer
    {
        $mail = new PHPMailer(true); //true = lève des exceptions

        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USERNAME;
        $mail->Password   = MAIL_PASSWORD;
        $mail->SMTPSecure = MAIL_ENCRYPTION; // 'tls'
        $mail->Port       = MAIL_PORT;       // 587
        $mail->CharSet    = 'UTF-8';
        $mail->setLanguage('fr', PHPMAILER_VENDOR . '/phpmailer/phpmailer/language/');

        $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);

        return $mail;
    }

    /**
     * envios l'e-mail de bienvenue après inscription.
     */
    private function sendWelcomeEmail(string $email, string $firstname): void
    {
        try {
            $mail = $this->createMailer();
            $mail->addAddress($mail, $firstname);
            $mail->isHTML(true);
            $mail->Subject = 'Bienvenue sur le Planificateur de Repas !';
            $mail->Body = $this->emailHtml('Welcome', [
                'firstname' => htmlspecialchars($firstname),
                'app_url' => APP_URL,
            ]);
            $mail->AltBody = "Bonjour {$firstname}, \n\nBienvenue !
            Connectez-vous : " . APP_URL . "/login";
            $mail->send();
        } catch (MailException $e) {
            // inscription réussit même si l'email échoue.
            error_log('[AuthController] E-mail échoué :' . $e->getMessage());
        }
    }

    // Envoie le lien de réinitialisation de mot de passe.
    private function sendPasswordResetEmail(string $email, string $firstname, string $token): void
    {
        try {
            $resetUrl = APP_URL . "/reset-password?token=" . urlencode($token);
            
            $mail = $this->createMailer();
            $mail->addAddress($email, $firstname);
            $mail->isHTML(true);
            $mail->Subject = 'Réinitialisation de votre mot de passe';
            $mail->Body = $this->emailHtml('Password-reset', [
                'firstname' => htmlspecialchars($firstname),
                'reset_url' => $resetUrl,
            ]);
            $mail->AltBody = "Bonjour {$firstname}, \n\nRéinitialisez votre mot de passe : {$resetUrl}\n\nValable 1 heure.";
            $mail->send();
        } catch (MailException $e) {
            error_log('[AuthController] E-mail reset échoué : ' . $e->getMessage());
        }
    }

    // le corps HTML d'un e-mail
    private function emailHtml(string $template, array $vars): string
    {
        $primary = '#576238';
        $red     = '#CE2A2A';
        $bg      = '#F0EADC';

        $content = match ($template) {
            'welcome' => "
                <h2 style='color:{$primary}'>Bonjour {$vars['firstname']} ! 👋</h2>
                <p>Votre compte a bien été créé. Commencez à planifier vos repas.</p>
                <p style='text-align:center;margin:2rem 0;'>
                    <a href='{$vars['app_url']}/login'
                       style='background:{$primary};color:#fff;padding:.9rem 2rem;
                              border-radius:8px;text-decoration:none;font-weight:600;'>
                        Accéder à l'application →
                    </a>
                </p>",

            'password-reset' => "
                <h2 style='color:{$primary}'>Réinitialisation du mot de passe</h2>
                <p>Bonjour {$vars['firstname']},</p>
                <p>Cliquez ci-dessous pour créer un nouveau mot de passe.
                   Ce lien est valable <strong>1 heure</strong>.</p>
                <p style='text-align:center;margin:2rem 0;'>
                    <a href='{$vars['reset_url']}'
                       style='background:{$red};color:#fff;padding:.9rem 2rem;
                              border-radius:8px;text-decoration:none;font-weight:600;'>
                        Réinitialiser mon mot de passe →
                    </a>
                </p>
                <p style='font-size:.85rem;color:#999;'>
                    Si vous n'êtes pas à l'origine de cette demande, ignorez cet e-mail.
                </p>",

            default => '<p>Message automatique.</p>',
        };

        return "
        <div style='font-family:Commissioner,sans-serif;background:{$bg};padding:2rem;'>
          <div style='max-width:580px;margin:0 auto;background:#fff;border-radius:12px;overflow:hidden;'>
            <div style='background:{$primary};padding:2rem;text-align:center;'>
              <h1 style='color:#F3E7D9;font-family:Georgia,serif;margin:0;'>🍽️ Planificateur de Repas</h1>
            </div>
            <div style='padding:2rem;'>{$content}</div>
            <div style='background:{$bg};padding:1rem;text-align:center;font-size:.8rem;color:#999;'>
              © 2026 Planificateur de Repas
            </div>
          </div>
        </div>";

    }

    // Helpers

    private function isAjax(): bool
    {
        return ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') ===
        'XMLHttpRequest'
            || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/ json');
    }

    private function verifyCsrf(): bool 
    {
        $token = $_POST['csrf_token'] ??  $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        return Security::verifyCsrf($token);
    }

    private function respond(
        bool    $success,
        string  $message,
        int     $code = 200,
        bool    $isAjax = false,
        ?string $redirectTo = null,
        array   $extra = []
    ): void {
        if ($isAjax) {
            http_response_code($code);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra));
            exit;
        }

        $_SESSION['flash'] = [
            'type' => $success ? 'success' : 'error',
            'msg'  => $message,
        ];
        header('Location: ' . ($redirectTo ?? ($_SERVER['HTTP_REFERER'] ?? '/')));
        exit;
    }

    private function validateRegistration(
        string $firstname, string $lastname,
        string $email, string $password, string $confirm
    ): ?string {
        if (empty($firstname))                    return 'Le prénom est requis.';
        if (empty($lastname))                     return 'Le nom est requis.';
        if (!Security::isValidEmail($email))      return 'Adresse e-mail invalide.';
        if (!Security::isStrongPassword($password))
            return 'Mot de passe trop faible (8 car. min, 1 majuscule, 1 chiffre).';
        if ($password !== $confirm)               return 'Les mots de passe ne correspondent pas.';
        return null;
    }


}
?>