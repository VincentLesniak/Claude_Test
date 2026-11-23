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
     *
     * Mise à jour (partie « My cinema by PiePHP ») : avec aussi les tables
     * genres, films et historiques.
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

        // Ajout (partie « My cinema by PiePHP ») : les tables du mini-site,
        // comme dans database.sql. Elles sont créées pour TOUS les tests :
        // UserModel a maintenant la relation 'has many historiques', donc
        // lire un utilisateur lit aussi la table historiques.
        // Les FOREIGN KEY de MySQL sont omises : SQLite ne les applique pas
        // par défaut, et les models suppriment eux-mêmes l'historique lié.
        $pdo->exec(
            'CREATE TABLE genres (
                id  INTEGER PRIMARY KEY AUTOINCREMENT,
                nom VARCHAR(100) NOT NULL UNIQUE
            )'
        );
        $pdo->exec(
            'CREATE TABLE films (
                id       INTEGER PRIMARY KEY AUTOINCREMENT,
                titre    VARCHAR(255) NOT NULL,
                annee    INTEGER NULL,
                resume   TEXT NULL,
                genre_id INTEGER NULL
            )'
        );
        $pdo->exec(
            'CREATE TABLE historiques (
                id      INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                film_id INTEGER NOT NULL,
                vu_le   DATETIME NOT NULL
            )'
        );

        Database::setConnection($pdo);
        return $pdo;
    }
}
