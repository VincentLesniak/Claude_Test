<?php

namespace Core;

use RuntimeException;

/**
 * Moteur de templates de PiePHP (étape « Avancé - le moteur de templates »).
 *
 * PLAN DU FICHIER
 *   1. compile()      : transforme le TEXTE d'une vue (syntaxe « à la Blade »)
 *                       en PHP classique, à coups de preg_replace ;
 *   2. compileFile()  : fait la même chose pour un FICHIER et range le résultat
 *                       dans un dossier de cache (la vue n'est recompilée que
 *                       si elle a changé) ; renvoie le chemin du fichier PHP
 *                       prêt à être inclus ;
 *   3. méthodes privées : une par famille de tags ({{ }}, @if, @foreach...).
 *
 * LE PRINCIPE (sujet, page 14)
 * Une vue est un mélange de HTML et de PHP. Écrire <?php if (...) : ?> ou
 * <?= htmlentities($x) ?> partout est long et on oublie facilement
 * l'échappement. Le moteur propose des « tags » plus courts, puis les remplace
 * par le PHP équivalent AVANT d'exécuter la vue :
 *
 *   Ce qu'on écrit dans la vue      Ce que le moteur produit
 *   ------------------------------  -----------------------------------------
 *   {{ $welcome_text }}             <?= htmlentities(...$welcome_text...) ?>
 *   @if (count($records) === 1)     <?php if (count($records) === 1) : ?>
 *   @elseif (count($records) > 1)   <?php elseif (count($records) > 1) : ?>
 *   @else                           <?php else : ?>
 *   @endif                          <?php endif; ?>
 *   @foreach ($users as $user)      <?php foreach ($users as $user) : ?>
 *   @endforeach                     <?php endforeach; ?>
 *   @isset($records)                <?php if (isset($records)) : ?>
 *   @endisset                       <?php endif; ?>
 *   @empty($records)                <?php if (empty($records)) : ?>
 *   @endempty                       <?php endif; ?>
 *
 * C'est exactement ce que fait Blade, le moteur de Laravel : on dit qu'il
 * « compile » la vue. Le résultat reste du PHP ordinaire, exécuté par include
 * comme avant : la vue garde accès à ses variables ($user, $title...).
 *
 * Une vue écrite en PHP pur (sans aucun tag) ressort du moteur à l'identique :
 * les anciennes vues continuent donc de fonctionner sans modification.
 *
 * Attention : le moteur lit TOUT le fichier, y compris les commentaires. Un
 * tag écrit dans un commentaire de vue est donc compilé lui aussi (sans
 * danger dans un commentaire PHP, mais déroutant) : dans les vues, on décrit
 * les tags en toutes lettres plutôt que de les écrire.
 *
 * RAPPEL SUR LES REGEX (expressions régulières) utilisées ici
 *   #...#     délimiteurs : le motif est entre les deux « # » (on évite « / »
 *             qui apparaît souvent dans du HTML) ; les lettres après le
 *             dernier « # » sont des options :
 *               s  le « . » attrape aussi les retours à la ligne ;
 *               x  mode « commenté » : les espaces du motif sont ignorés.
 *   \s*       zéro, un ou plusieurs espaces / retours à la ligne.
 *   (...)     un groupe « capturant » : son contenu est récupéré dans $1,
 *             $2... (ou dans $m[1], $m[2]... avec preg_replace_callback).
 *   (?<nom>…) un groupe capturant NOMMÉ : récupéré dans $m['nom'].
 *   (?:...)   un groupe NON capturant : il sert juste à regrouper.
 *   .+?       « au moins un caractère, le moins possible » (le « ? » rend
 *             le « + » paresseux : il s'arrête au PREMIER « }} » trouvé).
 *   \b        une « frontière de mot » : @else\b ne trouve pas le début de
 *             @elseif.
 *   (?<!...)  « lookbehind » négatif : vérifie ce qu'il y a JUSTE AVANT,
 *             sans le consommer. (?<![\w@])@if refuse « contact@if.fr ».
 */
