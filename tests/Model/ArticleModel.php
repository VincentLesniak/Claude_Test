<?php

namespace Tests\Model;

use Core\Entity;

/**
 * Model de test : l'article du blog pris en exemple par le sujet
 * (« Avancé - Les relations de model »).
 *
 * Il n'existe que pour les tests (tests/RelationTest.php), d'où son
 * namespace Tests\Model : l'application, elle, n'a pas d'articles.
 *
 *   - 'has many comments' : un article a plusieurs commentaires, la table
 *     comments contient article_id (1 : N) ;
 *   - 'has many tags' : TagModel dit aussi 'has many articles', c'est donc
 *     un N : N, rangé dans la table pivot articles_tags.
 */
class ArticleModel extends Entity
{
    /**
     * Les relations du model, écrites comme dans le sujet. "private
     * static" : seul ce model (et Entity, grâce à la Reflection) les lit.
     *
     * @var string[]
     */
    private static $relations = ['has many comments', 'has many tags'];
}
