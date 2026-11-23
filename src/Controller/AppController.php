<?php

// Namespace "Controller" : la classe est rangée dans src/Controller/.
// PSR-12 impose un namespace pour chaque classe ; l'autoloader la retrouve
// grâce à son nom court (UserController.php dans src/Controller/).
namespace Controller;

// "use" importe la classe mère Core\Controller : dans ce fichier, le mot
// "Controller" seul désigne donc Core\Controller (et pas le namespace).
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
