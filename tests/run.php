<?php

// phpcs:disable PSR1.Files.SideEffects
// Comme index.php, ce fichier définit une constante ET exécute du code :
// on désactive la règle PSR-1 correspondante pour lui seul.

/**
 * tests/run.php : lance tous les tests du projet.
 *
 * Utilisation, depuis la racine du projet :   php tests/run.php
 *
 * Le lanceur trouve TOUT SEUL les fichiers tests/*Test.php : pour ajouter
 * des tests, il suffit de créer un nouveau fichier XxxTest.php, rien à
 * modifier ici. Chaque fichier de test renvoie (return) un tableau
 * "nom du test" => fonction. Une fonction qui se termine sans exception
 * est un test réussi.
 *
 * Le script se termine avec le code 0 si tout passe, 1 sinon : c'est ce
 * code que GitHub Actions regarde pour afficher une croix rouge ou une
 * coche verte.
 */

// Les vues utilisent la constante BASE_URI, normalement définie par
// index.php. Les tests qui appellent Core ou un controller directement ne
// passent pas par index.php : on la définit ici, avec la valeur d'un projet
// installé à la racine du serveur ("" : pas de préfixe dans les URL).
define('BASE_URI', '');

require_once __DIR__ . '/Assert.php';
require_once __DIR__ . '/Server.php';

$passed = 0;
$failed = 0;

// glob() liste les fichiers correspondant au motif, triés par nom.
foreach (glob(__DIR__ . '/*Test.php') as $file) {
    echo basename($file) . PHP_EOL;
    $tests = require $file;

    foreach ($tests as $name => $test) {
        try {
            $test();
            echo "  [OK]    $name" . PHP_EOL;
            $passed++;
        } catch (Throwable $e) {
            // Throwable attrape les exceptions ET les erreurs PHP fatales
            // "rattrapables" (TypeError, Error...).
            echo "  [ECHEC] $name" . PHP_EOL . '          ' . $e->getMessage() . PHP_EOL;
            $failed++;
        }
    }
}

echo PHP_EOL . "$passed test(s) réussi(s), $failed échec(s)." . PHP_EOL;
exit($failed === 0 ? 0 : 1);
