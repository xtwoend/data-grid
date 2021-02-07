<?php

namespace Xtwoend\DataGrid;

use Exception;
use Illuminate\Http\Request as IlluminateRequest;

/**
 * Class Request.
 *
 * @package Xtwoend\DataGrid
 * @method input($key, $default = null)
 * @method has($key)
 * @method query($key, $default = null)
 * @author  Arjay Angeles <aqangeles@gmail.com>
 */
class Request
{
    /**
     * @var \Illuminate\Http\Request
     */
    protected $request;

    /**
     * Request constructor.
     *
     * @param \Illuminate\Http\Request $request
     */
    public function __construct(IlluminateRequest $request)
    {
        $this->request = $request;
    }

    /**
     * Proxy non existing method calls to request class.
     *
     * @param mixed $name
     * @param mixed $arguments
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        if (method_exists($this->request, $name)) {
            return call_user_func_array([$this->request, $name], $arguments);
        }

        return null;
    }

    /**
     * Get attributes from request instance.
     *
     * @param string $name
     * @return mixed
     */
    public function __get($name)
    {
        return $this->request->__get($name);
    }

    /**
     * Get all columns request input.
     *
     * @return array
     */
    public function columns()
    {   
        $filters = [];
        if($this->request->has('filters') && !empty($this->request->input('filters')))
        {
            $filters = json_decode(str_replace('\'','"',$this->request->filters), true);
        }

        return (array) $filters;
    }

    /**
     * Check if request uses legacy code
     *
     * @throws Exception
     */
    public function checkLegacyCode()
    {
        if (! $this->request->input('draw') && $this->request->input('sEcho')) {
            throw new Exception('DataTables legacy code is not supported! Please use DataTables 1.10++ coding convention.');
        } elseif (! $this->request->input('draw') && ! $this->request->input('columns')) {
            throw new Exception('Insufficient parameters');
        }
    }

    /**
     * Check if Datatables is searchable.
     *
     * @return bool
     */
    public function isSearchable()
    {
        return $this->request->input('_search');
    }

    /**
     * Check if Datatables must uses regular expressions
     *
     * @param integer $index
     * @return string
     */
    public function isRegex($index)
    {
        return $this->request->input("columns.$index.search.regex") === 'true';
    }

    /**
     * Get orderable columns
     *
     * @return array
     */
    public function orderableColumns()
    {   
        if (! $this->isOrderable()) {
            return [];
        }
        
        $orderable[] = ['column' => $this->request->get('sidx'), 'direction' => $this->request->get('sord', 'asc')];

        return $orderable;
    }

    /**
     * Check if Datatables ordering is enabled.
     *
     * @return bool
     */
    public function isOrderable()
    {
        return $this->request->has('sidx') && !is_null($this->request->get('sidx'));
    }

    /**
     * Check if a column is orderable.
     *
     * @param  integer $index
     * @return bool
     */
    public function isColumnOrderable($index)
    {
        return $this->request->input("columns.$index.orderable") == 'true';
    }

    /**
     * Get searchable column indexes
     *
     * @return array
     */
    public function searchableColumnIndex()
    {
        $searchable = [];
        for ($i = 0, $c = count($this->request->input('filters')); $i < $c; $i++) {
            if ($this->isColumnSearchable($i, false)) {
                $searchable[] = $i;
            }
        }

        return $searchable;
    }

    /**
     * Check if a column is searchable.
     *
     * @param integer $i
     * @param bool $column_search
     * @return bool
     */
    public function isColumnSearchable($i, $column_search = true)
    {
        if ($column_search) {
            return $this->request->input("columns.$i.searchable") === 'true' && $this->columnKeyword($i) != '';
        }

        return $this->request->input("columns.$i.searchable") === 'true';
    }

    /**
     * Get column's search value.
     *
     * @param integer $index
     * @return string
     */
    public function columnKeyword($index)
    {
        $filters = $this->columns();
        if(empty($filters['rules'])){
            return;
        }

        $filter = $filters['rules'][$index];
        
        $filter = $this->searchFilter($filter);
        
        return $filter['data'];
    }

    /**
     * Get global search keyword
     *
     * @return string
     */
    public function keyword()
    {
        return $this->request->input('search.value');
    }

    /**
     * Get column identity from input or database.
     *
     * @param integer $i
     * @return string
     */
    public function columnName($i)
    {
        $column = $this->request->input("columns.$i");

        return isset($column['name']) && $column['name'] <> '' ? $column['name'] : $column['data'];
    }

    /**
     * Check if Datatables allow pagination.
     *
     * @return bool
     */
    public function isPaginationable()
    {
        return ! is_null($this->request->input('page')) && ! is_null($this->request->input('rows')) && $this->request->input('rows') != -1;
    }

    public function searchFilter($filter)
    {
        switch ($filter['op'])
        {
            case 'eq': //equal
                $filter['op'] = '=';
                break;
            case 'ne': //not equal
                $filter['op'] = '!=';
                break;
            case 'lt': //less
                $filter['op'] = '<';
                break;
            case 'le': //less or equal
                $filter['op'] = '<=';
                break;
            case 'gt': //greater
                $filter['op'] = '>';
                break;
            case 'ge': //greater or equal
                $filter['op'] = '>=';
                break;
            case 'bw': //begins with
                $filter['op'] = 'like';
                $filter['data'] = $filter['data'] . '%';
                break;
            case 'bn': //does not begin with
                $filter['op'] = 'not like';
                $filter['data'] = $filter['data'] . '%';
                break;
            case 'in': //is in
                $filter['op'] = 'is in';
                break;
            case 'ni': //is not in
                $filter['op'] = 'is not in';
                break;
            case 'ew': //ends with
                $filter['op'] = 'like';
                $filter['data'] = '%' . $filter['data'];
                break;
            case 'en': //does not end with
                $filter['op'] = 'not like';
                $filter['data'] = '%' . $filter['data'];
                break;
            case 'cn': //contains
                $filter['op'] = 'like';
                $filter['data'] = '%' . str_replace(' ', '%', $filter['data']) . '%';
                break;
            case 'cnpg': //contains PostgreSQL
                $filter['op'] = 'ilike';
                $filter['data'] = '%' . str_replace(' ', '%', $filter['data']) . '%';
                break;
            case 'nc': //does not contains
                $filter['op'] = 'not like';
                $filter['data'] = '%' . $filter['data'] . '%';
                break;
            case 'nu': //is null
                $filter['op'] = 'is null';
                $filter['data'] = '';
                break;
            case 'nn': //is not null
                $filter['op'] = 'is not null';
                $filter['data'] = '';
                break;
            case 'btw': //between
                $filter['op'] = 'between';
                break;
        }

        return $filter;
    }
}
