<?php

namespace Kinikit\Persistence\Database\Vendors\Google\Spanner;

use Google\Cloud\Spanner\KeySet;
use Kinikit\Persistence\Database\BulkData\DefaultBulkDataManager;

class SpannerBulkDataManager extends DefaultBulkDataManager {

    /**
     * @param string $tableName
     * @param array $rows
     * @param array $insertColumns
     * @param bool $ignoreDuplicates
     * @return void
     */
    public function doInsert($tableName, $rows, $insertColumns, $ignoreDuplicates = false) {

        while ($slice = array_splice($rows, 0, $this->batchSize)) {
            $mutations = [];

            foreach ($slice as $sliceItem) {
                $rowData = [];
                foreach ($insertColumns as $column) {
                    $rowData[$column] = $sliceItem[$column] ?? null;
                }
                $mutations[] = $rowData;
            }

            $method = $ignoreDuplicates ? 'insertOrUpdate' : 'insert';

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

        while ($slice = array_splice($rows, 0, $this->batchSize)) {
            $mutations = [];

            foreach ($slice as $row) {
                $rowData = [];
                foreach ($allColumns as $column) {
                    $rowData[$column] = $row[$column] ?? null;
                }
                $mutations[] = $rowData;
            }

            $spannerConnection = $this->databaseConnection->getSpannerConnection();
            $spannerConnection->runTransaction(function ($transaction) use ($tableName, $mutations) {
                $transaction->update($tableName, $mutations);
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
        while ($slice = array_splice($rows, 0, $this->batchSize)) {
            $mutations = [];

            foreach ($slice as $row) {
                $rowData = [];
                foreach ($replaceColumns as $column) {
                    $rowData[$column] = $row[$column] ?? null;
                }
                $mutations[] = $rowData;
            }

            $spannerConnection = $this->databaseConnection->getSpannerConnection();
            $spannerConnection->runTransaction(function ($transaction) use ($tableName, $mutations) {
                $transaction->insertOrUpdate($tableName, $mutations);
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
        while ($slice = array_splice($pkValues, 0, $this->batchSize)) {
            $keys = [];

            foreach ($slice as $pkRow) {
                if (is_array($pkRow)) {
                    $keyGroup = [];
                    foreach ($matchColumns as $column) {
                        $keyGroup[] = $pkRow[$column] ?? null;
                    }
                    $keys[] = $keyGroup;
                } else {
                    $keys[] = $pkRow;
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

}