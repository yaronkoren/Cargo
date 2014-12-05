<?php
/**
 * @author Yaron Koren
 * @ingroup Cargo
 */

class CargoListFormat extends CargoDisplayFormat {

	function __construct( $output, $parser = null ) {
		parent::__construct( $output, $parser );
		$this->mOutput->addModules( 'ext.cargo.main' );
	}

	function allowedParameters() {
		return array( 'delimiter' );
	}

	function displayRow( $row, $fieldDescriptions ) {
		$text = '';
		$startParenthesisAdded = false;
		$firstField = true;
		foreach ( $fieldDescriptions as $fieldName => $fieldDescription ) {
			if ( !array_key_exists( $fieldName, $row ) ) {
				continue;
			}
			$fieldValue = $row[$fieldName];
			if ( trim( $fieldValue ) == '' ) {
				continue;
			}
			if ( $firstField ) {
				$text = $fieldValue;
				$firstField = false;
			} else {
				if ( ! $startParenthesisAdded ) {
					$text .= ' (';
					$startParenthesisAdded = true;
				} else {
					$text .= ', ';
				}
				$text .= "<span class=\"cargoFieldName\">$fieldName:</span> $fieldValue";
			}
		}
		if ( $startParenthesisAdded ) {
			$text .= ')';
		}
		return $text;
	}

	function display( $valuesTable, $formattedValuesTable, $fieldDescriptions, $displayParams ) {
		$text = '';
		$delimiter = ( array_key_exists( 'delimiter', $displayParams ) ) ? $displayParams['delimiter'] : wfMessage( 'comma-separator' )->text();
		foreach ( $formattedValuesTable as $i => $row ) {
			if ( $i > 0 ) {
				$text .= $delimiter . ' ';
			}
			$text .= $this->displayRow( $row, $fieldDescriptions );
		}
		return $text;
	}

}
