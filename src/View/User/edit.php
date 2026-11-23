<?php
/*
 * Vue : modifier son profil (UserController::editAction, URL /user/edit).
 *
 * Ajout (partie « My cinema by PiePHP »). Variables reçues :
 *   $email : l'email actuel (ou celui qui vient d'être tapé, après une erreur) ;
 *   $error : (facultatif) le message d'erreur.
 * En cas de succès, l'action redirige vers le profil avec un message : cette
 * vue n'a donc pas de message de réussite.
 *
 * Le champ mot de passe est vide et facultatif : on ne réaffiche JAMAIS un
 * mot de passe (la base ne contient d'ailleurs que son empreinte).
 * autocomplete="new-password" : le navigateur ne le remplit pas tout seul
 * avec le mot de passe enregistré.
 */
?>
<h1>Modifier mon profil</h1>

@isset($error)
    <p class="error" role="alert">{{ $error }}</p>
@endisset

<form method="post" action="{{ BASE_URI }}/user/edit">
    <p>
        <label for="email">Email</label>
        <input type="email" id="email" name="email" value="{{ $email ?? '' }}" required>
    </p>
    <p>
        <label for="password">Nouveau mot de passe (laisser vide pour le garder)</label>
        <input type="password" id="password" name="password" autocomplete="new-password">
    </p>
    <p>
        <button type="submit">Enregistrer</button>
    </p>
</form>

<p><a href="{{ BASE_URI }}/user/show">Retour au profil</a></p>
