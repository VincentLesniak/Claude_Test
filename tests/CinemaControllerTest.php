<?php

/**
 * Tests des controllers du mini-site my_cinema (partie « My cinema by
 * PiePHP ») : FilmController, GenreController, HistoriqueController, les
 * nouvelles actions de UserController (modifier et supprimer son profil),
 * et les outils ajoutés à Core\Controller (redirect(), flash(),
 * requireLogin()).
 *
 * Comme dans UserControllerTest, on simule une requête : on remplit $_GET,
 * $_POST, $_SERVER['REQUEST_METHOD'] et $_SESSION, on appelle l'action, puis
 * on lit ce qui est affiché à la destruction du controller.
 *
 * En ligne de commande, aucun en-tête HTTP n'est envoyé : pour savoir où
 * mène une redirection, on lit la petite page « Redirection vers ... » que
 * prépare Controller::redirect().
 */

use Controller\FilmController;
use Controller\GenreController;
use Controller\HistoriqueController;
use Controller\UserController;
use Core\Database;
use Tests\Assert;
use Tests\TestDatabase;

require_once dirname(__DIR__) . '/Core/autoload.php';

/**
 * Exécute $class::$action() et renvoie la page affichée.
 *
 * @param array $get  les paramètres de l'URL (?id=3 -> ['id' => '3'])
 * @param array $post les champs envoyés ; vide = une visite en GET
 * @param bool  $asPost force un POST même sans champ (bouton seul)
 */
$call = function (
    string $class,
    string $action,
    array $get = [],
    array $post = [],
    bool $asPost = false
): string {
    $_GET = $get;
    $_POST = $post;
    $_SERVER['REQUEST_METHOD'] = ($post !== [] || $asPost) ? 'POST' : 'GET';
    try {
        $controller = new $class();
        ob_start();
        $controller->$action();
        unset($controller);
        return ob_get_clean();
    } finally {
        $_GET = [];
        $_POST = [];
        unset($_SERVER['REQUEST_METHOD']);
    }
};

/**
 * Une base neuve, avec un membre (id 1, connecté si $login), deux genres et
 * un film :
 *   genres  1 "Science-fiction", 2 "Comédie"
 *   films   1 "Alien" (1979, genre 1)
 *   users   1 "a@b.c" (mot de passe "motdepasse"), 2 "autre@b.c"
 */
$database = function (bool $login = true): PDO {
    $pdo = TestDatabase::create();
    $pdo->exec("INSERT INTO genres (nom) VALUES ('Science-fiction'), ('Comédie')");
    $pdo->exec("INSERT INTO films (titre, annee, genre_id) VALUES ('Alien', 1979, 1)");
    $hash = password_hash('motdepasse', PASSWORD_DEFAULT);
    $pdo->exec("INSERT INTO users (email, password) VALUES ('a@b.c', '$hash'), ('autre@b.c', 'x')");
    $_SESSION = $login ? ['user_id' => 1] : [];
    return $pdo;
};

/**
 * Remet tout à zéro après un test : plus de base partagée, session vide.
 */
$cleanUp = function (): void {
    Database::setConnection(null);
    $_SESSION = [];
};

