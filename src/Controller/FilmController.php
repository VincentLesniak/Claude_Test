<?php

namespace Controller;

use Core\Controller;
use Model\FilmModel;
use Model\GenreModel;
use PDOException;
use RuntimeException;

/**
 * Controller de l'entité « Film » (partie « My cinema by PiePHP »).
 *
 * Les URL, déduites par le routage automatique /controller/action :
 *   /film/show?id=3   le détail d'un film (tout le monde)
 *   /film/add         ajouter un film (membres connectés)
 *   /film/edit?id=3   modifier un film (membres connectés)
 *   /film/delete?id=3 supprimer un film (membres, formulaire POST)
 * La liste des films est la page d'accueil (AppController::indexAction()).
 *
 * PLAN DU FICHIER
 *   - indexAction()  : /film renvoie vers la page d'accueil ;
 *   - showAction(), addAction(), editAction(), deleteAction() ;
 *   - outils privés : findFilm(), fillFromForm(), renderForm().
 *
 * Le schéma de addAction() et editAction() est celui de tous les
 * formulaires du site :
 *   GET  : on affiche le formulaire ;
 *   POST : on remplit le model avec les champs, on demande au model si
 *          c'est valide, puis on enregistre (save()) et on redirige ;
 *          sinon on réaffiche le formulaire avec l'erreur et ce qui a été tapé.
 * Le controller ne contient ni SQL (c'est le model) ni HTML (c'est la vue).
 */
class FilmController extends Controller
{
    /**
     * /film : il n'y a pas de page « liste des films » à part, c'est la page
     * d'accueil. On y renvoie le visiteur.
     *
     * Pourquoi ? Sans cette action, /film afficherait la page 404.
     * Pertinence : une seule liste de films à maintenir.
     */
    public function indexAction()
    {
        $this->redirect('/');
    }

    /**
     * /film/show?id=3 : le détail d'un film, son genre compris.
     *
     * new FilmModel(['id' => 3]) lit le film ET son genre (relation
     * 'has one genre') : la vue affiche $film['genre']['nom'] sans aucune
     * requête de plus. C'est ce que demande le sujet : « au clic sur un
     * film, afficher le détail du film sur une nouvelle page » et
     * « ajouter le genre à la description détaillée du film ».
     *
     * Pourquoi ? C'est la page vers laquelle mènent la liste des films et
     * les redirections après un ajout ou une modification.
     * Pertinence : un id absent ou inconnu renvoie à l'accueil avec un
     * message, plutôt qu'une page vide.
     */
    public function showAction()
    {
        try {
            $film = $this->findFilm();
        } catch (PDOException | RuntimeException $e) {
            $this->render('show', ['title' => 'Film', 'error' => $this->databaseError()]);
            return;
        }
        if ($film === null) {
            return; // findFilm() a préparé la redirection
        }

        // On passe à la vue un TABLEAU, pas l'objet : la vue n'a qu'à
        // afficher, elle ne doit pas pouvoir appeler save() ou delete().
        $this->render('show', ['title' => $film->titre, 'film' => $this->toArray($film)]);
    }

    /**
     * /film/add : ajouter un film à la banque de films.
     *
     * Pourquoi ? Demandé par le sujet (« Ajouter un film »).
     * Pertinence : en cas de succès, save() remplit $film->id, ce qui permet
     * de rediriger tout de suite vers la page du nouveau film.
     */
    public function addAction()
    {
        if (!$this->requireLogin()) {
            return;
        }

        try {
            $film = new FilmModel();
            if ($this->request->isPost()) {
                $this->fillFromForm($film);
                $error = $film->validate();
                if ($error === null) {
                    $film->save();
                    $this->flash('success', 'Le film « ' . $film->titre . ' » a été ajouté.');
                    $this->redirect('/film/show?id=' . $film->id);
                    return;
                }
                $this->renderForm('Ajouter un film', $film, '/film/add', $error);
                return;
            }
            $this->renderForm('Ajouter un film', $film, '/film/add');
        } catch (PDOException | RuntimeException $e) {
            $this->render('form', ['title' => 'Ajouter un film', 'error' => $this->databaseError()]);
        }
    }

    /**
     * /film/edit?id=3 : modifier un film.
     *
     * Le film est lu en base, puis ses attributs sont remplacés par ceux du
     * formulaire. save() compare avec les valeurs lues et n'envoie que les
     * colonnes modifiées (voir Entity::save()).
     *
     * Pourquoi ? Demandé par le sujet (« Modifier un film »).
     * Pertinence : le même formulaire (vue form.php) sert à l'ajout et à la
     * modification, seule l'adresse d'envoi change.
     */
    public function editAction()
    {
        if (!$this->requireLogin()) {
            return;
        }

        try {
            $film = $this->findFilm();
            if ($film === null) {
                return;
            }
            $url = '/film/edit?id=' . $film->id;

            if ($this->request->isPost()) {
                $this->fillFromForm($film);
                $error = $film->validate();
                if ($error === null) {
                    $film->save();
                    $this->flash('success', 'Le film a été modifié.');
                    $this->redirect('/film/show?id=' . $film->id);
                    return;
                }
                $this->renderForm('Modifier un film', $film, $url, $error);
                return;
            }
            $this->renderForm('Modifier un film', $film, $url);
        } catch (PDOException | RuntimeException $e) {
            $this->render('form', ['title' => 'Modifier un film', 'error' => $this->databaseError()]);
        }
    }

