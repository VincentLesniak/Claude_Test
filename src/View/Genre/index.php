<?php
/*
 * Vue : la liste des genres (GenreController::indexAction, URL /genre).
 *
 * Variables reçues :
 *   $genres        la liste des genres, triés par nom. Chacun contient
 *                  l'entrée 'films' (relation 'has many films') : la liste
 *                  de ses films. count() donne leur nombre ;
 *   $error         (facultatif) le message si la base est indisponible ;
 *   $currentUserId l'id du membre connecté, ou null (ajouté par render()).
 *
 * Le bouton « Supprimer » est un formulaire POST (voir Film/show.php). Le
 * controller refuse de supprimer un genre qui contient encore des films.
 */
?>
<h1>Genres</h1>

@isset($error)
    <p class="error" role="alert">{{ $error }}</p>
@endisset

@if ($currentUserId !== null)
    <p><a href="{{ BASE_URI }}/genre/add">Ajouter un genre</a></p>
@endif

@empty($genres)
    <p>Aucun genre pour le moment.</p>
@endempty

@if (!empty($genres))
    <ul>
    @foreach ($genres as $genre)
        <li>
            {{ $genre['nom'] }} ({{ count($genre['films']) }} film(s))
            @if ($currentUserId !== null)
                <a href="{{ BASE_URI }}/genre/edit?id={{ $genre['id'] }}">Modifier</a>
                <form method="post" action="{{ BASE_URI }}/genre/delete?id={{ $genre['id'] }}">
                    <p><button type="submit">Supprimer</button></p>
                </form>
            @endif
        </li>
    @endforeach
    </ul>
@endif
