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
 *
 * Mise à jour (étape "Avancé - le moteur de templates") : cette vue utilise
 * maintenant les tags du moteur (Core/TemplateEngine.php, le tableau des
 * équivalences est en haut de ce fichier). Les règles ci-dessus restent
 * vraies, c'est le moteur qui les applique :
 *   - les doubles accolades deviennent htmlentities(..., ENT_QUOTES, 'UTF-8',
 *     false) : même protection XSS que htmlspecialchars(), toujours sans
 *     double échappement (le "false" à la fin) ;
 *   - les directives isset, if, else et endif (précédées d'un arobase)
 *     deviennent les "if (...) :" qu'on écrivait avant à la main.
 * Le (int) devant l'id disparaît : les accolades échappent tout, un nombre
 * aussi.
 * Attention : le moteur lit TOUT le fichier, commentaires compris. On n'écrit
 * donc pas de vrai tag dans un commentaire de vue (il serait compilé lui
 * aussi : sans danger dans un commentaire PHP, mais source de confusion).
 */
?>
<h1>Profil</h1>

@isset($error)
    <p class="error" role="alert">{{ $error }}</p>
@endisset
@if (!empty($example))
    <p>Profil d'exemple. <a href="{{ BASE_URI }}/user/login">Connectez-vous</a> pour voir le vôtre.</p>
@else
    <p><a href="{{ BASE_URI }}/user/logout">Se déconnecter</a></p>
@endif

<dl>
    <dt>Identifiant</dt>
    <dd>{{ $user['id'] }}</dd>

    <dt>Email</dt>
    <dd>{{ $user['email'] }}</dd>
</dl>
