<?php

// Namespace "Controller" : la classe est rangée dans src/Controller/.
// PSR-12 impose un namespace pour chaque classe ; l'autoloader la retrouve
// grâce à son nom court (UserController.php dans src/Controller/).
namespace Controller;

// "use" importe la classe mère Core\Controller : dans ce fichier, le mot
// "Controller" seul désigne donc Core\Controller (et pas le namespace).
use Core\Controller;

/**
 * Controller de l'entité "user" (avec UserModel et la table users).
 *
 * Convention de nommage utilisée par le routeur :
 *   URL /user/login  ->  classe UserController  ->  méthode loginAction()
 * Le suffixe "Action" distingue les méthodes appelables depuis une URL des
 * autres méthodes de la classe : une méthode helper() ne sera jamais
 * accessible par /user/helper, car le Core cherche "helperAction".
 *
 * Chaque action se contente pour l'instant d'afficher sa vue de
 * src/View/User/ avec render(). Le traitement des formulaires (enregistrer
 * un utilisateur, vérifier un mot de passe) viendra à l'étape "Mise en
 * pratique", avec le model.
 */
class UserController extends Controller
{
    /**
     * /user : page d'accueil de l'espace utilisateur.
     */
    public function indexAction()
    {
        $this->render('index', ['title' => 'Utilisateurs']);
    }

    /**
     * /user/add : l'URL d'exemple du sujet pour le routage. "Ajouter un
     * utilisateur", c'est l'inscrire : on affiche le même formulaire que
     * registerAction(). Une même vue peut servir à plusieurs actions.
     */
    public function addAction()
    {
        $this->render('register', ['title' => 'Inscription']);
    }

    /**
     * /user/register (ou /inscription, voir src/routes.php) : formulaire
     * d'inscription.
     */
    public function registerAction()
    {
        $this->render('register', ['title' => 'Inscription']);
    }

    /**
     * /user/login : formulaire de connexion.
     */
    public function loginAction()
    {
        $this->render('login', ['title' => 'Connexion']);
    }

    /**
     * /user/show : le profil d'un utilisateur.
     *
     * Exemple de passage de données à une vue : le tableau 'user' devient la
     * variable $user dans src/View/User/show.php. Comme on ne lit pas encore
     * la base de données, ce sont des données d'exemple écrites en dur ; plus
     * tard, elles viendront de UserModel.
     */
    public function showAction()
    {
        $user = ['id' => 1, 'email' => 'exemple@piephp.fr'];

        $this->render('show', ['title' => 'Profil', 'user' => $user]);
    }
}
