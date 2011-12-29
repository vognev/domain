<?php

class Domain_Collection implements Iterator, ArrayAccess, Countable
{
    /**
     * Mapper responsible for collection entities
     * @var Domain_Mapper_Abstract
     */
    protected $_mapper;

    /**
     * Total count of entities in collection
     * @var int
     */
    protected $_total       = 0;

    /**
     * Cache of raw entity data
     * @var array
     */
    protected $_raw         = array();

    /**
     * Iterator pointer
     * @var int
     */
    protected  $_pointer;

    /**
     * Entities
     * @var array
     */
    protected $_all         = array();

    protected $_deleted     = array();

    protected $_new         = array();

    /**
     * @codeCoverageIgnore
     * @return void
     */
    public function dump()
    {
        var_dump(
            array(
                 'total'    => $this->_total,
                 'all'      => $this->_all,
                 'raw'      => $this->_raw,
                 'deleted'  => $this->_deleted,
                 'new'      => $this->_new
            )
        );
    }

    public function __construct(Domain_Mapper_Abstract $mapper,
                                $raw = array())
    {
        $this->_mapper      = $mapper;
        $this->_raw         = $raw;
        $this->_total       = count($raw);
    }

    public function getDeleted()
    {
        return $this->_deleted;
    }

    public function getNew()
    {
        return $this->_new;
    }

    public function clear()
    {
        while($this->_total > 0) {
            $entity = $this->getRow($this->_total - 1);
            if (!in_array($entity, $this->_deleted, true))
                $this->_deleted[] = $entity;
            $this->_total--;
        }

        $this->_new     = array();
        $this->_all     = array();
        $this->_raw     = array();
        $this->_pointer = null;
    }

    public function commit()
    {
        $this->_deleted     = array();
        $this->_new         = array();
    }

    /**
     * Returns Entity class of collection
     * @return string
     */
    public function getEntityClass()
    {
        return $this->_mapper->getEntityClass();
    }

    /**
     * Adds Entity to Collection
     * @param Domain_Entity_Abstract $entity
     * @return void
     */
    public function add(Domain_Entity_Abstract $entity)
    {
        $class = $this->_mapper->getEntityClass();
        $this->ensure(
            $entity instanceof $class,
            __METHOD__ . "() Entity is not subclass of $class"
        );

        if (false !== ($key = $this->_key($this->_deleted, $entity))) {
            array_splice($this->_deleted, $key, 1);
        } else {
            if ( false === $this->_key($this->_new, $entity)) {
                $this->_new[] = $entity;
            }
        }

        $this->_all[$this->_total++] = $entity;
    }

    /**
     * Checks if Collection already contains Entity
     * @param Domain_Entity_Abstract $that
     * @return bool
     */
    public function contains(Domain_Entity_Abstract $that)
    {
        return false !== $this->_key($this, $that);
    }

    protected function _key($map, Domain_Entity_Abstract $entity)
    {
        if (count($map)) foreach($map as $idx => $element)
            if ($entity->equalsTo($element))
                return $idx;
        return false;
    }

    /**
     * Get Entity from Objects cache or create Entity from raw data if
     * there is no Object yet
     * @param int $num
     * @return null|Domain_Entity_Abstract
     */
    protected function getRow($num)
    {
        if ($num >= $this->_total || $num < 0) {
            return null;
        }
        if (isset($this->_all[$num])) {
            return $this->_all[$num];
        }

        if (isset($this->_raw[$num])) {
            $this->_all[$num] = $this->_mapper->mapEntityFromRow($this->_raw[$num]);
            return $this->_all[$num];
        }
    }

    public function toArray()
    {
        $rows = array();
        for ($i = 0; $i < $this->_total; $i++)
            $rows[] = $this->getRow($i);
        return $rows;
    }

    protected function ensure($condition, $message)
    {
        if (!$condition) {
            require_once 'Domain/Collection/Exception.php';
            throw new Domain_Collection_Exception($message);
        }
    }

    /* Iterator Implementation */

    public function rewind()
    {
        $this->_pointer = 0;
    }

    public function current()
    {
        return $this->getRow($this->_pointer);
    }

    public function key()
    {
        return $this->_pointer;
    }

    public function next()
    {
        $row = $this->getRow($this->_pointer);
        if ($row)
            $this->_pointer++;
        return $row;
    }

    public function valid()
    {
        return !is_null($this->current());
    }

    /* ArrayAccess Implementation */

    public function offsetExists($offset)
    {
        return array_key_exists($offset, $this->_raw) ||
               array_key_exists($offset, $this->_all);
    }

    public function offsetGet($offset)
    {
        return $this->getRow($offset);
    }

    public function offsetSet($offset, $value)
    {
        $this->ensure(false, "Unallowed");
    }

    public function offsetUnset($offset)
    {
        $entity             = $this->getRow($offset);

        //if (!in_array($entity, $this->_deleted, true))
        $this->_deleted[]   = $entity;

        if ( false !==($key = array_search($entity, $this->_new, true)))
            unset($this->_new[$key]);

        if (array_key_exists($offset, $this->_all))
            //array_splice($this->_all, $offset, 1);
            unset($this->_all[$offset]);

        if (array_key_exists($offset, $this->_raw))
            unset($this->_raw[$offset]);

        $this->_total--;
    }

    /* Countable Implementation */
    public function count()
    {
        return $this->_total;
    }
}
 
