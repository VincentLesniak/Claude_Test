<?php

// Namespace "Model" : la classe est rangée dans src/Model/ (exigé par PSR-12).
namespace Model;

use Core\Database;
use InvalidArgumentException;
use PDO;

/**
 * Model de l'entité "user" : c'est lui qui parlera à la table users en base.
 * Il est vide pour l'instant ; les attributs email/password et la méthode
 * save() arrivent à l'étape "Mise en pratique".
 *
 * Mise à jour (étapes "Mise en pratique" et "Le Model") : le model est codé.
 * C'est le SEUL endroit du projet qui écrit du SQL sur la table users : le
 * controller demande "enregistre cet utilisateur", "donne-moi l'utilisateur
 * n°3", sans savoir comment c'est fait.
 *
 * Il contient :
 *   - les attributs privés email et password + save() ("Mise en pratique") ;
 *   - le CRUD demandé par la partie "Le Model" :
 *       create()  C comme Create : ajoute une ligne, renvoie son id
 *       read()    R comme Read   : lit une ligne d'après son id
 *       update()  U comme Update : modifie une ligne d'après son id
 *       delete()  D comme Delete : supprime une ligne d'après son id
 *       readAll() lit toutes les lignes (read_all dans le sujet)
 *   - checkCredentials() pour la connexion ("Pour aller plus loin").
 *
 * SÉCURITÉ, deux règles appliquées partout ici :
 *   1. Requêtes PRÉPARÉES : la requête SQL et les valeurs sont envoyées
 *      séparément ("WHERE id = :id", puis ['id' => 3]). Une valeur ne peut
 *      donc jamais être lue comme du SQL : pas d'injection SQL possible.
 *   2. Mots de passe HACHÉS avec password_hash() : la base ne contient jamais
 *      le mot de passe lui-même, seulement une empreinte impossible à
 *      inverser. Même si la base fuite, les mots de passe restent secrets.
 */
class UserModel
{
    /**
     * Le nom de la table gérée par ce model.
     */
    private const TABLE = 'users';

    /**
     * Les colonnes que create() et update() ont le droit de remplir.
     *
     * Les NOMS de colonnes ne peuvent pas passer par une requête préparée
     * (seules les valeurs le peuvent) : on les compare donc à cette "liste
     * blanche". Un champ inconnu, ou un nom piégé comme "email = '' --",
     * est refusé au lieu d'être collé dans le SQL.
     */
    private const COLUMNS = ['email', 'password'];

    /**
     * La connexion à la base de données.
     *
     * @var PDO
     */
    private $db;

    /**
     * L'email de l'utilisateur (sujet : attribut privé).
     *
     * @var string
     */
    private $email = '';

    /**
     * Le mot de passe EN CLAIR, tel que tapé dans le formulaire. Il n'est
     * jamais enregistré tel quel : save() le hache avant de l'écrire.
     *
     * @var string
     */
    private $password = '';

