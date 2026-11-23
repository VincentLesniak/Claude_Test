<?php

namespace Core;

use AllowDynamicProperties;
use InvalidArgumentException;
use PDO;
use ReflectionObject;
use ReflectionProperty;

/**
 * Entity : la classe mère de tous les models (sujet, partie « Les entités »).
 *
 * À l'étape précédente, l'ORM a retiré le SQL des models. Mais chaque model
 * devait encore écrire SES méthodes create(), read(), update()... qui
 * faisaient toutes la même chose : appeler l'ORM avec le nom de SA table.
 * Entity écrit ce code une seule fois ; les models en héritent avec
 * "extends Entity" et n'ont plus qu'à décrire ce qui leur est propre.
 *
 * Une ENTITÉ, c'est un objet PHP qui représente UNE ligne d'une table :
 * chaque colonne devient un attribut public de l'objet.
 *
 *   ligne de la table users          objet UserModel
 *   id | email | password      ->    $user->id, $user->email, $user->password
 *
 * Utilisation (exemple du sujet, dans registerAction) :
 *   $params = $this->request->getQueryParams();
 *   $user = new UserModel($params);    // ['email' => ..., 'password' => ...]
 *   if (!$user->id) {                  // pas d'id = pas encore en base
 *       $user->save();                 // INSERT, puis $user->id est rempli
 *   }
 *
 *   $user = new UserModel(['id' => 3]); // lit la ligne n°3 en base
 *   echo $user->email;
 *   $user->email = 'nouveau@b.c';
 *   $user->save();                      // UPDATE de la ligne n°3
 *
 * Plan du fichier :
 *   - le CONSTRUCTEUR, qui suit les deux cas du sujet (attributs donnés,
 *     ou id à lire en base) puis crée un attribut public par entrée ;
 *   - save() : enregistre l'entité elle-même (INSERT ou UPDATE) ;
 *   - le CRUD hérité par les models : create, read, update, delete,
 *     readAll et find, qui appellent l'ORM avec la bonne table ;
 *   - getTable() : devine le nom de la table d'après le nom du model ;
 *   - deux "crochets" (hooks) que les models peuvent redéfinir :
 *     columns() (les colonnes enregistrables) et prepareFields() (un
 *     traitement avant écriture, ex. hacher un mot de passe) ;
 *   - des outils privés : setAttribute() et getFields().
 *
 * Qui fait quoi ?
 *   Controller -> Model (extends Entity) -> ORM -> PDO -> base de données
 * Le controller manipule des objets, l'entité sait QUELLE table et QUELLES
 * colonnes, l'ORM sait écrire le SQL, PDO parle à la base.
 *
 * "abstract" : on ne crée jamais un objet Entity tout seul (quelle table
 * utiliserait-il ?), seulement des models qui en héritent.
 *
 * #[AllowDynamicProperties] : depuis PHP 8.2, créer un attribut qui n'est
 * pas déclaré dans la classe (un attribut "dynamique") affiche un
 * avertissement "Deprecated". Or c'est exactement ce que demande le sujet :
 * un attribut public par clé du tableau. Cet "attribut PHP" (une étiquette
 * posée sur la classe) l'autorise pour Entity ET pour ses classes filles.
 */
#[AllowDynamicProperties]
abstract class Entity
{
    /**
     * Les noms qu'un attribut venu du tableau ne peut pas prendre : ce sont
     * les attributs internes de l'entité.
     *
     * Pourquoi ? Le constructeur fait $this->$nom = $valeur pour chaque clé.
     * Sans cette liste, un formulaire contenant un champ "db" remplacerait
     * la connexion à la base par une simple chaîne, et tout casserait.
     */
    private const RESERVED = ['db', 'orm', 'original'];

    /**
     * Le nom de la table, pour un model dont le nom ne suit pas la
     * convention (voir getTable()). Vide = deviné automatiquement.
     *
     * "static" : la valeur appartient à la CLASSE (UserModel) et pas à
     * chaque objet, puisque tous les UserModel utilisent la même table.
     *
     * @var string
     */
    protected static $table = '';

    /**
     * L'id de la ligne gérée par l'entité, ou null si elle n'est pas
     * encore enregistrée en base.
     *
     * Pourquoi le déclarer ici ? Tous les models ont un id, et le sujet
     * teste "if (!$user->id)" : l'attribut doit exister (et valoir null)
     * même quand on ne l'a pas donné, sinon PHP afficherait "Undefined
     * property".
     *
     * @var int|null
     */
    public $id = null;

