<?php

class Domain_Criteria_Abstract
{
    protected $page                 = null;

    protected $itemCountPerPage     = 20;

    /**
     * @throws Exception
     * @param mixed|Domain_Entity_Abstract|Domain_Collection $value
     * @param null|string $class
     * @param null|string $field
     * @return mixed
     */
    protected function parseValue($value, $class = null, $field = null)
    {
        if (is_null($value)) {

            return null;

        } elseif (is_scalar($value)) {

            return $value;

        } elseif (is_array($value)) {

            $results = array();
            if (count($value)) foreach($value as $_)
                $results[] = (string) $_;
            return $results;

        } elseif (!is_null($class)) {

            if (is_null($field))
                throw new Exception(__METHOD__ . '() field should be not null when $class is defined');

            if ($value instanceof $class) {

                if (!isset($value->$field))
                    throw new Exception(__METHOD__ . '() ' . $class . '::$'.$field . ' does not defined');

                return $value->$field;

            } elseif ($value instanceof Domain_Collection) {

                /** @var $value Domain_Collection */
                if ($value->getEntityClass() == $class) {
                    $results = array();
                    if (count($value)) foreach($value as $_) {
                        if (!isset($_->$field))
                            throw new Exception(__METHOD__ . '() ' . $class . '::$'.$field . ' does not defined');
                        $results[] = $_->$field;
                    }
                    return $results;
                } else {
                    throw new Exception(__METHOD__ . '() $value does not contains $class');
                }

            }
        } else {
            throw new Exception(__METHOD__ . '() Illegal argument');
        }
    }

    public function setPage($newPage)
    {
        $this->page = $newPage;
    }

    public function getPage()
    {
        return $this->page;
    }

    public function setItemCountPerPage($newCount)
    {
        $this->itemCountPerPage = $newCount;
    }

    public function getItemCountPerPage()
    {
        return $this->itemCountPerPage;
    }
}