<?php

namespace voilab\tctable;

/**
 * This class can quickly and easily draw tables with an advanced management
 * of page breaks, row height calculation and plugins integration.
 *
 * One of the more intensive process is in {@link getCurrentRowHeight()} which
 * call the method {@link \TCPDF::getNumLines()}. The other one is obviously
 * {@link addCell()} which draw cells. Everything else is only setters and
 * getters, and small foreach.
 *
 * From an optimization point of view, {@link \TCPDF::getNumLines()} is called
 * 1 time for each multiline cell of each row, but not for headers. Columns
 * foreach is used in this cases:
 * <ul>
 *     <li>1 time when drawing headers</li>
 *     <li>2 time when drawing a row (to find max height and to draw it)</li>
 *     <li>1 time to find FitColumn width (plugin)</li>
 *     <li>1 time per row to find the background color with StripeRows
 *     (plugin)</li>
 *     <li>Widow rows are parsed twice (1 time for height calculation, and
 *     1 time as a normal row, to draw its cells)</li>
 * </ul>
 * User methods (renderer and body function) are called twice:
 * <ul>
 *     <li>Renderer is called during height calculation</li>
 *     <li>Renderer is called also during cell drawing</li>
 *     <li>Body function is called during widows height calculation</li>
 *     <li>Body function is also called during row drawing</li>
 * </ul>
 */
class TcTable {

    /**
     * Event: before a row is added
     * <ul>
     *     <li><i>TcTable</i> <b>$table</b> TcTable behind the event</li>
     *     <li><i>array</i> <b>$row</b> row data</li>
     *     <li><i>int</i> <b>$rowIndex</b> row index</li>
     * </ul>
     * @return void|bool Return FALSE to stop event chain and to not draw the
     * row
     */
    const EV_ROW_ADD = 1;

    /**
     * Event: after a row is added
     * <ul>
     *     <li><i>TcTable</i> <b>$table</b> TcTable behind the event</li>
     *     <li><i>array</i> <b>$row</b> row data</li>
     *     <li><i>int</i> <b>$rowIndex</b> row index</li>
     * </ul>
     * @return void|bool Return FALSE to stop event chain
     */
    const EV_ROW_ADDED = 2;

    /**
     * Event: before cell height is calculated
     * <ul>
     *     <li><i>TcTable</i> <b>$table</b> TcTable behind the event</li>
     *     <li><i>string</i> <b>$column</b> column string index</li>
     *     <li><i>mixed</i> <b>$data</b> the cell data</li>
     *     <li><i>array</i> <b>$row</b> row data</li>
     * </ul>
     * @return mixed the new data which will be used to calculate cell height
     * Stop event chain if value is not null.
     */
    const EV_ROW_HEIGHT_GET = 3;

    /**
     * Event: before headers are added
     * <ul>
     *     <li><i>TcTable</i> <b>$table</b> TcTable behind the event</li>
     * </ul>
     * @return void|bool Return FALSE to stop event chain and not draw headers
     */
    const EV_HEADER_ADD = 4;

    /**
     * Event: after headers are added
     * <ul>
     *     <li><i>TcTable</i> <b>$table</b> TcTable behind the event</li>
     * </ul>
     * @return void|bool Return FALSE to stop event chain
     */
    const EV_HEADER_ADDED = 5;

    /**
     * Event: after a column definition is added
     * <ul>
     *     <li><i>TcTable</i> <b>$table</b> TcTable behind the event</li>
     *     <li><i>string</i> <b>$column</b> column index</li>
     *     <li><i>array</i> <b>$definition</b> column definition</li>
     * </ul>
     * @return void|bool Return FALSE to stop event chain
     */
    const EV_COLUMN_ADDED = 6;

