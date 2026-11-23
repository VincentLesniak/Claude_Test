<?php
/*
 * Vue : profil d'un utilisateur (UserController::showAction, URL /user/show).
 *
 * Variable reçue : $user, un tableau ['id' => ..., 'email' => ...]. Elle
 * vient du 2e paramètre de render() : $this->render('show', ['user' => $user]).
 *
 * SÉCURITÉ : toute donnée qui peut venir d'un visiteur (ici l'email saisi à
 * l'inscription) passe par htmlspecialchars() avant d'être affichée. Il
 * transforme < > " ' & en entités HTML (&lt; ...) : si quelqu'un s'inscrit
 * avec un "email" contenant <script>, il s'affiche comme du texte au lieu
 * d'être exécuté par le navigateur (faille XSS).
 *
 * Mise à jour (étape "Le Model") : $user vient maintenant de la base
 * (UserModel::read()) quand un utilisateur est connecté. Sinon, $example
 * vaut true et on affiche les données d'exemple avec un lien de connexion.
 * htmlspecialchars() reçoit "false" en 4e paramètre (double_encode) : l'email
 * a déjà été échappé par Request à l'inscription, on évite de l'échapper deux
 * fois ("&amp;amp;" à l'écran). Les balises restent bien neutralisées.
 */
?>
<h1>Profil</h1>

<?php if (isset($error)) : ?>
    <p class="error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8', false) ?></p>
<?php endif; ?>
<?php if (!empty($example)) : ?>
    <p>Profil d'exemple. <a href="<?= BASE_URI ?>/user/login">Connectez-vous</a> pour voir le vôtre.</p>
<?php else : ?>
    <p><a href="<?= BASE_URI ?>/user/logout">Se déconnecter</a></p>
<?php endif; ?>

<dl>
    <dt>Identifiant</dt>
    <dd><?= (int) $user['id'] ?></dd>

    <dt>Email</dt>
    <dd><?= htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8', false) ?></dd>
</dl>
