<?php
/**
 * public/index.php — Front controller / routeur central
 *
 * Ce fichier gère TOUS les chemins du projet :
 *   - sert index.html comme page principale (accueil)
 *   - sert les fichiers statiques (assets : css, js, images, polices)
 *   - route les pages applicatives (login, register, app, compte)
 *   - route les pages légales (.html)
 *   - délègue les requêtes /api/* au point d'entrée API
 *
 * Utilisé comme script de routage du serveur PHP intégré :
 *   php -S localhost:8080 -t . public/index.php
 * ou via la directive de réécriture du serveur web (Apache/Nginx).
 */

declare(strict_types=1);

/** Racine du projet (dossier parent de /public). */
$ROOT = dirname(__DIR__);

/** Chemin demandé, nettoyé (sans query string, sans slash final). */
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';
$uri = '/' . trim(rawurldecode($uri), '/');   // normalise : "" → "/", "/login/" → "/login"

/* ─────────────────────────────────────────────────────────────────
   1. FICHIERS STATIQUES
   Si l'URL pointe vers un fichier réel (assets, images, polices…),
   on laisse le serveur PHP le servir tel quel (return false).
───────────────────────────────────────────────────────────────── */
if ($uri !== '/' && preg_match('#\.(css|js|mjs|map|png|jpe?g|gif|svg|ico|webp|avif|woff2?|ttf|eot|pdf|txt|json)$#i', $uri)) {
    $asset = $ROOT . $uri;
    if (is_file($asset)) {
        // En contexte « php -S » : laisser le serveur intégré servir le fichier.
        if (PHP_SAPI === 'cli-server') {
            return false;
        }
        // En contexte serveur classique : streamer le fichier nous-mêmes.
        $mimes = [
            'css' => 'text/css', 'js' => 'application/javascript', 'mjs' => 'application/javascript',
            'json' => 'application/json', 'svg' => 'image/svg+xml', 'png' => 'image/png',
            'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif',
            'webp' => 'image/webp', 'avif' => 'image/avif', 'ico' => 'image/x-icon',
            'woff' => 'font/woff', 'woff2' => 'font/woff2', 'ttf' => 'font/ttf',
            'pdf' => 'application/pdf', 'txt' => 'text/plain',
        ];
        $ext = strtolower(pathinfo($asset, PATHINFO_EXTENSION));
        header('Content-Type: ' . ($mimes[$ext] ?? 'application/octet-stream'));
        readfile($asset);
        exit;
    }
}

/* ─────────────────────────────────────────────────────────────────
   2. API REST  (/api/...)
   Délègue au point d'entrée API existant (api.php).
───────────────────────────────────────────────────────────────── */
if ($uri === '/api' || str_starts_with($uri, '/api/')) {
    // On retire le préfixe /api pour qu'api.php reçoive la route via ?route=
    $_GET['route'] = trim(substr($uri, strlen('/api')), '/');
    require $ROOT . '/api.php';
    exit;
}
// Compatibilité : appels directs à api.php (utilisés par assets/js/api.js)
if ($uri === '/api.php' || str_starts_with($uri, '/api.php/')) {
    require $ROOT . '/api.php';
    exit;
}

/* ─────────────────────────────────────────────────────────────────
   3. ROUTES NOMMÉES
   Chemins « propres » → fichier réel du projet.
   index.html est la PAGE PRINCIPALE de l'application.
───────────────────────────────────────────────────────────────── */
$routes = [
    '/'          => 'index.html',                       // ← page principale (accueil)
    '/accueil'   => 'index.html',
    '/home'      => 'index.html',

    // ── Pages "côté utilisateur" : déplacées dans le dossier user/ ──
    // On mappe à la fois l'URL propre (/login) ET l'accès direct (/login.php)
    // vers le fichier dans user/, pour ne casser aucun lien existant.
    '/login'         => 'user/login.php',
    '/login.php'     => 'user/login.php',
    '/connexion'     => 'user/login.php',

    '/register'      => 'user/register.php',
    '/register.php'  => 'user/register.php',
    '/inscription'   => 'user/register.php',

    '/forgot-password'      => 'user/forgot-password.php',   // demande de réinitialisation
    '/forgot-password.php'  => 'user/forgot-password.php',
    '/mot-de-passe-oublie'  => 'user/forgot-password.php',

    '/reset-password'       => 'user/reset-password.php',    // nouveau mot de passe
    '/reset-password.php'   => 'user/reset-password.php',

    '/verify-email'         => 'user/verify-email.php',      // confirmation d'adresse
    '/verify-email.php'     => 'user/verify-email.php',

    '/app'       => 'index.php',                         // l'application (SPA, requiert auth)
    '/dashboard' => 'index.php',
    '/menu'      => 'index.php',

    '/account'     => 'public/account.php',
    '/account.php' => 'public/account.php',   // accès direct (navigation JS : account.php?tab=…)
    '/compte'      => 'public/account.php',

    '/contact'         => 'Contact.html',
    '/confidentialite' => 'PolitiqueDeConfidentialite.html',
    '/conditions'      => 'ConditionsDeUtilisation.html',
];

if (isset($routes[$uri])) {
    serve_file($ROOT . '/' . $routes[$uri], $ROOT);
}

/* ─────────────────────────────────────────────────────────────────
   4. ACCÈS DIRECT À UN FICHIER .php / .html
   Permet de conserver la navigation existante (login.php, account.php…).
───────────────────────────────────────────────────────────────── */
$candidate = $ROOT . $uri;
$realRoot  = realpath($ROOT);
$realFile  = realpath($candidate);

// Sécurité : empêcher de remonter hors de la racine (path traversal)
if ($realFile !== false && $realRoot !== false && str_starts_with($realFile, $realRoot)) {
    if (is_file($realFile) && preg_match('#\.(php|html?)$#i', $realFile)) {
        serve_file($realFile, $ROOT);
    }
}

/* ─────────────────────────────────────────────────────────────────
   5. 404 — Page introuvable
───────────────────────────────────────────────────────────────── */
http_response_code(404);
header('Content-Type: text/html; charset=UTF-8');
echo '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8">'
   . '<title>404 — Page introuvable</title>'
   . '<style>body{font-family:sans-serif;text-align:center;padding:4rem 1rem;background:#F0EADC;color:#2A191F}'
   . 'h1{font-size:3rem;margin:0}a{color:#CE2A2A}</style></head><body>'
   . '<h1>404</h1><p>La page demandée est introuvable.</p>'
   . '<p><a href="/">← Retour à l\'accueil</a></p></body></html>';
exit;


/* ═══════════════════════════════════════════════════════════════════
   FONCTION UTILITAIRE
═══════════════════════════════════════════════════════════════════ */

/**
 * Sert un fichier : exécute le PHP, ou streame le HTML/statique.
 *
 * @param string $file  Chemin absolu du fichier à servir.
 * @param string $root  Racine du projet (répertoire de travail pour les require relatifs).
 */
function serve_file(string $file, string $root): void
{
    if (!is_file($file)) {
        http_response_code(404);
        exit;
    }

    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));

    if ($ext === 'php') {
        // Se placer dans la racine pour que les chemins relatifs des scripts fonctionnent.
        chdir($root);
        require $file;
        exit;
    }

    if ($ext === 'html' || $ext === 'htm') {
        header('Content-Type: text/html; charset=UTF-8');
        readfile($file);
        exit;
    }

    // Autres types : streaming brut.
    readfile($file);
    exit;
}
