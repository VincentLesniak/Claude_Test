<?php

namespace Tests\Model;

use Core\Entity;

/**
 * Model de test : un commentaire appartient à UN article
 * ('has one article', la table comments contient article_id).
 *
 * Voir ArticleModel pour le contexte.
 */
class CommentModel extends Entity
{
    /**
     * @var string[]
     */
    private static $relations = ['has one article'];
}
