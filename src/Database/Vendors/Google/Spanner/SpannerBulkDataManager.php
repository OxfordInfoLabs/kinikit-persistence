<?php

namespace Kinikit\Persistence\Database\Vendors\Google\Spanner;

use Google\Cloud\Spanner\Date;
use Google\Cloud\Spanner\KeySet;
use Google\Cloud\Spanner\Timestamp;
use Kinikit\Core\Logging\Logger;
use Kinikit\Persistence\Database\BulkData\DefaultBulkDataManager;
use Kinikit\Persistence\Database\MetaData\TableColumn;
use Kinikit\Persistence\Database\PreparedStatement\BindParameter;
use Kinikit\Persistence\Database\PreparedStatement\BindParameterType;

class SpannerBulkDataManager extends DefaultBulkDataManager {

    /**
     * @param string $tableName
     * @param array $rows
     * @param array $insertColumns
     * @param bool $ignoreDuplicates
     * @return void
     */
    public function doInsert($tableName, $rows, $insertColumns, $ignoreDuplicates = false) {

        Logger::log($tableName);
        Logger::log($rows);
        Logger::log($insertColumns);

        $bindTypes = $this->getColumnBindTypes($tableName);

        while ($slice = array_splice($rows, 0, $this->batchSize)) {
            $mutations = [];

            foreach ($slice as $sliceItem) {
                $rowData = [];
                foreach ($insertColumns as $column) {
                    $rowData[$column] = $this->bindMutationValue($sliceItem[$column] ?? null, $bindTypes[$column] ?? null);
                }
                $mutations[] = $rowData;
            }

            $method = $ignoreDuplicates ? 'insertOrUpdateBatch' : 'insertBatch';

            Logger::log($mutations);

            $spannerConnection = $this->databaseConnection->getSpannerConnection();
            $spannerConnection->runTransaction(function ($transaction) use ($tableName, $mutations, $method) {
                $transaction->$method($tableName, $mutations);
                $transaction->commit();
            });
        }
    }

    /**
     * @param string $tableName
     * @param array $rows
     * @param array $updateColumns
     * @param string[] $matchColumns
     * @return void
     */
    public function doUpdate($tableName, $rows, $updateColumns, $matchColumns) {

        $allColumns = array_unique(array_merge($matchColumns, $updateColumns));
        $bindTypes = $this->getColumnBindTypes($tableName);

        while ($slice = array_splice($rows, 0, $this->batchSize)) {
            $mutations = [];

            foreach ($slice as $row) {
                $rowData = [];
                foreach ($allColumns as $column) {
                    $rowData[$column] = $this->bindMutationValue($row[$column] ?? null, $bindTypes[$column] ?? null);
                }
                $mutations[] = $rowData;
            }

            $spannerConnection = $this->databaseConnection->getSpannerConnection();
            $spannerConnection->runTransaction(function ($transaction) use ($tableName, $mutations) {
                $transaction->updateBatch($tableName, $mutations);
                $transaction->commit();
            });
        }

    }

    /**
     * @param $tableName
     * @param $rows
     * @param $replaceColumns
     * @return void
     */
    public function doReplace($tableName, $rows, $replaceColumns) {
        $bindTypes = $this->getColumnBindTypes($tableName);

        while ($slice = array_splice($rows, 0, $this->batchSize)) {
            $mutations = [];

            foreach ($slice as $row) {
                $rowData = [];
                foreach ($replaceColumns as $column) {
                    $rowData[$column] = $this->bindMutationValue($row[$column] ?? null, $bindTypes[$column] ?? null);
                }
                $mutations[] = $rowData;
            }

            $spannerConnection = $this->databaseConnection->getSpannerConnection();
            $spannerConnection->runTransaction(function ($transaction) use ($tableName, $mutations) {
                $transaction->insertOrUpdateBatch($tableName, $mutations);
                $transaction->commit();
            });
        }
    }

    /**
     * @param $tableName
     * @param $pkValues
     * @param $matchColumns
     * @return void
     */
    public function doDelete($tableName, $pkValues, $matchColumns) {
        $bindTypes = $this->getColumnBindTypes($tableName);

        while ($slice = array_splice($pkValues, 0, $this->batchSize)) {
            $keys = [];

            foreach ($slice as $pkRow) {
                if (is_array($pkRow)) {
                    $keyGroup = [];
                    foreach ($matchColumns as $column) {
                        $keyGroup[] = $this->bindMutationValue($pkRow[$column] ?? null, $bindTypes[$column] ?? null);
                    }
                    $keys[] = $keyGroup;
                } else {
                    $keys[] = $this->bindMutationValue($pkRow, $bindTypes[$matchColumns[0]] ?? null);
                }
            }

            $keySet = new KeySet(['keys' => $keys]);

            $spannerConnection = $this->databaseConnection->getSpannerConnection();
            $spannerConnection->runTransaction(function ($transaction) use ($tableName, $keySet) {
                $transaction->delete($tableName, $keySet);
                $transaction->commit();
            });
        }
    }


    /**
     * Resolve column types once per bulk operation so date strings can be converted
     * to the values expected by the Spanner mutation API.
     *
     * @param string $tableName
     * @return BindParameterType[]
     */
    private function getColumnBindTypes(string $tableName): array {
        $bindTypes = [];
        foreach ($this->databaseConnection->getTableMetaData($tableName)->getColumns() as $column) {
            $bindType = match ($column->getType()) {
                TableColumn::SQL_DATE => BindParameterType::TYPE_DATE,
                TableColumn::SQL_DATE_TIME, TableColumn::SQL_TIMESTAMP => BindParameterType::TYPE_DATETIME,
                default => null
            };

            if ($bindType) {
                $bindTypes[$column->getName()] = $bindType;
            }
        }
        return $bindTypes;
    }


    private function bindMutationValue(mixed $value, ?BindParameterType $columnType): mixed {
        if ($value instanceof Date || $value instanceof Timestamp) {
            return $value;
        }

        if ($value instanceof BindParameter) {
            return SpannerPreparedStatement::bindParameter($value);
        }

        if ($columnType) {
            return SpannerPreparedStatement::bindParameter(new BindParameter($value, $columnType));
        }

        return $value;
    }

}