    /**
     * Event: before a cell is added
     * <ul>
     *     <li><i>TcTable</i> <b>$table</b> TcTable behind the event</li>
     *     <li><i>string</i> <b>$column</b> column index</li>
     *     <li><i>mixed</i> <b>$data</b> cell data</li>
     *     <li><i>array</i> <b>$definition</b> the row definition (that can
     *     be changed)</li>
     *     <li><i>array</i> <b>$row</b> row data</li>
     *     <li><i>bool</i> <b>$header</b> true if it's a header cell</li>
     * </ul>
     * @return mixed the data to set in the cell. Stop event chain if value is
     * not null
     */
    const EV_CELL_ADD = 7;

    /**
     * Event: after a cell is added
     * <ul>
     *     <li><i>TcTable</i> <b>$table</b> TcTable behind the event</li>
     *     <li><i>string</i> <b>$column</b> column index</li>
     *     <li><i>mixed</i> <b>$data</b> cell data</li>
     *     <li><i>array</i> <b>$definition</b> the row definition (that can
     *     be changed)</li>
     *     <li><i>array</i> <b>$row</b> row data</li>
     *     <li><i>bool</i> <b>$header</b> true if it's a header cell</li>
     * </ul>
     * @return void|bool Return FALSE to stop event chain
     */
    const EV_CELL_ADDED = 8;

    /**
     * Event: before a page break is added
     * <ul>
     *     <li><i>TcTable</i> <b>$table</b> TcTable behind the event</li>
     *     <li><i>array</i> <b>$row</b> row data if $widow = FALSE, the complete
     *     set of rows if $widow = TRUE</li>
     *     <li><i>int</i> <b>$rowIndex</b> row index</li>
     *     <li><i>bool</i> <b>$widow</b> TRUE if page is added because widows
     *     can't be drawn on the page, FALSE if it's only the current row that
     *     can't be drawn because of bottom margin</li>
     * </ul>
     * @return void|bool Return FALSE to stop event chain and not add the page
     */
    const EV_PAGE_ADD = 9;

    /**
     * Event: après qu'un saut de page soit ajouté
     * <ul>
     *     <li><i>TcTable</i> <b>$table</b> TcTable behind the event</li>
     *     <li><i>array</i> <b>$row</b> row data if $widow = FALSE, the complete
     *     set of rows if $widow = TRUE</li>
     *     <li><i>int</i> <b>$rowIndex</b> row index</li>
     *     <li><i>bool</i> <b>$widow</b> TRUE if page is added because widows
     *     can't be drawn on the page, FALSE if it's only the current row that
     *     can't be drawn because of bottom margin</li>
     * </ul>
     * @return void|bool Return FALSE to stop event chain
     */
    const EV_PAGE_ADDED = 10;

    /**
     * Event: before data rows are added
     * <ul>
     *     <li><i>TcTable</i> <b>$table</b> TcTable behind the event</li>
     *     <li><i>array</i> <b>$rows</b> complete set of data rows</li>
     * </ul>
     * @return void|bool Return FALSE to stop event chain and not draw any row
     */
    const EV_BODY_ADD = 11;

    /**
     * Event: after data rows are added
     * <ul>
     *     <li><i>TcTable</i> <b>$table</b> TcTable behind the event</li>
     *     <li><i>array</i> <b>$rows</b> complete set of data rows</li>
     * </ul>
     * @return void|bool Return FALSE to stop event chain
     */
    const EV_BODY_ADDED = 12;

    /**
     * The TCPDF instance
     * @var \TCPDF
     */
    private $pdf;

    /**
     * Default columns definitions
     * @var array
     */
    private $defaultColumnDefinition = [];

    /**
     * Columns definitions
     * @var array
     */
    private $columnDefinition = [];

    /**
     * When a row is drawn, we copy the column definition in this property, so
     * we can modifiy it for this row only, without affecting the default
     * configuration
     * @var array
     */
    private $rowDefinition = [];

    /**
     * Events list available when trigger is called
     * @var array
     */
    private $events = [];

    /**
     * Plugins attached to this table
     * @var Plugin[]
     */
    private $plugins = [];

