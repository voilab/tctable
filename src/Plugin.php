<?php

namespace voilab\tctable;

interface Plugin {

    /**
     * Configuration of the plugin. Manage relations with TcTable, using events
     * like $tctable->on(TcTable::EV_ROW_ADD, [$this, 'myFunc']);
     *
     * @param TcTable $table
     * @return void
     */
    public function configure(TcTable $table);

}
