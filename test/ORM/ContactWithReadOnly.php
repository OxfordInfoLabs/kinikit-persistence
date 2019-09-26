<?php


namespace Kinikit\Persistence\ORM;

/**
 * @table contact
 */
class ContactWithReadOnly {

    /**
     * @var integer
     */
    private $id;

    /**
     * @var string
     */
    private $name;

    /**
     * @readOnly
     * @manyToOne
     * @parentJoinColumns primary_address_id
     *
     * @var Address
     */
    private $primaryAddress;


    /**
     * @readOnly
     * @manyToMany
     * @linkTable contact_other_addresses
     *
     * @var Address[]
     */
    private $otherAddresses;


    /**
     * @readOnly
     * @oneToOne
     *
     * @var Profile
     */
    private $profile;


    /**
     * @readOnly
     * @oneToMany
     *
     * @var PhoneNumber[]
     */
    private $phoneNumbers;

    /**
     * Contact constructor.
     * @param string $name
     * @param Address $primaryAddress
     * @param Address[] $otherAddresses
     * @param Profile $profile
     * @param PhoneNumber[] $phoneNumbers
     */
    public function __construct($name = null, $primaryAddress = null, $otherAddresses = null, $profile = null, $phoneNumbers = null) {
        $this->name = $name;
        $this->primaryAddress = $primaryAddress;
        $this->otherAddresses = $otherAddresses;
        $this->profile = $profile;
        $this->phoneNumbers = $phoneNumbers;
    }


    /**
     * @return int
     */
    public function getId() {
        return $this->id;
    }

    /**
     * @param int $id
     */
    public function setId(int $id) {
        $this->id = $id;
    }

    /**
     * @return string
     */
    public function getName() {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName(string $name): void {
        $this->name = $name;
    }

    /**
     * @return Address
     */
    public function getPrimaryAddress() {
        return $this->primaryAddress;
    }

    /**
     * @param Address $primaryAddress
     */
    public function setPrimaryAddress($primaryAddress) {
        $this->primaryAddress = $primaryAddress;
    }

    /**
     * @return Address[]
     */
    public function getOtherAddresses() {
        return $this->otherAddresses;
    }

    /**
     * @param Address[] $otherAddresses
     */
    public function setOtherAddresses(array $otherAddresses) {
        $this->otherAddresses = $otherAddresses;
    }

    /**
     * @return Profile
     */
    public function getProfile() {
        return $this->profile;
    }

    /**
     * @param Profile $profile
     */
    public function setProfile($profile): void {
        $this->profile = $profile;
    }

    /**
     * @return PhoneNumber[]
     */
    public function getPhoneNumbers() {
        return $this->phoneNumbers;
    }

    /**
     * @param PhoneNumber[] $phoneNumbers
     */
    public function setPhoneNumbers(array $phoneNumbers): void {
        $this->phoneNumbers = $phoneNumbers;
    }


}
