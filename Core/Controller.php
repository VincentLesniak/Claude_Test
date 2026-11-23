<?php

namespace Core;

use InvalidArgumentException;
use RuntimeException;

/**
 * Classe mère de tous les controllers de l'application.
 *
 * Chaque controller (UserController, AppController...) écrit "extends Controller"
 * et hérite donc de tout ce qui est défini ici. Pour l'instant elle est vide,
 * mais c'est ici qu'on ajoutera à l'étape suivante la méthode render() qui
 * affiche une vue dans le layout : le code sera écrit une seule fois et
 * disponible dans tous les controllers.
 *
 * Mise à jour (étape "Le Controller") : c'est fait, la classe n'est plus vide,
 * render() et __destruct() sont codées ci-dessous.
 *
 * RAPPEL DU PRINCIPE (sujet, partie "Le Controller") :
 *   1. l'action appelle $this->render('login') ;
 *   2. render() fabrique la page complète (la vue insérée dans le layout) mais
 *      ne l'affiche PAS : elle la garde de côté dans self::$render ;
 *   3. quand PHP détruit le controller (fin de Core::run()), il appelle
 *      automatiquement __destruct(), qui affiche enfin la page.
 *
 * Pourquoi attendre la fin ? Une fois qu'un seul caractère est envoyé au
 * navigateur, PHP envoie aussi les en-têtes HTTP (code 200, cookies, header()
 * de redirection...) et on ne peut plus les modifier. En n'affichant rien
 * avant la toute fin, l'action garde la possibilité de rediriger, de démarrer
 * une session ou de changer le code HTTP jusqu'au dernier moment.
 *
 * Mise à jour (partie « My cinema by PiePHP ») : le mini-site a besoin, dans
 * PLUSIEURS controllers (User, Film, Genre, Historique), de savoir qui est
 * connecté, de renvoyer le visiteur vers une autre page après un formulaire
 * et de lui afficher un message sur cette page. Ces outils sont écrits ici
 * une seule fois, comme render() :
 *
 * PLAN DU FICHIER
 *   - __construct()     : crée $this->request ;
 *   - render()          : prépare la page (vue + layout) ;
 *   - redirect()        : prépare une REDIRECTION au lieu d'une page ;
 *   - flash()           : un message à afficher sur la PROCHAINE page ;
 *   - startSession(), currentUserId(), requireLogin() : la connexion ;
 *   - postValue(), queryId() : lire un champ ou un id sans mauvaise surprise ;
 *   - databaseError()   : le message quand la base ne répond pas ;
 *   - capture(), compileView() : outils privés de render() ;
 *   - __destruct()      : envoie enfin la redirection ou la page.
 */
class Controller
{
    /**
     * La page HTML complète en attente d'affichage (null = rien à afficher).
     *
     * Le sujet l'appelle $_render, mais PSR-12 déconseille le "_" au début
     * d'un nom de propriété (c'était une vieille convention pour "privé",
     * remplacée par les mots-clés private/protected) : on l'appelle $render.
     * PHP ne confond pas la propriété self::$render et la méthode render().
     *
     * "static" : la propriété appartient à la CLASSE et non à un objet. Elle
     * est donc partagée par tous les controllers : n'importe lequel peut écrire
     * self::$render = '...' (le sujet s'en sert plus loin pour afficher
     * "Votre compte a été créé.").
     *
     * "protected" : accessible dans Controller et ses classes filles, mais pas
     * depuis l'extérieur (ex. depuis une vue ou depuis Core).
     *
     * @var string|null
     */
    protected static $render = null;

    /**
     * Ajout (partie « My cinema by PiePHP ») : l'adresse vers laquelle
     * renvoyer le visiteur (null = pas de redirection).
     *
     * Même principe que $render : redirect() ne fait que la NOTER, et
     * __destruct() envoie l'en-tête HTTP "Location" à la toute fin.
     * "private" : les controllers passent par redirect(), jamais par
     * l'attribut lui-même.
     *
     * @var string|null
     */
    private static $redirect = null;

    /**
     * Ajout (étape "Request") : les paramètres GET et POST de la requête,
     * déjà nettoyés. Dans une action : $this->request->getPost('email').
     *
     * @var Request
     */
    protected $request;