    /**
     * Check if we want to show headers when {@link addBody()}
     * @var bool
     */
    private $showHeader = true;

    /**
     * Min height for the rows
     * @var float
     */
    private $columnHeight;

    /**
     * Table footer height
     * @var float
     */
    private $footerHeight = 0;

    /**
     * The calculated height for the current row
     * @var float
     */
    private $rowHeight;

    /**
     * Minimum rows number to have on the last page
     * @var int
     */
    private $minWidowsOnPage;

    /**
     * When addBody is called, we calculate widows height so we can check if
     * it's possible to draw them all on the current page (if not, we add a new
     * page). To avoid to calculate the height twice, 1 time here and 1 time
     * when parsing the row, we save heights here so we can reuse them.
     * @var array
     */
    private $_widowsCalculatedHeight = [];

    /**
     * Constructor. Get the TCPDF instance as first argument. We MUST define
     * {@link \TCPDF::SetAutoPageBreak()} with a bottom margin (2cm for
     * example) if we want consistent page breaks
     *
     * @param \TCPDF $pdf
     * @param float $minColumnHeight min height for each content row
     * @param int $minWidowsOnPage the minimum number of rows we want on the
     * last page. 0 = no check
     */
    public function __construct(\TCPDF $pdf, $minColumnHeight, $minWidowsOnPage) {
        $this->pdf = $pdf;
        $this->columnHeight = $minColumnHeight;
        $this->minWidowsOnPage = $minWidowsOnPage;
    }

    /**
     * Add a plugin
     *
     * @param Plugin $plugin the instanciated plugin
     * @param string $key a key to quickly find the plugin with getPlugin()
     * @return TcTable
     */
    public function addPlugin(Plugin $plugin, $key = null) {
        if ($key) {
            $this->plugins[$key] = $plugin;
        } else {
            $this->plugins[] = $plugin;
        }
        $plugin->configure($this);
        return $this;
    }

    /**
     * Get a plugin
     *
     * @param mixed $key plugin index (0, 1, 2, etc) or string
     * @return Plugin
     */
    public function getPlugin($key) {
        return isset($this->plugins[$key]) ? $this->plugins[$key] : null;
    }

    /**
     * Set an action to do on the specified event
     *
     * @param int $event event code
     * @param callable $fn function to call when the event is triggered
     * @return TcTable
     */
    public function on($event, callable $fn) {
        if (!isset($this->events[$event])) {
            $this->events[$event] = [];
        }
        $this->events[$event][] = $fn;
        return $this;
    }

    /**
     * Browse registered actions for this event
     *
     * @param int $event event code
     * @param array $args arra of arguments to pass to the event function
     * @param bool $acceptReturn TRUE so a non-null function return can be used
     * by TcTable
     * @return mixed desired content or null
     */
    public function trigger($event, array $args = [], $acceptReturn = false) {
        if (isset($this->events[$event])) {
            array_unshift($args, $this);
            foreach ($this->events[$event] as $fn) {
                $data = call_user_func_array($fn, $args);
                if ($acceptReturn && $data !== null) {
                    return $data;
                } elseif ($data === false) {
                    return false;
                }
            }
        }
    }

    /**
     * Get pdf used by this TcTable
     *
     * @return \TCPDF
     */
    public function getPdf() {
        return $this->pdf;
    }

