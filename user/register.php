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
    <meta name="description" content="Créer un compte sur le Planificateur de Repas">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
    <title>Créer un compte — Planificateur de Repas</title>
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

    <!-- ══ PANNEAU GAUCHE — Héro décoratif ══ -->
    <aside class="hero-panel slide-from-left" aria-hidden="true"
           style="background: var(--title-color); order: -1;">
        <div class="hero-accent"></div>
        <span class="hero-tag">🌿 Commencez gratuitement</span>
        <h2 class="hero-title">
            Bien manger,<br>
            <em>sans y penser<br>chaque jour</em>
        </h2>
        <p class="hero-desc">
            Créez votre compte en quelques secondes et commencez
            à planifier des menus qui vous ressemblent.
        </p>
        <ol class="steps" aria-hidden="true">
            <li class="step">
                <span class="step-num">1</span>
                <span class="step-text">
                    <strong>Ajoutez vos ingrédients</strong>
                    Renseignez vos stocks et leurs prix
                </span>
            </li>
            <li class="step">
                <span class="step-num">2</span>
                <span class="step-text">
                    <strong>Créez vos recettes</strong>
                    Importez ou composez vos plats préférés
                </span>
            </li>
            <li class="step">
                <span class="step-num">3</span>
                <span class="step-text">
                    <strong>Générez votre menu</strong>
                    En un clic, un planning équilibré pour la semaine
                </span>
            </li>
        </ol>
    </aside>

    <!-- ══ PANNEAU DROIT — Formulaire ══ -->
    <section class="auth-panel" aria-labelledby="register-heading">

        <div class="brand">
            <div class="brand-logo" aria-hidden="true">🍽️</div>
            <span class="brand-name">Planificateur de Repas</span>
        </div>

        <h1 class="auth-heading" id="register-heading">
            Créer votre compte
        </h1>
        <p class="auth-subheading">
            Rejoignez-nous et organisez vos repas<br>facilement chaque semaine.
        </p>

        <form class="auth-form" id="register-form" novalidate>

            <!-- Zone d'erreur (masquée par défaut) -->
            <div class="error-msg" id="register-error" role="alert" aria-live="assertive">
                <span aria-hidden="true">⚠️</span>
                <span id="error-text"></span>
            </div>

            <!-- Zone de succès (masquée par défaut) -->
            <div class="success-msg" id="register-success" role="status" aria-live="polite">
                <span aria-hidden="true">✅</span>
                <span>Compte créé avec succès ! Redirection…</span>
            </div>

            <!-- Prénom + Nom (deux colonnes) -->
            <div class="form-row">
                <div class="form-group">
                    <label for="firstname">
                        Prénom <span aria-hidden="true">*</span>
                    </label>
                    <div class="input-wrapper">
                        <span class="icon" aria-hidden="true">👤</span>
                        <input
                            type="text"
                            id="firstname"
                            name="firstname"
                            required
                            aria-required="true"
                            autocomplete="given-name"
                            placeholder="Marie"
                        >
                    </div>
                </div>
                <div class="form-group">
                    <label for="lastname">
                        Nom <span aria-hidden="true">*</span>
                    </label>
                    <div class="input-wrapper">
                        <span class="icon" aria-hidden="true">👤</span>
                        <input
                            type="text"
                            id="lastname"
                            name="lastname"
                            required
                            aria-required="true"
                            autocomplete="family-name"
                            placeholder="Dupont"
                        >
                    </div>
                </div>
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

            <!-- Champ mot de passe + indicateur de force -->
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
                        autocomplete="new-password"
                        placeholder="Minimum 8 caractères"
                        aria-describedby="pw-strength-label"
                    >
                    <button
                        type="button"
                        class="toggle-pw"
                        id="toggle-pw"
                        aria-label="Afficher ou masquer le mot de passe"
                        aria-pressed="false"
                    >👁️</button>
                </div>
                <!-- Indicateur de force du mot de passe -->
                <div class="pw-strength" aria-live="polite">
                    <div class="strength-bar" aria-hidden="true">
                        <div class="strength-segment" id="seg1"></div>
                        <div class="strength-segment" id="seg2"></div>
                        <div class="strength-segment" id="seg3"></div>
                        <div class="strength-segment" id="seg4"></div>
                    </div>
                    <span class="strength-label" id="pw-strength-label"></span>
                </div>
                <!-- Règles du mot de passe -->
                <ul class="pw-rules" id="pw-rules" aria-label="Critères du mot de passe">
                    <li class="pw-rule" id="rule-length"  data-rule="length">
                        <span class="rule-icon" aria-hidden="true"></span>
                        Minimum 8 caractères
                    </li>
                    <li class="pw-rule" id="rule-upper"   data-rule="upper">
                        <span class="rule-icon" aria-hidden="true"></span>
                        Au moins une lettre majuscule
                    </li>
                    <li class="pw-rule" id="rule-lower"   data-rule="lower">
                        <span class="rule-icon" aria-hidden="true"></span>
                        Au moins une lettre minuscule
                    </li>
                    <li class="pw-rule" id="rule-digit"   data-rule="digit">
                        <span class="rule-icon" aria-hidden="true"></span>
                        Au moins un chiffre
                    </li>
                    <li class="pw-rule" id="rule-special" data-rule="special">
                        <span class="rule-icon" aria-hidden="true"></span>
                        Au moins un caractère spécial (!@#$%…)
                    </li>
                </ul>
            </div>

            <!-- Confirmation mot de passe -->
            <div class="form-group">
                <label for="confirm-password">
                    Confirmer le mot de passe <span aria-hidden="true">*</span>
                </label>
                <div class="input-wrapper">
                    <span class="icon" aria-hidden="true">🔐</span>
                    <input
                        type="password"
                        id="confirm-password"
                        name="confirm-password"
                        required
                        aria-required="true"
                        autocomplete="new-password"
                        placeholder="Répétez votre mot de passe"
                    >
                    <button
                        type="button"
                        class="toggle-pw"
                        id="toggle-pw2"
                        aria-label="Afficher ou masquer la confirmation"
                        aria-pressed="false"
                    >👁️</button>
                </div>
                <span class="field-hint" id="confirm-hint" aria-live="polite"></span>
            </div>

            <!-- Acceptation des CGU -->
            <div class="form-group">
                <label class="cgu-label">
                    <input type="checkbox" id="cgu" name="cgu" required aria-required="true">
                    J'accepte les <a href="/ConditionsDeUtilisation.html">Conditions d'utilisation</a>
                    et la <a href="/PolitiqueDeConfidentialite.html">Politique de confidentialité</a>
                </label>
            </div>

            <!-- Bouton d'inscription -->
            <button type="submit" class="btn-main btn-green" id="submit-btn">
                <span class="btn-inner">
                    <span>Créer mon compte</span>
                    <span aria-hidden="true">→</span>
                </span>
            </button>

            <!-- Lien vers la connexion -->
            <p class="auth-switch">
                Déjà un compte ?
                <a href="/login.php">Se connecter</a>
            </p>

            <p class="contact-info">
                Pour nous contacter, veuillez remplir le formulaire de contact.
                <a href="/Contact.html">Contact</a>
            </p>

        </form>

    </section>

    <script src="/assets/js/api.js"></script>
    <script src="/assets/js/auth.js"></script>
    <script src="/assets/js/theme.js"></script>
</body>
</html>