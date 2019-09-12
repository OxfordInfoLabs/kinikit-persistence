<?php


namespace Kinikit\Persistence\ORM;

class Address {


    /**
     * @var integer
     */
    protected $id;

    /**
     * @var string
     */
    private $name;

    /**
     * @var string
     */
    private $street1;

    /**
     * @var string
     */
    private $street2;

    /**
     * @var string
     */
    private $phoneNumber;

    /**
     * @var string
     */
    private $countryCode;


    /**
     * Create new address object
     *
     * Address constructor.
     */
    public function __construct($id = null, $name = null, $street1 = null, $street2 = null, $phoneNumber = null, $countryCode = null) {
        $this->id = $id;
        $this->name = $name;
        $this->street1 = $street1;
        $this->street2 = $street2;
        $this->phoneNumber = $phoneNumber;
        $this->countryCode = $countryCode;
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
    public function getName(): string {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName(string $name): void {
        $this->name = $name;
    }

    /**
     * @return string
     */
    public function getStreet1(): string {
        return $this->street1;
    }

    /**
     * @param string $street1
     */
    public function setStreet1(string $street1): void {
        $this->street1 = $street1;
    }

    /**
     * @return string
     */
    public function getStreet2(): string {
        return $this->street2;
    }

    /**
     * @param string $street2
     */
    public function setStreet2(string $street2): void {
        $this->street2 = $street2;
    }

    /**
     * @return string
     */
    public function getPhoneNumber(): string {
        return $this->phoneNumber;
    }

    /**
     * @param string $phoneNumber
     */
    public function setPhoneNumber(string $phoneNumber): void {
        $this->phoneNumber = $phoneNumber;
    }

    /**
     * @return string
     */
    public function getCountryCode(): string {
        return $this->countryCode;
    }

    /**
     * @param string $countryCode
     */
    public function setCountryCode(string $countryCode): void {
        $this->countryCode = $countryCode;
    }


}