    /**
     * Ajout (étape "Request") : le sujet demande d'instancier Request "dans
     * le constructeur de tous vos controllers". En le faisant ICI, dans la
     * classe mère, tous les controllers en profitent sans écrire une ligne.
     *
     * Attention : si un controller écrit un jour son propre __construct(),
     * il devra appeler parent::__construct() au début, sinon $this->request
     * restera vide.
     */
    public function __construct()
    {
        $this->request = new Request();
    }

    /**
     * Construit la page : la vue $view insérée dans le layout src/View/index.php.
     *
     * Le dossier de la vue est déduit du nom du controller :
     *   UserController + render('login')  ->  src/View/User/login.php
     *   AppController  + render('index')  ->  src/View/App/index.php
     *
     * @param string $view  nom du fichier de vue, sans ".php"
     * @param array  $scope variables à transmettre à la vue (et au layout) :
     *                      ['email' => 'a@b.c'] crée la variable $email
     *
     * @throws RuntimeException si le fichier de vue n'existe pas
     */
    protected function render($view, $scope = [])
    {
        $root = dirname(__DIR__);

        // get_class($this) renvoie le nom COMPLET de la classe de l'objet,
        // namespace compris : "Controller\UserController". C'est bien la classe
        // fille, même si ce code est écrit dans Core\Controller.
        //
        // Piège : le sujet utilise basename() pour garder "UserController",
        // mais sous Linux basename() ne coupe que sur "/" et laisserait
        // "Controller\UserController" intact. On transforme donc d'abord les
        // "\" en "/" ; basename() garde alors ce qui suit le dernier "/".
        $className = basename(str_replace('\\', '/', get_class($this)));

        // "UserController" -> "User" : le nom du dossier de vues.
        $folder = str_replace('Controller', '', $className);

        $file = implode(DIRECTORY_SEPARATOR, [$root, 'src', 'View', $folder, $view . '.php']);

        // Le sujet ne fait rien si la vue n'existe pas : la page reste alors
        // blanche sans explication. Une exception avec le chemin cherché est
        // bien plus simple à déboguer (une faute de frappe se voit tout de suite).
        if (!file_exists($file)) {
            throw new RuntimeException("Vue introuvable : $file");
        }

        // Ajout (partie « My cinema by PiePHP ») : deux variables offertes à
        // TOUTES les vues et au layout, sans que chaque action ait à les
        // transmettre :
        //   $flash         le message laissé par flash() sur la page
        //                  précédente (ou null), affiché une seule fois ;
        //   $currentUserId l'id de l'utilisateur connecté (ou null) : le
        //                  layout affiche « Profil » ou « Connexion », une
        //                  vue affiche ou cache le bouton « Modifier »...
        // "+=" sur des tableaux ajoute seulement les clés ABSENTES : si
        // l'action a déjà donné un 'flash', c'est le sien qui est gardé.
        $scope += ['flash' => $this->takeFlash(), 'currentUserId' => $this->currentUserId()];

        // Étape 1 : le contenu de la vue, seul (sans <html>, <head>...).
        $content = $this->capture($file, $scope);

        // Étape 2 : le layout, qui reçoit ce contenu dans la variable $view
        // (il l'affiche avec un simple echo), ainsi que les variables de $scope
        // (par exemple $title pour la balise <title>).
        $layout = implode(DIRECTORY_SEPARATOR, [$root, 'src', 'View', 'index.php']);
        $scope['view'] = $content;

        // On garde la page de côté : elle sera affichée par __destruct().
        self::$render = $this->capture($layout, $scope);
    }

