<?php

namespace Kinikit\Persistence\UPF\Framework;

use Kinikit\Core\Exception\BadParameterException;
use Kinikit\Core\Exception\ValidationException;
use Kinikit\Core\Object\SerialisableObject;
use Kinikit\Core\Util\ClassUtils;
use Kinikit\Core\Util\ObjectArrayUtils;
use Kinikit\Core\Util\Serialisation\XML\XMLToObjectConverter;
use Kinikit\Persistence\UPF\Exception\InvalidFieldRelationshipException;
use Kinikit\Persistence\UPF\Exception\NoEnabledEngineException;
use Kinikit\Persistence\UPF\Exception\NoneExistentEngineException;
use Kinikit\Persistence\UPF\Exception\ObjectNotFoundException;
use Kinikit\Persistence\UPF\Exception\OptimisticLockingException;
use Kinikit\Persistence\UPF\Exception\UPFObjectDeleteVetoedException;
use Kinikit\Persistence\UPF\Exception\UPFObjectSaveVetoedException;
use Kinikit\Persistence\UPF\Exception\WrongPrimaryKeyLengthException;

/**
 * Main worker object for coordinating persistence of objects.
 * This is configured with a number of engines which can be passed
 * on construction or added later on as well as a mapper manager. This
 * coordinator coordinates the synchronised saving / removal of
 * objects from all configured engines as well as retrieval of objects using the
 * first configured engine which matches the rule defined in the persistence
 * engine.
 *
 * The coordinator also enforces the generic object business rules such as lazy
 * loading, read only objects / collections and delete cascade rules.
 *
 *
 * @author mark
 *
 */
class ObjectPersistenceCoordinator extends SerialisableObject {

    private $engines = array();
    private $fieldFormatters = array();
    private $indexedFieldFormatters = array();
    private $optimisticLockingProvider = null;
    private $mapperManager;
    private $defaultObjectLocation;
    private $defaultInterceptorLocation;
    private $transactionDepth = 0;
    private $interceptorEvaluator;

    const TRANSACTION_STARTED = "Transaction Started";
    const TRANSACTION_FAILED = "Transaction Failed";
    const TRANSACTION_SUCCEEDED = "Transaction Succeeded";

    /**
     * Construct the object persistence coordinator with an array of engines
     *
     * @param $engines array
     * @param $mapperManager ObjectMapperManager
     */
    public function __construct($engines = array(), $mapperManager = null) {
        if (is_array($engines)) {
            foreach ($engines as $engine) {
                $this->addEngine($engine);
            }
        } else if ($engines) {
            $this->addEngine($engines);
        }

        if ($mapperManager && !($mapperManager instanceof ObjectMapperManager)) {
            throw new BadParameterException ("ObjectPersistenceCoordinator::ObjectPersistenceCoordinator", "mapperManager", $mapperManager);
        }

        $this->mapperManager = $mapperManager ? $mapperManager : new ObjectMapperManager ();
    }

    /**
     * Create an object persistence coordinator instance from an XML
     * configuration file.
     *
     * @param
     *         $configFilename
     * @return ObjectPersistenceCoordinator
     */
    public static function createFromConfigFile($configFilename) {

        // Replace the constants
        $definedConstants = get_defined_constants(true);

        $constants = isset($definedConstants["user"]) ? $definedConstants["user"] : array();

        // Replace all constants
        foreach ($constants as $key => $value) {
            $configFilename = str_replace($key, $value, $configFilename);
        }

        // Construct mappings to make the XML more readable
        $tagClassMappings = array();
        $tagClassMappings ["UPF"] = "\\Kinikit\\Persistence\\UPF\\Framework\\ObjectPersistenceCoordinator";
        $tagClassMappings ["Object"] = "\\Kinikit\\Persistence\\UPF\\Framework\\ObjectMapper";
        $tagClassMappings ["Field"] = "\\Kinikit\\Persistence\\UPF\\Framework\\ObjectPersistableField";
        $tagClassMappings ["Relationship"] = "\\Kinikit\\Persistence\\UPF\\Framework\\ObjectRelationship";
        $tagClassMappings ["RelatedField"] = "\\Kinikit\\Persistence\\UPF\\Framework\\ObjectRelatedField";
        $tagClassMappings["OrderingField"] = "\\Kinikit\\Persistence\\UPF\\Framework\\ObjectOrderingField";

        $tagClassMappings["ObjectIndexPersistenceEngine"] = "\\Kinikit\\Persistence\\UPF\\Engines\\ObjectIndex\\ObjectIndexPersistenceEngine";
        $tagClassMappings["ORMPersistenceEngine"] = "\\Kinikit\\Persistence\\UPF\\Engines\\ORM\\ORMPersistenceEngine";

        $tagClassMappings["MySQLDatabaseConnection"] = "\\Kinikit\\Persistence\\Database\\Connection\\MySQL\\MySQLDatabaseConnection";
        $tagClassMappings["MSSQLDatabaseConnection"] = "\\Kinikit\\Persistence\\Database\\Connection\\MSSQL\\MSSQLDatabaseConnection";
        $tagClassMappings["SQLite3DatabaseConnection"] = "\\Kinikit\\Persistence\\Database\\Connection\\SQLite3\\SQLite3DatabaseConnection";

        $tagClassMappings["DateFieldFormatter"] = "\\Kinikit\\Persistence\\UPF\\FieldFormatters\\DateFieldFormatter";
        $tagClassMappings["NumberFieldFormatter"] = "\\Kinikit\\Persistence\\UPF\\FieldFormatters\\NumberFieldFormatter";
        $tagClassMappings["CSVFieldFormatter"] = "\\Kinikit\\Persistence\\UPF\\FieldFormatters\\CSVFieldFormatter";
        $tagClassMappings["MoneyFieldFormatter"] = "\\Kinikit\\Persistence\\UPF\\FieldFormatters\\MoneyFieldFormatter";
        $tagClassMappings["SerialisingFieldFormatter"] = "\\Kinikit\\Persistence\\UPF\\FieldFormatters\\SerialisingFieldFormatter";

        $tagClassMappings["SQLOptimisticLockingProvider"] = "\\Kinikit\\Persistence\\UPF\\LockingProviders\\SQLOptimisticLockingProvider";


        $converter = new XMLToObjectConverter ($tagClassMappings);

        if (file_exists($configFilename))
            return $converter->convert(file_get_contents($configFilename));

    }


