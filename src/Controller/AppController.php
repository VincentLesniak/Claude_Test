<?php

// Namespace "Controller" : rangée dans src/Controller/. PSR-12 impose un
// namespace pour chaque classe ; l'autoloader la retrouve grâce au nom court.
namespace Controller;

use Core\Controller;

/**
 * Controller par défaut : utilisé quand l'URL ne précise pas de controller
 * (ex. http://localhost/PiePHP/ appelle AppController::indexAction()).
 */
class AppController extends Controller
{
    public function indexAction()
    {
        echo __CLASS__ . '::' . __FUNCTION__ . ' [OK]' . PHP_EOL;
    }
}
