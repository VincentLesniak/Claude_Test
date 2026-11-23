<?php

// Namespace "Model" : la classe est rangée dans src/Model/ (exigé par PSR-12).
namespace Model;

use Core\Database;
use Core\ORM;
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
 *
 * Mise à jour (étape « Avancé - l'ORM ») : le model n'écrit plus de SQL
 * lui-même. Il demande à Core\ORM ("crée cette ligne dans users", "lis la
 * ligne n°3"...), et c'est l'ORM qui fabrique et exécute la requête. Le
 * model reste le seul à parler de la table users, mais en PHP. Les deux
 * règles de sécurité ci-dessus restent vraies : les requêtes préparées sont
 * maintenant faites par l'ORM, et le hachage reste ici (c'est une règle
 * propre aux utilisateurs, l'ORM n'a pas à la connaître).
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
     * Ajout (étape ORM) : le mini-ORM qui écrit les requêtes SQL pour ce
     * model, branché sur la même connexion que $db.
     *
     * @var ORM
     */
    private $orm;

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
        $this->orm = new ORM($this->db);
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
     * Mise à jour (étape ORM) : cette requête est maintenant écrite par
     * ORM::create(). Les marqueurs (:email), prepare()/execute() et
     * lastInsertId() y ont déménagé, avec leurs explications.
     *
     * @param array $fields colonne => valeur
     * @return int l'id attribué par la base (AUTO_INCREMENT)
     */
    public function create(array $fields): int
    {
        return $this->orm->create(self::TABLE, $this->prepareFields($fields));
    }

    /**
     * Lit une ligne d'après son id.
     *
     * @return array|null la ligne (colonne => valeur), ou null si l'id n'existe pas
     */
    public function read(int $id): ?array
    {
        // Mise à jour (étape ORM) : ORM::read() exécute le SELECT et
        // transforme déjà le false de fetch() (aucune ligne) en null.
        return $this->orm->read(self::TABLE, $id);
    }

    /**
     * Met à jour les champs donnés de la ligne $id.
     *
     * Exemple : update(3, ['email' => 'new@b.c']) exécute
     *   UPDATE users SET email = :email WHERE id = :id
     *
     * Mise à jour (étape ORM) : requête écrite par ORM::update(), qui
     * renvoie true si rowCount() (le nombre de lignes touchées) est > 0.
     *
     * @return bool true si une ligne a été modifiée
     */
    public function update(int $id, array $fields): bool
    {
        return $this->orm->update(self::TABLE, $id, $this->prepareFields($fields));
    }

    /**
     * Supprime la ligne $id.
     *
     * @return bool true si une ligne a été supprimée
     */
    public function delete(int $id): bool
    {
        // Mise à jour (étape ORM) : le DELETE est écrit par ORM::delete().
        return $this->orm->delete(self::TABLE, $id);
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
        // Mise à jour (étape ORM) : c'est ORM::find() qui fait la requête.
        // Sans paramètre, il prend toutes les lignes triées par "id ASC".
        return $this->orm->find(self::TABLE);
    }

    /**
     * Lit un utilisateur d'après son email (unique dans la table).
     *
     * @return array|null la ligne, ou null si l'email n'est pas inscrit
     */
    public function readByEmail(string $email): ?array
    {
        // Mise à jour (étape ORM) : find() avec un WHERE sous forme de
        // tableau, donc une valeur préparée (l'email vient du formulaire).
        // LIMIT 1 : l'email est unique, une ligne suffit. find() renvoie une
        // LISTE de lignes : on prend la première, ou null si la liste est vide.
        $users = $this->orm->find(self::TABLE, ['WHERE' => ['email' => $email], 'LIMIT' => 1]);
        return $users[0] ?? null;
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
