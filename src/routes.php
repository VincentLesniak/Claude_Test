<?php

/*
 * Déclaration des routes "manuelles" de l'application.
 *
 * Ce fichier est chargé par Core::run() à chaque requête. Chaque appel à
 * Router::connect() associe une URL à un controller et à une action :
 *   - 'controller' => 'user'  correspond à la classe UserController
 *   - 'action'     => 'add'   correspond à la méthode addAction()
 *
 * Une URL qui n'est pas déclarée ici n'est pas perdue : le Core essaiera le
 * routage automatique /controller/action (ex. /user/add).
 */

use Core\Router;

// Page d'accueil.
Router::connect('/', ['controller' => 'app', 'action' => 'index']);

// Exemple de route "jolie" : l'URL ne ressemble pas au nom du controller.
Router::connect('/inscription', ['controller' => 'user', 'action' => 'register']);
