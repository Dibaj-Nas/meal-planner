<?php
require_once __DIR__ . '/../config/config.php';

use App\Core\Security;
use App\Middleware\AuthMiddleware;

AuthMiddleware::requireGuest('index.php');
$csrfToken = Security::csrfToken();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Connexion au Planificateur de Repas">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
    <title>Se connecter — Planificateur de Repas</title>
    <link rel="icon" href="/assets/img/PR.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,700;1,400&family=Commissioner:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/auth.css">
    <link rel="stylesheet" href="/assets/css/theme.css">
    <script>
        (function(){var t=localStorage.getItem('theme')||'light';if(t==='system')t=matchMedia('(prefers-color-scheme:dark)').matches?'dark':'light';document.documentElement.setAttribute('data-theme',t);}());
    </script>
</head>
<body>

    <!-- ══ PANNEAU GAUCHE — Formulaire ══ -->
    <section class="auth-panel" aria-labelledby="login-heading">

        <div class="brand-logo" aria-hidden="true"><img src="/assets/img/logoPlanificateurDeRepas.png" alt="logo de Planificateur de Repas" width="100"></div>
        

        <h1 class="auth-heading" id="login-heading">
            Bon retour<br>parmi nous
        </h1>
        <p class="auth-subheading">
            Connectez-vous pour accéder à vos menus<br>et continuer à bien manger.
        </p>

        <?php /* Bandeau affiché après une réinitialisation réussie (redirigé depuis reset-password.php) */ ?>
        <?php if (($_GET['reset'] ?? '') === 'success'): ?>
            <div class="success-msg" role="status"
                 style="display:flex;gap:.5rem;align-items:center;background:rgba(74,144,96,.12);
                        border:1px solid rgba(74,144,96,.4);color:#2f6b45;padding:.75rem 1rem;
                        border-radius:10px;margin-bottom:1rem;font-size:.9rem;">
                <span aria-hidden="true">✅</span>
                <span>Mot de passe réinitialisé. Vous pouvez vous connecter.</span>
            </div>
        <?php endif; ?>

        <form class="auth-form" id="login-form" novalidate>

            <!-- Zone d'erreur (masquée par défaut) -->
            <div class="error-msg" id="login-error" role="alert" aria-live="assertive">
                <span aria-hidden="true">⚠️</span>
                <span id="error-text"></span>
            </div>

            <!-- Champ e-mail -->
            <div class="form-group">
                <label for="email">
                    Adresse e-mail <span aria-hidden="true">*</span>
                </label>
                <div class="input-wrapper">
                    <span class="icon" aria-hidden="true">✉️</span>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        required
                        aria-required="true"
                        autocomplete="email"
                        placeholder="vous@exemple.fr"
                    >
                </div>
            </div>

            <!-- Champ mot de passe -->
            <div class="form-group">
                <label for="password">
                    Mot de passe <span aria-hidden="true">*</span>
                </label>
                <div class="input-wrapper">
                    <span class="icon" aria-hidden="true">🔒</span>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        required
                        aria-required="true"
                        autocomplete="current-password"
                        placeholder="••••••••"
                    >
                    <button
                        type="button"
                        class="toggle-pw"
                        id="toggle-pw"
                        aria-label="Afficher ou masquer le mot de passe"
                        aria-pressed="false"
                    >👁️</button>
                </div>
            </div>

            <!-- Options : Se souvenir + Mot de passe oublié -->
            <div class="form-options">
                <label class="checkbox-label">
                    <input type="checkbox" id="remember" name="remember">
                    Se souvenir de moi
                </label>
                <a href="/forgot-password.php" class="forgot-link">Mot de passe oublié ?</a>
            </div>

            <!-- Bouton de connexion -->
            <button type="submit" class="btn-main" id="submit-btn">
                <span class="btn-inner">
                    <span>Se connecter</span>
                    <span aria-hidden="true">→</span>
                </span>
            </button>

            <!-- Lien vers l'inscription -->
            <p class="auth-switch">
                Nouveau ici ?
                <a href="/register.php">Créer un compte</a>
            </p>

        </form>

    </section>

    <!-- ══ PANNEAU DROIT — Héro décoratif ══ -->
    <aside class="hero-panel slide-from-right" aria-hidden="true"
           style="background: var(--secondary-color);">
        <div class="hero-deco"></div>
        <span class="hero-tag">✨ Application gratuite</span>
        <h2 class="hero-title">
            Optimisez votre<br>quotidien,
            <em>une<br>assiette à la fois</em>
        </h2>
        <p class="hero-desc">
            Générez des menus hebdomadaires équilibrés et économiques,
            adaptés à vos goûts et votre budget.
        </p>
        <ul class="hero-features">
            <li><span class="feature-dot"></span> Menus générés automatiquement</li>
            <li><span class="feature-dot"></span> Calcul du budget en temps réel</li>
            <li><span class="feature-dot"></span> Apports nutritionnels suivis</li>
            <li><span class="feature-dot"></span> Export PDF &amp; calendrier ICS</li>
        </ul>
    </aside>

    <script src="/assets/js/api.js"></script>
    <script src="/assets/js/auth.js"></script>
    <script src="/assets/js/theme.js"></script>
</body>
</html>