<?php
namespace App\Core;

use PDO;
use PDOException;

// Database - singleton PDO

// fournit une connexion PDO unique à MYSQL
// utilisation : $pdo = Database::getInstance()->getConnection();

class Database
{
    /** @var Database|null Instance unique */
    private static ?Database $instance = null;

    /** @var PDO Connexion active */
    private PDO $pdo;

    /**
     * Constructeur privé — impossible d'instancier directement.
     * Configure PDO avec des options sécurisées.
     *
     * @throws \RuntimeException si la connexion échoue
     */
    private function __construct()
    {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            DB_HOST,
            DB_NAME,
            DB_CHARSET
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,   // Exceptions sur erreur SQL
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,         // Tableaux associatifs
            PDO::ATTR_EMULATE_PREPARES   => false,                     // Vraies requêtes préparées
            PDO::ATTR_PERSISTENT         => false,                     // Pas de connexions persistantes
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
        ];

        try {
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            /* On masque les détails de connexion en production */
            $message = APP_DEBUG
                ? 'Connexion BDD échouée : ' . $e->getMessage()
                : 'Erreur de connexion à la base de données.';
            throw new \RuntimeException($message, 500, $e);
        }
    }

    /**
     * Retourne l'instance unique (Singleton).
     *
     * @return self
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Expose la connexion PDO.
     *
     * @return PDO
     */
    public function getConnection(): PDO
    {
        return $this->pdo;
    }

    /**
     * Raccourci : prépare et exécute une requête.
     *
     * @param  string $sql    Requête SQL avec placeholders
     * @param  array  $params Paramètres liés
     * @return \PDOStatement
     */
    public function query(string $sql, array $params = []): \PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Retourne la dernière valeur auto-incrémentée (après INSERT).
     *
     * @return string
     */
    public function lastInsertId(): string
    {
        return $this->pdo->lastInsertId();
    }

    /* Empêche le clonage et la désérialisation du singleton */
    private function __clone() {}
    public function __wakeup(): void
    {
        throw new \RuntimeException('Désérialisation du singleton interdite.');
    }
}