    /**
     * Define a column. Config array is mostly what we find in \TCPDF Cell,
     * MultiCell and Image.
     *
     * Frequently used Cell and MultiCell options:
     * <ul>
     *     <li><i>callable</i> <b>renderer</b>: renderer function for datas.
     *     Recieve (TcTable $table, $data, array $columns, $height). The last
     *     parameter is TRUE when called during height calculation. This method
     *     is called twice, one time for cell height calculation and one time
     *     for data drawing.</li>
     *     <li><i>string</i> <b>header</b>: column header text</li>
     *     <li><i>float</i> <b>width</b>: column width</li>
     *     <li><i>string</i> <b>border</b>: cell border (LTBR)</li>
     *     <li><i>string</i> <b>align</b>: text horizontal align (LCR)</li>
     *     <li><i>string</i> <b>valign</b>: text vertical align (TCB)</li>
     * </ul>
     *
     * MultiCell options:
     * <ul>
     *     <li><i>bool</i> <b>isMultiLine</b>: true tell this is a multiline
     *     column</li>
     *     <li><i>bool</i> <b>isHtml</b>: true to tell that this is HTML
     *     content</li>
     * </ul>
     *
     * Image options (experimental)
     * <ul>
     *     <li><i>bool</i> <b>isImage</b>: indique si cette cellule est une
     *     image</li>
     *     <li><i>string</i> <b>type</b>: JPEG or PNG</li>
     *     <li><i>bool</i> <b>resize</b>: see doc {@link \TCPDF::Image}</li>
     *     <li><i>int</i> <b>dpi</b>: see doc {@link \TCPDF::Image}</li>
     *     <li><i>string</i> <b>palign</b>: see doc {@link \TCPDF::Image}</li>
     *     <li><i>bool</i> <b>isMask</b>: see doc {@link \TCPDF::Image}</li>
     *     <li><i>mixed</i> <b>imgMask</b>: see doc {@link \TCPDF::Image}</li>
     *     <li><i>bool</i> <b>hidden</b>: see doc {@link \TCPDF::Image}</li>
     *     <li><i>bool</i> <b>fitOnPage</b>: see doc {@link \TCPDF::Image}</li>
     *     <li><i>bool</i> <b>alt</b>: see doc {@link \TCPDF::Image}</li>
     *     <li><i>array</i> <b>altImgs</b>: see doc {@link \TCPDF::Image}</li>
     * </ul>
     *
     * All other options available:
     * <ul>
     *     <li><i>float</i> <b>height</b>: min height for cell (par défaut
     *     {@link setColumnHeight()}</li>
     *     <li><i>bool</i> <b>ln</b>: managed by TcTable. This option is
     *     ignored.</li>
     *     <li><i>bool</i> <b>fill</b>: see doc {@link \TCPDF::Cell}</li>
     *     <li><i>string</i> <b>link</b>: see doc {@link \TCPDF::Cell}</li>
     *     <li><i>int</i> <b>stretch</b>: see doc {@link \TCPDF::Cell}</li>
     *     <li><i>bool</i> <b>ignoreHeight</b>: see doc {@link \TCPDF::Cell}</li>
     *     <li><i>string</i> <b>calign</b>: see doc {@link \TCPDF::Cell}</li>
     *     <li><i>mixed</i> <b>x</b>: see doc {@link \TCPDF::MultiCell}</li>
     *     <li><i>mixed</i> <b>y</b>: see doc {@link \TCPDF::MultiCell}</li>
     *     <li><i>bool</i> <b>reseth</b>: see doc {@link \TCPDF::MultiCell}</li>
     *     <li><i>float</i> <b>maxh</b>: see doc {@link \TCPDF::MultiCell}</li>
     *     <li><i>bool</i> <b>autoPadding</b>: see doc {@link \TCPDF::MultiCell}</li>
     *     <li><i>bool</i> <b>fitcell</b>: see doc {@link \TCPDF::MultiCell}</li>
     *     <li><i>string</i> <b>cellPadding</b>: see doc {@link \TCPDF::getNumLines}</li>
     * </ul>
     *
     * @param string $column
     * @param array $definition
     * @return TcTable
     */
    public function addColumn($column, array $definition) {
        // set new column with default definitions. Note that [ln] is always
        // set to FALSE. When addBody(), TRUE will be setted for the last
        // column.
        $this->columnDefinition[$column] = array_merge([
            'isMultiLine' => false,
            'isImage' => false,
            'renderer' => null,
            'header' => '',
            // cell
            'width' => 10,
            'height' => $this->getColumnHeight(),
            'border' => 0,
            'ln' => false,
            'align' => 'L',
            'fill' => false,
            'link' => '',
            'stretch' => 0,
            'ignoreHeight' => false,
            'calign' => 'T',
            'valign' => 'M',
            // multiCell
            'x' => '',
            'y' => '',
            'reseth' => true,
            'isHtml' => false,
            'maxh' => null,
            'autoPadding' => true,
            'fitcell' => false,
            // images
            'type' => '',
            'resize' => false,
            'dpi' => 300,
            'palign' => '',
            'isMask' => false,
            'imgMask' => false,
            'hidden' => false,
            'fitOnPage' => false,
            'alt' => false,
            'altImgs' => [],
            // getNumLines
            'cellPadding' => ''
        ], $this->defaultColumnDefinition, $definition);

        $this->trigger(self::EV_COLUMN_ADDED, [$column, $this->columnDefinition[$column]]);
        return $this;
    }

