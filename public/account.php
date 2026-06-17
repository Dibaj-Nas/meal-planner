<?php
/**
 * account.php — Espace utilisateur (page unique)
 *
 * 4 onglets : Profil | Paramètres | Menus sauvegardés | Favoris
 *
 * Bootstrap via config.php (autoloader PSR-4, session sécurisée, CSRF).
 * Authentification via AuthMiddleware. Données chargées via le modèle User
 * et mises à jour côté client via Ajax (account.js + api.js).
 *
 * méthodes User utilisées :
 *   getProfile · updateProfile · changePassword
 *   getUserSettings · saveUserSettings
 *   getSavedMenus · deleteWeeklyMenu
 *   getFavorites · removeFavorite
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

use App\Core\Security;
use App\Middleware\AuthMiddleware;
use App\Models\User;

AuthMiddleware::requireAuth();

$user      = new User();
$userId    = (int) AuthMiddleware::currentUserId();
$csrfToken = Security::csrfToken();

$validTabs = ['profile', 'settings', 'saved-menus', 'favorites'];
$activeTab = $_GET['tab'] ?? 'profile';
if (!in_array($activeTab, $validTabs, true)) $activeTab = 'profile';

// Données initiales (rechargées aussi via Ajax)
$profile  = $user->getProfile($userId);
$settings = $user->getUserSettings($userId);
$menus    = ($activeTab === 'saved-menus') ? ($user->getSavedMenus($userId) ?? []) : [];
$favType  = Security::sanitize($_GET['meal_type'] ?? '');
$favorites= ($activeTab === 'favorites') ? ($user->getFavorites($userId) ?? []) : [];
if ($favType) {
    $favorites = array_values(array_filter($favorites, fn($r) => ($r['meal_type'] ?? '') === $favType));
}

/* ── Helpers ── */
$fn  = htmlspecialchars($profile['firstname'] ?? '');
$ln  = htmlspecialchars($profile['lastname']  ?? '');
$em  = htmlspecialchars($profile['email']     ?? '');
$ini = strtoupper(substr($profile['firstname'] ?? 'U', 0, 1) . substr($profile['lastname'] ?? '', 0, 1));
$memberSince = isset($profile['created_at'])
    ? (new DateTime($profile['created_at']))->format('d/m/Y')
    : '—';

$dietPrefs  = ['Tous', 'Végétarien', 'Vegan', 'Sans Porc'];
$mealTypes  = [
    'breakfast' => 'Petit-déjeuner',
    'lunch'     => 'Déjeuner',
    'dinner'    => 'Dîner',
];
$mealLabels = ['all' => 'Tous', 'breakfast' => 'Petit-déj', 'lunch' => 'Déjeuner', 'dinner' => 'Dîner'];
$themes     = ['light' => '☀️ Clair', 'dark' => '🌙 Sombre', 'system' => '🖥️ Système'];
$DAYS       = ['Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi','Dimanche'];
$MEALS      = ['Petit-déj','Déjeuner','Dîner'];

