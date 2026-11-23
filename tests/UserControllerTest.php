<?php

/**
 * Tests de l'inscription et de la connexion dans UserController.
 *
 * On simule l'envoi d'un formulaire : on remplit $_POST et
 * $_SERVER['REQUEST_METHOD'], on appelle l'action, puis on lit la page
 * affichée à la destruction du controller. La base est une base SQLite en
 * mémoire (tests/TestDatabase.php) donnée à Core\Database.
 */

use Controller\UserController;
use Core\Database;
use Model\UserModel;
use Tests\Assert;
use Tests\TestDatabase;

require_once dirname(__DIR__) . '/Core/autoload.php';

/**
 * Appelle $action comme si le formulaire avait été envoyé avec $post (ou
 * comme une simple visite en GET si $post est vide), et renvoie la page.
 */
$submit = function (string $action, array $post = []): string {
    $_POST = $post;
    $_SERVER['REQUEST_METHOD'] = $post === [] ? 'GET' : 'POST';
    try {
        // Request est créée dans le constructeur : $_POST doit être rempli AVANT.
        $controller = new UserController();
        ob_start();
        $controller->$action();
        unset($controller);
        return ob_get_clean();
    } finally {
        $_POST = [];
        unset($_SERVER['REQUEST_METHOD']);
    }
};

return [
    'l\'inscription enregistre l\'utilisateur en base' => function () use ($submit): void {
        $pdo = TestDatabase::create();
        try {
            $html = $submit('registerAction', ['email' => ' a@b.c ', 'password' => 'motdepasse']);

            Assert::contains('Votre compte a été créé', $html);
            $user = (new UserModel($pdo))->readByEmail('a@b.c');
            Assert::true($user !== null, 'utilisateur absent de la base (l\'email doit être nettoyé par trim)');
            Assert::true(password_verify('motdepasse', $user['password']), 'mot de passe mal enregistré');
        } finally {
            Database::setConnection(null);
        }
    },

    'l\'inscription refuse un email déjà utilisé' => function () use ($submit): void {
        TestDatabase::create();
        try {
            $submit('registerAction', ['email' => 'a@b.c', 'password' => 'motdepasse']);
            $html = $submit('registerAction', ['email' => 'a@b.c', 'password' => 'autremotdepasse']);
            Assert::contains('Un compte existe déjà avec cet email.', $html);
        } finally {
            Database::setConnection(null);
        }
    },

    'l\'inscription vérifie l\'email et la longueur du mot de passe' => function () use ($submit): void {
        // Pas de base ici : ces erreurs sont détectées avant d'y toucher.
        $html = $submit('registerAction', ['email' => 'pas-un-email', 'password' => 'motdepasse']);
        Assert::contains('Adresse email invalide.', $html);
        Assert::contains('value="pas-un-email"', $html, 'l\'email tapé est remis dans le champ');

        $html = $submit('registerAction', ['email' => 'a@b.c', 'password' => 'court']);
        Assert::contains('au moins 8 caractères', $html);
    },

    'afficher les formulaires (GET) ne touche pas à la base' => function () use ($submit): void {
        // Aucune base configurée : si l'action essayait de s'y connecter, elle
        // afficherait le message "base de données indisponible".
        Database::setConnection(null);
        foreach (['registerAction', 'loginAction'] as $action) {
            $html = $submit($action);
            Assert::true(strpos($html, 'indisponible') === false, "$action a essayé de se connecter");
            Assert::true(strpos($html, 'class="error"') === false, "$action affiche une erreur");
        }
    },

    'une base injoignable affiche un message clair au lieu de planter' => function () use ($submit): void {
        // Un fichier de configuration temporaire qui pointe vers une base
        // SQLite dans un dossier qui n'existe pas : la connexion échoue.
        // tempnam() crée un fichier vide au nom unique dans le dossier temporaire.
        $config = tempnam(sys_get_temp_dir(), 'piephp');
        file_put_contents($config, json_encode([
            'database' => ['driver' => 'sqlite', 'path' => '/dossier/inexistant/base.sqlite'],
        ]));
        Database::useConfigFile($config);
        try {
            $html = $submit('registerAction', ['email' => 'a@b.c', 'password' => 'motdepasse']);
            Assert::contains('La base de données est indisponible.', $html);
        } finally {
            Database::useConfigFile(null);
            unlink($config);
        }
    },

    'la connexion réussit avec le bon mot de passe' => function () use ($submit): void {
        TestDatabase::create();
        try {
            $submit('registerAction', ['email' => 'a@b.c', 'password' => 'motdepasse']);
            $html = $submit('loginAction', ['email' => 'a@b.c', 'password' => 'motdepasse']);

            Assert::contains('Connexion réussie', $html);
            Assert::same(1, $_SESSION['user_id'] ?? null, 'id de l\'utilisateur dans la session');
        } finally {
            $_SESSION = [];
            Database::setConnection(null);
        }
    },

    'la connexion échoue avec un mauvais mot de passe ou un email inconnu' => function () use ($submit): void {
        TestDatabase::create();
        try {
            $submit('registerAction', ['email' => 'a@b.c', 'password' => 'motdepasse']);
            foreach ([['a@b.c', 'mauvaismdp'], ['inconnu@b.c', 'motdepasse']] as [$email, $password]) {
                $html = $submit('loginAction', ['email' => $email, 'password' => $password]);
                Assert::contains('Email ou mot de passe incorrect.', $html, "$email / $password");
                Assert::true(!isset($_SESSION['user_id']), 'utilisateur connecté par erreur');
            }
        } finally {
            Database::setConnection(null);
        }
    },

    'le profil affiche l\'utilisateur connecté, lu en base' => function () use ($submit): void {
        TestDatabase::create();
        try {
            $submit('registerAction', ['email' => 'moi@piephp.fr', 'password' => 'motdepasse']);
            $submit('loginAction', ['email' => 'moi@piephp.fr', 'password' => 'motdepasse']);
            $html = $submit('showAction');

            Assert::contains('moi@piephp.fr', $html);
            Assert::true(strpos($html, 'exemple@piephp.fr') === false, 'profil d\'exemple affiché');
            Assert::true(strpos($html, '$2y$') === false, 'l\'empreinte du mot de passe est affichée');
        } finally {
            $_SESSION = [];
            Database::setConnection(null);
        }
    },

    'la déconnexion vide la session' => function () use ($submit): void {
        $_SESSION = ['user_id' => 1];
        $html = $submit('logoutAction');
        Assert::contains('Vous êtes déconnecté.', $html);
        Assert::same([], $_SESSION);
    },
];
