<?php


namespace Kinikit\Persistence\TableMapper\Relationship;


class OneToManyTableRelationship extends OneToOneTableRelationship {

    /**
     * Override is multiple for one to many
     *
     * @return bool
     */
    public function isMultiple() {
        return true;
    }


}
