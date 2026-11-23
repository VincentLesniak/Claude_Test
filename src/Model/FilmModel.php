<?php

namespace Model;

use Core\Entity;

/**
 * Model de l'entité « Film » (partie « My cinema by PiePHP »).
 *
 * Le cœur du site : la « banque de films » que les utilisateurs mettent à
 * jour. Le sujet l'écrit « Film 'has one' Genre » : chaque film a un genre,
 * dont l'id est rangé dans la colonne films.genre_id.
 *
 * PLAN DU FICHIER
 *   - $relations : 'has one genre' ;
 *   - COLUMNS et les attributs : titre, annee, resume, genre_id ;
 *   - validate() : les règles d'un film (titre obligatoire, année
 *     plausible, genre existant) ;
 *   - delete() : redéfini pour effacer aussi l'historique du film ;
 *   - columns() : la liste blanche donnée à Entity::save().
 *
 * Le CRUD est hérité de Core\Entity. Exemple :
 *   $film = new FilmModel(['titre' => 'Metropolis', 'annee' => 1927]);
 *   $film->save();                        // INSERT, $film->id est rempli
 *   $film = new FilmModel(['id' => 1]);   // lu en base, genre compris
 *   $film->genre['nom'];                  // 'Science-fiction' (ou null)
 */
class FilmModel extends Entity
{
    /**
     * La longueur maximale d'un titre, la même que VARCHAR(255).
     */
    public const TITRE_MAX = 255;

    /**
     * La première année acceptée : 1888, l'année des plus anciens films
     * connus. En dessous, c'est forcément une faute de frappe.
     */
    public const ANNEE_MIN = 1888;

    /**
     * Les colonnes que save() a le droit d'enregistrer.
     */
    private const COLUMNS = ['titre', 'annee', 'resume', 'genre_id'];

    /**
     * La relation demandée par le sujet : 'has one genre'.
     *
     * Pourquoi ? Chaque film lu contient alors une entrée 'genre' : la
     * ligne de son genre (['id' => 1, 'nom' => 'Science-fiction']), ou null
     * s'il n'en a pas. Pertinence : c'est ce qui permet d'« ajouter le genre
     * à la description détaillée du film » (demandé par le sujet) sans
     * écrire la jointure : l'ORM la fait (voir ORM::readRelation()).
     *
     * @var string[]
     */
    private static $relations = ['has one genre'];

    /**
     * Le titre du film (colonne titre, obligatoire).
     *
     * @var string
     */
    public $titre = '';

    /**
     * L'année de sortie (colonne annee), ou null si on ne la connaît pas.
     *
     * @var int|string|null
     */
    public $annee = null;

    /**
     * Le résumé (colonne resume, facultatif).
     *
     * @var string|null
     */
    public $resume = '';

    /**
     * L'id du genre (colonne genre_id), ou null pour un film sans genre.
     *
     * @var int|string|null
     */
    public $genre_id = null;

    /**
     * Vérifie le film avant de l'enregistrer.
     *
     * Règles :
     *   - un titre, de TITRE_MAX caractères au plus ;
     *   - une année vide, ou un nombre entier entre ANNEE_MIN et l'an
     *     prochain (un film peut être annoncé avant sa sortie) ;
     *   - un genre vide, ou l'id d'un genre qui existe.
     *
     * filter_var(..., FILTER_VALIDATE_INT, ['options' => [...]]) renvoie le
     * nombre s'il s'agit d'un entier entre min_range et max_range, et false
     * sinon : "1999" passe, "abc", "19.5" ou "3000" non.
     *
     * Pourquoi ? Comme pour GenreModel, les règles d'un film sont dans son
     * model ; le controller se contente de demander si c'est valide.
     * Pertinence : sans la vérification du genre, un formulaire modifié à la
     * main (genre_id=999) donnerait une erreur de clé étrangère MySQL au
     * lieu d'un message clair.
     *
     * @return string|null le message d'erreur, ou null si tout est bon
     */
    public function validate(): ?string
    {
        $titre = trim((string) $this->titre);
        if ($titre === '') {
            return 'Le titre est obligatoire.';
        }
        if (mb_strlen($titre) > self::TITRE_MAX) {
            return 'Le titre ne doit pas dépasser ' . self::TITRE_MAX . ' caractères.';
        }

        if ($this->annee !== null) {
            // date('Y') : l'année en cours, ex. "2026".
            $range = ['min_range' => self::ANNEE_MIN, 'max_range' => (int) date('Y') + 1];
            if (filter_var($this->annee, FILTER_VALIDATE_INT, ['options' => $range]) === false) {
                return "L'année doit être un nombre entre " . self::ANNEE_MIN . ' et ' . $range['max_range'] . '.';
            }
        }

        if ($this->genre_id !== null) {
            // read() renvoie null si l'id n'existe pas. On donne au
            // GenreModel la MÊME connexion que ce film (voir
            // Entity::getConnection()).
            $genres = new GenreModel([], $this->getConnection());
            if (!ctype_digit((string) $this->genre_id) || $genres->read((int) $this->genre_id) === null) {
                return "Le genre choisi n'existe pas.";
            }
        }
        return null;
    }

    /**
     * Supprime le film ET ses lignes d'historique.
     *
     * Même signature que Entity::delete() (obligatoire pour redéfinir une
     * méthode) : sans argument, le film se supprime lui-même.
     * parent::delete() appelle ensuite la version d'Entity, qui fait le
     * vrai DELETE.
     *
     * Pourquoi ? Une ligne d'historique sans film n'a plus de sens : le
     * profil afficherait un film vide. La base le fait aussi (ON DELETE
     * CASCADE dans database.sql), mais SQLite ne respecte les clés
     * étrangères que si on le lui demande : le faire ici marche partout.
     * Pertinence : le controller n'a qu'une ligne à écrire, $film->delete(),
     * la règle « supprimer un film efface son historique » est au bon
     * endroit (le model) et ne peut pas être oubliée.
     *
     * @return bool true si le film a été supprimé
     */
    public function delete(?int $id = null): bool
    {
        $id = $id ?? $this->id;
        if ($id === null) {
            return false;
        }

        (new HistoriqueModel([], $this->getConnection()))->deleteForFilm($id);
        return parent::delete($id);
    }

    /**
     * La liste blanche des colonnes (voir Entity::columns()).
     *
     * Pourquoi ? Un film lu en base contient aussi l'entrée 'genre' (la
     * relation) : seules les quatre vraies colonnes partent en base.
     * Pertinence : même règle que les autres models.
     */
    protected function columns(): array
    {
        return self::COLUMNS;
    }
}