    /**
     *
     * @return the $engines
     */
    public function getEngines() {
        return $this->engines;
    }

    /**
     *
     * @return ObjectOptimisticLockingProvider $optimisticLockingProvider
     */
    public function getOptimisticLockingProvider() {
        return $this->optimisticLockingProvider;
    }

    /**
     *
     * @param $optimisticLockingProvider the
     *         $optimisticLockingProvider to set
     */
    public function setOptimisticLockingProvider($optimisticLockingProvider) {
        $this->optimisticLockingProvider = $optimisticLockingProvider;
    }

    /**
     * Get the mapper manager
     *
     * @return ObjectMapperManager $mapperManager
     */
    public function getMapperManager() {
        return $this->mapperManager;
    }

    /**
     * Set the array of engine upon the coordinator (useful for XML binding)
     */

    public function setEngines($engines, $replace = false) {

        if ($engines && !is_array($engines)) $engines = array($engines);

        if ($replace) {
            $this->engines = $engines;
        } else {
            foreach ($engines as $engine) {
                $this->addEngine($engine);
            }
        }
    }

    /**
     * Set an array of object mappers as a convenience mechanism for populating
     * the
     * contained mapper manager.
     * Useful for XML config.
     *
     * @param $objectMappings array
     */

    public function setObjectMappers($objectMappings) {
        if (!$this->getMapperManager()) {
            $this->mapperManager = new ObjectMapperManager ();
        }

        if (!is_array($objectMappings)) {
            $objectMappings = array($objectMappings);
        }

        // Add the mappers.
        foreach ($objectMappings as $objectMapping) {
            $this->mapperManager->addMapper($objectMapping);
        }

    }

    /**
     * Add a persistence engine to the coordinator
     *
     * @param $engine ObjectPersistenceEngine
     */

    public function addEngine($engine) {
        if ($engine instanceof ObjectPersistenceEngine) $this->engines [] = $engine; else
            throw new BadParameterException ("ObjectPersistenceCoordinator::addEngine", "engine", $engine);
    }

    /**
     * Return the evaluator for the interceptors.
     *
     * @return the $interceptors
     */

    public function getInterceptorEvaluator() {
        return $this->interceptorEvaluator;
    }

    /**
     * Add an array of global interceptors, for any object being persisted
     *
     * @param $interceptors array
     */
    public function setInterceptors($interceptors) {

        if (!is_array($interceptors)) $interceptors = array($interceptors);

        if ($this->interceptorEvaluator) {
            $existingInterceptors = $this->interceptorEvaluator->getInterceptors();
        } else {
            $existingInterceptors = array();
        }

        $interceptors = array_merge($existingInterceptors, $interceptors);

        $this->interceptorEvaluator = new UPFObjectInterceptorEvaluator (null, $interceptors);
    }

    /**
     *
     * @return the $fieldFormatters
     */

    public function getFieldFormatters() {
        return $this->fieldFormatters;
    }

    /**
     *
     * @param $fieldFormatters field_type
     */
    public function setFieldFormatters($fieldFormatters) {

        if ($fieldFormatters && !is_array($fieldFormatters)) $fieldFormatters = array($fieldFormatters);

        // Make an internal indexed array of formatters for quick lookup.
        foreach ($fieldFormatters as $formatter) {
            $this->indexedFieldFormatters [$formatter->getIdentifier()] = $formatter;
        }

        $this->fieldFormatters = array_merge($this->fieldFormatters, $fieldFormatters);
    }

    /**
     * Set a single or an array of mapping filenames for auxillary inclusion in
     * the main UPF file.
     *
     * @param $mappingFiles mixed
     */
    public function setIncludedMappingFiles($mappingFiles) {
        if (!is_array($mappingFiles)) $mappingFiles = array($mappingFiles);

        // Grab each file in turn and merge into the main structures.
        foreach ($mappingFiles as $file) {

            // Grab the sub coordinator
            $includedCoordinator = ObjectPersistenceCoordinator::createFromConfigFile($file);

            // Merge data as appropriate
            if ($includedCoordinator instanceof ObjectPersistenceCoordinator) {
                $this->setEngines($includedCoordinator->getEngines());

                if ($includedCoordinator->getOptimisticLockingProvider()) {
                    $this->setOptimisticLockingProvider($includedCoordinator->getOptimisticLockingProvider());
                }

                $interceptorEvaluator = $includedCoordinator->getInterceptorEvaluator();
                if ($interceptorEvaluator) $this->setInterceptors($interceptorEvaluator->getInterceptors());

                $this->setFieldFormatters($includedCoordinator->getFieldFormatters());

                $this->setObjectMappers($includedCoordinator->getMapperManager()->getAllMappers());
            }
        }

    }

    /**
     *
     * @return the $indexedFieldFormatters
     */
    public function getIndexedFieldFormatters() {
        return $this->indexedFieldFormatters;
    }

