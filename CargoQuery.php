<?php
/**
 * CargoQuery - class for the #cargo_query parser function.
 *
 * @author Yaron Koren
 * @ingroup Cargo
 */

class CargoQuery {

	/**
	 * Handles the #cargo_query parser function - calls a query on the
	 * Cargo data stored in the database.
	 */
	public static function run( &$parser ) {
		$params = func_get_args();
		array_shift( $params ); // we already know the $parser...

		$tablesStr = null;
		$fieldsStr = null;
		$whereStr = null;
		$joinOnStr = null;
		$groupByStr = null;
		$orderByStr = null;
		$limitStr = null;
		$format = 'auto'; // default
		$displayParams = array();

		foreach ( $params as $param ) {
			$parts = explode( '=', $param, 2 );
			
			if ( count( $parts ) != 2 ) {
				continue;
			}
			$key = trim( $parts[0] );
			$value = trim( $parts[1] );
			if ( $key == 'tables' || $key == 'table' ) {
				$tablesStr = $value;
			} elseif ( $key == 'fields' ) {
				$fieldsStr = $value;
			} elseif ( $key == 'where' ) {
				$whereStr = $value;
			} elseif ( $key == 'join on' ) {
				$joinOnStr = $value;
			} elseif ( $key == 'group by' ) {
				$groupByStr = $value;
			} elseif ( $key == 'order by' ) {
				$orderByStr = $value;
			} elseif ( $key == 'limit' ) {
				$limitStr = $value;
			} elseif ( $key == 'format' ) {
				$format = $value;
			} else {
				// We'll assume it's going to the formatter.
				$displayParams[$key] = $value;
			}
		}

		try {
			$queryResults = self::getOrDisplayQueryResultsFromStrings( $tablesStr, $fieldsStr, $whereStr, $joinOnStr, $groupByStr, $orderByStr, $limitStr, $format, $displayParams, $parser );
		} catch ( Exception $e ) {
			return CargoUtils::formatError( $e->getMessage() );
		}

		return $queryResults;
	}

	/**
	 * Given a format name, and a list of the fields, returns the name
	 * of the the function to call for that format.
	 */
	static function getFormatClass( $format, $fieldDescriptions ) {
		$formatClasses = array(
			'list' => 'CargoListFormat',
			'ul' => 'CargoULFormat',
			'ol' => 'CargoOLFormat',
			'template' => 'CargoTemplateFormat',
			'embedded' => 'CargoEmbeddedFormat',
			'outline' => 'CargoOutlineFormat',
			'tree' => 'CargoTreeFormat',
			'table' => 'CargoTableFormat',
			'dynamic table' => 'CargoDynamicTableFormat',
			'googlemaps' => 'CargoGoogleMapsFormat',
			'openlayers' => 'CargoOpenLayersFormat',
			'calendar' => 'CargoCalendarFormat',
			'bar chart' => 'CargoBarChartFormat',
			'category' => 'CargoCategoryFormat',
		);

		if ( array_key_exists( $format, $formatClasses ) ) {
			return $formatClasses[$format];
		}

		$formatClass = null;
		wfRunHooks( 'CargoGetFormatClass', array( $format, &$formatClass ) );
		if ( $formatClass != null ) {
			return $formatClass;
		}

		if ( count( $fieldDescriptions ) > 1 ) {
			$format = 'table';
		} else {
			$format = 'list';
		}
		return $formatClasses[$format];
	}

	/**
	 * Display the link to view more results, pointing to Special:ViewData.
	 */
	public static function viewMoreResultsLink( $tablesStr, $fieldsStr, $whereStr, $joinOnStr, $groupByStr, $orderByStr, $queryLimit, $format, $displayParams ) {
		$vd = Title::makeTitleSafe( NS_SPECIAL, 'ViewData' );
		$queryStringParams = array();
		$queryStringParams['tables'] = $tablesStr;
		$queryStringParams['fields'] = $fieldsStr;
		if ( $whereStr != '' ) {
			$queryStringParams['where'] = $whereStr;
		}
		if ( $joinOnStr != '' ) {
		$queryStringParams['join_on'] = $joinOnStr;
		}
		if ( $groupByStr != '' ) {
			$queryStringParams['group_by'] = $groupByStr;
		}
		if ( $orderByStr != '' ) {
			$queryStringParams['order_by'] = $orderByStr;
		}
		if ( $format != '' ) {
			$queryStringParams['format'] = $format;
		}
		$queryStringParams['offset'] = $queryLimit;
		$queryStringParams['limit'] = 100; // Is that a reasonable number in all cases?

		// Add format-specific params.
		foreach ( $displayParams as $key => $value ) {
			$queryStringParams[$key] = $value;
		}

		return Html::rawElement( 'p', null, Linker::link( $vd, wfMessage( 'moredotdotdot' )->parse(), array(), $queryStringParams ) );
	}

