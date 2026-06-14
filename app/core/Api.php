<?php
declare(strict_types=1);

namespace App\Core;

class Api
{
    public static function json(array $data, int $status = 200): never
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function body(): array
    {
        $raw = file_get_contents('php://input');
        if ($raw) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        return $_POST;
    }

    public static function verifyCsrf(array $body = []): bool
    {
        $token = $body['csrf_token']
            ?? $_POST['csrf_token']
            ?? $_SERVER['HTTP_X_CSRF_TOKEN']
            ?? '';
        return Security::verifyCsrf((string) $token);
    }
}