class TemplateEngine
{
    /**
     * Motif qui reconnaît des parenthèses ÉQUILIBRÉES, même imbriquées :
     * « (count($records) === 1) » en entier, et pas seulement « (count($records) ».
     *
     * Pourquoi ? Une regex simple comme \((.*?)\) s'arrêterait à la première
     * parenthèse fermante, celle de count(...) : la condition serait coupée
     * en deux et le PHP produit serait faux.
     *
     * Comment ça marche ? C'est un motif RÉCURSIF :
     *   (?<args>        on ouvre un groupe nommé « args » ;
     *     \(            une parenthèse ouvrante ;
     *     (?:           puis, autant de fois que nécessaire :
     *       [^()]++     soit des caractères qui ne sont pas des parenthèses
     *                   (« ++ » = sans retour en arrière, plus rapide) ;
     *       | (?&args)  soit... un nouveau groupe « args » complet : le motif
     *                   s'appelle lui-même pour les parenthèses imbriquées ;
     *     )*
     *     \)            et enfin la parenthèse fermante qui correspond.
     *   )
     *
     * Pertinence : toutes les directives qui prennent une expression (@if,
     * @elseif, @foreach, @isset, @empty) l'utilisent ; on l'écrit une fois ici.
     *
     * Limite connue : une parenthèse SEULE dans une chaîne, comme
     * @if ($a === ')'), fausse le comptage. C'est rare dans une vue.
     */
    private const ARGS = '(?<args>\((?:[^()]++|(?&args))*\))';

    /**
     * Dossier où sont rangées les vues compilées (le « cache »).
     *
     * @var string
     */
    private $cacheDir;

    /**
     * Pourquoi ? Le PHP produit par compile() doit être écrit dans un fichier
     * pour pouvoir être inclus (include ne lit que des fichiers ; eval()
     * existe mais est dangereux et plus difficile à déboguer).
     *
     * Par défaut, on utilise le dossier temporaire du système
     * (sys_get_temp_dir() : /tmp sous Linux, C:\Windows\Temp ou similaire
     * sous Windows) : il est toujours accessible en écriture, sans réglage de
     * droits sur le projet. Les tests passent leur propre dossier.
     *
     * Pertinence : c'est aussi le « système de caching des vues » proposé
     * aux plus aventureux par le sujet : une vue n'est recompilée que si son
     * fichier a été modifié depuis la dernière compilation.
     *
     * @param string|null $cacheDir dossier du cache (null = dossier temporaire)
     */
    public function __construct($cacheDir = null)
    {
        $this->cacheDir = $cacheDir ?? sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'piephp-views';
    }

    /**
     * Transforme le texte d'une vue en PHP : c'est le cœur du moteur.
     *
     * Pourquoi cet ORDRE ?
     *   - traiter les conditions avant ou après les boucles ne change rien ;
     *   - en revanche @elseif est remplacé avant @else (sinon « @else »
     *     pourrait être reconnu au début de « @elseif » ; le \b de la regex
     *     l'empêche aussi, deux sécurités valent mieux qu'une) ;
     *   - les fins (@endif, @endforeach...) sont des mots sans parenthèses,
     *     traités à la fin ;
     *   - {{ }} peut se trouver dans n'importe quel bloc, on le traite en
     *     premier : son contenu ne contient jamais de directive « @ ».
     *
     * Pertinence : séparée de compileFile(), cette méthode ne touche à aucun
     * fichier. On peut la tester directement avec des chaînes de caractères.
     *
     * @param string $source le contenu de la vue
     *
     * @return string le même contenu, en PHP classique
     */
    public function compile($source)
    {
        $php = $this->compileEchos($source);
        $php = $this->compileConditions($php);
        $php = $this->compileLoops($php);
        $php = $this->compileEnds($php);

        return $php;
    }

