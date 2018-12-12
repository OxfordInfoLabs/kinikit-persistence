<?php

namespace Kinikit\Persistence\UPF\Framework;

use Kinikit\Core\Exception\BadParameterException;

/**
 * Handy wrapper for managing a collection of object mapper objects in
 * particular to pass to a persitence engine instance.
 *
 * @author mark
 *
 */
class ObjectMapperManager {

    private $objectMappers = array();

    /**
     * Construct optionally with an array of object mappers
     */
    public function __construct($objectMappers = null) {

        if ($objectMappers) {
            if (is_array($objectMappers)) {
                foreach ($objectMappers as $mapper) {
                    $this->addMapper($mapper);
                }
            } else {
                $this->addMapper($objectMappers);
            }
        }
    }

    /**
     * Add a new mapper to this manager
     *
     * @param $objectIndexMapper ObjectIndexMapper
     */
    public function addMapper($objectMapper) {

        if (!($objectMapper instanceof ObjectMapper)) {
            throw new BadParameterException ("ObjectMapperManager->addMapper", "objectIndexMapper", get_class($objectMapper));
        }

        // Add the mapper for our array
        $this->objectMappers [$objectMapper->getClassName()] = $objectMapper;

    }

    /**
     * Get a mapper in use for a class name
     *
     * @param $className string
     * @return ObjectMapper
     */
    public function getMapperForClass($className) {

        $workingClassName = $className;
        while ($workingClassName && !array_key_exists($workingClassName, $this->objectMappers)) {
            $workingClassName = get_parent_class($workingClassName);
        }

        $matchingMapper = !$workingClassName ? new ObjectMapper ($className) : $this->objectMappers [$workingClassName];

        while ($matchingMapper->getExtends()) {

            // Get the parent and make a dummy function call to ensure it is a real object.
            $parentMapper = $this->getMapperForClass($matchingMapper->getExtends());
            $parentMapper->getExtends();

            $mergedFields = array_merge($parentMapper->getFields(), $matchingMapper->getFields());
            $mergedRelationships = array_merge($parentMapper->getRelationships(), $matchingMapper->getRelationships());

            $parentInterceptors = $parentMapper->getInterceptorEvaluator() ? $parentMapper->getInterceptorEvaluator()->getInterceptors() : array();
            $myInterceptors = $matchingMapper->getInterceptorEvaluator() ? $matchingMapper->getInterceptorEvaluator()->getInterceptors() : array();
            $mergedInterceptors = array_merge($parentInterceptors, $myInterceptors);

            $parentData = $parentMapper->__getSerialisablePropertyMap();
            $childData = $matchingMapper->__getSerialisablePropertyMap();

            // Get the aggregate of all set properties on both parent and child
            $allProperties = array_merge(array_keys($parentData), array_keys($childData));

            // Create the combined map, prioritising child data over parent where possible.
            foreach ($allProperties as $key) {
                $combinedMap[$key] = isset($childData[$key]) ? $childData[$key] : $parentData[$key];
            }

            // Deal with special merge cases
            $combinedMap["fields"] = $mergedFields;
            $combinedMap["relationships"] = $mergedRelationships;
            $combinedMap["extends"] = $parentMapper->getExtends();
            $combinedMap["interceptors"] = $mergedInterceptors;


            $matchingMapper = new ObjectMapper($matchingMapper->getClassName());
            $matchingMapper->__setSerialisablePropertyMap($combinedMap);

            $this->addMapper($matchingMapper);
        }

        return $matchingMapper;
    }

    /**
     * Get the mapper in use for an object instance
     *
     * @param $object object
     * @return ObjectMapper
     */
    public function getMapperForObject($object) {
        $mapper = $this->getMapperForClass(get_class($object));
        $mapper->getPersistableFieldValueMapForObject($object);
        return $mapper;
    }

    /**
     * Return all mappers as an array.
     */
    public function getAllMappers() {
        return array_values($this->objectMappers);
    }

}

?>