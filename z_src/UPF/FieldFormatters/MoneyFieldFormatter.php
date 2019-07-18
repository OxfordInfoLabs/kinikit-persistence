<?php

namespace Kinikit\Persistence\UPF\FieldFormatters;
use Kinikit\Persistence\UPF\Framework\ObjectFieldFormatter;

/**
 * Money for rounding numbers to 2 decimal places and converts vack to 4 decimal places.
 * 
 * @author oxil
 *
 */
class MoneyFieldFormatter extends ObjectFieldFormatter {
	
	/**
	 * Format the number to 2 decimal places
	 *
	 * @see ObjectFieldFormatter::format()
	 */
	public function format($unformattedValue) {
		return number_format ( $unformattedValue, 2 );
	}
	
	/**
	 * Unformat the number back to 4 decimal places
	 *
	 * @see ObjectFieldFormatter::format()
	 */
	public function unformat($formattedValue) {
		$formattedValue = str_replace ( ",", "", $formattedValue );
		return number_format ( $formattedValue, 4, ".", "" );
	}

}

?>