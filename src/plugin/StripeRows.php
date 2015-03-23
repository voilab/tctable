<?php

namespace voilab\tctable\plugin;

use voilab\tctable\TcTable;
use voilab\tctable\Plugin;

class StripeRows implements Plugin {

    /**
     * Détermine si on commence en fill ou pas
     * @var bool
     */
    private $startFill;

    /**
     * En mode "stripeRows", permet d'alterner le background des lignes
     * @var bool
     */
    private $rowCurrentStripe;

    /**
     * Constructeur du plugin. Permet de déterminer si on veut commencer la
     * 1ère ligne par du fill ou pas
     *
     * @param bool $startFill true pour commencer en fill
     */
    public function __construct($startFill) {
        $this->startFill = $startFill;
    }

    /**
     * {@inheritDocs}
     */
    public function configure(TcTable $table) {
        $table
            ->on(TcTable::EV_BODY_ADD, [$this, 'resetFill'])
            ->on(TcTable::EV_ROW_ADD, [$this, 'setFill']);
    }

    /**
     * Set le fill pour le lancement du dessin des lignes de la table. Utile
     * si on instancie une TcTable et qu'on appelle plusieurs fois le addBody
     * avec des données différentes.
     *
     * @return void
     */
    public function resetFill() {
        $this->rowCurrentStripe = !$this->startFill;
    }

    /**
     * Détermine la couleur de fond de la colonne, en fonction de l'alternance
     *
     * @param TcTable $table
     * @return void
     */
    public function setFill(TcTable $table) {
        $fill = $this->rowCurrentStripe = !$this->rowCurrentStripe;
        foreach ($table->getRowDefinition() as $column => $row) {
            $table->setRowDefinition($column, 'fill', $row['fill'] ?: $fill);
        }
        // ajustement du Y à cause du fill qui passe par-dessus le border de la
        // cellule précédente et qui le cache, du coup
        $y = 0.6 / $table->getPdf()->getScaleFactor();
        $table->getPdf()->SetY($table->getPdf()->GetY() + $y);
    }

}