    /**
     * Add many columns in one shot
     *
     * @see addColumn
     * @param array $columns
     * @return \mangetasoupe\pdf\TcTable
     */
    public function setColumns(array $columns) {
        foreach ($columns as $key => $def) {
            $this->addColumn($key, $def);
        }
        return $this;
    }

    /**
     * Set a specific definition for a column, like the value of border, calign
     * and so on
     *
     * @param string $column column string index
     * @param string $definition definition name (border, calign, etc.)
     * @param mixed $value definition value ('LTBR', 'T', etc.)
     * @return TcTable
     */
    public function setColumnDefinition($column, $definition, $value) {
        $this->columnDefinition[$column][$definition] = $value;
        return $this;
    }

    /**
     * Set default column definitions
     *
     * @param array $definition
     * @return TcTable
     */
    public function setDefaultColumnDefinition(array $definition) {
        $this->defaultColumnDefinition = $definition;
        return $this;
    }

    /**
     * Get all columns definition
     *
     * @return array
     */
    public function getColumns() {
        return $this->columnDefinition;
    }

    /**
     * Get column width
     *
     * @param string $column
     * @return float
     */
    public function getColumnWidth($column) {
        return $this->getColumn($column)['width'];
    }

    /**
     * Get a definition for a column. The returned array is the one used when
     * drawing a Cell, MultiCell or Image
     *
     * @param string $column
     * @return array
     */
    public function getColumn($column) {
        return $this->columnDefinition[$column];
    }

    /**
     * Set min height used for each cell (headers and contents)
     *
     * @param float $height
     * @return TcTable
     */
    public function setColumnHeight($height) {
        $this->columnHeight = $height;
        return $this;
    }

