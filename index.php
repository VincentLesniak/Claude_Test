<?php
/**
 * index.php : point d'entrée de l'application.
 *
 * ÉTAPE 2 (debug) : on affiche le contenu des trois "superglobales" de PHP
 * pour comprendre ce que le serveur nous transmet à chaque requête.
 * Une superglobale est un tableau rempli automatiquement par PHP et
 * accessible partout dans le code.
 *
 * Test : ouvre http://localhost/PiePHP/src/View/User/login.php, remplis le
 * formulaire et valide. Tu arrives ici avec les données dans $_POST.
 * Essaie aussi http://localhost/PiePHP/index.php?nom=pie&age=42 pour
 * remplir $_GET.
 */

// La balise <pre> conserve les espaces et les retours à la ligne :
// le résultat de print_r() (ou var_dump()) devient lisible dans le navigateur.

echo '<h2>$_POST : données envoyées par un formulaire en method="post"</h2>';
echo '<pre>';
print_r($_POST);
echo '</pre>';

echo '<h2>$_GET : paramètres présents dans l\'URL (après le "?")</h2>';
echo '<pre>';
print_r($_GET);
echo '</pre>';

// $_SERVER contient les infos sur la requête et le serveur. Les clés les
// plus utiles pour la suite du projet :
//  - REQUEST_METHOD : GET ou POST
//  - REQUEST_URI    : l'URL demandée (servira au routeur)
//  - DOCUMENT_ROOT  : le dossier racine d'Apache (ex. /var/www/html)
//  - SCRIPT_NAME    : le chemin du script exécuté
echo '<h2>$_SERVER : informations sur la requête et le serveur</h2>';
echo '<pre>';
print_r($_SERVER);
echo '</pre>';
