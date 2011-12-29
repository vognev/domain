<?php

abstract class Domain_Entity_Abstract implements Domain_Entity_Interface
{
    /**
     * @var array actual Entity data
     */
    protected $_aData           = array();

    protected $_cleanFields     = array();

    public function __construct($data = array())
    {
        if ($data && is_array($data)) {
            $this->fromArray($data);
        }
        $this->markClean();
    }

    public function markClean()
    {
        $this->_cleanFields     = array();
    }

    public function isDirty($field = null)
    {
        if (null === $field)
            return count($this->_cleanFields) > 0;

        $this->ensure(
            array_key_exists($field, $this->_aData),
            __METHOD__ . "() Unknown property '$field'"
        );

        return array_key_exists($field, $this->_cleanFields);
    }

    public function getCleanValue($field)
    {
        $this->ensure(
            array_key_exists($field, $this->_aData),
            __METHOD__ . "() Unknown property '$field'"
        );

        return array_key_exists($field, $this->_cleanFields)
               ? $this->_cleanFields[$field]
               : $this->_aData[$field];
    }

    public function fromArray($data)
    {
        foreach ($data as $k => $v) {
            $this->$k = $v;
        }
    }

    public function toArray()
    {
        $return = array();
        foreach ($this->_aData as $k => $v)
            $return[$k] = $this->$k;
        return $return;
    }

    public function set($field, $value)
    {
        $this->$field = $value;
        return $this;
    }

    public function __set($name, $value)
    {
        if (array_key_exists($name, $this->_aData)) {
            if ($this->_aData[$name] != $value &&
                !array_key_exists($name, $this->_cleanFields)) {
                $this->_cleanFields[$name] = $this->_aData[$name];
            }
            $this->_aData[$name] = $value;
        }
    }

    public function __get($name)
    {
        if (array_key_exists($name, $this->_aData)) {
            return $this->_aData[$name];
        } else {
            throw new Domain_Exception(__METHOD__ . "() Unknown field named \"$name\"");
        }
    }

    public function __isset($name)
    {
        return isset($this->_aData[$name]);
    }

    abstract function equalsTo($that);

    protected function ensureComparable($that)
    {
        $this->ensure(
            get_class($this) == get_class($that),
            "Entities are uncomparable"
        );
    }

    protected function ensure($condition, $message)
    {
        if (!$condition)
            throw new Domain_Exception($message);
    }
}
