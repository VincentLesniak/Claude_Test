<?php

// Namespace "Controller" : la classe est rangée dans src/Controller/.
// PSR-12 impose un namespace pour chaque classe ; l'autoloader la retrouve
// grâce à son nom court (UserController.php dans src/Controller/).
namespace Controller;

// "use" importe la classe mère Core\Controller : dans ce fichier, le mot
// "Controller" seul désigne donc Core\Controller (et pas le namespace).
use Core\Controller;
use Model\HistoriqueModel;
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
 *
 * Mise à jour (partie « My cinema by PiePHP ») : l'entité User du
 * mini-site demande « inscription, connexion, voir son profil, modifier son
 * profil, supprimer son profil ». Les trois premières existaient ; s'ajoutent :
 *   - editAction()   /user/edit   : modifier son email et son mot de passe ;
 *   - deleteAction() /user/delete : supprimer son compte (avec confirmation) ;
 *   - le profil (showAction) affiche l'historique des films vus.
 * startSession() et databaseError() ont déménagé dans Core\Controller, pour
 * servir aussi aux controllers Film, Genre et Historique.
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
        // Mise à jour (partie « My cinema by PiePHP ») : les lignes qui
        // vidaient la session (démarrer la session, $_SESSION = [], puis
        // session_destroy()) sont dans endSession(), car deleteAction() fait
        // la même chose après avoir supprimé le compte.
        $this->endSession();

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

        // Mise à jour (partie « My cinema by PiePHP ») : pour un vrai
        // profil, on ajoute l'historique des films vus. Chaque ligne contient
        // l'entrée 'film' (relation 'has one film' de HistoriqueModel) : la
        // vue affiche le titre du film sans autre requête.
        if (!$scope['example'] && !isset($scope['error'])) {
            try {
                $scope['historiques'] = (new HistoriqueModel())->findForUser((int) $user['id']);
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
     * Ajout (partie « My cinema by PiePHP ») : /user/edit, modifier son
     * profil (email et, si on le souhaite, mot de passe).
     *
     * Le mot de passe est FACULTATIF ici : laissé vide, il ne change pas.
     * L'utilisateur est lu en base (new UserModel(['id' => ...])), puis on
     * remplace ses attributs. Entity::save() n'envoie que les colonnes
     * modifiées : un mot de passe non retapé n'est donc jamais renvoyé (ni
     * haché une seconde fois). Un nouveau mot de passe est haché par
     * UserModel::prepareFields(), comme à l'inscription.
     *
     * L'id vient de la SESSION, jamais de l'URL : on ne peut modifier que
     * SON propre profil.
     *
     * Pourquoi ? Demandé par le sujet (« Modifier son profil »).
     * Pertinence : on réutilise validate() et readByEmail() de
     * l'inscription ; seule différence, l'email peut rester le même (ce
     * n'est pas un doublon s'il appartient à l'utilisateur lui-même).
     */
    public function editAction()
    {
        if (!$this->requireLogin()) {
            return;
        }
        $scope = ['title' => 'Modifier mon profil'];

        try {
            $user = new UserModel(['id' => $this->currentUserId()]);
            if ($user->id === null) {
                // Le compte a disparu (supprimé ailleurs) : on oublie la session.
                $this->endSession();
                $this->redirect('/user/login');
                return;
            }
            $scope['email'] = $user->email;

            if ($this->request->isPost()) {
                $email = $this->postValue('email');
                $password = $this->postValue('password');
                $scope['email'] = $email;
                // Le 3e paramètre : le mot de passe n'est pas obligatoire ici.
                $scope['error'] = $this->validate($email, $password, false);

                $other = $scope['error'] === null ? $user->readByEmail($email) : null;
                if ($other !== null && (int) $other['id'] !== $user->id) {
                    $scope['error'] = 'Un compte existe déjà avec cet email.';
                }

                if ($scope['error'] === null) {
                    $user->email = $email;
                    if ($password !== '') {
                        $user->password = $password;
                    }
                    $user->save();
                    $this->flash('success', 'Votre profil a été modifié.');
                    $this->redirect('/user/show');
                    return;
                }
            }
        } catch (PDOException | RuntimeException $e) {
            $scope['error'] = $this->databaseError();
        }

        $this->render('edit', $scope);
    }

    /**
     * Ajout (partie « My cinema by PiePHP ») : /user/delete, supprimer son
     * compte.
     *
     *   GET  : une page de confirmation (« Êtes-vous sûr ? ») ;
     *   POST : le bouton de cette page, qui supprime vraiment.
     * Supprimer un compte est définitif : on ne le fait jamais sur un
     * simple lien (GET), et on laisse une chance de changer d'avis.
     *
     * L'historique du membre est supprimé avant lui. On le trouve grâce à
     * la relation 'has many historiques' de UserModel : $user->historiques
     * contient toutes ses lignes. Ensuite, la session est vidée (le membre
     * n'existe plus) et on affiche la page de connexion avec un message,
     * comme logoutAction().
     *
     * Pourquoi ? Demandé par le sujet (« Supprimer son profil »).
     * Pertinence : sans effacer l'historique d'abord, ses lignes
     * désigneraient un utilisateur qui n'existe plus (MySQL les efface
     * aussi grâce à ON DELETE CASCADE, mais SQLite non, par défaut).
     */
    public function deleteAction()
    {
        if (!$this->requireLogin()) {
            return;
        }
        if (!$this->request->isPost()) {
            $this->render('delete', ['title' => 'Supprimer mon compte']);
            return;
        }

        try {
            $user = new UserModel(['id' => $this->currentUserId()]);
            $historiques = new HistoriqueModel();
            foreach ($user->historiques ?? [] as $historique) {
                $historiques->delete((int) $historique['id']);
            }
            $user->delete();
        } catch (PDOException | RuntimeException $e) {
            $this->render('delete', ['title' => 'Supprimer mon compte', 'error' => $this->databaseError()]);
            return;
        }

        $this->endSession();
        $this->render('login', ['title' => 'Connexion', 'success' => 'Votre compte a été supprimé.']);
    }

    /**
     * Vérifie les champs du formulaire d'inscription.
     *
     * filter_var(..., FILTER_VALIDATE_EMAIL) renvoie false si le texte n'a
     * pas la forme d'une adresse email.
     *
     * Mise à jour (partie « My cinema by PiePHP ») : un 3e paramètre,
     * facultatif. Sur la page « Modifier mon profil », le mot de passe peut
     * rester vide (il ne change pas) : $passwordRequired vaut alors false,
     * et la longueur n'est vérifiée que si un mot de passe a été tapé.
     * L'inscription, elle, appelle toujours validate() avec deux arguments :
     * true par défaut, rien ne change pour elle.
     *
     * @return string|null le message d'erreur, ou null si tout est bon
     */
    private function validate(string $email, string $password, bool $passwordRequired = true): ?string
    {
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return 'Adresse email invalide.';
        }
        if (!$passwordRequired && $password === '') {
            return null;
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

    /**
     * Ajout (partie « My cinema by PiePHP ») : vide la session (le membre
     * n'est plus connecté).
     *
     * $_SESSION = [] efface les données tout de suite (pour la fin de cette
     * requête), session_destroy() supprime le fichier de session sur le
     * serveur (pour les suivantes).
     *
     * Pourquoi ? logoutAction() et deleteAction() (et editAction(), si le
     * compte a disparu) déconnectent le membre de la même façon.
     * Pertinence : les trois lignes sont écrites une seule fois.
     */
    private function endSession(): void
    {
        $this->startSession();
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    // databaseError() renvoyait le message affiché quand la base ne répond
    // pas, sans le détail de l'erreur (il pourrait renseigner sur le
    // serveur).
    // Mise à jour (partie « My cinema by PiePHP ») : elle a déménagé dans
    // Core\Controller, en "protected", pour servir à tous les controllers.
}
