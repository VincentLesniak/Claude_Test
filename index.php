<?php

// phpcs:disable PSR1.Files.SideEffects
// PSR-1 demande qu'un fichier soit SOIT déclaratif (il définit des
// constantes, classes, fonctions), SOIT exécutant (il "fait" quelque
// chose : echo, require, new...). Or define() compte comme une
// déclaration, et ce fichier lance aussi l'application. C'est normal pour
// un point d'entrée : on désactive donc cette règle pour ce fichier seul.

/**
 * index.php : point d'entrée UNIQUE de l'application.
 *
 * Toutes les requêtes passent par ce fichier (grâce au .htaccess).
 * Son rôle est volontairement minuscule : préparer l'environnement,
 * puis donner la main au framework (la classe Core\Core).
 *
 * Rappel de l'étape 2 : pour revoir le contenu des superglobales, il
 * suffit de remettre temporairement ces lignes :
 *     echo '<pre>'; print_r($_POST); print_r($_GET); print_r($_SERVER); echo '</pre>';
 */

/*
 * BASE_URI : le chemin du projet vu depuis le navigateur.
 * Exemple avec le projet dans /var/www/html/PiePHP :
 *   __DIR__                  = '/var/www/html/PiePHP'   (dossier de ce fichier)
 *   $_SERVER['DOCUMENT_ROOT'] = '/var/www/html'          (racine d'Apache)
 *   substr(__DIR__, strlen(DOCUMENT_ROOT)) coupe le début -> '/PiePHP'
 * str_replace('\\', '/', ...) remplace les "\" de Windows par des "/".
 * (Dans une chaîne PHP, '\\' représente UN seul antislash.)
 * Le routeur s'en servira pour retirer '/PiePHP' de l'URL demandée.
 */
define('BASE_URI', str_replace('\\', '/', substr(__DIR__, strlen($_SERVER['DOCUMENT_ROOT']))));

/*
 * On inclut l'autoloader. implode() colle les morceaux avec le bon
 * séparateur de dossiers : 'Core/autoload.php' sous Linux,
 * 'Core\autoload.php' sous Windows.
 * C'est le SEUL require du projet : ensuite, toutes les classes seront
 * chargées automatiquement.
 *
 * Essaie de commenter cette ligne : tu obtiendras l'erreur demandée par le
 * sujet, "Fatal error: Class 'Core\Core' not found".
 */
require_once implode(DIRECTORY_SEPARATOR, ['Core', 'autoload.php']);

// On crée l'application (l'autoloader charge Core/Core.php à ce moment-là)
// puis on la lance : Core::run() confie la requête au routeur, qui appelle
// le bon controller (ex. /user/add -> UserController::addAction()).
$app = new Core\Core();
$app->run();