    /**
     * La connexion à la base (privée : ce n'est pas une colonne).
     *
     * @var PDO
     */
    private $db;

    /**
     * L'ORM qui écrit le SQL pour cette entité.
     *
     * @var ORM
     */
    private $orm;

    /**
     * Les valeurs telles qu'elles sont en base (après une lecture ou un
     * enregistrement). save() s'en sert pour n'envoyer que ce qui a changé.
     *
     * @var array
     */
    private $original = [];

    /**
     * Construit l'entité à partir d'un tableau associatif.
     *
     * Le sujet décrit deux cas :
     *   1. le tableau contient les attributs : new UserModel(['email' => 'a@b.c'])
     *      -> l'entité représente une ligne qui n'est PAS encore en base ;
     *   2. le tableau contient un id : new UserModel(['id' => 3])
     *      -> on lit la ligne n°3 avec l'ORM, et son contenu REMPLACE le
     *      tableau. Si l'id n'existe pas, l'entité reste vide (id null).
     * PUIS, dans les deux cas, chaque clé devient un attribut public.
     *
     * Comme pour l'ORM, on peut donner sa propre connexion (les tests
     * passent une base SQLite en mémoire) ; sinon, celle de Core\Database.
     *
     * Pourquoi ? C'est le cœur de l'étape : passer d'un tableau (anonyme,
     * sans méthodes) à un OBJET qui sait s'enregistrer lui-même.
     * Pertinence : sans lui, le controller devrait appeler un setter par
     * champ (setEmail(), setPassword()...) ou écrire lui-même la lecture en
     * base. Avec lui, une ligne suffit : new UserModel($params).
     *
     * @param array    $params attributs, ou ['id' => ...] pour lire en base
     * @param PDO|null $db     la connexion à utiliser (tests)
     * @throws InvalidArgumentException si une clé n'est pas un nom d'attribut valide
     */
    public function __construct(array $params = [], ?PDO $db = null)
    {
        $this->db = $db ?? Database::getConnection();
        $this->orm = new ORM($this->db);

        if (isset($params['id'])) {
            // Cas 2 : le retour de read() remplace le tableau. read() renvoie
            // null pour un id inexistant : "?? []" donne alors un tableau vide.
            $params = $this->orm->read(static::getTable(), (int) $params['id']) ?? [];
            // Ces valeurs viennent de la base : save() n'aura rien à
            // renvoyer tant qu'on ne les modifie pas.
            $this->original = $params;
        }

        foreach ($params as $name => $value) {
            $this->setAttribute((string) $name, $value);
        }

        // MySQL renvoie les nombres sous forme de texte ("3") : on remet l'id
        // en entier, pour que $user->id === 3 soit vrai.
        if ($this->id !== null) {
            $this->id = (int) $this->id;
        }
    }

    /**
     * Enregistre l'entité en base et renvoie son id.
     *
     *   - pas d'id : la ligne n'existe pas encore -> create() (INSERT), puis
     *     l'id choisi par la base est rangé dans $this->id ;
     *   - un id    : la ligne existe -> update() (UPDATE) des seules colonnes
     *     modifiées depuis la lecture. Rien de modifié = aucune requête.
     *
     * Pourquoi ? Le sujet l'utilise tel quel : $user->save(). L'entité
     * connaît ses propres valeurs, elle n'a pas besoin qu'on les lui redonne.
     * Pertinence : sans elle, il faudrait choisir soi-même entre create() et
     * update() et recopier chaque attribut dans un tableau. N'envoyer que
     * les colonnes modifiées évite aussi un piège : un mot de passe lu en
     * base est déjà haché, le renvoyer le ferait hacher une 2e fois.
     *
     * @return int l'id de la ligne
     */
    public function save(): int
    {
        $fields = $this->getFields();

        if ($this->id === null) {
            $this->id = $this->create($fields);
            $this->original = $fields;
            return $this->id;
        }

        // array_diff_assoc() garde les entrées de $fields dont la valeur
        // n'est pas la même dans $this->original : les colonnes modifiées.
        // On compare des textes, car la base peut renvoyer 5 sous forme "5".
        $changed = array_diff_assoc(
            array_map('strval', $fields),
            array_map('strval', $this->original)
        );
        // On reprend les vraies valeurs (pas leur version texte).
        $changed = array_intersect_key($fields, $changed);

        if ($changed !== []) {
            $this->update($this->id, $changed);
            $this->original = $changed + $this->original;
        }
        return $this->id;
    }

