<?php

/**
 * tests/Server.php : démarre le serveur web intégré de PHP pour les tests.
 *
 * Pour tester une page "comme un navigateur", il faut un serveur HTTP.
 * Plutôt qu'Apache, on utilise "php -S", le petit serveur fourni avec PHP
 * (parfait pour les tests, jamais en production). On le lance en
 * arrière-plan une seule fois, puis on lui envoie des requêtes.
 *
 * Limite à connaître : php -S ignore le .htaccess. Les règles de
 * réécriture d'Apache ne sont donc pas testées ici.
 */

namespace Tests;

use RuntimeException;

class Server
{
    private const HOST = '127.0.0.1:8765';

    /** @var resource|null le processus du serveur (null tant qu'il n'est pas lancé) */
    private static $process = null;

    /**
     * Envoie une requête au serveur et renvoie le corps de la réponse.
     *
     * @param string $path   le chemin demandé, ex. '/index.php?a=1'
     * @param array  $post   si non vide, la requête est un POST avec ces champs
     */
    public static function request(string $path, array $post = []): string
    {
        self::start();

        $options = ['http' => ['method' => 'GET', 'ignore_errors' => true]];
        if ($post !== []) {
            // http_build_query transforme ['email' => 'a@b.c'] en "email=a%40b.c",
            // exactement ce qu'envoie un navigateur quand on valide un formulaire.
            $options['http'] = [
                'method' => 'POST',
                'header' => 'Content-Type: application/x-www-form-urlencoded',
                'content' => http_build_query($post),
                'ignore_errors' => true,
            ];
        }

        $body = file_get_contents('http://' . self::HOST . $path, false, stream_context_create($options));
        if ($body === false) {
            throw new RuntimeException("Impossible de joindre le serveur pour $path");
        }
        return $body;
    }

    /**
     * Lance "php -S" à la racine du projet si ce n'est pas déjà fait, puis
     * attend qu'il réponde.
     */
    private static function start(): void
    {
        if (self::$process !== null) {
            return;
        }

        $root = dirname(__DIR__);
        $command = [PHP_BINARY, '-S', self::HOST, '-t', $root];
        // proc_open lance une commande en arrière-plan ; on jette sa sortie.
        $null = ['file', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null', 'w'];
        self::$process = proc_open($command, [1 => $null, 2 => $null], $pipes);

        // On s'assure que le serveur sera arrêté à la fin des tests.
        register_shutdown_function(function (): void {
            proc_terminate(self::$process);
        });

        // Le serveur met quelques millisecondes à démarrer : on réessaie
        // de s'y connecter pendant 5 secondes maximum.
        for ($i = 0; $i < 50; $i++) {
            $socket = @fsockopen('127.0.0.1', 8765);
            if ($socket !== false) {
                fclose($socket);
                return;
            }
            usleep(100000);
        }
        throw new RuntimeException('Le serveur PHP intégré n\'a pas démarré');
    }
}
