<?php

namespace Kinikit\Persistence\ORM\Query\Filter;

interface Filter {

    /**
     * @return string
     */
    public function getSQLClause();

    /**
     * @return array
     */
    public function getParameterValues();


}