    /**
     * Save an object to the index
     *
     * @param $object SerialisableObject
     */
    public function saveObject($object) {

        try {

            // Firstly get an appropriate mapper for the supplied object
            $mapper = $this->mapperManager->getMapperForObject($object);

            // If not noValidateOnSave attribute set, validate first and throw out if an issue.
            if (!$mapper->getNoValidateOnSave()) {
                $validationErrors = $object->validate();
                if (sizeof($validationErrors) > 0) {
                    throw new ValidationException($validationErrors);
                }
            }


            // If we have defined any global interceptors, run these for presave
            // first
            if ($this->interceptorEvaluator) {
                $proceed = $this->interceptorEvaluator->evaluateInterceptorsForPreSave($object, $this);
                if ($proceed == false) throw new UPFObjectSaveVetoedException ($mapper->getClassName());
            }

            // Then try any local interceptors.
            $interceptorEvaluator = $mapper->getInterceptorEvaluator();
            if ($interceptorEvaluator) {

                $proceed = $interceptorEvaluator->evaluateInterceptorsForPreSave($object, $this);
                if ($proceed == false) throw new UPFObjectSaveVetoedException ($mapper->getClassName());
            }
            // Register transaction status
            $this->registerTransactionStatus(ObjectPersistenceCoordinator::TRANSACTION_STARTED);


            // Firstly update any child master relationships.
            foreach ($mapper->getRelationships() as $relationship) {

                // Only persist at this stage child objects where the
                // relationship is master child.
                if ($relationship->getMaster() == ObjectRelationship::MASTER_CHILD) {
                    $childObjectValue = $object->__getSerialisablePropertyValue($relationship->getFieldName());


                    if ($childObjectValue) {

                        if (!$relationship->getReadOnly()) {

                            if ($childObjectValue instanceof ObjectFacade) {
                                $childObjectValue = $childObjectValue->returnRealObject();
                            } else {

                                // Save the object.
                                $this->saveObject($childObjectValue);
                            }

                        }

                        // Synchronise relational fields
                        $this->synchroniseRelatedFields($relationship, $object, $childObjectValue);


                    }
                }

            }


            // Gather the static values we need against each object row.
            $pkValue = $mapper->getPrimaryKeyValueForObject($object, $this->indexedFieldFormatters);
            $pkValue = is_array($pkValue) ? array_values($pkValue) : array($pkValue);


            // Loop through each component of the PK. Expand out any references.
            for ($i = 0; $i < sizeof($pkValue); $i++) {
                $pkEntry = $pkValue [$i];
                if (is_object($pkEntry)) {
                    $pkMapper = $this->getMapperManager()->getMapperForObject($pkEntry);
                    $pkValue [$i] = $pkMapper->getPrimaryKeyValueForObject($pkEntry);

                }
            }

            $primaryKey = join("||", $pkValue);

            // If we have a locking provider, check for a lock and throw
            // accordingly before doing any more work if we have one
            $this->checkForOptimisticLock($mapper, $primaryKey, $object);


            // Get the persistable value map and persist them all in the
            // database
            $persistables = $mapper->getPersistableFieldValueMapForObject($object);

            // Now loop through each persistable value and construct a simple
            // map for persisting.
            // Recursively saving any children.
            $insertValues = array();

            // Loop through and set up the main array of primitive values to
            // insert.
            foreach ($persistables as $fieldName => $persistable) {
                $persistableField = $persistable [0];
                $fieldValue = $persistable [1];

                // Ensure we unformat if required.
                if (($persistableField instanceof ObjectPersistableField) && $persistableField->getFormatter()) {
                    $formatterInstance =
                        isset ($this->indexedFieldFormatters [$persistableField->getFormatter()]) ? $this->indexedFieldFormatters [$persistableField->getFormatter()] : null;
                    if ($formatterInstance) {
                        $fieldValue = $formatterInstance->unformat($fieldValue);
                    }
                }

                // Add the insert value to the array provided it has not been omitted for update
                if (!($fieldValue instanceof OmittedForUpdateValue)) $insertValues [$fieldName] = $fieldValue;
            }


            // Call the save method on the storage engine to effect the save if not read only.
            // Also process relationships for all many-many objects for each
            // engine.
            if (!$mapper->getReadOnly()) {


                foreach ($this->getEngines() as $engine) {

                    // Continue if the mapper is not enabled for the current engine.
                    if (!$mapper->isEnabledForEngine($engine->getIdentifier())) {
                        continue;
                    }


                    // Call the main save - catch any exceptions and determine whether or not to throw based upon rules.

                    try {
                        $primaryKey = $engine->saveObjectData($mapper, $insertValues, $primaryKey, $persistables, $object);

                        // Synchronise the primary key on this object if required.
                        $primaryKeyFields = $mapper->getPrimaryKeyFields();
                        $primaryKeyArray = $primaryKey !== null ? explode("||", $primaryKey) : array();

                        // Format any primary key values as required.
                        $primaryKeyArray = $this->applyFormatterToPrimaryKeyValues($primaryKeyArray, $mapper);

                        if (sizeof($primaryKeyFields) == sizeof($primaryKeyArray)) {

                            for ($i = 0; $i < sizeof($primaryKeyFields); $i++) {
                                $object->__setSerialisablePropertyValue($primaryKeyFields [$i]->getFieldName(), $primaryKeyArray [$i]);
                            }

                        } else {
                            throw new WrongPrimaryKeyLengthException ($mapper->getClassName(), sizeof($primaryKeyFields));
                        }
                    } catch (\Exception $e) {

                        // If we are ignoring failures, fail silently - otherwise moan.
                        if (!$engine->getIgnoreFailures()) {
                            throw $e;
                        }
                    }

                }
            }

            // Now update any parent master relationships.
            foreach ($mapper->getRelationships() as $relationship) {

                // Only persist at this stage child objects where the
                // relationship is master child.
                if ((!$relationship->getReadOnly()) && ($relationship->getMaster() == ObjectRelationship::MASTER_PARENT)) {

                    // Grab all children matching parent values
                    $parentFieldValues = array();
                    foreach ($relationship->getRelatedFields() as $relatedField) {

                        if ($relatedField->getParentField()) {
                            $parentFieldValues [$relatedField->getChildField()] =
                                $object->__getSerialisablePropertyValue($relatedField->getParentField());
                        } else if ($relatedField->getStaticValue()) {
                            $parentFieldValues [$relatedField->getChildField()] = $relatedField->getStaticValue();
                        }


                    }

                    // Grab the new object values.
                    $childObjectValues = $object->__getSerialisablePropertyValue($relationship->getFieldName());
                    $childObjectValues =
                        $childObjectValues ? (is_array($childObjectValues) ? $childObjectValues : array($childObjectValues)) : array();

                    // Grab the existing object data.
                    $childMapper = $this->getMapperManager()->getMapperForClass($relationship->getRelatedClassName());
                    $existingObjects =
                        $this->getObjectsForFieldValues($relationship->getRelatedClassName(), $parentFieldValues);
                    $existingObjects = is_array($existingObjects) ? $existingObjects : array();

                    // Grab any order fields
                    $orderFields = $relationship->getOrderingFields();
                    $autoFields = null;
                    if (is_array($orderFields)) {
                        $autoFields =
                            ObjectArrayUtils::getMemberValueArrayForObjects("field", ObjectArrayUtils::filterArrayOfObjectsByMember("autoIndex", $orderFields, true));
                    }

                    // Save all new values
                    $keepPKs = array();
                    $index = 0;
                    foreach ($childObjectValues as $childObjectValue) {

                        // If we have any auto order fields, set these to the current index.
                        if ($autoFields) {
                            foreach ($autoFields as $autoField) {
                                $childObjectValue->__setSerialisablePropertyValue($autoField, $index);
                            }
                        }

                        // Synchronise values from parent
                        $this->synchroniseRelatedFields($relationship, $object, $childObjectValue);

                        // Save the child
                        $this->saveObject($childObjectValue);

                        $childObjectPK = $childMapper->getPrimaryKeyValueForObject($childObjectValue);

                        $keepPKs [join("||", $childObjectPK)] = 1;

                        $index++;

                    }

                    // Now clean up any redundant values
                    foreach ($existingObjects as $existingObject) {

                        $objectPK = $childMapper->getPrimaryKeyValueForObject($existingObject);
                        $pkString = join("||", $objectPK);
                        if (!isset ($keepPKs [$pkString])) {
                            $this->removeObject($existingObject);
                        }
                    }

                }

            }

            // If we are in a locking scenario, update the locking value on
            // successful save.
            if ($this->getOptimisticLockingProvider() && $mapper->getLocking()) {
                $newLockingData =
                    $this->getOptimisticLockingProvider()->updateLockingDataForObject($mapper, $primaryKey);

                // Synchronise this object with the new locking value to prevent
                // issues going forward.
                $lockingDataField = $mapper->getLockingDataField() ? $mapper->getLockingDataField() : "lockingData";
                $object->__setSerialisablePropertyValue($lockingDataField, $newLockingData);
            }

            // If no exception, register success
            $this->registerTransactionStatus(ObjectPersistenceCoordinator::TRANSACTION_SUCCEEDED);

            // If we have defined any global interceptors, run these for
            // postsave first
            if ($this->interceptorEvaluator) {
                $proceed = $this->interceptorEvaluator->evaluateInterceptorsForPostSave($object, $this);
                if ($proceed == false) throw new UPFObjectSaveVetoedException ($mapper->getClassName());
                $proceed = $this->interceptorEvaluator->evaluateInterceptorsForPostMap($object, $this);
                if ($proceed == false) throw new UPFObjectSaveVetoedException ($mapper->getClassName());
            }

            // Then try any local interceptors.
            $interceptorEvaluator = $mapper->getInterceptorEvaluator();
            if ($interceptorEvaluator) {

                $proceed = $interceptorEvaluator->evaluateInterceptorsForPostSave($object, $this);
                if ($proceed == false) throw new UPFObjectSaveVetoedException ($mapper->getClassName());

                $proceed = $interceptorEvaluator->evaluateInterceptorsForPostMap($object, $this);
                if ($proceed == false) throw new UPFObjectSaveVetoedException ($mapper->getClassName());
            }


            return $primaryKey;

        } catch (\Exception $e) {


            // Ensure we rollback the save transaction before throwing
            $this->registerTransactionStatus(ObjectPersistenceCoordinator::TRANSACTION_FAILED);
            throw ($e);
        }

    }

