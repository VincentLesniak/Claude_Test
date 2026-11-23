<?php

// Namespace "Model" : la classe est rangée dans src/Model/ (exigé par PSR-12).
namespace Model;

use Core\Entity;
use InvalidArgumentException;

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
 *
 * Mise à jour (étape « Les entités ») : UserModel hérite de Core\Entity
 * ("extends Entity"). Le CRUD qu'il écrivait lui-même (create, read,
 * update, delete, readAll : appeler l'ORM avec la table 'users') est
 * maintenant écrit une seule fois dans Entity, pour tous les models ; ses
 * explications y ont déménagé. Les exemples restent vrais :
 *   create(['email' => 'a@b.c', 'password' => 'secret']) exécute
 *     INSERT INTO users (email, password) VALUES (:email, :password)
 *   update(3, ['email' => 'new@b.c']) exécute
 *     UPDATE users SET email = :email WHERE id = :id
 * Le nom de la table est deviné par Entity::getTable() : UserModel -> users.
 *
 * Ce qui reste ici, c'est ce qui est PROPRE aux utilisateurs : la liste
 * blanche des colonnes, le hachage des mots de passe (prepareFields()),
 * la recherche par email et la vérification à la connexion.
 *
 * On l'utilise maintenant comme une entité :
 *   $user = new UserModel(['email' => 'a@b.c', 'password' => 'secret']);
 *   $user->save();                      // $user->id contient le nouvel id
 *   $user = new UserModel(['id' => 3]); // l'utilisateur n°3, lu en base
 */
class UserModel extends Entity
{
    // Le nom de la table gérée par ce model était rangé ici, dans une
    // constante TABLE = 'users'.
    // Mise à jour (étape « Les entités ») : Entity::getTable() le déduit du
    // nom de la classe (UserModel -> users), la constante est donc inutile.

    /**
     * Les colonnes que create() et update() ont le droit de remplir.
     *
     * Les NOMS de colonnes ne peuvent pas passer par une requête préparée
     * (seules les valeurs le peuvent) : on les compare donc à cette "liste
     * blanche". Un champ inconnu, ou un nom piégé comme "email = '' --",
     * est refusé au lieu d'être collé dans le SQL.
     */
    private const COLUMNS = ['email', 'password'];

    // La connexion à la base ($db) et le mini-ORM branché dessus ($orm,
    // ajouté à l'étape ORM) étaient deux attributs privés de ce model.
    // Mise à jour (étape « Les entités ») : ils sont dans Entity, qui les
    // initialise dans son constructeur, comme le demandait le sujet.

    /**
     * L'email de l'utilisateur (sujet : attribut privé).
     *
     * Mise à jour (étape « Les entités ») : l'attribut devient PUBLIC, comme
     * le demande le sujet pour les attributs d'une entité ($user->email).
     * On le déclare quand même ici (Entity pourrait le créer seul) pour
     * qu'on voie d'un coup d'œil les colonnes d'un utilisateur.
     *
     * @var string
     */
    public $email = '';

    /**
     * Le mot de passe EN CLAIR, tel que tapé dans le formulaire. Il n'est
     * jamais enregistré tel quel : save() le hache avant de l'écrire.
     *
     * Mise à jour (étape « Les entités ») : public lui aussi. Attention :
     * pour un utilisateur lu en base (new UserModel(['id' => 3])), il
     * contient l'EMPREINTE, pas le mot de passe en clair. save() ne la
     * renvoie pas tant qu'on n'y touche pas (voir Entity::save()), elle
     * n'est donc jamais hachée deux fois. Ne l'envoie jamais à une vue.
     *
     * @var string
     */
    public $password = '';

    // Le constructeur initialisait la connexion (demande du sujet), avec la
    // connexion partagée de Core\Database par défaut, ou une autre donnée
    // par les tests (SQLite en mémoire).
    // Mise à jour (étape « Les entités ») : c'est le constructeur d'Entity
    // qui le fait, avec un 1er paramètre en plus, le tableau d'attributs :
    //   new UserModel(['email' => 'a@b.c'], $pdo)

    /**
     * Les "setters" modifient les attributs privés depuis l'extérieur. Comme
     * ils renvoient $this, on peut les enchaîner :
     *   $user->setEmail('a@b.c')->setPassword('secret')->save();
     *
     * Mise à jour (étape « Les entités ») : les attributs sont publics, on
     * peut aussi écrire $user->email = 'a@b.c'. Les setters restent utiles
     * pour enchaîner les appels, et parce qu'ils vérifient le type (string).
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

    // save() enregistrait en base un nouvel utilisateur avec les attributs
    // du model (sujet, partie "Mise en pratique"), le mot de passe étant
    // haché par create().
    // Mise à jour (étape « Les entités ») : save() est hérité d'Entity. Il
    // prend les colonnes de columns() (email, password), fait un INSERT si
    // l'utilisateur n'a pas d'id, un UPDATE sinon, et le hachage se fait
    // toujours au passage, dans prepareFields().

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

    // Le CRUD create(), read(), update(), delete() et readAll() était écrit
    // ici. Leurs commentaires disaient notamment :
    //   - read() renvoie la ligne, ou null si l'id n'existe pas ;
    //   - update() et delete() renvoient true si une ligne a été touchée
    //     (rowCount() > 0) ;
    //   - readAll() s'appelle read_all dans le sujet, mais PSR-1 impose le
    //     camelCase ; sans paramètre, find() prend toutes les lignes
    //     triées par "id ASC" (query() suffisait avant l'ORM : aucune
    //     valeur venant du visiteur).
    // Mise à jour (étape « Les entités ») : ces méthodes sont héritées
    // d'Entity, avec exactement le même comportement (testé dans
    // tests/UserModelTest.php). delete() accepte en plus d'être appelée
    // sans id : $user->delete() supprime l'utilisateur lui-même.

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
        // Mise à jour (étape « Les entités ») : find() d'Entity, qui connaît
        // déjà la table (plus besoin de la redonner à l'ORM).
        $users = $this->find(['WHERE' => ['email' => $email], 'LIMIT' => 1]);
        return $users[0] ?? null;
    }

    /**
     * Les colonnes que save() enregistre : la liste blanche.
     *
     * Pourquoi ? Le tableau donné au constructeur peut venir d'un formulaire
     * (getQueryParams()) et contenir d'autres clés, comme un bouton
     * "submit". Elles restent sur l'objet, mais save() ne les envoie pas.
     * Pertinence : redéfinit le crochet Entity::columns(), qui renverrait
     * sinon TOUS les attributs publics.
     */
    protected function columns(): array
    {
        return self::COLUMNS;
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
     * Mise à jour (étape « Les entités ») : la méthode redéfinit le crochet
     * Entity::prepareFields(), qu'Entity appelle avant chaque create() et
     * update(). Elle passe de "private" à "protected" : une méthode privée
     * est invisible pour la classe mère, qui ne pourrait pas l'appeler.
     *
     * @throws InvalidArgumentException pour une colonne inconnue ou aucun champ
     */
    protected function prepareFields(array $fields): array
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
