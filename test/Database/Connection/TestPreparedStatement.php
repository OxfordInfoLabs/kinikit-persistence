<?php

namespace Kinikit\Persistence\Database\Connection;

use Kinikit\Persistence\Database\PreparedStatement\PreparedStatement;

class TestPreparedStatement implements PreparedStatement {

    public $executedParameters;
    public $closed = false;

    public function getStatementSQL(): string {
        // TODO: Implement getStatementSQL() method.
    }

    public function execute(array $parameterValues): void {
        $this->executedParameters = $parameterValues;
    }

    public function close(): void {
        $this->closed = true;
    }
}