    /**
     * Remove an object from the index.
     *
     * @param $object object
     */
    public function removeObject($object) {

        try {

            // Firstly get an appropriate mapper for the supplied object
            $mapper = $this->mapperManager->getMapperForObject($object);

            // If we have defined any global interceptors, run these for
            // predelete first
            if ($this->interceptorEvaluator) {
                $proceed = $this->interceptorEvaluator->evaluateInterceptorsForPreDelete($object, $this);
                if ($proceed == false) throw new UPFObjectDeleteVetoedException ($mapper->getClassName());
            }

            $interceptorEvaluator = $mapper->getInterceptorEvaluator();
            if ($interceptorEvaluator) {

                $proceed = $interceptorEvaluator->evaluateInterceptorsForPreDelete($object, $this);
                if ($proceed == false) throw new UPFObjectDeleteVetoedException ($mapper->getClassName());
            }
            // Register transaction status
            $this->registerTransactionStatus(ObjectPersistenceCoordinator::TRANSACTION_STARTED);

            // Grab the PK value as well.
            $pkValues =
                $this->applyFormatterToPrimaryKeyValues(array_values($mapper->getPrimaryKeyValueForObject($object)), $mapper, true);
            $primaryKey = join("||", $pkValues);


            // If we have a locking provider, check for a lock and throw
            // accordingly before doing any more work if we have one
            $this->checkForOptimisticLock($mapper, $primaryKey, $object);

            // Grab persistable fields
            $persistableFieldMap = $mapper->getPersistableFieldValueMapForObject($object);

            // Loop through all relationships defined and act accordingly.
            foreach ($mapper->getRelationships() as $relationship) {

                if ($relationship->getDeleteCascade()) {

                    // Grab all children matching parent values
                    $parentFieldValues = array();
                    foreach ($relationship->getRelatedFields() as $relatedField) {

                        if ($relatedField->getParentField()) {

                            $parentFieldValues [$relatedField->getChildField()] =
                                $object->__getSerialisablePropertyValue($relatedField->getParentField());


                        } else if ($relatedField->getStaticValue()) {
                            $parentFieldValues [$relatedField->getChildField()] = $relatedField->getStaticValue();
                        }
                    }
                    $relatedObjects =
                        $this->getObjectsForFieldValues($relationship->getRelatedClassName(), $parentFieldValues);

                    foreach ($relatedObjects as $relatedObject) {
                        $this->removeObject($relatedObject);
                    }

                }

            }

            // Pass this object directly onto the configured persistence
            // engines.
            foreach ($this->getEngines() as $engine) {
                if ($mapper->isEnabledForEngine($engine->getIdentifier())) {

                    try {
                        $engine->removeObjectData($mapper, $primaryKey, $persistableFieldMap);
                    } catch (\Exception $e) {
                        if (!$engine->getIgnoreFailures()) throw $e;
                    }
                }
            }

        } catch (\Exception $e) {
            // Ensure we rollback the save transaction before throwing
            $this->registerTransactionStatus(ObjectPersistenceCoordinator::TRANSACTION_FAILED);
            throw ($e);
        }

        // If we are in a locking scenario, update the locking value on
        // successful save.
        if ($this->getOptimisticLockingProvider() && $mapper->getLocking()) {
            $newLockingData = $this->getOptimisticLockingProvider()->updateLockingDataForObject($mapper, $primaryKey);

        }

        // If no exception, register success
        $this->registerTransactionStatus(ObjectPersistenceCoordinator::TRANSACTION_SUCCEEDED);

        // If we have defined any global interceptors, run these for postdelete
        // first
        if ($this->interceptorEvaluator) {
            $proceed = $this->interceptorEvaluator->evaluateInterceptorsForPostDelete($object, $this);
            if ($proceed == false) throw new UPFObjectDeleteVetoedException ($mapper->getClassName());
        }

        $interceptorEvaluator = $mapper->getInterceptorEvaluator();
        if ($interceptorEvaluator) {
            $proceed = $interceptorEvaluator->evaluateInterceptorsForPostDelete($object, $this);
            if ($proceed == false) throw new UPFObjectDeleteVetoedException ($mapper->getClassName());
        }

    }

