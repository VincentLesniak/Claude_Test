<?php

/**
 * Tests de la classe Core\Router (routes déclarées avec connect()).
 */

use Core\Router;
use Tests\Assert;

require_once dirname(__DIR__) . '/Core/autoload.php';

return [
    'get() renvoie la route enregistrée par connect()' => function (): void {
        Router::connect('/test-router', ['controller' => 'user', 'action' => 'show']);
        Assert::same(['controller' => 'user', 'action' => 'show'], Router::get('/test-router'));
    },

    'les "/" au début et à la fin ne comptent pas' => function (): void {
        Router::connect('test-slash/', ['controller' => 'app', 'action' => 'index']);
        Assert::same(['controller' => 'app', 'action' => 'index'], Router::get('/test-slash'));
        Assert::same(['controller' => 'app', 'action' => 'index'], Router::get('/test-slash/'));
    },

    'get() renvoie null pour une URL non déclarée' => function (): void {
        Assert::same(null, Router::get('/route-jamais-declaree'));
    },
];
