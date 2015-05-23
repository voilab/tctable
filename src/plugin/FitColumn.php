<?php

namespace voilab\tctable\plugin;

use voilab\tctable\TcTable;
use voilab\tctable\Plugin;

class FitColumn extends Plugin {

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
     * Table max width. Default to page width minus margins
     * @var float
     */
    private $maxWidth;

    /**
     * Plugin constructor
     *
     * @param string $columnIndex stretched column index
     * @param float $maxWidth table max width. Default to page width minus
     * margins
     */
    public function __construct($columnIndex, $maxWidth = null) {
        $this->columnIndex = $columnIndex;
        $this->maxWidth = $maxWidth;
    }

    /**
     * {@inheritDocs}
     */
    protected function getEvents(TcTable $table) {
        return [
            TcTable::EV_BODY_ADD => [$this, 'setWidth']
        ];
    }

    /**
     * Reset width, so calculation will be re-executed
     *
     * @return FitColumn
     */
    public function resetWidth() {
        $this->width = null;
        return $this;
    }

    /**
     * Set table max width. Default to page width minus margins
     *
     * @param float $width
     * @return FitColumn
     */
    public function setMaxWidth($width) {
        $this->maxWidth = $width;
        return $this;
    }

    /**
     * Check the max width of the stretched column. This method is called just
     * before we start to add data rows
     *
     * @param TcTable $table
     * @return void
     */
    public function setWidth(TcTable $table) {
        if (!$this->width) {
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
     * other cells width or the specified maxwidth if any.
     *
     * @param TcTable $table
     * @param array|float $width sum of all other cells width
     * @return float
     */
    private function getRemainingColumnWidth(TcTable $table, $width) {
        if (!$this->maxWidth) {
            $margins = $table->getPdf()->getMargins();
            $content_width = $table->getPdf()->getPageWidth() - $margins['left'] - $margins['right'];
        } else {
            $content_width = $this->maxWidth;
        }
        $result = $content_width - (is_array($width) ? array_sum($width) : $width);
        return $result > 0 ? $result : 0;
    }

}
