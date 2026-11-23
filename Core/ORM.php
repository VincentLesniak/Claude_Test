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
 * Mise à jour (revue de la PR) : chaque méthode a maintenant un paragraphe
 * « Pourquoi ? » (le problème qu'elle résout) et « Pertinence » (ce qui se
 * passerait sans elle, et qui l'utilise). Plan du fichier :
 *   - 5 méthodes PUBLIQUES, celles du sujet, que les models appellent :
 *     create, read, update, delete (le "CRUD") et find (la recherche) ;
 *   - 5 méthodes PRIVÉES, des outils internes qu'on ne peut pas appeler
 *     depuis l'extérieur : buildWhere et buildLimit FABRIQUENT des morceaux
 *     de SQL, checkOrderBy, checkFields et checkName VÉRIFIENT ce qu'on va
 *     coller dans le SQL. Les ranger à part évite de répéter le même code
 *     dans chaque méthode publique, et un seul endroit à corriger si besoin.
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
 *
 * Mise à jour (étape « Avancé - Les relations de model ») : read() et find()
 * acceptent un 3e paramètre facultatif, $relations. Pour chaque relation
 * donnée, la ligne renvoyée reçoit une entrée de plus qui contient les
 * enregistrements LIÉS, lus avec une jointure (INNER JOIN) :
 *
 *   $orm->read('articles', 1, [['type' => 'has many', 'table' => 'comments']]);
 *   // ['id' => 1, 'titre' => '...', 'comments' => [[...], [...]]]
 *
 * L'ORM ne connaît pas les models : il reçoit les relations déjà
 * "traduites" en tableaux (type, table, pivot). C'est Core\Entity qui lit
 * les phrases du model ('has many comments'...) et les traduit. Le plan
 * du fichier gagne :
 *   - une constante RELATION_TYPES (les trois types possibles) ;
 *   - une méthode publique et statique singular() (articles -> article),
 *     qui sert à fabriquer les noms de clés étrangères (article_id) ;
 *   - deux méthodes privées : addRelations() (ajoute les entrées à une
 *     ligne) et readRelation() (écrit et exécute la jointure d'UNE
 *     relation) ;
 *   - une méthode privée de vérification : checkRelation().
 */
class ORM
{
    /**
     * Les paramètres par défaut de find(), repris du sujet :
     *   WHERE '1'         : une condition toujours vraie = toutes les lignes ;
     *   ORDER BY 'id ASC' : de la plus ancienne à la plus récente ;
     *   LIMIT ''          : pas de limite.
     *
     * Pourquoi une constante ? Elle sert deux fois : comme valeur par défaut
     * du paramètre de find(), et pour compléter les clés que l'appelant n'a
     * pas données. Elle donne aussi la liste des clés autorisées.
     */
    public const FIND_DEFAULTS = [
        'WHERE' => '1',
        'ORDER BY' => 'id ASC',
        'LIMIT' => '',
    ];

    /**
     * Les trois types de relations que l'ORM sait lire (sujet, « Avancé -
     * Les relations de model »). Avec l'exemple du blog :
     *
     *   'has many'     (1 : N) un article a plusieurs commentaires.
     *                  La table comments contient une colonne article_id.
     *   'has one'      (N : 1) un commentaire appartient à UN article.
     *                  C'est la même colonne comments.article_id, lue dans
     *                  l'autre sens.
     *   'many to many' (N : N) un article a plusieurs tags, un tag a
     *                  plusieurs articles. Les liens sont rangés dans une
     *                  3e table, la "table pivot" articles_tags, qui
     *                  contient article_id et tag_id.
     *
     * Pourquoi une constante ? checkRelation() s'en sert pour refuser un
     * type mal orthographié ('has meny') avec un message clair.
     * Pertinence : elle documente aussi, en un seul endroit, tout ce que
     * l'ORM sait faire en matière de relations.
     */
    public const RELATION_TYPES = ['has many', 'has one', 'many to many'];

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
     *
     * Pourquoi ? L'ORM a besoin d'une connexion pour exécuter ses requêtes.
     * Pertinence : pouvoir la DONNER (au lieu de la créer ici) permet aux
     * tests d'utiliser SQLite en mémoire, et à UserModel de partager sa
     * propre connexion avec son ORM (une seule connexion par page).
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
     * Pourquoi ? C'est le C de CRUD : sans elle, impossible d'ajouter une
     * ligne sans écrire un INSERT à la main dans chaque model.
     * Pertinence : elle renvoie l'id créé, car c'est la base qui le choisit
     * (AUTO_INCREMENT) et l'appelant en a presque toujours besoin juste
     * après (ex. connecter l'utilisateur qu'on vient d'inscrire, ou
     * rediriger vers /article/12). UserModel::create() et save() l'utilisent.
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
     * Pourquoi ? C'est le R de CRUD : le cas le plus fréquent est d'afficher
     * UNE ligne précise (la page d'un article, le profil d'un utilisateur),
     * et on la retrouve par son id, la clé primaire.
     * Pertinence : find() saurait aussi le faire, mais renverrait une LISTE
     * d'une ligne ; read() renvoie directement la ligne (ou null), ce qui
     * rend le code des models plus court et plus lisible.
     *
     * Mise à jour (étape « Les relations de model ») : le 3e paramètre,
     * facultatif, donne les relations à charger (voir addRelations()). Le
     * sujet demande que read() renvoie aussi « tous les enregistrements
     * liés » : avec la relation 'has many comments', la ligne de l'article
     * contient une entrée 'comments'. Sans 3e paramètre, rien ne change.
     *
     * @param array $relations les relations à charger (voir RELATION_TYPES)
     * @return array|null la ligne (colonne => valeur), ou null si l'id n'existe pas
     */
    public function read(string $table, int $id, array $relations = []): ?array
    {
        $this->checkName($table);

        $statement = $this->db->prepare("SELECT * FROM $table WHERE id = :id");
        $statement->execute(['id' => $id]);

        // fetch() renvoie false quand il n'y a aucune ligne : on le
        // transforme en null, plus clair pour celui qui appelle read().
        $row = $statement->fetch();
        if ($row === false) {
            return null;
        }
        return $this->addRelations($table, $row, $relations);
    }

    /**
     * Met à jour les champs donnés de la ligne $id.
     *
     * Exemple : update('users', 3, ['email' => 'new@b.c']) exécute
     *   UPDATE users SET email = :email WHERE id = :id
     *
     * Pourquoi ? C'est le U de CRUD : modifier une ligne existante (changer
     * un email, corriger un titre) sans la supprimer et la recréer, ce qui
     * changerait son id et casserait les liens vers elle.
     * Pertinence : on ne modifie QUE les colonnes données, les autres
     * gardent leur valeur. Le booléen dit si la ligne existait : le
     * controller peut alors afficher « article introuvable ».
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
     * Pourquoi ? C'est le D de CRUD : supprimer une ligne d'après son id
     * (un compte fermé, un article retiré).
     * Pertinence : le "WHERE id = :id" est écrit ici une fois pour toutes.
     * Un DELETE sans WHERE effacerait TOUTE la table : en passant par
     * delete(), cette erreur est impossible. Le booléen dit si la ligne
     * existait vraiment.
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
     * Pourquoi ? read() ne trouve une ligne que par son id. Or on cherche
     * souvent autrement : un utilisateur par son email, les articles d'un
     * auteur, les 10 derniers articles... find() couvre tous ces cas.
     * Pertinence : c'est la méthode la plus souple de l'ORM, et donc celle
     * qui demande le plus de vérifications (WHERE, ORDER BY, LIMIT), confiées
     * aux méthodes privées plus bas. UserModel::readAll() et readByEmail()
     * l'utilisent.
     *
     * Mise à jour (étape « Les relations de model ») : comme read(), un 3e
     * paramètre facultatif ajoute les enregistrements liés à CHAQUE ligne
     * trouvée.
     *
     * @param array $relations les relations à charger (voir RELATION_TYPES)
     * @return array la liste des lignes (un tableau associatif par ligne)
     * @throws InvalidArgumentException pour une clé inconnue ou une valeur invalide
     */
    public function find(string $table, array $params = self::FIND_DEFAULTS, array $relations = []): array
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
        $rows = $statement->fetchAll();

        // array_map() applique la fonction à chaque ligne et renvoie le
        // tableau des résultats. "fn (array $row) => ..." est une fonction
        // fléchée (PHP 7.4+) : une fonction anonyme d'UNE expression, qui
        // voit automatiquement les variables d'autour ($table, $relations),
        // sans avoir à écrire "use ($table, $relations)".
        return array_map(fn (array $row): array => $this->addRelations($table, $row, $relations), $rows);
    }

    /**
     * Le singulier d'un nom de table : on retire le "s" final.
     *
     *   articles -> article     comments -> comment     tags -> tag
     *
     * C'est la convention inverse de Entity::getTable() (UserModel ->
     * users) : on retire le "s" que getTable() a ajouté. Elle est simple,
     * donc elle a ses limites : "people" ou "categories" ne donnent pas
     * "person" ou "category". Un tel model peut toujours choisir sa table
     * avec protected static $table, mais ses clés étrangères suivront
     * cette règle (people_id, categorie_id).
     *
     * Pourquoi ? Les clés étrangères portent le nom de la table au
     * SINGULIER : un commentaire appartient à UN article, sa colonne
     * s'appelle donc article_id (et pas articles_id).
     * Pertinence : publique et statique car Core\Entity s'en sert aussi
     * pour retrouver un model d'après sa table (tags -> TagModel). Une
     * seule règle, écrite une seule fois, pour l'ORM et les entités.
     */
    public static function singular(string $table): string
    {
        // '/s$/' : un "s" ($ = en fin de chaîne). On le remplace par rien.
        return preg_replace('/s$/', '', $table);
    }

    /**
     * Ajoute à une ligne une entrée par relation, qui contient les
     * enregistrements liés.
     *
     * Chaque relation est un tableau :
     *   'type'  : 'has many', 'has one' ou 'many to many' ;
     *   'table' : la table liée (ex. 'comments') ;
     *   'name'  : facultatif, le nom de l'entrée ajoutée à la ligne
     *             (par défaut, celui de la table) ;
     *   'pivot' : pour 'many to many' seulement, la table pivot.
     *
     * Exemple : la ligne ['id' => 1, 'titre' => 'Un titre'] de la table
     * articles, avec [['type' => 'has many', 'table' => 'comments']],
     * devient ['id' => 1, 'titre' => 'Un titre', 'comments' => [...]].
     *
     * Pourquoi ? read() et find() font exactement le même travail sur leurs
     * lignes : il est écrit ici une seule fois.
     * Pertinence : c'est ce que demande le sujet (« le tableau associatif
     * retourné doit contenir une entrée comments »). Limite connue : avec
     * find(), on fait une requête PAR ligne et PAR relation (le "problème
     * N+1"). C'est sans importance pour un petit site, et bien plus simple
     * à lire qu'une seule énorme requête.
     *
     * @param array $row       la ligne lue (elle doit contenir son id)
     * @param array $relations les relations à charger
     * @return array la ligne, complétée
     */
    private function addRelations(string $table, array $row, array $relations): array
    {
        foreach ($relations as $relation) {
            $this->checkRelation($relation);
            // "??" : la clé 'name' si elle existe, sinon le nom de la table.
            $name = $relation['name'] ?? $relation['table'];
            $row[$name] = $this->readRelation($table, (int) $row['id'], $relation);
        }
        return $row;
    }

    /**
     * Lit les enregistrements liés à la ligne $id de $table, pour UNE
     * relation, avec une jointure (INNER JOIN) comme le demande le sujet.
     *
     * Une jointure "colle" deux tables côte à côte en suivant une condition
     * (ON ...). Seules les paires qui respectent la condition sont gardées.
     * On sélectionne ensuite uniquement les colonnes de la table liée
     * ("comments.*" : toutes les colonnes de comments).
     *
     * Avec articles, comments, tags et la table pivot articles_tags :
     *
     *   has many (articles -> comments) : les commentaires de l'article 1
     *     SELECT comments.* FROM comments
     *     INNER JOIN articles ON comments.article_id = articles.id
     *     WHERE articles.id = :id
     *
     *   has one (comments -> articles) : l'article du commentaire 5
     *     SELECT articles.* FROM articles
     *     INNER JOIN comments ON comments.article_id = articles.id
     *     WHERE comments.id = :id
     *
     *   many to many (articles -> tags) : les tags de l'article 1
     *     SELECT tags.* FROM tags
     *     INNER JOIN articles_tags ON articles_tags.tag_id = tags.id
     *     WHERE articles_tags.article_id = :id
     *
     * (Pour 'has many', un simple "SELECT * FROM comments WHERE article_id
     * = :id" marcherait aussi. La jointure est gardée pour suivre le sujet,
     * et parce que les trois requêtes se ressemblent ainsi beaucoup.)
     *
     * Pourquoi ? C'est ici que le SQL des relations est écrit, et nulle
     * part ailleurs : les models ne font que DÉCRIRE leurs relations.
     * Pertinence : l'id passe par un marqueur préparé (:id), et les noms de
     * tables ont été vérifiés par checkRelation() : ces requêtes sont aussi
     * protégées que les autres. 'has one' renvoie UNE ligne (ou null),
     * les deux autres une LISTE (éventuellement vide).
     *
     * @return array|null la liste des lignes liées, ou une ligne (ou null) pour 'has one'
     */
    private function readRelation(string $table, int $id, array $relation): ?array
    {
        $related = $relation['table'];

        // "match" (PHP 8) ressemble à un switch, mais c'est une EXPRESSION :
        // il renvoie une valeur, ici la requête SQL du bon type. Il compare
        // avec === (type et valeur), et il n'y a pas de "break" à écrire.
        // Les types ont déjà été vérifiés par checkRelation() : un 4e cas
        // n'est pas possible.
        $sql = match ($relation['type']) {
            'has many' => sprintf(
                'SELECT %2$s.* FROM %2$s INNER JOIN %1$s ON %2$s.%3$s = %1$s.id WHERE %1$s.id = :id',
                $table,
                $related,
                self::singular($table) . '_id'
            ),
            'has one' => sprintf(
                'SELECT %2$s.* FROM %2$s INNER JOIN %1$s ON %1$s.%3$s = %2$s.id WHERE %1$s.id = :id',
                $table,
                $related,
                self::singular($related) . '_id'
            ),
            'many to many' => sprintf(
                'SELECT %2$s.* FROM %2$s INNER JOIN %3$s ON %3$s.%5$s = %2$s.id WHERE %3$s.%4$s = :id',
                $table,
                $related,
                $relation['pivot'],
                self::singular($table) . '_id',
                self::singular($related) . '_id'
            ),
        };
        // Dans sprintf(), "%2$s" veut dire « le 2e argument, comme texte ».
        // Numéroter les arguments permet d'utiliser plusieurs fois le même
        // (la table liée apparaît 3 fois) sans le répéter dans l'appel.

        if ($relation['type'] !== 'has one') {
            // Un ordre stable : du plus ancien au plus récent, comme find().
            $sql .= " ORDER BY $related.id ASC";
        }

        $statement = $this->db->prepare($sql);
        $statement->execute(['id' => $id]);

        if ($relation['type'] === 'has one') {
            $row = $statement->fetch();
            return $row === false ? null : $row;
        }
        return $statement->fetchAll();
    }

    /**
     * Transforme le paramètre WHERE de find() en morceau de SQL + valeurs.
     *
     *   '1'                         -> ['1', []]
     *   ['email' => 'a@b.c']        -> ['email = :email', ['email' => 'a@b.c']]
     *   ['email' => 'a', 'id' => 2] -> ['email = :email AND id = :id', [...]]
     *
     * Pourquoi ? find() accepte deux formes de WHERE (chaîne ou tableau) ;
     * cette méthode les ramène à UNE seule forme, que find() n'a plus qu'à
     * coller dans la requête.
     * Pertinence : c'est elle qui transforme ['email' => $email] en
     * "email = :email" + une valeur PRÉPARÉE. C'est ce qui permet de
     * chercher avec une saisie de visiteur sans risque d'injection SQL.
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
     * Pourquoi ? ORDER BY contient des noms de colonnes, et PDO ne sait pas
     * préparer des noms (seulement des valeurs). Il est donc collé tel quel
     * dans le SQL : il faut le vérifier avant.
     * Pertinence : sans elle, un tri venu de l'URL (ex. ?tri=...) pourrait
     * contenir "id; DROP TABLE users" et serait exécuté.
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
     * Pourquoi ? Comme ORDER BY, LIMIT est collé dans le SQL. On aurait pu
     * le préparer avec bindValue(..., PDO::PARAM_INT), mais execute($values)
     * envoie tout comme du texte, et LIMIT accepte aussi la forme
     * "début, nombre" : le vérifier ici reste plus simple, et aussi sûr.
     * Pertinence : on accepte seulement des entiers positifs, donc rien
     * d'autre ne peut se glisser dans la requête. Utile pour la pagination
     * (page 3 de 10 articles = LIMIT 20, 10).
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
     * Pourquoi ? create(), update() et le WHERE en tableau de find() font
     * la même vérification sur leurs colonnes : on l'écrit une seule fois ici.
     * Pertinence : un tableau vide donnerait un SQL invalide ("INSERT INTO
     * users () VALUES ()") et une erreur PDO difficile à comprendre ; on
     * préfère un message clair, en français, avant d'aller en base.
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
     * Vérifie une relation avant de l'utiliser dans une requête :
     *   - le type fait partie de RELATION_TYPES ;
     *   - la table liée (et la table pivot, obligatoire pour 'many to
     *     many') passent checkName() ;
     *   - le nom de l'entrée, s'il est donné, aussi.
     *
     * Pourquoi ? Les noms de tables d'une relation sont collés dans le SQL
     * de readRelation(), comme ceux de create() ou find().
     * Pertinence : c'est la même règle de sécurité que pour le reste de
     * l'ORM (aucun nom non vérifié dans une requête), et une faute de
     * frappe donne un message clair au lieu d'une erreur SQL.
     *
     * @throws InvalidArgumentException si la relation est incomplète ou invalide
     */
    private function checkRelation(array $relation): void
    {
        if (!in_array($relation['type'] ?? null, self::RELATION_TYPES, true)) {
            $type = var_export($relation['type'] ?? null, true);
            throw new InvalidArgumentException("Type de relation inconnu : $type");
        }
        $this->checkName((string) ($relation['table'] ?? ''));
        if (isset($relation['name'])) {
            $this->checkName((string) $relation['name']);
        }
        if ($relation['type'] === 'many to many') {
            $this->checkName((string) ($relation['pivot'] ?? ''));
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
     * Pourquoi ? Les noms de table et de colonne sont collés dans le SQL
     * (PDO ne peut pas les préparer). C'est la seule porte d'entrée
     * restante pour une injection SQL : checkName() la ferme.
     * Pertinence : TOUTES les méthodes publiques l'appellent sur la table,
     * et checkFields() sur chaque colonne. C'est le dernier verrou de
     * sécurité de l'ORM, même si un model oublie sa propre liste blanche.
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
