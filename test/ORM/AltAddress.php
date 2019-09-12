<?php


namespace Kinikit\Persistence\ORM;

/**
 *
 * @table address
 * @interceptor Kinikit\Persistence\ORM\Interceptor\InlineORMInterceptor
 */
class AltAddress {

    /**
     * @var integer
     */
    protected $id;

    /**
     * @column name
     * @var string
     */
    private $altName;

    /**
     * @column street_1
     * @var string
     */
    private $altStreet1;

    /**
     * @column street_2
     * @var string
     */
    private $altStreet2;

    /**
     * @column phone_number
     * @var string
     */
    private $altPhoneNumber;

    /**
     * @column country_code
     * @var string
     */
    private $altCountryCode;

    /**
     * AltAddress constructor.
     * @param int $id
     * @param string $altName
     * @param string $altStreet1
     * @param string $altStreet2
     * @param string $altPhoneNumber
     * @param string $altCountryCode
     */
    public function __construct($id, $altName, $altStreet1, $altStreet2, $altPhoneNumber, $altCountryCode) {
        $this->id = $id;
        $this->altName = $altName;
        $this->altStreet1 = $altStreet1;
        $this->altStreet2 = $altStreet2;
        $this->altPhoneNumber = $altPhoneNumber;
        $this->altCountryCode = $altCountryCode;
    }


    /**
     * @return int
     */
    public function getId(): int {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getAltName(): string {
        return $this->altName;
    }

    /**
     * @return string
     */
    public function getAltStreet1(): string {
        return $this->altStreet1;
    }

    /**
     * @return string
     */
    public function getAltStreet2(): string {
        return $this->altStreet2;
    }

    /**
     * @return string
     */
    public function getAltPhoneNumber(): string {
        return $this->altPhoneNumber;
    }

    /**
     * @return string
     */
    public function getAltCountryCode(): string {
        return $this->altCountryCode;
    }


}
