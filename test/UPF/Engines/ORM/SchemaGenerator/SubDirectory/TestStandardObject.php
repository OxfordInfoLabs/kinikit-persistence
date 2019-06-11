<?php


namespace Kinikit\Persistence\UPF\Engines\ORM\SchemaGenerator\SubDirectory;


use Kinikit\Persistence\UPF\Object\ActiveRecord;

class TestStandardObject extends ActiveRecord {


    /**
     * Standard auto increment id
     *
     * @var integer
     */
    protected $id;


    /**
     * String field
     *
     * @var string
     * @validation required
     */
    private $name;


    /**
     * At home boolean
     *
     * @var boolean
     */
    private $atHome;

    /**
     * @return int
     */
    public function getId() {
        return $this->id;
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
    public function setName($name) {
        $this->name = $name;
    }

    /**
     * @return bool
     */
    public function isAtHome() {
        return $this->atHome;
    }

    /**
     * @param bool $atHome
     */
    public function setAtHome($atHome) {
        $this->atHome = $atHome;
    }


}
