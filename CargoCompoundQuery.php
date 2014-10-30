<?php
/**
 * CargoCompoundQuery - class for the #cargo_compound_query parser function.
 *
 * @author Yaron Koren
 * @ingroup Cargo
 */

class CargoCompoundQuery {

	/**
	 * Handles the #cargo_compound_query parser function - calls a
	 * "compound query", consisting of two or more queries of the
	 * Cargo data stored in the database, and then displays their
	 * results together.
	 *
	 * This function is based conceptually on the #compound_query
	 * parser function defined by the Semantic Compound Queries
	 * extension.
	 */
	public static function run( &$parser ) {
		$params = func_get_args();
		array_shift( $params ); // we already know the $parser...

		// Split up the parameters into query params and other params -
		// we do that just by looking for the string "tables=";
		// hopefully that will never show up in non-query params.
		// Another possibility is to always check for ";", but query
		// params can in theory hold a "tables=" clause and nothing
		// else.
		$queryParams = $otherParams = array();
		foreach ( $params as $param ) {
			 if ( strpos( $param, 'tables=' ) !== false ) {
				$queryParams[] = $param;
			} else {
				$otherParams[] = $param;
			}
		}

		$sqlQueries = array();
		$querySpecificParams = array();
		foreach ( $queryParams as $param ) {
			$tablesStr = null;
			$fieldsStr = null;
			$whereStr = null;
			$joinOnStr = null;
			$groupByStr = null;
			$orderByStr = null;
			$limitStr = null;
			$format = 'auto'; // default
			$displayParams = array();

			$queryClauses = explode( ';', $param );
			$displayParamsForThisQuery = array();
			foreach( $queryClauses as $clause ) {
				$parts = explode( '=', $clause, 2 );
				if ( count( $parts ) != 2 ) {
					continue;
				}
				$key = trim( $parts[0] );
				$value = trim( $parts[1] );
				if ( $key == 'tables' ) {
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
				} else {
					$displayParamsForThisQuery[$key] = $value;
				}
			}
			$sqlQueries[] = CargoSQLQuery::newFromValues( $tablesStr, $fieldsStr, $whereStr, $joinOnStr, $groupByStr, $orderByStr, $limitStr );
			$querySpecificParams[] = $displayParamsForThisQuery;
		}

		foreach ( $otherParams as $param ) {
			$parts = explode( '=', $param, 2 );
			
			if ( count( $parts ) != 2 ) {
				continue;
			}
			$key = trim( $parts[0] );
			$value = trim( $parts[1] );
			if ( $key == 'format' ) {
				$format = $value;
			} else {
				// We'll assume it's going to the formatter.
				$displayParams[$key] = $value;
			}
		}

		try {
			$queryResults = self::getOrDisplayQueryResultsFromStrings( $sqlQueries, $querySpecificParams, $format, $displayParams, $parser );
		} catch ( Exception $e ) {
			return CargoUtils::formatError( $e->getMessage() );
		}

		return $queryResults;
	}

	public static function getOrDisplayQueryResultsFromStrings( $sqlQueries, $querySpecificParams, $format = null, $displayParams = null, $parser = null ) {
		$formatClass = CargoQuery::getFormatClass( $format, array() );
		$formatObject = new $formatClass( $parser->getOutput() );
		if ( $formatObject->isDeferred() ) {
			$text = $formatObject->queryAndDisplay( $sqlQueries, $displayParams, $querySpecificParams );
			$text = $parser->insertStripItem( $text, $parser->mStripState );
			return $text;
		}

		$allQueryResults = array();
		$formattedQueryResults = array();
		$allFieldDescriptions = array();

		$rowNum = 0;
		foreach ( $sqlQueries as $i => $sqlQuery ) {
			$queryResults = $sqlQuery->run();
			$allQueryResults = array_merge( $allQueryResults, $queryResults );
			$formattedQueryResults = array_merge( $formattedQueryResults, CargoQuery::getFormattedQueryResults( $queryResults, $sqlQuery->mFieldDescriptions, $parser ) );
			//$formattedQueryResultsArray[] = $formattedQueryResults;
			foreach ( $sqlQuery->mFieldDescriptions as $alias => $description ) {
				$allFieldDescriptions[$alias] = $description;
			}

			// Now add this query's own display parameters to
			// the row for every result of that query within an
			// array contained in the $diaplayParams object.
			$numResultsForThisQuery = count( $queryResults );
			$displayParamsForThisQuery = $querySpecificParams[$i];
			foreach ( $displayParamsForThisQuery as $paramName => $paramValue ) {
				if ( array_key_exists( $paramName, $displayParams ) ) {
					// Just make sure it's an array.
					if ( !is_array( $displayParams[$paramName] ) ) {
						throw new MWException( "Error: \"$paramName\" cannot be used as both a query-specific parameter and an overall display parameter." );
					}
				} else {
					$displayParams[$paramName] = array();
				}
				// Now, add it in for each row.
				for ( $j = $rowNum; $j < $rowNum + $numResultsForThisQuery; $j++ ) {
					$displayParams[$paramName][$j] = $paramValue;
				}
			}

			$rowNum += $numResultsForThisQuery;
		}

		if ( is_null( $format ) ) {
			return $allQueryResults;
		}

		// Finally, do the display, based on the format.
		$text = $formatObject->display( $allQueryResults, $formattedQueryResults, $allFieldDescriptions, $displayParams );

		// Don't show a "view more" link.
		// @TODO - is such a thing possible for a compound query,
		// especially if there's a limit set for each query?

		$text = $parser->insertStripItem( $text, $parser->mStripState );

		return $text;
	}

}