    /**
     * Get an object by primary key.
     * The key may be passed as a single value (usual)
     * or an array of values in the compound pk scenario. A single object is
     * returned if
     * the get operation is successful, otherwise an ObjectNotFound exception is
     * raised.
     *
     * In order to retrieve the PK, the installed engines are checked in their
     * added order until a matching
     * engine is found to handle the lookup (determined by a call to
     * isPersistingForMapper() for the mapper representing
     * the class)
     *
     * @param $objectClass string
     * @param $primaryKeyValues mixed
     *
     * @return Object
     */
    public function getObjectByPrimaryKey($objectClass, $primaryKeyValues) {

        // Firstly get an appropriate mapper for the supplied object
        $mapper = $this->mapperManager->getMapperForClass($objectClass);

        $inUseEngine = $this->getFirstAvailableEngineForMapper($mapper);

        if (!is_array($primaryKeyValues)) $primaryKeyValues = explode("||", $primaryKeyValues);

        // Unformat any values if required
        $primaryKeyValues = $this->applyFormatterToPrimaryKeyValues($primaryKeyValues, $mapper, true);

        $primaryKeyValues = join("||", $primaryKeyValues);

        $matchingData = $inUseEngine->getObjectDataByPrimaryKey($mapper, $primaryKeyValues);

        // Return the mapped object if match is found.
        if ($matchingData != null) {
            $mappedData = $this->mapObjectDataToObject($mapper, $matchingData);
            if ($mappedData) {
                return $mappedData;
            } else {
                throw new ObjectNotFoundException ($objectClass, $primaryKeyValues);
            }
        } else {
            throw new ObjectNotFoundException ($objectClass, $primaryKeyValues);
        }
    }

    /**
     * Return multiple object using the array of primary key values passed in.
     * As in the single case above, the primary key values
     * entries can be either single values or an array of values if the PK is
     * compound. If the ignore missing objects is passed in as
     * true, any pk values which don't match an object will be silently ignored
     * otherwise an ObjectNotFound exception is raised for
     * any missing values.
     *
     * @param $objectClass string
     * @param $primaryKeyValues array
     * @param $ignoreMissingObjects boolean
     *
     * @return array
     */
    public function getMultipleObjectsByPrimaryKey($objectClass, $primaryKeyValues, $ignoreMissingObjects = false) {

        // Firstly get an appropriate mapper for the supplied object
        $mapper = $this->mapperManager->getMapperForClass($objectClass);

        $inUseEngine = $this->getFirstAvailableEngineForMapper($mapper);

        // Ensure that the primary key values are all keyed in as associative
        // arrays before continuing
        $normalisedValues = array();
        foreach ($primaryKeyValues as $primaryKeyValue) {

            if (!is_array($primaryKeyValue)) $primaryKeyValue = explode("||", $primaryKeyValue);

            // Unformat any values if required
            $primaryKeyValue = $this->applyFormatterToPrimaryKeyValues($primaryKeyValue, $mapper, true);

            $primaryKeyValue = join("||", $primaryKeyValue);

            $normalisedValues [] = $primaryKeyValue;
        }

        // Get unique values
        $normalisedValues = array_unique($normalisedValues);

        $matchingData = $inUseEngine->getMultipleObjectsDataByPrimaryKey($mapper, $normalisedValues);


        // If we are not ignoring missing objects, throw an exception if we
        // encounter missing objects.
        if (!$ignoreMissingObjects && (sizeof($matchingData) != sizeof($normalisedValues))) {
            throw new ObjectNotFoundException ($objectClass, "MULTIPLE PKs");
        }

        // Otherwise, make a new mapped object array, keeping the order of the
        // returned array the same as the supplied pks.
        $returnedValues = array();
        foreach ($normalisedValues as $pk) {

            if (is_array($pk)) $pk = join("||", $pk);
            if (array_key_exists($pk, $matchingData)) $returnedValues [$objectClass . ":" . $pk] =
                $this->mapObjectDataToObject($mapper, $matchingData [$pk]);
        }

        return $returnedValues;

    }

