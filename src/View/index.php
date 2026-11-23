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
 *
 * Mise à jour (partie « My cinema by PiePHP ») : render() donne aussi au
 * layout deux variables (voir Core\Controller::render()) :
 *   $currentUserId : l'id du membre connecté, ou null. Le menu affiche
 *                    « Profil » et « Déconnexion » à un membre, et
 *                    « Inscription » et « Connexion » à un visiteur ;
 *   $flash         : le message laissé par l'action précédente avant une
 *                    redirection (['type' => 'success', 'message' => ...]),
 *                    ou null. Il est affiché au-dessus de chaque page.
 * Le menu gagne aussi les pages du mini-site : les films (page d'accueil)
 * et les genres.
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
            <?php /* Mise à jour (partie « My cinema ») : l'accueil affiche la liste des films. */ ?>
            <a href="<?= BASE_URI ?>/">Films</a> |
            <a href="<?= BASE_URI ?>/genre">Genres</a> |
            <?php /* Mise à jour (partie « My cinema ») : les liens dépendent de la
                     connexion. empty() est vrai pour null : visiteur non connecté. */ ?>
            <?php if (empty($currentUserId)) : ?>
                <a href="<?= BASE_URI ?>/user/register">Inscription</a> |
                <a href="<?= BASE_URI ?>/user/login">Connexion</a>
            <?php else : ?>
                <?php /* Ajout (étape "Le Model") : le profil de l'utilisateur connecté. */ ?>
                <a href="<?= BASE_URI ?>/user/show">Profil</a> |
                <a href="<?= BASE_URI ?>/user/logout">Déconnexion</a>
            <?php endif; ?>
        </nav>
    </header>

    <main>
        <?php /* Ajout (partie « My cinema ») : le message flash de l'action précédente.
                 Le type ne peut valoir que 'success' ou 'error' (écrit par nos
                 controllers), le message est échappé comme toute donnée affichée. */ ?>
        <?php if (!empty($flash)) : ?>
            <p class="<?= $flash['type'] === 'error' ? 'error' : 'success' ?>"
               role="<?= $flash['type'] === 'error' ? 'alert' : 'status' ?>">
                <?= htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8', false) ?>
            </p>
        <?php endif; ?>
        <?php /* Ici s'insère le contenu propre à chaque page. Pas de htmlspecialchars :
                 $view est du HTML que nous avons nous-mêmes produit. */ ?>
        <?= $view ?>
    </main>
</body>
</html>
