<?php
/**
 * Class for the #cargo_attach parser function.
 *
 * @author Yaron Koren
 * @ingroup Cargo
 */

class CargoAttach {

	/**
	 * Handles the #cargo_attach parser function.
	 */
	public static function run( &$parser ) {
		if ( $parser->getTitle()->getNamespace() != NS_TEMPLATE ) {
			return CargoUtils::formatError( "Error: #cargo_attach must be called from a template page." );
		}

		$params = func_get_args();
		array_shift( $params ); // we already know the $parser...

		$tableName = null;
		$cargoFields = array();
		foreach ( $params as $param ) {
			$parts = explode( '=', $param, 2 );
			
			if ( count( $parts ) != 2 ) {
				continue;
			}
			$key = trim( $parts[0] );
			$value = trim( $parts[1] );
			if ( $key == '_table' ) {
				$tableName = $value;
			}
		}

		// Validate table name.
		if ( $tableName == '' ) {
			return CargoUtils::formatError( "Error: Table name must be specified." );
		}

		$dbr = wfGetDB( DB_SLAVE );
		$res = $dbr->select( 'cargo_tables', 'COUNT(*)', array( 'main_table' => $tableName ) );
		$row = $dbr->fetchRow( $res );
		if ( $row[0] == 0 ) {
			return CargoUtils::formatError( "Error: The specified table, \"$tableName\", does not exist." );
		}

		$parserOutput = $parser->getOutput();
		$parserOutput->setProperty( 'CargoAttachedTable', $tableName );

		// Link to the Special:ViewTable page for this table.
		$vt = SpecialPage::getTitleFor( 'ViewTable' );
		$pageName = $vt->getPrefixedText() . "/$tableName";
		$viewTableMsg = wfMessage( 'ViewTable' )->parse();
		$text = "This template adds rows to the table \"$tableName\". [[$pageName|$viewTableMsg]].";

		return $text;
	}

}
