<?php

declare(strict_types=1);

namespace Olobase\DataTable;

use Laminas\Db\Sql\Expression;
use Laminas\Db\Sql\SqlInterface;
use Laminas\Db\Adapter\AdapterInterface;
use Olobase\Exception\MethodMandatoryException;

/**
 * @author Oloma <support@oloma.dev>
 *
 * Column filters
 */
class ColumnFilters implements ColumnFiltersInterface
{
    protected $adapter;
    protected $select;
    protected $data = array();
    protected $alias = array();
    protected $columns = array();
    protected $groupedColumns = array();
    protected $columnData = array();
    protected $searchData = array();
    protected $likeColumns = array();
    protected $whereColumns = array();
    protected $searchColumns = array();
    protected $likeData = array();
    protected $whereData = array();
    protected $orderData = array();

    /**
     * Constructor
     */
    public function __construct(AdapterInterface $adapter)
    {
        $this->adapter = $adapter;
    }

    /**
     * Reset column filter object
     *
     * @return void
     */
    public function clear()
    {
        $this->data = array();
        $this->columns = array();
        $this->groupedColumns = array();
        $this->alias = array();
        $this->columnData = array();
        $this->searchData = array();
        $this->likeColumns = array();
        $this->whereColumns = array();
        $this->searchColumns = array();
        $this->orderData = array();
        return $this;
    }

    /**
     * Set columns
     *
     * @param object $select
     */
    public function setSelect(SqlInterface $select)
    {
        $this->select = $select;
        return $this;
    }

    /**
     * Set columns
     *
     * @param object $select
     */
    public function getSelect() : SqlInterface
    {
        return $this->select;
    }

    /**
     * Set columns
     *
     * @param array $columns columns
     */
    public function setColumns(array $columns)
    {
        foreach ($columns as $name) {
            $this->columns[(string)$name] = (string)$name;
        }
        return $this;
    }

    /**
     * Set search columns
     * 
     * @param array $columns
     */
    public function setSearchColumns(array $columns)
    {
        foreach ($columns as $name) {
            $this->searchColumns[(string)$name] = (string)$name;
        }
        return $this;
    }

    /**
     * Set like columns
     * 
     * @param array $columns
     */
    public function setLikeColumns(array $columns)
    {
        foreach ($columns as $name) {
            $this->likeColumns[(string)$name] = (string)$name;
        }
        return $this;
    }

    /**
     * Set where columns
     * 
     * @param array $columns
     */
    public function setWhereColumns(array $columns)
    {
        foreach ($columns as $name) {
            $this->whereColumns[(string)$name] = (string)$name;
        }
        return $this;
    }

    /**
     * Unset columns
     * 
     * @param  array  $columns columns
     */
    public function unsetColumns(array $columns)
    {
        foreach ($columns as $name) {
            unset($this->columns[$name]);
        }
        return $this;
    }

    /**
     * Set sql alias : CONCAT(u.firstname ,' ', u.lastname) AS name
     *
     * @param string $name  requested column name
     * @param string $alias
     */
    public function setAlias(string $name, $alias)
    {
        if ($alias instanceof Expression) {
            $values = $alias->getExpressionData();
            if (is_array($values[0]) && empty($values[0][1])) {
                $quotedValues = array_map(function($v) {
                        return is_string($v) ? "'".$v."'" : $v;
                    },
                    $values[0][1]
                );
                $this->alias[$name] = vsprintf($values[0][0], $quotedValues);
            } else if (false == empty($values[0]) && is_string($values[0])) {
                $this->alias[$name] = $values[0]; // without arguments ...
            }
        } else {
            $this->alias[$name] = $alias;
        }
        return $this;
    }

    /**
     * Set grouped columns
     * 
     * @param string         $groupName  name
     * @param array          $columns    column names
     * @param callable|null  $returnFunc null|callable
     */
    public function setGroupedColumns(
        string $groupName,
        array $columns,
        $returnFunc = null
    ) {
        if (is_null($returnFunc)) {
            $returnFunc = function($val) {
                return (bool)$val;
            };
        }
        foreach ($columns as $name) {
            $this->groupedColumns[$name] = [
                'groupName' => $groupName,
                'callable' => $returnFunc
            ];
        }
        return $this;
    }

    /**
     * Returns to normalized data
     * 
     * @return array
     */
    public function getRawData(): array
    {
        $data = $this->getData();
        $newData = array();
        if (! empty($this->columns)) {
            foreach ($this->columns as $name => $value) {
                if (empty($name)) {
                    break;
                }
                if (empty($value) != '') {  // filter columns
                    $newData[$name] = $value;
                }
            }
        }
        return $newData;
    }

