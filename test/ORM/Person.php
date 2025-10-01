<?php

namespace Kinikit\Persistence\ORM;

/**
 * @table person
 */
class Person {

    /**
     * @var int
     */
    private ?int $id;


    /**
     * @var string
     */
    private ?string $name;

    /**
     * @var PersonAttribute[]
     *
     * @oneToMany
     * @childJoinColumns person_id
     */
    private ?array $personAttributes;

    /**
     * @param string $name
     * @param PersonAttribute[] $personAttributes
     */
    public function __construct(?string $name, ?array $personAttributes) {
        $this->name = $name;
        $this->personAttributes = $personAttributes;
        $this->id = null;
    }


    public function getId(): ?int {
        return $this->id;
    }

    public function setId(?int $id): void {
        $this->id = $id;
    }

    public function getName(): string {
        return $this->name;
    }

    public function setName(string $name): void {
        $this->name = $name;
    }

    public function getPersonAttributes(): array {
        return $this->personAttributes;
    }

    public function setPersonAttributes(array $personAttributes): void {
        $this->personAttributes = $personAttributes;
    }


}
