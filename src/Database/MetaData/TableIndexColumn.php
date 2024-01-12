<?php

namespace Kinikit\Persistence\Database\MetaData;

class TableIndexColumn {

    /**
     * @var string
     */
    private $name;

    /**
     * @var integer
     */
    private $maxBytesToIndex = -1;

    /**
     * @param string $name
     * @param int $indexLength
     */
    public function __construct($name, $maxBytesToIndex = -1) {
        $this->name = $name;
        $this->maxBytesToIndex = $maxBytesToIndex;
    }

    /**
     * @return string
     */
    public function getName() {
        return $this->name;
    }

    /**
     * @return int
     */
    public function getMaxBytesToIndex() {
        return $this->maxBytesToIndex;
    }


}