    /**
     * Set filter data (GET or POST)
     *
     * @param array $data
     */
    public function setData(array $data)
    {
        $searchWords = array();
        if (! empty($data['q']) && strlen($data['q']) > 0) {
            $searchStr = $data['q'];
            $searchWords = explode(' ', $searchStr);
        }
        $this->data = $data;
        $platform = $this->adapter->getPlatform();
        //
        // Search data
        // 
        foreach ($this->searchColumns as $name) {
            if (! empty($searchWords)) {  // search data for all columns
                if (array_key_exists($name, $this->alias)) { // sql function support
                    $this->searchData[$this->alias[$name]] = $searchWords;
                } else {
                    $colName = $platform->quoteIdentifier($name);
                    $this->searchData[$colName] = $searchWords;
                }
            }
        }
        unset($name);
        //
        // Like data
        // 
        foreach ($this->likeColumns as $name) {
            if (array_key_exists($name, $data)) {
                $this->setColumnData($name, $data[$name], 'like');
            }
        }
        unset($name);
        //
        // Where data
        // 
        foreach ($this->whereColumns as $name) {
            if (array_key_exists($name, $data)) {
                $this->setColumnData($name, $data[$name], 'where');
            }
        }
        //
        // Grouped where data
        // 
        unset($name);
        foreach ($this->groupedColumns as $name => $props) {
            $groupName = $props['groupName'];
            if (false == empty($data[$groupName]) 
                && in_array($name, $data[$groupName])
            ) {
                $returnClosure = $props['callable'];
                $this->setColumnData($name, $returnClosure($name), 'where');
            }
        }
        //
        // Sort data
        // 
        if (! empty($data['_sort'])) {
            $o = 0;
            foreach ($data['_sort'] as $colName) {
                if (false == empty($colName) && isset($this->columns[$colName]) && false == empty($data['_order'])) {
                    $direction = (strtolower($data['_order'][$o]) == 'asc') ? 'ASC' : 'DESC';
                    $formattedColName = empty($this->alias[$colName]) ? $colName : $this->alias[$colName];
                    $this->orderData[$o] = $formattedColName.' '.$direction;
                    ++$o;
                }
            }
        }
        return $this;
    }

    /**
     * Set where or like data
     * 
     * @param string $name      col key
     * @param string $value     data value
     * @param string $direction 'where' or 'like'
     * @param mixed $value      default value
     */
    protected function setColumnData($name, $value = null, $direction = 'where')
    {
        $platform = $this->adapter->getPlatform();
        $colValue = is_null($value) ? $data[$name] : $value;

        if (isset($this->alias[$name])) { // sql function support
            $funcName = $this->alias[$name];
            switch ($value) { // boolean support
                case 'true':
                    $this->{$direction."Data"}[$funcName] = 1;
                    break;
                case 'false':
                    $this->{$direction."Data"}[$funcName] = 0;
                    break;
                default:
                    $this->{$direction."Data"}[$funcName] = Self::normalizeData($colValue);
                    break;
            }
        } else {
            $colName = $platform->quoteIdentifier($name);
            switch ($value) { // boolean support
                case 'true':
                    $this->{$direction."Data"}[$colName] = 1;
                    break;
                case 'false':
                    $this->{$direction."Data"}[$colName] = 0;
                    break;
                default:
                    $this->{$direction."Data"}[$colName] = Self::normalizeData($colValue);
                    break;
            }
        }
    }

    /**
     * Set date filter for date columns
     * 
     * @param string $dateColumnName column name
     * @param string $endDateColumnName if exists
     * @param string $fixedDateColumnName if fixed date exists do the query with it
     */
    public function setDateFilter($dateColumnName, $endDateColumnName = null, $fixedDateColumnName = null)
    {
        $this->checkSelect();
        $data = $this->getData();

        $dateColumn = $dateColumnName;
        $endDate = $endDateColumnName;
        $fixedDate = $fixedDateColumnName;
        if (isset($this->alias[$dateColumnName])) {
            $dateColumn = $this->alias[$dateColumnName];
        }
        if (isset($this->alias[$endDate])) {
            $endDate = $this->alias[$endDate];
        }
        $columnStart = $dateColumnName.'Start';
        $columnEnd = $dateColumnName.'End';

        // "between" date filter
        // 
        if (empty($endDateColumnName)) {
            $dateColumn = Self::removeQuotes($dateColumn);
            if (! empty($data[$columnStart]) && empty($data[$columnEnd])) {
                $nest = $this->select->where->nest();
                    $nest->and->equalTo($dateColumn, $data[$columnStart]);
                $nest->unnest();
            } else if (! empty($data[$columnEnd]) && empty($data[$columnStart])) {
                $nest = $this->select->where->nest();
                    $nest->and->equalTo($dateColumn, $data[$columnEnd]);
                $nest->unnest();
            } else if (! empty($data[$columnEnd]) && ! empty($data[$columnStart])) {
                $nest = $this->select->where->nest();
                    $nest->and->between($dateColumn, $data[$columnStart], $data[$columnEnd]);
                $nest->unnest();    
            }
        } else {  // equality & fixed date filter
            $columnStart = $dateColumn;
            $columnEnd = $endDate;
            $startKey = Self::removeQuotes($columnStart);
            $endKey = Self::removeQuotes($columnEnd);
            $fixedKey = Self::removeQuotes($fixedDate);
            if ($fixedDate && ! empty($data[$fixedKey])) {
                $nest = $this->select->where->nest();
                    $nest->and->lessThanOrEqualTo($columnStart, $data[$fixedKey])
                         ->and->greaterThanOrEqualTo($columnEnd, $data[$fixedKey]);
                $nest->unnest();    
            } else {
                if (! empty($data[$startKey]) && empty($data[$endKey])) {
                    $nest = $this->select->where->nest();
                        $nest->and->equalTo($columnStart, $data[$startKey]);
                    $nest->unnest();
                } else if (! empty($data[$endKey]) && empty($data[$startKey])) {
                    $nest = $this->select->where->nest();
                        $nest->and->equalTo($columnEnd, $data[$endKey]);
                    $nest->unnest();
                } else if (! empty($data[$startKey]) && ! empty($data[$endKey])) {
                    $nest = $this->select->where->nest();
                        $nest->and->lessThanOrEqualTo($columnStart, $data[$endKey])
                             ->and->greaterThanOrEqualTo($columnEnd, $data[$startKey]);
                    $nest->unnest();    
                }
            }
        }
    }

