<?php

use Core\Controller;

/**
 * Controller de l'entité "user" (avec UserModel et la table users).
 *
 * Convention de nommage utilisée par le routeur :
 *   URL /user/add  ->  classe UserController  ->  méthode addAction()
 * Le suffixe "Action" distingue les méthodes appelables depuis une URL des
 * autres méthodes de la classe : une méthode helper() ne sera jamais
 * accessible par /user/helper, car le Core cherche "helperAction".
 */
class UserController extends Controller
{
    public function indexAction()
    {
        echo __CLASS__ . '::' . __FUNCTION__ . ' [OK]' . PHP_EOL;
    }

    public function addAction()
    {
        echo __CLASS__ . '::' . __FUNCTION__ . ' [OK]' . PHP_EOL;
    }
}
