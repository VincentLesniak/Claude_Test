<?php

/**
 * Tests des relations entre models (sujet, « Avancé - Les relations de
 * model ») : Entity::getRelations(), puis read(), find(), readAll() et le
 * constructeur, qui renvoient les enregistrements liés. Quelques tests
 * appellent aussi l'ORM directement.
 *
 * On reprend l'exemple du blog du sujet, avec trois models de test rangés
 * dans tests/Model/ :
 *   ArticleModel  'has many comments', 'has many tags'
 *   CommentModel  'has one article'
 *   TagModel      'has many articles'
 *
 * Les données de chaque test (base SQLite en mémoire, vide au départ) :
 *   articles       1 "Premier", 2 "Second"
 *   comments       1 "Bravo" (article 1), 2 "Merci" (article 1),
 *                  3 "Bof" (article 2), 4 "Orphelin" (aucun article)
 *   tags           1 "php", 2 "sql"
 *   articles_tags  article 1 <-> php, article 1 <-> sql, article 2 <-> php
 */

use Core\Database;
use Core\Entity;
use Core\ORM;
use Model\UserModel;
use Tests\Assert;
use Tests\Model\ArticleModel;
use Tests\Model\CommentModel;
use Tests\Model\TagModel;
use Tests\TestDatabase;

require_once dirname(__DIR__) . '/Core/autoload.php';
// Les models de test ne sont pas dans src/Model/ : l'autoloader ne les
// trouverait pas, on les charge donc nous-mêmes.
require_once __DIR__ . '/Model/ArticleModel.php';
require_once __DIR__ . '/Model/CommentModel.php';
require_once __DIR__ . '/Model/TagModel.php';

/**
 * Une base neuve, remplie avec les données décrites plus haut.
 */
$database = function (): PDO {
    $pdo = TestDatabase::create();
    Database::setConnection(null);

    $pdo->exec('CREATE TABLE articles (id INTEGER PRIMARY KEY AUTOINCREMENT, titre VARCHAR(255) NOT NULL)');
    $pdo->exec(
        'CREATE TABLE comments (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            content    TEXT NOT NULL,
            article_id INTEGER NULL
        )'
    );
    $pdo->exec('CREATE TABLE tags (id INTEGER PRIMARY KEY AUTOINCREMENT, name VARCHAR(255) NOT NULL)');
    // La table pivot : pas besoin d'id, seulement les deux clés étrangères.
    $pdo->exec('CREATE TABLE articles_tags (article_id INTEGER NOT NULL, tag_id INTEGER NOT NULL)');

    $pdo->exec("INSERT INTO articles (titre) VALUES ('Premier'), ('Second')");
    $pdo->exec(
        "INSERT INTO comments (content, article_id)
         VALUES ('Bravo', 1), ('Merci', 1), ('Bof', 2), ('Orphelin', NULL)"
    );
    $pdo->exec("INSERT INTO tags (name) VALUES ('php'), ('sql')");
    $pdo->exec('INSERT INTO articles_tags (article_id, tag_id) VALUES (1, 1), (1, 2), (2, 1)');
    return $pdo;
};

/**
 * Vérifie que $test lève une InvalidArgumentException.
 */
$expectInvalid = function (callable $test): void {
    try {
        $test();
    } catch (InvalidArgumentException $e) {
        return;
    }
    throw new RuntimeException('aucune InvalidArgumentException levée');
};

