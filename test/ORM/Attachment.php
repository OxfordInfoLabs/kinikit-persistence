<?php


namespace Kinikit\Persistence\ORM;


class Attachment {

    /**
     * @var integer
     */
    private $id;

    /**
     * @var string
     */
    private $content;


    /**
     * Construct with content
     *
     * Attachment constructor.
     * @param string $content
     */
    public function __construct($content = null) {
        $this->content = $content;
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
    public function getContent() {
        return $this->content;
    }

    /**
     * @param string $content
     */
    public function setContent($content) {
        $this->content = $content;
    }


}
