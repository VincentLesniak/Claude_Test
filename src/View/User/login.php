<?php
/**
 * Vue : formulaire de connexion (src/View/User/login.php)
 * Affichée par UserController::loginAction(), URL /user/login.
 *
 * Dans le modèle MVC, une VUE ne contient que de l'affichage : pas de
 * requête SQL, pas de logique métier. Ici c'est encore une page HTML
 * complète, car nous n'avons pas de "layout" commun (il viendra plus tard
 * avec la méthode render() du Controller).
 *
 * Mise à jour (étape "Le Controller") : le layout est arrivé. Ce fichier
 * n'est plus une page complète : le <!doctype>, le <head> et le <title> sont
 * dans src/View/index.php, ajouté autour par render().
 *
 * Fonctionnement du formulaire :
 *  - method="post" : les données sont envoyées dans le CORPS de la requête
 *    HTTP (invisibles dans l'URL). PHP les range dans le tableau $_POST.
 *    Avec method="get", elles iraient dans l'URL (?email=...) et dans $_GET.
 *    Pour un mot de passe, on utilise toujours POST.
 *  - action="/PiePHP/index.php" : l'URL qui va RECEVOIR les données.
 *    Le chemin dépend de l'endroit où est installé le projet
 *    (ici /var/www/html/PiePHP -> http://localhost/PiePHP/).
 *    Mise à jour (étape "Le Controller") : l'action est maintenant
 *    "<?= BASE_URI ?>/user/login", c'est-à-dire loginAction elle-même (elle
 *    saura traiter les données quand le model sera prêt). BASE_URI vaut
 *    "/PiePHP" chez toi (voir index.php) : plus besoin d'écrire le chemin en dur.
 *  - l'attribut name="..." de chaque champ devient la CLÉ dans $_POST :
 *    name="email" -> $_POST['email'].
 *
 * Pour vérifier que la page est valide W3C : https://validator.w3.org/
 * (colle le code source de la page affichée, layout compris).
 *
 * Mise à jour (étape "Mise en pratique") : la vue reçoit maintenant de
 * l'action jusqu'à trois variables facultatives :
 *   $error   : un message d'erreur (email invalide, déjà utilisé...)
 *   $success : un message de réussite
 *   $email   : l'email déjà tapé, remis dans le champ pour ne pas avoir à
 *              le retaper après une erreur
 * Elles sont affichées avec htmlspecialchars(). Le 4e paramètre "false"
 * (double_encode) évite d'échapper DEUX fois : Request a déjà transformé
 * "&" en "&amp;", on ne veut pas obtenir "&amp;amp;" à l'écran.
 */
?>
<h1>Connexion</h1>

<?php /* Mise à jour (étape "Mise en pratique") : message envoyé par l'action
   après l'envoi du formulaire ($error ou $success, voir UserController).
   isset() : la variable n'existe pas au premier affichage (en GET). */ ?>
<?php if (isset($error)) : ?>
    <p class="error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8', false) ?></p>
<?php endif; ?>
<?php if (isset($success)) : ?>
    <p class="success" role="status"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8', false) ?></p>
<?php endif; ?>

<form method="post" action="<?= BASE_URI ?>/user/login">
    <!-- Le "for" du label correspond à l'"id" du champ : cliquer sur le
         texte place le curseur dans le champ (accessibilité). -->
    <p>
        <label for="email">Email</label>
        <!-- type="email" : le navigateur vérifie le format x@y.z. -->
        <input type="email" id="email" name="email" value="<?= htmlspecialchars($email ?? '', ENT_QUOTES, 'UTF-8', false) ?>" required>
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
