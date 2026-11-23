-- Table de la première entité "user" (étape 5 du sujet).
-- À importer dans MySQL : mysql -u root -p piephp < database.sql
-- Ce fichier n'est pas accessible depuis le navigateur grâce au .htaccess.
CREATE TABLE IF NOT EXISTS users (
    id       INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    email    VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL
);
