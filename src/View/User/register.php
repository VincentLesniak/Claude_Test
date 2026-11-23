<?php
/*
 * Vue : formulaire d'inscription (UserController::registerAction,
 * URL /user/register ou /inscription).
 *
 * Même principe que login.php : un POST avec les champs email et password.
 * L'action pointe vers registerAction, qui enregistrera l'utilisateur en
 * base à l'étape "Mise en pratique" (pour l'instant elle réaffiche le
 * formulaire).
 *
 * Mise à jour (étape "Mise en pratique") : c'est fait, registerAction
 * enregistre l'utilisateur quand le formulaire est envoyé. La vue reçoit maintenant de
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
<h1>Inscription</h1>

<?php /* Mise à jour (étape "Mise en pratique") : message envoyé par l'action
   après l'envoi du formulaire ($error ou $success, voir UserController).
   isset() : la variable n'existe pas au premier affichage (en GET). */ ?>
<?php if (isset($error)) : ?>
    <p class="error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8', false) ?></p>
<?php endif; ?>
<?php if (isset($success)) : ?>
    <p class="success" role="status"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8', false) ?></p>
<?php endif; ?>

<form method="post" action="<?= BASE_URI ?>/user/register">
    <p>
        <label for="email">Email</label>
        <input type="email" id="email" name="email" value="<?= htmlspecialchars($email ?? '', ENT_QUOTES, 'UTF-8', false) ?>" required>
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
