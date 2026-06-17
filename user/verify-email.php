<?php
/**
 * verify-email.php — Confirmation d'adresse e-mail
 * ================================================
 *
 * L'utilisateur arrive ici en cliquant sur le lien reçu à l'inscription :
 *   verify-email.php?token=XXXX
 *
 * On vérifie le jeton ; s'il est valide, on passe email_verified à 1 et
 * on affiche un message de succès. Sinon, un message d'erreur.
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

use App\Models\User;

$token = (string) ($_GET['token'] ?? '');
$userModel = new User();

// On cherche l'utilisateur associé au jeton (valide, non expiré, non utilisé).
$user = $token !== '' ? $userModel->findByVerificationToken($token) : null;

$verified = false;
if ($user) {
    // Jeton valide → on confirme l'adresse et on consomme le jeton.
    $verified = $userModel->markEmailVerified((int) $user['id'], $token);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vérification d'e-mail — Planificateur de Repas</title>
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
            padding:2.5rem 2rem; box-shadow:var(--shadow-lg); text-align:center;
            position:relative; z-index:1; }
        .verify-icon { font-size:3rem; margin-bottom:0.5rem; }
    </style>
</head>
<body>
    <main class="card-auth">
        <?php if ($verified): ?>
            <div class="verify-icon" aria-hidden="true">✅</div>
            <h1 class="auth-heading" style="font-size:1.6rem;">Adresse confirmée&nbsp;!</h1>
            <p class="auth-subheading">
                Merci&nbsp;<?= htmlspecialchars($user['firstname'], ENT_QUOTES) ?>, votre adresse
                e-mail est désormais vérifiée. Vous pouvez vous connecter en toute sérénité.
            </p>
            <a href="/login.php" class="btn-main" style="text-decoration:none;display:inline-block;margin-top:1rem;">
                <span class="btn-inner"><span>Se connecter</span><span aria-hidden="true">→</span></span>
            </a>
        <?php else: ?>
            <div class="verify-icon" aria-hidden="true">⚠️</div>
            <h1 class="auth-heading" style="font-size:1.6rem;">Lien invalide</h1>
            <p class="auth-subheading">
                Ce lien de vérification est invalide, a expiré, ou a déjà été utilisé.
            </p>
            <a href="/login.php" class="auth-switch" style="display:inline-block;margin-top:0.5rem;">← Retour à la connexion</a>
        <?php endif; ?>
    </main>
    <script src="/assets/js/theme.js"></script>
</body>
</html>
