<?php

// BASE_URI : chemin du projet vu depuis l'URL (ex. "/PiePHP").
// __DIR__ = dossier de ce fichier sur le disque, DOCUMENT_ROOT = racine web
// d'Apache. En retirant l'un de l'autre on obtient le chemin dans l'URL.
// Le str_replace remplace les "\" de Windows par des "/".
define('BASE_URI', str_replace('\\', '/', substr(__DIR__, strlen($_SERVER['DOCUMENT_ROOT']))));

require_once implode(DIRECTORY_SEPARATOR, ['Core', 'autoload.php']);

$app = new Core\Core();
$app->run();
