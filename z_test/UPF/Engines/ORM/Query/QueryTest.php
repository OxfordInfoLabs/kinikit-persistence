<?php
/**
 * Created by PhpStorm.
 * User: mark
 * Date: 16/08/2018
 * Time: 11:13
 */

namespace Kinikit\Persistence\UPF\Engines\ORM\Query;


use Kinikit\Core\Object\SerialisableObject;

class QueryTest extends SerialisableObject {

    protected $id;
    private $myCompoundField;
    private $anotherField;
    private $deadField;

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
    public function getMyCompoundField() {
        return $this->myCompoundField;
    }

    /**
     * @param mixed $myCompoundField
     */
    public function setMyCompoundField($myCompoundField) {
        $this->myCompoundField = $myCompoundField;
    }

    /**
     * @return mixed
     */
    public function getAnotherField() {
        return $this->anotherField;
    }

    /**
     * @param mixed $anotherField
     */
    public function setAnotherField($anotherField) {
        $this->anotherField = $anotherField;
    }

    /**
     * @return mixed
     */
    public function getDeadField() {
        return $this->deadField;
    }

    /**
     * @param mixed $deadField
     */
    public function setDeadField($deadField) {
        $this->deadField = $deadField;
    }


}