return [
    'un visiteur non connecté est renvoyé vers la connexion' => function () use ($call, $database, $cleanUp): void {
        $database(false);
        try {
            foreach ([FilmController::class, GenreController::class] as $class) {
                $html = $call($class, 'addAction');
                Assert::contains('Redirection vers <a href="/user/login">', $html, $class);
            }
            Assert::same('Connectez-vous pour accéder à cette page.', $_SESSION['flash']['message'] ?? null);
        } finally {
            $cleanUp();
        }
    },

    'redirect() refuse une adresse vers un autre site' => function (): void {
        $controller = new FilmController();
        $redirect = function (string $path): void {
            $this->redirect($path);
        };
        foreach (['//exemple.com', 'http://exemple.com', '/\\exemple.com'] as $path) {
            try {
                $redirect->call($controller, $path);
                throw new LogicException("$path accepté");
            } catch (InvalidArgumentException $e) {
                // attendu
            }
        }
        unset($controller);
    },

    'l\'accueil affiche les films de la base, avec leur genre' => function () use ($call, $database, $cleanUp): void {
        $database(false);
        try {
            $html = $call(\Controller\AppController::class, 'indexAction');
            Assert::contains('<a href="/film/show?id=1">Alien</a>', $html);
            Assert::contains('Science-fiction', $html);
            Assert::true(strpos($html, '/film/add') === false, 'lien d\'ajout montré à un visiteur');
        } finally {
            $cleanUp();
        }
    },

    'la page d\'un film affiche son genre' => function () use ($call, $database, $cleanUp): void {
        $database();
        try {
            $html = $call(FilmController::class, 'showAction', ['id' => '1']);
            Assert::contains('<h1>Alien</h1>', $html);
            Assert::contains('<dd>Science-fiction</dd>', $html);
            Assert::contains('action="/historique/add?film_id=1"', $html, 'bouton « J\'ai vu ce film »');
        } finally {
            $cleanUp();
        }
    },

    'un film inconnu renvoie à l\'accueil avec un message' => function () use ($call, $database, $cleanUp): void {
        $database();
        try {
            foreach (['99', 'abc', ''] as $id) {
                $html = $call(FilmController::class, 'showAction', ['id' => $id]);
                Assert::contains('Redirection vers <a href="/">', $html, "id $id");
            }
            Assert::same("Ce film n'existe pas.", $_SESSION['flash']['message'] ?? null);
        } finally {
            $cleanUp();
        }
    },

    'ajouter un film l\'enregistre puis redirige vers sa page' => function () use ($call, $database, $cleanUp): void {
        $pdo = $database();
        try {
            $html = $call(FilmController::class, 'addAction', [], [
                'titre' => 'Metropolis', 'annee' => '1927', 'genre_id' => '1', 'resume' => 'Une ville.',
            ]);
            Assert::contains('Redirection vers <a href="/film/show?id=2">', $html);
            $row = $pdo->query('SELECT titre, annee, genre_id FROM films WHERE id = 2')->fetch();
            Assert::same(['titre' => 'Metropolis', 'annee' => 1927, 'genre_id' => 1], $row);
        } finally {
            $cleanUp();
        }
    },

    'un film invalide réaffiche le formulaire avec l\'erreur' => function () use ($call, $database, $cleanUp): void {
        $pdo = $database();
        try {
            $html = $call(FilmController::class, 'addAction', [], ['titre' => '', 'annee' => '1990']);
            Assert::contains('Le titre est obligatoire.', $html);
            Assert::contains('value="1990"', $html, 'ce qui a été tapé est remis dans le champ');
            Assert::same(1, (int) $pdo->query('SELECT COUNT(*) FROM films')->fetchColumn(), 'film enregistré');
        } finally {
            $cleanUp();
        }
    },

    'modifier un film, sans toucher à son id' => function () use ($call, $database, $cleanUp): void {
        $pdo = $database();
        try {
            $form = $call(FilmController::class, 'editAction', ['id' => '1']);
            Assert::contains('value="Alien"', $form, 'le formulaire est pré-rempli');

            // Un champ "id" glissé dans le formulaire est ignoré.
            $call(FilmController::class, 'editAction', ['id' => '1'], [
                'id' => '5', 'titre' => 'Aliens', 'annee' => '', 'genre_id' => '2',
            ]);
            $row = $pdo->query('SELECT id, titre, annee, genre_id FROM films')->fetch();
            Assert::same(['id' => 1, 'titre' => 'Aliens', 'annee' => null, 'genre_id' => 2], $row);
        } finally {
            $cleanUp();
        }
    },

    'supprimer un film : seulement en POST' => function () use ($call, $database, $cleanUp): void {
        $pdo = $database();
        try {
            $call(FilmController::class, 'deleteAction', ['id' => '1']); // GET : rien ne se passe
            Assert::same(1, (int) $pdo->query('SELECT COUNT(*) FROM films')->fetchColumn(), 'supprimé en GET');

            $html = $call(FilmController::class, 'deleteAction', ['id' => '1'], [], true);
            Assert::contains('Redirection vers <a href="/">', $html);
            Assert::same(0, (int) $pdo->query('SELECT COUNT(*) FROM films')->fetchColumn(), 'film non supprimé');
        } finally {
            $cleanUp();
        }
    },

    'genres : liste, ajout, modification' => function () use ($call, $database, $cleanUp): void {
        $pdo = $database();
        try {
            Assert::contains('Science-fiction (1 film(s))', $call(GenreController::class, 'indexAction'));

            $call(GenreController::class, 'addAction', [], ['nom' => 'Drame']);
            // Les vues du moteur de templates échappent avec htmlentities() :
            // "é" devient "&eacute;". html_entity_decode() fait l'inverse,
            // pour comparer avec le texte lisible.
            $html = $call(GenreController::class, 'addAction', [], ['nom' => 'Drame']);
            Assert::contains('Ce genre existe déjà.', html_entity_decode($html));

            $call(GenreController::class, 'editAction', ['id' => '2'], ['nom' => 'Comédie musicale']);
            $names = $pdo->query('SELECT nom FROM genres ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
            Assert::same(['Science-fiction', 'Comédie musicale', 'Drame'], $names);
        } finally {
            $cleanUp();
        }
    },

    'un genre qui a encore des films n\'est pas supprimé' => function () use ($call, $database, $cleanUp): void {
        $pdo = $database();
        try {
            $call(GenreController::class, 'deleteAction', ['id' => '1'], [], true);
            Assert::contains('contient encore 1 film(s)', $_SESSION['flash']['message'] ?? '');

            $call(GenreController::class, 'deleteAction', ['id' => '2'], [], true);
            $names = $pdo->query('SELECT nom FROM genres')->fetchAll(PDO::FETCH_COLUMN);
            Assert::same(['Science-fiction'], $names);
        } finally {
            $cleanUp();
        }
    },

    'historique : ajouter un film vu, puis le retirer' => function () use ($call, $database, $cleanUp): void {
        $pdo = $database();
        try {
            $html = $call(HistoriqueController::class, 'addAction', ['film_id' => '1'], [], true);
            Assert::contains('Redirection vers <a href="/film/show?id=1">', $html);
            $row = $pdo->query('SELECT user_id, film_id FROM historiques')->fetch();
            Assert::same(['user_id' => 1, 'film_id' => 1], $row);

            // Le profil affiche le film vu (relation 'has one film').
            Assert::contains('Alien</a>', $call(UserController::class, 'showAction'));

            $call(HistoriqueController::class, 'deleteAction', ['id' => '1'], [], true);
            Assert::same(0, (int) $pdo->query('SELECT COUNT(*) FROM historiques')->fetchColumn());
        } finally {
            $cleanUp();
        }
    },

    'historique : impossible de retirer la ligne d\'un autre membre' => function () use (
        $call,
        $database,
        $cleanUp
    ): void {
        $pdo = $database();
        try {
            $pdo->exec("INSERT INTO historiques (user_id, film_id, vu_le) VALUES (2, 1, '2026-01-01 10:00:00')");
            $call(HistoriqueController::class, 'deleteAction', ['id' => '1'], [], true);

            Assert::same(1, (int) $pdo->query('SELECT COUNT(*) FROM historiques')->fetchColumn(), 'ligne supprimée');
            Assert::same("Cette ligne d'historique n'existe pas.", $_SESSION['flash']['message'] ?? null);
        } finally {
            $cleanUp();
        }
    },

    'modifier son profil : email, et mot de passe facultatif' => function () use ($call, $database, $cleanUp): void {
        $pdo = $database();
        try {
            $html = $call(UserController::class, 'editAction', [], ['email' => 'autre@b.c', 'password' => '']);
            Assert::contains('Un compte existe déjà avec cet email.', html_entity_decode($html));

            $html = $call(UserController::class, 'editAction', [], ['email' => 'nouveau@b.c', 'password' => '']);
            Assert::contains('Redirection vers <a href="/user/show">', $html);
            $user = $pdo->query('SELECT email, password FROM users WHERE id = 1')->fetch();
            Assert::same('nouveau@b.c', $user['email']);
            Assert::true(password_verify('motdepasse', $user['password']), 'mot de passe changé alors que vide');

            $call(UserController::class, 'editAction', [], ['email' => 'nouveau@b.c', 'password' => 'autremotdepasse']);
            $hash = $pdo->query('SELECT password FROM users WHERE id = 1')->fetchColumn();
            Assert::true(password_verify('autremotdepasse', $hash), 'nouveau mot de passe mal enregistré');
        } finally {
            $cleanUp();
        }
    },

    'supprimer son compte : confirmation, puis compte et historique effacés' => function () use (
        $call,
        $database,
        $cleanUp
    ): void {
        $pdo = $database();
        try {
            $pdo->exec("INSERT INTO historiques (user_id, film_id, vu_le) VALUES (1, 1, '2026-01-01 10:00:00')");

            $html = $call(UserController::class, 'deleteAction'); // GET : la confirmation
            Assert::contains('Oui, supprimer mon compte', $html);
            Assert::same(2, (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(), 'supprimé en GET');

            $html = $call(UserController::class, 'deleteAction', [], [], true);
            Assert::contains('Votre compte a été supprimé.', $html);
            Assert::same(1, (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(), 'compte non supprimé');
            Assert::same(0, (int) $pdo->query('SELECT COUNT(*) FROM historiques')->fetchColumn(), 'historique resté');
            Assert::same([], $_SESSION, 'le membre est encore connecté');
        } finally {
            $cleanUp();
        }
    },
];
