<?php

namespace Kinikit\Persistence\ORM\Query;

class SummarisedValue {

    /**
     * @var mixed
     */
    private $memberValue;

    /**
     * @var mixed
     */
    private $expressionValue;

    /**
     * Construct a summarised value as a member value and metric value
     *
     * @param mixed $memberValue
     * @param mixed $metricValue
     */
    public function __construct($memberValue, $metricValue) {
        $this->memberValue = $memberValue;
        $this->expressionValue = $metricValue;
    }


    /**
     * @return mixed
     */
    public function getMemberValue() {
        return $this->memberValue;
    }

    /**
     * @return mixed
     */
    public function getExpressionValue() {
        return $this->expressionValue;
    }


}