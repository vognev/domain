<?php

class Domain_Event_Dispatcher
{
    /** @var Domain_Event_Dispatcher */
    protected static $_instance;

    protected $_listeners = array();

    public static function getInstance()
    {
        if (null === self::$_instance) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    public static function resetInstance()
    {
        self::$_instance = null;
    }

    protected function __construct() {;}
    protected function __clone() {;}

    public function addListener($event, $callable)
    {
        if (!is_string($event)) {
            require_once 'Domain/Event/Exception.php';
            throw new Domain_Event_Exception('$event should be the string');
        }

        if (!is_callable($callable)) {
            require_once 'Domain/Event/Exception.php';
            throw new Domain_Event_Exception('$callable is not callable');
        }

        if (!array_key_exists($event, $this->_listeners)) {
            $this->_listeners[$event] = array(
                $callable
            );
        } else {
            // prevent multiple subscriptions
            if (false === array_search($callable, $this->_listeners[$event]))
                $this->_listeners[$event][] = $callable;
        }

        return $this;
    }

    public function removeListener($event, $callable)
    {
        if (!is_string($event)) {
            require_once 'Domain/Event/Exception.php';
            throw new Domain_Event_Exception('$event should be the string');
        }

        if (array_key_exists($event, $this->_listeners) &&
            ($idx = array_search($callable, $this->_listeners))) {
            unset($this->_listeners[$idx]);
        }

        return $this;
    }

    public function notify(Domain_Event_Abstract $event)
    {
        $eventType = get_class($event);
        if (array_key_exists($eventType, $this->_listeners) &&
            count($this->_listeners[$eventType])) {
            foreach($this->_listeners[$eventType] as $callable) {
                call_user_func($callable, $event);
            }
        }
    }

}