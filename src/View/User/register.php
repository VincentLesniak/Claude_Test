<?php
/*
 * Vue : formulaire d'inscription (UserController::registerAction,
 * URL /user/register ou /inscription).
 *
 * Même principe que login.php : un POST avec les champs email et password.
 * L'action pointe vers registerAction, qui enregistrera l'utilisateur en
 * base à l'étape "Mise en pratique" (pour l'instant elle réaffiche le
 * formulaire).
 */
?>
<h1>Inscription</h1>

<form method="post" action="<?= BASE_URI ?>/user/register">
    <p>
        <label for="email">Email</label>
        <input type="email" id="email" name="email" required>
    </p>
    <p>
        <label for="password">Mot de passe</label>
        <!-- autocomplete="new-password" : le navigateur propose de générer
             un mot de passe au lieu de remplir un mot de passe enregistré. -->
        <input type="password" id="password" name="password" autocomplete="new-password" required>
    </p>
    <p>
        <button type="submit">Créer mon compte</button>
    </p>
</form>
