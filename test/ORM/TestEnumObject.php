<?php

namespace Kinikit\Persistence\ORM;

enum FakeStatus {
    case ACTIVE;
    case PASSIVE;
}

class TestEnumObject {
    /**
     * @var int
     * @primaryKey
     */
    private ?int $id;
    private ?FakeStatus $status;

    /**
     * @param ?int $id
     * @param ?FakeStatus $status
     */
    public function __construct(?int $id, ?FakeStatus $status) {
        $this->id = $id;
        $this->status = $status;
    }

    public function getId(): int {
        return $this->id;
    }

    public function setId(int $id): void {
        $this->id = $id;
    }

    public function getStatus(): FakeStatus {
        return $this->status;
    }

    public function setStatus(FakeStatus $status): void {
        $this->status = $status;
    }

}
