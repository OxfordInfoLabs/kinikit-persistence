<?php
/**
 * Holder object for a history wrapper.  This will wrap objects which are returned for queries
 * made to the object index where history data is required.
 */

namespace Kinikit\Persistence\UPF\Engines\ObjectIndex;

class ObjectVersionWrapper extends SerialisableObject {

    protected $versionDate;
    protected $object;

    /**
     * Constructor
     *
     * @param $versionDate
     * @param $object
     */
    function __construct($versionDate, $object) {
        $this->versionDate = $versionDate;
        $this->object = $object;
    }


    /**
     * @return mixed
     */
    public function getObject() {
        return $this->object;
    }

    /**
     * @return mixed
     */
    public function getVersionDate() {
        return $this->versionDate;
    }


}