    /**
     * Crée une nouvelle ligne dans la table du model et renvoie son id.
     *
     * Pourquoi ? C'est le C de CRUD, écrit UNE fois pour tous les models :
     * avant, UserModel avait son propre create() qui appelait l'ORM avec
     * 'users' ; un ArticleModel aurait recopié le même code avec 'articles'.
     * Pertinence : save() l'utilise pour un nouvel enregistrement ; on peut
     * aussi l'appeler directement avec un tableau. prepareFields() passe
     * avant : c'est là qu'un model ajoute ses règles (liste blanche, hachage).
     *
     * @param array $fields colonne => valeur
     * @return int l'id attribué par la base
     */
    public function create(array $fields): int
    {
        return $this->orm->create(static::getTable(), $this->prepareFields($fields));
    }

    /**
     * Lit une ligne d'après son id (un tableau, ou null).
     *
     * Pourquoi ? Le R de CRUD. Le constructeur avec ['id' => 3] renvoie un
     * OBJET ; read() renvoie un simple TABLEAU, pratique pour une vue.
     * Pertinence : UserController::showAction() l'utilise pour le profil.
     *
     * @return array|null la ligne (colonne => valeur), ou null si l'id n'existe pas
     */
    public function read(int $id): ?array
    {
        return $this->orm->read(static::getTable(), $id);
    }

    /**
     * Met à jour les champs donnés de la ligne $id.
     *
     * Pourquoi ? Le U de CRUD, commun à tous les models.
     * Pertinence : save() l'appelle avec les seules colonnes modifiées. Les
     * champs passent aussi par prepareFields(), comme pour create().
     *
     * @return bool true si une ligne a été modifiée
     */
    public function update(int $id, array $fields): bool
    {
        return $this->orm->update(static::getTable(), $id, $this->prepareFields($fields));
    }

    /**
     * Supprime la ligne $id ; sans argument, supprime l'entité elle-même.
     *
     *   $users->delete(3);                     // la ligne n°3
     *   $user = new UserModel(['id' => 3]);
     *   $user->delete();                       // la même chose
     *
     * Pourquoi ? Le D de CRUD. Le paramètre est facultatif pour qu'une
     * entité chargée puisse se supprimer sans répéter son propre id.
     * Pertinence : après sa propre suppression, l'entité repasse à
     * id = null : un save() la recréerait comme une NOUVELLE ligne, au lieu
     * de tenter un UPDATE sur une ligne qui n'existe plus.
     *
     * @return bool true si une ligne a été supprimée
     */
    public function delete(?int $id = null): bool
    {
        $id = $id ?? $this->id;
        if ($id === null) {
            return false; // jamais enregistrée : rien à supprimer
        }

        $deleted = $this->orm->delete(static::getTable(), $id);
        if ($id === $this->id) {
            $this->id = null;
            $this->original = [];
        }
        return $deleted;
    }

    /**
     * Lit toutes les lignes de la table, de la plus ancienne à la plus récente.
     *
     * Pourquoi ? Le sujet demande read_all() dans chaque model ("Le Model") ;
     * PSR-1 impose le camelCase : readAll().
     * Pertinence : une page "liste des utilisateurs" ou "tous les articles".
     *
     * @return array la liste des lignes (un tableau associatif par ligne)
     */
    public function readAll(): array
    {
        return $this->orm->find(static::getTable());
    }

    /**
     * Cherche des lignes de la table : mêmes paramètres que ORM::find()
     * ('WHERE', 'ORDER BY', 'LIMIT'), sans avoir à redonner la table.
     *
     * Pourquoi ? Chaque model a ses propres recherches (un utilisateur par
     * email, les articles d'un auteur) : find() les rend courtes à écrire.
     * Pertinence : UserModel::readByEmail() l'utilise. Les vérifications de
     * sécurité (WHERE préparé, ORDER BY et LIMIT contrôlés) restent celles
     * de l'ORM.
     *
     * @return array la liste des lignes
     */
    public function find(array $params = ORM::FIND_DEFAULTS): array
    {
        return $this->orm->find(static::getTable(), $params);
    }

