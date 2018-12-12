<?php

namespace Kinikit\Persistence\UPF\Engines\ObjectIndex;

/**
 * Test Session Reference Provider
 *
 * Class TestSessionReferenceProvider
 */
class TestSessionReferenceProvider implements ObjectIndexSessionReferenceProvider {

    private $sessionRef;

    public function __construct($sessionRef) {
        $this->sessionRef = $sessionRef;
    }

    /**
     * Single get session ref method for returning a session reference for historical logging purposes.
     *
     * @return mixed
     */
    public function getSessionRef() {
        return $this->sessionRef;
    }
}