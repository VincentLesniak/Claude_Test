<?php

/**
 * Core/autoload.php : chargement automatique des classes.
 *
 * LE PROBLÈME : sans autoloader, il faudrait écrire un require_once pour
 * CHAQUE fichier de classe utilisé. Avec des dizaines de classes, c'est
 * vite ingérable (et un oubli = "Fatal error: Class not found").
 *
 * LA SOLUTION : spl_autoload_register() enregistre une fonction que PHP
 * appellera AUTOMATIQUEMENT la première fois qu'on utilise une classe qu'il
 * ne connaît pas encore (new, appel statique, extends...). PHP lui passe le
 * nom complet de la classe, par exemple "Core\Core". À nous de trouver le
 * fichier correspondant et de l'inclure.
 *
 * Déroulé avec $app = new Core\Core(); dans index.php :
 *  1. PHP ne connaît pas Core\Core -> il appelle notre fonction avec
 *     $className = 'Core\Core'
 *  2. on construit le chemin  <racine>/Core/Core.php
 *  3. le fichier existe -> require_once -> la classe est définie
 *  4. PHP peut enfin faire le "new".
 */

spl_autoload_register(function (string $className): void {
    // dirname(__DIR__) : __DIR__ est le dossier de CE fichier (Core/),
    // dirname() remonte d'un niveau -> la racine du projet PiePHP/.
    $root = dirname(__DIR__);

    // 1er essai : le namespace correspond aux dossiers.
    // Les namespaces utilisent "\" alors que les chemins Linux utilisent
    // "/" : on remplace par DIRECTORY_SEPARATOR (le bon séparateur selon
    // le système, "/" sous Linux, "\" sous Windows).
    //   'Core\Core' -> <racine>/Core/Core.php
    $file = $root . DIRECTORY_SEPARATOR
        . str_replace('\\', DIRECTORY_SEPARATOR, $className) . '.php';

    if (file_exists($file)) {
        require_once $file;
        return;
    }

    // 2e essai : les classes de l'application, rangées dans src/.
    // Le sujet prévoit des classes comme UserController ou UserModel sans
    // namespace : on les cherche dans chaque dossier de src/.
    // basename() garde seulement la dernière partie du nom
    // (au cas où la classe aurait quand même un namespace).
    $shortName = basename(str_replace('\\', '/', $className));
    $folders = ['Controller', 'Model', 'View'];

    foreach ($folders as $folder) {
        $file = implode(DIRECTORY_SEPARATOR, [$root, 'src', $folder, $shortName . '.php']);
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }

    // Si on arrive ici, on ne fait rien : PHP essaiera les éventuels autres
    // autoloaders, puis lèvera l'erreur "Class not found". Ne pas lancer
    // d'erreur nous-mêmes permet de cohabiter avec d'autres autoloaders
    // (par exemple celui de Composer).
});
