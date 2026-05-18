<?php
/**
 * config.php — Constantes de connexion à la base de données.
 *
 */

// Base de données
define('DB_HOST',    getenv('DB_HOST')    ?: 'localhost');
define('DB_PORT',    getenv('DB_PORT')    ?: '3306');
define('DB_NAME',    getenv('DB_NAME')    ?: 'meal_planner');
define('DB_USER',    getenv('DB_USER')    ?: 'root');
define('DB_PASS',    getenv('DB_PASS')    ?: '');
define('DB_CHARSET', 'utf8mb4');

// Application
define('APP_DEBUG',  getenv('APP_ENV') !== 'production');
define('APP_SECRET', getenv('APP_SECRET') ?: 'change_this_secret_in_production_32chars');

// Durée de vie de la session (secondes) — 24 h
define('SESSION_LIFETIME', 86400);