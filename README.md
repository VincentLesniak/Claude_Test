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
