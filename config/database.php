<?php
/**
 * NexFlow CRM - MySQL Database Connection
 * XAMPP / Localhost Configuration
 */

if (!defined('NEXFLOW_DB_HOST')) {
    define('NEXFLOW_DB_HOST', '127.0.0.1');
}

if (!defined('NEXFLOW_DB_NAME')) {
    define('NEXFLOW_DB_NAME', 'nexflow_crm');
}

if (!defined('NEXFLOW_DB_USER')) {
    define('NEXFLOW_DB_USER', 'root');
}

if (!defined('NEXFLOW_DB_PASS')) {
    define('NEXFLOW_DB_PASS', '');
}

/**
 * Create and return a PDO database connection.
 */
function nexflow_db(): PDO
{
    static $pdo = null;

    // Reuse existing connection
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = 'mysql:host=' . NEXFLOW_DB_HOST
         . ';dbname=' . NEXFLOW_DB_NAME
         . ';charset=utf8mb4';

    try {
        $pdo = new PDO(
            $dsn,
            NEXFLOW_DB_USER,
            NEXFLOW_DB_PASS,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );

        return $pdo;

    } catch (PDOException $e) {
        // Do not expose database credentials/errors to users.
        error_log('NexFlow Database Error: ' . $e->getMessage());

        throw new RuntimeException(
            'Unable to connect to the NexFlow database.'
        );
    }
}