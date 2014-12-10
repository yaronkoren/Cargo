<?php

/**
 * Ideally this would probably implement the "Iterator" interface, but that
 * seems like too much work for the limited usage this class gets.
 *
 * @author Yaron Koren
 * @ingroup Cargo
 */

class CargoTableSchema {

	var $mFieldDescriptions = array();

	public static function newFromDBString( $dbString ) {
		$tableSchema = new CargoTableSchema();
		$tableSchemaDBArray = unserialize( $dbString );
		if ( !is_array( $tableSchemaDBArray ) ) {
			throw new MWException( "Invalid field information found for table." );
		}
		foreach ( $tableSchemaDBArray as $fieldName => $fieldDBArray ) {
			$tableSchema->mFieldDescriptions[$fieldName] = CargoFieldDescription::newFromDBArray( $fieldDBArray );
		}
		return $tableSchema;
	}

	function toDBString() {
		$tableSchemaDBArray = array();
		foreach ( $this->mFieldDescriptions as $fieldName => $fieldDesc ) {
			$tableSchemaDBArray[$fieldName] = $fieldDesc->toDBArray();
		}
		return serialize( $tableSchemaDBArray );
	}
}
