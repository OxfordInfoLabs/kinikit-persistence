<?php
/**
 * Created by PhpStorm.
 * User: nathanalan
 * Date: 22/08/2018
 * Time: 14:27
 */

namespace Kinikit\Persistence\UPF\Engines\ORM\Query;

use Kinikit\Core\Object\SerialisableObject;

class FilterResults extends SerialisableObject {

    private $results;
    private $count;
    private $pageSize;
    private $page;
    private $hasMoreData;

    public function __construct($results = null, $count = null, $pageSize = null, $page = null, $hasMoreData = null) {
        $this->results = $results;
        $this->count = $count;
        $this->pageSize = $pageSize;
        $this->page = $page;
        $this->hasMoreData = $hasMoreData;
    }

    /**
     * @return mixed
     */
    public function getResults() {
        return $this->results;
    }

    /**
     * @param mixed $results
     */
    public function setResults($results) {
        $this->results = $results;
    }

    /**
     * @return mixed
     */
    public function getCount() {
        return $this->count;
    }

    /**
     * @param mixed $count
     */
    public function setCount($count) {
        $this->count = $count;
    }

    /**
     * @return mixed
     */
    public function getPageSize() {
        return (int)$this->pageSize;
    }

    /**
     * @param mixed $pageSize
     */
    public function setPageSize($pageSize) {
        $this->pageSize = $pageSize;
    }

    /**
     * @return mixed
     */
    public function getPage() {
        return $this->page;
    }

    /**
     * @param mixed $page
     */
    public function setPage($page) {
        $this->page = $page;
    }

    /**
     * @return mixed
     */
    public function getHasMoreData() {
        return $this->hasMoreData;
    }

    /**
     * @param mixed $hasMoreData
     */
    public function setHasMoreData($hasMoreData) {
        $this->hasMoreData = $hasMoreData;
    }

    public function getTotalPages() {
        return round($this->getCount() / $this->getPageSize(), 0);
    }
}