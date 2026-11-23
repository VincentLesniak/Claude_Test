<?php

/**
 * Tests de Model\UserModel : save(), le CRUD et la vérification des
 * identifiants. Chaque test part d'une base SQLite vide (tests/TestDatabase.php).
 */

use Core\Database;
use Model\UserModel;
use Tests\Assert;
use Tests\TestDatabase;

require_once dirname(__DIR__) . '/Core/autoload.php';

/**
 * Un UserModel branché sur une base neuve.
 */
$model = function (): UserModel {
    $pdo = TestDatabase::create();
    Database::setConnection(null);
    return new UserModel([], $pdo);
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
    'save() enregistre l\'utilisateur et renvoie son id' => function () use ($model): void {
        $users = $model();
        $id = $users->setEmail('a@b.c')->setPassword('motdepasse')->save();

        Assert::same(1, $id, 'id du premier utilisateur');
        Assert::same('a@b.c', $users->read($id)['email']);
    },

    'le mot de passe est haché, jamais stocké en clair' => function () use ($model): void {
        $users = $model();
        $id = $users->setEmail('a@b.c')->setPassword('motdepasse')->save();
        $hash = $users->read($id)['password'];

        Assert::true($hash !== 'motdepasse', 'mot de passe en clair dans la base');
        Assert::true(password_verify('motdepasse', $hash), 'empreinte incorrecte');
    },

    'create() renvoie un nouvel id à chaque fois' => function () use ($model): void {
        $users = $model();
        Assert::same(1, $users->create(['email' => 'a@b.c', 'password' => 'x']));
        Assert::same(2, $users->create(['email' => 'd@e.f', 'password' => 'y']));
    },

    'read() renvoie null pour un id inexistant' => function () use ($model): void {
        Assert::same(null, $model()->read(42));
    },

    'update() modifie les champs donnés' => function () use ($model): void {
        $users = $model();
        $id = $users->create(['email' => 'a@b.c', 'password' => 'x']);

        Assert::same(true, $users->update($id, ['email' => 'new@b.c']), 'retour de update()');
        Assert::same('new@b.c', $users->read($id)['email']);
        Assert::same(false, $users->update(42, ['email' => 'z@b.c']), 'id inexistant');
    },

    'update() hache aussi un nouveau mot de passe' => function () use ($model): void {
        $users = $model();
        $id = $users->create(['email' => 'a@b.c', 'password' => 'ancien']);
        $users->update($id, ['password' => 'nouveau']);

        Assert::true(password_verify('nouveau', $users->read($id)['password']), 'nouveau mot de passe');
    },

    'delete() supprime la ligne' => function () use ($model): void {
        $users = $model();
        $id = $users->create(['email' => 'a@b.c', 'password' => 'x']);

        Assert::same(true, $users->delete($id), 'retour de delete()');
        Assert::same(null, $users->read($id));
        Assert::same(false, $users->delete($id), 'deuxième suppression');
    },

    'readAll() renvoie toutes les lignes dans l\'ordre' => function () use ($model): void {
        $users = $model();
        Assert::same([], $users->readAll(), 'table vide');

        $users->create(['email' => 'a@b.c', 'password' => 'x']);
        $users->create(['email' => 'd@e.f', 'password' => 'y']);
        Assert::same(['a@b.c', 'd@e.f'], array_column($users->readAll(), 'email'));
    },

    'une colonne hors liste blanche est refusée (pas d\'injection)' => function () use ($model, $expectInvalid): void {
        $users = $model();
        $expectInvalid(function () use ($users): void {
            $users->create(['email' => 'a@b.c', "password) VALUES ('x', 'y'); --" => 'z']);
        });
        $expectInvalid(function () use ($users): void {
            $users->update(1, ['id' => 99]);
        });
        $expectInvalid(function () use ($users): void {
            $users->create([]);
        });
    },

    'une valeur piégée est enregistrée telle quelle (requêtes préparées)' => function () use ($model): void {
        $users = $model();
        $email = "x' OR '1'='1";
        $id = $users->create(['email' => $email, 'password' => 'x']);

        Assert::same($email, $users->read($id)['email']);
        Assert::same(1, count($users->readAll()), 'la table a été modifiée par l\'injection');
    },

    'checkCredentials() accepte le bon mot de passe, sans renvoyer l\'empreinte' => function () use ($model): void {
        $users = $model();
        $users->setEmail('a@b.c')->setPassword('motdepasse')->save();

        $user = $users->checkCredentials();
        Assert::same(['id' => 1, 'email' => 'a@b.c'], $user);
    },

    'checkCredentials() refuse un mauvais mot de passe ou un email inconnu' => function () use ($model): void {
        $users = $model();
        $users->setEmail('a@b.c')->setPassword('motdepasse')->save();

        Assert::same(null, $users->setPassword('mauvais')->checkCredentials(), 'mauvais mot de passe');
        $users->setEmail('inconnu@b.c')->setPassword('motdepasse');
        Assert::same(null, $users->checkCredentials(), 'email inconnu');
    },

    'sans connexion donnée, le model utilise Core\Database' => function (): void {
        $pdo = TestDatabase::create();
        try {
            $id = (new UserModel())->create(['email' => 'a@b.c', 'password' => 'x']);
            Assert::same('a@b.c', (new UserModel([], $pdo))->read($id)['email']);
        } finally {
            Database::setConnection(null);
        }
    },
];
