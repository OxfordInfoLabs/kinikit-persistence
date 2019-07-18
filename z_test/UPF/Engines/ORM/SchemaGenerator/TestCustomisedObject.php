<?php


namespace Kinikit\Persistence\UPF\Engines\ORM\SchemaGenerator;


use Kinikit\Persistence\UPF\Object\ActiveRecord;

/**
 *
 * @ormTable my_funny_table
 */
class TestCustomisedObject extends ActiveRecord {

    /**
     * @var string
     * @primaryKey
     */
    private $name;


    /**
     * @var string
     * @primaryKey
     * @ormType DATE
     */
    private $dob;


    /**
     * @var string
     * @ormType VARCHAR(1000)
     *
     */
    private $description;


    /**
     * @var string
     * @ormType LONGTEXT
     */
    private $comments;

    /**
     * @var string
     * @ormType DATETIME
     * @validation required
     */
    private $lastUpdated;

    /**
     * @return string
     */
    public function getName() {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName($name) {
        $this->name = $name;
    }

    /**
     * @return string
     */
    public function getDob() {
        return $this->dob;
    }

    /**
     * @param string $dob
     */
    public function setDob($dob) {
        $this->dob = $dob;
    }

    /**
     * @return string
     */
    public function getComments() {
        return $this->comments;
    }

    /**
     * @param string $comments
     */
    public function setComments($comments) {
        $this->comments = $comments;
    }

    /**
     * @return string
     */
    public function getLastUpdated() {
        return $this->lastUpdated;
    }

    /**
     * @param string $lastUpdated
     */
    public function setLastUpdated($lastUpdated) {
        $this->lastUpdated = $lastUpdated;
    }

    /**
     * @return string
     */
    public function getDescription() {
        return $this->description;
    }

    /**
     * @param string $description
     */
    public function setDescription($description) {
        $this->description = $description;
    }


}
