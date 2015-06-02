<?php

namespace voilab\tctable\traits;

use voilab\tctable\Plugin;

trait Pluginable {

    /**
     * Plugins attached to this table
     * @var Plugin[]
     */
    private $plugins = [];

    /**
     * Add a plugin
     *
     * @param Plugin $plugin the instanciated plugin
     * @param string $key a key to quickly find the plugin with getPlugin()
     * @return Pluginable
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

}
