<?php

namespace voilab\tctable\plugin;

use voilab\tctable\TcTable;
use voilab\tctable\Plugin;

class Widows implements Plugin {

    /**
     * Minimum rows number to have on the last page
     * @var int
     */
    private $minWidowsOnPage;

    /**
     * Height we need to display all widows on the same page
     * @var flat
     */
    private $height;

    /**
     * Number of rows in the complete dataset
     * @var int
     */
    private $count;

    /**
     * The complete dataset
     * @var array
     */
    private $rows;

    /**
     * The bottom page break trigger
     * @var float
     */
    private $pageBreakTrigger;

    /**
     * Table footer height
     * @var float
     */
    private $footerHeight = 0;

    /**
     * When addBody is called, we calculate widows height so we can check if
     * it's possible to draw them all on the current page (if not, we add a new
     * page). To avoid to calculate the height twice, 1 time here and 1 time
     * when parsing the row, we save heights here so we can reuse them.
     * @var array
     */
    private $_widowsCalculatedHeight = [];

    /**
     * Set min widows on page
     *
     * @param int $minWidowsOnPage the minimum number of rows we want on the
     * last page. Default to 0 = no check
     * @param float Table footer height
     */
    public function __construct($minWidowsOnPage, $footerHeight = 0) {
        $this->minWidowsOnPage = (int) $minWidowsOnPage;
        $this->footerHeight = $footerHeight;
    }

    /**
     * {@inheritDocs}
     */
    public function configure(TcTable $table) {
        $table
            ->on(TcTable::EV_BODY_ADD, [$this, 'initialize'])
            ->on(TcTable::EV_ROW_ADD, [$this, 'checkAvailableHeight'])
            ->on(TcTable::EV_ROW_SKIPPED, [$this, 'onRowSkipped'])
            ->on(TcTable::EV_ROW_HEIGHT_GET_COPY, [$this, 'onRowHeightGetCopy'])
            ->on(TcTable::EV_BODY_ADDED, [$this, 'purge']);
    }

    /**
     * Set table footer height. Used to adapt widows on last page, in case the
     * footer is alone on the last page and it's not what we want.
     *
     * @param float $height
     * @return TcTable
     */
    public function setFooterHeight($height) {
        $this->footerHeight = $height;
        return $this;
    }

    /**
     * Called before body is added. Configure everything about widows
     *
     * @param TcTable $table
     * @param array $rows
     * @param callable $fn
     * @return void
     */
    public function initialize(TcTable $table, array $rows, callable $fn = null) {
        $this->_widowsCalculatedHeight = [];
        $this->height = $this->getCalculatedWidowsHeight($table, $rows, $fn);
        $this->count = count($rows);
        $this->rows = $rows;
        $this->pageBreakTrigger = $table->getPdF()->getPageHeight() - $table->getPdf()->getBreakMargin() - $this->footerHeight;
    }

    /**
     * Called after body is added. Purge all references
     *
     * @return void
     */
    public function purge() {
        $this->rows = null;
    }

    /**
     * Called when a row is skipped, and so not displayed in the pdf
     *
     * @return void
     */
    public function onRowSkipped() {
        // adapt total rows, so the widow management behave the best it can
        $this->count--;
    }

    /**
     * Called when TcTable copy default column definition inside the current
     * row definition. We set an action here, so we can use the widow's cache
     * height, instead of calculating it a second time
     *
     * @param TcTable $table
     * @param array $row
     * @param int $rowIndex
     * @return float
     */
    public function onRowHeightGetCopy(TcTable $table, array $row = null, $rowIndex = null) {
        // if current row index is one of the already-calculated widows height,
        // we take this value, instead of calculating it a second time.
        if ($rowIndex !== null && isset($this->_widowsCalculatedHeight[$rowIndex])) {
            return $this->_widowsCalculatedHeight[$rowIndex];
        }
    }

    /**
     * Called before adding a row. Check if it's possible, taking widows into
     * account. If not, a page add event is triggered
     *
     * @param TcTable $table
     * @param array $row
     * @param int $rowIndex
     * @return void
     */
    public function checkAvailableHeight(TcTable $table, array $row, $rowIndex) {
        $table->stashRowDefinition();
        $pdf = $table->getPdf();
        // if the remaining widows can't be drawn on this current page, we
        // need to force to add a new page.
        if ($rowIndex + $this->minWidowsOnPage >= $this->count && $pdf->GetY() + $this->height >= $this->pageBreakTrigger) {
            if ($table->trigger(TcTable::EV_PAGE_ADD, [$this->rows, $rowIndex, true]) !== false) {
                $pdf->AddPage();
                $table->trigger(TcTable::EV_PAGE_ADDED, [$this->rows, $rowIndex, true]);
            }
            // reset row definition, because in the event, plugins may have
            // chosen to draw headers, so the row definition will have changed.
            $table->applyRowDefinition();
        }
    }

    /**
     * Get real height that widows will take. Used to force a page break if the
     * remaining height isn't enough to draw all the widows on the current page.
     *
     * @param TcTable $table
     * @param array $rows the complete set of data
     * @param callable $fn addBody function for data layout
     * @return float
     */
    private function getCalculatedWidowsHeight(TcTable $table, $rows, callable $fn = null) {
        $count = count($rows);
        $limit = $count - $this->minWidowsOnPage;
        $h = 0;
        if ($this->minWidowsOnPage && $count && $limit >= 0) {
            for ($i = $count - 1; $i >= $limit; $i--) {
                // the userfunc has returned false everytime, so in the end, the
                // data array is empty. Return current height.
                if (!isset($rows[$i])) {
                    return $h;
                }
                $data = $fn ? $fn($table, $rows[$i], true) : $rows[$i];
                // check row only if it's an array. It gives the possibility to
                // skip some rows with the user func
                if (is_array($data)) {
                    $this->_widowsCalculatedHeight[$i] = $table->getCurrentRowHeight($data);
                    $h += $this->_widowsCalculatedHeight[$i];
                } else {
                    // adapt limit so the widow management behave the best it
                    // can
                    $limit--;
                }
            }
        }
        return $h;
    }

}
