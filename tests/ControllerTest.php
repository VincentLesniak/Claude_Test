<?php

/**
 * Tests de Core\Controller : render() et l'affichage différé dans
 * __destruct().
 *
 * On utilise les vrais controllers de l'application (UserController,
 * AppController) plutôt que des faux : render() déduit le dossier des vues
 * du nom de la classe, il faut donc une classe qui a ses vues.
 */

use Controller\UserController;
use Tests\Assert;

require_once dirname(__DIR__) . '/Core/autoload.php';

/**
 * Appelle une action, puis détruit le controller, et renvoie deux textes :
 *   - ce qui a été affiché PENDANT l'action ;
 *   - ce qui a été affiché à la DESTRUCTION de l'objet.
 */
$call = function (string $action): array {
    $controller = new UserController();

    ob_start();
    $controller->$action();
    $duringAction = ob_get_clean();

    ob_start();
    // unset() supprime la dernière variable qui pointe vers l'objet : PHP
    // le détruit aussitôt et appelle __destruct().
    unset($controller);
    $atDestruction = ob_get_clean();

    return [$duringAction, $atDestruction];
};

return [
    'render() n\'affiche rien pendant l\'action' => function () use ($call): void {
        [$duringAction] = $call('loginAction');
        Assert::same('', $duringAction, 'texte affiché avant la fin');
    },

    'la page s\'affiche à la destruction du controller' => function () use ($call): void {
        [, $atDestruction] = $call('loginAction');
        Assert::contains('<h1>Connexion</h1>', $atDestruction, 'la vue est absente');
    },

    'la vue est insérée dans le layout src/View/index.php' => function () use ($call): void {
        [, $html] = $call('loginAction');
        // La vue se trouve bien ENTRE <main> et </main>, à sa place dans le layout.
        Assert::true(
            (bool) preg_match('#<main>.*<h1>Connexion</h1>.*</main>#s', $html),
            'la vue n\'est pas dans le <main> du layout'
        );
        Assert::same(1, substr_count($html, '<!doctype html>'), 'nombre de <!doctype>');
    },

    'les variables du $scope arrivent dans la vue et le layout' => function () use ($call): void {
        [, $html] = $call('showAction');
        Assert::contains('exemple@piephp.fr', $html, 'variable $user dans la vue');
        Assert::contains('<title>PiePHP - Profil</title>', $html, 'variable $title dans le layout');
    },

    'la page n\'est affichée qu\'une fois' => function () use ($call): void {
        $call('loginAction');
        // Le controller suivant n'a rien rendu : il ne doit pas réafficher
        // la page du précédent (la propriété statique est remise à zéro).
        ob_start();
        $controller = new UserController();
        unset($controller);
        Assert::same('', ob_get_clean(), 'page réaffichée');
    },

    'une vue inexistante lève une exception claire' => function (): void {
        $controller = new UserController();

        // render() est protected : impossible de l'appeler depuis le test.
        // Closure::call() exécute la fonction "comme si" elle était écrite
        // dans la classe ($this = $controller), ce qui donne accès à render().
        $renderMissing = function (): void {
            $this->render('vueinexistante');
        };

        try {
            $renderMissing->call($controller);
        } catch (RuntimeException $e) {
            Assert::contains('vueinexistante.php', $e->getMessage(), 'message de l\'exception');
            return;
        }
        throw new RuntimeException('aucune exception levée');
    },

    'htmlspecialchars protège la vue show contre le HTML injecté' => function (): void {
        $controller = new UserController();
        $renderShow = function (): void {
            $this->render('show', ['user' => ['id' => 2, 'email' => '<script>alert(1)</script>']]);
        };

        ob_start();
        $renderShow->call($controller);
        unset($controller);
        $html = ob_get_clean();

        Assert::true(strpos($html, '<script>') === false, 'la balise <script> n\'est pas échappée');
        Assert::contains('&lt;script&gt;', $html);
    },
];
