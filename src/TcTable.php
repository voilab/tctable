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
     *     <li><i>array|object</i> <b>$row</b> row data</li>
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
     *     <li><i>array|object</i> <b>$row</b> row data</li>
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
     *     <li><i>array|object</i> <b>$row</b> row data</li>
     * </ul>
     * @return void|float the cell's height. Stop event chain if not null.
     */
    const EV_CELL_HEIGHT_GET = 3;

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
     *     <li><i>array|object</i> <b>$row</b> row data</li>
     *     <li><i>bool</i> <b>$header</b> true if it's a header cell</li>
     * </ul>
     * @return void|bool Return FALSE to stop event chain and set the displayed
     * data to an empty string
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
     *     <li><i>array|object</i> <b>$row</b> row data</li>
     *     <li><i>bool</i> <b>$header</b> true if it's a header cell</li>
     * </ul>
     * @return void|bool Return FALSE to stop event chain
     */
    const EV_CELL_ADDED = 8;

    /**
     * Event: before a page break is added
     * <ul>
     *     <li><i>TcTable</i> <b>$table</b> TcTable behind the event</li>
     *     <li><i>array|object|Traversable</i> <b>$row</b> row data if $widow =
     *     FALSE, the complete set of rows if $widow = TRUE</li>
     *     <li><i>int</i> <b>$rowIndex</b> row index</li>
     *     <li><i>bool</i> <b>$widow</b> TRUE if page is added because widows
     *     can't be drawn on the page (plugin), FALSE if it's only the current
     *     row that can't be drawn because of bottom margin</li>
     * </ul>
     * @return void|bool Return FALSE to stop event chain and not add the page
     */
    const EV_PAGE_ADD = 9;

    /**
     * Event: après qu'un saut de page soit ajouté
     * <ul>
     *     <li><i>TcTable</i> <b>$table</b> TcTable behind the event</li>
     *     <li><i>array|object|Traversable</i> <b>$row</b> row data if $widow =
     *     FALSE, the complete set of rows if $widow = TRUE</li>
     *     <li><i>int</i> <b>$rowIndex</b> row index</li>
     *     <li><i>bool</i> <b>$widow</b> TRUE if page is added because widows
     *     can't be drawn on the page (plugin), FALSE if it's only the current
     *     row that can't be drawn because of bottom margin</li>
     * </ul>
     * @return void|bool Return FALSE to stop event chain
     */
    const EV_PAGE_ADDED = 10;

    /**
     * Event: before data rows are added
     * <ul>
     *     <li><i>TcTable</i> <b>$table</b> TcTable behind the event</li>
     *     <li><i>array|Traversable</i> <b>$rows</b> complete set of data
     *     rows</li>
     *     <li><i>callable</i> <b>$fn</b> body user function</li>
     * </ul>
     * @return void|bool Return FALSE to stop event chain and not draw any row
     */
    const EV_BODY_ADD = 11;

    /**
     * Event: after data rows are added
     * <ul>
     *     <li><i>TcTable</i> <b>$table</b> TcTable behind the event</li>
     *     <li><i>array|Traversable</i> <b>$rows</b> complete set of data
     *     rows</li>
     * </ul>
     * @return void|bool Return FALSE to stop event chain
     */
    const EV_BODY_ADDED = 12;

    /**
     * Event: after the body is skipped (no rows are drawn in the pdf)
     * <ul>
     *     <li><i>TcTable</i> <b>$table</b> TcTable behind the event</li>
     *     <li><i>array|Traversable</i> <b>$rows</b> complete set of data
     *     rows</li>
     * </ul>
     * @return void|bool Return FALSE to stop event chain
     */
    const EV_BODY_SKIPPED = 13;

    /**
     * Event: after a row is skipped (it is not drawn in the pdf)
     * <ul>
     *     <li><i>TcTable</i> <b>$table</b> TcTable behind the event</li>
     *     <li><i>array|object</i> <b>$row</b> row data</li>
     *     <li><i>int</i> <b>$rowIndex</b> row index</li>
     * </ul>
     * @return void|bool Return FALSE to stop event chain
     */
    const EV_ROW_SKIPPED = 14;

    /**
     * Event: during copy of row definition, get row height
     * <ul>
     *     <li><i>TcTable</i> <b>$table</b> TcTable behind the event</li>
     *     <li><i>array|object</i> <b>$row</b> row data</li>
     *     <li><i>int</i> <b>$rowIndex</b> row index</li>
     * </ul>
     * @return void|float Return the height to set for the rowdefinition when
     * values are copied from default column definition to current row. If not
     * null, will stop event chain
     */
    const EV_ROW_HEIGHT_GET = 15;

    /**
     * The TCPDF instance
     * @var \TCPDF
     */
    private $pdf;

    /**
     * Height of the bottom margin (used in cunjunction with
     * \TCPDF::SetAutoPageBreak())
     * @var float
     */
    private $bottomMargin;

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
     * The calculated height for the current row
     * @var float
     */
    private $rowHeight;

    /**
     * Constructor. Get the TCPDF instance as first argument. We MUST define
     * {@link TcTable::setBottomMargin()} if we want consistent page breaks
     *
     * @param \TCPDF $pdf
     * @param float $minColumnHeight min height for each content row
     * @param float $bottomMargin bottom margin height (default to the one
     * setted via \TCPDF::SetAutoPageBreak($break, $marginBottom)
     */
    public function __construct(\TCPDF $pdf, $minColumnHeight, $bottomMargin = null) {
        $this->pdf = $pdf;
        $this->columnHeight = $minColumnHeight;
        $this->bottomMargin = $bottomMargin !== null
            ? $bottomMargin
            : $pdf->getMargins()['bottom'];
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
     * Remove a plugin and its events by the key
     *
     * @param string $key the key used in TcTable::addPlugin()
     * @return TcTable
     */
    public function removePlugin($key) {
        if (!isset($this->plugins[$key])) {
            return $this;
        }
        if (method_exists($this->plugins[$key], 'unconfigure')) {
            $this->plugins[$key]->unconfigure($this);
        }
        unset($this->plugins[$key]);
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
     * Remove an action setted for the specified event
     *
     * @param int $event event code
     * @param callable $fn function to call when the event was triggered
     * @return TcTable
     */
    public function un($event, callable $fn = null) {
        if (isset($this->events[$event])) {
            if ($fn) {
                foreach ($this->events[$event] as $k => $ev) {
                    if ($ev === $fn) {
                        unset($this->events[$event][$k]);
                        break;
                    }
                }
            } else {
                unset($this->events[$event]);
            }
        }
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
                if ($acceptReturn) {
                    if ($data !== null) {
                        return $data;
                    }
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
     * Define a column. Config array is mostly what we find in \TCPDF Cell and
     * MultiCell.
     *
     * Frequently used Cell and MultiCell options:
     * <ul>
     *     <li><i>callable</i> <b>renderer</b>: renderer function for datas.
     *     Recieve (TcTable $table, $data, $row, $column, $height). The last
     *     parameter is TRUE when called during height calculation. This method
     *     is called twice, one time for cell height calculation and one time
     *     for data drawing.</li>
     *     <li><i>callable</i> <b>headerRenderer</b>: renderer function for
     *     headers. Recieve (TcTable $table, $data, $row, $column)</li>
     *     <li><i>string</i> <b>header</b>: column header text</li>
     *     <li><i>float</i> <b>width</b>: column width</li>
     *     <li><i>string</i> <b>border</b>: cell border (LTBR)</li>
     *     <li><i>string</i> <b>align</b>: text horizontal align (LCR)</li>
     *     <li><i>string</i> <b>valign</b>: text vertical align (TCB)</li>
     *     <li><i>float</i> <b>height</b>: min height for cell (default to
     *     {@link setColumnHeight()}</li>
     * </ul>
     *
     * MultiCell options:
     * <ul>
     *     <li><i>bool</i> <b>isMultiLine</b>: true tell this is a multiline
     *     column</li>
     *     <li><i>bool</i> <b>isMultiLineHeader</b>: true tell this is a header
     *     multiline</li>
     *     <li><i>bool</i> <b>isHtml</b>: true to tell that this is HTML
     *     content</li>
     * </ul>
     *
     * All other options available:
     * <ul>
     *     <li><i>callable</i> <b>drawFn</b>: a callable function that will draw
     *     the cell/multicell/image or anything else. The func receive as args
     *     (TcTable $table, mixed $data, array $columnDefinition,
     *     string $column, array|object $row)</li>
     *     <li><i>callable</i> <b>drawHeaderFn</b>: a callable function that
     *     will draw the cell/multicell/image or anything else. The func receive
     *     as args (TcTable $table, mixed $data, array $columnDefinition,
     *     string $column, array|object $row)</li>
     *     <li><i>bool</i> <b>ln</b>: managed by TcTable. This option is
     *     ignored.</li>
     *     <li><i>bool</i> <b>fill</b>: see doc {@link \TCPDF::Cell}</li>
     *     <li><i>string</i> <b>link</b>: see doc {@link \TCPDF::Cell}</li>
     *     <li><i>int</i> <b>stretch</b>: see doc {@link \TCPDF::Cell}</li>
     *     <li><i>bool</i> <b>ignoreHeight</b>: see doc
     *     {@link \TCPDF::Cell}</li>
     *     <li><i>string</i> <b>calign</b>: see doc {@link \TCPDF::Cell}</li>
     *     <li><i>mixed</i> <b>x</b>: see doc {@link \TCPDF::MultiCell}</li>
     *     <li><i>mixed</i> <b>y</b>: see doc {@link \TCPDF::MultiCell}</li>
     *     <li><i>bool</i> <b>reseth</b>: see doc {@link \TCPDF::MultiCell}</li>
     *     <li><i>float</i> <b>maxh</b>: see doc {@link \TCPDF::MultiCell}</li>
     *     <li><i>bool</i> <b>autoPadding</b>: see doc
     *     {@link \TCPDF::MultiCell}</li>
     *     <li><i>bool</i> <b>fitcell</b>: see doc
     *     {@link \TCPDF::MultiCell}</li>
     *     <li><i>string</i> <b>cellPadding</b>: see doc
     *     {@link \TCPDF::getNumLines}</li>
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
            'isMultiLineHeader' => false,
            'isImage' => false,
            'renderer' => null,
            'headerRenderer' => null,
            'header' => '',
            'drawFn' => null,
            'drawHeaderFn' => null,
            // cell
            'width' => 10,
            'height' => null,
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
            // getNumLines
            'cellPadding' => ''
        ], $this->defaultColumnDefinition, $definition);

        if ($this->columnDefinition[$column]['height'] === null) {
            $this->columnDefinition[$column]['height'] = $this->getColumnHeight();
        }

        $this->trigger(self::EV_COLUMN_ADDED, [
            $column,
            $this->columnDefinition[$column]
        ]);
        return $this;
    }

    /**
     * Add many columns in one shot
     *
     * @see addColumn
     * @param array $columns
     * @param bool $add true to add these columns to existing columns
     * @return \mangetasoupe\pdf\TcTable
     */
    public function setColumns(array $columns, $add = false) {
        if (!$add) {
            $this->columnDefinition = [];
        }
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
     * Set the bottom margin of the table (used in cunjunction with
     * \TCPDF::SetAutoPageBreak())
     *
     * @param float $margin
     * @return TcTable
     */
    public function setBottomMargin($margin) {
        $this->bottomMargin = $margin;
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
     * @param string|null $column column string index. If null, the definition
     * will be set for all columns
     * @param string $definition specific configuration (border, etc.)
     * @param mixed $value value ('LBR' for border, etc.)
     * @return TcTable
     */
    public function setRowDefinition($column, $definition, $value) {
        if ($column === null) {
            foreach ($this->columnDefinition as $key => $v) {
                $this->rowDefinition[$key][$definition] = $value;
            }
        } else {
            $this->rowDefinition[$column][$definition] = $value;
        }
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
     * Browse all cells for this row to find which content has the max height.
     * Then we can adapt the height of all the other cells of this line.
     *
     * @param array|object $row
     * @return float
     */
    public function getCurrentRowHeight($row) {
        // get the max height for this row
        $h = $this->getColumnHeight();
        $this->setRowHeight($h);
        foreach ($this->columnDefinition as $key => $def) {
            $h = $def['height'];
            if ($h > $this->getRowHeight()) {
                $this->setRowHeight($h);
            }
            if ((!isset($row[$key]) && !is_callable($def['renderer']) && !is_callable($def['drawFn'])) || !$def['isMultiLine']) {
                continue;
            }
            $data = $this->fetchDataByUserFunc($def, isset($row[$key]) ? $row[$key] : '', $key, $row, false, true);
            $hd = $this->trigger(self::EV_CELL_HEIGHT_GET, [$key, $data, $row], true);
            if ($hd === null) {
                // getNumLines doesn't care about HTML. To simulate carriage return,
                // we replace <br> with \n. Any better idea? Transactions?
                $data_to_check = strip_tags(str_replace(['<br>', '<br/>', '<br />'], PHP_EOL, $data));
                $nb = $this->pdf->getNumLines($data_to_check, $def['width'],
                    $def['reseth'], $def['autoPadding'],
                    $def['cellPadding'], $def['border']);

                $hd = $nb * $h;
            }
            if ($hd > $this->getRowHeight()) {
                $this->setRowHeight($hd);
            }
        }
        return $this->getRowHeight();
    }

    /**
     * Add table headers
     *
     * @return TcTable
     */
    public function addHeader() {
        $height = $this->getRowHeight();
        $definition = $this->rowDefinition;

        $this->copyDefaultColumnDefinitions(null);
        if ($this->trigger(self::EV_HEADER_ADD) !== false) {
            foreach ($this->columnDefinition as $key => $def) {
                $this->addCell($key, $def['header'], $this->columnDefinition, true);
            }
            $this->trigger(self::EV_HEADER_ADDED);
        }
        // reset row definition, because headers also copy their own column
        // definition and override the data row definition already done before
        // this method is called
        $this->setRowHeight($height);
        $this->rowDefinition = $definition;
        return $this;
    }

    /**
     * Add content to the table. It launches events at start and end, if we need
     * to add some custom stuff.
     *
     * The callable function structure is as follow:
     * <ul>
     *     <li><i>TcTable</i> <b>$table</b> the TcTable object</li>
     *     <li><i>array|object</i> <b>$row</b> current row</li>
     *     <li><i>int</i> <b>$index</b> current row index</li>
     *     <li><i>bool</i> <b>$widow</b> TRUE if this method is called when
     *     parsing widows (from plugin plugin\Widows)</li>
     * </ul>
     * <ul>
     *     <li>Return <i>array</i> formatted data, where keys are the one
     *     configured in the column definition</li>
     * </ul>
     *
     * @param array|Traversable $rows the complete set of data
     * @param callable $fn data layout function
     * @return TcTable
     */
    public function addBody($rows, callable $fn = null) {
        // last column will have TRUE for the TCPDF [ln] property
        end($this->columnDefinition);
        $this->columnDefinition[key($this->columnDefinition)]['ln'] = true;

        $auto_pb = $this->pdf->getAutoPageBreak();
        $bmargin = $this->pdf->getMargins()['bottom'];
        $this->pdf->SetAutoPageBreak(false, $this->bottomMargin);
        if ($this->trigger(self::EV_BODY_ADD, [$rows, $fn]) === false) {
            $this->trigger(self::EV_BODY_SKIPPED, [$rows]);
            $this->endBody($auto_pb, $bmargin);
            return $this;
        }
        if ($this->showHeader) {
            $this->addHeader();
        }
        foreach ($rows as $index => $row) {
            $data = $fn ? $fn($this, $row, $index, false) : $row;
            // draw row only if it's an array. It gives the possibility to skip
            // some rows with the user func
            if (is_array($data) || is_object($data)) {
                $this->addRow($data, $index);
            } else {
                $this->trigger(self::EV_ROW_SKIPPED, [$row, $index]);
            }
        }
        $this->trigger(self::EV_BODY_ADDED, [$rows]);
        $this->endBody($auto_pb, $bmargin);
        return $this;
    }

    /**
     * Add a row
     *
     * @param array|object $row row data
     * @param int $index row index
     * @return TcTable
     */
    private function addRow($row, $index = null) {
        $this->copyDefaultColumnDefinitions($row, $index);
        if ($this->trigger(self::EV_ROW_ADD, [$row, $index]) === false) {
            return $this;
        }
        $h = $this->getRowHeight();
        $page_break_trigger = $this->pdf->getPageHeight() - $this->pdf->getBreakMargin();
        if ($this->pdf->GetY() + $h >= $page_break_trigger) {
            if ($this->trigger(self::EV_PAGE_ADD, [$row, $index, false]) !== false) {
                $this->pdf->AddPage();
                $this->trigger(self::EV_PAGE_ADDED, [$row, $index, false]);
            }
        }
        foreach ($this->columnDefinition as $key => $value) {
            if (isset($this->rowDefinition[$key])) {
                $this->addCell($key, isset($row[$key]) ? $row[$key] : '', $row);
            }
        }
        $this->trigger(self::EV_ROW_ADDED, [$row, $index]);
        return $this;
    }

    /**
     * Draw a cell
     *
     * @param string $column column string index
     * @param mixed $data data to draw inside the cell
     * @param array|object $row all datas for this line
     * @param bool $header true if we draw header cell
     * @return TcTable
     */
    private function addCell($column, $data, $row, $header = false) {
        $c = $this->rowDefinition[$column];
        $data = $this->fetchDataByUserFunc($c, $data, $column, $row, $header, false);
        if ($this->trigger(self::EV_CELL_ADD, [$column, $data, $c, $row, $header]) === false) {
            $data = '';
        }
        if ($this->drawByUserFunc($data, $column, $row, $header) === false) {
            $h = $this->getRowHeight();
            if ((!$header && $c['isMultiLine']) || ($header && $c['isMultiLineHeader'])) {
                // for multicell, if maxh = null, set it to cell's height, so
                // vertical alignment can work
                $this->pdf->MultiCell($c['width'], $h, $data, $c['border'],
                    $c['align'], $c['fill'], $c['ln'], $c['x'], $c['y'],
                    $c['reseth'], $c['stretch'], $c['isHtml'],
                    $c['autoPadding'], $c['maxh'] === null ? $h : $c['maxh'],
                    $c['valign'], $c['fitcell']);
            } else {
                $this->pdf->Cell($c['width'], $h, $data, $c['border'],
                    $c['ln'], $c['align'], $c['fill'], $c['link'],
                    $c['stretch'], $c['ignoreHeight'], $c['calign'],
                    $c['valign']);
            }
        }
        $this->trigger(self::EV_CELL_ADDED, [$column, $data, $c, $row, $header]);
        return $this;
    }

    /**
     * Get data by user function, if it exists
     *
     * @param array $c the column definition
     * @param mixed $data data to draw inside the cell
     * @param string $column column string index
     * @param array|object $row all datas for this line
     * @param bool $header true if we draw header cell
     * @param bool $heightc determine if we are in height calculation or not
     * @return mixed
     */
    private function fetchDataByUserFunc($c, $data, $column, $row, $header, $heightc) {
        if (!$header && is_callable($c['renderer'])) {
            $data = $c['renderer']($this, $data, $row, $column, $heightc);
        } elseif ($header && is_callable($c['headerRenderer'])) {
            $data = $c['headerRenderer']($this, $data, $row, $column, $heightc);
        }
        return $data;
    }

    /**
     * Draw cell/image or anything else by user function, if it exists
     *
     * @param mixed $data data to draw inside the cell
     * @param string $column column string index
     * @param array|object $row all datas for this line
     * @param bool $header true if we draw header cell
     * @return bool false to execute normal cell drawing
     */
    private function drawByUserFunc($data, $column, $row, $header) {
        $c = $this->rowDefinition[$column];
        $result = false;
        if (!$header && is_callable($c['drawFn'])) {
            $result = $c['drawFn']($this, $data, $c, $column, $row);
        } elseif ($header && is_callable($c['drawHeaderFn'])) {
            $result = $c['drawHeaderFn']($this, $data, $c, $column, $row);
        }
        return $result;
    }

    /**
     * Finish process in body function, empty parameters, reset defaults in
     * the main PDF, etc.
     *
     * @param bool $autoPb
     * @param float $bMargin
     * @return void
     */
    private function endBody($autoPb, $bMargin) {
        $this->pdf->SetAutoPageBreak($autoPb, $bMargin);
    }

    /**
     * Copy column definition inside a new property. It allows us to customize
     * it only for this row. For the next row, column definition will again be
     * the default one.
     *
     * Usefull for plugins that need to temporarily, for one precise row, to
     * change column information (like background color, border, etc)
     *
     * @param array|object $columns row datas (for each cell)
     * @param int $rowIndex row index
     * @return void
     */
    private function copyDefaultColumnDefinitions($columns = null, $rowIndex = null) {
        $this->rowDefinition = $this->columnDefinition;
        $h = $this->trigger(self::EV_ROW_HEIGHT_GET, [$columns, $rowIndex], true);
        if ($h === null) {
            $h = $columns !== null
                ? $this->getCurrentRowHeight($columns)
                : $this->getColumnHeight();
        }
        $this->setRowHeight($h);
    }

}
