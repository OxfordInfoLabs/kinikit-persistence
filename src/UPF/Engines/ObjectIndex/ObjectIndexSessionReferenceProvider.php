<?php

namespace Kinikit\Persistence\UPF\Engines\ObjectIndex;

/**
 * Session reference provider interface - defines single method getSessionRef() which will be used to be inserted
 * into the object index history logging table.
 */
interface ObjectIndexSessionReferenceProvider {

    /**
     * Single get session ref method for returning a session reference for historical logging purposes.
     *
     * @return mixed
     */
    public function getSessionRef();

}