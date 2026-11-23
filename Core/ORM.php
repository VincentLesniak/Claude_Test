<?php

namespace Core;

use InvalidArgumentException;
use PDO;

/**
 * ORM : écrit le SQL à la place des models (sujet, partie « Avancé - l'ORM »).
 *
 * ORM veut dire "Object-Relational Mapping" : faire le lien entre les objets
 * PHP et les tables de la base. Ici, c'est un "mini-ORM" sous forme de helper :
 * on lui donne un nom de table et un tableau PHP, il fabrique la requête SQL,
 * l'exécute et renvoie le résultat. Les models n'écrivent plus une seule
 * ligne de SQL eux-mêmes.
 *
 * Utilisation (exemple du sujet) :
 *   $orm = new ORM();
 *   $id = $orm->create('articles', ['titre' => 'Un titre', 'author' => 'Rodrigue']);
 *   $article = $orm->read('articles', $id);
 *   $orm->update('articles', $id, ['titre' => 'Nouveau titre']);
 *   $orm->find('articles', ['WHERE' => ['author' => 'Rodrigue'], 'LIMIT' => 10]);
 *   $orm->delete('articles', $id);
 *
 * Chaque méthode suit le prototype donné par le sujet. Seuls les noms de
 * paramètres et les types (int, array, bool...) ont été précisés.
 *
 * SÉCURITÉ : un ORM est l'endroit idéal pour protéger TOUTES les requêtes
 * d'un coup, puisqu'elles passent toutes par lui.
 *   1. Les VALEURS passent toujours par des requêtes préparées (marqueurs
 *      ":colonne"), comme dans UserModel : elles ne sont jamais lues comme
 *      du SQL.
 *   2. Les NOMS (table, colonnes) ne peuvent pas être préparés : PDO ne sait
 *      préparer que des valeurs. On vérifie donc qu'ils ne contiennent que
 *      des lettres, des chiffres et des "_" (voir checkName()). Un nom piégé
 *      comme "users; DROP TABLE users" est refusé avant d'arriver en base.
 *   3. Dans find(), ORDER BY et LIMIT sont vérifiés de la même façon.
 */
class ORM
{
    /**
     * Les paramètres par défaut de find(), repris du sujet :
     *   WHERE '1'         : une condition toujours vraie = toutes les lignes ;
     *   ORDER BY 'id ASC' : de la plus ancienne à la plus récente ;
     *   LIMIT ''          : pas de limite.
     */
    public const FIND_DEFAULTS = [
        'WHERE' => '1',
        'ORDER BY' => 'id ASC',
        'LIMIT' => '',
    ];

    /**
     * La connexion à la base de données.
     *
     * @var PDO
     */
    private $db;