    /**
     * Return an array of objects using the engine specific query passed in for
     * the class of objects identified.
     * If no engine identifier is passed in, the first available engine for the
     * class of object supplied will be picked
     * by convention and assumed to be the desired engine.
     * @param $objectClass
     * @param $queryObject
     * @return array
     * @throws NoEnabledEngineException
     * @throws NoneExistentEngineException
     */
    public function query($objectClass, $queryObject) {

        // Grab the mapper
        $mapper = $this->mapperManager->getMapperForClass($objectClass);
        $engine = $this->getFirstAvailableEngineForMapper($mapper);

        // Grab all arguments after the first query object and pass them through
        $additionalArgs = func_get_args();
        array_shift($additionalArgs);
        array_shift($additionalArgs);

        if (sizeof($additionalArgs) == 1 && is_array($additionalArgs[0])) {
            $additionalArgs = $additionalArgs[0];
        }


        // Get results
        $results = $engine->query($mapper, $queryObject, $additionalArgs);


        // Loop through each result, mapping each object
        $returnResults = array();
        if ($results) {
            foreach ($results as $result) {
                $mappedResult = $this->mapObjectDataToObject($mapper, $result);
                if ($mappedResult != null) {
                    $returnResults [] = $mappedResult;
                }
            }
        }

        if ($queryObject instanceof QueryResults) {
            $returnResults = $queryObject->processResults($returnResults, $this, $objectClass);
        }

        return $returnResults;

    }


    /**
     * Return the count of results for the supplied query
     *
     * @param $objectClass
     * @param $queryObject
     * @param null $engineIdentifier
     *
     * @return integer
     */
    public function count($objectClass, $queryObject) {

        // Grab the mapper
        $mapper = $this->mapperManager->getMapperForClass($objectClass);
        $engine = $this->getFirstAvailableEngineForMapper($mapper);

        // Grab all arguments after the first query object and pass them through
        $additionalArgs = func_get_args();
        array_shift($additionalArgs);
        array_shift($additionalArgs);

        if (sizeof($additionalArgs) == 1 && is_array($additionalArgs[0])) {
            $additionalArgs = $additionalArgs[0];
        }

        // Get results
        $results = $engine->count($mapper, $queryObject, $additionalArgs);

        return $results;
    }


    /**
     * Synchronise any contained relationships on an existing object.  This is particularly useful
     * when creating new objects to fill in any available relational data.
     *
     * @param SerialisableObject $existingObject
     */
    public function synchroniseRelationships($existingObject) {

        // Firstly get an appropriate mapper for the supplied object
        $mapper = $this->mapperManager->getMapperForObject($existingObject);

        // Now grab the member data from the object
        $memberData = $existingObject->__getSerialisablePropertyMap();

        // Map any relationship data
        $this->mapRelationshipData($mapper, $memberData);

        // Update the member data
        $existingObject->__setSerialisablePropertyMap($memberData, true);

    }


    // Get objects for field values.
    public function getObjectsForFieldValues($objectClass, $fieldValues, $orderingFields = array(), $engineIdentifier = null) {


        // Grab the mapper
        $mapper = $this->mapperManager->getMapperForClass($objectClass);

        if ($engineIdentifier) {
            $engine = $this->getInstalledEngineByIdentifier($engineIdentifier);
        } else {
            $engine = $this->getFirstAvailableEngineForMapper($mapper);
        }

        $engineData = $engine->getObjectsDataForFieldValues($mapper, $fieldValues, $orderingFields);

        $objects = array();
        if ($engineData) {
            foreach ($engineData as $dataItem) {
                $result = $this->mapObjectDataToObject($mapper, $dataItem);
                if ($result) $objects [] = $result;
            }
        }

        return $objects;

    }

