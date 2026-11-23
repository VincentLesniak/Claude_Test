<?php

namespace Tests\Model;

use Core\Entity;

/**
 * Model de test : un tag a plusieurs articles, et un article plusieurs
 * tags ('has many articles' ici, 'has many tags' dans ArticleModel) :
 * c'est une relation N : N, rangée dans la table pivot articles_tags.
 *
 * Voir ArticleModel pour le contexte.
 */
class TagModel extends Entity
{
    /**
     * @var string[]
     */
    private static $relations = ['has many articles'];
}