    /**
     * Le sujet demande d'initialiser la connexion dans le constructeur.
     *
     * Par défaut, on prend la connexion partagée de Core\Database (lue dans
     * config.json). On peut aussi en donner une autre : les tests passent
     * une base SQLite en mémoire.
     */
    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getConnection();
    }

    /**
     * Les "setters" modifient les attributs privés depuis l'extérieur. Comme
     * ils renvoient $this, on peut les enchaîner :
     *   $user->setEmail('a@b.c')->setPassword('secret')->save();
     */
    public function setEmail(string $email): self
    {
        $this->email = $email;
        return $this;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;
        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    /**
     * Enregistre en base un nouvel utilisateur avec les attributs du model
     * (sujet, partie "Mise en pratique"). Le mot de passe est haché par
     * create().
     *
     * @return int l'id du nouvel utilisateur
     */
    public function save(): int
    {
        return $this->create(['email' => $this->email, 'password' => $this->password]);
    }

    /**
     * Connexion : renvoie l'utilisateur dont l'email et le mot de passe
     * correspondent aux attributs du model, ou null.
     *
     * password_verify() hache le mot de passe tapé de la même façon que lors
     * de l'inscription et compare le résultat à l'empreinte enregistrée.
     *
     * On renvoie null dans les DEUX cas d'échec (email inconnu ou mauvais mot
     * de passe) : le controller affiche alors le même message, et un pirate
     * ne peut pas deviner quels emails sont inscrits.
     *
     * @return array|null l'utilisateur, SANS son mot de passe
     */
    public function checkCredentials(): ?array
    {
        $user = $this->readByEmail($this->email);
        if ($user === null || !password_verify($this->password, $user['password'])) {
            return null;
        }

        unset($user['password']);
        return $user;
    }

    /**
     * Crée une nouvelle ligne avec les champs donnés et renvoie son id.
     *
     * Exemple : create(['email' => 'a@b.c', 'password' => 'secret']) exécute
     *   INSERT INTO users (email, password) VALUES (:email, :password)
     * avec les valeurs ['email' => 'a@b.c', 'password' => '$2y$10$...'].
     *
     * @param array $fields colonne => valeur
     * @return int l'id attribué par la base (AUTO_INCREMENT)
     */
    public function create(array $fields): int
    {
        $fields = $this->prepareFields($fields);

        $columns = array_keys($fields);
        // Pour chaque colonne, un "marqueur" nommé : email -> :email.
        $markers = array_map(function (string $column): string {
            return ':' . $column;
        }, $columns);

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            self::TABLE,
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
     * @return array|null la ligne (colonne => valeur), ou null si l'id n'existe pas
     */
    public function read(int $id): ?array
    {
        $statement = $this->db->prepare('SELECT * FROM ' . self::TABLE . ' WHERE id = :id');
        $statement->execute(['id' => $id]);

        // fetch() renvoie false quand il n'y a aucune ligne : on le
        // transforme en null, plus clair pour celui qui appelle read().
        $user = $statement->fetch();
        return $user === false ? null : $user;
    }

    /**
     * Met à jour les champs donnés de la ligne $id.
     *
     * Exemple : update(3, ['email' => 'new@b.c']) exécute
     *   UPDATE users SET email = :email WHERE id = :id
     *
     * @return bool true si une ligne a été modifiée
     */
    public function update(int $id, array $fields): bool
    {
        $fields = $this->prepareFields($fields);

        $assignments = array_map(function (string $column): string {
            return $column . ' = :' . $column;
        }, array_keys($fields));

        $sql = sprintf('UPDATE %s SET %s WHERE id = :id', self::TABLE, implode(', ', $assignments));

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
    public function delete(int $id): bool
    {
        $statement = $this->db->prepare('DELETE FROM ' . self::TABLE . ' WHERE id = :id');
        $statement->execute(['id' => $id]);
        return $statement->rowCount() > 0;
    }

    /**
     * Lit toutes les lignes de la table, de la plus ancienne à la plus récente.
     *
     * Le sujet l'appelle read_all, mais PSR-1 (inclus dans PSR-12) impose
     * des noms de méthodes en "camelCase" : readAll.
     *
     * @return array la liste des utilisateurs (un tableau par ligne)
     */
    public function readAll(): array
    {
        // query() suffit ici : la requête ne contient aucune valeur venant
        // du visiteur, il n'y a rien à préparer.
        return $this->db->query('SELECT * FROM ' . self::TABLE . ' ORDER BY id')->fetchAll();
    }

    /**
     * Lit un utilisateur d'après son email (unique dans la table).
     *
     * @return array|null la ligne, ou null si l'email n'est pas inscrit
     */
    public function readByEmail(string $email): ?array
    {
        $statement = $this->db->prepare('SELECT * FROM ' . self::TABLE . ' WHERE email = :email');
        $statement->execute(['email' => $email]);
        $user = $statement->fetch();
        return $user === false ? null : $user;
    }

    /**
     * Vérifie les champs avant create() et update() :
     *   - chaque colonne doit être dans la liste blanche self::COLUMNS ;
     *   - il faut au moins un champ (sinon la requête SQL serait invalide) ;
     *   - un mot de passe est haché avant d'aller en base.
     *
     * password_hash(..., PASSWORD_DEFAULT) choisit l'algorithme recommandé
     * par PHP (bcrypt aujourd'hui) et ajoute un "sel" aléatoire : deux
     * utilisateurs avec le même mot de passe ont des empreintes différentes.
     * L'empreinte fait 60 caractères : la colonne VARCHAR(255) est assez grande.
     *
     * @throws InvalidArgumentException pour une colonne inconnue ou aucun champ
     */
    private function prepareFields(array $fields): array
    {
        if ($fields === []) {
            throw new InvalidArgumentException('Aucun champ à enregistrer');
        }

        foreach (array_keys($fields) as $column) {
            if (!in_array($column, self::COLUMNS, true)) {
                throw new InvalidArgumentException("Colonne inconnue dans la table users : $column");
            }
        }

        if (isset($fields['password'])) {
            $fields['password'] = password_hash($fields['password'], PASSWORD_DEFAULT);
        }

        return $fields;
    }
}
