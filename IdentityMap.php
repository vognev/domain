<?php

class Domain_IdentityMap
{
    /**
     * @var Domain_IdentityMap
     */
    protected static $_instance;

    protected $_entities = array();

    protected final function __construct()
    {

    }

    public function __clone()
    {
        throw new Domain_Exception("Domain_IdentityMap cannot be cloned");
    }

    public static function getInstance()
    {
        if (!self::$_instance)
            self::$_instance = new self();
        return self::$_instance;
    }

    public static function resetInstance()
    {
        self::$_instance = null;
    }

    public function __isset($key)
    {
        return array_key_exists($key, $this->_entities);
    }

    public function __set($key, $value)
    {
        if (!($value instanceof Domain_Entity_Abstract))
            throw new Domain_Exception("Not a Entity");
        if (isset($this->_entities[$key]))
            throw new Domain_Exception("IdentityMap forbids overwriting of existent object with key [$key]");
        $this->_entities[$key] = $value;
    }

    public function __get($key)
    {
        return $this->_entities[$key];
    }

    public function __unset($key)
    {
        unset($this->_entities[$key]);
    }
}