    /**
     * Map a set of object data to an object.
     * This is analogous to mapping a result set into objects and forms the real
     * heart of the relational - object mapping.
     *
     * @param $objectMapper ObjectMapper
     * @param $objectData array
     */
    public function mapObjectDataToObject($objectMapper, $objectData) {

        $newObject = null;

        $proposedObject = $objectMapper->getClassName();

        // Check for any global interceptor evaluators and evaluate these first.
        if ($this->interceptorEvaluator) {
            $proceed = $this->interceptorEvaluator->evaluateInterceptorsForPreMap($proposedObject, $objectData, $this);
            if ($proceed == false) return null;
        }

        $interceptorEvaluator = $objectMapper->getInterceptorEvaluator();
        if ($interceptorEvaluator) {

            $proceed = $interceptorEvaluator->evaluateInterceptorsForPreMap($proposedObject, $objectData, $this);
            if ($proceed === false) {
                return null;
            } else {

                // Check for a location value
                $objectMapper = $this->mapperManager->getMapperForClass($proceed);
                $newObject = ClassUtils::createNewClassInstance($proceed, null, true);
            }
        }


        if ($newObject == null) {

            // Create a new instance of a class using the class utils for
            // standardised checking.
            $newObject = ClassUtils::createNewClassInstance($objectMapper->getClassName(), null, true);


        }


        // Augment the object data with any other persistable field entries we
        // might find
        if ($objectMapper->getFields()) {
            foreach ($objectMapper->getFields() as $field) {
                $fieldName = is_object($field) ? $field->getFieldName() : $field;
                if (!isset ($objectData [$fieldName])) {
                    $objectData [$fieldName] = null;
                }

                // If a formatter defined on the field, invoke that now to
                // format the field on the way out.
                if (($field instanceof ObjectPersistableField) && $field->getFormatter()) {
                    $fieldFormatter =
                        isset ($this->indexedFieldFormatters [$field->getFormatter()]) ? $this->indexedFieldFormatters [$field->getFormatter()] : null;
                    if ($fieldFormatter) {
                        $objectData [$fieldName] = $fieldFormatter->format($objectData [$fieldName]);
                    }
                }

            }
        }


        // Map the relationship data using the mapper and object data array.
        if (sizeof($objectMapper->getRelationships()) > 0)
            $this->mapRelationshipData($objectMapper, $objectData);


        // If we have an optimistic locking provider defined, also add any
        // locking data from that as well
        if ($this->getOptimisticLockingProvider() && $objectMapper->getLocking()) {

            // Grab the primary key array
            $primaryKeyArray = $objectMapper->getPrimaryKeyValueForArrayOfValues($objectData);

            $lockingDataField =
                $objectMapper->getLockingDataField() ? $objectMapper->getLockingDataField() : "lockingData";
            if (is_array($primaryKeyArray)) $objectData [$lockingDataField] =
                $this->getOptimisticLockingProvider()->getLockingDataForObject($objectMapper, join("||", $primaryKeyArray));
        }


        // Set the object data upon the new object ignoring any non-existents.
        $newObject->__setSerialisablePropertyMap($objectData, true);


        // Evaluate global post map interceptors if they exist
        if ($this->interceptorEvaluator) {
            $proceed = $this->interceptorEvaluator->evaluateInterceptorsForPostMap($newObject, $this);
            if ($proceed == false) return null;
        }

        // Evaluate local post maps if they exist.
        $interceptorEvaluator = $objectMapper->getInterceptorEvaluator();
        if ($interceptorEvaluator) {
            $proceed = $interceptorEvaluator->evaluateInterceptorsForPostMap($newObject, $this);
            if ($proceed == false) return null;
        }


        // Return the new object once complete
        return $newObject;

    }


    /**
     * Map relationship data to the array of object data using the mapper
     *
     *
     * @param $objectMapper
     * @param $objectData
     * @throws InvalidFieldRelationshipException
     * @throws NoEnabledEngineException
     */
    private function mapRelationshipData($objectMapper, &$objectData) {

        // Loop through relationships, performing any nested processing as
        // required.
        $relationships = is_array($objectMapper->getRelationships()) ? $objectMapper->getRelationships() : array();
        foreach ($relationships as $relationship) {

            $fieldName = $relationship->getFieldName();

            // If we have a relationship field and we haven't already evaluated
            // out to an object facade, look to reconcile this
            // relationship
            if ($relationship) {

                // If no relationship class defined, throw an exception as we
                // must have this
                if (!$relationship->getRelatedClassName()) {
                    throw new InvalidFieldRelationshipException ($objectMapper->getClassName(), $fieldName);
                }

                // Find the first available engine for the related object class
                // type.
                // THIS MAKES THE ASSUMPTION THAT ANY RELATIONAL INFRASTRUCTURE
                // e.g. Join Tables IS MANAGED BY THE SAME ENGINE
                // THAT MANAGES THE CHILD. Also store lazy loading and related
                // mapper for convenience.
                $relatedMapper = $this->mapperManager->getMapperForClass($relationship->getRelatedClassName());
                $inUseEngine = $this->getFirstAvailableEngineForMapper($relatedMapper);
                $lazyLoading = $relationship->getLazyLoad();

                // Generate a list of child query fields and query for them
                $parentFieldValues = array();
                foreach ($relationship->getRelatedFields() as $relatedField) {

                    $staticValue = $relatedField->getStaticValue();

                    if ($relatedField->getParentField()) {
                        $parentFieldValues [$relatedField->getChildField()] =
                            $objectData [$relatedField->getParentField()];
                    } else if (isset($staticValue)) {
                        $parentFieldValues [$relatedField->getChildField()] = $relatedField->getStaticValue() == "NULL" ? null : $relatedField->getStaticValue();
                    }
                }

                // Handle facade objects for lazy loaded entities.
                if ($relationship->getLazyLoad()) {

                    if ($relationship->getIsMultiple()) {
                        $fieldValue = new ObjectArrayFacade ($relationship->getRelatedClassName(), $parentFieldValues);
                    } else {
                        $fieldValue = new ObjectFacade ($relationship->getRelatedClassName(), $parentFieldValues);
                    }

                    $fieldValue->injectPersistenceCoordinatorInstance($this);
                } else {


                    $relatedObjects =
                        $this->getObjectsForFieldValues($relationship->getRelatedClassName(), $parentFieldValues, $relationship->getOrderingFields());

                    // Handle relationships
                    if ($relationship->getIsMultiple()) {
                        $fieldValue = is_array($relatedObjects) ? $relatedObjects : array();
                    } else {
                        if (is_array($relatedObjects) && sizeof($relatedObjects) > 0) {
                            $fieldValue = $relatedObjects [0];
                        } else if ($relationship->getCreateIfNull()) {
                            $className = $relationship->getRelatedClassName();
                            $fieldValue = new $className();
                            $fieldValue->__setSerialisablePropertyMap($parentFieldValues, true);
                            $this->synchroniseRelationships($fieldValue);
                        } else {
                            $fieldValue = null;
                        }
                    }

                }

            }

            // Update the array with the potentially modified value.
            $objectData [$fieldName] = $fieldValue;

        }
    }


