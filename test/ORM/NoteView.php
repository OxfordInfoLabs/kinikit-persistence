<?php


namespace Kinikit\Persistence\ORM;


class NoteView {


    /**
     * @var integer
     * @primaryKey
     */
    private $id;


    /**
     * @var string
     */
    private $note;


    /**
     * Note constructor.
     *
     * @param int $id
     * @param string $note
     * @param Note[] $childNotes
     */
    public function __construct($id, $note) {
        $this->id = $id;
        $this->note = $note;

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

   
}
