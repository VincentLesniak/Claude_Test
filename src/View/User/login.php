<?php
/**
 * Vue : formulaire de connexion (UserController::loginAction, URL /user/login)
 *
 * Dans le modèle MVC, une VUE ne contient que de l'affichage : pas de
 * requête SQL, pas de logique métier. Ce n'est plus une page HTML complète :
 * le <!doctype>, le <head> et le <title> sont dans le layout
 * (src/View/index.php), ajouté autour par la méthode render() du Controller.
 *
 * Fonctionnement du formulaire :
 *  - method="post" : les données sont envoyées dans le CORPS de la requête
 *    HTTP (invisibles dans l'URL). PHP les range dans le tableau $_POST.
 *    Avec method="get", elles iraient dans l'URL (?email=...) et dans $_GET.
 *    Pour un mot de passe, on utilise toujours POST.
 *  - action="<?= BASE_URI ?>/user/login" : l'URL qui va RECEVOIR les
 *    données, ici loginAction elle-même (elle saura les traiter quand le
 *    model sera prêt). BASE_URI vaut "/PiePHP" chez toi (voir index.php).
 *  - l'attribut name="..." de chaque champ devient la CLÉ dans $_POST :
 *    name="email" -> $_POST['email'].
 *
 * Pour vérifier que la page est valide W3C : https://validator.w3.org/
 * (colle le code source de la page affichée, layout compris).
 */
?>
<h1>Connexion</h1>

<form method="post" action="<?= BASE_URI ?>/user/login">
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
