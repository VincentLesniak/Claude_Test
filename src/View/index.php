<?php
/*
 * LAYOUT : src/View/index.php
 *
 * Le "squelette" commun à toutes les pages : <!doctype>, <head>, menu...
 * Il est écrit UNE seule fois ici, au lieu d'être recopié dans chaque vue.
 * Core\Controller::render() l'inclut après avoir préparé la vue demandée.
 *
 * Variables disponibles (créées par render() avec extract()) :
 *   $view  : le HTML de la vue (src/View/User/login.php par exemple)
 *   $title : (facultatif) le titre de la page, choisi par l'action
 *   + toutes les autres variables passées dans le $scope de render()
 *
 * BASE_URI ("/PiePHP" chez toi, défini dans index.php) préfixe les liens :
 * ils fonctionnent ainsi quel que soit le dossier où est installé le projet.
 *
 * Rappel : "<?= x ?>" est le raccourci de "<?php echo x; ?>".
 */
?>
<!doctype html>
<html lang="fr">
<head>
    <!-- Encodage des caractères : indispensable pour les accents. -->
    <meta charset="UTF-8">
    <!-- Adapte l'affichage à la largeur de l'écran (mobile). -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Le <title> est obligatoire pour que la page soit valide W3C. -->
    <?php /* Si l'action n'a pas transmis de $title, on affiche seulement "PiePHP". */ ?>
    <title><?= isset($title) ? 'PiePHP - ' . htmlspecialchars($title) : 'PiePHP' ?></title>
</head>
<body>
    <header>
        <nav>
            <a href="<?= BASE_URI ?>/">Accueil</a> |
            <a href="<?= BASE_URI ?>/user/register">Inscription</a> |
            <a href="<?= BASE_URI ?>/user/login">Connexion</a> |
            <?php /* Ajout (étape "Le Model") : le profil de l'utilisateur connecté. */ ?>
            <a href="<?= BASE_URI ?>/user/show">Profil</a>
        </nav>
    </header>

    <main>
        <?php /* Ici s'insère le contenu propre à chaque page. Pas de htmlspecialchars :
                 $view est du HTML que nous avons nous-mêmes produit. */ ?>
        <?= $view ?>
    </main>
</body>
</html>
