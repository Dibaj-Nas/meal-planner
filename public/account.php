<?php
/**
 * account.php - espace utillisateur (page unique)
 * 
 * TODO
 * 4 onglets : Profil | Paramètres | Menus sauvegardés | Favoris
 * 
 * méthodes user.php utilisées : 
 *   - get profile  ($userId)
 *   - updateProfile ($userId, $data)
 *   - changePassword ($userId, $current, $new)
 *   - getUserSettings ($userId, $data)
 *   - getSavedMenus($userId)
 *   - deleteWeeklyMenu($userId, $menuId)
 *   - getFavorites($userId)
 *   - removeFavorite($userId, $recipeId)
 */
declare(strict_types=1);
session_start();

require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/core/Security.php';
require_once __DIR__ . '/../app/models/User.php';

// garde 
if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user = new User();
$userId = (int) $_SESSION['user_id'];

// onglet actif (paramètre GET et POST)
$validTabs = ['profile', 'settings', 'saved-menus', 'favorites'];
$activeTab = $_GET['tab'] ?? 'profile';
if (!in_array($activeTab, $validTabs, true)) $activeTab = 'profile';

// traitement des action POST
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    /* ── Mise à jour du profil ── */
    if ($action === 'update_profile') {
        $activeTab = 'profile';
        $firstname = Security::sanitize($_POST['firstname'] ?? '');
        $lastname  = Security::sanitize($_POST['lastname']  ?? '');
        $email     = Security::sanitize($_POST['email']     ?? '');

        if (!$firstname || !$lastname || !$email) {
            $error = 'Tous les champs sont obligatoires.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Adresse e-mail invalide.';
        } else {
            $r = $user->updateProfile($userId, compact('firstname', 'lastname', 'email'));
            if ($r['success']) {
                $_SESSION['user_name']  = "$firstname $lastname";
                $_SESSION['user_email'] = $email;
                $success = 'Profil mis à jour avec succès.';
            } else {
                $error = $r['message'] ?? 'Erreur lors de la mise à jour.';
            }
        }
    }

    /* ── Changement de mot de passe ── */
    if ($action === 'change_password') {
        $activeTab = 'profile';
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password']     ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (!$current || !$new || !$confirm) {
            $error = 'Tous les champs mot de passe sont obligatoires.';
        } elseif ($new !== $confirm) {
            $error = 'Les nouveaux mots de passe ne correspondent pas.';
        } elseif (strlen($new) < 8) {
            $error = 'Minimum 8 caractères requis.';
        } else {
            $r = $user->changePassword($userId, $current, $new);
            $success = $r['success'] ? 'Mot de passe modifié.' : '';
            $error   = $r['success'] ? '' : ($r['message'] ?? 'Mot de passe actuel incorrect.');
        }
    }

    /* ── Sauvegarde des paramètres ── */
    if ($action === 'save_settings') {
        $activeTab = 'settings';
        $r = $user->saveUserSettings($userId, [
            'dietary_pref'    => Security::sanitize($_POST['dietary_pref']    ?? 'Tous'),
            'default_budget'  => (float) ($_POST['default_budget']  ?? 0),
            'default_persons' => (int)   ($_POST['default_persons'] ?? 2),
            'theme'           => Security::sanitize($_POST['theme'] ?? 'light'),
            'notifications'   => isset($_POST['notifications']) ? 1 : 0,
        ]);
        $success = $r['success'] ? 'Paramètres enregistrés.' : '';
        $error   = $r['success'] ? '' : ($r['message'] ?? 'Erreur.');
    }

    /* ── Suppression d'un menu sauvegardé ── */
    if ($action === 'delete_menu') {
        $activeTab = 'saved-menus';
        $menuId = (int) ($_POST['menu_id'] ?? 0);
        if ($menuId > 0) {
            $r = $user->deleteWeeklyMenu($userId, $menuId);
            $success = $r['success'] ? 'Menu supprimé.' : '';
            $error   = $r['success'] ? '' : ($r['message'] ?? 'Erreur.');
        }
    }

    /* ── Suppression d'un favori ── */
    if ($action === 'remove_favorite') {
        $activeTab = 'favorites';
        $recipeId = (int) ($_POST['recipe_id'] ?? 0);
        if ($recipeId > 0) {
            $r = $user->removeFavorite($userId, $recipeId);
            $success = $r['success'] ? 'Recette retirée des favoris.' : '';
            $error   = $r['success'] ? '' : ($r['message'] ?? 'Erreur.');
        }
    }
}

// changement des données selon l'onglet
$profile  = $user->getProfile($userId);
$settings = $user->getUserSettings($userId);
$menus    = ($activeTab === 'saved-menus') ? ($user->getSavedMenus($userId) ?? []) : [];
$favType  = Security::sanitize($_GET['type'] ?? '');
$favorites= ($activeTab === 'favorites')   ? ($user->getFavorites($userId)  ?? []) : [];
if ($favType) {
    $favorites = array_filter($favorites, fn($r) => ($r['meal_type'] ?? '') === $favType);
}

/* ── Helpers ── */
$fn  = htmlspecialchars($profile['firstname'] ?? '');
$ln  = htmlspecialchars($profile['lastname']  ?? '');
$em  = htmlspecialchars($profile['email']     ?? '');
$ini = strtoupper(substr($profile['firstname'] ?? 'U', 0, 1) . substr($profile['lastname'] ?? '', 0, 1));
$memberSince = isset($profile['created_at'])
    ? (new DateTime($profile['created_at']))->format('d/m/Y')
    : '—';

