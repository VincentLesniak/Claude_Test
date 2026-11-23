<?php

namespace Core;

/**
 * Request : les données envoyées par le visiteur, nettoyées.
 *
 * Sujet, partie "Request" : cette classe récupère les paramètres des requêtes
 * HTTP GET et POST et "sécurise ces inputs" (trim, stripslashes,
 * htmlspecialchars). Elle est instanciée dans le constructeur de
 * Core\Controller : chaque controller dispose donc de $this->request.
 *
 * RAPPEL : d'où viennent ces données ?
 *   - GET  : les paramètres de l'URL. /user/show?id=3 donne $_GET['id'] = '3'.
 *   - POST : les champs d'un formulaire method="post", envoyés dans le corps
 *            de la requête. name="email" donne $_POST['email'].
 * Le visiteur contrôle entièrement ces valeurs (il peut modifier le HTML du
 * formulaire, ou envoyer une requête à la main avec curl) : on ne leur fait
 * JAMAIS confiance. C'est pour ça qu'on passe toujours par cette classe au
 * lieu de lire $_GET / $_POST directement dans les controllers.
 *
 * Exemple dans une action :
 *   if ($this->request->isPost()) {
 *       $email = $this->request->getPost('email');
 *   }
 */
class Request
{
    /**
     * Les paramètres de l'URL ($_GET), déjà nettoyés.
     *
     * @var array
     */
    private $query;

    /**
     * Les champs du formulaire ($_POST), déjà nettoyés.
     *
     * @var array
     */
    private $post;

    /**
     * La méthode HTTP de la requête, en majuscules : 'GET', 'POST'...
     *
     * @var string
     */
    private $method;

    /**
     * On nettoie tout UNE fois, dès la création de l'objet : ensuite, les
     * controllers ne manipulent que des valeurs propres.
     */
    public function __construct()
    {
        $this->query = $this->clean($_GET);
        $this->post = $this->clean($_POST);

        // $_SERVER['REQUEST_METHOD'] n'existe pas quand PHP tourne en ligne de
        // commande (dans les tests par exemple) : "??" donne alors 'GET'.
        $this->method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    /**
     * La requête vient-elle d'un formulaire envoyé en POST ?
     *
     * Une même action sert souvent deux fois : en GET pour AFFICHER le
     * formulaire, puis en POST pour le TRAITER quand le visiteur le valide.
     */
    public function isPost(): bool
    {
        return $this->method === 'POST';
    }

    /**
     * Un paramètre de l'URL ($_GET), ou tous si on ne donne pas de nom.
     *
     * @param string|null $name    nom du paramètre, ex. 'id' pour ?id=3
     * @param mixed       $default valeur renvoyée si le paramètre est absent
     * @return mixed
     */
    public function getQuery(?string $name = null, $default = null)
    {
        if ($name === null) {
            return $this->query;
        }
        return $this->query[$name] ?? $default;
    }

    /**
     * Un champ du formulaire ($_POST), ou tous si on ne donne pas de nom.
     *
     * @param string|null $name    nom du champ, ex. 'email'
     * @param mixed       $default valeur renvoyée si le champ est absent
     * @return mixed
     */
    public function getPost(?string $name = null, $default = null)
    {
        if ($name === null) {
            return $this->post;
        }
        return $this->post[$name] ?? $default;
    }

    /**
     * Tous les paramètres de la requête, GET et POST réunis.
     *
     * C'est la méthode qu'utilise le sujet plus loin (partie "Les entités") :
     *   $params = $this->request->getQueryParams();
     *   $user = new UserModel($params);
     *
     * array_merge() : si un même nom existe dans l'URL ET dans le formulaire,
     * la valeur du formulaire (le 2e tableau) gagne.
     */
    public function getQueryParams(): array
    {
        return array_merge($this->query, $this->post);
    }

    /**
     * Nettoie un tableau de valeurs, case par case.
     *
     * Un tableau peut en contenir d'autres : un champ name="tags[]" donne
     * $_POST['tags'] = ['php', 'mvc']. On s'appelle alors soi-même sur le
     * sous-tableau (c'est une fonction "récursive").
     */
    private function clean(array $data): array
    {
        $cleaned = [];
        foreach ($data as $key => $value) {
            $cleaned[$key] = is_array($value) ? $this->clean($value) : $this->cleanValue((string) $value);
        }
        return $cleaned;
    }

    /**
     * Les trois nettoyages demandés par le sujet, dans l'ordre :
     *
     *   trim()             retire les espaces (et retours à la ligne) au début
     *                      et à la fin : "  a@b.c " devient "a@b.c".
     *   stripslashes()     retire les antislashs "\" ajoutés devant les
     *                      guillemets : l'\'exemple devient l'exemple. (Un
     *                      vieux réglage de PHP, magic_quotes, les ajoutait
     *                      tout seul ; il n'existe plus depuis PHP 5.4.)
     *   htmlspecialchars() transforme < > " ' & en entités HTML (&lt; ...).
     *                      Si un visiteur tape <script>, la valeur ne pourra
     *                      jamais être exécutée par un navigateur (faille XSS).
     *
     * ENT_QUOTES : échappe aussi l'apostrophe ' (pas seulement le guillemet ").
     *
     * À SAVOIR pour la soutenance : htmlspecialchars() protège l'AFFICHAGE
     * (HTML), pas la base de données. Contre l'injection SQL, ce sont les
     * requêtes préparées de UserModel qui protègent. Et comme la valeur est
     * modifiée, un mot de passe "a&b" devient "a&amp;b" : ce n'est pas grave
     * tant que l'inscription ET la connexion passent toutes les deux par
     * Request (le mot de passe est transformé de la même façon les deux fois).
     */
    private function cleanValue(string $value): string
    {
        return htmlspecialchars(stripslashes(trim($value)), ENT_QUOTES, 'UTF-8');
    }
}