    /**
     * Compile un fichier de vue (si nécessaire) et renvoie le chemin du
     * fichier PHP compilé, à passer à include.
     *
     * Pourquoi ? Compiler à chaque affichage coûterait des regex inutiles :
     * on garde le résultat dans le cache et on ne recompile que si la vue a
     * été modifiée APRÈS la dernière compilation (comparaison des dates de
     * modification avec filemtime()).
     *
     * Le nom du fichier compilé est md5(chemin de la vue) : deux vues nommées
     * index.php dans deux dossiers différents (App/index.php, User/index.php)
     * ont ainsi deux fichiers compilés différents.
     *
     * Pertinence : c'est la seule méthode dont Controller a besoin.
     *
     * @param string $file chemin complet de la vue
     *
     * @return string chemin du fichier PHP compilé
     *
     * @throws RuntimeException si la vue est illisible ou le cache non inscriptible
     */
    public function compileFile($file)
    {
        $compiled = $this->cacheDir . DIRECTORY_SEPARATOR . md5($file) . '.php';

        // clearstatcache() : PHP garde en mémoire les dates de modification
        // déjà lues (pour aller plus vite). Si une vue est modifiée pendant le
        // même script (cas des tests), on veut la date à jour.
        clearstatcache();

        if (is_file($compiled) && filemtime($compiled) >= filemtime($file)) {
            return $compiled;
        }

        $source = file_get_contents($file);
        if ($source === false) {
            throw new RuntimeException("Vue illisible : $file");
        }

        // Le « @ » devant mkdir() cache l'avertissement si deux requêtes
        // créent le dossier en même temps ; on vérifie ensuite nous-mêmes.
        // 0777 : droits demandés (réduits automatiquement par le système).
        // true : crée aussi les dossiers parents manquants.
        if (!is_dir($this->cacheDir) && !@mkdir($this->cacheDir, 0777, true) && !is_dir($this->cacheDir)) {
            throw new RuntimeException("Dossier de cache impossible à créer : {$this->cacheDir}");
        }

        // LOCK_EX : verrou pendant l'écriture, pour qu'une autre requête ne
        // lise pas un fichier à moitié écrit.
        //
        // On ajoute en tête un commentaire PHP qui rappelle la vue d'origine :
        // en cas d'erreur, PHP cite le fichier compilé (md5...php), ce
        // commentaire permet de retrouver la vraie vue. Il n'affiche rien et,
        // placé sur la ligne 1 sans retour à la ligne, il ne décale pas les
        // numéros de ligne.
        $header = '<?php /* Vue compilée par PiePHP depuis : ' . $file . ' */ ?>';
        if (file_put_contents($compiled, $header . $this->compile($source), LOCK_EX) === false) {
            throw new RuntimeException("Écriture impossible dans le cache : $compiled");
        }

        return $compiled;
    }

    /**
     * {{ $expression }}  ->  <?= htmlentities((string) ($expression), ...) ?>
     *
     * Pourquoi échapper ? Une variable peut contenir du texte saisi par un
     * visiteur. Sans échappement, un « <script> » s'exécuterait chez tous ceux
     * qui affichent la page (faille XSS). htmlentities() transforme < > " '
     * & (et les accents) en entités HTML : le texte s'affiche tel quel, sans
     * être interprété. C'est pour ça que {{ }} échappe TOUJOURS.
     *
     * Les paramètres de htmlentities() :
     *   - (string) (...) : convertit en texte (null devient "" au lieu d'un
     *     avertissement « Deprecated » en PHP 8.1+). Les parenthèses autour de
     *     l'expression évitent les surprises de priorité : (string) $a ?? 'b'
     *     ne ferait pas ce qu'on croit ;
     *   - ENT_QUOTES : échappe aussi les apostrophes (utile dans un attribut
     *     HTML entre '...') ;
     *   - 'UTF-8' : l'encodage des pages du projet ;
     *   - false (double_encode) : ne ré-échappe pas une entité déjà présente.
     *     Request échappe déjà les saisies du formulaire : sans ce false, un
     *     « & » enregistré en base (« &amp; ») s'afficherait « &amp;amp; ».
     *     Les balises restent neutralisées, la protection est intacte.
     *
     * La regex : #\{\{\s*(.+?)\s*\}\}#s
     *   \{\{ et \}\}  les accolades sont spéciales en regex (a{2} = « aa ») :
     *                 on les « échappe » avec \ pour chercher le caractère ;
     *   \s*(.+?)\s*   l'expression, sans les espaces autour ;
     *   s             l'expression peut tenir sur plusieurs lignes.
     *
     * Pertinence : c'est le tag le plus utilisé d'une vue.
     */
    private function compileEchos($source)
    {
        return preg_replace(
            '#\{\{\s*(.+?)\s*\}\}#s',
            "<?= htmlentities((string) ($1), ENT_QUOTES, 'UTF-8', false) ?>",
            $source
        );
    }

