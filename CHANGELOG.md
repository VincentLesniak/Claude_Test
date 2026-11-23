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

Étapes « Mise en pratique », « Le Model » et « Request » du sujet.

### Ajouté
- Classe `Core\Request` : récupère les paramètres GET et POST et les
  nettoie (`trim`, `stripslashes`, `htmlspecialchars`), y compris les
  sous-tableaux. Méthodes `isPost()`, `getQuery()`, `getPost()` et
  `getQueryParams()`.
- Classe `Core\Database` : une seule connexion PDO partagée, construite à
  partir de la section `database` de `config.json` (MySQL, ou SQLite pour
  les tests). Les vrais identifiants vont dans `config.local.json`, ignoré
  par Git et lu à la place de `config.json` s'il existe. Modèle à copier :
  `exemple.config.local.json`.
- `Model\UserModel` : attributs privés `email` et `password`, `save()`, et
  le CRUD `create()`, `read()`, `update()`, `delete()`, `readAll()`
  (`read_all` dans le sujet), plus `readByEmail()` et `checkCredentials()`.
  Requêtes préparées, liste blanche des colonnes et mots de passe hachés
  avec `password_hash()`.
- Inscription (`/user/register`, `/inscription`) : vérifie l'email, la
  longueur du mot de passe (8 caractères minimum) et refuse un email déjà
  inscrit.
- Connexion (`/user/login`) avec session, et déconnexion (`/user/logout`).
- Messages d'erreur et de réussite dans les vues `register` et `login`,
  lien « Profil » dans le menu.
- Tests de `Request`, `Database`, `UserModel` et de l'inscription / connexion
  (base SQLite en mémoire, `tests/TestDatabase.php`).

### Modifié
- `Core\Controller` crée un objet `Request` dans son constructeur : tous
  les controllers disposent de `$this->request`.
- `/user/show` affiche le profil de l'utilisateur connecté, lu en base avec
  `UserModel::read()` (le profil d'exemple reste affiché sinon).
- Une base de données injoignable affiche un message clair au lieu d'une
  erreur PHP.
- CI : `Core/Request.php` et `Core/Database.php` ajoutés aux fichiers
  requis ; extension `pdo_sqlite` pour les tests.

## [0.2.0] - 2026-11-23

Étapes 4 et 5 du sujet (front controller et routeur), puis étape « Le
Controller » (méthode `render()` et vues).

### Ajouté
- Méthode `Core\Controller::render($view, $scope)` : insère la vue
  `src/View/<Controller>/<vue>.php` dans le layout `src/View/index.php`
  (avec `ob_start()`), et affiche la page à la destruction du controller
  (`__destruct()`). Une vue introuvable lève une exception explicite.
- Layout commun `src/View/index.php` (titre de page, menu de navigation).
- Vues `src/View/App/index.php`, `src/View/User/index.php`,
  `src/View/User/register.php` et `src/View/User/show.php`.
- Actions `UserController::registerAction()`, `loginAction()` et
  `showAction()`.
- Tests de `render()` (`tests/ControllerTest.php`) et des pages rendues
  dans le layout.
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
- Les actions des controllers affichent leur vue avec `render()` au lieu
  d'un simple `echo`. `/user/add` affiche le formulaire d'inscription.
- La route `/inscription` pointe vers `UserController::registerAction()`.
- `src/View/User/login.php` ne contient plus qu'un morceau de page (le
  layout ajoute `<html>`, `<head>`...) et s'affiche via `/user/login` ; son
  formulaire envoie vers `/user/login` en utilisant `BASE_URI`.
- `tests/run.php` définit `BASE_URI`, utilisée par les vues.
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

[Non publié]: https://github.com/VincentLesniak/Claude_Test/compare/0.2.0...dev
[0.2.0]: https://github.com/VincentLesniak/Claude_Test/releases/tag/0.2.0
[0.1.0]: https://github.com/VincentLesniak/Claude_Test/releases/tag/0.1.0
