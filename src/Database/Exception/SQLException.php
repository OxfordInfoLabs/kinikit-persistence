<?php

namespace Kinikit\Persistence\Database\Exception;

/**
 * Generic SQL Exception
 *
 */
class SQLException extends \Exception {

    /**
     * @var string
     */
    private $sqlStateCode;

    public function __construct($sqlError, $sqlStateCode = null) {
        parent::__construct($sqlError);
        $this->sqlStateCode = $sqlStateCode;
    }

    /**
     * @return mixed
     */
    public function getSqlStateCode() {
        return $this->sqlStateCode;
    }


}

?>
