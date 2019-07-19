<?php

namespace Kinikit\Persistence\Database\Util;

use Kinikit\Persistence\Database\Connection\DatabaseConnection;
use Kinikit\Persistence\Database\Connection\DefaultDB;
use Kinikit\Persistence\Database\Exception\SQLException;


/**
 * Sequence generator for generating auto_increment style sequences if required.  This is used for example to generate
 * unique primary keys for the object index if required.
 *
 * @author mark
 *
 */
class SequenceGenerator {

    private static $instance;
    private $databaseConnection;

    /**
     * Construct with the database connection to use for this sequence generator
     *
     * @param DatabaseConnection $databaseConnection
     */
    public function __construct($databaseConnection) {
        $this->databaseConnection = $databaseConnection;
    }

    /**
     * Get the default singleton instance of this generator for convenience using the default database connection
     *
     * @return SequenceGenerator
     */
    public static function defaultInstance() {
        if (!SequenceGenerator::$instance) {
            SequenceGenerator::$instance = new SequenceGenerator (DefaultDB::instance());
        }

        return SequenceGenerator::$instance;
    }

    /**
     * Get the current value for the supplied sequence.  Create the sequence if necessary
     */
    public function getCurrentSequenceValue($sequenceName) {
        return $this->ensureTableAndSequenceExists($sequenceName);
    }

    /**
     * Increment the sequence for the given sequence (by name) and return the new sequence number.
     * Create the sequence if necessary.
     *
     * @param string $sequenceName
     */
    public function incrementSequence($sequenceName) {
        $newValue = $this->ensureTableAndSequenceExists($sequenceName) + 1;
        $this->databaseConnection->query("UPDATE kinikit_sequence SET current_value = " . $newValue . " WHERE sequence_name = '" . $sequenceName . "'",);
        return $newValue;
    }

    // Ensure that the sequence table exists.  Return the current value from the sequence or 0 if a new one.
    private function ensureTableAndSequenceExists($sequenceName) {

        $this->databaseConnection->query("CREATE TABLE IF NOT EXISTS kinikit_sequence (sequence_name VARCHAR(255), current_value INTEGER)",);


        $currentValue = $this->databaseConnection->queryForSingleValue("SELECT current_value FROM kinikit_sequence WHERE sequence_name = '" . $sequenceName . "'");
        if (!is_numeric($currentValue)) {
            $this->databaseConnection->query("INSERT INTO kinikit_sequence (sequence_name, current_value) values ('" . $sequenceName . "', 0)",);
            $currentValue = 0;
        }

        return $currentValue;
    }

}

?>
