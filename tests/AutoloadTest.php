<?php

/**
 * Tests de Core/autoload.php : l'autoloader trouve-t-il les bonnes classes ?
 */

use Tests\Assert;

require_once dirname(__DIR__) . '/Core/autoload.php';

return [
    'charge Core\Core depuis Core/Core.php (namespace = dossier)' => function (): void {
        // class_exists() déclenche l'autoloader si la classe est inconnue.
        Assert::true(class_exists('Core\Core'), 'la classe Core\Core est introuvable');
    },

    'retrouve une classe sans namespace rangée dans src/Controller' => function (): void {
        // On crée un faux contrôleur le temps du test, puis on le supprime
        // (finally s'exécute même si le test échoue).
        $file = dirname(__DIR__) . '/src/Controller/AutoloadProbeController.php';
        file_put_contents($file, "<?php\n\nclass AutoloadProbeController\n{\n}\n");
        try {
            Assert::true(class_exists('AutoloadProbeController'), 'classe de src/Controller introuvable');
        } finally {
            unlink($file);
        }
    },

    'renvoie false (sans planter) pour une classe qui n\'existe pas' => function (): void {
        Assert::same(false, class_exists('Classe\Qui\NExistePas'));
    },
];