return [
    'getRelations() reconnaît 1 : N, N : 1 et N : N' => function (): void {
        Assert::same(
            [
                ['type' => 'has many', 'table' => 'comments', 'name' => 'comments'],
                ['type' => 'many to many', 'table' => 'tags', 'name' => 'tags', 'pivot' => 'articles_tags'],
            ],
            ArticleModel::getRelations()
        );
        Assert::same(
            [['type' => 'has one', 'table' => 'articles', 'name' => 'article']],
            CommentModel::getRelations()
        );
        // Même table pivot vue depuis l'autre côté.
        Assert::same(
            [['type' => 'many to many', 'table' => 'articles', 'name' => 'articles', 'pivot' => 'articles_tags']],
            TagModel::getRelations()
        );
    },

    'un model sans relation n\'en a aucune' => function (): void {
        Assert::same([], UserModel::getRelations());
    },

    'has many : read() contient les commentaires de l\'article' => function () use ($database): void {
        $article = (new ArticleModel([], $database()))->read(1);

        Assert::same('Premier', $article['titre']);
        Assert::same(['Bravo', 'Merci'], array_column($article['comments'], 'content'));
    },

    'many to many : les tags d\'un article, les articles d\'un tag' => function () use ($database): void {
        $pdo = $database();

        $article = (new ArticleModel([], $pdo))->read(1);
        Assert::same(['php', 'sql'], array_column($article['tags'], 'name'));

        $tag = (new TagModel([], $pdo))->read(1);
        Assert::same(['Premier', 'Second'], array_column($tag['articles'], 'titre'));
        Assert::same(['php'], array_column((new ArticleModel([], $pdo))->read(2)['tags'], 'name'));
    },

    'has one : read() contient l\'article du commentaire' => function () use ($database): void {
        $pdo = $database();

        $comment = (new CommentModel([], $pdo))->read(3);
        Assert::same('Second', $comment['article']['titre']);
        // Sans article_id, pas d'article : null (et pas une erreur).
        Assert::same(null, (new CommentModel([], $pdo))->read(4)['article']);
    },

    'find() et readAll() ajoutent les relations à chaque ligne' => function () use ($database): void {
        $pdo = $database();
        $articles = (new ArticleModel([], $pdo))->readAll();

        Assert::same(2, count($articles));
        Assert::same(['Bof'], array_column($articles[1]['comments'], 'content'));
        Assert::same(['php'], array_column($articles[1]['tags'], 'name'));

        $found = (new CommentModel([], $pdo))->find(['WHERE' => ['article_id' => 1]]);
        Assert::same(['Premier', 'Premier'], array_column(array_column($found, 'article'), 'titre'));
    },

    'le constructeur avec un id crée un attribut par relation' => function () use ($database): void {
        $article = new ArticleModel(['id' => 1], $database());

        Assert::same(2, count($article->comments));
        Assert::same('sql', $article->tags[1]['name']);
    },

    'save() n\'enregistre pas les relations' => function () use ($database): void {
        $pdo = $database();

        // Une entité lue avec ses relations, modifiée puis enregistrée :
        // "comments" et "tags" ne sont pas des colonnes, l'UPDATE ne doit
        // contenir que "titre".
        $article = new ArticleModel(['id' => 1], $pdo);
        $article->titre = 'Modifié';
        $article->save();
        Assert::same('Modifié', $article->read(1)['titre']);

        // Même chose pour un INSERT, si l'utilisateur a rempli une relation.
        $new = new ArticleModel(['titre' => 'Nouveau', 'comments' => []], $pdo);
        Assert::same(3, $new->save());
        Assert::same([], $new->read(3)['comments']);
    },

    'une phrase de relation incomprise est refusée' => function () use ($database, $expectInvalid): void {
        $pdo = $database();
        $expectInvalid(function () use ($pdo): void {
            $entity = new class ([], $pdo) extends Entity {
                protected static $table = 'articles';
                private static $relations = ['has meny comments'];
            };
            $entity->read(1);
        });
    },

    'le sujet écrit \'has many\' . $table : la version collée marche aussi' => function () use ($database): void {
        $entity = new class ([], $database()) extends Entity {
            protected static $table = 'articles';
            private static $relations = ['has many' . 'comments'];
        };
        Assert::same(['Bravo', 'Merci'], array_column($entity->read(1)['comments'], 'content'));
    },

    'ORM : read() et find() sans relation ne changent pas' => function () use ($database): void {
        $orm = new ORM($database());
        Assert::same(['id', 'titre'], array_keys($orm->read('articles', 1)));
        Assert::same(['id', 'titre'], array_keys($orm->find('articles')[0]));
    },

    'ORM : une relation donnée à la main' => function () use ($database): void {
        $orm = new ORM($database());
        $tag = $orm->read('tags', 2, [
            ['type' => 'many to many', 'table' => 'articles', 'pivot' => 'articles_tags'],
        ]);
        Assert::same(['Premier'], array_column($tag['articles'], 'titre'));
    },

    'ORM : une relation invalide est refusée' => function () use ($database, $expectInvalid): void {
        $orm = new ORM($database());
        // Type inconnu.
        $expectInvalid(function () use ($orm): void {
            $orm->read('articles', 1, [['type' => 'belongs to', 'table' => 'comments']]);
        });
        // Nom de table piégé.
        $expectInvalid(function () use ($orm): void {
            $orm->read('articles', 1, [['type' => 'has many', 'table' => 'comments; DROP TABLE articles']]);
        });
        // Table pivot oubliée.
        $expectInvalid(function () use ($orm): void {
            $orm->read('articles', 1, [['type' => 'many to many', 'table' => 'tags']]);
        });
    },

    'ORM::singular() retire le "s" final' => function (): void {
        Assert::same('article', ORM::singular('articles'));
        Assert::same('tag', ORM::singular('tags'));
    },
];
