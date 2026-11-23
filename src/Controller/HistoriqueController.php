<?php

namespace Controller;

use Core\Controller;
use Model\FilmModel;
use Model\HistoriqueModel;
use PDOException;
use RuntimeException;

/**
 * Controller de l'entité « Historique » (partie « My cinema by PiePHP »).
 *
 * Le sujet demande deux fonctionnalités :
 *   - ajouter un film vu dans la table historique :
 *       POST /historique/add?film_id=3   (bouton « J'ai vu ce film »)
 *   - supprimer un film vu de la table historique :
 *       POST /historique/delete?id=7     (bouton sur la page du profil)
 * L'historique lui-même s'affiche sur le profil (UserController::showAction()).
 *
 * Ce controller n'a AUCUNE vue : ses deux actions modifient la base puis
 * redirigent (avec un message flash) vers la page d'où vient le visiteur.
 *
 * PLAN DU FICHIER
 *   - addAction(), deleteAction() ;
 *   - allowed() : les vérifications communes (connecté, en POST).
 */
class HistoriqueController extends Controller
{
    /**
     * POST /historique/add?film_id=3 : ajoute le film à l'historique du
     * membre connecté, à la date d'aujourd'hui.
     *
     * L'id de l'utilisateur vient de la SESSION (currentUserId()), jamais du
     * formulaire : sinon, n'importe qui pourrait remplir l'historique d'un
     * autre membre en changeant un champ caché.
     *
     * date('Y-m-d H:i:s') : la date et l'heure actuelles au format attendu
     * par une colonne DATETIME, ex. "2026-11-23 14:05:00".
     *
     * Pourquoi ? Demandé par le sujet (« Ajouter un film vu dans la table
     * historique »).
     * Pertinence : on vérifie que le film existe avant d'écrire, pour ne
     * jamais créer de ligne qui pointe vers un film inconnu.
     */
    public function addAction()
    {
        if (!$this->allowed()) {
            return;
        }

        $filmId = $this->queryId('film_id');
        try {
            $film = $filmId === null ? null : new FilmModel(['id' => $filmId]);
            if ($film === null || $film->id === null) {
                $this->flash('error', "Ce film n'existe pas.");
                $this->redirect('/');
                return;
            }

            $historique = new HistoriqueModel([
                'user_id' => $this->currentUserId(),
                'film_id' => $film->id,
                'vu_le' => date('Y-m-d H:i:s'),
            ]);
            $historique->save();
            $this->flash('success', '« ' . $film->titre . ' » a été ajouté à votre historique.');
            $this->redirect('/film/show?id=' . $film->id);
        } catch (PDOException | RuntimeException $e) {
            $this->flash('error', $this->databaseError());
            $this->redirect('/');
        }
    }

    /**
     * POST /historique/delete?id=7 : retire une ligne de l'historique.
     *
     * On vérifie que la ligne appartient bien au membre connecté : l'id est
     * dans l'URL, donc n'importe qui peut en essayer un autre. Pour une
     * ligne d'un autre membre, on répond « introuvable », comme pour une
     * ligne qui n'existe pas (on ne révèle rien sur les autres comptes).
     *
     * Pourquoi ? Demandé par le sujet (« Supprimer un film vu de la table
     * historique »).
     * Pertinence : sans la vérification du propriétaire, un membre pourrait
     * vider l'historique de tous les autres en essayant les id un par un.
     */
    public function deleteAction()
    {
        if (!$this->allowed()) {
            return;
        }

        $id = $this->queryId();
        try {
            $historique = $id === null ? null : new HistoriqueModel(['id' => $id]);
            // (int) : selon la base, user_id peut être lu en texte ("1").
            // PSR-12 : une condition sur plusieurs lignes commence à la ligne
            // suivante, et ") {" se retrouve seul sur sa ligne.
            if (
                $historique === null
                || $historique->id === null
                || (int) $historique->user_id !== $this->currentUserId()
            ) {
                $this->flash('error', "Cette ligne d'historique n'existe pas.");
            } else {
                $historique->delete();
                $this->flash('success', 'Le film a été retiré de votre historique.');
            }
        } catch (PDOException | RuntimeException $e) {
            $this->flash('error', $this->databaseError());
        }
        $this->redirect('/user/show');
    }

    /**
     * Les deux actions demandent un membre connecté ET une requête POST.
     *
     * Renvoie false (avec la redirection déjà prête) si ce n'est pas le cas.
     * Une visite en GET (un lien suivi par erreur, un robot) ne modifie
     * donc jamais la base.
     *
     * Pourquoi ? Les deux actions commencent par les mêmes vérifications.
     * Pertinence : elles sont écrites une fois, dans le bon ordre (d'abord
     * la connexion, puis la méthode HTTP).
     */
    private function allowed(): bool
    {
        if (!$this->requireLogin()) {
            return false;
        }
        if (!$this->request->isPost()) {
            $this->redirect('/user/show');
            return false;
        }
        return true;
    }
}