    /**
     * /film/delete?id=3 : supprimer un film (formulaire POST uniquement).
     *
     * Pourquoi POST ? Un simple lien (GET) peut être suivi sans le vouloir :
     * par un robot, par le préchargement du navigateur, ou par une image
     * piégée sur un autre site. Une action qui DÉTRUIT des données ne doit
     * jamais se déclencher en GET.
     * Pertinence : FilmModel::delete() efface aussi les lignes d'historique
     * du film ; le controller n'a pas à y penser.
     */
    public function deleteAction()
    {
        if (!$this->requireLogin()) {
            return;
        }
        if (!$this->request->isPost()) {
            $this->redirect('/');
            return;
        }

        try {
            $film = $this->findFilm();
            if ($film === null) {
                return;
            }
            $film->delete();
            $this->flash('success', 'Le film « ' . $film->titre . ' » a été supprimé.');
        } catch (PDOException | RuntimeException $e) {
            $this->flash('error', $this->databaseError());
        }
        $this->redirect('/');
    }

    /**
     * Le film dont l'id est dans l'URL (?id=3), ou null.
     *
     * Quand le film n'existe pas, la redirection vers l'accueil (avec un
     * message) est déjà préparée : l'action n'a plus qu'à s'arrêter.
     * L'entité ne reçoit QUE l'id, jamais toute l'URL.
     *
     * Pourquoi ? showAction(), editAction() et deleteAction() commencent
     * toutes par là. Pertinence : le cas « film introuvable » est traité
     * une seule fois, de la même façon partout.
     */
    private function findFilm(): ?FilmModel
    {
        $id = $this->queryId();
        // Entity : un id inconnu laisse l'entité vide (id null).
        $film = $id === null ? null : new FilmModel(['id' => $id]);
        if ($film === null || $film->id === null) {
            $this->flash('error', "Ce film n'existe pas.");
            $this->redirect('/');
            return null;
        }
        return $film;
    }

    /**
     * Recopie les champs du formulaire dans le film.
     *
     * Un champ vide devient null pour l'année et le genre : la colonne
     * accepte NULL (« inconnu »), alors que '' n'est pas un nombre.
     * L'opérateur "?:" renvoie la valeur de gauche si elle est « vraie »,
     * sinon celle de droite : '' ?: null donne null, '1999' ?: null donne
     * '1999'. ("0" est aussi « faux » en PHP, mais n'est ni une année ni un
     * id valide : null convient aussi.)
     *
     * Pourquoi ? addAction() et editAction() lisent les mêmes quatre champs.
     * Pertinence : on lit les champs UN PAR UN (liste blanche) au lieu de
     * donner tout $_POST au model : un champ "id" ajouté au formulaire ne
     * pourra pas changer le film modifié.
     */
    private function fillFromForm(FilmModel $film): void
    {
        $film->titre = $this->postValue('titre');
        $film->annee = $this->postValue('annee') ?: null;
        $film->resume = $this->postValue('resume');
        $film->genre_id = $this->postValue('genre_id') ?: null;
    }

    /**
     * Affiche le formulaire d'ajout / de modification (vue form.php).
     *
     * La vue a besoin de la liste des genres pour la liste déroulante :
     * GenreModel::find() la donne, triée par nom.
     *
     * Pourquoi ? Quatre endroits affichent ce formulaire (GET et POST en
     * erreur, pour l'ajout et pour la modification).
     * Pertinence : les variables de la vue sont préparées au même endroit,
     * aucune ne peut être oubliée.
     *
     * @param string      $title  le titre de la page
     * @param FilmModel   $film   le film (vide pour un ajout)
     * @param string      $action l'adresse d'envoi du formulaire
     * @param string|null $error  le message d'erreur à afficher
     */
    private function renderForm(string $title, FilmModel $film, string $action, ?string $error = null): void
    {
        $scope = [
            'title' => $title,
            'film' => $this->toArray($film),
            'genres' => (new GenreModel())->find(['ORDER BY' => 'nom ASC']),
            'action' => $action,
        ];
        if ($error !== null) {
            $scope['error'] = $error;
        }
        $this->render('form', $scope);
    }

    /**
     * Le film sous forme de tableau, pour une vue.
     *
     * get_object_vars() appelé de L'EXTÉRIEUR de l'objet ne renvoie que ses
     * attributs publics : les colonnes, l'id et la relation 'genre' (s'il
     * a été lu en base), jamais la connexion à la base.
     *
     * Pourquoi ? Les vues travaillent avec des tableaux ($film['titre']),
     * comme celles des étapes précédentes.
     */
    private function toArray(FilmModel $film): array
    {
        return get_object_vars($film);
    }
}
