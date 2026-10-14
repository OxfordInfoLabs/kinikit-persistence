<?php

namespace Kinikit\Persistence\Database\Connection;

use Kinikit\Persistence\Database\PreparedStatement\PreparedStatement;

class TestPreparedStatement implements PreparedStatement {

    public function getStatementSQL(): string {
        // TODO: Implement getStatementSQL() method.
    }

    public function execute($parameterValues): void {
        // TODO: Implement execute() method.
    }

    public function close(): void {
        // TODO: Implement close() method.
    }
}