# Journal des modifications (CHANGELOG)

Toutes les évolutions notables de PiePHP sont listées ici, de la plus
récente à la plus ancienne.

Le format suit [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/) et
les numéros de version suivent le [versionnage sémantique](https://semver.org/lang/fr/)
(MAJEUR.MINEUR.CORRECTIF).

Pendant le développement, chaque modification est d'abord notée dans la
section « Non publié ». Au moment d'une release, cette section est renommée
avec le numéro de version et la date (voir « Versionnage » dans le README).

## [Non publié]

Étapes 4 et 5 du sujet : front controller et routeur.

### Ajouté
- `.htaccess` actif : toutes les requêtes passent par `index.php`, sauf les
  fichiers de `webroot/` et `robots.txt`.
- Classe `Core\Router` (`connect()`, `get()`) et fichier `src/routes.php`
  pour déclarer des routes à la main.
- Routage automatique `/controller/action` (ou `?c=...&a=...`) dans
  `Core\Core::run()`, avec `app` / `index` par défaut.
- Page 404 (`src/View/Error/404.php`) avec le code HTTP 404 quand le
  controller ou l'action n'existe pas.
- Classe mère `Core\Controller`, controllers `AppController` et
  `UserController`, model `UserModel` et table `users` (`database.sql`).
- Tests du routeur (`tests/RouterTest.php`, `tests/CoreTest.php`).

### Modifié
- Les classes de `src/` ont un namespace (`Controller\`, `Model\`), exigé
  par PSR-12.
- La page d'accueil affiche `AppController::indexAction` au lieu de
  `Core\Core [OK]`.
- La page `src/View/User/login.php` n'est plus accessible directement
  (bloquée par le `.htaccess`, comme le demande le sujet).
- Le workflow de release accepte les tags `X.Y.Z` comme `vX.Y.Z`, complète
  une release déjà créée depuis l'interface GitHub et peut être lancé à la
  main pour un tag existant.

## [0.1.0] - 2026-11-23

Étapes 1 à 3 du sujet : le framework démarre.

### Ajouté
- Arborescence MVC : `Core/`, `src/{Controller,Model,View}`,
  `webroot/{assets,css,js}`.
- `robots.txt`, `.htaccess` (règles de réécriture préparées, encore
  commentées), `.gitignore`, `config.json`.
- Vue `src/View/User/login.php` : formulaire POST email / mot de passe.
- Classe `Core\Core` et sa méthode `run()`.
- Autoloader `Core/autoload.php` basé sur `spl_autoload_register`.
- Intégration continue : structure, lint, PSR-12 et tests automatiques
  (`php tests/run.php`).
- Versionnage : fichier `VERSION`, ce journal, workflow de release
  automatique sur les tags `vX.Y.Z`.

[Non publié]: https://github.com/VincentLesniak/Claude_Test/compare/0.1.0...dev
[0.1.0]: https://github.com/VincentLesniak/Claude_Test/releases/tag/0.1.0
