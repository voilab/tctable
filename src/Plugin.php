<?php

namespace voilab\tctable;

abstract class Plugin {

    abstract protected function getEvents(TcTable $table);

    /**
     * Configuration of the plugin. Manage relations with TcTable, using events
     * like $tctable->on(TcTable::EV_ROW_ADD, [$this, 'myFunc']);
     *
     * @param TcTable $table
     * @return void
     */
    public function configure(TcTable $table) {
        $events = $this->getEvents($table);
        foreach ($events as $event => $fn) {
            $table->on($event, $fn);
        }
    }

    /**
     * Deconfiguration of the plugin. Used to remove all events setted in the
     * table
     *
     * @param TcTable $table
     * @return void
     */
    public function unconfigure(TcTable $table) {
        $events = $this->getEvents($table);
        foreach ($events as $event => $fn) {
            $table->un($event, $fn);
        }
    }

}
