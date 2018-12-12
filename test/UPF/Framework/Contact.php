<?php

namespace Kinikit\Persistence\UPF\Framework;

use Kinikit\Core\Object\SerialisableObject;

class Contact extends SerialisableObject {

    protected $id;
    private $name;
    private $telephone;
    private $address;
    private $friends;

    public function __construct($id = null, $name = null, $telephone = null, $address = null, $friends = null) {
        $this->id = $id;
        $this->name = $name;
        $this->telephone = $telephone;
        $this->address = $address;
        $this->friends = $friends;
    }

    /**
     * @return the $id
     */
    public function getId() {
        return $this->id;
    }

    /**
     * @return the $name
     */
    public function getName() {
        return $this->name;
    }

    /**
     * @return the $telephone
     */
    public function getTelephone() {
        return $this->telephone;
    }

    /**
     * @return the $address
     */
    public function getAddress() {
        return $this->address;
    }

    /**
     * @return the $friends
     */
    public function getFriends() {
        return $this->friends;
    }

    /**
     * @param $id the $id to set
     */
    public function setId($id) {
        $this->id = $id;
    }

    /**
     * @param $name the $name to set
     */
    public function setName($name) {
        $this->name = $name;
    }

    /**
     * @param $telephone the $telephone to set
     */
    public function setTelephone($telephone) {
        $this->telephone = $telephone;
    }

    /**
     * @param $address the $address to set
     */
    public function setAddress($address) {
        $this->address = $address;
    }

    /**
     * @param $friends the $friends to set
     */
    public function setFriends($friends) {
        $this->friends = $friends;
    }


}

?>