    /**
     * Le nom de la table du model.
     *
     * Convention : le nom de la classe, sans le suffixe "Model", en
     * minuscules, avec un "s" (une table contient PLUSIEURS lignes) :
     *   UserModel    -> users
     *   ArticleModel -> articles
     * Un model qui ne suit pas la convention (ex. une table "people")
     * redéfinit simplement : protected static $table = 'people';
     *
     * "static::" (et pas "self::") : PHP regarde la classe RÉELLE de
     * l'objet (UserModel), pas la classe où le code est écrit (Entity).
     * C'est ce qu'on appelle la "liaison statique tardive".
     *
     * Pourquoi ? Toutes les méthodes du CRUD ont besoin du nom de la table.
     * Pertinence : grâce à la convention, un nouveau model n'a besoin
     * d'aucune configuration ("convention plutôt que configuration", comme
     * CakePHP). Publique et statique : on peut la demander sans objet,
     * UserModel::getTable(), ce qui servira aux relations entre models.
     */
    public static function getTable(): string
    {
        if (static::$table !== '') {
            return static::$table;
        }

        // static::class : le nom complet, ex. "Model\UserModel".
        // On garde la dernière partie ("UserModel"), puis on retire "Model".
        $parts = explode('\\', static::class);
        $name = preg_replace('/Model$/', '', end($parts));

        return strtolower($name) . 's';
    }

    /**
     * Crochet : les colonnes que save() doit enregistrer.
     *
     * Par défaut (tableau vide), tous les attributs publics sauf l'id.
     * Un model peut donner une liste précise : les autres attributs (ex. le
     * bouton "submit" d'un formulaire passé avec getQueryParams()) restent
     * sur l'objet, mais ne sont pas envoyés en base.
     *
     * Pourquoi ? Le tableau donné au constructeur peut contenir des clés
     * qui ne sont pas des colonnes ; l'INSERT échouerait alors.
     * Pertinence : UserModel le redéfinit avec sa liste blanche (email,
     * password). "protected" : visible par les classes filles, pas ailleurs.
     *
     * @return string[]
     */
    protected function columns(): array
    {
        return [];
    }

    /**
     * Crochet : dernier traitement des champs avant create() et update().
     *
     * Par défaut, rien : l'ORM vérifie déjà les noms de colonnes.
     *
     * Pourquoi ? Certaines règles sont propres à UN model : hacher un mot
     * de passe, refuser une colonne... Entity ne peut pas les connaître.
     * Pertinence : UserModel le redéfinit pour sa liste blanche et le
     * hachage. Sans ce crochet, il devrait redéfinir create() ET update().
     *
     * @return array les champs, éventuellement modifiés
     */
    protected function prepareFields(array $fields): array
    {
        return $fields;
    }

    /**
     * Crée (ou remplace) l'attribut public $name.
     *
     * Pourquoi ? "$this->$name = $value" crée un attribut dont le NOM est
     * dans la variable $name. C'est puissant, donc on vérifie le nom avant.
     * Pertinence : sans vérification, une clé "db" écraserait la connexion
     * (voir RESERVED), et une clé "mon champ" donnerait un attribut
     * inutilisable ($user->mon champ n'est pas du PHP valide).
     *
     * @throws InvalidArgumentException si le nom est invalide ou réservé
     */
    private function setAttribute(string $name, $value): void
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name) || in_array($name, self::RESERVED, true)) {
            throw new InvalidArgumentException("Nom d'attribut invalide pour une entité : $name");
        }
        $this->$name = $value;
    }

    /**
     * Les attributs publics de l'entité (sauf l'id) : colonne => valeur.
     *
     * get_object_vars($this) renverrait AUSSI les attributs privés ($db,
     * $orm...), car on est à l'intérieur de la classe. ReflectionObject
     * permet d'inspecter l'objet et de ne garder que les attributs publics,
     * y compris ceux créés par le constructeur.
     *
     * Pourquoi ? save() doit transformer l'objet en tableau pour l'ORM.
     * Pertinence : c'est le chemin inverse du constructeur (tableau -> objet).
     *
     * @return array
     */
    private function getFields(): array
    {
        $fields = [];
        $properties = (new ReflectionObject($this))->getProperties(ReflectionProperty::IS_PUBLIC);
        foreach ($properties as $property) {
            $name = $property->getName();
            if ($property->isStatic() || $name === 'id') {
                continue;
            }
            $fields[$name] = $this->$name;
        }

        $columns = $this->columns();
        if ($columns !== []) {
            // array_flip : ['email', 'password'] -> ['email' => 0, 'password' => 1],
            // pour garder seulement ces clés avec array_intersect_key.
            $fields = array_intersect_key($fields, array_flip($columns));
        }
        return $fields;
    }
}
