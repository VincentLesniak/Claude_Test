<?php

// Namespace "Controller" : la classe est rangée dans src/Controller/.
// PSR-12 impose un namespace pour chaque classe ; l'autoloader la retrouve
// grâce à son nom court (AppController.php dans src/Controller/).
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
    /**
     * Page d'accueil : affiche src/View/App/index.php dans le layout.
     */
    public function indexAction()
    {
        // render() est héritée de Core\Controller. Le dossier "App" est
        // déduit du nom de la classe (AppController -> src/View/App/).
        // La clé 'title' devient la variable $title, utilisée par le layout
        // pour la balise <title>.
        $this->render('index', ['title' => 'Accueil']);
    }
}
