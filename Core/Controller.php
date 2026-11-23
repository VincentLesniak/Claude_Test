<?php

namespace Core;

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
        if (self::$render !== null) {
            echo self::$render;

            // La propriété est statique, donc partagée : sans cette remise à
            // zéro, le prochain controller détruit (ex. dans les tests, qui
            // enchaînent plusieurs requêtes) réafficherait la même page.
            self::$render = null;
        }
    }
}
