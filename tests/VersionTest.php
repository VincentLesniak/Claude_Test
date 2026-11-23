<?php

/**
 * Tests du versionnage : le fichier VERSION et le CHANGELOG restent
 * cohérents entre eux.
 *
 * Pourquoi tester ça ? Parce que c'est exactement le genre d'oubli qu'on
 * fait en fin de soirée : changer la version sans mettre à jour le journal
 * (ou l'inverse). Le CI le détecte à notre place.
 */

use Tests\Assert;

$root = dirname(__DIR__);

return [
    'VERSION contient un numéro sémantique MAJEUR.MINEUR.CORRECTIF' => function () use ($root): void {
        $version = trim(file_get_contents($root . '/VERSION'));
        // ^ et $ : la ligne entière doit correspondre ; \d+ : un ou plusieurs chiffres.
        Assert::true(
            (bool) preg_match('/^\d+\.\d+\.\d+$/', $version),
            "VERSION doit ressembler à 1.2.3, trouvé \"$version\""
        );
    },

    'le CHANGELOG a une section pour la version courante' => function () use ($root): void {
        $version = trim(file_get_contents($root . '/VERSION'));
        Assert::contains("## [$version]", file_get_contents($root . '/CHANGELOG.md'), 'CHANGELOG.md');
    },

    'le CHANGELOG garde une section « Non publié » pour la suite' => function () use ($root): void {
        Assert::contains('## [Non publié]', file_get_contents($root . '/CHANGELOG.md'), 'CHANGELOG.md');
    },
];
