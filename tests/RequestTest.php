<?php

/**
 * Tests de Core\Request : récupération et nettoyage de GET et POST.
 *
 * Request lit les superglobales $_GET, $_POST et $_SERVER. En ligne de
 * commande elles sont vides : on les remplit nous-mêmes avant chaque test,
 * puis on les remet à zéro (finally) pour ne pas gêner les tests suivants.
 */

use Core\Request;
use Tests\Assert;

require_once dirname(__DIR__) . '/Core/autoload.php';

/**
 * Crée une Request comme si le navigateur avait envoyé $get, $post et la
 * méthode $method.
 */
$makeRequest = function (array $get = [], array $post = [], string $method = 'GET'): Request {
    $_GET = $get;
    $_POST = $post;
    $_SERVER['REQUEST_METHOD'] = $method;
    try {
        return new Request();
    } finally {
        $_GET = [];
        $_POST = [];
        unset($_SERVER['REQUEST_METHOD']);
    }
};

return [
    'trim retire les espaces autour des valeurs' => function () use ($makeRequest): void {
        $request = $makeRequest([], ['email' => "  a@b.c \n"], 'POST');
        Assert::same('a@b.c', $request->getPost('email'));
    },

    'stripslashes retire les antislashs' => function () use ($makeRequest): void {
        $request = $makeRequest(['nom' => "l\\'exemple"]);
        Assert::same('l&#039;exemple', $request->getQuery('nom'), 'antislash retiré, apostrophe échappée');
    },

    'htmlspecialchars neutralise les balises HTML' => function () use ($makeRequest): void {
        $request = $makeRequest(['q' => '<script>alert(1)</script>']);
        Assert::same('&lt;script&gt;alert(1)&lt;/script&gt;', $request->getQuery('q'));
    },

    'les sous-tableaux (name="tags[]") sont nettoyés aussi' => function () use ($makeRequest): void {
        $request = $makeRequest([], ['tags' => [' php ', '<b>']], 'POST');
        Assert::same(['php', '&lt;b&gt;'], $request->getPost('tags'));
    },

    'un paramètre absent renvoie la valeur par défaut' => function () use ($makeRequest): void {
        $request = $makeRequest();
        Assert::same(null, $request->getPost('email'));
        Assert::same('', $request->getPost('email', ''));
        Assert::same([], $request->getQuery());
    },

    'isPost() distingue GET et POST' => function () use ($makeRequest): void {
        Assert::same(false, $makeRequest()->isPost(), 'requête GET');
        Assert::same(true, $makeRequest([], [], 'post')->isPost(), 'requête POST (minuscules)');
    },

    'sans REQUEST_METHOD (ligne de commande), la requête est un GET' => function (): void {
        unset($_SERVER['REQUEST_METHOD']);
        Assert::same(false, (new Request())->isPost());
    },

    'getQueryParams() réunit GET et POST, POST gagne' => function () use ($makeRequest): void {
        $request = $makeRequest(['id' => '3', 'email' => 'url@b.c'], ['email' => 'form@b.c'], 'POST');
        Assert::same(['id' => '3', 'email' => 'form@b.c'], $request->getQueryParams());
    },

    'chaque controller reçoit une Request dans son constructeur' => function (): void {
        $controller = new Controller\AppController();
        // La propriété est protected : Closure::call() permet de la lire
        // "depuis l'intérieur" de l'objet.
        $getRequest = function () {
            return $this->request;
        };
        Assert::true($getRequest->call($controller) instanceof Request, '$this->request n\'est pas une Request');
    },
];
