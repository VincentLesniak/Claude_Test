<?php

/**
 * Tests de Core\TemplateEngine (étape « Avancé - le moteur de templates »).
 *
 * Deux sortes de vérifications :
 *   - compile() : le PHP produit est bien celui du tableau du sujet ;
 *   - $render : on EXÉCUTE la vue compilée avec des variables, et on regarde
 *     le HTML obtenu. C'est ce qui compte vraiment : le PHP produit doit
 *     être valide et faire ce qu'on attend.
 *
 * Chaque test utilise un dossier de cache neuf dans le dossier temporaire,
 * pour ne pas dépendre des tests précédents.
 */

use Core\TemplateEngine;
use Tests\Assert;

require_once dirname(__DIR__) . '/Core/autoload.php';

/**
 * Crée un dossier vide dans le dossier temporaire et renvoie son chemin.
 * uniqid() donne un nom différent à chaque appel.
 */
$tempDir = function (): string {
    $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('piephp-test-', true);
    mkdir($dir);
    return $dir;
};

/**
 * Écrit $template dans un fichier de vue, le compile, l'exécute avec les
 * variables de $scope et renvoie le HTML produit. C'est ce que fait
 * Controller::render(), en miniature.
 */
$render = function (string $template, array $scope = []) use ($tempDir): string {
    $dir = $tempDir();
    $view = $dir . DIRECTORY_SEPARATOR . 'vue.php';
    file_put_contents($view, $template);

    $engine = new TemplateEngine($dir . DIRECTORY_SEPARATOR . 'cache');
    $compiled = $engine->compileFile($view);

    // Une fonction à part, pour que la vue ne voie que les variables du $scope.
    $run = function (string $__file, array $__scope): string {
        extract($__scope);
        ob_start();
        include $__file;
        return ob_get_clean();
    };

    return $run($compiled, $scope);
};

/** Retire tous les espaces, pour comparer le texte sans se soucier de l'indentation. */
$compact = function (string $html): string {
    return preg_replace('#\s+#', '', $html);
};

$records = <<<'VIEW'
@if (count($records) === 1)
    I have one record!
@elseif (count($records) > 1)
    I have multiple records!
@else
    I don't have any records!
@endif
VIEW;

