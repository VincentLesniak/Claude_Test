<?php

/**
 * Tests de la classe Core\Core.
 */

use Tests\Assert;

require_once dirname(__DIR__) . '/Core/autoload.php';

return [
    'run() affiche "Core\Core [OK]"' => function (): void {
        // ob_start() "capture" tout ce qui est affiché (echo) au lieu de
        // l'envoyer à l'écran ; ob_get_clean() le récupère sous forme de texte.
        ob_start();
        (new Core\Core())->run();
        $output = ob_get_clean();

        Assert::same('Core\Core [OK]' . PHP_EOL, $output);
    },
];
