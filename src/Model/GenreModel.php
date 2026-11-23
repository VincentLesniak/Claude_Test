<?php

namespace Model;

use Core\Entity;

/**
 * Model de l'entité « Genre » (partie « My cinema by PiePHP »).
 *
 * Un genre (Action, Comédie...) regroupe des films. Le sujet l'écrit
 * « Genre 'has many films' » : un genre a plusieurs films, et chaque film
 * garde l'id de son genre dans la colonne films.genre_id.
 *
 * PLAN DU FICHIER
 *   - $relations : la phrase du sujet ('has many films') ;
 *   - COLUMNS, $nom : la seule colonne enregistrable ;
 *   - validate() : les règles d'un nom de genre (obligatoire, unique...) ;
 *   - columns() : la liste blanche donnée à Entity::save().
 *
 * Tout le reste (create, read, update, delete, readAll, find, save) est
 * hérité de Core\Entity : c'est tout l'intérêt du framework, ce model
 * n'écrit AUCUNE requête SQL. La table est devinée d'après le nom de la
 * classe : GenreModel -> genres.
 *
 * Exemple :
 *   $genre = new GenreModel(['id' => 1]);
 *   $genre->nom;     // 'Science-fiction'
 *   $genre->films;   // la liste des films de ce genre (relation)
 */
class GenreModel extends Entity
{
    /**
     * La longueur maximale d'un nom, la même que VARCHAR(100) dans
     * database.sql.
     */
    public const NOM_MAX = 100;

    /**
     * Les colonnes que save() a le droit d'enregistrer.
     */
    private const COLUMNS = ['nom'];

    /**
     * La relation demandée par le sujet : 'has many films'.
     *
     * FilmModel dit 'has one genre' (et pas 'has many genres') : Entity en
     * déduit un 1 : N, lu dans films.genre_id (voir Entity::getRelations()).
     *
     * Pourquoi ? Chaque genre lu contient alors une entrée 'films', la liste
     * de ses films. Pertinence : la page des genres affiche combien de
     * films chacun contient, et GenreController::deleteAction() refuse de
     * supprimer un genre qui a encore des films, sans écrire de SQL.
     *
     * @var string[]
     */
    private static $relations = ['has many films'];

    /**
     * Le nom du genre (colonne nom).
     *
     * @var string
     */
    public $nom = '';

    /**
     * Vérifie le genre avant de l'enregistrer.
     *
     * Règles : un nom obligatoire, de NOM_MAX caractères au plus, et pas
     * déjà pris par un AUTRE genre (la colonne est UNIQUE en base).
     *
     * mb_strlen() compte les CARACTÈRES ("é" = 1), alors que strlen()
     * compte les octets ("é" = 2 en UTF-8) : c'est mb_strlen() qui
     * correspond à VARCHAR(100).
     *
     * Pourquoi ? Les règles d'un genre appartiennent au model : le
     * controller demande seulement « est-ce valide ? ». Les attributs
     * required/maxlength du formulaire HTML se contournent facilement, seule
     * la vérification côté serveur compte.
     * Pertinence : sans le test d'unicité, MySQL refuserait l'INSERT et on
     * afficherait une erreur de base de données au lieu d'un message clair.
     *
     * @return string|null le message d'erreur, ou null si tout est bon
     */
    public function validate(): ?string
    {
        $nom = trim((string) $this->nom);
        if ($nom === '') {
            return 'Le nom du genre est obligatoire.';
        }
        if (mb_strlen($nom) > self::NOM_MAX) {
            return 'Le nom du genre ne doit pas dépasser ' . self::NOM_MAX . ' caractères.';
        }

        // find() hérité d'Entity : un WHERE en tableau, donc une valeur
        // préparée. On ignore le genre lui-même (cas de la modification :
        // garder son propre nom n'est pas un doublon).
        foreach ($this->find(['WHERE' => ['nom' => $nom]]) as $other) {
            if ((int) $other['id'] !== $this->id) {
                return 'Ce genre existe déjà.';
            }
        }
        return null;
    }

    /**
     * La liste blanche des colonnes (voir Entity::columns()).
     *
     * Pourquoi ? Un genre lu en base contient aussi l'entrée 'films' (la
     * relation) ; Entity la retire déjà, mais la liste blanche garantit
     * qu'aucun autre attribut ajouté par erreur n'arrive dans l'INSERT.
     * Pertinence : même règle que UserModel, une ligne suffit.
     */
    protected function columns(): array
    {
        return self::COLUMNS;
    }
}
