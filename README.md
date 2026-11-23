# PiePHP

Un framework MVC "fait maison", réalisé pas à pas en suivant le sujet PiePHP.

## Arborescence

```
PiePHP/
├── index.php        Point d'entrée unique de l'application (front controller)
├── .htaccess        Configuration Apache (réécriture d'URL, sécurité)
├── robots.txt       Consignes pour les robots d'indexation
├── Core/            Le cœur du framework (code réutilisable, indépendant du site)
├── src/             Le code propre à TON application
│   ├── Controller/  Les contrôleurs : la logique, ils font le lien Model <-> View
│   ├── Model/       Les modèles : l'accès à la base de données
│   └── View/        Les vues : uniquement de l'affichage (HTML)
└── webroot/         Les seuls fichiers publics : images, CSS, JS
    ├── assets/
    ├── css/
    └── js/
```

## Installation (LAMP sur Ubuntu)

Place le dossier dans `/var/www/html/PiePHP`, puis ouvre
http://localhost/PiePHP/ dans le navigateur.

Sans Apache, tu peux aussi tester avec le serveur intégré de PHP, depuis le
dossier parent du projet : `php -S localhost:8000` puis
http://localhost:8000/PiePHP/ (attention : le serveur intégré ignore le
`.htaccess`).