$dietPrefs  = ['Tous','Végétarien','Vegan','Sans gluten','Sans lactose'];
$themes     = ['light' => '☀️ Clair', 'dark' => '🌙 Sombre', 'system' => '🖥️ Système'];
$mealTypes  = ['Petit-déjeuner','Déjeuner','Dîner'];
$DAYS       = ['Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi','Dimanche'];
$MEALS      = ['Petit-déj','Déjeuner','Dîner'];

function tab_url(string $t): string {
    return 'account.php?tab=' . $t;
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
    <link rel="icon" href="assets/img/PR.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,700;1,400&family=Commissioner:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="account-page">

<!--  EN-TÊTE  -->
<header class="account-header">
    <a href="index.php" class="account-logo" aria-label="Retour à l'accueil">
        <img src="assets/img/logoPlanificateurDeRepas.png" alt="Logo" width="36">
        <span>Planificateur de Repas</span>
    </a>
    <a href="index.php" class="account-back-btn">← Retour à l'application</a>
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

    <?php if ($success): ?>
        <div class="alert alert-success" role="alert">✅ <?= esc($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger" role="alert">⚠️ <?= esc($error) ?></div>
    <?php endif; ?>


<!-- Onglet 1 — Profil -->

<?php if ($activeTab === 'profile'): ?>

    <div class="account-grid">

        <!-- Informations personnelles -->
        <section class="account-card" aria-labelledby="sec-info">
            <h2 id="sec-info">Informations personnelles</h2>
            <form method="POST" action="account.php?tab=profile" novalidate>
                <input type="hidden" name="action" value="update_profile">

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
            <form method="POST" action="account.php?tab=profile" novalidate>
                <input type="hidden" name="action" value="change_password">

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

    <form method="POST" action="account.php?tab=settings" novalidate>
        <input type="hidden" name="action" value="save_settings">
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
            <a href="index.php" class="btn btn-danger" style="text-decoration:none">Annuler</a>
        </div>
    </form>


<!-- onglet 3 — Menus sauvegardés -->
<?php elseif ($activeTab === 'saved-menus'): ?>

    <?php if (empty($menus)): ?>
        <div class="account-empty">
            <div class="account-empty-icon">📋</div>
            <h2>Aucun menu sauvegardé</h2>
            <p>Générez un menu et sauvegardez-le pour le retrouver ici.</p>
            <a href="index.php" class="btn btn-secondary">Générer un menu</a>
        </div>
    <?php else: ?>
        <div class="account-menus">
            <?php foreach ($menus as $menu):
                $mid     = (int) $menu['id'];
                $mlabel  = esc($menu['label'] ?? 'Menu sans titre');
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
                        <a href="index.php?load_menu=<?= $mid ?>"
                           class="btn btn-success btn-sm">📂 Charger</a>
                        <form method="POST" action="account.php?tab=saved-menus"
                              onsubmit="return confirm('Supprimer ce menu ?')">
                            <input type="hidden" name="action"  value="delete_menu">
                            <input type="hidden" name="menu_id" value="<?= $mid ?>">
                            <button type="submit" class="btn btn-danger btn-sm">🗑 Supprimer</button>
                        </form>
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
        <a href="account.php?tab=favorites"
           class="account-filter<?= !$favType ? ' active' : '' ?>">Tous</a>
        <?php foreach ($mealTypes as $t): ?>
            <a href="account.php?tab=favorites&type=<?= urlencode($t) ?>"
               class="account-filter<?= $favType === $t ? ' active' : '' ?>">
                <?= esc($t) ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <?php if (empty($favorites)): ?>
        <div class="account-empty">
            <div class="account-empty-icon">⭐</div>
            <h2>Aucun favori<?= $favType ? " pour « $favType »" : '' ?></h2>
            <p>Ajoutez des recettes en favori depuis l'onglet <strong>Mes recettes</strong>.</p>
            <a href="index.php" class="btn btn-secondary">Voir mes recettes</a>
        </div>
    <?php else: ?>
        <div class="account-fav-grid">
            <?php foreach ($favorites as $recipe):
                $rid   = (int) $recipe['id'];
                $rname = esc($recipe['name']      ?? '');
                $rmt   = esc($recipe['meal_type'] ?? '');
                $rft   = esc($recipe['food_type'] ?? '');
                $rtime = (int)($recipe['prep_time'] ?? 0);
                $rcost = number_format((float)($recipe['estimated_cost'] ?? 0), 2, ',', ' ');
                $rcal  = (int)($recipe['calories'] ?? 0);
                $rprot = (int)($recipe['proteins'] ?? 0);
                $rings = esc($recipe['ingredients'] ?? '');
                $rdate = isset($recipe['favorited_at'])
                    ? (new DateTime($recipe['favorited_at']))->format('d/m/Y')
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
                <?php if ($rings): ?>
                    <p class="account-fav-ings">📝 <?= $rings ?></p>
                <?php endif; ?>
                <div class="account-fav-footer">
                    <span class="account-fav-date">Ajouté le <?= $rdate ?></span>
                    <form method="POST" action="account.php?tab=favorites"
                          onsubmit="return confirm('Retirer des favoris ?')">
                        <input type="hidden" name="action"    value="remove_favorite">
                        <input type="hidden" name="recipe_id" value="<?= $rid ?>">
                        <button type="submit" class="btn btn-danger btn-sm">✕ Retirer</button>
                    </form>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

<?php endif; ?>
</main>

<script src="assets/js/account.js"></script>
</body>
</html>