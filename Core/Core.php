<?php

/**
 * Core/Core.php : la classe principale du framework.
 *
 * C'est elle qui "lance" l'application. Pour l'instant, run() affiche juste
 * un message pour prouver que la classe a bien été trouvée et chargée.
 * Plus tard, c'est ici qu'on branchera le routeur : lire l'URL, choisir le
 * bon contrôleur, appeler la bonne action.
 */

// Le namespace est comme un "nom de famille" pour les classes : le nom
// complet de cette classe est Core\Core. Cela évite les conflits si une
// autre bibliothèque possède aussi une classe "Core".
// Convention suivie ici (inspirée de PSR-4) : le namespace correspond au
// dossier, et le nom de la classe au nom du fichier.
//   Core\Core  ->  Core/Core.php
// C'est grâce à cette correspondance que l'autoloader retrouve le fichier.
namespace Core;

class Core
{
    /**
     * Démarre l'application.
     *
     * __CLASS__ est une "constante magique" : PHP la remplace par le nom
     * complet de la classe courante, ici "Core\Core".
     * PHP_EOL est le retour à la ligne adapté au système (\n sous Linux).
     */
    public function run()
    {
        echo __CLASS__ . " [OK]" . PHP_EOL;
    }
}
