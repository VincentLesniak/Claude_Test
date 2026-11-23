<?php

/**
 * tests/TestDatabase.php : une base de données jetable pour les tests.
 *
 * Les tests ne doivent pas dépendre de MySQL (il n'est pas installé sur les
 * machines de GitHub Actions, et on ne veut pas salir ta vraie base). On
 * utilise donc SQLite "en mémoire" : une base complète qui vit dans la RAM
 * le temps du test et disparaît ensuite. Grâce à PDO, UserModel ne voit
 * aucune différence avec MySQL.
 *
 * Core\Database est chargée par l'autoloader, inclus par les fichiers de
 * test (pas ici : PSR-1 interdit de déclarer une classe ET d'exécuter du code
 * comme require dans le même fichier).
 */

namespace Tests;

use Core\Database;
use PDO;

class TestDatabase
{
    /**
     * Crée une base vide avec la table users, et la donne à Core\Database
     * pour que les controllers l'utilisent à la place de MySQL.
     */
    public static function create(): PDO
    {
        $pdo = Database::connect(['driver' => 'sqlite', 'path' => ':memory:']);

        // Même table que database.sql, traduite pour SQLite :
        // "INTEGER PRIMARY KEY AUTOINCREMENT" remplace "AUTO_INCREMENT".
        $pdo->exec(
            'CREATE TABLE users (
                id       INTEGER PRIMARY KEY AUTOINCREMENT,
                email    VARCHAR(255) NOT NULL UNIQUE,
                password VARCHAR(255) NOT NULL
            )'
        );

        Database::setConnection($pdo);
        return $pdo;
    }
}
