<?php


namespace Kinikit\Persistence\ORM;

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
     * @var \DateTime
     */
    private $dateOfBirth;

    /**
     * @var \DateTime
     */
    private $instantiated;

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





}
