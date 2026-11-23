<?php

/**
 * Tests des fichiers de configuration à la racine du projet.
 */

use Tests\Assert;

$root = dirname(__DIR__);

return [
    'config.json est un JSON valide avec les paramètres de la base' => function () use ($root): void {
        // json_decode renvoie null si le JSON est mal formé (virgule en trop...).
        $config = json_decode(file_get_contents($root . '/config.json'), true);
        Assert::true(is_array($config), 'config.json n\'est pas un JSON valide');

        foreach (['host', 'port', 'dbname', 'user', 'password'] as $key) {
            Assert::true(array_key_exists($key, $config['database'] ?? []), "clé database.$key absente");
        }
    },

    'robots.txt s\'adresse aux robots et protège Core/ et src/' => function () use ($root): void {
        $robots = file_get_contents($root . '/robots.txt');
        Assert::contains('User-agent:', $robots);
        Assert::contains('Disallow: /Core/', $robots);
        Assert::contains('Disallow: /src/', $robots);
    },
];