    /**
     * Ajout (partie « My cinema by PiePHP ») : renvoie le visiteur vers une
     * autre page du site, ex. $this->redirect('/film/show?id=3').
     *
     * Le principe « Post / Redirect / Get » : après un formulaire envoyé en
     * POST (ajout d'un film...), on ne renvoie pas directement une page, on
     * demande au navigateur d'aller sur une autre adresse (en GET). Sans
     * cela, la touche F5 renverrait le formulaire et ajouterait le film une
     * deuxième fois.
     *
     * Techniquement, c'est l'en-tête HTTP "Location" avec le code 303
     * ("See Other" : « va voir là-bas, en GET »). Comme pour render(), rien
     * n'est envoyé tout de suite : __destruct() s'en charge à la fin.
     *
     * Une petite page avec un lien est aussi préparée : le navigateur ne
     * l'affiche pas (il suit la redirection), mais elle sert si l'en-tête ne
     * peut pas partir (texte déjà affiché), et les tests la lisent pour
     * vérifier où mène la redirection.
     *
     * Pourquoi ? Presque toutes les actions du mini-site qui modifient la
     * base finissent par une redirection.
     * Pertinence : BASE_URI est ajouté ici, une fois pour toutes ; l'action
     * écrit seulement le chemin dans le site. Un chemin qui ne commence pas
     * par "/" est refusé : on ne redirige jamais vers un autre site (une
     * adresse venue d'un formulaire pourrait sinon envoyer le visiteur
     * n'importe où).
     *
     * @param string $path chemin dans le site, ex. '/film/show?id=3'
     * @throws InvalidArgumentException si le chemin ne commence pas par un seul "/"
     */
    protected function redirect(string $path): void
    {
        // "//exemple.com" serait compris par le navigateur comme un AUTRE
        // site, et certains navigateurs lisent "\" comme "/" : on exige un
        // "/" qui n'est suivi ni d'un "/" ni d'un "\".
        // (?![/\\]) : « lookahead » négatif, vérifie le caractère SUIVANT
        // sans le consommer. Dans le code, les 4 antislashs de la chaîne PHP
        // deviennent 2 pour la regex, qui les lit comme UN antislash.
        // "/" seul (la page d'accueil) est accepté.
        if (!preg_match('#^/(?![/\\\\])#', $path)) {
            throw new InvalidArgumentException("Redirection invalide : $path");
        }

        self::$redirect = BASE_URI . $path;
        $url = htmlspecialchars(self::$redirect, ENT_QUOTES, 'UTF-8');
        self::$render = '<p>Redirection vers <a href="' . $url . '">' . $url . '</a></p>';
    }

    /**
     * Ajout (partie « My cinema by PiePHP ») : range un message à afficher
     * sur la PROCHAINE page, ex. $this->flash('success', 'Film ajouté.').
     *
     * On l'appelle un message « flash » car il ne s'affiche qu'une fois :
     * render() le retire de la session en le lisant (voir takeFlash()).
     * Le layout l'affiche avec la classe CSS du type ('success' ou 'error').
     *
     * Pourquoi la session ? Après redirect(), c'est une NOUVELLE requête qui
     * affiche la page : les variables PHP de l'action précédente n'existent
     * plus. La session est la mémoire du serveur entre deux requêtes.
     * Pertinence : sans message, le visiteur redirigé ne saurait pas si son
     * ajout ou sa suppression a fonctionné.
     *
     * @param string $type    'success' ou 'error'
     * @param string $message le texte, échappé à l'affichage par le layout
     */
    protected function flash(string $type, string $message): void
    {
        $this->startSession();
        $_SESSION['flash'] = ['type' => $type, 'message' => $message];
    }

