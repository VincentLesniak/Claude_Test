<?php

/**
 * Tests "fonctionnels" : on interroge le site comme le ferait un navigateur,
 * via le serveur intégré de PHP (voir tests/Server.php).
 */

use Tests\Assert;
use Tests\Server;

return [
    'la page d\'accueil lance l\'application' => function (): void {
        Assert::contains('Core\Core [OK]', Server::request('/'));
    },

    'index.php ne produit ni erreur ni avertissement PHP' => function (): void {
        $body = Server::request('/index.php');
        foreach (['Fatal error', 'Warning', 'Notice', 'Deprecated'] as $error) {
            Assert::true(strpos($body, $error) === false, "index.php affiche un(e) \"$error\"");
        }
    },

    'le formulaire de connexion est un POST vers index.php' => function (): void {
        $html = Server::request('/src/View/User/login.php');

        // DOMDocument lit le HTML comme un navigateur et permet de chercher
        // des balises précises au lieu de comparer du texte.
        $dom = new DOMDocument();
        $dom->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
        $forms = $dom->getElementsByTagName('form');

        Assert::same(1, $forms->length, 'nombre de formulaires');
        Assert::same('post', strtolower($forms->item(0)->getAttribute('method')), 'method du formulaire');
        Assert::contains('index.php', $forms->item(0)->getAttribute('action'), 'action du formulaire');
    },

    'le formulaire contient les champs email et password' => function (): void {
        $dom = new DOMDocument();
        $dom->loadHTML(Server::request('/src/View/User/login.php'), LIBXML_NOERROR | LIBXML_NOWARNING);

        $types = [];
        foreach ($dom->getElementsByTagName('input') as $input) {
            $types[$input->getAttribute('name')] = $input->getAttribute('type');
        }
        Assert::same('email', $types['email'] ?? null, 'champ email');
        Assert::same('password', $types['password'] ?? null, 'champ password');
    },

    'la page de connexion a les bases d\'un HTML valide' => function (): void {
        $html = Server::request('/src/View/User/login.php');
        Assert::contains('<!DOCTYPE html>', $html);
        Assert::contains('<html lang="fr">', $html);
        Assert::contains('<meta charset="UTF-8">', $html);
        Assert::true((bool) preg_match('#<title>[^<]+</title>#', $html), 'balise <title> vide ou absente');
    },
];
