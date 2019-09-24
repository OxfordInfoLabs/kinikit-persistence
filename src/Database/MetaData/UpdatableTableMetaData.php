<?php
/**
 * Created by PhpStorm.
 * User: mark
 * Date: 24/09/2019
 * Time: 10:02
 */

namespace Kinikit\Persistence\Database\MetaData;


/**
 * Simple extension of TableMetaData to allow for updatability especially when
 * using this for ORM table generation.
 *
 * Class UpdatableTableMetaData
 * @package Kinikit\Persistence\Database\MetaData
 */
class UpdatableTableMetaData extends TableMetaData {

    /**
     * Add a column to the table meta data.  This simply changes scope of
     * underlying addColumn to public.
     *
     * @param $column
     */
    public function addColumn($column) {
        parent::addColumn($column);
    }

}