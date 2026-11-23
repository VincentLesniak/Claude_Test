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

## Configuration (`config.json`)

Les paramètres de connexion à la base de données sont dans `config.json`
(le futur `Core/Database.php` le lira avec `json_decode`). Le format JSON
n'accepte pas les commentaires, d'où cette explication ici :
`host`/`port` désignent le serveur MySQL, `dbname` la base, `user` et
`password` le compte utilisé. Adapte ces valeurs à ta machine.

Mise à jour (étape « Le Model ») : `Core/Database.php` existe maintenant et
lit ce fichier. Pour que l'inscription et la connexion fonctionnent :

1. crée la base : `mysql -u root -p -e "CREATE DATABASE piephp CHARACTER SET utf8mb4"`
2. crée la table `users` : `mysql -u root -p piephp < database.sql`
3. copie `exemple.config.local.json` en `config.local.json`
   (`cp exemple.config.local.json config.local.json`) et remplace
   `TON_UTILISATEUR_MYSQL` et `TON_MOT_DE_PASSE_MYSQL` par ton `user` et ton
   `password`. `config.local.json` est ignoré par Git (`.gitignore`) : tes
   vrais identifiants ne partent jamais sur GitHub. `config.json`, lui, est
   commité et ne contient que des valeurs d'exemple.

Si la base est injoignable, les pages d'inscription et de connexion
affichent « La base de données est indisponible » au lieu d'une erreur PHP.
Les tests, eux, n'ont pas besoin de MySQL : ils utilisent une base SQLite en
mémoire (`tests/TestDatabase.php`).

## L'ORM (`Core/ORM.php`)

