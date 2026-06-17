<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Api — Boîte à outils pour les réponses d'API (format JSON)
 * ==========================================================
 *
 * Les contrôleurs (AuthController, MenuController…) répondent au navigateur
 * en JSON. Cette classe regroupe 3 aides réutilisées partout :
 *   - json()      : envoyer une réponse JSON et arrêter le script
 *   - body()      : lire les données envoyées par le client (JSON ou formulaire)
 *   - verifyCsrf(): vérifier le jeton anti-CSRF présent dans la requête
 *
 * Toutes les méthodes sont "static" : on les appelle sans créer d'objet,
 * par exemple Api::json([...]).
 */
class Api
{
    /**
     * Envoie une réponse JSON au client puis termine le script.
     *
     * Le type de retour "never" indique à PHP que cette fonction ne rend
     * jamais la main (elle se termine toujours par exit).
     *
     * @param array $data   Données à encoder en JSON (ex : ['success' => true]).
     * @param int   $status Code HTTP (200 = OK, 404 = introuvable, 401…).
     */
    public static function json(array $data, int $status = 200): never
    {
        // On vide tout tampon de sortie en cours pour éviter que du HTML
        // parasite (espaces, warnings…) ne corrompe le JSON renvoyé.
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');

        // JSON_UNESCAPED_UNICODE : garde les accents lisibles (é au lieu de é).
        // JSON_UNESCAPED_SLASHES : n'échappe pas les "/" (URLs plus lisibles).
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * Lit le corps de la requête entrante et le renvoie sous forme de tableau.
     *
     * Le client peut envoyer :
     *   - du JSON (cas de nos appels Ajax via fetch) → on le décode ;
     *   - un formulaire classique (application/x-www-form-urlencoded) → $_POST.
     *
     * @return array Données reçues (tableau associatif).
     */
    public static function body(): array
    {
        // php://input contient le corps brut de la requête (utile pour le JSON).
        $raw = file_get_contents('php://input');
        if ($raw) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        // Repli : données d'un formulaire HTML standard.
        return $_POST;
    }

    /**
     * Vérifie le jeton CSRF fourni par le client.
     *
     * Le jeton peut arriver de 3 façons : dans le corps JSON, dans $_POST,
     * ou dans l'en-tête HTTP "X-CSRF-TOKEN" (envoyé par notre client Ajax).
     * On délègue la comparaison sécurisée à Security::verifyCsrf().
     *
     * @param array $body Corps de la requête (déjà lu via self::body()).
     * @return bool true si le jeton est valide.
     */
    public static function verifyCsrf(array $body = []): bool
    {
        $token = $body['csrf_token']
            ?? $_POST['csrf_token']
            ?? $_SERVER['HTTP_X_CSRF_TOKEN']
            ?? '';
        return Security::verifyCsrf((string) $token);
    }
}
