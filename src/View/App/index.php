<?php
/*
 * Vue : page d'accueil (AppController::indexAction).
 *
 * Une vue n'est qu'un MORCEAU de page : pas de <html>, <head> ni <body>,
 * c'est le layout (src/View/index.php) qui les ajoute autour.
 *
 * Mise à jour (partie « My cinema by PiePHP ») : l'accueil affiche les films
 * de la base, comme le demande le sujet. Variables reçues :
 *   $films         la liste des films (un tableau par film), chacun avec
 *                  son entrée 'genre' (relation 'has one genre' : la ligne
 *                  du genre, ou null) ;
 *   $error         (facultatif) le message si la base est indisponible ;
 *   $currentUserId l'id du membre connecté, ou null (ajouté par render()).
 *
 * La vue utilise les tags du moteur de templates (Core/TemplateEngine.php) :
 * les doubles accolades affichent une valeur échappée, les directives
 * précédées d'un arobase (if, foreach, empty, isset) deviennent les
 * structures PHP correspondantes. Comme indiqué dans show.php, on n'écrit
 * pas de vrai tag dans ce commentaire : il serait compilé lui aussi.
 *
 * Chaque titre est un lien vers /film/show?id=... : « au clic sur un film,
 * afficher le détail du film sur une nouvelle page ».
 */
?>
<h1>my_cinema</h1>
<p>La banque de films de la communauté, propulsée par PiePHP.</p>

@isset($error)
    <p class="error" role="alert">{{ $error }}</p>
@endisset

@if ($currentUserId !== null)
    <p><a href="{{ BASE_URI }}/film/add">Ajouter un film</a></p>
@endif

@empty($films)
    <p>Aucun film pour le moment.</p>
@endempty

@if (!empty($films))
    <ul>
    @foreach ($films as $film)
        <li>
            <a href="{{ BASE_URI }}/film/show?id={{ $film['id'] }}">{{ $film['titre'] }}</a>
            @if (!empty($film['annee']))
                ({{ $film['annee'] }})
            @endif
            @if ($film['genre'] !== null)
                - {{ $film['genre']['nom'] }}
            @endif
        </li>
    @endforeach
    </ul>
@endif
