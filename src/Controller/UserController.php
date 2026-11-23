<?php

// Namespace "Controller" : la classe est rangée dans src/Controller/.
// PSR-12 impose un namespace pour chaque classe ; l'autoloader la retrouve
// grâce à son nom court (UserController.php dans src/Controller/).
namespace Controller;

// "use" importe la classe mère Core\Controller : dans ce fichier, le mot
// "Controller" seul désigne donc Core\Controller (et pas le namespace).
use Core\Controller;
use Model\UserModel;
use PDOException;
use RuntimeException;

/**
 * Controller de l'entité "user" (avec UserModel et la table users).
 *
 * Convention de nommage utilisée par le routeur :
 *   URL /user/add  ->  classe UserController  ->  méthode addAction()
 * Le suffixe "Action" distingue les méthodes appelables depuis une URL des
 * autres méthodes de la classe : une méthode helper() ne sera jamais
 * accessible par /user/helper, car le Core cherche "helperAction".
 *
 * Chaque action se contente pour l'instant d'afficher sa vue de
 * src/View/User/ avec render(). Le traitement des formulaires (enregistrer
 * un utilisateur, vérifier un mot de passe) viendra à l'étape "Mise en
 * pratique", avec le model.
 *
 * Mise à jour (étapes "Mise en pratique", "Le Model" et "Request") : c'est
 * fait. registerAction() et loginAction() servent deux fois :
 *   - en GET  (on arrive sur la page)        : elles affichent le formulaire ;
 *   - en POST (on a validé le formulaire)    : elles lisent les champs avec
 *     $this->request, demandent au UserModel d'enregistrer ou de vérifier
 *     l'utilisateur, puis réaffichent la vue avec un message.
 * Le controller ne contient AUCUN SQL : c'est le rôle du model. Il ne
 * contient pas non plus de HTML : c'est le rôle des vues. Il "contrôle".
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
     *
     * Mise à jour (étape "Mise en pratique") : quand le formulaire est
     * envoyé (POST), on suit le sujet : instancier UserModel, récupérer les
     * paramètres POST, mettre à jour les attributs du model, appeler save().
     * Avant d'enregistrer, on vérifie les champs : le "required" et le
     * type="email" du HTML se contournent facilement, seule la vérification
     * côté serveur (ici, en PHP) compte vraiment.
     *
     * Mise à jour (étape « Les entités ») : l'utilisateur est créé comme une
     * entité, d'après l'usage donné par le sujet :
     *   $user = new UserModel($params);
     *   if (!$user->id) { $user->save(); }
     * On donne au constructeur les deux champs déjà nettoyés et vérifiés,
     * plutôt que tout getQueryParams() : on sait ainsi exactement ce qui
     * entre dans l'objet (un champ "id" glissé dans le formulaire ne
     * pourra pas faire charger, puis modifier, le compte de quelqu'un
     * d'autre). Pas d'id dans le tableau = nouvel utilisateur.
     */
    public function registerAction()
    {
        $scope = ['title' => 'Inscription'];

        if ($this->request->isPost()) {
            $email = $this->request->getPost('email', '');
            $password = $this->request->getPost('password', '');

            $scope['email'] = $email;
            $scope['error'] = $this->validate($email, $password);

            if ($scope['error'] === null) {
                try {
                    $user = new UserModel(['email' => $email, 'password' => $password]);
                    // L'email est UNIQUE dans la table : on vérifie avant,
                    // pour afficher un message clair au lieu d'une erreur SQL.
                    if ($user->readByEmail($email) !== null) {
                        $scope['error'] = 'Un compte existe déjà avec cet email.';
                    } else {
                        // Mise à jour (étape « Les entités ») : les attributs
                        // ont été donnés au constructeur, save() les enregistre
                        // et remplit $user->id. Le "if (!$user->id)" du sujet
                        // est inutile ici : sans id dans le tableau, l'entité
                        // est forcément nouvelle.
                        $user->save();
                        $scope['success'] = 'Votre compte a été créé. Vous pouvez vous connecter.';
                        unset($scope['email']);
                    }
                } catch (PDOException | RuntimeException $e) {
                    $scope['error'] = $this->databaseError();
                }
            }
        }

        $this->render('register', $scope);
    }

    /**
     * /user/login : formulaire de connexion.
     *
     * Mise à jour (étape "Mise en pratique", partie "Pour aller plus loin") :
     * en POST, le model vérifie l'email et le mot de passe. S'ils sont bons,
     * on retient l'id de l'utilisateur dans la SESSION.
     *
     * La session, c'est la mémoire du serveur entre deux pages : HTTP oublie
     * tout d'une requête à l'autre. session_start() donne au navigateur un
     * cookie avec un identifiant unique (PHPSESSID) ; à chaque page suivante,
     * PHP retrouve grâce à lui le tableau $_SESSION de ce visiteur.
     *
     * Mise à jour (étape « Les entités ») : l'email et le mot de passe tapés
     * sont donnés au constructeur du model, au lieu de deux setters.
     */
    public function loginAction()
    {
        $scope = ['title' => 'Connexion'];

        if ($this->request->isPost()) {
            $email = $this->request->getPost('email', '');
            $scope['email'] = $email;

            try {
                $user = (new UserModel([
                    'email' => $email,
                    'password' => $this->request->getPost('password', ''),
                ]))->checkCredentials();

                if ($user === null) {
                    // Même message que l'email soit inconnu ou le mot de passe
                    // faux : on ne révèle pas quels emails sont inscrits.
                    $scope['error'] = 'Email ou mot de passe incorrect.';
                } else {
                    $this->startSession();
                    // session_regenerate_id() change l'identifiant de session
                    // à la connexion : un identifiant volé AVANT la connexion
                    // ne sert plus à rien (attaque "fixation de session").
                    if (session_status() === PHP_SESSION_ACTIVE) {
                        session_regenerate_id(true);
                    }
                    $_SESSION['user_id'] = (int) $user['id'];
                    $scope['success'] = 'Connexion réussie. Bienvenue ' . $user['email'] . ' !';
                }
            } catch (PDOException | RuntimeException $e) {
                $scope['error'] = $this->databaseError();
            }
        }

        $this->render('login', $scope);
    }

    /**
     * /user/logout : déconnexion. On vide la session, puis on affiche le
     * formulaire de connexion.
     */
    public function logoutAction()
    {
        $this->startSession();
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }

        $this->render('login', ['title' => 'Connexion', 'success' => 'Vous êtes déconnecté.']);
    }

    /**
     * /user/show : le profil d'un utilisateur.
     *
     * Exemple de passage de données à une vue : le tableau 'user' devient la
     * variable $user dans src/View/User/show.php. Comme on ne lit pas encore
     * la base de données, ce sont des données d'exemple écrites en dur ; plus
     * tard, elles viendront de UserModel.
     *
     * Mise à jour (étape "Le Model") : si un utilisateur est connecté, son
     * profil est lu en base avec UserModel::read(). Sinon, on garde les
     * données d'exemple, avec une invitation à se connecter.
     */
    public function showAction()
    {
        $user = ['id' => 1, 'email' => 'exemple@piephp.fr'];
        $scope = ['title' => 'Profil', 'example' => true];

        $this->startSession();
        if (isset($_SESSION['user_id'])) {
            try {
                $found = (new UserModel())->read((int) $_SESSION['user_id']);
                if ($found !== null) {
                    $user = $found;
                    $scope['example'] = false;
                }
            } catch (PDOException | RuntimeException $e) {
                $scope['error'] = $this->databaseError();
            }
        }

        // On ne transmet à la vue que ce qu'elle affiche : jamais l'empreinte
        // du mot de passe, même hachée.
        $scope['user'] = ['id' => $user['id'], 'email' => $user['email']];
        $this->render('show', $scope);
    }

    /**
     * Vérifie les champs du formulaire d'inscription.
     *
     * filter_var(..., FILTER_VALIDATE_EMAIL) renvoie false si le texte n'a
     * pas la forme d'une adresse email.
     *
     * @return string|null le message d'erreur, ou null si tout est bon
     */
    private function validate(string $email, string $password): ?string
    {
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return 'Adresse email invalide.';
        }
        if (strlen($password) < 8) {
            return 'Le mot de passe doit contenir au moins 8 caractères.';
        }
        return null;
    }

    // startSession() démarrait la session si ce n'était pas déjà fait
    // (session_start() envoie un cookie, donc un en-tête HTTP : impossible
    // après headers_sent(), ce qui arrive dans les tests en ligne de
    // commande).
    // Mise à jour (partie « My cinema by PiePHP ») : elle a déménagé dans
    // Core\Controller, en "protected", avec ses explications : les
    // controllers Film, Genre et Historique en ont besoin aussi.

    // databaseError() renvoyait le message affiché quand la base ne répond
    // pas, sans le détail de l'erreur (il pourrait renseigner sur le
    // serveur).
    // Mise à jour (partie « My cinema by PiePHP ») : elle a déménagé dans
    // Core\Controller, en "protected", pour servir à tous les controllers.
}
