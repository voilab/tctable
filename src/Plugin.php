<?php

namespace voilab\tctable;

interface Plugin {

    /**
     * Configure le plugin, gère les relations avec la table en utilisant les
     * événements du type $table->on(TcTable::EV_ROW_ADD, [$this, 'maFunc']);
     *
     * @param TcTable $table
     * @return void
     */
    public function configure(TcTable $table);

}
