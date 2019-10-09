<?php


namespace Kinikit\Persistence\ORM;

/**
 * Class ShallowNote
 * @package Kinikit\Persistence\ORM
 *
 * @table note
 */
class ShallowNote {

    /**
     * @var integer
     */
    private $id;


    /**
     * @var string
     */
    private $note;


    /**
     * @oneToMany
     * @childJoinColumns parent_note_id
     * @maxDepth 2
     *
     * @var ShallowNote[]
     */
    private $childNotes;


    /**
     * Note constructor.
     *
     * @param int $id
     * @param string $note
     * @param Note[] $childNotes
     */
    public function __construct($id, $note, $childNotes) {
        $this->id = $id;
        $this->note = $note;
        $this->childNotes = $childNotes;
    }

    /**
     * @return int
     */
    public function getId() {
        return $this->id;
    }

    /**
     * @param int $id
     */
    public function setId($id) {
        $this->id = $id;
    }

    /**
     * @return string
     */
    public function getNote() {
        return $this->note;
    }

    /**
     * @param string $note
     */
    public function setNote($note) {
        $this->note = $note;
    }

    /**
     * @return Note[]
     */
    public function getChildNotes() {
        return $this->childNotes;
    }

    /**
     * @param Note[] $childNotes
     */
    public function setChildNotes($childNotes) {
        $this->childNotes = $childNotes;
    }


}
