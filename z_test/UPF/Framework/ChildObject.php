<?php

namespace Kinikit\Persistence\UPF\Framework;
use Kinikit\Core\Object\SerialisableObject;

/**
 * Created by PhpStorm.
 * User: mark
 * Date: 19/11/2013
 * Time: 18:11
 */
class ChildObject extends SerialisableObject {


    private $id;
    private $parentId;
    private $name;
    private $category;
    private $postcode;
    private $telephoneNumber;
    private $orderIndex;


    public function __construct($name = null, $postcode = null, $telephoneNumber = null, $category = null) {
        $this->name = $name;
        $this->postcode = $postcode;
        $this->telephoneNumber = $telephoneNumber;
        $this->category = $category;
    }

    /**
     * @param mixed $id
     */
    public function setId($id) {
        $this->id = $id;
    }

    /**
     * @return mixed
     */
    public function getId() {
        return $this->id;
    }

    /**
     * @param mixed $name
     */
    public function setName($name) {
        $this->name = $name;
    }

    /**
     * @return mixed
     */
    public function getName() {
        return $this->name;
    }

    /**
     * @param mixed $parentId
     */
    public function setParentId($parentId) {
        $this->parentId = $parentId;
    }

    /**
     * @return mixed
     */
    public function getParentId() {
        return $this->parentId;
    }

    /**
     * @param mixed $postcode
     */
    public function setPostcode($postcode) {
        $this->postcode = $postcode;
    }

    /**
     * @return mixed
     */
    public function getPostcode() {
        return $this->postcode;
    }

    /**
     * @param mixed $telephoneNumber
     */
    public function setTelephoneNumber($telephoneNumber) {
        $this->telephoneNumber = $telephoneNumber;
    }

    /**
     * @return mixed
     */
    public function getTelephoneNumber() {
        return $this->telephoneNumber;
    }

    /**
     * @param mixed $orderIndex
     */
    public function setOrderIndex($orderIndex) {
        $this->orderIndex = $orderIndex;
    }

    /**
     * @return mixed
     */
    public function getOrderIndex() {
        return $this->orderIndex;
    }

    /**
     * @param mixed $category
     */
    public function setCategory($category) {
        $this->category = $category;
    }

    /**
     * @return mixed
     */
    public function getCategory() {
        return $this->category;
    }


}
