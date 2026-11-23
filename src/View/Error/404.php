<?php
/*
 * Vue affichée par Core::run() quand le controller ou l'action demandés
 * n'existent pas. C'est une vue : uniquement de l'affichage, aucune logique.
 * Le code HTTP 404 est envoyé par le Core avant d'inclure ce fichier.
 */
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>404 - Page introuvable</title>
</head>
<body>
    <h1>404</h1>
    <p>La page demandée n'existe pas.</p>
</body>
</html>
