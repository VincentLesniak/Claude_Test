<?php

/**
 * Tests de Core\Entity : le constructeur (attributs donnés ou id lu en
 * base), save(), delete() et le CRUD hérité par les models.
 *
 * Entity est abstraite : on teste avec une petite classe "anonyme" qui en
 * hérite et gère une table articles (l'exemple du sujet : titre, content,
 * author), puis avec le vrai UserModel. Chaque test part d'une base SQLite
 * vide, en mémoire.
 */

use Core\Database;
use Core\Entity;
use Model\UserModel;
use Tests\Assert;
use Tests\TestDatabase;

require_once dirname(__DIR__) . '/Core/autoload.php';

/**
 * Une base neuve avec les tables users et articles.
 */
$database = function (): PDO {
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
    return $pdo;
};

/**
 * Crée une entité "article". Une classe anonyme ("new class ... extends")
 * n'a pas de vrai nom : on lui donne donc sa table explicitement.
 */
$article = function (array $params, PDO $pdo): Entity {
    return new class ($params, $pdo) extends Entity {
        protected static $table = 'articles';
    };
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

$fields = ['titre' => 'Un titre', 'content' => 'Du texte', 'author' => 'Rodrigue'];

return [
    'chaque clé du tableau devient un attribut public' => function () use ($database, $article, $fields): void {
        $entity = $article($fields, $database());

        Assert::same('Rodrigue', $entity->author);
        Assert::true((new ReflectionProperty($entity, 'titre'))->isPublic(), 'attribut titre non public');
        Assert::same(null, $entity->id, 'id d\'une entité pas encore enregistrée');
    },

    'save() crée la ligne et remplit l\'id' => function () use ($database, $article, $fields): void {
        $pdo = $database();
        $entity = $article($fields, $pdo);

        Assert::same(1, $entity->save(), 'retour de save()');
        Assert::same(1, $entity->id, 'id rempli après save()');
        Assert::same('Un titre', $entity->read(1)['titre']);
    },

    'avec un id, le constructeur lit la ligne en base' => function () use ($database, $article, $fields): void {
        $pdo = $database();
        $article($fields, $pdo)->save();

        $entity = $article(['id' => '1'], $pdo);
        Assert::same(1, $entity->id, 'id en entier');
        Assert::same('Du texte', $entity->content);
        Assert::same('Rodrigue', $entity->author);
    },

    'avec un id inexistant, l\'entité reste vide' => function () use ($database, $article): void {
        $entity = $article(['id' => 42], $database());

        Assert::same(null, $entity->id);
        Assert::true(!isset($entity->titre), 'aucun attribut ne doit être créé');
    },

    'save() sur une entité lue en base fait un UPDATE' => function () use ($database, $article, $fields): void {
        $pdo = $database();
        $article($fields, $pdo)->save();

        $entity = $article(['id' => 1], $pdo);
        $entity->titre = 'Modifié';
        Assert::same(1, $entity->save(), 'même id après la mise à jour');

        Assert::same('Modifié', $entity->read(1)['titre']);
        Assert::same(1, count($entity->readAll()), 'save() ne doit pas créer de 2e ligne');
        // Sans modification, save() ne fait rien (et ne plante pas).
        Assert::same(1, $article(['id' => 1], $pdo)->save());
    },

    'delete() sans argument supprime l\'entité elle-même' => function () use ($database, $article, $fields): void {
        $pdo = $database();
        $entity = $article($fields, $pdo);
        Assert::same(false, $entity->delete(), 'entité jamais enregistrée');

        $entity->save();
        Assert::same(true, $entity->delete(), 'retour de delete()');
        Assert::same(null, $entity->id, 'id remis à null');
        Assert::same(null, $entity->read(1));
    },

    'find() et readAll() connaissent déjà la table' => function () use ($database, $article, $fields): void {
        $pdo = $database();
        $article($fields, $pdo)->save();
        $article(['author' => 'Alice'] + $fields, $pdo)->save();

        $entity = $article([], $pdo);
        Assert::same(2, count($entity->readAll()));
        Assert::same(['Alice'], array_column($entity->find(['WHERE' => ['author' => 'Alice']]), 'author'));
    },

    'un nom d\'attribut réservé ou invalide est refusé' => function () use ($database, $article, $expectInvalid): void {
        $pdo = $database();
        // "db" écraserait la connexion de l'entité.
        $expectInvalid(function () use ($article, $pdo): void {
            $article(['db' => 'piège'], $pdo);
        });
        $expectInvalid(function () use ($article, $pdo): void {
            $article(['mon champ' => 'x'], $pdo);
        });
    },

    'getTable() devine la table d\'après le nom du model' => function (): void {
        Assert::same('users', UserModel::getTable());
    },

    'UserModel : l\'usage du sujet dans registerAction' => function () use ($database): void {
        $pdo = $database();
        $params = ['email' => 'a@b.c', 'password' => 'motdepasse', 'submit' => 'Valider'];

        $user = new UserModel($params, $pdo);
        if (!$user->id) {
            $user->save();
        }

        Assert::same(1, $user->id);
        $row = $user->read(1);
        Assert::same('a@b.c', $row['email']);
        Assert::true(password_verify('motdepasse', $row['password']), 'mot de passe haché');
        // "submit" n'est pas une colonne : columns() l'a écarté du save().
        Assert::same('Valider', $user->submit);
    },

    'UserModel : un utilisateur relu puis enregistré garde son mot de passe' => function () use ($database): void {
        $pdo = $database();
        (new UserModel(['email' => 'a@b.c', 'password' => 'motdepasse'], $pdo))->save();

        $user = new UserModel(['id' => 1], $pdo);
        $user->email = 'new@b.c';
        $user->save();

        $row = $user->read(1);
        Assert::same('new@b.c', $row['email']);
        // L'empreinte n'a pas été re-hachée : le mot de passe marche toujours.
        Assert::true(password_verify('motdepasse', $row['password']), 'mot de passe haché deux fois');
    },
];
