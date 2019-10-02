<?php


namespace Kinikit\Persistence\Objects;

/**
 * Class Note
 * @package Kinikit\Persistence\Objects
 *
 * @table new_note
 */
class Note {

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
     *
     * @var Note[]
     */
    private $childNotes;


    /**
     * Note constructor.
     *
     * @param int $id
     * @param string $note
     * @param Note[] $childNotes
     */
    public function __construct($id, $note,  $childNotes) {
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
