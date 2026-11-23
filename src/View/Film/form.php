<?php
/*
 * Vue : formulaire d'ajout ET de modification d'un film
 * (FilmController::addAction et editAction, URL /film/add et /film/edit?id=3).
 *
 * Une seule vue pour deux actions : seuls le titre de la page ($title) et
 * l'adresse d'envoi ($action) changent. Variables reçues :
 *   $title  le titre (« Ajouter un film » ou « Modifier un film ») ;
 *   $action l'adresse d'envoi, ex. /film/edit?id=3 ;
 *   $film   les valeurs à mettre dans les champs (vides pour un ajout, celles
 *           du film pour une modification, ou ce qui a été tapé après une
 *           erreur) ;
 *   $genres la liste des genres, pour la liste déroulante ;
 *   $error  (facultatif) le message d'erreur renvoyé par le model.
 * Si la base est indisponible, seuls $title et $error existent : le
 * formulaire n'est pas affiché.
 *
 * Les attributs required, maxlength, min et max aident le visiteur (le
 * navigateur prévient avant l'envoi), mais ne protègent rien : la vraie
 * vérification est FilmModel::validate(), côté serveur.
 *
 * La liste déroulante (select) envoie la value de l'option choisie : l'id
 * du genre. L'option vide (value="") signifie « aucun genre ». L'option du
 * genre actuel reçoit l'attribut selected, pour être choisie d'avance.
 * On compare des textes avec (string) : l'id peut arriver en nombre (base)
 * ou en texte (formulaire).
 */
?>
<h1>{{ $title }}</h1>

@isset($error)
    <p class="error" role="alert">{{ $error }}</p>
@endisset

@isset($film)
<form method="post" action="{{ BASE_URI }}{{ $action }}">
    <p>
        <label for="titre">Titre</label>
        <input type="text" id="titre" name="titre" maxlength="255" value="{{ $film['titre'] ?? '' }}" required>
    </p>
    <p>
        <label for="annee">Année</label>
        <input type="number" id="annee" name="annee" min="1888" value="{{ $film['annee'] ?? '' }}">
    </p>
    <p>
        <label for="genre_id">Genre</label>
        <select id="genre_id" name="genre_id">
            <option value="">Aucun genre</option>
            @foreach ($genres as $genre)
                <option value="{{ $genre['id'] }}" @if ((string) $genre['id'] === (string) ($film['genre_id'] ?? '')) selected @endif>{{ $genre['nom'] }}</option>
            @endforeach
        </select>
    </p>
    <p>
        <label for="resume">Résumé</label>
        <textarea id="resume" name="resume" rows="5" cols="60">{{ $film['resume'] ?? '' }}</textarea>
    </p>
    <p>
        <button type="submit">Enregistrer</button>
    </p>
</form>
@endisset

<p><a href="{{ BASE_URI }}/">Retour à la liste des films</a></p>
