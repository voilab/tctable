<?php

namespace voilab\tctable\plugin;

use voilab\tctable\TcTable;
use voilab\tctable\Plugin;

class Debug implements Plugin {

    /**
     * Events to listen to
     * @var array
     */
    private $listen;

    /**
     * The debuggeur printer output
     * @var debug\PrinterInterface
     */
    private $printer;

    /**
     * The currently invoked event
     * @var array
     */
    private $eventInvoker;

    /**
     * Current row index
     * @var int
     */
    private $current = 0;

    /**
     * Debug row index starting point
     * @var int
     */
    private $from;

    /**
     * Number of rows to print
     * @var int
     */
    private $length;

    /**
     * True to stop all process when not in bounds
     * @var bool
     */
    private $outOfBounds = false;

    /**
     * A function to determine if we can print the row or not
     * @var callable
     */
    private $boundsFn;

    /**
     * Plugin constructor
     *
     * @param array $listen events to listen to. Default to rowadd, rowadded,
     * rowskipped, pageadd, pageadded, headeradd, headeradded
     */
    public function __construct(?array $listen = null, ?debug\PrinterInterface $printer = null) {
        $this->listen = $listen ?: [
            TcTable::EV_HEADER_ADD, TcTable::EV_HEADER_ADDED,
            TcTable::EV_PAGE_ADD, TcTable::EV_PAGE_ADDED,
            TcTable::EV_ROW_ADD, TcTable::EV_ROW_ADDED, TcTable::EV_ROW_SKIPPED
        ];
        $this->printer = $printer ?: new debug\PrinterHtml();
    }

    /**
     * Set debug printer
     *
     * @param debug\PrinterInterface $printer
     * @return Debug
     */
    public function setPrinter(debug\PrinterInterface $printer) {
        $this->printer = $printer;
        return $this;
    }

    /**
     * Return the debug printer
     *
     * @return debug\PrinterInterface
     */
    public function getPrinter() {
        return $this->printer;
    }

    /**
     * Set valid bounds (rows which will be printer)
     *
     * @param int $from from row index...
     * @param int $length number of rows to print
     * @return Debug
     */
    public function setBounds($from, $length = null, $stopOutOfBounds = false) {
        $this->from = $from;
        $this->length = $length;
        $this->outOfBounds = (bool) $stopOutOfBounds;
        return $this;
    }

    /**
     * Set valid bounds through a user function. Recieve two arguments: the
     * plugin instance and current index
     *
     * @param callable $fn must return a boolean
     * @return Debug
     */
    public function setBoundsFn(callable $fn) {
        $this->boundsFn = $fn;
        return $this;
    }

    /**
     * Get the last event called. Returns an array with two key:
     * <code>
     * Array(
     *     context => for example "rowAdded"
     *     id => the event id (constant in TcTable main class)
     * )
     * </code>
     * @return array
     */
    public function getEventInvoker() {
        return $this->eventInvoker;
    }

    /**
     * {@inheritDocs}
     */
    public function configure(TcTable $table) {
        $table
            ->on(TcTable::EV_COLUMN_ADDED, [$this, 'columnAdded'])
            ->on(TcTable::EV_BODY_ADD, [$this, 'bodyAdd'])
            ->on(TcTable::EV_BODY_ADDED, [$this, 'bodyAdded'])
            ->on(TcTable::EV_BODY_SKIPPED, [$this, 'bodySkipped'])
            ->on(TcTable::EV_HEADER_ADD, [$this, 'headerAdd'])
            ->on(TcTable::EV_HEADER_ADDED, [$this, 'headerAdded'])
            ->on(TcTable::EV_PAGE_ADD, [$this, 'pageAdd'])
            ->on(TcTable::EV_PAGE_ADDED, [$this, 'pageAdded'])
            ->on(TcTable::EV_ROW_ADD, [$this, 'rowAdd'])
            ->on(TcTable::EV_ROW_ADDED, [$this, 'rowAdded'])
            ->on(TcTable::EV_ROW_SKIPPED, [$this, 'rowSkipped'])
            ->on(TcTable::EV_ROW_HEIGHT_GET, [$this, 'rowHeightGet'])
            ->on(TcTable::EV_CELL_ADD, [$this, 'cellAdd'])
            ->on(TcTable::EV_CELL_ADDED, [$this, 'cellAdded'])
            ->on(TcTable::EV_CELL_HEIGHT_GET, [$this, 'cellHeightGet']);
    }

    public function cellAdd(TcTable $table, $column, $data, array $definition, $row, $header) {
        $this->setEventInvoker(TcTable::EV_CELL_ADD, __METHOD__);
        if ($this->listenTo() && $this->inBounds()) {
            $this->printer->output($this, [
                'header' => $header,
                'column' => $column,
                'data' => $data,
                'definition' => $definition,
                'row' => $row
            ]);
        }
    }

    public function cellAdded(TcTable $table, $column, $data, array $definition, $row, $header) {
        $this->setEventInvoker(TcTable::EV_CELL_ADDED, __METHOD__);
        if ($this->listenTo() && $this->inBounds()) {
            $this->printer->output($this, [
                'header' => $header,
                'column' => $column,
                'data' => $data,
                'definition' => $definition,
                'row' => $row
            ]);
        }
    }