return [
    '{{ }} devient un echo de htmlentities()' => function (): void {
        Assert::same(
            "<?= htmlentities((string) (\$welcome_text), ENT_QUOTES, 'UTF-8', false) ?>",
            (new TemplateEngine())->compile('{{ $welcome_text }}')
        );
    },

    '{{ }} échappe le HTML (protection XSS)' => function () use ($render): void {
        $html = $render('<p>{{ $name }}</p>', ['name' => '<script>alert("x")</script>']);
        Assert::true(strpos($html, '<script>') === false, 'la balise <script> n\'est pas échappée');
        Assert::contains('&lt;script&gt;', $html);
        Assert::contains('&quot;x&quot;', $html, 'guillemets');
    },

    '{{ }} ne ré-échappe pas une donnée déjà échappée par Request' => function () use ($render): void {
        Assert::same('<p>a &amp; b</p>', $render('<p>{{ $text }}</p>', ['text' => 'a &amp; b']));
    },

    '{{ }} accepte une expression et null' => function () use ($render): void {
        Assert::same('3-', $render('{{ 1 + 2 }}-{{ $rien }}', ['rien' => null]));
    },

    '@if / @elseif / @else / @endif (tableau du sujet)' => function () use ($render, $compact, $records): void {
        Assert::same("Ihaveonerecord!", $compact($render($records, ['records' => [1]])));
        Assert::same("Ihavemultiplerecords!", $compact($render($records, ['records' => [1, 2]])));
        Assert::same("Idon'thaveanyrecords!", $compact($render($records, ['records' => []])));
    },

    'les parenthèses imbriquées sont gardées entières' => function (): void {
        Assert::same(
            '<?php if (count($records) === 1) : ?>',
            (new TemplateEngine())->compile('@if (count($records) === 1)')
        );
        Assert::same(
            '<?php if (in_array(strtolower($a), [f(1), f(2)])) : ?>',
            (new TemplateEngine())->compile('@if(in_array(strtolower($a), [f(1), f(2)]))')
        );
    },

    '@foreach / @endforeach avec {{ }} dans la boucle' => function () use ($render, $compact): void {
        $template = "@foreach (\$users as \$user)\n<p>This is user {{ \$user->id }}</p>\n@endforeach";
        $users = [(object) ['id' => 1], (object) ['id' => 2]];
        Assert::same('<p>Thisisuser1</p><p>Thisisuser2</p>', $compact($render($template, ['users' => $users])));
    },

    '@isset / @endisset' => function () use ($render): void {
        // Un espace avant @endisset : collé à un mot (« défini@endisset »), il
        // serait pris pour une adresse email et pas compilé (comme dans Blade).
        $template = '@isset($records)défini @endisset';
        Assert::same('défini ', $render($template, ['records' => []]));
        Assert::same('', $render($template, ['records' => null]), 'null compte comme non défini');
        Assert::same('', $render($template));
    },

    '@empty / @endempty' => function () use ($render): void {
        $template = '@empty($records)vide @endempty';
        Assert::same('vide ', $render($template, ['records' => []]));
        Assert::same('', $render($template, ['records' => [1]]));
    },

    'une vue en PHP pur ressort inchangée' => function (): void {
        $php = "<?php if (\$a) : ?>\n<p><?= htmlspecialchars(\$b) ?></p>\n<?php endif; ?>";
        Assert::same($php, (new TemplateEngine())->compile($php));
    },

    'une adresse email ou un @ sans parenthèses ne sont pas des directives' => function (): void {
        $text = 'contact@if.fr, admin@empty(x).fr, @if sans parenthèses, @elsewhere';
        Assert::same($text, (new TemplateEngine())->compile($text));
    },

    'compileFile() garde la vue compilée en cache et la recompile si elle change' => function () use ($tempDir): void {
        $dir = $tempDir();
        $view = $dir . DIRECTORY_SEPARATOR . 'vue.php';
        file_put_contents($view, '{{ $a }}');

        $engine = new TemplateEngine($dir . DIRECTORY_SEPARATOR . 'cache');
        $compiled = $engine->compileFile($view);
        Assert::contains('htmlentities((string) ($a)', file_get_contents($compiled));
        Assert::contains($view, file_get_contents($compiled), 'commentaire avec la vue d\'origine');
        Assert::same($compiled, $engine->compileFile($view), 'même fichier compilé');

        // On modifie la vue et on la date d'une minute dans le futur
        // (touch) : elle est alors plus récente que le fichier compilé.
        file_put_contents($view, '{{ $b }}');
        touch($view, time() + 60);
        Assert::contains('htmlentities((string) ($b)', file_get_contents($engine->compileFile($view)));
    },

    'Controller::render() compile la vue show (plus aucun tag dans la page)' => function (): void {
        $controller = new Controller\UserController();
        $renderShow = function (): void {
            $this->render('show', ['user' => ['id' => 7, 'email' => 'a@b.fr'], 'example' => true]);
        };

        ob_start();
        $renderShow->call($controller);
        unset($controller);
        $html = ob_get_clean();

        Assert::contains('<dd>7</dd>', $html, 'id');
        Assert::contains('<dd>a@b.fr</dd>', $html, 'email');
        Assert::contains('Profil d\'exemple.', $html, 'branche @if');
        Assert::true(strpos($html, 'Se déconnecter') === false, 'la branche @else ne doit pas s\'afficher');
        Assert::true(strpos($html, '{{') === false && strpos($html, '@endif') === false, 'tag non compilé');
    },
];
