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
 */
class Core
{
    public function run()
    {
        $url = $this->getUrl();

        // 2) Routes déclarées à la main dans src/routes.php.
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
        if (!ctype_alnum($route['controller']) || !ctype_alnum($route['action'])) {
            http_response_code(404);
            echo '404';
            return;
        }

        // 4) Conventions de nommage : 'user' -> UserController, 'add' -> addAction.
        // ucfirst() met la première lettre en majuscule.
        $controllerName = ucfirst($route['controller']) . 'Controller';
        $actionName = $route['action'] . 'Action';

        // class_exists() déclenche l'autoloader : si le fichier n'existe pas,
        // il renvoie false au lieu de planter avec une "Fatal error".
        if (!class_exists($controllerName) || !method_exists($controllerName, $actionName)) {
            http_response_code(404); // code HTTP pour le navigateur et les robots
            echo '404';
            return;
        }

        // 5) PHP accepte une chaîne comme nom de classe ou de méthode :
        // new $controllerName() équivaut ici à new UserController().
        $controller = new $controllerName();
        $controller->$actionName();
    }

    /**
     * Renvoie l'URL demandée, relative au projet.
     * Ex. "/PiePHP/user/add?x=1" donne "/user/add".
     */
    private function getUrl()
    {
        // parse_url(..., PHP_URL_PATH) enlève la partie "?x=1".
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

        // On retire le préfixe BASE_URI ("/PiePHP") s'il est présent.
        if (BASE_URI !== '' && strpos($path, BASE_URI) === 0) {
            $path = substr($path, strlen(BASE_URI));
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
        // explode découpe "user/add" en ['user', 'add'] ;
        // array_filter retire les morceaux vides (dus aux "/" en trop).
        $parts = array_values(array_filter(explode('/', trim($url, '/'))));

        // Ordre de priorité : URL "propre", puis paramètre GET, puis défaut.
        $controller = $parts[0] ?? $_GET['c'] ?? 'app';
        $action = $parts[1] ?? $_GET['a'] ?? 'index';

        return ['controller' => $controller, 'action' => $action];
    }
}
