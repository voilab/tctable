<?php

namespace voilab\tctable\plugin\debug;

use voilab\tctable\plugin\Debug;

interface PrinterInterface {

    /**
     * Method that print, in some way, the data content from the event. You can
     * find which event called the method with $plugin->getEventInvoker();
     *
     * @param Debug $plugin the debug plugin instance
     * @param array $data data coming from the event
     * @return void
     */
    public function output(Debug $plugin, array $data);

}