Ajout (étape « Avancé - l'ORM ») : les models n'écrivent plus de SQL. Ils
appellent `Core\ORM`, qui fabrique la requête et l'exécute :

```php
$orm = new ORM();
$id = $orm->create('articles', ['titre' => 'Un titre', 'author' => 'Rodrigue']);
$orm->read('articles', $id);                        // la ligne, ou null
$orm->update('articles', $id, ['titre' => 'Autre']); // true / false
$orm->find('articles', ['WHERE' => ['author' => 'Rodrigue'], 'ORDER BY' => 'id DESC', 'LIMIT' => 10]);
$orm->delete('articles', $id);                      // true / false
```

Pour `find()`, préfère un `WHERE` en tableau (`['email' => $email]`) : la
valeur est préparée, donc sans danger même si elle vient d'un formulaire. Un
`WHERE` en chaîne (`'id > 3'`) est collé tel quel dans la requête : à
réserver aux conditions écrites par toi, jamais à une saisie de visiteur.

## Les entités (`Core/Entity.php`)

Ajout (étape « Les entités ») : chaque model hérite de `Core\Entity`. Un
objet model représente UNE ligne de sa table, chaque colonne devient un
attribut public, et le CRUD n'est plus à réécrire dans chaque model :

```php
$user = new UserModel(['email' => 'a@b.c', 'password' => 'secret']);
$user->save();                       // INSERT, $user->id est rempli

$user = new UserModel(['id' => 3]);  // lit la ligne n°3
$user->email = 'nouveau@b.c';
$user->save();                       // UPDATE des colonnes modifiées
$user->delete();                     // supprime la ligne n°3
```

La table est déduite du nom du model (`UserModel` -> `users`,
`ArticleModel` -> `articles`). Pour un autre nom :
`protected static $table = 'people';`.

Attention : ne donne pas tout `getQueryParams()` à une entité sans
réfléchir. Un `?id=3` ajouté à l'URL ferait charger (et `save()` modifier)
la ligne n°3. Passe seulement les champs attendus.

## Les relations entre models

Ajout (étape « Avancé - Les relations de model ») : un model décrit ses
liens avec d'autres models dans un attribut **privé statique** `$relations`,
avec les phrases du sujet. Exemple du blog :

```php
class ArticleModel extends Entity
{
    private static $relations = ['has many comments', 'has many tags'];
}
class CommentModel extends Entity
{
    private static $relations = ['has one article'];
}
class TagModel extends Entity
{
    private static $relations = ['has many articles'];
}
```

`read()`, `find()`, `readAll()` et le constructeur avec un id renvoient
alors aussi les enregistrements liés :

```php
$article = new ArticleModel(['id' => 1]);
$article->comments;  // liste des commentaires de l'article
$article->tags;      // liste de ses tags
(new CommentModel(['id' => 5]))->article;  // UNE ligne, ou null
```

Les tables doivent suivre ces conventions :

| Relation | Phrase | Ce que la base doit contenir |
| --- | --- | --- |
| 1 : N | `ArticleModel` : `'has many comments'` | une colonne `comments.article_id` |
| N : 1 | `CommentModel` : `'has one article'` | la même colonne `comments.article_id` |
| N : N | `'has many tags'` ET `TagModel` : `'has many articles'` | une table pivot `articles_tags` (`article_id`, `tag_id`) |

La table pivot porte le nom des deux tables, dans l'ordre alphabétique,
séparées par `_`. Une clé étrangère porte le nom de la table au singulier
(sans le `s` final) suivi de `_id`.

Les relations ne sont chargées que sur un niveau (les commentaires d'un
article ne contiennent pas à leur tour leur article), et ne sont jamais
enregistrées par `save()`.

## Le moteur de templates (`Core/TemplateEngine.php`)

Ajout (étape « Avancé - le moteur de templates ») : `render()` passe chaque
vue (et le layout) dans un petit moteur inspiré de Blade, qui remplace des
tags courts par du PHP classique avant d'exécuter la vue :

| Dans la vue | PHP produit |
| --- | --- |
| `{{ $title }}` | `<?= htmlentities((string) ($title), ENT_QUOTES, 'UTF-8', false) ?>` |
| `@if (cond)` / `@elseif (cond)` / `@else` / `@endif` | `<?php if (cond) : ?>` ... `<?php endif; ?>` |
| `@foreach ($users as $user)` / `@endforeach` | `<?php foreach ($users as $user) : ?>` ... `<?php endforeach; ?>` |
| `@isset($x)` / `@endisset` | `<?php if (isset($x)) : ?>` ... `<?php endif; ?>` |
| `@empty($x)` / `@endempty` | `<?php if (empty($x)) : ?>` ... `<?php endif; ?>` |

- `{{ }}` échappe **toujours** le HTML (protection XSS), sans ré-échapper ce
  que `Request` a déjà échappé.
- Une vue en PHP pur ressort inchangée : les anciennes vues fonctionnent.
- Un `@` collé à un mot (`contact@if.fr`) n'est pas une directive.
- Les vues compilées sont gardées en cache dans le dossier temporaire du
  système (`piephp-views/`) et recompilées seulement quand la vue change.

Exemple : `src/View/User/show.php`.

## Tests

Depuis la racine du projet : `php tests/run.php`

Le lanceur exécute automatiquement chaque fichier `tests/*Test.php`. Pour
tester une nouvelle fonctionnalité, crée un fichier `tests/XxxTest.php` qui
renvoie un tableau `"description du test" => function () { ... }` (voir les
fichiers existants), il sera pris en compte sans rien modifier d'autre.

## Intégration continue (`.github/workflows/piephp-strict-ci.yml`)

À chaque push et pull request, GitHub Actions lance quatre jobs sur des
machines fournies par GitHub (`ubuntu-latest`, gratuit pour un dépôt public,
2000 minutes par mois en privé) :

- **File Structure Verification** : les fichiers requis par le sujet sont
  présents. La liste suit l'avancement : les fichiers des étapes suivantes
  sont en commentaire dans le workflow, à décommenter quand ils sont codés.
- **PHP Linting** : `php -l` sur chaque fichier PHP (erreurs de syntaxe).
- **PSR-12 Validation** : style de code vérifié par PHP_CodeSniffer sur
  `Core/`, `src/`, `tests/` et `index.php` (les vues de `src/View/` sont
  ignorées). En local : `phpcs --standard=PSR12 --ignore=src/View/,webroot/ Core/ src/ tests/ index.php`
- **Tests** : `php tests/run.php`.

## Versionnage

### Numéro de version : `VERSION` et versionnage sémantique

La version courante est dans le fichier `VERSION` (une seule ligne, ex.
`0.1.0`). Un fichier texte tout simple, c'est la "source de vérité" lisible
aussi bien par PHP que par les scripts du CI.

Le numéro suit le [versionnage sémantique](https://semver.org/lang/fr/),
MAJEUR.MINEUR.CORRECTIF :

- **CORRECTIF** (0.1.0 -> 0.1.1) : correction de bug, rien ne change pour
  l'utilisateur du framework.
- **MINEUR** (0.1.0 -> 0.2.0) : nouvelle fonctionnalité compatible avec
  l'existant.
- **MAJEUR** (0.x -> 1.0.0) : changement qui casse la compatibilité.

Tant qu'on est en `0.x`, le framework est "en construction" : on monte la
version MINEURE à chaque étape du sujet terminée (0.1.0 = étapes 1 à 3,
0.2.0 = sécurité, routeur et controller, 0.3.0 = model et request,
0.4.0 = ORM, 0.5.0 = entités, 0.6.0 = relations...). La
**1.0.0** correspondra au sujet complet,
le jour du rendu.

### Journal : `CHANGELOG.md`

Chaque PR ajoute une ligne dans la section « Non publié » du CHANGELOG
(Ajouté / Modifié / Corrigé / Supprimé). Le Git log dit *comment* le code a
changé, commit par commit ; le CHANGELOG dit *ce qui a changé* pour un
humain, version par version. Un test (`tests/VersionTest.php`) vérifie que
la version de `VERSION` a bien sa section dans le CHANGELOG.

### Branches

- `main` : uniquement les versions publiées, envoyées directement depuis
  `dev` (sans PR). Chaque version porte un tag `X.Y.Z`. C'est ce qu'on rend ou ce qu'on montre en soutenance.
  Mise à jour (version 0.2.0) : les versions passent désormais par une pull
  request `dev` -> `main`, voir « Publier une version » ci-dessous.
- `dev` : la branche d'intégration. Tout le travail en cours y est
  fusionné, via des pull requests validées par le CI.
- une branche par fonctionnalité (ex. `feature/routeur`), créée depuis
  `dev` et fusionnée dans `dev` par pull request.

Pourquoi séparer `main` et `dev` ? Pour avoir toujours une version stable
sous la main, même si `dev` est cassé en plein milieu d'une étape.

### Publier une version (release)

1. Sur `dev`, renommer « Non publié » en `[X.Y.Z] - AAAA-MM-JJ` dans le
   CHANGELOG, recréer une section « Non publié » vide au-dessus, et écrire
   `X.Y.Z` dans `VERSION`.
2. Envoyer la version directement sur `main`, sans pull request : le code
   a déjà été relu et validé par le CI dans les PR vers `dev`.
   `git checkout main && git merge --ff-only dev && git push origin main`
   (`--ff-only` refuse la fusion si `main` contient un commit absent de
   `dev` : `main` reste ainsi une simple copie des versions de `dev`.)
   Mise à jour (version 0.2.0) : on passe maintenant par une pull request
   `dev` -> `main`. Le CI s'exécute une dernière fois sur la version
   complète et la PR garde une trace de chaque publication. Une fois la PR
   fusionnée, on passe à l'étape 3 (le tag) sur `main`.
3. Sur `main`, créer et pousser le tag (avec ou sans `v` devant) :
   `git tag -a X.Y.Z -m "Version X.Y.Z" && git push origin X.Y.Z`,
   ou créer la release depuis l'interface GitHub (Releases > Draft a new
   release), qui crée le tag au passage.
4. Le workflow `.github/workflows/release.yml` vérifie que le tag
   correspond à `VERSION`, relance les tests et publie la release GitHub
   (ou complète celle qui existe) avec les notes du CHANGELOG. Il peut
   aussi être lancé à la main depuis l'onglet Actions pour un tag existant.

Un tag *annoté* (`-a`) enregistre l'auteur, la date et un message, comme un
commit : c'est la forme recommandée pour marquer une version.
