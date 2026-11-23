<?php

/**
 * Tests de Core\ORM : create, read, update, delete et find, sur une table
 * "articles" comme dans l'exemple du sujet. Chaque test part d'une base
 * SQLite vide, en mémoire.
 */

use Core\Database;
use Core\ORM;
use Tests\Assert;
use Tests\TestDatabase;

require_once dirname(__DIR__) . '/Core/autoload.php';

/**
 * Un ORM branché sur une base neuve qui contient une table articles.
 */
$orm = function (): ORM {
    $pdo = TestDatabase::create();
    Database::setConnection(null);
    $pdo->exec(
        'CREATE TABLE articles (
            id      INTEGER PRIMARY KEY AUTOINCREMENT,
            titre   VARCHAR(255) NOT NULL,
            content TEXT NOT NULL,
            author  VARCHAR(255) NOT NULL
        )'
    );
    return new ORM($pdo);
};

/**
 * Ajoute trois articles et renvoie l'ORM.
 */
$withArticles = function () use ($orm): ORM {
    $articles = $orm();
    $articles->create('articles', ['titre' => 'Premier', 'content' => 'a', 'author' => 'Rodrigue']);
    $articles->create('articles', ['titre' => 'Deuxième', 'content' => 'b', 'author' => 'Alice']);
    $articles->create('articles', ['titre' => 'Troisième', 'content' => 'c', 'author' => 'Rodrigue']);
    return $articles;
};

/**
 * Vérifie que $test lève une InvalidArgumentException.
 */
$expectInvalid = function (callable $test, string $message): void {
    try {
        $test();
    } catch (InvalidArgumentException $e) {
        return;
    }
    throw new RuntimeException("aucune InvalidArgumentException levée : $message");
};

