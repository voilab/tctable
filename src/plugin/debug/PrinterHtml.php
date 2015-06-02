<?php

namespace voilab\tctable\plugin\debug;

use voilab\tctable\plugin\Debug;

class PrinterHtml implements PrinterInterface {

    /**
     * print objects or not
     * @var bool
     */
    private $printObjects = false;

    /**
     * Number of level of recursion autorized
     * @var int
     */
    private $deepLevel = null;

    /**
     * Determine if we want to print objects or ignore them (their class name
     * is displayed instead of the whole object)
     *
     * @param bool $print
     * @return PrinterHtml
     */
    public function setPrintObjects($print) {
        $this->printObjects = (bool) $print;
        return $this;
    }

    /**
     * Set the allowed number of recursions in array
     *
     * @param int $level
     * @return PrinterHtml
     */
    public function setDeepLevel($level) {
        $this->deepLevel = $level;
        return $this;
    }

    /**
     * {@inheritDocs}
     */
    public function output(Debug $plugin, array $data) {
        $tmp = !$this->printObjects ? $this->purgeObjects($data, 0) : $data;
        echo "<pre>";
        print_r(
            array_merge(
                ['event' => $plugin->getEventInvoker()],
                $tmp
            )
        );
        echo "</pre>";
    }

    /**
     * Replace objects by their classname, so the print_r function won't print
     * the whole object tree (which can be huge sometimes)
     *
     * @param array $data
     * @param int $level
     * @return array
     */
    private function purgeObjects(array $data, $level) {
        $tmp = [];
        foreach ($data as $k => $v) {
            if (is_array($v)) {
                $tmp[$k] = $this->deepLevel === null || $level <= $this->deepLevel
                    ? $this->purgeObjects($v, ++$level)
                    : '(array)';

            } elseif (is_object($v)) {
                $tmp[$k] = spl_object_hash($v) . ' ' . get_class($v);
            } else {
                ob_start();
                var_dump($v);
                $tmp[$k] = str_replace('</pre>', '',
                    preg_replace("/<pre[^>]+\>/i", '$1', ob_get_clean()));
            }
        }
        return $tmp;
    }

}
