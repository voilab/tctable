<?php

namespace voilab\tctable\plugin;

use voilab\tctable\TcTable;
use voilab\tctable\Plugin;

class StripeRows implements Plugin {

    /**
     * Determine whether we start with a filled row or not
     * @var bool
     */
    private $startFill;

    /**
     * The current fill mode
     * @var bool
     */
    private $rowCurrentStripe;

    /**
     * Plugin constructor
     *
     * @param bool $startFill true to start with a filled row
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
     * Set fill just before we add all rows. When we instanciate the TcTable
     * and call addBody many times, it ensures that the first row starts
     * always the same.
     *
     * @return void
     */
    public function resetFill() {
        $this->rowCurrentStripe = !$this->startFill;
    }

    /**
     * Set the background of the row
     *
     * @param TcTable $table
     * @return void
     */
    public function setFill(TcTable $table) {
        $fill = $this->rowCurrentStripe = !$this->rowCurrentStripe;
        foreach ($table->getRowDefinition() as $column => $row) {
            $table->setRowDefinition($column, 'fill', $row['fill'] ?: $fill);
        }
        $this->moveY($table);
    }

    /**
     * Adjust Y because cell background passes over the previous cell's border,
     * hiding it.
     *
     * @param TcTable $table
     */
    public function moveY(TcTable $table) {
        $y = 0.6 / $table->getPdf()->getScaleFactor();
        $table->getPdf()->SetY($table->getPdf()->GetY() + $y);
    }

}
