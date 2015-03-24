<?php

namespace voilab\tctable\plugin;

use voilab\tctable\TcTable;
use voilab\tctable\Plugin;

class FitColumn implements Plugin {

    /**
     * Stretched column index
     * @var string
     */
    private $columnIndex;

    /**
     * Calculated width at EV_BODY_ADD event
     * @var float
     */
    private $width;

    /**
     * Determines whether or not the width will be recalculated each time we
     * call  {@link TcTable::addBody()}
     * @var bool
     */
    private $memorizeWidth;

    /**
     * Plugin constructor
     *
     * @param string $columnIndex stretched column index
     * @param bool $memorizeWidth FALSE to recalculate width each time
     * {@link TcTable::addBody()} is called
     */
    public function __construct($columnIndex, $memorizeWidth = true) {
        $this->columnIndex = $columnIndex;
        $this->memorizeWidth = $memorizeWidth;
    }

    /**
     * {@inheritDocs}
     */
    public function configure(TcTable $table) {
        $table->on(TcTable::EV_BODY_ADD, [$this, 'setWidth']);
    }

    /**
     * Check the max width if the stretched column. This method is called just
     * before we start to add data rows
     *
     * @param TcTable $table
     * @return void
     */
    public function setWidth(TcTable $table) {
        if (!$this->width || !$this->memorizeWidth) {
            $widths = [];
            foreach ($table->getColumns() as $key => $column) {
                $widths[$key] = $column['width'];
            }
            unset($widths[$this->columnIndex]);
            $this->width = $this->getRemainingColumnWidth($table, $widths);
        }
        $width = $this->width;
        $table->setColumnDefinition($this->columnIndex, 'width', $width);
    }

    /**
     * Get the remaining width available, taking into account margins and
     * other cells width.
     *
     * @param TcTable $table
     * @param array|float $width sum of all other cells width
     * @return float
     */
    private function getRemainingColumnWidth(TcTable $table, $width) {
        $margins = $table->getPdf()->getMargins();
        $content_width = $table->getPdf()->getPageWidth() - $margins['left'] - $margins['right'];
        return $content_width - (is_array($width) ? array_sum($width) : $width);
    }

}
