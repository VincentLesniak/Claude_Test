-- Table de la première entité "user" (étape 5 du sujet).
-- À importer dans MySQL : mysql -u root -p piephp < database.sql
-- Ce fichier n'est pas accessible depuis le navigateur grâce au .htaccess.
CREATE TABLE IF NOT EXISTS users (
    id       INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    email    VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL
);

-- ---------------------------------------------------------------------------
-- Ajout (partie « My cinema by PiePHP ») : les tables du mini-site.
--
-- Plan :
--   genres       un genre de film (Action, Comédie...)
--   films        un film, qui a UN genre        -> colonne genre_id
--   historiques  « tel utilisateur a vu tel film » -> colonnes user_id, film_id
--
-- Les noms suivent les conventions de Core\Entity et Core\ORM :
--   - table = nom du model en minuscules + "s" (FilmModel -> films,
--     HistoriqueModel -> historiques) ;
--   - clé étrangère = table liée au singulier + "_id" (genres -> genre_id).
-- C'est grâce à elles que les phrases des models ('has one genre',
-- 'has many films'...) suffisent : l'ORM devine les jointures tout seul.
--
-- L'ordre de création compte : une table qui contient une clé étrangère
-- (FOREIGN KEY) ne peut être créée qu'APRÈS la table qu'elle désigne.
-- Pour réimporter le fichier sur une base existante, rien ne casse :
-- "IF NOT EXISTS" saute les tables déjà présentes.
-- ---------------------------------------------------------------------------

-- Les genres. UNIQUE : deux genres ne peuvent pas avoir le même nom.
CREATE TABLE IF NOT EXISTS genres (
    id  INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL UNIQUE
);

-- Les films. Film 'has one' Genre : chaque film garde l'id de son genre.
--   - genre_id peut être NULL : un film peut ne pas (encore) avoir de genre ;
--   - annee est facultative (SMALLINT : un petit nombre entier suffit) ;
--   - resume est un TEXT : un résumé peut dépasser 255 caractères.
-- FOREIGN KEY ... REFERENCES : MySQL refuse un genre_id qui n'existe pas
-- dans genres, et refuse de supprimer un genre encore utilisé (le site
-- l'empêche aussi avant, avec un message clair : voir GenreController).
CREATE TABLE IF NOT EXISTS films (
    id       INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    titre    VARCHAR(255) NOT NULL,
    annee    SMALLINT UNSIGNED NULL,
    resume   TEXT NULL,
    genre_id INT UNSIGNED NULL,
    FOREIGN KEY (genre_id) REFERENCES genres (id)
);

-- L'historique : une ligne = un film vu par un utilisateur, à une date.
-- User 'has many historiques' et Historique 'has one film'.
-- Un même film peut être vu plusieurs fois (aucune contrainte UNIQUE).
-- ON DELETE CASCADE : si l'utilisateur ou le film disparaît, ses lignes
-- d'historique disparaissent avec lui. Le site les supprime déjà lui-même
-- (FilmModel::delete(), UserController::deleteAction()) : c'est une
-- sécurité de plus, au niveau de la base.
CREATE TABLE IF NOT EXISTS historiques (
    id      INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    film_id INT UNSIGNED NOT NULL,
    vu_le   DATETIME NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    FOREIGN KEY (film_id) REFERENCES films (id) ON DELETE CASCADE
);

-- Quelques genres et films pour ne pas démarrer avec un site vide.
-- INSERT IGNORE : si la ligne existe déjà (même nom de genre, UNIQUE),
-- MySQL la saute au lieu de s'arrêter sur une erreur.
INSERT IGNORE INTO genres (id, nom) VALUES
    (1, 'Science-fiction'),
    (2, 'Comédie'),
    (3, 'Animation');

INSERT IGNORE INTO films (id, titre, annee, resume, genre_id) VALUES
    (1, 'Le Voyage dans la Lune', 1902, 'Des astronomes partent explorer la Lune à bord d''un obus.', 1),
    (2, 'Les Temps modernes', 1936, 'Un ouvrier lutte pour survivre dans le monde industrialisé.', 2);
