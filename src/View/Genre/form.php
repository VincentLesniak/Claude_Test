<?php
/*
 * Vue : formulaire d'ajout ET de modification d'un genre
 * (GenreController::addAction et editAction).
 *
 * Variables reçues :
 *   $title  le titre (« Ajouter un genre » ou « Modifier un genre ») ;
 *   $action l'adresse d'envoi, ex. /genre/edit?id=2 ;
 *   $nom    la valeur du champ (vide pour un ajout) ;
 *   $error  (facultatif) le message d'erreur renvoyé par le model.
 */
?>
<h1>{{ $title }}</h1>

@isset($error)
    <p class="error" role="alert">{{ $error }}</p>
@endisset

<form method="post" action="{{ BASE_URI }}{{ $action }}">
    <p>
        <label for="nom">Nom du genre</label>
        <input type="text" id="nom" name="nom" maxlength="100" value="{{ $nom }}" required>
    </p>
    <p>
        <button type="submit">Enregistrer</button>
    </p>
</form>

<p><a href="{{ BASE_URI }}/genre">Retour à la liste des genres</a></p>
