<?php

namespace Kinikit\Persistence\Database\Exception;

class SpannerSQLException extends SQLException {

    public function __construct(string $rawMessage, int $code) {
        parent::__construct($rawMessage, $code);
    }

}