<?php

namespace Kinikit\Persistence\Database\Connection;

/**
 * Generic SQL Exception
 *
 */
class SQLException extends \Exception {

    public function __construct($sqlError) {
        parent::__construct($sqlError);
    }

}

?>
