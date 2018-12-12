<?php
/**
 * Created by PhpStorm.
 * User: mark
 * Date: 20/08/2018
 * Time: 09:48
 */

namespace Kinikit\Persistence\UPF\Framework;


use Kinikit\Core\Object\SerialisableObject;

/**
 * @mapped
 * @noValidateOnSave
 *
 * Class ObjectWithSuppressedValidation
 * @package Kinikit\Persistence\UPF\Framework
 */
class ObjectWithSuppressedValidation extends SerialisableObject {

    /**
     * @validation required
     */
    private $id;

    /**
     * @validation required,name
     */
    private $name;

    /**
     * @validation required, numeric, range(18|65)
     */
    private $age;

    /**
     * @return mixed
     */
    public function getId() {
        return $this->id;
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
    public function getName() {
        return $this->name;
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
    public function getAge() {
        return $this->age;
    }

    /**
     * @param mixed $age
     */
    public function setAge($age) {
        $this->age = $age;
    }


}