    /**
     * Return the first available engine for the supplied mapper.
     *
     * @param $mapper ObjectMapper
     * @return ObjectPersistenceEngine
     */
    public function getFirstAvailableEngineForMapper($mapper) {
        $engines = $this->getEngines();
        foreach ($engines as $engine) {
            if ($mapper->isEnabledForEngine($engine->getIdentifier())) return $engine;
        }

        throw new NoEnabledEngineException ($mapper->getClassName());

    }

    /**
     * Get an installed engine by identifier or throw if it cannot be found.
     *
     * @param $identifier string
     */
    public function getInstalledEngineByIdentifier($identifier) {
        foreach ($this->getEngines() as $engine) {
            if ($engine->getIdentifier() == $identifier) return $engine;
        }

        throw new NoneExistentEngineException ($identifier);
    }



    // Helper function for synchronising primary key fields primarily and to
    // simplify the logic
    // above.
    private function setFieldValuesOnObject($pkValues, $targetObject) {

        // Convert both to arrays as required
        if (!is_array($pkValues)) {
            $pkValues = array($pkValues);
        }

        // Synchronise key fields from parent to child.
        foreach ($pkValues as $fieldName => $value) {

            // Set the value on the target object
            if ($targetObject) $targetObject->__setSerialisablePropertyValue($fieldName, $value);
        }

    }

    // Register a transaction status with all engines.
    // Also ensure that we only do this if at the top level of the operation
    // and not for child operations as this is complete overkill.
    private function registerTransactionStatus($status) {

        if ($status != ObjectPersistenceCoordinator::TRANSACTION_STARTED) $this->transactionDepth--;

        if ($this->transactionDepth == 0) {

            // Loop through each engine, applying the status accordingly
            foreach ($this->getEngines() as $engine) {

                try {
                    switch ($status) {


                        case ObjectPersistenceCoordinator::TRANSACTION_STARTED :

                            $engine->persistenceTransactionStarted();
                            break;
                        case ObjectPersistenceCoordinator::TRANSACTION_SUCCEEDED :

                            $engine->persistenceTransactionSucceeded();
                            break;
                        case ObjectPersistenceCoordinator::TRANSACTION_FAILED :

                            $engine->persistenceTransactionFailed();
                            break;
                    }
                } catch (\Exception $e) {

                    // Fail silently if we are ignoring failures for this engine.
                    if (!$engine->getIgnoreFailures()) throw $e;
                }
            }

            // Register transaction stuff on the locking provider as well.
            if ($this->getOptimisticLockingProvider()) {
                $provider = $this->getOptimisticLockingProvider();
                switch ($status) {
                    case ObjectPersistenceCoordinator::TRANSACTION_STARTED :
                        $provider->persistenceTransactionStarted();
                        break;
                    case ObjectPersistenceCoordinator::TRANSACTION_SUCCEEDED :
                        $provider->persistenceTransactionSucceeded();
                        break;
                    case ObjectPersistenceCoordinator::TRANSACTION_FAILED :
                        $provider->persistenceTransactionFailed();
                        break;
                }
            }
        }

        if ($status == ObjectPersistenceCoordinator::TRANSACTION_STARTED) $this->transactionDepth++;

    }

    // Rationalised function for checking for an optimistic lock.
    private function checkForOptimisticLock($mapper, $primaryKey, $object) {

        if ($this->getOptimisticLockingProvider() && $mapper->getLocking()) {
            $lockingDataField = $mapper->getLockingDataField() ? $mapper->getLockingDataField() : "lockingData
";
            $lockingData = $object->__getSerialisablePropertyValue($lockingDataField);
            $locked = $this->getOptimisticLockingProvider()->isObjectLocked($mapper, $primaryKey, $lockingData);

            if ($locked) throw new OptimisticLockingException ($mapper->getClassName(), $primaryKey);
        }

    }

    // Synchronise any related fields on the parent and child as required.
    private function synchroniseRelatedFields($relationship, $parentObject, $childObject) {

        foreach ($relationship->getRelatedFields() as $relatedField) {

            if ($relationship->getMaster() == ObjectRelationship::MASTER_CHILD) {

                if ($relatedField->getChildField()) {
                    $childValue = $childObject->__getSerialisablePropertyValue($relatedField->getChildField());
                } else if ($relatedField->getStaticValue()) {
                    $childValue = $relatedField->getStaticValue();
                }

                $parentObject->__setSerialisablePropertyValue($relatedField->getParentField(), $childValue);
            } else {

                if ($relatedField->getParentField()) {
                    $parentValue = $parentObject->__getSerialisablePropertyValue($relatedField->getParentField());
                } else if ($relatedField->getStaticValue()) {
                    $parentValue = $relatedField->getStaticValue();
                }

                $childObject->__setSerialisablePropertyValue($relatedField->getChildField(), $parentValue);
            }

        }
    }

    // Format or unformat an array of primary key values if required. use the
    // mapper to obtain the
    // primary key fields.
    private function applyFormatterToPrimaryKeyValues($valuesArray, $mapper, $unformat = false) {
        $pkFields = $mapper->getPrimaryKeyFields();

        // Sanity check
        if (sizeof($valuesArray) == sizeof($pkFields)) {

            // Unformat as required
            for ($i = 0; $i < sizeof($valuesArray); $i++) {
                $pkField = $pkFields [$i];

                if ($pkField instanceof ObjectPersistableField) {

                    $formatter = $pkField->getFormatter();
                    if (isset ($formatter) && isset ($this->indexedFieldFormatters [$formatter])) {
                        $valuesArray [$i] =
                            $unformat ? $this->indexedFieldFormatters [$formatter]->unformat($valuesArray [$i]) : $this->indexedFieldFormatters [$formatter]->format($valuesArray [$i]);
                    }

                }
            }

        }

        return $valuesArray;

    }

}

?>