	/**
	 * Takes in a set of strings representing elements of a SQL query,
	 * and returns either an array of results, or a display of the
	 * results, depending on whether or not the format parameter is
	 * specified.
	 * This method is used by both #cargo_query and the 'cargoquery'
	 * API action.
	 */
	public static function getOrDisplayQueryResultsFromStrings( $tablesStr, $fieldsStr, $whereStr, $joinOnStr, $groupByStr, $orderByStr, $limitStr, $format = null, $displayParams = null, $parser = null ) {

		$sqlQuery = CargoSQLQuery::newFromValues( $tablesStr, $fieldsStr, $whereStr, $joinOnStr, $groupByStr, $orderByStr, $limitStr );

		$formatClass = self::getFormatClass( $format, $sqlQuery->mFieldDescriptions );
		if ( $parser == null ) {
			global $wgParser;
			$parser = $wgParser;
		}
		$formatObject = new $formatClass( $parser->getOutput(), $parser );

		// Let the format run the query itself, if it wants to.
		if ( $formatObject->isDeferred() ) {
			$text = $formatObject->queryAndDisplay( array( $sqlQuery ), $displayParams );
			$text = $parser->insertStripItem( $text, $parser->mStripState );
			return $text;
		}

		$queryResults = $sqlQuery->run();

		if ( is_null( $format ) ) {
			return $queryResults;
		}

		$formattedQueryResults = self::getFormattedQueryResults( $queryResults, $sqlQuery->mFieldDescriptions, $parser );

		// Finally, do the display, based on the format.
		$text = $formatObject->display( $queryResults, $formattedQueryResults, $sqlQuery->mFieldDescriptions, $displayParams );

		// If there are (seemingly) more results than what we showed,
		// show a "View more" link that links to Special:ViewData.
		if ( count( $queryResults ) == $sqlQuery->mQueryLimit ) {
			$text .= self::viewMoreResultsLink( $tablesStr, $fieldsStr, $whereStr, $joinOnStr, $groupByStr, $orderByStr, $sqlQuery->mQueryLimit, $format, $displayParams );
		}

		$text = $parser->insertStripItem( $text, $parser->mStripState );

		return $text;
	}

	static function getFormattedQueryResults( $queryResults, $fieldDescriptions, $parser ) {
		// The assignment will do a copy.
		$formattedQueryResults = $queryResults;
		foreach ( $queryResults as $rowNum => $row ) {
			foreach ( $row as $fieldName => $value ) {
				if ( trim( $value ) == '' ) {
					continue;
				}

				if ( !array_key_exists( $fieldName, $fieldDescriptions ) ) {
					continue;
				}

				$fieldDescription = $fieldDescriptions[$fieldName];
				if ( array_key_exists( 'type', $fieldDescription ) ) {
					$type = trim( $fieldDescription['type'] );
				} else {
					$type = null;
				}

				$text = '';
				if ( array_key_exists( 'isList', $fieldDescription ) ) {
					// There's probably an easier way to do
					// this, using array_map().
					$delimiter = $fieldDescription['delimiter'];
					$fieldValues = explode( $delimiter, $value );
					foreach( $fieldValues as $i => $fieldValue ) {
						if ( trim( $fieldValue ) == '' ) continue;
						if ( $i > 0 ) $text .= "$delimiter ";
						$text .= self::formatFieldValue( $fieldValue, $type, $fieldDescription, $parser );
					}
				} else {
					$text = self::formatFieldValue( $value, $type, $fieldDescription, $parser );
				}
				if ( $text != '' ) {
					$formattedQueryResults[$rowNum][$fieldName] = $text;
				}
			}
		}
		return $formattedQueryResults;
	}

	public static function formatFieldValue( $value, $type, $fieldDescription, $parser ) {
		if ( $type == 'Page' ) {
			$title = Title::newFromText( $value );
			return Linker::link( $title );
		} elseif ( $type == 'File' ) {
			// 'File' values are basically pages in the File:
			// namespace; they are displayed as thumbnails within
			// queries.
			$title = Title::newFromText( $value, NS_FILE );
			return Linker::makeThumbLinkObj( $title, wfLocalFile( $title ), $value, '' );
		} elseif ( $type == 'URL' ) {
			if ( array_key_exists( 'link text', $fieldDescription ) ) {
				return Html::element( 'a', array( 'href' => $value ), $fieldDescription['link text'] );
			} else {
				// Otherwise, do nothing.
				return null;
			}
		} elseif ( $type == 'Date' || $type == 'Datetime' ) {
			global $wgAmericanDates;
			$seconds = strtotime( $value );
			if ( $wgAmericanDates ) {
				// We use MediaWiki's representation of month
				// names, instead of PHP's, because its i18n
				// support is of course far superior.
				$dateText = CargoDrilldownUtils::monthToString( date( 'm', $seconds ) );
				$dateText .= ' ' . date( 'j, Y', $seconds );
			} else {
				$dateText = date( 'Y-m-d', $seconds );
			}
			if ( $type == 'Date' ) {
				return $dateText;
			}

			// It's a Datetime - add time as well.
			// @TODO - have some variable for 24-hour time display?
			$timeText = date( 'g:i:s A', $seconds );
			return "$dateText $timeText";
		} elseif ( $type == 'Wikitext' || $type == '' ) {
			return CargoUtils::smartParse( $value, $parser );
		}
		// If it's not any of these specially-handled types, just
		// return null.
	}

}
