<?php


namespace Kinikit\Persistence\ORM;


class Contact {

    /**
     * @var integer
     */
    private $id;

    /**
     * @var string
     */
    private $name;


    /**
     * @manyToOne
     * @parentJoinColumns primary_address_id
     *
     * @var Address
     */
    private $primaryAddress;


    /**
     * @manyToMany
     * @linkTable contact_other_addresses
     *
     * @var Address[]
     */
    private $otherAddresses;


    /**
     * @oneToOne
     *
     * @var Profile
     */
    private $profile;


    /**
     * @oneToMany
     *
     * @var PhoneNumber[]
     */
    private $phoneNumbers;


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
    public function setPrimaryAddress(Address $primaryAddress) {
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
    public function getProfile(): Profile {
        return $this->profile;
    }

    /**
     * @param Profile $profile
     */
    public function setProfile(Profile $profile): void {
        $this->profile = $profile;
    }

    /**
     * @return PhoneNumber[]
     */
    public function getPhoneNumbers(): array {
        return $this->phoneNumbers;
    }

    /**
     * @param PhoneNumber[] $phoneNumbers
     */
    public function setPhoneNumbers(array $phoneNumbers): void {
        $this->phoneNumbers = $phoneNumbers;
    }


}