    /**
     * Démarre la session si ce n'est pas déjà fait.
     *
     * session_start() envoie un cookie, donc un en-tête HTTP : impossible si
     * du texte a déjà été envoyé au navigateur (headers_sent()). Grâce à
     * render(), rien n'est affiché avant la fin, donc en temps normal ce cas
     * n'arrive pas ; il arrive en revanche dans les tests en ligne de
     * commande, où l'on évite ainsi un avertissement.
     *
     * Mise à jour (partie « My cinema by PiePHP ») : cette méthode était une
     * méthode privée de UserController. Elle a déménagé ici, en
     * "protected", car tous les controllers du mini-site en ont besoin
     * (savoir qui est connecté, laisser un message flash).
     */
    protected function startSession(): void
    {
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            session_start();
        }
    }

    /**
     * Ajout (partie « My cinema by PiePHP ») : l'id de l'utilisateur
     * connecté, ou null pour un simple visiteur.
     *
     * loginAction() range cet id dans $_SESSION['user_id'] à la connexion.
     *
     * Pourquoi ? Chaque action qui dépend de l'utilisateur (ajouter un film
     * vu à SON historique, modifier SON profil) commence par cette question.
     * Pertinence : le nom de la clé de session n'est écrit qu'ici et dans
     * loginAction() ; les autres controllers n'ont pas à le connaître.
     */
    protected function currentUserId(): ?int
    {
        $this->startSession();
        return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    }

    /**
     * Ajout (partie « My cinema by PiePHP ») : réservé aux membres
     * connectés. Renvoie true si quelqu'un est connecté ; sinon, prépare un
     * message et une redirection vers la page de connexion, et renvoie false.
     *
     * Utilisation, au début d'une action :
     *   if (!$this->requireLogin()) {
     *       return;   // la redirection est déjà prête
     *   }
     *
     * Pourquoi ? Le sujet décrit un site communautaire : tout le monde peut
     * CONSULTER les films, mais seuls les membres les modifient.
     * Pertinence : la vérification se fait côté serveur, dans l'action. Cacher
     * le bouton « Modifier » dans la vue ne suffirait pas : n'importe qui
     * peut taper l'adresse /film/edit à la main.
     */
    protected function requireLogin(): bool
    {
        if ($this->currentUserId() !== null) {
            return true;
        }
        $this->flash('error', 'Connectez-vous pour accéder à cette page.');
        $this->redirect('/user/login');
        return false;
    }

    /**
     * Ajout (partie « My cinema by PiePHP ») : la valeur d'un champ du
     * formulaire envoyé en POST, toujours sous forme de TEXTE ('' si le
     * champ est absent).
     *
     * Request::getPost() renvoie déjà la valeur nettoyée (trim,
     * htmlspecialchars...). Mais avec un champ nommé "titre[]" (facile à
     * fabriquer en modifiant la page), PHP range un TABLEAU dans $_POST :
     * le traiter comme du texte provoquerait une erreur. is_string() écarte
     * ce cas.
     *
     * Pourquoi ? Les formulaires du mini-site (films, genres, profil) ne
     * contiennent que des champs texte.
     * Pertinence : chaque action lit ses champs en une ligne, sans répéter
     * la vérification.
     *
     * @param string $name le nom du champ (attribut name="..." du formulaire)
     */
    protected function postValue(string $name): string
    {
        $value = $this->request->getPost($name, '');
        return is_string($value) ? $value : '';
    }

    /**
     * Ajout (partie « My cinema by PiePHP ») : un id lu dans l'URL
     * (?id=3), ou null s'il est absent ou s'il ne s'agit pas d'un nombre.
     *
     * Toutes les pages qui concernent UN film ou UN genre portent son id
     * dans l'URL : /film/show?id=3, /film/edit?id=3, et même les
     * formulaires de suppression (method="post" action="/film/delete?id=3" :
     * les paramètres de l'URL arrivent dans $_GET même pour un POST).
     * ctype_digit() vérifie que le texte n'a que des chiffres : "3" passe,
     * "3abc", "-1" ou "" non.
     *
     * Pourquoi ? On ne donne jamais toute l'URL à une entité (un "?id="
     * glissé dans l'URL ferait charger une autre ligne) : on lit SEULEMENT
     * le paramètre attendu, converti en entier.
     * Pertinence : une seule règle pour tous les controllers, et un id
     * invalide devient null, que l'action traite comme « introuvable ».
     *
     * @param string $name le nom du paramètre, 'id' par défaut
     */
    protected function queryId(string $name = 'id'): ?int
    {
        $value = $this->request->getQuery($name, '');
        return is_string($value) && ctype_digit($value) ? (int) $value : null;
    }

    /**
     * Message affiché quand la base ne répond pas (MySQL arrêté, mauvais mot
     * de passe dans config.json...). On ne montre pas le détail de l'erreur
     * au visiteur : il pourrait contenir des informations sur le serveur.
     *
     * Mise à jour (partie « My cinema by PiePHP ») : cette méthode était une
     * méthode privée de UserController. Elle a déménagé ici, en
     * "protected", pour servir à tous les controllers ; le message parle
     * maintenant de toutes les tables de database.sql (plus seulement users).
     */
    protected function databaseError(): string
    {
        return 'La base de données est indisponible. Vérifiez config.json et '
            . 'que les tables existent (database.sql).';
    }

    /**
     * Ajout (partie « My cinema by PiePHP ») : lit le message flash et le
     * retire de la session (il ne doit s'afficher qu'une fois).
     *
     * @return array|null ['type' => ..., 'message' => ...], ou null
     */
    private function takeFlash(): ?array
    {
        $this->startSession();
        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);
        return is_array($flash) ? $flash : null;
    }

    /**
     * Inclut un fichier PHP et renvoie ce qu'il affiche, au lieu de l'envoyer
     * au navigateur.
     *
     * ob_start() ("output buffering") ouvre un tampon : tous les echo et le
     * HTML qui suivent y sont stockés au lieu d'être envoyés. ob_get_clean()
     * renvoie le contenu du tampon sous forme de texte et le ferme.
     *
     * Pourquoi une méthode à part ? Un fichier inclus voit toutes les variables
     * de la fonction qui l'inclut. Ici la vue ne verra que $file, $scope et les
     * variables de $scope, pas les variables internes de render().
     *
     * Mise à jour (étape "Avancé - le moteur de templates") : on n'inclut
     * plus directement le fichier de la vue, mais sa version COMPILÉE par
     * Core\TemplateEngine. Les tags {{ }}, @if, @foreach... y sont déjà
     * remplacés par du PHP classique. Une vue en PHP pur ressort inchangée du
     * moteur : les anciennes vues fonctionnent toujours.
     */
    private function capture($file, $scope)
    {
        // extract() transforme chaque case du tableau en variable :
        // ['email' => 'a@b.c'] crée $email = 'a@b.c'.
        // EXTR_SKIP : si une clé s'appelle "file" ou "scope", on n'écrase pas
        // nos propres variables (sinon include $file inclurait autre chose).
        extract($scope, EXTR_SKIP);

        ob_start();
        // compileView() renvoie le chemin du fichier compilé (dans le cache).
        // On l'appelle directement dans include, sans variable intermédiaire :
        // une variable de plus serait visible (et modifiable) dans la vue.
        include $this->compileView($file);
        return ob_get_clean();
    }

    /**
     * Ajout (étape "Avancé - le moteur de templates") : passe la vue dans
     * Core\TemplateEngine et renvoie le chemin du fichier PHP compilé.
     *
     * Pourquoi une méthode ? Écrire "include (new TemplateEngine())->..."
     * directement dans capture() est refusé par PSR-12 (l'outil prend
     * include pour une fonction suivie d'un espace interdit). Et c'est plus
     * lisible : capture() dit "j'inclus la vue compilée", le détail est ici.
     *
     * Pertinence : si un jour on veut régler le moteur (autre dossier de
     * cache, désactiver le cache en développement...), c'est le seul endroit
     * à modifier.
     *
     * @param string $file chemin de la vue d'origine
     *
     * @return string chemin de la vue compilée, à inclure
     */
    private function compileView($file)
    {
        return (new TemplateEngine())->compileFile($file);
    }

    /**
     * Le "destructeur", l'inverse de __construct() : PHP l'appelle tout seul
     * quand l'objet n'est plus utilisé. Dans Core::run(), la variable
     * $controller disparaît à la fin de la méthode : c'est à ce moment que la
     * page s'affiche, après que l'action a complètement terminé son travail.
     */
    public function __destruct()
    {
        // Mise à jour (partie « My cinema by PiePHP ») : s'il y a une
        // redirection, on envoie d'abord l'en-tête "Location" (code 303).
        // Le navigateur ira alors sur cette adresse sans afficher la page.
        if (self::$redirect !== null) {
            if (!headers_sent()) {
                header('Location: ' . self::$redirect, true, 303);
            }
            self::$redirect = null;
        }

        if (self::$render !== null) {
            echo self::$render;

            // La propriété est statique, donc partagée : sans cette remise à
            // zéro, le prochain controller détruit (ex. dans les tests, qui
            // enchaînent plusieurs requêtes) réafficherait la même page.
            self::$render = null;
        }
    }
}
