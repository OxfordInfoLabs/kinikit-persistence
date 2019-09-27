<?php


namespace Kinikit\Persistence\ORM;


class Document {

    /**
     * @var integer
     */
    private $id;

    /**
     * @var string
     */
    private $content;


    /**
     * @oneToMany
     * @childJoinColumns parent_id,type=NOTE
     *
     * @var Attachment[]
     */
    private $notes;


    /**
     * @oneToMany
     * @childJoinColumns parent_id,type=COMMENT
     *
     * @var Attachment[]
     */
    private $comments;

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
    public function getContent() {
        return $this->content;
    }

    /**
     * @param string $content
     */
    public function setContent($content) {
        $this->content = $content;
    }

    /**
     * @return Attachment[]
     */
    public function getNotes() {
        return $this->notes;
    }

    /**
     * @param Attachment[] $notes
     */
    public function setNotes($notes) {
        $this->notes = $notes;
    }

    /**
     * @return Attachment[]
     */
    public function getComments() {
        return $this->comments;
    }

    /**
     * @param Attachment[] $comments
     */
    public function setComments($comments) {
        $this->comments = $comments;
    }


}
