<?php

/**
 * Tests de la classe Core\Core : run() doit confier la requête au bon
 * controller selon l'URL.
 *
 * Ici on n'utilise pas de serveur HTTP : on simule la requête en remplissant
 * nous-mêmes $_SERVER['REQUEST_URI'] et $_GET, comme le ferait Apache.
 */

use Tests\Assert;

require_once dirname(__DIR__) . '/Core/autoload.php';

/**
 * Lance Core::run() pour une URL donnée et renvoie ce qui a été affiché.
 * ob_start() "capture" les echo, ob_get_clean() les récupère en texte.
 */
$run = function (string $uri, array $get = []): string {
    $_SERVER['REQUEST_URI'] = $uri;
    $_GET = $get;
    ob_start();
    (new Core\Core())->run();
    return ob_get_clean();
};

return [
    '"/" appelle AppController::indexAction()' => function () use ($run): void {
        Assert::contains('Controller\AppController::indexAction [OK]', $run('/'));
    },

    '"/user/add" appelle UserController::addAction()' => function () use ($run): void {
        Assert::contains('Controller\UserController::addAction [OK]', $run('/user/add'));
    },

    '"/user" utilise l\'action par défaut index' => function () use ($run): void {
        Assert::contains('Controller\UserController::indexAction [OK]', $run('/user'));
    },

    'les "/" en trop et les ?paramètres sont ignorés' => function () use ($run): void {
        Assert::contains('Controller\UserController::addAction [OK]', $run('/user/add/?x=1'));
    },

    '"?c=user&a=add" fonctionne aussi' => function () use ($run): void {
        $output = $run('/', ['c' => 'user', 'a' => 'add']);
        Assert::contains('Controller\UserController::addAction [OK]', $output);
    },

    'une route déclarée dans src/routes.php est prioritaire' => function () use ($run): void {
        Assert::contains('Controller\UserController::addAction [OK]', $run('/inscription'));
    },

    'un controller inconnu affiche la 404' => function () use ($run): void {
        Assert::contains('<h1>404</h1>', $run('/nimportequoi'));
    },

    'une action inconnue affiche la 404' => function () use ($run): void {
        Assert::contains('<h1>404</h1>', $run('/user/supprimer'));
    },

    'un nom avec des caractères spéciaux est refusé (404)' => function () use ($run): void {
        Assert::contains('<h1>404</h1>', $run('/..%2F..%2Fetc/passwd'));
        Assert::contains('<h1>404</h1>', $run('/', ['c' => '../Core/Core']));
        Assert::contains('<h1>404</h1>', $run('/', ['c' => ['tableau']]));
    },
];
