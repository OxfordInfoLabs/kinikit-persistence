<?php

namespace Kinikit\Persistence\Database\Exception;

class BigQuerySQLException extends SQLException {

    public function __construct(string $rawMessage, int $code) {
        $errorObj = json_decode($rawMessage, true);
        $message = $errorObj ? $errorObj["error"]["errors"][0]["message"] : $rawMessage;
        parent::__construct($message, $code);
    }

}