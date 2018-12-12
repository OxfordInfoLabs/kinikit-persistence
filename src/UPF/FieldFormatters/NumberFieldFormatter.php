<?php

namespace Kinikit\Persistence\UPF\FieldFormatters;
use Kinikit\Persistence\UPF\Framework\ObjectFieldFormatter;

/**
 * Number formatter for rounding numbers to a number of decimal places.
 * 
 * @author oxil
 *
 */
class NumberFieldFormatter extends ObjectFieldFormatter {
	
	private $decimalPlaces;
	
	/**
	 * @return the $decimalPlaces
	 */
	public function getDecimalPlaces() {
		return $this->decimalPlaces;
	}
	
	/**
	 * @param field_type $decimalPlaces
	 */
	public function setDecimalPlaces($decimalPlaces) {
		$this->decimalPlaces = $decimalPlaces;
	}
	
	/* 
	 * Format the number to the required decimal places
	 * 
	 * @see ObjectFieldFormatter::format()
	 */
	public function format($unformattedValue) {
		return number_format ( $unformattedValue, $this->decimalPlaces );
	}
	
	/* 
	 * Return the number intact as unformatting is usually not possible.
	 * 
	 * @see ObjectFieldFormatter::unformat()
	 */
	public function unformat($formattedValue) {
		return $formattedValue;
	}

}

?>