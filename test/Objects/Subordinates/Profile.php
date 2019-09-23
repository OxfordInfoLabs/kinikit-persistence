<?php


namespace Kinikit\Persistence\Objects\Subordinates;

/**
 * Profile class
 *
 * Class Profile
 * @package Kinikit\Persistence\ORM
 */
class Profile {

    /**
     * @var integer
     */
    private $id;

    /**
     * @sqlType DATE
     * @var \DateTime
     */
    private $dateOfBirth;

    /**
     * @var \DateTime
     */
    private $instantiated;


    /**
     * @json
     * @sqlType VARCHAR(500)
     * @var array
     */
    private $data;

    /**
     * Profile constructor.
     * @param int $id
     * @param \DateTime $dateOfBirth
     * @param \DateTime $instantiated
     */
    public function __construct($id, $dateOfBirth, $instantiated) {
        $this->id = $id;
        $this->dateOfBirth = $dateOfBirth;
        $this->instantiated = $instantiated;
    }


    /**
     * @return int
     */
    public function getId(): int {
        return $this->id;
    }

    /**
     * @param int $id
     */
    public function setId(int $id): void {
        $this->id = $id;
    }

    /**
     * @return \DateTime
     */
    public function getDateOfBirth(): \DateTime {
        return $this->dateOfBirth;
    }

    /**
     * @param \DateTime $dateOfBirth
     */
    public function setDateOfBirth(\DateTime $dateOfBirth): void {
        $this->dateOfBirth = $dateOfBirth;
    }

    /**
     * @return \DateTime
     */
    public function getInstantiated(): \DateTime {
        return $this->instantiated;
    }

    /**
     * @param \DateTime $instantiated
     */
    public function setInstantiated(\DateTime $instantiated): void {
        $this->instantiated = $instantiated;
    }

    /**
     * @return array
     */
    public function getData() {
        return $this->data;
    }

    /**
     * @param array $data
     */
    public function setData($data) {
        $this->data = $data;
    }


}
