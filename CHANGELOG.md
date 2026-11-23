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

Étape « Avancé - Les relations de model » du sujet.

### Ajouté
- Relations entre models : chaque model peut déclarer un attribut privé
  statique `$relations` avec les phrases du sujet (`'has many comments'`,
  `'has one article'`). `'has many'` des deux côtés (`ArticleModel` :
  `'has many tags'`, `TagModel` : `'has many articles'`) donne une relation
  many to many, rangée dans la table pivot `articles_tags`.
- `Entity::getRelations()` traduit ces phrases pour l'ORM (type, table,
  table pivot). Une phrase incomprise lève une `InvalidArgumentException`.
- `ORM::read()` et `ORM::find()` prennent un 3e paramètre facultatif
  `$relations` : chaque ligne reçoit une entrée par relation (une liste pour
  `has many` et `many to many`, une ligne ou `null` pour `has one`), lue avec
  un `INNER JOIN`. Noms de tables vérifiés comme le reste de l'ORM.
- `ORM::singular()` (`articles` -> `article`), pour les clés étrangères.
- Tests des relations (`tests/RelationTest.php`) avec trois models de test
  (`tests/Model/`).

### Modifié
- `Entity::read()`, `find()`, `readAll()` et le constructeur avec un id
  renvoient aussi les enregistrements liés (`$article->comments`). `save()`
  n'envoie jamais les relations en base.

## [0.5.0] - 2026-11-23

Étape « Les entités » du sujet.

### Ajouté
- Classe abstraite `Core\Entity`, mère de tous les models. Son constructeur
  prend un tableau associatif : soit les attributs du model, soit
  `['id' => ...]`, auquel cas la ligne est lue avec l'ORM et remplace le
  tableau. Chaque clé devient un attribut public (`$user->email`) ; `$id`
  vaut `null` tant que l'entité n'est pas en base. Noms d'attributs vérifiés
  (les attributs internes ne peuvent pas être écrasés).
- `save()` : `INSERT` pour une nouvelle entité (remplit `$id`), `UPDATE` des
  seules colonnes modifiées sinon. `delete()` sans argument supprime
  l'entité elle-même.
- CRUD commun hérité par les models : `create()`, `read()`, `update()`,
  `delete()`, `readAll()` et `find()`, sans redonner le nom de la table.
- `getTable()` déduit la table du nom du model (`UserModel` -> `users`),
  modifiable avec `protected static $table`. Crochets `columns()` et
  `prepareFields()` pour les règles propres à un model.
- Tests des entités (`tests/EntityTest.php`).

### Modifié
- `Model\UserModel` étend `Core\Entity` : son CRUD est hérité, il garde sa
  liste blanche, le hachage des mots de passe, `readByEmail()` et
  `checkCredentials()`. `email` et `password` deviennent des attributs
  publics. Le constructeur prend le tableau d'attributs en 1er paramètre :
  `new UserModel(['email' => ...], $pdo)`.
- Un utilisateur relu en base puis enregistré n'a plus son mot de passe
  haché une deuxième fois.
- L'inscription et la connexion créent le `UserModel` à partir d'un
  tableau, comme dans l'exemple du sujet.
- Job « File Structure Verification » de la CI : `Core/Entity.php` est requis.

## [0.4.0] - 2026-11-23

Étape « Avancé - l'ORM » du sujet.

### Ajouté
- Classe `Core\ORM` : un mini-ORM qui écrit le SQL à la place des models,
  avec les méthodes du sujet `create()` (renvoie l'id), `read()` (la ligne,
  ou `null`), `update()` et `delete()` (renvoient un booléen) et `find()`
  (paramètres `WHERE`, `ORDER BY` et `LIMIT`, valeurs par défaut du sujet).
  Valeurs toujours préparées ; noms de table et de colonne, `ORDER BY` et
  `LIMIT` vérifiés pour bloquer l'injection SQL. `WHERE` accepte un tableau
  `colonne => valeur` (préparé, recommandé) ou une chaîne SQL comme dans le
  sujet.
- Tests de l'ORM (`tests/ORMTest.php`) sur une table `articles`.

### Modifié
- `Model\UserModel` n'écrit plus de SQL : son CRUD, `readAll()` et
  `readByEmail()` passent par `Core\ORM`. Le comportement ne change pas
  (liste blanche des colonnes et hachage des mots de passe gardés).
- Job « File Structure Verification » de la CI : `Core/ORM.php` est requis.

## [0.3.0] - 2026-11-23

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

[Non publié]: https://github.com/VincentLesniak/Claude_Test/compare/0.5.0...dev
[0.5.0]: https://github.com/VincentLesniak/Claude_Test/releases/tag/0.5.0
[0.4.0]: https://github.com/VincentLesniak/Claude_Test/releases/tag/0.4.0
[0.3.0]: https://github.com/VincentLesniak/Claude_Test/releases/tag/0.3.0
[0.2.0]: https://github.com/VincentLesniak/Claude_Test/releases/tag/0.2.0
[0.1.0]: https://github.com/VincentLesniak/Claude_Test/releases/tag/0.1.0
