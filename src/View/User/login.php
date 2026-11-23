<?php
/**
 * Vue : formulaire de connexion (src/View/User/login.php)
 *
 * Dans le modèle MVC, une VUE ne contient que de l'affichage : pas de
 * requête SQL, pas de logique métier. Ici c'est encore une page HTML
 * complète, car nous n'avons pas de "layout" commun (il viendra plus tard
 * avec la méthode render() du Controller).
 *
 * Fonctionnement du formulaire :
 *  - method="post" : les données sont envoyées dans le CORPS de la requête
 *    HTTP (invisibles dans l'URL). PHP les range dans le tableau $_POST.
 *    Avec method="get", elles iraient dans l'URL (?email=...) et dans $_GET.
 *    Pour un mot de passe, on utilise toujours POST.
 *  - action="/PiePHP/index.php" : l'URL qui va RECEVOIR les données.
 *    Le chemin dépend de l'endroit où est installé le projet
 *    (ici /var/www/html/PiePHP -> http://localhost/PiePHP/).
 *  - l'attribut name="..." de chaque champ devient la CLÉ dans $_POST :
 *    name="email" -> $_POST['email'].
 *
 * Pour vérifier que la page est valide W3C : https://validator.w3.org/
 */
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <!-- Encodage des caractères : indispensable pour les accents. -->
    <meta charset="UTF-8">
    <!-- Adapte l'affichage à la largeur de l'écran (mobile). -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Le <title> est obligatoire pour que la page soit valide W3C. -->
    <title>PiePHP - Connexion</title>
</head>
<body>
    <h1>Connexion</h1>

    <form method="post" action="/PiePHP/index.php">
        <!-- Le "for" du label correspond à l'"id" du champ : cliquer sur le
             texte place le curseur dans le champ (accessibilité). -->
        <p>
            <label for="email">Email</label>
            <!-- type="email" : le navigateur vérifie le format x@y.z. -->
            <input type="email" id="email" name="email" required>
        </p>
        <p>
            <label for="password">Mot de passe</label>
            <!-- type="password" : les caractères sont masqués à l'écran,
                 mais attention, ils circulent en clair sans HTTPS. -->
            <input type="password" id="password" name="password" required>
        </p>
        <p>
            <button type="submit">Se connecter</button>
        </p>
    </form>
</body>
</html>