return [
    'create() ajoute une ligne et renvoie son id (exemple du sujet)' => function () use ($orm): void {
        $articles = $orm();
        $id = $articles->create('articles', [
            'titre' => 'un super titre',
            'content' => 'et voici un super article de blog',
            'author' => 'Rodrigue',
        ]);

        Assert::same(1, $id, 'id du premier article');
        Assert::same(
            ['id' => 1, 'titre' => 'un super titre', 'content' => 'et voici un super article de blog',
                'author' => 'Rodrigue'],
            $articles->read('articles', $id)
        );
    },

    'read() renvoie null pour un id inexistant' => function () use ($orm): void {
        Assert::same(null, $orm()->read('articles', 42));
    },

    'update() modifie les champs donnés et renvoie un booléen' => function () use ($withArticles): void {
        $articles = $withArticles();

        Assert::same(true, $articles->update('articles', 1, ['titre' => 'Modifié']), 'retour de update()');
        Assert::same('Modifié', $articles->read('articles', 1)['titre']);
        Assert::same('Rodrigue', $articles->read('articles', 1)['author'], 'les autres champs ne bougent pas');
        Assert::same(false, $articles->update('articles', 42, ['titre' => 'x']), 'id inexistant');
    },

    'delete() supprime la ligne et renvoie un booléen' => function () use ($withArticles): void {
        $articles = $withArticles();

        Assert::same(true, $articles->delete('articles', 1), 'retour de delete()');
        Assert::same(null, $articles->read('articles', 1));
        Assert::same(false, $articles->delete('articles', 1), 'deuxième suppression');
        Assert::same(2, count($articles->find('articles')), 'les autres lignes restent');
    },

    'find() sans paramètre renvoie toutes les lignes, triées par id' => function () use ($orm, $withArticles): void {
        Assert::same([], $orm()->find('articles'), 'table vide');
        Assert::same(
            ['Premier', 'Deuxième', 'Troisième'],
            array_column($withArticles()->find('articles'), 'titre')
        );
    },

    'find() avec un WHERE en tableau (valeurs préparées)' => function () use ($withArticles): void {
        $found = $withArticles()->find('articles', ['WHERE' => ['author' => 'Rodrigue']]);
        Assert::same(['Premier', 'Troisième'], array_column($found, 'titre'));

        $found = $withArticles()->find('articles', ['WHERE' => ['author' => 'Rodrigue', 'titre' => 'Premier']]);
        Assert::same([1], array_column($found, 'id'), 'plusieurs conditions = AND');
    },

    'find() avec un WHERE en chaîne, comme dans le sujet' => function () use ($withArticles): void {
        $found = $withArticles()->find('articles', ['WHERE' => 'id > 1']);
        Assert::same([2, 3], array_column($found, 'id'));
    },

    'find() garde les valeurs par défaut des clés non données' => function () use ($withArticles): void {
        // Seul LIMIT est donné : ORDER BY reste "id ASC".
        $found = $withArticles()->find('articles', ['LIMIT' => 2]);
        Assert::same([1, 2], array_column($found, 'id'));
    },

    'find() avec ORDER BY et LIMIT (y compris la pagination)' => function () use ($withArticles): void {
        $articles = $withArticles();

        $found = $articles->find('articles', ['ORDER BY' => 'id DESC', 'LIMIT' => '2']);
        Assert::same([3, 2], array_column($found, 'id'), 'tri décroissant limité à 2');

        $found = $articles->find('articles', ['ORDER BY' => 'author asc, id desc']);
        Assert::same([2, 3, 1], array_column($found, 'id'), 'tri sur deux colonnes');

        $found = $articles->find('articles', ['LIMIT' => '1, 1']);
        Assert::same([2], array_column($found, 'id'), 'LIMIT début, nombre');
    },

    'une valeur piégée est enregistrée telle quelle (requêtes préparées)' => function () use ($withArticles): void {
        $articles = $withArticles();
        $piege = "x' OR '1'='1";
        $id = $articles->create('articles', ['titre' => $piege, 'content' => 'c', 'author' => 'a']);

        Assert::same($piege, $articles->read('articles', $id)['titre']);
        Assert::same([], $articles->find('articles', ['WHERE' => ['author' => $piege]]), 'WHERE piégé');
        Assert::same(4, count($articles->find('articles')), 'la table a été modifiée par l\'injection');
    },

    'les noms de table et de colonne piégés sont refusés' => function () use ($orm, $expectInvalid): void {
        $articles = $orm();
        $expectInvalid(function () use ($articles): void {
            $articles->read('articles; DROP TABLE articles', 1);
        }, 'table piégée');
        $expectInvalid(function () use ($articles): void {
            $articles->create('articles', ["titre) VALUES ('x'); --" => 'z']);
        }, 'colonne piégée');
        $expectInvalid(function () use ($articles): void {
            $articles->find('articles', ['WHERE' => ['1 OR 1' => 'x']]);
        }, 'colonne piégée dans WHERE');
        $expectInvalid(function () use ($articles): void {
            $articles->create('articles', []);
        }, 'aucun champ');
        $expectInvalid(function () use ($articles): void {
            $articles->update('articles', 1, ['id' => 99]);
        }, 'modification de l\'id');
    },

    'find() refuse un ORDER BY, un LIMIT ou une clé invalides' => function () use ($orm, $expectInvalid): void {
        $articles = $orm();
        $expectInvalid(function () use ($articles): void {
            $articles->find('articles', ['ORDER BY' => 'id; DROP TABLE articles']);
        }, 'ORDER BY piégé');
        $expectInvalid(function () use ($articles): void {
            $articles->find('articles', ['LIMIT' => '1; DROP TABLE articles']);
        }, 'LIMIT piégé');
        $expectInvalid(function () use ($articles): void {
            $articles->find('articles', ['LIMIT' => '-1']);
        }, 'LIMIT négatif');
        $expectInvalid(function () use ($articles): void {
            $articles->find('articles', ['ORDERBY' => 'id']);
        }, 'clé mal orthographiée');
    },

    'sans connexion donnée, l\'ORM utilise Core\Database' => function (): void {
        TestDatabase::create();
        try {
            $id = (new ORM())->create('users', ['email' => 'a@b.c', 'password' => 'x']);
            Assert::same('a@b.c', (new ORM())->read('users', $id)['email']);
        } finally {
            Database::setConnection(null);
        }
    },
];
