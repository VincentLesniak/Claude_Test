<?php

namespace Core;

use PDO;
use RuntimeException;

/**
 * Database : ouvre la connexion à la base de données, une seule fois.
 *
 * Le sujet (partie "Le Model") demande d'initialiser la connexion dans le
 * constructeur du model. Si chaque model ouvrait SA connexion, une page qui
 * utilise trois models se connecterait trois fois à MySQL : c'est lent et
 * inutile. Cette classe garde donc la connexion dans une propriété statique
 * et la redonne à tous ceux qui la demandent (on appelle ça un "singleton").
 *
 * Utilisation (dans le constructeur d'un model) :
 *   $this->db = Database::getConnection();
 *
 * La connexion utilise PDO ("PHP Data Objects") : la même syntaxe PHP marche
 * avec MySQL, SQLite, PostgreSQL... Seule la chaîne de connexion (le "DSN")
 * change. Les tests en profitent : ils utilisent une base SQLite en mémoire,
 * sans avoir besoin d'installer MySQL.
 */
class Database
{
    /**
     * La connexion partagée (null tant que personne ne l'a demandée).
     *
     * @var PDO|null
     */
    private static $connection = null;

    /**
     * Le fichier de configuration à lire (null = config.json à la racine).
     *
     * @var string|null
     */
    private static $configFile = null;

    /**
     * Renvoie la connexion, en la créant au premier appel à partir de la
     * section "database" de config.json.
     *
     * @throws RuntimeException si config.json est absent ou mal formé
     * @throws \PDOException    si la base refuse la connexion (mauvais mot de
     *                          passe, MySQL arrêté, base inexistante...)
     */
    public static function getConnection(): PDO
    {
        if (self::$connection === null) {
            self::$connection = self::connect(self::loadConfig());
        }
        return self::$connection;
    }

    /**
     * Remplace la connexion partagée. Sert surtout aux tests, qui donnent une
     * base SQLite en mémoire au lieu de MySQL. null oublie la connexion : la
     * prochaine demande relira config.json.
     */
    public static function setConnection(?PDO $connection): void
    {
        self::$connection = $connection;
    }

    /**
     * Choisit un autre fichier de configuration que config.json (null revient
     * à config.json). Oublie aussi la connexion en cours, pour que la
     * prochaine demande utilise bien le nouveau fichier. Sert aux tests, par
     * exemple pour simuler une base injoignable.
     */
    public static function useConfigFile(?string $file): void
    {
        self::$configFile = $file;
        self::$connection = null;
    }

    /**
     * Ouvre une nouvelle connexion PDO avec les paramètres donnés.
     *
     * @param array $config la section "database" de config.json
     */
    public static function connect(array $config): PDO
    {
        return new PDO(
            self::buildDsn($config),
            $config['user'] ?? null,
            $config['password'] ?? null,
            [
                // En cas d'erreur SQL, PDO lance une exception (PDOException)
                // au lieu de renvoyer false en silence : impossible de rater
                // une requête qui échoue.
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                // fetch() renvoie ['id' => 1, 'email' => ...] : seulement les
                // noms de colonnes, sans les doublons numériques [0 => 1, ...].
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                // Les requêtes préparées sont vraiment envoyées à MySQL en
                // deux temps (requête, puis valeurs) au lieu d'être simulées
                // par PHP : c'est la meilleure protection contre l'injection SQL.
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
    }

    /**
     * Fabrique le DSN, la chaîne qui dit à PDO où se connecter.
     *
     *   mysql  : "mysql:host=localhost;port=3306;dbname=piephp;charset=utf8mb4"
     *   sqlite : "sqlite:/chemin/vers/base.sqlite" (ou "sqlite::memory:")
     *
     * charset=utf8mb4 : le "vrai" UTF-8 de MySQL (accents, emojis...).
     *
     * @throws RuntimeException si le driver n'est pas géré
     */
    public static function buildDsn(array $config): string
    {
        $driver = $config['driver'] ?? 'mysql';

        if ($driver === 'sqlite') {
            return 'sqlite:' . ($config['path'] ?? ':memory:');
        }

        if ($driver === 'mysql') {
            return sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $config['host'] ?? 'localhost',
                $config['port'] ?? 3306,
                $config['dbname'] ?? '',
                $config['charset'] ?? 'utf8mb4'
            );
        }

        throw new RuntimeException("Driver de base de données non géré : $driver");
    }

    /**
     * Lit la section "database" de config.json, à la racine du projet.
     *
     * config.json n'est pas accessible depuis le navigateur grâce au
     * .htaccess : le mot de passe de la base ne peut pas être lu par un
     * visiteur.
     *
     * Ajout : le .htaccess protège du NAVIGATEUR, mais pas de Git. Un fichier
     * commité est lisible par tous ceux qui voient le dépôt, et il reste dans
     * l'historique même si on l'efface ensuite. On sépare donc :
     *   - config.json       : commité, valeurs d'exemple sans vrai secret
     *                         (root sans mot de passe, le défaut d'un LAMP local) ;
     *   - config.local.json : TES vrais identifiants, ignoré par Git
     *                         (voir .gitignore), jamais envoyé sur GitHub.
     *   - exemple.config.local.json : le modèle de config.local.json, commité,
     *                         avec les mêmes clés et des valeurs à remplacer
     *                         (TON_UTILISATEUR_MYSQL...). On le copie, on le
     *                         remplit, et c'est tout.
     * S'il existe, config.local.json est lu À LA PLACE de config.json.
     * (Les gros frameworks font pareil avec un fichier .env, ex. Laravel.)
     */
    private static function loadConfig(): array
    {
        $root = dirname(__DIR__) . DIRECTORY_SEPARATOR;
        $file = self::$configFile ?? (is_file($root . 'config.local.json')
            ? $root . 'config.local.json'
            : $root . 'config.json');
        if (!is_file($file)) {
            throw new RuntimeException("Fichier de configuration introuvable : $file");
        }

        // json_decode(..., true) renvoie un tableau associatif (et non un objet).
        $config = json_decode((string) file_get_contents($file), true);
        if (!is_array($config) || !isset($config['database']) || !is_array($config['database'])) {
            throw new RuntimeException('config.json doit contenir une section "database"');
        }

        return $config['database'];
    }
}
