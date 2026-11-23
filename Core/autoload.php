<?php

/*
 * Autoloader : PHP appelle cette fonction automatiquement la première fois
 * qu'on utilise une classe qu'il ne connaît pas encore (new, extends, ::...).
 * On lui dit où trouver le fichier, il l'inclut, et on n'écrit plus de require.
 */
spl_autoload_register(function ($class) {
    $root = dirname(__DIR__);

    // 1) Classe avec namespace : "Core\Router" -> Core/Router.php
    //    Le namespace correspond au chemin du dossier.
    $file = $root . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $class) . '.php';
    if (is_file($file)) {
        require_once $file;
        return;
    }

    // 2) Classe sans namespace (UserController, UserModel...) : on la cherche
    //    dans les dossiers de l'application.
    foreach (['Controller', 'Model'] as $folder) {
        $file = implode(DIRECTORY_SEPARATOR, [$root, 'src', $folder, $class . '.php']);
        if (is_file($file)) {
            require_once $file;
            return;
        }
    }
    // Si rien n'est trouvé, on ne fait rien : class_exists() renverra false,
    // ce qui permet au Core d'afficher une 404 proprement.
});
