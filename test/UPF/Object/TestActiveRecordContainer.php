<?php
/**
 * Created by PhpStorm.
 * User: mark
 * Date: 16/08/2018
 * Time: 12:36
 */

namespace Kinikit\Persistence\UPF\Object;

/**
 * @mapped
 * @ormTable active_record_container
 * @interceptors Kinikit\Persistence\UPF\Framework\TestObjectInterceptor1, Kinikit\Persistence\UPF\Framework\TestObjectInterceptor2
 *
 * Class TestActiveRecordContainer
 * @package Kinikit\Persistence\UPF\Object
 */
class TestActiveRecordContainer extends ActiveRecord {

    /**
     * @primaryKey
     * @ormColumn tag_name
     * @validation required
     */
    private $tag;

    /**
     * @validation required,maxlength(255)
     */
    private $description;

    /**
     * @relationship
     * @isMultiple
     * @relatedClassName TestActiveRecord
     * @relatedFields tag=>containerTag, staticValue=>"PIGGY"
     * @orderingFields name:ASC, id:DESC
     */
    private $activeRecords;

    /**
     * @unmapped
     */
    private $nonPersisted;


    /**
     * @return mixed
     */
    public function getTag() {
        return $this->tag;
    }

    /**
     * @param mixed $tag
     */
    public function setTag($tag) {
        $this->tag = $tag;
    }

    /**
     * @return mixed
     */
    public function getDescription() {
        return $this->description;
    }

    /**
     * @param mixed $description
     */
    public function setDescription($description) {
        $this->description = $description;
    }

    /**
     * @return mixed
     */
    public function getActiveRecords() {
        return $this->activeRecords;
    }

    /**
     * @param mixed $activeRecords
     */
    public function setActiveRecords($activeRecords) {
        $this->activeRecords = $activeRecords;
    }

    /**
     * @return mixed
     */
    public function getNonPersisted() {
        return $this->nonPersisted;
    }

    /**
     * @param mixed $nonPersisted
     */
    public function setNonPersisted($nonPersisted) {
        $this->nonPersisted = $nonPersisted;
    }


}