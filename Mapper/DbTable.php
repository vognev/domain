<?php

/**
 * @desc Mapper class for single table entity
 * @throws Domain_Exception|Domain_Mapper_Exception
 */
abstract class Domain_Mapper_DbTable extends Domain_Mapper_Abstract
{
    /**
     * @var string Table name
     */
    protected $_table;

    /**
     * @var string|array Fields which identify record in database
     */
    protected $_identity  = 'id';

    /**
     * @var Zend_Db_Adapter_Abstract
     */
    protected $_adapter;

    /**
     * @param Zend_Db_Adapter_Abstract $adapter
     * @return Domain_Mapper_DbTable
     */
    public function setAdapter(Zend_Db_Adapter_Abstract $adapter)
    {
        $this->_adapter = $adapter;
        return $this;
    }

    /**
     * @throws Domain_Mapper_Exception
     * @return Zend_Db_Adapter_Abstract
     */
    public function getAdapter()
    {
        if (!$this->_adapter) {
            throw new Domain_Mapper_Exception("Unable to find suitable adapter");
        }
        return $this->_adapter;
    }

    /**
     * @param string $table New tab;e name
     * @return void
     */
    public function setTable($table)
    {
        $this->_table = $table;
    }

    /**
     * @return string Current table name
     */
    public function getTable()
    {
        return $this->_table;
    }

    /**
     * @codeCoverageIgnore
     * @param mixed $identity
     * @return null|Domain_Entity_Abstract
     */
    public function findByIdentity($identity)
    {
        if (!is_array($this->_identity)) {
            $identity = array($this->_identity => $identity);
        }

        if (($key = $this->getIdentityKey($identity)) &&  $this->isInMap($key))
            return $this->getFromMap($key);

        $stmnt = $this->getAdapter()
                ->select()
                ->from($this->getTable());

        $where = $this->getIdentityClause($identity);
        foreach($where as $clause)
            $stmnt->where($clause);

        $row = $stmnt->query()->fetch();

        if ($row)
            return $this->mapEntityFromRow($row);

        return null;
    }

    /**
     * @codeCoverageIgnore
     * @throws Domain_Mapper_Exception
     * @param $fldName
     * @param $fldVal
     * @param bool $onlyFirst
     * @return Domain_Collection|Domain_Entity_Abstract|null
     */
    public function findByField($fldName, $fldVal, $onlyFirst = false)
    {
        $ent2DbMap = array_flip($this->_map);
        if (!array_key_exists($fldName, $ent2DbMap))
            throw new Domain_Mapper_Exception(__METHOD__ . " Unknown field \"$fldName\"");

        $fldName = $ent2DbMap[$fldName];

        $query = $this->getAdapter()->select()->from($this->getTable())
            ->where("$fldName = ?", $fldVal);

        $hndl = $query->query();

        if ($onlyFirst) {
            return $hndl->rowCount() ? $this->mapEntityFromRow($hndl->fetch()) : null;
        } else {
            return new Domain_Collection($this, $hndl->fetchAll());
        }
    }

// @codeCoverageIgnoreStart
    /**
     * @throws Domain_Exception
     * @param null $order
     * @param null $direction
     * @param null $count
     * @param null $offset
     * @return array
     */
    public function findAll($order = null, $direction = null, $count = null, $offset = null)
    {
        $ent2DbMap = array_flip($this->_map);
        if (!is_null($order) && array_key_exists($order, $ent2DbMap))
            $order = $ent2DbMap[$order];
        else
            throw new Domain_Exception("Unknown order field '$order' for entity {$this->_entityClass}");

        if (!is_null($direction) && in_array(strtolower($direction), array('asc', 'desc'))) {
            $direction = strtolower($direction);
        } else {
            $direction = 'asc';
        }

        $entities = array();
        $handle = $this->getAdapter()
                ->select()
                ->from($this->getTable())
                ->order("$order $direction")
                ->limit($count, $offset)
                ->query();

        while($row = $handle->fetch())
            $entities[] = $this->mapEntityFromRow($row);

        return $entities;
    }
// @codeCoverageIgnoreEnd

    /**
     * @codeCoverageIgnore
     * @param Domain_Entity_Abstract $entity
     * @return void
     */
    public function insert(Domain_Entity_Abstract $entity)
    {
        $this->getAdapter()->insert(
            $this->getTable(),
            $this->mapRowFromEntity($entity)
        );

        $this->setIdentity(
            $entity,
            $this->getAdapter()->lastInsertId($this->getTable())
        );

        $this->addToMap($this->getIdentityKey($entity), $entity);
        $entity->markClean();
    }

    /**
     * @codeCoverageIgnore
     * @param Domain_Entity_Abstract $entity
     * @return void
     */
    public function update(Domain_Entity_Abstract $entity)
    {
        $this->getAdapter()->update(
            $this->getTable(),
            $this->mapRowFromEntity($entity),
            $this->getIdentityClause($this->getIdentity($entity))
        );
        $entity->markClean();
    }

