<?php
/*
 * Vue : espace utilisateur (UserController::indexAction, URL /user).
 * Un morceau de page seulement : le layout ajoute <html>, <head> et le menu.
 */
?>
<h1>Utilisateurs</h1>
<ul>
    <li><a href="<?= BASE_URI ?>/user/register">Créer un compte</a></li>
    <li><a href="<?= BASE_URI ?>/user/login">Se connecter</a></li>
    <li><a href="<?= BASE_URI ?>/user/show">Voir un profil</a></li>
</ul>