    public function cellHeightGet(TcTable $table, $column, $data, $row) {
        $this->setEventInvoker(TcTable::EV_CELL_HEIGHT_GET, __METHOD__);
        if ($this->listenTo() && $this->inBounds()) {
            $this->printer->output($this, [
                'column' => $column,
                'data' => $data,
                'row' => $row
            ]);
        }
    }

    public function bodyAdd(TcTable $table, $rows) {
        $this->setEventInvoker(TcTable::EV_BODY_ADD, __METHOD__);
        if ($this->listenTo() && $this->inBounds()) {
            $this->printer->output($this, [
                'rows' => $rows
            ]);
        }
    }

    public function bodyAdded(TcTable $table, $rows) {
        $this->setEventInvoker(TcTable::EV_BODY_ADDED, __METHOD__);
        if ($this->listenTo() && $this->inBounds()) {
            $this->printer->output($this, [
                'rows' => $rows
            ]);
        }
    }

    public function bodySkipped(TcTable $table, $rows) {
        $this->setEventInvoker(TcTable::EV_BODY_SKIPPED, __METHOD__);
        if ($this->listenTo() && $this->inBounds()) {
            $this->printer->output($this, [
                'rows' => $rows
            ]);
        }
    }

    public function rowAdd(TcTable $table, $row, $rowIndex) {
        $this->setEventInvoker(TcTable::EV_ROW_ADD, __METHOD__);
        if ($this->listenTo() && $this->inBounds()) {
            $this->printer->output($this, [
                'row data' => $row,
                'row index' => $rowIndex
            ]);
        }
    }

    public function rowAdded(TcTable $table, $row, $rowIndex) {
        $this->setEventInvoker(TcTable::EV_ROW_ADDED, __METHOD__);
        if ($this->listenTo() && $this->inBounds()) {
            $this->printer->output($this, [
                'row data' => $row,
                'row index' => $rowIndex
            ]);
        }
        $this->current++;
    }

    public function rowSkipped(TcTable $table, $row, $rowIndex) {
        $this->setEventInvoker(TcTable::EV_ROW_SKIPPED, __METHOD__);
        if ($this->listenTo() && $this->inBounds()) {
            $this->printer->output($this, [
                'row data' => $row,
                'row index' => $rowIndex
            ]);
        }
        $this->current++;
    }

    public function rowHeightGet(TcTable $table, $row, $rowIndex) {
        $this->setEventInvoker(TcTable::EV_ROW_HEIGHT_GET, __METHOD__);
        if ($this->listenTo() && $this->inBounds()) {
            $this->printer->output($this, [
                'row data' => $row,
                'row index' => $rowIndex
            ]);
        }
    }

    public function pageAdd(TcTable $table, $row, $rowIndex, $widow) {
        $this->setEventInvoker(TcTable::EV_PAGE_ADD, __METHOD__);
        if ($this->listenTo() && $this->inBounds()) {
            $this->printer->output($this, [
                'row data' => $row,
                'row index' => $rowIndex,
                'is widow' => $widow
            ]);
        }
    }

    public function pageAdded(TcTable $table, $row, $rowIndex, $widow) {
        $this->setEventInvoker(TcTable::EV_PAGE_ADDED, __METHOD__);
        if ($this->listenTo() && $this->inBounds()) {
            $this->printer->output($this, [
                'row data' => $row,
                'row index' => $rowIndex,
                'is widow' => $widow
            ]);
        }
    }

    public function headerAdd(TcTable $table) {
        $this->setEventInvoker(TcTable::EV_HEADER_ADD, __METHOD__);
        if ($this->listenTo() && $this->inBounds()) {
            $this->printer->output($this, []);
        }
    }

    public function headerAdded(TcTable $table) {
        $this->setEventInvoker(TcTable::EV_HEADER_ADDED, __METHOD__);
        if ($this->listenTo() && $this->inBounds()) {
            $this->printer->output($this, []);
        }
    }

    public function columnAdded(TcTable $table, $column, $definition) {
        $this->setEventInvoker(TcTable::EV_COLUMN_ADDED, __METHOD__);
        if ($this->listenTo() && $this->inBounds()) {
            $this->printer->output($this, [
                'column index' => $column,
                'column definition' => $definition
            ]);
        }
    }

    /**
     * Check if the current row is in debug bounds
     *
     * @return bool
     */
    private function inBounds() {
        $in = true;
        if ($this->from !== null) {
            $in = $this->current >= $this->from;
            if ($in && $this->length !== null) {
                $in = $this->current < $this->from + $this->length;
                if (!$in && $this->outOfBounds) {
                    die("Process stopped in TcTable Debug Plugin, because " .
                        "\$outOfBounds is set to TRUE in setBounds().");
                }
            }
        }
        if ($in && is_callable($this->boundsFn)) {
            return $this->boundsFn($this, $this->current);
        }
        return $in;
    }

    /**
     * Set current event information
     *
     * @param int $event event id
     * @param string $context context of the event (rowAdded, for example)
     * @return Debug
     */
    private function setEventInvoker($event, $context) {
        $this->eventInvoker = [
            'context' => $context,
            'id' => $event
        ];
        return $this;
    }

    /**
     * Check if the given event is listenable
     *
     * @param int $event event id, current event if null
     * @return bool
     */
    private function listenTo($event = null) {
        if ($event === null && $this->getEventInvoker()) {
            $event = $this->getEventInvoker()['id'];
        }
        return !$this->listen || in_array($event, $this->listen);
    }

}