    /**
     * @codeCoverageIgnore
     * @param Domain_Entity_Abstract $entity
     * @return void
     */
    public function delete(Domain_Entity_Abstract $entity)
    {
        $key = $this->getIdentityKey($entity);

        $this->getAdapter()->delete(
            $this->getTable(),
            $this->getIdentityClause(
                $this->getIdentity($entity)
            )
        );
        $this->setIdentity($entity, null);

        if ($this->isInMap($key))
            $this->delFromMap($key);
    }

    /**
     * @throws Domain_Mapper_Exception
     * @param $identity
     * @return array
     */
    public function getIdentityClause($identity)
    {
        $identityFields = (array) $this->_identity;

        if (count($identityFields) == 1 && !is_array($identity)) {
            $identity = array( current($identityFields) => $identity);
        }

        $clause = array();

        foreach($identityFields as $fldName) {
            if (!array_key_exists($fldName, $identity))
                throw new Domain_Mapper_Exception(__METHOD__ . "() Identity field \"$fldName\" is not provided");
            $clause[] = $this->getAdapter()->quoteInto(
                $fldName . ' = ?', $identity[$fldName]
            );
        }
        return $clause;
    }

    /**
     * @throws Domain_Exception
     * @param $entity
     * @return bool
     */
    protected function hasIdentity($entity)
    {
        if (!is_array($entity) && !($entity instanceof Domain_Entity_Abstract))
            throw new Domain_Exception(__METHOD__ . "() Invalid argument");

        if ($entity instanceof Domain_Entity_Abstract)
            $entity = $this->mapRowFromEntity($entity);

        $hasIdentity        = true;
        $identityFields     = (array) $this->_identity;

        foreach($identityFields as $fldName) {
            if (!isset($entity[$fldName]) || !$entity[$fldName]) {
                $hasIdentity = false; break;
            }
        }

        return $hasIdentity;
    }

    /**
     * @throws Domain_Exception
     * @param $entity
     * @return array|mixed
     */
    protected function getIdentity($entity)
    {
        if (!is_array($entity) && !($entity instanceof Domain_Entity_Abstract))
            throw new Domain_Exception(__METHOD__ . "() Invalid argument");

        if ($entity instanceof Domain_Entity_Abstract)
            $entity = $this->mapRowFromEntity($entity);

        $identity = array();
        foreach( (array) $this->_identity as $fldName )
            $identity[$fldName] = $entity[$fldName];

        if (count($identity) == 1){
            reset($identity);
            return current($identity);
        }

        return $identity;
    }

    /**
     * @throws Domain_Exception
     * @param $entity
     * @return null|string
     */
    protected function getIdentityKey($entity)
    {
        if (!is_array($entity) && !($entity instanceof Domain_Entity_Abstract))
            throw new Domain_Exception(__METHOD__ . "() Invalid argument");

        if ($entity instanceof Domain_Entity_Abstract)
            $entity = $this->mapRowFromEntity($entity);

        if ($this->hasIdentity($entity)) {

            $parts  = array_merge((array)$this->_entityClass, (array)$this->getIdentity($entity));
            $key    = implode('_', $parts);

            return $key;

        } else {
            return null;
        }
    }

    /**
     * @param Domain_Entity_Abstract $entity
     * @param $identity
     * @return void
     */
    protected function setIdentity(Domain_Entity_Abstract $entity, $identity)
    {
        $identityFields     = (array) $this->_identity;

        if (count($identityFields) == 1 && !is_array($identity))
            $identity = array($this->_identity => $identity);

        foreach($identityFields as $fldName) {
            $entity->{$this->_map[$fldName]} = $identity[$fldName];
        }
    }

    protected function getDbFieldName($propertyName)
    {
        $flip = array_flip($this->_map);
        if (array_key_exists($propertyName, $flip))
            return $flip[$propertyName];
        return false;
    }

    /**
     * @throws Domain_Exception
     * @param Domain_Criteria_Abstract $criteria
     * @return Zend_Db_Select
     */
    protected function _buildQueryByCriteria(Domain_Criteria_Abstract $criteria)
    {
        throw new Domain_Exception('Unimplemented ' . get_class($this) . '::_buildQueryByCriteria()');
    }

    protected function _addOrderClause(Zend_Db_Select $select, $order, $direction)
    {
        throw new Domain_Exception('Unimplemented ' . get_class($this) . '::_addOrderClause()');
    }

    public function findByCriteria(Domain_Criteria_Abstract $criteria, $order, $direction)
    {
        $select = $this->_buildQueryByCriteria($criteria);

        if (($page = $criteria->getPage())) {
            $select->limit($criteria->getItemCountPerPage(), ($page - 1) * $criteria->getItemCountPerPage());
        }

        $this->_addOrderClause($select, $order, $direction);

        return $select->query()->fetchAll();
    }

    public function getCountByCriteria(Domain_Criteria_Abstract $criteria)
    {
        $select = $this->_buildQueryByCriteria($criteria);
        $select
                ->reset(Zend_Db_Select::COLUMNS)
                ->reset(Zend_Db_Select::LIMIT_COUNT)
                ->reset(Zend_Db_Select::LIMIT_OFFSET)
                ->reset(Zend_Db_Select::ORDER)
                ->columns(new Zend_Db_Expr('COUNT(*)'));
        return $select->query()->fetchColumn();
    }

}
