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

    'exemple.config.local.json a les mêmes clés que config.json, sans vrai secret' => function () use ($root): void {
        $example = json_decode(file_get_contents($root . '/exemple.config.local.json'), true);
        $config = json_decode(file_get_contents($root . '/config.json'), true);
        Assert::true(is_array($example), 'exemple.config.local.json n\'est pas un JSON valide');

        // Mêmes clés : une fois copié en config.local.json, Core\Database y
        // trouve tout ce dont il a besoin.
        $keys = array_keys($config['database']);
        sort($keys);
        $exampleKeys = array_keys($example['database'] ?? []);
        sort($exampleKeys);
        Assert::same($keys, $exampleKeys, 'clés de la section database');

        // Les identifiants sont des valeurs à remplacer, pas de vrais secrets.
        Assert::contains('TON_', $example['database']['user'], 'user');
        Assert::contains('TON_', $example['database']['password'], 'password');
    },

    'robots.txt s\'adresse aux robots et protège Core/ et src/' => function () use ($root): void {
        $robots = file_get_contents($root . '/robots.txt');
        Assert::contains('User-agent:', $robots);
        Assert::contains('Disallow: /Core/', $robots);
        Assert::contains('Disallow: /src/', $robots);
    },
];
