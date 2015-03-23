<?php

namespace voilab\tctable\plugin;

use voilab\tctable\TcTable;
use voilab\tctable\Plugin;

class FitColumn implements Plugin {

    /**
     * L'index de la colonne à étirer au maximum
     * @var string
     */
    private $columnIndex;

    /**
     * Constructeur du plugin
     *
     * @param string $columnIndex l'index de la colonne à étirer au maximum
     */
    public function __construct($columnIndex) {
        $this->columnIndex = $columnIndex;
    }

    /**
     * {@inheritDocs}
     */
    public function configure(TcTable $table) {
        $table->on(TcTable::EV_BODY_ADD, [$this, 'setWidth']);
    }

    /**
     * Détermine la largeur maximale de la colonne. Cette méthode est appelée
     * dès qu'on lance le processus de dessin des lignes de la table
     *
     * @param TcTable $table
     * @return void
     */
    public function setWidth(TcTable $table) {
        $widths = [];
        foreach ($table->getColumns() as $key => $column) {
            $widths[$key] = $column['width'];
        }
        unset($widths[$this->columnIndex]);
        $table->setColumnDefinition($this->columnIndex, 'width', $this->getRemainingColumnWidth($table, $widths));
    }

    /**
     * Récupère la largeur pour la cellule d'un tableau qui doit s'adapter
     * à la largeur de la page
     *
     * @param TcTable $table
     * @param array|float $width l'addition des largeurs des autres cellules
     * @return float
     */
    private function getRemainingColumnWidth(TcTable $table, $width) {
        $margins = $table->getPdf()->getMargins();
        $content_width = $table->getPdf()->getPageWidth() - $margins['left'] - $margins['right'];
        return $content_width - (is_array($width) ? array_sum($width) : $width);
    }

}
