<?php

namespace Core;

/**
 * Routeur statique : fait le lien entre une URL et un couple controller/action.
 *
 * Pourquoi "statique" ? Il n'y a qu'une seule table de routes pour toute
 * l'application : pas besoin de créer un objet Router, on appelle directement
 * Router::connect() et Router::get(). La propriété $routes est partagée.
 *
 * Intérêt : le nom de l'URL est découplé du nom du controller. On peut par
 * exemple faire pointer "/inscription" vers UserController::registerAction()
 * sans que l'URL contienne "user" ou "register".
 */
class Router
{
    /**
     * Table des routes déclarées, sous la forme :
     *   ['/url' => ['controller' => 'user', 'action' => 'add'], ...]
     *
     * @var array<string, array{controller: string, action: string}>
     */
    private static $routes = [];

    /**
     * Déclare une route (appelé depuis src/routes.php).
     *
     * @param string $url   URL relative au projet, ex. '/register'
     * @param array  $route ['controller' => 'user', 'action' => 'register']
     */
    public static function connect($url, $route)
    {
        // On normalise l'URL pour que '/register', 'register' et '/register/'
        // désignent la même route.
        self::$routes[self::normalize($url)] = $route;
    }

    /**
     * Cherche la route qui correspond à l'URL demandée.
     *
     * @param string $url URL relative au projet, ex. '/register'
     *
     * @return array|null ['controller' => ..., 'action' => ...] si une route
     *                    a été déclarée avec connect(), sinon null. Dans ce cas
     *                    c'est le Core qui tentera le routage automatique.
     */
    public static function get($url)
    {
        $url = self::normalize($url);

        // L'opérateur ?? renvoie null si la clé n'existe pas (pas de warning).
        return self::$routes[$url] ?? null;
    }

    /**
     * Met une URL sous une forme unique : un seul "/" au début, aucun à la fin.
     * '' et '/' deviennent '/' (la page d'accueil).
     */
    private static function normalize($url)
    {
        return '/' . trim($url, '/');
    }
}
