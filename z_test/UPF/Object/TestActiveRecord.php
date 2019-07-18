<?php
/**
 * Created by PhpStorm.
 * User: mark
 * Date: 16/08/2018
 * Time: 11:56
 */

namespace Kinikit\Persistence\UPF\Object;


class TestActiveRecord extends ActiveRecord {

    protected $id;
    private $name;
    private $age;
    private $nickname;
    private $containerTag;

    /**
     * TestActiveRecord constructor.
     * @param $id
     * @param $name
     * @param $age
     * @param $nickname
     */
    public function __construct($name = null, $age = null, $nickname = null, $containerTag = null) {
        $this->name = $name;
        $this->age = $age;
        $this->nickname = $nickname;
        $this->containerTag = $containerTag;
    }


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

    /**
     * @return mixed
     */
    public function getNickname() {
        return $this->nickname;
    }

    /**
     * @param mixed $nickname
     */
    public function setNickname($nickname) {
        $this->nickname = $nickname;
    }

    /**
     * @return mixed
     */
    public function getContainerTag() {
        return $this->containerTag;
    }

    /**
     * @param mixed $containerTag
     */
    public function setContainerTag($containerTag) {
        $this->containerTag = $containerTag;
    }


}