    /**
     * Get min height for each cell (headers and contents)
     *
     * @return float
     */
    public function getColumnHeight() {
        return $this->columnHeight;
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
     * Check if we want to draw headers when calling {@link addBody()}.
     *
     * @param bool $show true to show headers
     * @return TcTable
     */
    public function setShowHeader($show) {
        $this->showHeader = $show;
        return $this;
    }

    /**
     * Get width from start to the given column. Given width's column is not
     * included in the sum.
     *
     * Example: $table->getColumnWidthUntil('D');
     * <pre>
     * | A | B | C | D | E |
     * |-> | ->| ->|   |   |
     * </pre>
     *
     * @param string $column column to stop sum
     * @return float
     */
    public function getColumnWidthUntil($column) {
        return $this->getColumnWidthBetween('', $column);
    }

    /**
     * Get width between two columns. Widths of these columns are included in
     * the sum.
     *
     * Example: $table->getColumnWidthBetween('B', 'D');
     * <pre>
     * | A | B | C | D | E |
     * |   |-> | ->| ->|   |
     * </pre>
     *
     * If column A is empty, behave like {@link TcTable::getColumnWidthUntil()}.
     * If column B is empty, behave like {@link TcTable::getColumnWidthFrom()}.
     *
     * @param string $columnA start column
     * @param string $columnB last column
     * @return float
     */
    public function getColumnWidthBetween($columnA, $columnB) {
        $width = 0;
        $check = false;
        foreach ($this->columnDefinition as $key => $def) {
            // begin sum either from start, or from column A
            if ($key == $columnA || !$columnA) {
                $check = true;
            }
            // stop sum if we want width from start to column B
            if (!$columnA && $key == $columnB) {
                break;
            }
            if ($check) {
                $width += $def['width'];
            }
            if ($key == $columnB) {
                break;
            }
        }
        return $width;
    }

    /**
     * Get width from a column to the end of the table. Given column's width is
     * added to the sum.
     *
     * Example: $table->getColumnWidthFrom('D');
     * <pre>
     * | A | B | C | D | E |
     * |   |   |   |-> | ->|
     * </pre>
     *
     * @param string $column the column from which we want to find the width
     * @return float
     */
    public function getColumnWidthFrom($column) {
        return $this->getColumnWidthBetween($column, '');
    }

    /**
     * Get table width (sum of all columns)
     *
     * @return float
     */
    public function getWidth() {
        return $this->getColumnWidthBetween('', '');
    }

    /**
     * Set the height of the current row, that will be used for all its cells
     *
     * @param float $height
     * @return TcTable
     */
    public function setRowHeight($height) {
        $this->rowHeight = $height;
        return $this;
    }

    /**
     * Get the real height for current row draw. Will be used to draw each
     * cell in this row
     *
     * @return float
     */
    public function getRowHeight() {
        return $this->rowHeight;
    }

    /**
     * Get custom configuration for the current row
     *
     * @return array
     */
    public function getRowDefinition() {
        return $this->rowDefinition;
    }

    /**
     * Set a custom configuration for one specific cell in the current row.
     *
     * @param string $column column string index
     * @param string $definition specific configuration (border, etc.)
     * @param mixed $value value ('LBR' for border, etc.)
     * @return TcTable
     */
    public function setRowDefinition($column, $definition, $value) {
        $this->rowDefinition[$column][$definition] = $value;
        return $this;
    }

    /**
     * Set custom configuration for the current row (used mainly in plugins)
     *
     * @param array $definition definition for current row
     * @return TcTable
     */
    public function setRows(array $definition) {
        $this->rowDefinition = $definition;
        return $this;
    }

    /**
     * Add table headers
     *
     * @return TcTable
     */
    public function addHeader() {
        $this->copyDefaultColumnDefinitions(null);
        if ($this->trigger(self::EV_HEADER_ADD) !== false) {
            foreach ($this->columnDefinition as $key => $def) {
                $this->addCell($key, $def['header'], $this->columnDefinition, true);
            }
            $this->trigger(self::EV_HEADER_ADDED);
        }
        return $this;
    }

    /**
     * Add content to the table. This method manage widows, page break and
     * loads of other things. It launches events at start and end, if we need
     * to add some custom stuff.
     *
     * The callable function structure is as follow:
     * <ul>
     *     <li><i>TcTable</i> <b>$table</b> the TcTable object</li>
     *     <li><i>array</i> <b>$row</b> current row</li>
     *     <li><i>bool</i> <b>$widow</b> TRUE if this method is called when
     *     parsing widows</li>
     * </ul>
     * <ul>
     *     <li>Return <i>array</i> formatted data, where keys are the one
     *     configured in the column definition</li>
     * </ul>
     *
     * @param array $rows the complete set of data
     * @param callable $fn data layout function
     * @return TcTable
     */
    public function addBody(array $rows, callable $fn = null) {
        // last column will have TRUE for the TCPDF [ln] property
        end($this->columnDefinition);
        $this->columnDefinition[key($this->columnDefinition)]['ln'] = true;

        $auto_pb = $this->pdf->getAutoPageBreak();
        $margins = $this->pdf->getMargins();
        $this->pdf->SetAutoPageBreak(false, $margins['bottom']);
        if ($this->trigger(self::EV_BODY_ADD, [$rows]) === false) {
            return $this;
        }
        if ($this->showHeader) {
            $this->addHeader();
        }
        $page_break_trigger = $this->pdf->getPageHeight() - $this->pdf->getBreakMargin() - $this->footerHeight;
        $count = count($rows);
        $h = $this->getCalculatedWidowsHeight($rows, $fn);
        foreach ($rows as $index => $row) {
            // if the remaining widows can't be drawn on this current page, we
            // need to force to add a new page.
            if ($index + $this->minWidowsOnPage >= $count && $this->pdf->GetY() + $h >= $page_break_trigger) {
                if ($this->trigger(self::EV_PAGE_ADD, [$rows, $index, true]) !== false) {
                    $this->pdf->AddPage();
                    $this->trigger(self::EV_PAGE_ADDED, [$rows, $index, true]);
                }
            }
            $this->addRow($fn ? $fn($this, $row, false) : $row, $index);
        }
        $this->trigger(self::EV_BODY_ADDED, [$rows]);
        $this->_widowsCalculatedHeight = [];
        $this->pdf->SetAutoPageBreak($auto_pb, $margins['bottom']);
        return $this;
    }

    /**
     * Add a row
     *
     * @param array $row row data
     * @param int $index row index
     * @return TcTable
     */
    private function addRow(array $row, $index = null) {
        $this->copyDefaultColumnDefinitions($row, $index);
        if ($this->trigger(self::EV_ROW_ADD, [$row, $index]) === false) {
            return $this;
        }
        $row_height = $this->getRowHeight();
        $row_definition = $this->rowDefinition;

        $h = current($row_definition)['height'];
        $page_break_trigger = $this->pdf->getPageHeight() - $this->pdf->getBreakMargin();
        if ($this->pdf->GetY() + $h >= $page_break_trigger) {
            if ($this->trigger(self::EV_PAGE_ADD, [$row, $index, false]) !== false) {
                $this->pdf->AddPage();
                $this->trigger(self::EV_PAGE_ADDED, [$row, $index, false]);
            }
            // reset row definition, because in the event, plugins may have
            // chosen to draw headers, so the row definition will have changed.
            $this->setRowHeight($row_height);
            $this->rowDefinition = $row_definition;
        }

        foreach ($this->columnDefinition as $key => $value) {
            $this->addCell($key, isset($row[$key]) ? $row[$key] : '', $row);
        }
        $this->trigger(self::EV_ROW_ADDED, [$row, $index]);
        return $this;
    }

    /**
     * Get real height that widows will take. Used to force a page break if the
     * remaining height isn't enough to draw all the widows on the current page.
     *
     * @param array $rows the complete set of data
     * @param callable $fn addBody function for data layout
     * @return float
     */
    private function getCalculatedWidowsHeight($rows, callable $fn = null) {
        $count = count($rows);
        $limit = $count - $this->minWidowsOnPage;
        $h = 0;
        if ($this->minWidowsOnPage && $count && $limit >= 0) {
            for ($i = $count - 1; $i >= $limit; $i--) {
                $this->_widowsCalculatedHeight[$i] = $this->getCurrentRowHeight($fn ? $fn($this, $rows[$i], true) : $rows[$i]);
                $h += $this->_widowsCalculatedHeight[$i];
            }
        }
        return $h;
    }

    /**
     * Copy column definition inside a new property. It allows us to customize
     * it only for this row. For the next row, column definition will again be
     * the default one.
     *
     * Usefull for plugins that need to temporarily, for one precise row, to
     * change column information (like background color, border, etc)
     *
     * @param array $columns row datas (for each cell)
     * @param int $rowIndex row index
     * @return void
     */
    private function copyDefaultColumnDefinitions(array $columns = null, $rowIndex = null) {
        $this->rowDefinition = $this->columnDefinition;
        // if current row index is one of the already-calculated widows height,
        // we take this value, instead of calculating it a second time.
        $h = $rowIndex !== null && isset($this->_widowsCalculatedHeight[$rowIndex])
            ? $this->_widowsCalculatedHeight[$rowIndex]
            : ($columns !== null ? $this->getCurrentRowHeight($columns) : $this->getColumnHeight());

        $this->setRowHeight($h);
    }

    /**
     * Browse all cells for this row to find which content has the max height.
     * Then we can adapt the height of all the other cells of this line.
     *
     * @param array $row
     * @return float
     */
    private function getCurrentRowHeight(array $row) {
        // get the max height for this row
        $h = $this->getColumnHeight();
        $this->setRowHeight($h);
        foreach ($this->columnDefinition as $key => $def) {
            if (!isset($row[$key]) || !$def['isMultiLine']) {
                continue;
            }
            $data = $row[$key];
            if (is_callable($def['renderer'])) {
                $data = $def['renderer']($this, $data, $row, true);
            }
            $plugin_data = $this->trigger(self::EV_ROW_HEIGHT_GET, [$key, $data, $row], true);
            if ($plugin_data !== null) {
                $data = $plugin_data;
            }
            // getNumLines doesn't care about HTML. To simulate carriage return,
            // we replace <br> with \n. Any better idea? Transactions?
            $data_to_check = strip_tags(str_replace(['<br>', '<br/>', '<br />'], PHP_EOL, $data));
            $nb = $this->pdf->getNumLines($data_to_check, $def['width'],
                $def['reseth'], $def['autoPadding'],
                $def['cellPadding'], $def['border']);

            $hd = $nb * $h;
            if ($hd > $this->getRowHeight()) {
                $this->setRowHeight($hd);
            }
        }
        return $this->getRowHeight();
    }

    /**
     * Draw a cell
     *
     * @param string $column column string index
     * @param mixed $data data to draw inside the cell
     * @param array $row all datas for this line
     * @param bool $header true if we draw header cell
     * @return TcTable
     */
    private function addCell($column, $data, array $row, $header = false) {
        if (!isset($this->rowDefinition[$column])) {
            return;
        }
        $c = $this->rowDefinition[$column];
        if (!$header && is_callable($c['renderer'])) {
            $data = $c['renderer']($this, $data, $row, false);
        }
        $plugin_data = $this->trigger(self::EV_CELL_ADD, [$column, $data, $c, $row, $header], true);
        if ($plugin_data !== null) {
            $data = $plugin_data;
        }
        $h = $this->getRowHeight();
        if ($c['isMultiLine']) {
            // for multicell, if maxh = null, set it to cell's height, so
            // vertical alignment can work
            $this->pdf->MultiCell($c['width'], $h, $data, $c['border'],
                $c['align'], $c['fill'], $c['ln'], $c['x'], $c['y'], $c['reseth'],
                $c['stretch'], $c['isHtml'], $c['autoPadding'], $c['maxh'] === null ? $h : $c['maxh'],
                $c['valign'], $c['fitcell']);
        } elseif ($c['isImage']) {
            $this->pdf->Image($data, $this->pdf->GetX() + $c['x'],
                $this->pdf->GetY() + $c['y'], $c['width'], $h,
                $c['type'], $c['link'], $c['align'], $c['resize'], $c['dpi'],
                $c['palign'], $c['isMask'], $c['imgMask'], $c['border'],
                $c['fitcell'], $c['hidden'], $c['fitOnPage'], $c['alt'],
                $c['altImgs']);
            $this->pdf->SetX($this->GetX() + $c['width']);
        } else {
            $this->pdf->Cell($c['width'], $h, $data, $c['border'],
                $c['ln'], $c['align'], $c['fill'], $c['link'], $c['stretch'],
                $c['ignoreHeight'], $c['calign'], $c['valign']);
        }
        $this->trigger(self::EV_CELL_ADDED, [$column, $c, $data, $row, $header]);
        return $this;
    }

}
