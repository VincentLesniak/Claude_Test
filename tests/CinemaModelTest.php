<?php

/**
 * Tests des models du mini-site my_cinema (partie « My cinema by PiePHP ») :
 * FilmModel, GenreModel et HistoriqueModel.
 *
 * On vérifie surtout ce que le sujet demande :
 *   - les relations : Film 'has one' Genre, Genre 'has many films',
 *     User 'has many historiques', Historique 'has one film' ;
 *   - les règles de chaque model (validate()) ;
 *   - la suppression d'un film, qui efface aussi son historique.
 * La base est une base SQLite en mémoire (tests/TestDatabase.php), vide au
 * départ de chaque test.
 */

use Core\Database;
use Model\FilmModel;
use Model\GenreModel;
use Model\HistoriqueModel;
use Model\UserModel;
use Tests\Assert;
use Tests\TestDatabase;

require_once dirname(__DIR__) . '/Core/autoload.php';

/**
 * Une base neuve avec :
 *   genres       1 "Science-fiction", 2 "Comédie"
 *   films        1 "Alien" (genre 1), 2 "Brazil" (genre 1), 3 "Sans genre"
 *   users        1 "a@b.c"
 *   historiques  1 (user 1, film 2), 2 (user 1, film 1, plus récent)
 */
$database = function (): PDO {
    $pdo = TestDatabase::create();
    // Les models reçoivent $pdo directement : on retire la connexion
    // partagée pour être sûr qu'aucun model ne prend une autre base.
    Database::setConnection(null);

    $pdo->exec("INSERT INTO genres (nom) VALUES ('Science-fiction'), ('Comédie')");
    $pdo->exec(
        "INSERT INTO films (titre, annee, genre_id) VALUES
            ('Alien', 1979, 1), ('Brazil', 1985, 1), ('Sans genre', NULL, NULL)"
    );
    $pdo->exec("INSERT INTO users (email, password) VALUES ('a@b.c', 'x')");
    $pdo->exec(
        "INSERT INTO historiques (user_id, film_id, vu_le) VALUES
            (1, 2, '2026-01-01 10:00:00'), (1, 1, '2026-02-01 10:00:00')"
    );
    return $pdo;
};

return [
    'les tables sont devinées d\'après le nom des models' => function (): void {
        Assert::same('films', FilmModel::getTable());
        Assert::same('genres', GenreModel::getTable());
        Assert::same('historiques', HistoriqueModel::getTable());
    },

    'les phrases du sujet donnent les bonnes relations' => function (): void {
        Assert::same([['type' => 'has one', 'table' => 'genres', 'name' => 'genre']], FilmModel::getRelations());
        Assert::same([['type' => 'has many', 'table' => 'films', 'name' => 'films']], GenreModel::getRelations());
        Assert::same([['type' => 'has one', 'table' => 'films', 'name' => 'film']], HistoriqueModel::getRelations());
    },

    'Film \'has one\' Genre : le film lu contient son genre' => function () use ($database): void {
        $pdo = $database();
        $film = new FilmModel(['id' => 1], $pdo);
        Assert::same('Alien', $film->titre);
        Assert::same('Science-fiction', $film->genre['nom']);

        // Un film sans genre : la relation vaut null (et pas une erreur).
        Assert::same(null, (new FilmModel(['id' => 3], $pdo))->genre);
    },

    'Genre \'has many films\' : le genre lu contient ses films' => function () use ($database): void {
        $pdo = $database();
        Assert::same(['Alien', 'Brazil'], array_column((new GenreModel(['id' => 1], $pdo))->films, 'titre'));
        Assert::same([], (new GenreModel(['id' => 2], $pdo))->films);
    },

    'User \'has many historiques\' et Historique \'has one film\'' => function () use ($database): void {
        $pdo = $database();
        Assert::same(2, count((new UserModel(['id' => 1], $pdo))->historiques));

        // findForUser() : le plus récent d'abord, film compris.
        $rows = (new HistoriqueModel([], $pdo))->findForUser(1);
        Assert::same(['Alien', 'Brazil'], array_column(array_column($rows, 'film'), 'titre'));
    },

    'save() n\'envoie jamais la relation en base' => function () use ($database): void {
        $pdo = $database();
        $film = new FilmModel(['id' => 1], $pdo);
        $film->titre = 'Aliens';
        $film->save(); // planterait avec une colonne "genre" inconnue

        Assert::same('Aliens', (new FilmModel(['id' => 1], $pdo))->titre);
    },

    'FilmModel::validate() : titre, année et genre' => function () use ($database): void {
        $pdo = $database();
        $cases = [
            [['titre' => ''], 'Le titre est obligatoire.'],
            [['titre' => str_repeat('a', 256)], 'Le titre ne doit pas dépasser 255 caractères.'],
            [['titre' => 'X', 'annee' => 'abc'], "L'année doit être"],
            [['titre' => 'X', 'annee' => '1500'], "L'année doit être"],
            [['titre' => 'X', 'genre_id' => '99'], "Le genre choisi n'existe pas."],
            [['titre' => 'X', 'genre_id' => '1abc'], "Le genre choisi n'existe pas."],
        ];
        foreach ($cases as [$fields, $expected]) {
            $error = (new FilmModel($fields, $pdo))->validate();
            Assert::contains($expected, (string) $error, var_export($fields, true));
        }

        $valid = new FilmModel(['titre' => 'Metropolis', 'annee' => '1927', 'genre_id' => '1'], $pdo);
        Assert::same(null, $valid->validate(), 'film valide');
    },

    'GenreModel::validate() : nom obligatoire et unique' => function () use ($database): void {
        $pdo = $database();
        Assert::same('Le nom du genre est obligatoire.', (new GenreModel(['nom' => ' '], $pdo))->validate());
        Assert::same('Ce genre existe déjà.', (new GenreModel(['nom' => 'Comédie'], $pdo))->validate());
        Assert::same(null, (new GenreModel(['nom' => 'Drame'], $pdo))->validate());

        // Garder son propre nom en modifiant un genre n'est pas un doublon.
        Assert::same(null, (new GenreModel(['id' => 2], $pdo))->validate());
    },

    'supprimer un film efface aussi ses lignes d\'historique' => function () use ($database): void {
        $pdo = $database();
        Assert::true((new FilmModel(['id' => 2], $pdo))->delete(), 'film non supprimé');

        Assert::same(null, (new FilmModel([], $pdo))->read(2), 'le film existe encore');
        $films = array_column((new HistoriqueModel([], $pdo))->findForUser(1), 'film_id');
        Assert::same([1], array_map('intval', $films), 'l\'historique du film supprimé est resté');
    },
];
