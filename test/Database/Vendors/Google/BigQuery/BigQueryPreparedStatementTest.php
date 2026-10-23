<?php

namespace Kinikit\Persistence\Database\Vendors\Google\BigQuery;

use Google\Cloud\BigQuery\BigQueryClient;
use Google\Cloud\BigQuery\QueryJobConfiguration;
use Kinikit\Persistence\Database\PreparedStatement\BindParameter;
use Kinikit\Persistence\Database\PreparedStatement\BindParameterType;
use PHPUnit\Framework\TestCase;

include_once "autoloader.php";

class BigQueryPreparedStatementTest extends TestCase {

    public function testExecuteUnwrapsBindParametersBeforeSubmittingToBigQuery(): void {
        $queryConfiguration = $this->createMock(QueryJobConfiguration::class);
        $queryConfiguration->expects($this->once())
            ->method("parameters")
            ->with([123, "value"]);

        $client = $this->createMock(BigQueryClient::class);
        $client->expects($this->once())->method("query")->with("SELECT ?, ?")->willReturn($queryConfiguration);
        $client->expects($this->once())->method("runQuery")->with($queryConfiguration);

        $statement = new BigQueryPreparedStatement("SELECT ?, ?", $client);
        $statement->execute([
            new BindParameter("123", BindParameterType::TYPE_INT),
            new BindParameter("value", BindParameterType::TYPE_STRING)
        ]);

        $this->assertSame("SELECT ?, ?", $statement->getStatementSQL());
    }
}
