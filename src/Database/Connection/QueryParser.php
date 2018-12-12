<?php

namespace Kinikit\Persistence\Database\Connection;

/**
 * Query Parser interface for implementing database specific query parsers if required.
 *
 * To change this template use File | Settings | File Templates.
 */
interface QueryParser {

    public function parse($sql);

}
