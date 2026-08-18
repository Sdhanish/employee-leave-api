<?php

/**
 * config.php
 * Database configuration and PDO connection helper.
 */

define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'employee_leave_db');
define('DB_USER', 'root');       // Change to your MySQL username
define('DB_PASS', '');           // Change to your MySQL password
define('DB_CHARSET', 'utf8mb4');

/**
 * Returns a PDO connection instance.
 * Throws a PDOException on failure (caught in each endpoint).
 */
function getDB(): PDO
{
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        DB_HOST,
        DB_PORT,
        DB_NAME,
        DB_CHARSET
    );

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,   // Throw exceptions on error
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,         // Return assoc arrays
        PDO::ATTR_EMULATE_PREPARES   => false,                    // Use real prepared statements
    ];

    return new PDO($dsn, DB_USER, DB_PASS, $options);
}