function tab_url(string $t): string {
    return '/account.php?tab=' . $t;
}
function sel(string $val, string $current): string {
    return $val === $current ? ' selected' : '';
}
function chk(bool $cond): string {
    return $cond ? ' checked' : '';
}
function esc(mixed $s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon compte — Planificateur de Repas</title>
    <meta name="csrf-token" content="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
    <link rel="icon" href="/assets/img/PR.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,700;1,400&family=Commissioner:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="/assets/css/theme.css">
    <script>
        (function(){var t=localStorage.getItem('theme')||'light';if(t==='system')t=matchMedia('(prefers-color-scheme:dark)').matches?'dark':'light';document.documentElement.setAttribute('data-theme',t);}());
    </script>
</head>
<body class="account-page">

<!--  EN-TÊTE  -->
<header class="account-header">
    <a href="/index.php" class="account-logo" aria-label="Retour à l'accueil">
        <img src="/assets/img/logoPlanificateurDeRepas.png" alt="Logo" width="36">
        <span>Planificateur de Repas</span>
    </a>
    <div style="display:flex;align-items:center;gap:0.75rem;">
        <button id="theme-toggle" type="button" class="theme-toggle-header" aria-label="Changer le thème" title="Mode sombre / clair">🌙</button>
        <a href="/index.php" class="account-back-btn">← Retour à l'application</a>
    </div>
</header>

<!--  HERO PROFIL -->
<div class="account-hero">
    <div class="account-avatar" aria-hidden="true"><?= $ini ?></div>
    <div>
        <h1 class="account-name"><?= $fn ?> <?= $ln ?></h1>
        <p class="account-meta">✉️ <?= $em ?> &nbsp;·&nbsp; 📅 Membre depuis le <?= $memberSince ?></p>
    </div>
</div>

<!--  ONGLETS NAVIGATION  -->
<nav class="account-tabs" aria-label="Sections du compte" role="tablist">
    <a class="account-tab<?= $activeTab === 'profile'     ? ' active' : '' ?>"
       href="<?= tab_url('profile') ?>"
       role="tab" aria-selected="<?= $activeTab === 'profile' ? 'true' : 'false' ?>">
        👤 Mon profil
    </a>
    <a class="account-tab<?= $activeTab === 'settings'    ? ' active' : '' ?>"
       href="<?= tab_url('settings') ?>"
       role="tab" aria-selected="<?= $activeTab === 'settings' ? 'true' : 'false' ?>">
        ⚙️ Paramètres
    </a>
    <a class="account-tab<?= $activeTab === 'saved-menus' ? ' active' : '' ?>"
       href="<?= tab_url('saved-menus') ?>"
       role="tab" aria-selected="<?= $activeTab === 'saved-menus' ? 'true' : 'false' ?>">
        📅 Menus sauvegardés
    </a>
    <a class="account-tab<?= $activeTab === 'favorites'   ? ' active' : '' ?>"
       href="<?= tab_url('favorites') ?>"
       role="tab" aria-selected="<?= $activeTab === 'favorites' ? 'true' : 'false' ?>">
        ⭐ Mes favoris
    </a>
</nav>

<!--  Contenu principal  -->
<main class="account-main" id="main-content">

    <div id="account-alerts" aria-live="polite"></div>


<!-- Onglet 1 — Profil -->

<?php if ($activeTab === 'profile'): ?>

    <div class="account-grid">

        <!-- Informations personnelles -->
        <section class="account-card" aria-labelledby="sec-info">
            <h2 id="sec-info">Informations personnelles</h2>
            <form id="profile-form" novalidate>

                <div class="form-group">
                    <label for="firstname">Prénom *</label>
                    <input type="text" id="firstname" name="firstname"
                           value="<?= $fn ?>" required autocomplete="given-name"
                           placeholder="Votre prénom">
                </div>
                <div class="form-group">
                    <label for="lastname">Nom *</label>
                    <input type="text" id="lastname" name="lastname"
                           value="<?= $ln ?>" required autocomplete="family-name"
                           placeholder="Votre nom">
                </div>
                <div class="form-group">
                    <label for="email">Adresse e-mail *</label>
                    <input type="email" id="email" name="email"
                           value="<?= $em ?>" required autocomplete="email"
                           placeholder="vous@exemple.fr">
                </div>

                <button type="submit" class="btn btn-secondary">💾 Enregistrer</button>
            </form>
        </section>

        <!-- Changer le mot de passe -->
        <section class="account-card" aria-labelledby="sec-pw">
            <h2 id="sec-pw">Changer le mot de passe</h2>
            <form id="password-form" novalidate>

                <div class="form-group">
                    <label for="current_password">Mot de passe actuel *</label>
                    <div class="pw-wrap">
                        <input type="password" id="current_password" name="current_password"
                               required autocomplete="current-password" placeholder="••••••••">
                        <button type="button" class="pw-toggle" data-target="current_password"
                                aria-label="Afficher/masquer">👁</button>
                    </div>
                </div>
                <div class="form-group">
                    <label for="new_password">Nouveau mot de passe *</label>
                    <div class="pw-wrap">
                        <input type="password" id="new_password" name="new_password"
                               required autocomplete="new-password"
                               placeholder="Minimum 8 caractères">
                        <button type="button" class="pw-toggle" data-target="new_password"
                                aria-label="Afficher/masquer">👁</button>
                    </div>
                    <!-- Barre de force -->
                    <div class="strength-bar-wrap" aria-hidden="true">
                        <div class="strength-bar" id="strength-bar"></div>
                    </div>
                    <span class="field-hint" id="strength-label" aria-live="polite"></span>
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirmer *</label>
                    <div class="pw-wrap">
                        <input type="password" id="confirm_password" name="confirm_password"
                               required autocomplete="new-password" placeholder="••••••••">
                        <button type="button" class="pw-toggle" data-target="confirm_password"
                                aria-label="Afficher/masquer">👁</button>
                    </div>
                    <span class="field-hint" id="confirm-hint" aria-live="polite"></span>
                </div>

                <button type="submit" class="btn btn-primary">🔑 Modifier le mot de passe</button>
            </form>
        </section>

    </div>


<!-- Onglet 2 — Paramètres -->

<?php elseif ($activeTab === 'settings'): ?>

    <form id="settings-form" novalidate>
        <div class="account-grid">

            <!-- Préférences alimentaires -->
            <section class="account-card" aria-labelledby="sec-diet">
                <h2 id="sec-diet">🥗 Préférences alimentaires</h2>
                <div class="form-group">
                    <label for="dietary_pref">Régime par défaut</label>
                    <select id="dietary_pref" name="dietary_pref">
                        <?php foreach ($dietPrefs as $p): ?>
                            <option value="<?= esc($p) ?>"<?= sel($p, $settings['dietary_pref'] ?? 'Tous') ?>>
                                <?= esc($p) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small>Utilisé lors de la génération automatique de menus.</small>
                </div>
            </section>

            <!-- Budget & personnes -->
            <section class="account-card" aria-labelledby="sec-budget">
                <h2 id="sec-budget">💶 Budget & convives</h2>
                <div class="form-group">
                    <label for="default_budget">Budget hebdomadaire par défaut (€)</label>
                    <input type="number" id="default_budget" name="default_budget"
                           min="0" step="0.5"
                           value="<?= esc($settings['default_budget'] ?? 80) ?>"
                           placeholder="80">
                </div>
                <div class="form-group">
                    <label for="default_persons">Nombre de personnes par défaut</label>
                    <input type="number" id="default_persons" name="default_persons"
                           min="1" max="20"
                           value="<?= esc($settings['default_persons'] ?? 2) ?>"
                           placeholder="2">
                </div>
            </section>

            <!-- Apparence -->
            <section class="account-card" aria-labelledby="sec-theme">
                <h2 id="sec-theme">🎨 Apparence</h2>
                <div class="account-radio-group">
                    <?php foreach ($themes as $val => $label): ?>
                        <label class="account-radio-label">
                            <input type="radio" name="theme" value="<?= $val ?>"
                                   <?= chk(($settings['theme'] ?? 'light') === $val) ?>>
                            <span class="account-radio-custom"></span>
                            <?= $label ?>
                        </label>
                    <?php endforeach; ?>
                </div>
                <small>Le thème est appliqué immédiatement et mémorisé sur cet appareil.</small>
            </section>

            <!-- Notifications -->
            <section class="account-card" aria-labelledby="sec-notif">
                <h2 id="sec-notif">🔔 Notifications</h2>
                <label class="account-toggle-label">
                    <input type="checkbox" name="notifications" id="notifications"
                           <?= chk(!empty($settings['notifications'])) ?>>
                    <span class="account-toggle" aria-hidden="true"></span>
                    Rappel de planification chaque lundi
                </label>
                <small>Un e-mail vous est envoyé pour vous rappeler de planifier votre semaine.</small>
            </section>

        </div>

        <div class="account-form-actions">
            <button type="submit" class="btn btn-secondary">💾 Enregistrer les paramètres</button>
            <a href="/index.php" class="btn btn-danger" style="text-decoration:none">Annuler</a>
        </div>
    </form>


<!-- onglet 3 — Menus sauvegardés -->
<?php elseif ($activeTab === 'saved-menus'): ?>

    <?php if (empty($menus)): ?>
        <div class="account-empty">
            <div class="account-empty-icon">📋</div>
            <h2>Aucun menu sauvegardé</h2>
            <p>Générez un menu et sauvegardez-le pour le retrouver ici.</p>
            <a href="/index.php" class="btn btn-secondary">Générer un menu</a>
        </div>
    <?php else: ?>
        <div class="account-menus" id="saved-menus-list">
            <?php foreach ($menus as $menu):
                $mid     = (int) $menu['id'];
                $mlabel  = esc(isset($menu['week_start'])
                    ? 'Semaine du ' . (new DateTime($menu['week_start']))->format('d/m/Y')
                    : 'Menu #' . $mid);
                $mdate   = isset($menu['created_at'])
                    ? (new DateTime($menu['created_at']))->format('d/m/Y à H\hi')
                    : '—';
                $mbud    = number_format((float)($menu['budget']     ?? 0), 2, ',', ' ');
                $mpersons= (int)($menu['persons'] ?? 1);
                $mcost   = number_format((float)($menu['total_cost'] ?? 0), 2, ',', ' ');
                $mdata   = json_decode($menu['menu_data'] ?? '{}', true);
            ?>
            <article class="account-menu-card">
                <div class="account-menu-header">
                    <div>
                        <h3><?= $mlabel ?></h3>
                        <p class="account-menu-meta">
                            Sauvegardé le <?= $mdate ?> &nbsp;·&nbsp;
                            <?= $mpersons ?> pers. &nbsp;·&nbsp;
                            Budget : <?= $mbud ?> € &nbsp;·&nbsp;
                            Coût estimé : <?= $mcost ?> €
                        </p>
                    </div>
                    <div class="account-menu-actions">
                        <a href="/index.php?load_menu=<?= $mid ?>"
                           class="btn btn-success btn-sm">📂 Charger</a>
                        <button type="button" class="btn btn-danger btn-sm btn-delete-menu"
                                data-menu-id="<?= $mid ?>">🗑 Supprimer</button>
                    </div>
                </div>

                <?php if (!empty($mdata)): ?>
                <div class="account-menu-preview">
                    <?php foreach (array_slice($DAYS, 0, 3) as $day):
                        if (empty($mdata[$day])) continue; ?>
                        <div class="account-preview-day">
                            <strong><?= $day ?></strong>
                            <?php foreach ($MEALS as $meal):
                                $r = $mdata[$day][$meal] ?? null;
                                if ($r): ?>
                                <span><em><?= $meal ?> :</em> <?= esc($r['name'] ?? '—') ?></span>
                            <?php endif; endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                    <?php if (count($DAYS) > 3): ?>
                        <p class="account-preview-more">+ <?= count($DAYS) - 3 ?> autres jours…</p>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>


<!-- onglet 4 — favoris -->

<?php elseif ($activeTab === 'favorites'): ?>

    <!-- Filtres type de repas -->
    <nav class="account-filter-tabs" aria-label="Filtrer par type">
        <a href="/account.php?tab=favorites"
           class="account-filter<?= !$favType ? ' active' : '' ?>">Tous</a>
        <?php foreach ($mealTypes as $key => $label): ?>
            <a href="/account.php?tab=favorites&meal_type=<?= urlencode($key) ?>"
               class="account-filter<?= $favType === $key ? ' active' : '' ?>">
                <?= esc($label) ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <?php if (empty($favorites)): ?>
        <div class="account-empty">
            <div class="account-empty-icon">⭐</div>
            <h2>Aucun favori<?= $favType ? ' pour « ' . esc($mealLabels[$favType] ?? $favType) . ' »' : '' ?></h2>
            <p>Ajoutez des recettes en favori depuis l'onglet <strong>Mes recettes</strong>.</p>
            <a href="/index.php" class="btn btn-secondary">Voir mes recettes</a>
        </div>
    <?php else: ?>
        <div class="account-fav-grid" id="favorites-list">
            <?php foreach ($favorites as $recipe):
                $rid   = (int) $recipe['id'];
                $rname = esc($recipe['name']      ?? '');
                $rmt   = esc($mealLabels[$recipe['meal_type'] ?? ''] ?? ($recipe['meal_type'] ?? ''));
                $rft   = esc($recipe['dietary'] ?? '');
                $rtime = (int)($recipe['prep_time'] ?? 0);
                $rcost = number_format((float)($recipe['estimated_cost'] ?? 0), 2, ',', ' ');
                $rcal  = (int)($recipe['calories'] ?? 0);
                $rprot = (int)($recipe['protein'] ?? 0);
                $rdate = isset($recipe['created_at'])
                    ? (new DateTime($recipe['created_at']))->format('d/m/Y')
                    : '—';
            ?>
            <article class="account-fav-card">
                <div class="account-fav-header">
                    <h3><?= $rname ?></h3>
                    <span class="account-star" title="Mis en favori le <?= $rdate ?>">⭐</span>
                </div>
                <div class="account-fav-badges">
                    <?php if ($rmt): ?><span class="item-detail">🍽️ <?= $rmt ?></span><?php endif; ?>
                    <?php if ($rft): ?><span class="item-detail">🌿 <?= $rft ?></span><?php endif; ?>
                    <?php if ($rtime): ?><span class="item-detail">⏱️ <?= $rtime ?> min</span><?php endif; ?>
                    <?php if ($rcost !== '0,00'): ?><span class="item-detail">💰 <?= $rcost ?> €</span><?php endif; ?>
                    <?php if ($rcal): ?><span class="item-detail">🔥 <?= $rcal ?> kcal</span><?php endif; ?>
                    <?php if ($rprot): ?><span class="item-detail">💪 <?= $rprot ?>g prot.</span><?php endif; ?>
                </div>
                <div class="account-fav-footer">
                    <span class="account-fav-date">Ajouté le <?= $rdate ?></span>
                    <button type="button" class="btn btn-danger btn-sm btn-remove-favorite"
                            data-recipe-id="<?= $rid ?>">✕ Retirer</button>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

<?php endif; ?>
</main>

<script>
    window.__ACCOUNT_TAB__ = <?= json_encode($activeTab) ?>;
</script>
<script src="/assets/js/api.js"></script>
<script src="/assets/js/account.js"></script>
<script src="/assets/js/theme.js"></script>
</body>
</html>
