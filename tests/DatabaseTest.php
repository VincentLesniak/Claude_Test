<?php

/**
 * Tests de Core\Database : fabrication du DSN et connexion partagée.
 */

use Core\Database;
use Tests\Assert;
use Tests\TestDatabase;

require_once dirname(__DIR__) . '/Core/autoload.php';

return [
    'le DSN MySQL est construit à partir de config.json' => function (): void {
        $config = json_decode(file_get_contents(dirname(__DIR__) . '/config.json'), true)['database'];
        Assert::same(
            'mysql:host=localhost;port=3306;dbname=piephp;charset=utf8mb4',
            Database::buildDsn($config)
        );
    },

    'le DSN SQLite utilise le chemin donné (ou la mémoire)' => function (): void {
        $config = ['driver' => 'sqlite', 'path' => '/tmp/base.sqlite'];
        Assert::same('sqlite:/tmp/base.sqlite', Database::buildDsn($config));
        Assert::same('sqlite::memory:', Database::buildDsn(['driver' => 'sqlite']));
    },

    'un driver inconnu lève une exception claire' => function (): void {
        try {
            Database::buildDsn(['driver' => 'oracle']);
        } catch (RuntimeException $e) {
            Assert::contains('oracle', $e->getMessage());
            return;
        }
        throw new RuntimeException('aucune exception levée');
    },

    'getConnection() renvoie toujours la même connexion' => function (): void {
        $pdo = TestDatabase::create();
        try {
            Assert::true(Database::getConnection() === $pdo, 'première connexion');
            Assert::true(Database::getConnection() === Database::getConnection(), 'connexions différentes');
        } finally {
            Database::setConnection(null);
        }
    },

    'les erreurs SQL lèvent une exception PDOException' => function (): void {
        $pdo = TestDatabase::create();
        Database::setConnection(null);
        try {
            $pdo->query('SELECT * FROM table_inexistante');
        } catch (PDOException $e) {
            return;
        }
        throw new RuntimeException('aucune exception levée');
    },
];
