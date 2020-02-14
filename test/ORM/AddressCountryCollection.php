<?php


namespace Kinikit\Persistence\ORM;


/**
 *
 *
 * Class ContactWithUKAddresses
 * @package Kinikit\Persistence\ORM
 */
class AddressCountryCollection {

    /**
     * @var integer
     */
    private $id;


    /**
     * @var string
     */
    private $countryOfInterest;


    /**
     * @var Address
     *
     * @manyToOne
     * @parentJoinColumns country_of_interest=>country_code
     */
    private $address;

    /**
     * @return int
     */
    public function getId() {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getCountryOfInterest() {
        return $this->countryOfInterest;
    }

    /**
     * @return Address[]
     */
    public function getAddress() {
        return $this->address;
    }


}
