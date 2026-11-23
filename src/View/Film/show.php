<?php
/*
 * Vue : le détail d'un film (FilmController::showAction, URL /film/show?id=3).
 *
 * Variables reçues :
 *   $film          le film : id, titre, annee, resume, genre_id, et l'entrée
 *                  'genre' (relation 'has one genre' : ['id' => ...,
 *                  'nom' => ...], ou null si le film n'a pas de genre) ;
 *   $error         (facultatif) le message si la base est indisponible ;
 *   $currentUserId l'id du membre connecté, ou null (ajouté par render()).
 *
 * Les boutons qui MODIFIENT la base (« J'ai vu ce film », « Supprimer ») sont
 * des formulaires en method="post" : jamais de simple lien pour une action
 * qui écrit ou détruit des données (voir FilmController::deleteAction()).
 * L'id est dans l'adresse d'envoi (action="...?id=3"), le controller le lit
 * avec queryId().
 *
 * Les boutons ne sont affichés qu'aux membres connectés, mais ce n'est
 * qu'un confort : la vraie protection est requireLogin() dans le controller.
 *
 * nl2br() (dans le résumé) transforme chaque retour à la ligne en balise
 * br : un résumé sur plusieurs paragraphes garde sa mise en forme. Il est
 * appelé APRÈS l'échappement, sinon les balises br seraient elles-mêmes
 * échappées. C'est pourquoi le résumé est écrit en PHP classique, sans les
 * doubles accolades (qui échapperaient le résultat de nl2br()).
 */
?>
@isset($error)
    <h1>Film</h1>
    <p class="error" role="alert">{{ $error }}</p>
@endisset

@isset($film)
    <h1>{{ $film['titre'] }}</h1>

    <dl>
        <dt>Année</dt>
        <dd>{{ $film['annee'] ?? 'Inconnue' }}</dd>

        <dt>Genre</dt>
        @if ($film['genre'] !== null)
            <dd>{{ $film['genre']['nom'] }}</dd>
        @else
            <dd>Aucun genre</dd>
        @endif

        <dt>Résumé</dt>
        <dd><?= nl2br(htmlspecialchars((string) ($film['resume'] ?? ''), ENT_QUOTES, 'UTF-8', false)) ?></dd>
    </dl>

    @if ($currentUserId !== null)
        <form method="post" action="{{ BASE_URI }}/historique/add?film_id={{ $film['id'] }}">
            <p><button type="submit">J'ai vu ce film</button></p>
        </form>
        <p><a href="{{ BASE_URI }}/film/edit?id={{ $film['id'] }}">Modifier ce film</a></p>
        <form method="post" action="{{ BASE_URI }}/film/delete?id={{ $film['id'] }}">
            <p><button type="submit">Supprimer ce film</button></p>
        </form>
    @endif
@endisset

<p><a href="{{ BASE_URI }}/">Retour à la liste des films</a></p>
