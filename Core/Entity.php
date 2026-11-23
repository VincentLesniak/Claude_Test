<?php

namespace Core;

use AllowDynamicProperties;
use InvalidArgumentException;
use PDO;
use ReflectionClass;
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
 *   - Ajout (partie « My cinema ») : getConnection(), qui prête la
 *     connexion aux models (pour qu'un model en utilise un autre).
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
 *
 * Mise à jour (étape « Avancé - Les relations de model ») : un model peut
 * décrire ses liens avec d'autres models dans un attribut privé statique
 * $relations, avec les phrases du sujet :
 *
 *   class ArticleModel extends Entity
 *   {
 *       private static $relations = ['has many comments', 'has many tags'];
 *   }
 *   class CommentModel extends Entity
 *   {
 *       private static $relations = ['has one article'];
 *   }
 *   class TagModel extends Entity
 *   {
 *       private static $relations = ['has many articles'];
 *   }
 *
 * read(), find(), readAll() et le constructeur avec un id renvoient alors
 * AUSSI les enregistrements liés :
 *
 *   $article = new ArticleModel(['id' => 1]);
 *   $article->comments;   // la liste des commentaires de l'article 1
 *   $article->tags;       // la liste de ses tags
 *   $comment = new CommentModel(['id' => 5]);
 *   $comment->article;    // l'article du commentaire 5 (une seule ligne)
 *
 * Comment Entity reconnaît le type de chaque relation :
 *   - 'has one article'   : comments contient article_id      -> N : 1 ;
 *   - 'has many comments' : comments contient article_id      -> 1 : N ;
 *   - 'has many tags' ET TagModel dit 'has many articles'     -> N : N,
 *     les liens sont dans la table pivot articles_tags (les deux tables
 *     dans l'ordre alphabétique, séparées par "_").
 * Entity traduit ces phrases en tableaux pour l'ORM (getRelations()), et
 * c'est l'ORM qui écrit les jointures SQL (ORM::readRelation()).
 *
 * Le plan du fichier gagne :
 *   - getRelations() : traduit les phrases du model pour l'ORM ;
 *   - des outils privés : declaredRelations(), parseRelation(),
 *     modelClass() et relationKeys().
 *
 * Pourquoi Entity ne déclare-t-elle pas elle-même $relations ? Le sujet
 * demande un attribut PRIVÉ dans chaque model. Or PHP interdit à une
 * classe fille de redéclarer en "private" un attribut que sa classe mère
 * déclare en "protected" ou "public" (on ne peut pas réduire la
 * visibilité). Entity ne déclare donc rien, et lit l'attribut privé de
 * chaque model avec la Reflection (voir declaredRelations()).
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
     * La syntaxe de la signature, morceau par morceau :
     *   array $params = []  un tableau, facultatif (vide par défaut) ;
     *   ?PDO $db = null     un objet PDO OU null ("?" = type "nullable"),
     *                       facultatif lui aussi (null par défaut) ;
     *   $db ?? Database::getConnection()
     *                       "??" renvoie $db s'il n'est pas null, sinon la
     *                       valeur de droite : la connexion partagée.
     * Donner la connexion de l'extérieur s'appelle l'"injection de
     * dépendance" : l'objet ne choisit pas lui-même sa base.
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
            $params = $this->orm->read(static::getTable(), (int) $params['id'], static::getRelations()) ?? [];
            // Ces valeurs viennent de la base : save() n'aura rien à
            // renvoyer tant qu'on ne les modifie pas.
            // Mise à jour (étape « Les relations de model ») : read() a aussi
            // rempli les relations (ex. 'comments'). Elles deviennent des
            // attributs comme les autres ($article->comments), mais ce ne
            // sont pas des colonnes : array_diff_key() les retire des
            // valeurs "telles qu'en base" (il garde les clés de $params
            // absentes de relationKeys()).
            $this->original = array_diff_key($params, self::relationKeys());
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
     * Mise à jour (étape « Les relations de model ») : la ligne contient
     * aussi une entrée par relation du model (voir getRelations()).
     *
     * @return array|null la ligne (colonne => valeur), ou null si l'id n'existe pas
     */
    public function read(int $id): ?array
    {
        return $this->orm->read(static::getTable(), $id, static::getRelations());
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
     * Mise à jour (étape « Les relations de model ») : chaque ligne contient
     * aussi ses enregistrements liés.
     *
     * @return array la liste des lignes (un tableau associatif par ligne)
     */
    public function readAll(): array
    {
        return $this->orm->find(static::getTable(), ORM::FIND_DEFAULTS, static::getRelations());
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
     * Mise à jour (étape « Les relations de model ») : comme read(), chaque
     * ligne trouvée contient aussi ses enregistrements liés.
     *
     * @return array la liste des lignes
     */
    public function find(array $params = ORM::FIND_DEFAULTS): array
    {
        return $this->orm->find(static::getTable(), $params, static::getRelations());
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
     *
     * Mise à jour (étape « Les relations de model ») : c'est fait.
     * getRelations() appelle getTable() sur l'AUTRE model d'une relation
     * ($model::getTable(), avec le nom de la classe dans une variable),
     * pour connaître sa table sans créer d'objet.
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
     * Les relations du model, traduites pour l'ORM.
     *
     * Chaque phrase de $relations devient un tableau, celui qu'attendent
     * ORM::read() et ORM::find(). Pour l'exemple du blog :
     *
     *   ArticleModel::getRelations() =
     *     [['type' => 'has many',     'table' => 'comments', 'name' => 'comments'],
     *      ['type' => 'many to many', 'table' => 'tags',     'name' => 'tags',
     *       'pivot' => 'articles_tags']]
     *   CommentModel::getRelations() =
     *     [['type' => 'has one', 'table' => 'articles', 'name' => 'article']]
     *
     * 'name' est le nom de l'entrée ajoutée à la ligne : le mot écrit dans
     * la phrase. 'has one article' donne donc une entrée 'article' (au
     * singulier : il n'y en a qu'un), et 'has many comments' une entrée
     * 'comments'.
     *
     * Comment distinguer 1 : N et N : N, puisque le sujet écrit 'has many'
     * dans les deux cas ? Pour 'has many tags', on cherche le model de la
     * table tags (TagModel, dans le même namespace). S'il dit lui aussi
     * 'has many articles', la relation est 'has many' des DEUX côtés :
     * c'est un N : N, rangé dans la table pivot articles_tags. Sinon, c'est
     * un 1 : N (la table tags contiendrait alors article_id).
     *
     * Pourquoi ? Les phrases du sujet sont faites pour être lues par un
     * humain ; l'ORM, lui, a besoin d'informations précises (quelle table,
     * quel type, quelle table pivot).
     * Pertinence : le model n'écrit qu'une phrase par relation, Entity
     * déduit tout le reste grâce aux conventions (noms de tables, de
     * models et de clés étrangères). Publique et statique : on peut
     * l'afficher pour comprendre ce que fait le framework,
     * var_dump(ArticleModel::getRelations()), sans créer d'objet.
     *
     * @return array la liste des relations (un tableau par relation)
     * @throws InvalidArgumentException si une phrase n'est pas comprise
     */
    public static function getRelations(): array
    {
        $relations = [];
        foreach (self::declaredRelations(static::class) as $declaration) {
            // [$type, $name] = ... : la "déstructuration" range la 1re case
            // du tableau renvoyé dans $type, la 2e dans $name.
            [$type, $name] = self::parseRelation($declaration);

            if ($type === 'has one') {
                // 'has one article' : la table est celle d'ArticleModel s'il
                // existe (il a pu choisir un autre nom), sinon "article" + "s".
                $model = self::modelClass($name);
                $relations[] = [
                    'type' => 'has one',
                    'table' => $model !== null ? $model::getTable() : $name . 's',
                    'name' => $name,
                ];
                continue;
            }

            // 'has many tags' : 1 : N, sauf si TagModel répond 'has many articles'.
            $relation = ['type' => 'has many', 'table' => $name, 'name' => $name];
            $model = self::modelClass(ORM::singular($name));
            if ($model !== null && in_array(['has many', static::getTable()], self::parsedRelations($model), true)) {
                // La table pivot : les deux tables, triées par ordre
                // alphabétique, pour que les DEUX models trouvent le même nom
                // (articles_tags, que l'on parte d'ArticleModel ou de TagModel).
                $tables = [static::getTable(), $name];
                sort($tables);
                $relation['type'] = 'many to many';
                $relation['pivot'] = implode('_', $tables);
            }
            $relations[] = $relation;
        }
        return $relations;
    }

    /**
     * Ajout (partie « My cinema by PiePHP ») : la connexion de l'entité,
     * pour les classes filles.
     *
     * Pourquoi ? Un model a parfois besoin d'un AUTRE model, sur la MÊME
     * base : FilmModel vérifie que le genre choisi existe (GenreModel) et
     * efface l'historique du film supprimé (HistoriqueModel). L'attribut
     * $db est privé (une clé "db" venue d'un formulaire ne doit jamais le
     * remplacer, voir RESERVED) : cette méthode le prête sans l'exposer.
     * Pertinence : sans elle, le second model prendrait la connexion par
     * défaut (Core\Database), et un model créé avec une autre base (une
     * base SQLite de test par exemple) mélangerait deux bases.
     * "protected" : seuls les models y ont accès, pas les controllers ni
     * les vues.
     *
     *   new GenreModel(['id' => 2], $this->getConnection());
     */
    protected function getConnection(): PDO
    {
        return $this->db;
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

        // Mise à jour (étape « Les relations de model ») : les relations
        // chargées ($article->comments) sont des attributs publics, mais pas
        // des colonnes de la table : on ne les envoie jamais en base.
        $fields = array_diff_key($fields, self::relationKeys());

        $columns = $this->columns();
        if ($columns !== []) {
            // array_flip : ['email', 'password'] -> ['email' => 0, 'password' => 1],
            // pour garder seulement ces clés avec array_intersect_key.
            $fields = array_intersect_key($fields, array_flip($columns));
        }
        return $fields;
    }

    /**
     * Lit l'attribut privé statique $relations d'un model (ou [] s'il n'en
     * a pas).
     *
     * Le sujet le veut "privé" : en temps normal, seule la classe qui le
     * déclare peut le lire, pas Entity (sa classe mère), ni un autre model.
     * La Reflection est l'outil de PHP qui permet d'INSPECTER une classe
     * (ses attributs, ses méthodes...) : ReflectionProperty représente un
     * attribut, et getValue() lit sa valeur, même privée (depuis PHP 8.1).
     *
     * Pourquoi ? getRelations() a besoin des phrases du model courant, et
     * de celles de l'AUTRE model pour reconnaître un N : N.
     * Pertinence : sans elle, il faudrait rendre $relations "protected"
     * (moins strict que le sujet) ou demander à chaque model d'écrire une
     * méthode qui le renvoie.
     *
     * @param string $class le nom complet du model, ex. "Model\ArticleModel"
     * @return string[] les phrases, ex. ['has many comments']
     * @throws InvalidArgumentException si $relations n'est pas un tableau statique
     */
    private static function declaredRelations(string $class): array
    {
        // property_exists() voit aussi les attributs privés.
        if (!property_exists($class, 'relations')) {
            return [];
        }

        $property = new ReflectionProperty($class, 'relations');
        if (!$property->isStatic() || !is_array($property->getValue())) {
            throw new InvalidArgumentException("$class::\$relations doit être un tableau statique");
        }
        return $property->getValue();
    }

    /**
     * Les phrases d'un model, découpées : [['has many', 'comments'], ...].
     *
     * Pourquoi ? getRelations() compare les relations de l'autre model avec
     * in_array() : c'est plus simple sur des phrases déjà découpées.
     * Pertinence : un raccourci de deux lignes, pour que getRelations()
     * reste lisible.
     */
    private static function parsedRelations(string $class): array
    {
        return array_map([self::class, 'parseRelation'], self::declaredRelations($class));
    }

    /**
     * Découpe une phrase de relation : 'has many comments' -> ['has many', 'comments'].
     *
     * L'expression régulière, morceau par morceau :
     *   ^ ... $              toute la phrase (début ... fin) ;
     *   \s*                  des espaces, ou aucun ;
     *   has\s+(many|one)     "has", des espaces, puis "many" OU "one"
     *                        (les parenthèses "capturent" ce mot) ;
     *   ([A-Za-z_]\w*)       le nom : une lettre ou "_", puis des lettres,
     *                        chiffres ou "_" (\w), capturé lui aussi ;
     *   le "i" final         majuscules ou minuscules ("Has Many" marche).
     * L'espace entre many/one et le nom est facultatif (\s*) car le sujet
     * écrit 'has many' . $table : collé, cela donne 'has manycomments'.
     *
     * Pourquoi ? Les phrases sont écrites à la main : une faute de frappe
     * ('has meny comments') doit donner une erreur claire, pas une
     * relation ignorée en silence.
     * Pertinence : le nom capturé est vérifié par la regex comme un nom de
     * table (lettres, chiffres, "_"), avant même que l'ORM le revérifie.
     *
     * @return array [le type ('has many' ou 'has one'), le nom]
     * @throws InvalidArgumentException si la phrase n'est pas comprise
     */
    private static function parseRelation($declaration): array
    {
        if (!is_string($declaration) || !preg_match('/^\s*has\s+(many|one)\s*([A-Za-z_]\w*)\s*$/i', $declaration, $m)) {
            throw new InvalidArgumentException('Relation invalide : ' . var_export($declaration, true));
        }
        // $m : la phrase entière dans $m[0], puis chaque partie capturée.
        return ['has ' . strtolower($m[1]), $m[2]];
    }

    /**
     * Le model qui correspond à un nom au singulier, s'il existe :
     * 'tag' -> "Model\TagModel" (même namespace que le model courant).
     *
     * class_exists() demande à l'autoloader de charger la classe si besoin,
     * et renvoie false si elle n'existe nulle part.
     *
     * Pourquoi ? Pour 'has one article', on veut la vraie table
     * d'ArticleModel ; pour 'has many tags', les relations de TagModel.
     * Pertinence : c'est la convention des models du projet (UserModel,
     * ArticleModel...) qui évite d'écrire le nom de la classe dans la
     * phrase. Renvoyer null (au lieu d'une erreur) permet une relation vers
     * une table qui n'a pas (encore) de model.
     *
     * @return string|null le nom complet de la classe, ou null
     */
    private static function modelClass(string $singular): ?string
    {
        // getNamespaceName() : "Model" pour "Model\ArticleModel".
        $namespace = (new ReflectionClass(static::class))->getNamespaceName();
        $class = ($namespace !== '' ? $namespace . '\\' : '') . ucfirst($singular) . 'Model';

        // is_subclass_of() : la classe doit aussi être un model (une Entity).
        return class_exists($class) && is_subclass_of($class, self::class) ? $class : null;
    }

    /**
     * Les noms des relations du model, en CLÉS : ['comments' => 0, 'tags' => 1].
     *
     * Pourquoi en clés ? Pour retirer les relations d'un tableau de champs
     * avec array_diff_key(), qui compare les clés.
     * Pertinence : le constructeur et getFields() s'en servent pour ne
     * jamais traiter une relation comme une colonne de la table (un INSERT
     * avec une colonne "comments" échouerait).
     */
    private static function relationKeys(): array
    {
        // array_column(..., 'name') : la valeur 'name' de chaque relation.
        return array_flip(array_column(static::getRelations(), 'name'));
    }
}
