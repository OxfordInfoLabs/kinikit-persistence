<?php

namespace Kinikit\Persistence\ORM;

class TestObject {
    /**
     * @var ?int
     * @primaryKey
     */
    private ?int $id;
    private ?string $blah;

    /**
     * @param ?string $blah
     * @param ?int $id
     */
    public function __construct(?string $blah, ?int $id = null) {
        $this->blah = $blah;
        $this->id = $id;
    }

    public function getBlah(): ?string {
        return $this->blah;
    }

    public function setBlah(string $blah): void {
        $this->blah = $blah;
    }

    public function getId(): ?int {
        return $this->id;
    }

    public function setId(?int $id): void {
        $this->id = $id;
    }



}