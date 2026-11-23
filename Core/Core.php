<?php

namespace Core;

/**
 * Cœur du framework : reçoit chaque requête (via index.php) et la confie au
 * bon controller.
 *
 * Déroulé de run() :
 *   1. on récupère l'URL demandée, sans le dossier du projet ni les ?paramètres
 *   2. on charge src/routes.php puis on demande au Router si une route existe
 *   3. sinon, routage automatique : /controller/action (ou ?c=...&a=...)
 *   4. on vérifie que le controller et l'action existent, sinon 404
 *   5. on instancie le controller et on appelle l'action
 *
 * Exemple complet : http://localhost/PiePHP/user/add
 *   -> URL "/user/add" -> aucune route déclarée -> controller "user", action "add"
 *   -> new \Controller\UserController() puis ->addAction()
 */
class Core
{
    public function run()
    {
        // 1) L'URL demandée, ex. "/user/add".
        $url = $this->getUrl();

        // 2) Routes déclarées à la main dans src/routes.php.
        // require_once : le fichier n'est chargé qu'une fois, même si run()
        // est appelée plusieurs fois (c'est le cas dans les tests).
        require_once implode(DIRECTORY_SEPARATOR, [dirname(__DIR__), 'src', 'routes.php']);

        // Exception : si l'URL contient ?c=... ou ?a=..., le visiteur demande
        // explicitement un controller ; sinon "/?c=user&a=add" tomberait sur
        // la route "/" de la page d'accueil.
        $route = null;
        if (!isset($_GET['c']) && !isset($_GET['a'])) {
            $route = Router::get($url);
        }

        // 3) Aucune route déclarée : on déduit controller et action de l'URL.
        if ($route === null) {
            $route = $this->autoRoute($url);
        }

        // Sécurité : le controller et l'action viennent de l'URL, donc du
        // visiteur. On n'accepte que des lettres et des chiffres, sinon une URL
        // comme "/../../secret" pourrait faire inclure n'importe quel fichier
        // par l'autoloader.
        // is_string() : avec "?c[]=x", PHP fabrique un tableau au lieu d'une
        // chaîne, et ctype_alnum() planterait.
        if (!$this->isValidName($route['controller']) || !$this->isValidName($route['action'])) {
            $this->notFound();
            return;
        }

        // 4) Conventions de nommage : 'user' -> Controller\UserController,
        // 'add' -> addAction. ucfirst() met la première lettre en majuscule.
        // Le préfixe "Controller\\" est le namespace des controllers (en PHP,
        // "\\" dans une chaîne donne un seul antislash).
        $controllerName = 'Controller\\' . ucfirst($route['controller']) . 'Controller';
        $actionName = $route['action'] . 'Action';

        // class_exists() déclenche l'autoloader : si le fichier n'existe pas,
        // il renvoie false au lieu de planter avec une "Fatal error".
        if (!class_exists($controllerName) || !method_exists($controllerName, $actionName)) {
            $this->notFound();
            return;
        }

        // 5) PHP accepte une chaîne comme nom de classe ou de méthode :
        // new $controllerName() équivaut ici à new \Controller\UserController().
        $controller = new $controllerName();
        $controller->$actionName();
    }

    /**
     * Un nom de controller ou d'action valide : une chaîne non vide faite
     * uniquement de lettres et de chiffres (ctype_alnum).
     */
    private function isValidName($name)
    {
        return is_string($name) && ctype_alnum($name);
    }

    /**
     * Affiche la page 404 (src/View/Error/404.php).
     * http_response_code(404) envoie le vrai code HTTP "Not Found" au
     * navigateur et aux robots : sans lui, la page serait vue comme un succès
     * (code 200) alors que la ressource n'existe pas.
     */
    private function notFound()
    {
        // headers_sent() : si du texte a déjà été affiché, les en-têtes HTTP
        // sont partis et on ne peut plus changer le code (PHP afficherait un
        // warning). Ça arrive par exemple pendant les tests en ligne de commande.
        if (!headers_sent()) {
            http_response_code(404);
        }
        include implode(DIRECTORY_SEPARATOR, [dirname(__DIR__), 'src', 'View', 'Error', '404.php']);
    }

    /**
     * Renvoie l'URL demandée, relative au projet.
     * Ex. "/PiePHP/user/add?x=1" donne "/user/add".
     */
    private function getUrl()
    {
        // parse_url(..., PHP_URL_PATH) enlève la partie "?x=1".
        // En ligne de commande (tests), REQUEST_URI n'existe pas : on prend '/'.
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

        // On retire le préfixe BASE_URI ("/PiePHP") s'il est présent.
        // BASE_URI est définie dans index.php ; si on n'est pas passé par lui
        // (tests en ligne de commande), on considère le projet à la racine.
        $baseUri = defined('BASE_URI') ? BASE_URI : '';
        if ($baseUri !== '' && strpos($path, $baseUri) === 0) {
            $path = substr($path, strlen($baseUri));
        }

        // "/index.php" et "/" désignent tous les deux la page d'accueil.
        if ($path === '/index.php') {
            $path = '/';
        }

        return $path;
    }

    /**
     * Routage automatique : "/user/add" -> controller "user", action "add".
     * Fonctionne aussi avec "?c=user&a=add". Valeurs par défaut : app / index.
     */
    private function autoRoute($url)
    {
        // explode découpe "user/add" en ['user', 'add'].
        // array_filter retire les morceaux vides (dus à un "//" dans l'URL) ;
        // le callback 'strlen' évite de retirer aussi "0", que PHP considère
        // comme faux. array_values renumérote ensuite les cases à partir de 0.
        $parts = array_values(array_filter(explode('/', trim($url, '/')), 'strlen'));

        // Ordre de priorité : URL "propre", puis paramètre GET, puis défaut.
        $controller = $parts[0] ?? $_GET['c'] ?? 'app';
        $action = $parts[1] ?? $_GET['a'] ?? 'index';

        return ['controller' => $controller, 'action' => $action];
    }
}
