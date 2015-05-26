<?php

class PluginManager implements \IteratorAggregate, \Countable, \ArrayAccess {

    private $plugins = [];

    private $table;

    public function setTable(TcTable $table) {
        $this->table = $table;
        return $this;
    }

    public function add($key, Plugin $plugin) {
        $plugin->configure($this->table);
        $this->plugins[$key] = $plugin;
        return $this;
    }

    public function has($key) {
        return isset($this->plugins[$key]);
    }

    public function get($key) {
        if (!$this->has($key)) {
            throw new Exception(sprintf("Plugin %s doesn't exist!", $key));
        }
        return $this->plugins[$key];
    }

    public function remove($key) {
        if ($this->has($key)) {
            $this->plugins[$key]->unconfigure($this->table);
            unset($this->plugins[$key]);
        }
        return $this;
    }

    public function getIterator() {
        return new \ArrayIterator($this->plugins);
    }

    public function count($mode = COUNT_NORMAL) {
        return count($this->plugins);
    }

    public function offsetExists($offset) {
        return $this->has($offset);
    }

    public function offsetGet($offset) {
        return $this->get($offset);
    }

    public function offsetSet($offset, Plugin $value) {
        return $this->add($offset, $value);
    }

    public function offsetUnset($offset) {
        return $this->remove($offset);
    }

}
