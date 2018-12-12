<?php

namespace Kinikit\Persistence\UPF\Framework;
use Kinikit\Core\Object\DynamicSerialisableObject;

/**
 * Ordering field for use in multiple relationships for ordering child results using child fields.
 *
 * Class ObjectRelatedOrderingField
 */
class ObjectOrderingField extends DynamicSerialisableObject {

    private $field;
    private $direction;
    private $autoIndex;

    const DIRECTION_ASC = "ASC";
    const DIRECTION_DESC = "DESC";


    /**
     * Constructor for testing etc.
     *
     * @param null $field
     * @param string $direction
     */
    public function __construct($field = null, $direction = ObjectOrderingField::DIRECTION_ASC, $autoIndex = false) {
        $this->field = $field;
        $this->direction = $direction;
        $this->autoIndex = $autoIndex;
    }

    /**
     * @param mixed $field
     */
    public function setField($field) {
        $this->field = $field;
    }

    /**
     * @return mixed
     */
    public function getField() {
        return $this->field;
    }

    /**
     * @param string $direction
     */
    public function setDirection($direction) {
        $this->direction = $direction;
    }

    /**
     * @return string
     */
    public function getDirection() {
        return $this->direction;
    }

    /**
     * @param mixed $autoIndex
     */
    public function setAutoIndex($autoIndex) {
        $this->autoIndex = $autoIndex;
    }

    /**
     * @return mixed
     */
    public function getAutoIndex() {
        return $this->autoIndex;
    }


}