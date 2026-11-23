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

_Rien pour l'instant._

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

[Non publié]: https://github.com/VincentLesniak/Claude_Test/compare/v0.1.0...dev
[0.1.0]: https://github.com/VincentLesniak/Claude_Test/releases/tag/v0.1.0
