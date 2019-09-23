<?php


namespace Kinikit\Persistence\Objects\Subordinates;


class PhoneNumber {

    /**
     * @var integer
     */
    private $id;

    /**
     * @var string
     */
    private $type;


    /**
     * @var string
     * @required
     */
    private $number;


    /**
     * Construct a phone number
     *
     * PhoneNumber constructor.
     * @param int $id
     * @param string $type
     * @param string $number
     */
    public function __construct($id = null, $type = null, $number = null) {
        $this->id = $id;
        $this->type = $type;
        $this->number = $number;
    }

    /**
     * @return int
     */
    public function getId(): int {
        return $this->id;
    }

    /**
     * @param int $id
     */
    public function setId(int $id): void {
        $this->id = $id;
    }

    /**
     * @return string
     */
    public function getType(): string {
        return $this->type;
    }

    /**
     * @param string $type
     */
    public function setType(string $type): void {
        $this->type = $type;
    }

    /**
     * @return string
     */
    public function getNumber(): string {
        return $this->number;
    }

    /**
     * @param string $number
     */
    public function setNumber(string $number): void {
        $this->number = $number;
    }


}
