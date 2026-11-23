<?php

namespace Controller;

use Core\Controller;
use Model\GenreModel;
use PDOException;
use RuntimeException;

/**
 * Controller de l'entité « Genre » (partie « My cinema by PiePHP »).
 *
 * Les URL :
 *   /genre              la liste des genres, avec leur nombre de films
 *   /genre/add          ajouter un genre (membres connectés)
 *   /genre/edit?id=2    modifier un genre (membres connectés)
 *   /genre/delete?id=2  supprimer un genre (membres, formulaire POST)
 *
 * PLAN DU FICHIER
 *   - indexAction(), addAction(), editAction(), deleteAction() ;
 *   - outils privés : findGenre(), renderForm().
 *
 * Même organisation que FilmController : GET affiche le formulaire, POST le
 * traite, le model valide, puis on redirige avec un message flash.
 */
class GenreController extends Controller
{
    /**
     * /genre : la liste des genres.
     *
     * Chaque genre contient l'entrée 'films' (relation 'has many films') :
     * la vue affiche combien de films il contient avec count().
     *
     * Pourquoi ? Il faut une page pour accéder à l'ajout, la modification
     * et la suppression des genres demandés par le sujet.
     * Pertinence : c'est une démonstration de la relation 'has many' : la
     * liste des films de chaque genre arrive sans écrire de jointure.
     */
    public function indexAction()
    {
        $scope = ['title' => 'Genres', 'genres' => []];
        try {
            $scope['genres'] = (new GenreModel())->find(['ORDER BY' => 'nom ASC']);
        } catch (PDOException | RuntimeException $e) {
            $scope['error'] = $this->databaseError();
        }
        $this->render('index', $scope);
    }

    /**
     * /genre/add : ajouter un genre.
     *
     * Pourquoi ? Demandé par le sujet (« Ajouter un genre »).
     * Pertinence : GenreModel::validate() refuse un nom vide ou déjà pris ;
     * le controller se contente d'afficher sa réponse.
     */
    public function addAction()
    {
        if (!$this->requireLogin()) {
            return;
        }

        try {
            $genre = new GenreModel();
            if ($this->request->isPost()) {
                $genre->nom = $this->postValue('nom');
                $error = $genre->validate();
                if ($error === null) {
                    $genre->save();
                    $this->flash('success', 'Le genre « ' . $genre->nom . ' » a été ajouté.');
                    $this->redirect('/genre');
                    return;
                }
                $this->renderForm('Ajouter un genre', $genre, '/genre/add', $error);
                return;
            }
            $this->renderForm('Ajouter un genre', $genre, '/genre/add');
        } catch (PDOException | RuntimeException $e) {
            $this->renderForm('Ajouter un genre', null, '/genre/add', $this->databaseError());
        }
    }

    /**
     * /genre/edit?id=2 : renommer un genre.
     *
     * Pourquoi ? Demandé par le sujet (« Modifier un genre »).
     * Pertinence : les films gardent le même genre_id, ils suivent donc le
     * nouveau nom sans rien modifier d'autre (c'est l'intérêt d'une table
     * genres séparée plutôt que d'un nom recopié dans chaque film).
     */
    public function editAction()
    {
        if (!$this->requireLogin()) {
            return;
        }

        try {
            $genre = $this->findGenre();
            if ($genre === null) {
                return;
            }
            $url = '/genre/edit?id=' . $genre->id;

            if ($this->request->isPost()) {
                $genre->nom = $this->postValue('nom');
                $error = $genre->validate();
                if ($error === null) {
                    $genre->save();
                    $this->flash('success', 'Le genre a été modifié.');
                    $this->redirect('/genre');
                    return;
                }
                $this->renderForm('Modifier un genre', $genre, $url, $error);
                return;
            }
            $this->renderForm('Modifier un genre', $genre, $url);
        } catch (PDOException | RuntimeException $e) {
            $this->renderForm('Modifier un genre', null, '/genre', $this->databaseError());
        }
    }

    /**
     * /genre/delete?id=2 : supprimer un genre (formulaire POST uniquement).
     *
     * Un genre qui contient encore des films n'est PAS supprimé : ces films
     * se retrouveraient avec un genre_id qui ne désigne plus rien (et MySQL
     * refuserait de toute façon, à cause de la clé étrangère). On demande
     * d'abord de changer le genre de ces films.
     *
     * Pourquoi ? Demandé par le sujet (« Supprimer un genre »).
     * Pertinence : $genre->films vient de la relation 'has many films' :
     * une seule ligne suffit pour savoir s'il reste des films.
     */
    public function deleteAction()
    {
        if (!$this->requireLogin()) {
            return;
        }
        if (!$this->request->isPost()) {
            $this->redirect('/genre');
            return;
        }

        try {
            $genre = $this->findGenre();
            if ($genre === null) {
                return;
            }
            if ($genre->films !== []) {
                $this->flash(
                    'error',
                    'Le genre « ' . $genre->nom . ' » contient encore ' . count($genre->films)
                        . ' film(s) : changez d\'abord leur genre.'
                );
            } else {
                $genre->delete();
                $this->flash('success', 'Le genre « ' . $genre->nom . ' » a été supprimé.');
            }
        } catch (PDOException | RuntimeException $e) {
            $this->flash('error', $this->databaseError());
        }
        $this->redirect('/genre');
    }

    /**
     * Le genre dont l'id est dans l'URL (?id=2), ou null (la redirection
     * vers la liste est alors déjà préparée).
     *
     * Pourquoi ? editAction() et deleteAction() commencent par là.
     * Pertinence : même principe que FilmController::findFilm().
     */
    private function findGenre(): ?GenreModel
    {
        $id = $this->queryId();
        $genre = $id === null ? null : new GenreModel(['id' => $id]);
        if ($genre === null || $genre->id === null) {
            $this->flash('error', "Ce genre n'existe pas.");
            $this->redirect('/genre');
            return null;
        }
        return $genre;
    }

    /**
     * Affiche le formulaire d'ajout / de modification (vue form.php).
     *
     * Pourquoi ? Plusieurs cas l'affichent (GET, POST en erreur, base
     * indisponible) pour l'ajout comme pour la modification.
     * Pertinence : la vue reçoit toujours les mêmes variables ; $nom vaut
     * '' quand il n'y a pas de genre (base indisponible).
     *
     * @param string           $title  le titre de la page
     * @param GenreModel|null  $genre  le genre (null si la base est indisponible)
     * @param string           $action l'adresse d'envoi du formulaire
     * @param string|null      $error  le message d'erreur à afficher
     */
    private function renderForm(string $title, ?GenreModel $genre, string $action, ?string $error = null): void
    {
        $scope = ['title' => $title, 'nom' => $genre !== null ? $genre->nom : '', 'action' => $action];
        if ($error !== null) {
            $scope['error'] = $error;
        }
        $this->render('form', $scope);
    }
}
