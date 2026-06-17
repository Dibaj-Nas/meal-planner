<?php
/**
 * reset-password.php — Définition d'un nouveau mot de passe
 * =========================================================
 *
 * Étape 2 du flux « Mot de passe oublié ? ». L'utilisateur arrive ici en
 * cliquant sur le lien reçu par e-mail : reset-password.php?token=XXXX
 *
 * Fonctionnement :
 *   - GET  : on vérifie que le jeton est valide, puis on affiche le formulaire.
 *   - POST : on revalide le jeton, on contrôle le mot de passe, on l'enregistre,
 *            on marque le jeton comme « utilisé », puis on redirige vers login.
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

use App\Core\Security;
use App\Middleware\AuthMiddleware;
use App\Models\User;

AuthMiddleware::requireGuest('index.php');

$csrfToken = Security::csrfToken();
$userModel = new User();

// Le token arrive en GET (lien e-mail) ou en POST (soumission du formulaire).
$token = (string) ($_POST['token'] ?? $_GET['token'] ?? '');
$error = '';

// ── On vérifie immédiatement la validité du jeton ──
// findByResetToken renvoie l'utilisateur si le jeton existe, n'est pas expiré
// et n'a pas déjà été utilisé. Sinon null.
$user = $token !== '' ? $userModel->findByResetToken($token) : null;
$tokenValid = $user !== null;

// ── Traitement du formulaire (POST) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tokenValid) {
    if (!Security::verifyCsrf($_POST['csrf_token'] ?? '')) {
        $error = 'Token de sécurité invalide. Réessayez.';
    } else {
        $new     = (string) ($_POST['new_password'] ?? '');
        $confirm = (string) ($_POST['confirm_password'] ?? '');

        if (!Security::isStrongPassword($new)) {
            $error = 'Mot de passe trop faible (8 caractères min., 1 majuscule, 1 chiffre, 1 caractère spécial).';
        } elseif ($new !== $confirm) {
            $error = 'Les deux mots de passe ne correspondent pas.';
        } else {
            // Tout est bon : on enregistre le nouveau mot de passe…
            $userModel->updatePassword((int) $user['id'], $new);
            // … et on invalide le jeton pour qu'il ne resserve pas.
            $userModel->markResetUsed($token);
            // Redirection vers la connexion avec un indicateur de succès.
            header('Location: /login.php?reset=success');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nouveau mot de passe — Planificateur de Repas</title>
    <link rel="icon" href="/assets/img/PR.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,700;1,400&family=Commissioner:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/auth.css">
    <link rel="stylesheet" href="/assets/css/theme.css">
    <script>
        (function(){var t=localStorage.getItem('theme')||'light';if(t==='system')t=matchMedia('(prefers-color-scheme:dark)').matches?'dark':'light';document.documentElement.setAttribute('data-theme',t);}());
    </script>
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

        <?php if (!$tokenValid): ?>
            <!-- Jeton absent, expiré ou déjà utilisé -->
            <h1 class="auth-heading" style="font-size:1.6rem;">Lien invalide</h1>
            <p class="auth-subheading">
                Ce lien de réinitialisation est invalide ou a expiré.
                Veuillez refaire une demande.
            </p>
            <p class="auth-switch"><a href="/forgot-password.php">Demander un nouveau lien</a></p>

        <?php else: ?>
            <h1 class="auth-heading" style="font-size:1.6rem;">Nouveau mot de passe</h1>
            <p class="auth-subheading">Choisissez un mot de passe sûr pour votre compte.</p>

            <?php if ($error): ?>
                <div class="error-msg" role="alert" style="display:flex;">
                    <span aria-hidden="true">⚠️</span> <span><?= htmlspecialchars($error, ENT_QUOTES) ?></span>
                </div>
            <?php endif; ?>

            <form class="auth-form" method="POST" action="/reset-password.php" novalidate>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">
                <!-- On reposte le token pour pouvoir le revalider côté serveur -->
                <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES) ?>">

                <div class="form-group">
                    <label for="new_password">Nouveau mot de passe <span aria-hidden="true">*</span></label>
                    <div class="input-wrapper">
                        <span class="icon" aria-hidden="true">🔒</span>
                        <input type="password" id="new_password" name="new_password" required
                               autocomplete="new-password" placeholder="Minimum 8 caractères">
                    </div>
                    <small>8 caractères min., 1 majuscule, 1 chiffre, 1 caractère spécial.</small>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirmer <span aria-hidden="true">*</span></label>
                    <div class="input-wrapper">
                        <span class="icon" aria-hidden="true">🔐</span>
                        <input type="password" id="confirm_password" name="confirm_password" required
                               autocomplete="new-password" placeholder="Répétez le mot de passe">
                    </div>
                </div>

                <button type="submit" class="btn-main">
                    <span class="btn-inner"><span>Enregistrer</span><span aria-hidden="true">→</span></span>
                </button>
            </form>
        <?php endif; ?>
    </main>
    <script src="/assets/js/theme.js"></script>
</body>
</html>
