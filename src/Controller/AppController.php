<?php

// Namespace "Controller" : la classe est rangée dans src/Controller/.
// PSR-12 impose un namespace pour chaque classe ; l'autoloader la retrouve
// grâce à son nom court (UserController.php dans src/Controller/).
namespace Controller;

// "use" importe la classe mère Core\Controller : dans ce fichier, le mot
// "Controller" seul désigne donc Core\Controller (et pas le namespace).
use Core\Controller;
use Model\FilmModel;
use PDOException;
use RuntimeException;

/**
 * Controller par défaut : utilisé quand l'URL ne précise pas de controller
 * (ex. http://localhost/PiePHP/ appelle AppController::indexAction()).
 */
class AppController extends Controller
{
    /**
     * Page d'accueil : affiche src/View/App/index.php dans le layout.
     *
     * Mise à jour (partie « My cinema by PiePHP ») : la page d'accueil est
     * la liste des films du mini-site my_cinema.
     *
     * Pourquoi ? C'est la première page que voit le visiteur : le sujet y
     * veut les films de la base.
     * Pertinence : AppController reste le controller par défaut (URL "/"),
     * il demande simplement les films à FilmModel.
     */
    public function indexAction()
    {
        // render() est héritée de Core\Controller. Le dossier "App" est
        // déduit du nom de la classe (AppController -> src/View/App/).
        // La clé 'title' devient la variable $title, utilisée par le layout
        // pour la balise <title>.
        // Mise à jour (partie « My cinema by PiePHP ») : le sujet demande
        // d'« afficher les films de la BDD sur une page d'accueil ».
        // find() hérité d'Entity, trié par titre : chaque film arrive avec
        // son entrée 'genre' (relation 'has one genre' de FilmModel).
        // Si la base ne répond pas, la page s'affiche quand même, avec un
        // message clair au lieu d'une erreur PHP.
        $scope = ['title' => 'Accueil', 'films' => []];
        try {
            $scope['films'] = (new FilmModel())->find(['ORDER BY' => 'titre ASC']);
        } catch (PDOException | RuntimeException $e) {
            $scope['error'] = $this->databaseError();
        }
        $this->render('index', $scope);
    }
}
