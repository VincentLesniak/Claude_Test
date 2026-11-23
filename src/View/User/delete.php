<?php
/*
 * Vue : confirmation de la suppression du compte (UserController::deleteAction,
 * URL /user/delete en GET).
 *
 * Ajout (partie « My cinema by PiePHP »). Supprimer son compte est
 * définitif : la page explique ce qui va disparaître, et seul le bouton
 * (un formulaire POST vers la même adresse) déclenche la suppression.
 * Variable reçue : $error (facultatif), si la base est indisponible.
 */
?>
<h1>Supprimer mon compte</h1>

@isset($error)
    <p class="error" role="alert">{{ $error }}</p>
@endisset

<p>Votre compte et tout votre historique de films vus seront supprimés définitivement.</p>

<form method="post" action="{{ BASE_URI }}/user/delete">
    <p><button type="submit">Oui, supprimer mon compte</button></p>
</form>

<p><a href="{{ BASE_URI }}/user/show">Non, revenir au profil</a></p>
