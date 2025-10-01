<?php

namespace Kinikit\Persistence\ORM;

/**
 * @table person_attribute
 */
class PersonAttribute {

    /**
     * @var int
     * @primaryKey
     */
    private ?int $personId;

    /**
     * @var string
     * @primaryKey
     */
    private ?string $attribute;

    /**
     * @param string $attribute
     */
    public function __construct(?string $attribute) {
        $this->attribute = $attribute;
        $this->personId = null;
    }


    public function getPersonId(): ?int {
        return $this->personId;
    }

    public function setPersonId(?int $personId): void {
        $this->personId = $personId;
    }

    public function getAttribute(): string {
        return $this->attribute;
    }

    public function setAttribute(string $attribute): void {
        $this->attribute = $attribute;
    }




}