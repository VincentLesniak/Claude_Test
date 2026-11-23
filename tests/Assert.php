<?php

/**
 * tests/Assert.php : petites fonctions de vérification pour nos tests.
 *
 * Un test automatique, c'est du code qui exécute ton code et vérifie que le
 * résultat est celui attendu. Si ce n'est pas le cas, on lance une
 * exception : le lanceur (tests/run.php) l'attrape et marque le test en
 * échec.
 *
 * On aurait pu utiliser PHPUnit (l'outil standard en PHP), mais écrire ces
 * quelques méthodes soi-même montre qu'un test n'a rien de magique.
 */

namespace Tests;

use RuntimeException;

class Assert
{
    /**
     * Vérifie que $actual vaut exactement $expected (même valeur, même type).
     */
    public static function same($expected, $actual, string $message = ''): void
    {
        if ($expected !== $actual) {
            throw new RuntimeException(
                ($message ? $message . ' : ' : '')
                . 'attendu ' . var_export($expected, true)
                . ', obtenu ' . var_export($actual, true)
            );
        }
    }

    /**
     * Vérifie qu'une condition est vraie.
     */
    public static function true(bool $condition, string $message): void
    {
        if (!$condition) {
            throw new RuntimeException($message);
        }
    }

    /**
     * Vérifie que $needle apparaît dans le texte $haystack.
     */
    public static function contains(string $needle, string $haystack, string $message = ''): void
    {
        if (strpos($haystack, $needle) === false) {
            throw new RuntimeException(
                ($message ? $message . ' : ' : '') . 'le texte ne contient pas ' . var_export($needle, true)
            );
        }
    }
}