    /**
     * Check select object is empty
     * 
     * @return void
     */
    protected function checkSelect()
    {
        if (empty($this->select)) {
            throw new MethodMandatoryException(
                sprintf(
                    'Select object cannot be empty. Please use: %s',
                    '$this->columnFilters->setSelect($select)'
                )
            );
        }
    }

    /**
     * Returns to filtered column => value
     *
     * @return array
     */
    public function getColumnData() : array
    {
        return $this->columnData;
    }

    /**
     * Returns to "like" data column => value
     *
     * @return array
     */
    public function getLikeData() : array
    {
        return $this->likeData;
    }

    /**
     * Returns to "where" data column => value
     *
     * @return array
     */
    public function getWhereData() : array
    {
        return $this->whereData;
    }

    /**
     * Returns to unfiltered data
     *
     * @return array
     */
    public function getData() : array
    {
        return $this->data;
    }

    /**
     * Returns to filtered order data: [name ASC, email DESC]
     *
     * @return array
     */
    public function getOrderData() : array
    {
        return $this->orderData;
    }

    /**
     * Returns to search data: columns => array('str1', 'str2')
     *
     * @return array
     */
    public function getSearchData() : array
    {
        return $this->searchData;
    }

    /**
     * Returns to true if not empty otherwise false
     *
     * @return boolean
     */
    public function searchDataIsNotEmpty() : bool
    {
        $this->checkSelect();
        if (false == empty($this->searchData)) {
            return true;
        }
        return false;
    }

    /**
     * Returns to true if empty otherwise false
     *
     * @return boolean
     */
    public function searchDataEmpty() : bool
    {
        $this->checkSelect();
        if (empty($this->searchData)) {
            return true;
        }
        return false;
    }

    /**
     * Returns to true if not empty otherwise false
     *
     * @return boolean
     */
    public function likeDataIsEmpty() : bool
    {
        $this->checkSelect();
        if (empty($this->likeData)) {
            return true;
        }
        return false;
    }

    /**
     * Returns to true if not empty otherwise false
     *
     * @return boolean
     */
    public function likeDataIsNotEmpty() : bool
    {
        $this->checkSelect();
        if (false == empty($this->likeData)) {
            return true;
        }
        return false;
    }

    /**
     * Returns to true if empty otherwise false
     *
     * @return boolean
     */
    public function whereDataIsEmpty() : bool
    {
        $this->checkSelect();
        if (empty($this->whereData)) {
            return true;
        }
        return false;
    }
    /**
     * Returns to true if not empty otherwise false
     *
     * @return boolean
     */
    public function whereDataIsNotEmpty() : bool
    {
        $this->checkSelect();
        if (false == empty($this->whereData)) {
            return true;
        }
        return false;
    }

    /**
     * Returns to true if empty otherwise false
     *
     * @return boolean
     */
    public function orderDataIsEmpty() : bool
    {
        $this->checkSelect();
        if (empty($this->orderData)) {
            return true;
        }
        return false;
    }

    /**
     * Returns to true if not empty otherwise false
     *
     * @return boolean
     */
    public function orderDataIsNotEmpty() : bool
    {
        $this->checkSelect();
        if (false == empty($this->orderData)) {
            return true;
        }
        return false;
    }

    /**
     * Remove key
     * 
     * @param  string $key 
     */
    protected static function removeQuotes($key)
    {
        if (is_string($key)) {
            $key = str_replace(["'","`",'"'], "", $key);   
        }
        return $key;
    }

    /**
     * Returns to colum names
     *
     * @return array
     */
    public function getColumns() : array
    {
        return $this->columns;
    }

    /**
     * Filter data for "id" values
     * 
     * @param  array $data 
     * @return array
     */
    protected static function normalizeData($data)
    {
        $newData = array();
        if (is_array($data) && false == empty($data[0]['id'])) {
            $i = 0;
            foreach ($data as $val) {
                if (false == empty($val['id'])) {
                  $newData[$i] = $val['id'];
                  ++$i;
                }
            } 
            return $newData;
        }
        return $data;
    }

}
