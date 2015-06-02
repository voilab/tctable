<?php

namespace voilab\tctable\traits;

trait Eventable {

    /**
     * Events list available when trigger is called
     * @var array
     */
    private $events = [];

    /**
     * Set an action to do on the specified event
     *
     * @param int $event event code
     * @param callable $fn function to call when the event is triggered
     * @return Eventable
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
     * @return Eventable
     */
    public function un($event, callable $fn) {
        if (isset($this->events[$event])) {
            foreach ($this->events[$event] as $k => $ev) {
                if ($ev === $fn) {
                    unset($this->events[$event][$k]);
                    break;
                }
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

}
