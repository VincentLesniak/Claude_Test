<?php

namespace Model;

use Core\Entity;

/**
 * Model de l'entité « Historique » (partie « My cinema by PiePHP »).
 *
 * Une ligne d'historique dit : « l'utilisateur n°X a vu le film n°Y à
 * telle date ». Le sujet donne deux relations :
 *   - User 'has many historiques' (écrite dans UserModel) ;
 *   - Historique 'has one film' (écrite ici).
 * La table historiques contient donc user_id et film_id.
 *
 * PLAN DU FICHIER
 *   - $relations : 'has one film' ;
 *   - COLUMNS et les attributs : user_id, film_id, vu_le ;
 *   - findForUser() : l'historique d'un utilisateur, film compris ;
 *   - deleteForFilm() : efface l'historique d'un film supprimé ;
 *   - columns() : la liste blanche donnée à Entity::save().
 *
 * La table est devinée par Entity::getTable() : HistoriqueModel ->
 * historiques. ORM::singular('historiques') donne 'historique', ce qui
 * permet aussi à Entity de retrouver ce model depuis UserModel.
 */
class HistoriqueModel extends Entity
{
    /**
     * Les colonnes que save() a le droit d'enregistrer.
     */
    private const COLUMNS = ['user_id', 'film_id', 'vu_le'];

    /**
     * La relation demandée par le sujet : 'has one film'.
     *
     * Pourquoi ? Chaque ligne d'historique lue contient alors une entrée
     * 'film' : la ligne complète du film (titre, année...), récupérée par
     * l'ORM avec un INNER JOIN.
     * Pertinence : le profil affiche le TITRE des films vus, alors que la
     * table historiques ne contient que leur id. Sans relation, il faudrait
     * relire chaque film à la main.
     *
     * @var string[]
     */
    private static $relations = ['has one film'];

    /**
     * L'id de l'utilisateur (colonne user_id).
     *
     * @var int|null
     */
    public $user_id = null;

    /**
     * L'id du film vu (colonne film_id).
     *
     * @var int|null
     */
    public $film_id = null;

    /**
     * La date à laquelle le film a été ajouté à l'historique, au format
     * MySQL "AAAA-MM-JJ HH:MM:SS" (colonne vu_le).
     *
     * @var string|null
     */
    public $vu_le = null;

    /**
     * L'historique d'un utilisateur, du plus récent au plus ancien.
     *
     * Chaque ligne contient l'entrée 'film' (relation 'has one film') :
     *   [['id' => 4, 'user_id' => 1, 'film_id' => 2, 'vu_le' => '...',
     *     'film' => ['id' => 2, 'titre' => 'Les Temps modernes', ...]], ...]
     *
     * "id DESC" en second critère : deux films ajoutés dans la même seconde
     * restent dans l'ordre d'ajout (le plus récent d'abord).
     *
     * Pourquoi ? C'est la requête du profil : elle a sa place dans le
     * model, pas dans le controller (qui ne doit pas savoir qu'il existe une
     * colonne user_id).
     * Pertinence : find() hérité d'Entity fait tout le travail, WHERE
     * préparé et relation compris : trois lignes suffisent.
     *
     * @param int $userId l'id de l'utilisateur
     * @return array la liste des lignes, film compris
     */
    public function findForUser(int $userId): array
    {
        return $this->find([
            'WHERE' => ['user_id' => $userId],
            'ORDER BY' => 'vu_le DESC, id DESC',
        ]);
    }

    /**
     * Supprime toutes les lignes d'historique d'un film.
     *
     * Pourquoi ? Quand un film est supprimé, ses lignes d'historique
     * pointeraient vers un film qui n'existe plus ('film' vaudrait null).
     * FilmModel::delete() appelle donc cette méthode juste avant.
     * Pertinence : l'ORM ne supprime que par id (le sujet ne demande pas de
     * DELETE ... WHERE) : on cherche les lignes, puis on les supprime une
     * par une avec le delete() hérité. Pour un petit site, c'est largement
     * suffisant, et aucune requête SQL n'est écrite ici.
     *
     * @param int $filmId l'id du film
     * @return int le nombre de lignes supprimées
     */
    public function deleteForFilm(int $filmId): int
    {
        $deleted = 0;
        foreach ($this->find(['WHERE' => ['film_id' => $filmId]]) as $row) {
            // "+=" sur un booléen : true compte pour 1, false pour 0.
            $deleted += (int) $this->delete((int) $row['id']);
        }
        return $deleted;
    }

    /**
     * La liste blanche des colonnes (voir Entity::columns()).
     *
     * Pourquoi ? Une ligne lue en base contient aussi l'entrée 'film' (la
     * relation) : seules les trois vraies colonnes doivent partir en base.
     * Pertinence : même règle que les autres models.
     */
    protected function columns(): array
    {
        return self::COLUMNS;
    }
}
