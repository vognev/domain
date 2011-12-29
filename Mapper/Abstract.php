<?php

abstract class Domain_Mapper_Abstract
{
    /**
     * @var string Entity class for this mapper
     */
    protected $_entityClass;

    /**
     * @var array Map for row <=> entity keys
     */
    protected $_map;

    public function __construct()
    {
        $this->_identityMap = Domain_IdentityMap::getInstance();
    }

    protected function getIdentityMap()
    {
        return Domain_IdentityMap::getInstance();
    }

    /**
     * @param string $key
     * @return bool
     */
    protected function isInMap($key)
    {
        $im = $this->getIdentityMap();
        return isset($im->$key);
    }

    /**
     * @param $key
     * @return Domain_Entity_Abstract
     */
    protected function getFromMap($key)
    {
        $im = $this->getIdentityMap();
        return $im->$key;
    }

    /**
     * @param string $key
     * @param Domain_Entity_Abstract $entity
     * @return void
     */
    protected function addToMap($key, Domain_Entity_Abstract $entity)
    {
        $im = $this->getIdentityMap();
        $im->$key = $entity;
    }

    /**
     * @param string $key
     * @return void
     */
    protected function delFromMap($key)
    {
        $im = $this->getIdentityMap();
        unset($im->$key);
    }

    /**
     * Maps database row fields to entity ones
     * @param array $row
     * @return Domain_Entity_Abstract
     */
    public function mapEntityFromRow(array $row)
    {
        if (
            $this->hasIdentity($row) &&
            ($key = $this->getIdentityKey($row)) &&
            $this->isInMap($key)
        ) {
            return $this->getFromMap($key);
        }

        /** @var $entity Domain_Entity_Abstract */
        $entity = new $this->_entityClass();
        foreach($this->_map as $rowFieldName => $entityFieldName) {
            $mapperMethod = '_set_' . $entityFieldName;

            if (method_exists($this, $mapperMethod)) {
                $entity->$entityFieldName = $this->$mapperMethod($row[$rowFieldName]);
            } else {
                $entity->$entityFieldName = $row[$rowFieldName];
            }
        }

        $entity->markClean();

        if (
            $this->hasIdentity($entity) &&
            ($key = $this->getIdentityKey($entity)) &&
            !$this->isInMap($key)
        )
            $this->addToMap($key, $entity);

        return $entity;
    }

    /**
     * Maps entity fields to database row ones
     * @param Domain_Entity_Abstract $entity
     * @return array
     */
    public function mapRowFromEntity(Domain_Entity_Abstract $entity)
    {
        $row = array();
        foreach(array_flip($this->_map) as $entityFieldName => $rowFieldName) {
            $mapperMethod = '_get_' . $entityFieldName;

            if (method_exists($this, $mapperMethod)) {
                $row[$rowFieldName] = $this->$mapperMethod($entity->$entityFieldName);
            } else {
                $row[$rowFieldName] = $entity->$entityFieldName;
            }
        }
        return $row;
    }

    public function getEntityClass()
    {
        $this->ensure(
            !is_null($this->_entityClass),
            "Mapper's Entity Class is undefined"
        );
        return $this->_entityClass;
    }

    abstract protected function hasIdentity($entity);
    abstract protected function getIdentity($entity);
    abstract protected function getIdentityKey($entity);
    abstract protected function setIdentity(Domain_Entity_Abstract $entity, $identity);

    protected function ensure($condition, $message)
    {
        if (!$condition) {
            require_once 'Domain/Mapper/Exception.php';
            throw new Domain_Mapper_Exception($message);
        }
    }
}