    /**
     * Comme pour les models : par défaut, la connexion partagée de
     * Core\Database (lue dans config.json) ; les tests peuvent donner leur
     * propre base SQLite en mémoire.
     */
    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getConnection();
    }

    /**
     * Crée une nouvelle ligne avec les champs donnés et renvoie son id.
     *
     * Exemple : create('users', ['email' => 'a@b.c', 'password' => 'x']) exécute
     *   INSERT INTO users (email, password) VALUES (:email, :password)
     * avec les valeurs ['email' => 'a@b.c', 'password' => 'x'].
     *
     * @param string $table  le nom de la table
     * @param array  $fields colonne => valeur
     * @return int l'id attribué par la base (AUTO_INCREMENT)
     */
    public function create(string $table, array $fields): int
    {
        $this->checkName($table);
        $this->checkFields($fields);

        $columns = array_keys($fields);
        // Pour chaque colonne, un "marqueur" nommé : email -> :email.
        $markers = array_map(function (string $column): string {
            return ':' . $column;
        }, $columns);

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $table,
            implode(', ', $columns),
            implode(', ', $markers)
        );

        // prepare() envoie la requête avec ses marqueurs, execute() envoie
        // les valeurs. lastInsertId() donne l'id que la base vient de créer.
        $this->db->prepare($sql)->execute($fields);
        return (int) $this->db->lastInsertId();
    }

    /**
     * Lit une ligne d'après son id.
     *
     * Le sujet dit « retourne un tableau associatif de l'enregistrement ».
     * Quand l'id n'existe pas, il n'y a pas d'enregistrement : on renvoie
     * null, plus facile à tester qu'un tableau vide.
     *
     * @return array|null la ligne (colonne => valeur), ou null si l'id n'existe pas
     */
    public function read(string $table, int $id): ?array
    {
        $this->checkName($table);

        $statement = $this->db->prepare("SELECT * FROM $table WHERE id = :id");
        $statement->execute(['id' => $id]);

        // fetch() renvoie false quand il n'y a aucune ligne : on le
        // transforme en null, plus clair pour celui qui appelle read().
        $row = $statement->fetch();
        return $row === false ? null : $row;
    }

    /**
     * Met à jour les champs donnés de la ligne $id.
     *
     * Exemple : update('users', 3, ['email' => 'new@b.c']) exécute
     *   UPDATE users SET email = :email WHERE id = :id
     *
     * @return bool true si une ligne a été modifiée
     */
    public function update(string $table, int $id, array $fields): bool
    {
        $this->checkName($table);
        $this->checkFields($fields);

        // On ne laisse pas modifier l'id lui-même : il sert à retrouver la
        // ligne, et son marqueur :id est déjà utilisé par le WHERE.
        if (array_key_exists('id', $fields)) {
            throw new InvalidArgumentException("L'id d'une ligne ne peut pas être modifié");
        }

        $assignments = array_map(function (string $column): string {
            return $column . ' = :' . $column;
        }, array_keys($fields));

        $sql = sprintf('UPDATE %s SET %s WHERE id = :id', $table, implode(', ', $assignments));

        $statement = $this->db->prepare($sql);
        $statement->execute($fields + ['id' => $id]);

        // rowCount() : le nombre de lignes touchées par la requête (0 si l'id
        // n'existe pas).
        return $statement->rowCount() > 0;
    }

    /**
     * Supprime la ligne $id.
     *
     * @return bool true si une ligne a été supprimée
     */
    public function delete(string $table, int $id): bool
    {
        $this->checkName($table);

        $statement = $this->db->prepare("DELETE FROM $table WHERE id = :id");
        $statement->execute(['id' => $id]);
        return $statement->rowCount() > 0;
    }

    /**
     * Cherche des lignes selon des critères et renvoie la liste.
     *
     * $params accepte trois clés, comme dans le sujet. On ne donne que
     * celles qu'on veut changer : les autres gardent leur valeur par défaut
     * (self::FIND_DEFAULTS).
     *
     *   'WHERE'    : les conditions. Deux formes possibles :
     *                - un TABLEAU colonne => valeur (RECOMMANDÉ) :
     *                    ['email' => 'a@b.c', 'role' => 'admin']
     *                  devient  WHERE email = :email AND role = :role
     *                  avec des valeurs préparées : sans danger, même si
     *                  'a@b.c' vient d'un formulaire ;
     *                - une CHAÎNE SQL, comme dans le sujet ('1', 'id > 3'...).
     *                  Elle est collée TELLE QUELLE dans la requête : n'y mets
     *                  jamais une valeur tapée par un visiteur (risque
     *                  d'injection SQL). Utilise le tableau pour ça.
     *   'ORDER BY' : le tri, ex. 'id ASC' ou 'author ASC, id DESC'.
     *   'LIMIT'    : le nombre maximum de lignes, ex. 10 ou '10' ; ou
     *                'début, nombre' pour paginer, ex. '20, 10' (les lignes
     *                21 à 30). '' = pas de limite.
     *
     * Exemple : find('articles', ['WHERE' => ['author' => 'Rodrigue'], 'LIMIT' => 5])
     *   SELECT * FROM articles WHERE author = :author ORDER BY id ASC LIMIT 5
     *
     * @return array la liste des lignes (un tableau associatif par ligne)
     * @throws InvalidArgumentException pour une clé inconnue ou une valeur invalide
     */
    public function find(string $table, array $params = self::FIND_DEFAULTS): array
    {
        $this->checkName($table);

        // Une clé mal orthographiée ('ORDERBY', 'where'...) serait sinon
        // ignorée en silence : on préfère prévenir tout de suite.
        foreach (array_keys($params) as $key) {
            if (!array_key_exists($key, self::FIND_DEFAULTS)) {
                throw new InvalidArgumentException("Paramètre inconnu pour find() : $key");
            }
        }
        // array_merge : les valeurs de $params remplacent celles par défaut
        // pour les mêmes clés, les autres clés restent.
        $params = array_merge(self::FIND_DEFAULTS, $params);

        [$where, $values] = $this->buildWhere($params['WHERE']);
        $sql = "SELECT * FROM $table WHERE $where";

        $orderBy = trim((string) $params['ORDER BY']);
        if ($orderBy !== '') {
            $this->checkOrderBy($orderBy);
            $sql .= " ORDER BY $orderBy";
        }

        $limit = trim((string) $params['LIMIT']);
        if ($limit !== '') {
            $sql .= ' LIMIT ' . $this->buildLimit($limit);
        }

        $statement = $this->db->prepare($sql);
        $statement->execute($values);
        return $statement->fetchAll();
    }

    /**
     * Transforme le paramètre WHERE de find() en morceau de SQL + valeurs.
     *
     *   '1'                         -> ['1', []]
     *   ['email' => 'a@b.c']        -> ['email = :email', ['email' => 'a@b.c']]
     *   ['email' => 'a', 'id' => 2] -> ['email = :email AND id = :id', [...]]
     *
     * @param string|array $where
     * @return array [la condition SQL, les valeurs à préparer]
     */
    private function buildWhere($where): array
    {
        if (is_string($where)) {
            // Une chaîne vide donnerait "WHERE " : requête invalide.
            return [trim($where) === '' ? '1' : $where, []];
        }

        if (!is_array($where)) {
            throw new InvalidArgumentException('WHERE doit être une chaîne ou un tableau colonne => valeur');
        }

        // Un tableau vide = aucune condition = toutes les lignes.
        if ($where === []) {
            return ['1', []];
        }

        $this->checkFields($where);
        $conditions = array_map(function (string $column): string {
            return $column . ' = :' . $column;
        }, array_keys($where));

        return [implode(' AND ', $conditions), $where];
    }

    /**
     * Vérifie ORDER BY : une liste de colonnes séparées par des virgules,
     * chacune suivie ou non de ASC ou DESC (majuscules ou minuscules).
     *
     * @throws InvalidArgumentException si ce n'est pas le cas
     */
    private function checkOrderBy(string $orderBy): void
    {
        foreach (explode(',', $orderBy) as $part) {
            // \s+ : un ou plusieurs espaces ; (?: ...)? : partie facultative ;
            // le "i" final rend le motif insensible à la casse (asc = ASC).
            if (!preg_match('/^\s*[A-Za-z_][A-Za-z0-9_]*(?:\s+(?:ASC|DESC))?\s*$/i', $part)) {
                throw new InvalidArgumentException("ORDER BY invalide : $orderBy");
            }
        }
    }

    /**
     * Vérifie LIMIT et le remet en forme : "10" ou "20, 10" (début, nombre).
     *
     * @throws InvalidArgumentException si LIMIT n'est pas fait de nombres entiers
     */
    private function buildLimit(string $limit): string
    {
        // ctype_digit() : true si la chaîne ne contient QUE des chiffres
        // (donc pas de signe moins, pas de virgule, pas de SQL caché).
        $numbers = array_map('trim', explode(',', $limit));
        if (count($numbers) > 2) {
            throw new InvalidArgumentException("LIMIT invalide : $limit");
        }
        foreach ($numbers as $number) {
            if (!ctype_digit($number)) {
                throw new InvalidArgumentException("LIMIT invalide : $limit");
            }
        }
        return implode(', ', $numbers);
    }

    /**
     * Vérifie les champs avant create(), update() et le WHERE de find() :
     *   - il faut au moins un champ (sinon la requête SQL serait invalide) ;
     *   - chaque nom de colonne doit passer checkName().
     *
     * @throws InvalidArgumentException si aucun champ ou si un nom est invalide
     */
    private function checkFields(array $fields): void
    {
        if ($fields === []) {
            throw new InvalidArgumentException('Aucun champ à enregistrer');
        }
        foreach (array_keys($fields) as $column) {
            $this->checkName((string) $column);
        }
    }

    /**
     * Vérifie qu'un nom de table ou de colonne est "sûr" : une lettre ou un
     * "_", puis des lettres, des chiffres ou des "_" (ex. users, created_at).
     *
     * C'est la même idée que la liste blanche de UserModel, mais en plus
     * général : l'ORM ne connaît pas les colonnes de chaque table, il refuse
     * donc tout ce qui ne RESSEMBLE pas à un nom. Le model, lui, peut garder
     * sa liste blanche pour être encore plus strict.
     *
     * @throws InvalidArgumentException si le nom contient autre chose
     */
    private function checkName(string $name): void
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name)) {
            throw new InvalidArgumentException("Nom de table ou de colonne invalide : $name");
        }
    }
}
