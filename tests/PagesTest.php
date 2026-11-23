<?php

/**
 * Tests "fonctionnels" : on interroge le site comme le ferait un navigateur,
 * via le serveur intégré de PHP (voir tests/Server.php).
 */

use Tests\Assert;
use Tests\Server;

/**
 * Récupère une page et la donne à DOMDocument.
 * DOMDocument lit le HTML comme un navigateur et permet de chercher
 * des balises précises au lieu de comparer du texte.
 */
$dom = function (string $path): DOMDocument {
    $dom = new DOMDocument();
    $dom->loadHTML(Server::request($path), LIBXML_NOERROR | LIBXML_NOWARNING);
    return $dom;
};

return [
    'la page d\'accueil affiche la vue App/index dans le layout' => function (): void {
        $html = Server::request('/');
        Assert::contains('<!doctype html>', $html, 'le layout est absent');
        // Mise à jour (partie « My cinema by PiePHP ») : l'accueil liste les
        // films, sous le titre « my_cinema ».
        Assert::contains('<h1>my_cinema</h1>', $html, 'la vue est absente');
    },

    // php -S renvoie vers index.php toute URL qui ne correspond à aucun
    // fichier : on peut donc tester le routage "/controller/action".
    '/user/add affiche le formulaire d\'inscription' => function (): void {
        Assert::contains('<h1>Inscription</h1>', Server::request('/user/add'));
    },

    'une page inexistante affiche la 404' => function (): void {
        Assert::contains('<h1>404</h1>', Server::request('/page/inexistante'));
    },

    'index.php ne produit ni erreur ni avertissement PHP' => function (): void {
        foreach (['/index.php', '/user', '/user/login', '/user/register', '/user/show'] as $path) {
            $body = Server::request($path);
            foreach (['Fatal error', 'Warning', 'Notice', 'Deprecated'] as $error) {
                Assert::true(strpos($body, $error) === false, "$path affiche un(e) \"$error\"");
            }
        }
    },

    'le formulaire de connexion est un POST vers /user/login' => function () use ($dom): void {
        $forms = $dom('/user/login')->getElementsByTagName('form');

        Assert::same(1, $forms->length, 'nombre de formulaires');
        Assert::same('post', strtolower($forms->item(0)->getAttribute('method')), 'method du formulaire');
        Assert::same('/user/login', $forms->item(0)->getAttribute('action'), 'action du formulaire');
    },

    'les formulaires de connexion et d\'inscription ont les champs email et password' => function () use ($dom): void {
        foreach (['/user/login', '/user/register'] as $path) {
            $types = [];
            foreach ($dom($path)->getElementsByTagName('input') as $input) {
                $types[$input->getAttribute('name')] = $input->getAttribute('type');
            }
            Assert::same('email', $types['email'] ?? null, "champ email de $path");
            Assert::same('password', $types['password'] ?? null, "champ password de $path");
        }
    },

    'le formulaire d\'inscription envoie vers registerAction' => function () use ($dom): void {
        $form = $dom('/user/register')->getElementsByTagName('form')->item(0);
        Assert::same('/user/register', $form->getAttribute('action'), 'action du formulaire');
    },

    'chaque page a les bases d\'un HTML valide, et une seule fois' => function (): void {
        foreach (['/', '/user', '/user/login', '/user/register', '/user/show'] as $path) {
            $html = Server::request($path);
            // substr_count compte les occurrences : si une vue contenait
            // encore son propre <html>, on en trouverait deux.
            Assert::same(1, substr_count($html, '<html lang="fr">'), "balise <html> de $path");
            Assert::contains('<meta charset="UTF-8">', $html, $path);
            Assert::true((bool) preg_match('#<title>[^<]+</title>#', $html), "<title> vide ou absent sur $path");
        }
    },
];
