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
 */
?>
<h1>Profil</h1>

<dl>
    <dt>Identifiant</dt>
    <dd><?= (int) $user['id'] ?></dd>

    <dt>Email</dt>
    <dd><?= htmlspecialchars($user['email']) ?></dd>
</dl>
