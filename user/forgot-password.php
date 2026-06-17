<?php
/**
 * forgot-password.php — Demande de réinitialisation de mot de passe
 * ================================================================
 *
 * Étape 1 du flux « Mot de passe oublié ? » (accessible depuis login.php).
 *
 * Fonctionnement :
 *   - GET  : on affiche un formulaire demandant l'adresse e-mail.
 *   - POST : on cherche le compte ; s'il existe, on génère un jeton,
 *            on le stocke (haché) et on envoie l'e-mail de réinitialisation.
 *
 * Sécurité importante : on affiche TOUJOURS le même message de succès,
 * que l'e-mail existe ou non. Sinon, un attaquant pourrait deviner quelles
 * adresses ont un compte (énumération de comptes).
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

use App\Core\Security;
use App\Middleware\AuthMiddleware;
use App\Models\User;
use App\Services\MailService;

// Un utilisateur déjà connecté n'a rien à faire ici → on le renvoie à l'app.
AuthMiddleware::requireGuest('index.php');

$csrfToken = Security::csrfToken();
$done    = false;   // passe à true une fois la demande traitée
$error   = '';

// ── Traitement du formulaire (POST) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Vérification du token CSRF (anti-falsification de requête)
    if (!Security::verifyCsrf($_POST['csrf_token'] ?? '')) {
        $error = 'Token de sécurité invalide. Rechargez la page et réessayez.';
    } else {
        $email = trim((string) ($_POST['email'] ?? ''));

        if (!Security::isValidEmail($email)) {
            $error = 'Veuillez saisir une adresse e-mail valide.';
        } else {
            // 2. On cherche l'utilisateur. S'il existe → on envoie l'e-mail.
            $user = (new User())->findByEmail($email);
            if ($user) {
                $token = Security::generateToken();           // jeton en clair
                (new User())->saveResetToken((int) $user['id'], $token);
                MailService::sendPasswordReset($user['email'], $user['firstname'], $token);
            }
            // 3. Message identique dans tous les cas (anti-énumération).
            $done = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mot de passe oublié — Planificateur de Repas</title>
    <link rel="icon" href="/assets/img/PR.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,700;1,400&family=Commissioner:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/auth.css">
    <link rel="stylesheet" href="/assets/css/theme.css">
    <script>
        (function(){var t=localStorage.getItem('theme')||'light';if(t==='system')t=matchMedia('(prefers-color-scheme:dark)').matches?'dark':'light';document.documentElement.setAttribute('data-theme',t);}());
    </script>
    <!-- Mise en page centrée (on neutralise la grille 2 colonnes d'auth.css) -->
    <style>
        body { display:flex; align-items:center; justify-content:center; padding:1.5rem; }
        .card-auth { width:100%; max-width:440px; background:var(--primary-color);
            border:1px solid var(--border, rgba(84,67,73,.2)); border-radius:16px;
            padding:2.5rem 2rem; box-shadow:var(--shadow-lg); position:relative; z-index:1; }
        .card-auth .brand-logo { text-align:center; margin-bottom:1rem; }
    </style>
</head>
<body>
    <main class="card-auth">
        <div class="brand-logo" aria-hidden="true">
            <img src="/assets/img/logoPlanificateurDeRepas.png" alt="" width="80">
        </div>

        <?php if ($done): ?>
            <!-- Écran de confirmation (toujours affiché après une demande valide) -->
            <h1 class="auth-heading" style="font-size:1.6rem;">Vérifiez vos e-mails</h1>
            <p class="auth-subheading">
                Si un compte est associé à cette adresse, vous recevrez un lien
                pour réinitialiser votre mot de passe. Pensez à vérifier vos spams.
            </p>
            <p class="auth-switch"><a href="/login.php">← Retour à la connexion</a></p>

        <?php else: ?>
            <!-- Formulaire de demande -->
            <h1 class="auth-heading" id="fp-heading" style="font-size:1.6rem;">Mot de passe oublié&nbsp;?</h1>
            <p class="auth-subheading">
                Saisissez votre adresse e-mail : nous vous enverrons un lien
                pour définir un nouveau mot de passe.
            </p>

            <?php if ($error): ?>
                <div class="error-msg" role="alert" style="display:flex;">
                    <span aria-hidden="true">⚠️</span> <span><?= htmlspecialchars($error, ENT_QUOTES) ?></span>
                </div>
            <?php endif; ?>

            <form class="auth-form" method="POST" action="/forgot-password.php" novalidate>
                <!-- Jeton CSRF : prouve que le formulaire vient bien de notre site -->
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">

                <div class="form-group">
                    <label for="email">Adresse e-mail <span aria-hidden="true">*</span></label>
                    <div class="input-wrapper">
                        <span class="icon" aria-hidden="true">✉️</span>
                        <input type="email" id="email" name="email" required
                               autocomplete="email" placeholder="vous@exemple.fr">
                    </div>
                </div>

                <button type="submit" class="btn-main">
                    <span class="btn-inner"><span>Envoyer le lien</span><span aria-hidden="true">→</span></span>
                </button>

                <p class="auth-switch"><a href="/login.php">← Retour à la connexion</a></p>
            </form>
        <?php endif; ?>
    </main>
    <script src="/assets/js/theme.js"></script>
</body>
</html>