    /**
     * @if / @elseif / @else, @isset et @empty.
     *
     * Pourquoi la syntaxe « : » (if (...) : ... endif;) ? C'est la syntaxe
     * alternative de PHP, faite pour les vues : chaque morceau est un
     * <?php ... ?> indépendant, sans accolades à faire correspondre.
     *
     * @isset et @empty sont des raccourcis pour un @if : ils se terminent
     * donc tous les deux par endif (voir compileEnds()).
     *
     * preg_replace_callback() : comme preg_replace, mais le remplacement est
     * calculé par une fonction qui reçoit les morceaux trouvés ($m). On
     * l'utilise pour choisir le mot PHP selon la directive trouvée.
     *
     * La regex : (?<![\w@])@(?<directive>elseif|if|isset|empty)\s*ARGS
     *   (?<![\w@])  rien de « mot » juste avant : une adresse comme
     *               contact@empty.fr n'est pas une directive ;
     *   elseif|if   « | » = OU. elseif est essayé avant if ;
     *   \s*         Blade accepte @if(...) comme @if (...) ;
     *   ARGS        les parenthèses équilibrées (voir la constante).
     *
     * Pertinence : sans parenthèses (@if tout seul, « @isset » dans un
     * texte), rien n'est remplacé : le moteur ne casse pas un texte normal.
     */
    private function compileConditions($source)
    {
        $pattern = '#(?<![\w@])@(?<directive>elseif|if|isset|empty)\s*' . self::ARGS . '#';

        $php = preg_replace_callback($pattern, function ($m) {
            switch ($m['directive']) {
                case 'isset':
                    return "<?php if (isset{$m['args']}) : ?>";
                case 'empty':
                    return "<?php if (empty{$m['args']}) : ?>";
                default:
                    // 'if' ou 'elseif' : le mot PHP est le même que la directive.
                    return "<?php {$m['directive']} {$m['args']} : ?>";
            }
        }, $source);

        // @else n'a pas de parenthèses : un simple preg_replace suffit.
        return preg_replace('#(?<![\w@])@else\b#', '<?php else : ?>', $php);
    }

    /**
     * @foreach ($users as $user)  ->  <?php foreach ($users as $user) : ?>
     *
     * Pourquoi une méthode à part ? Le sujet range les boucles dans une
     * ligne séparée de son tableau ; Blade a aussi @for et @while qui
     * viendraient s'ajouter ici facilement (« foreach|for|while »).
     *
     * Pertinence : la boucle est indispensable pour afficher une liste
     * (les films de my_cinema, les utilisateurs...).
     */
    private function compileLoops($source)
    {
        return preg_replace(
            '#(?<![\w@])@foreach\s*' . self::ARGS . '#',
            '<?php foreach $1 : ?>',
            $source
        );
    }

    /**
     * Les fins de bloc : @endif, @endisset, @endempty -> endif ;
     * @endforeach -> endforeach.
     *
     * Pourquoi un tableau motif => remplacement ? preg_replace() accepte des
     * tableaux : il applique chaque motif l'un après l'autre. Plus lisible
     * qu'une série d'appels.
     *
     * \b après le mot : « @endifs » (faute de frappe) n'est pas confondu
     * avec @endif ; il reste visible dans la page, donc facile à repérer.
     *
     * Pertinence : sans elles, PHP signale « unexpected end of file » : chaque
     * bloc ouvert doit être refermé.
     */
    private function compileEnds($source)
    {
        return preg_replace(
            [
                '#(?<![\w@])@(?:endif|endisset|endempty)\b#',
                '#(?<![\w@])@endforeach\b#',
            ],
            [
                '<?php endif; ?>',
                '<?php endforeach; ?>',
            ],
            $source
        );
    }
}
