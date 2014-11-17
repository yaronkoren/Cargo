<?php
/**
 * @author Yaron Koren
 * @ingroup Cargo
 *
 * Abstract class for formats that run the query or queries themselves,
 * instead of getting the results passed in to them.
 */

abstract class CargoDeferredFormat extends CargoDisplayFormat {
	function isDeferred() {
		return true;
	}

	/**
	 * Turns one or more Cargo SQL query objects into a set of URL
	 * query string parameters.
	 */
	function sqlQueriesToQueryParams( $sqlQueries ) {
		$queryParams = array(
			'tables' => array(),
			'join on' => array(),
			'fields' => array(),
			'where' => array(),
		);
		if ( count( $sqlQueries ) == 0 ) {
			return null;
		} elseif ( count( $sqlQueries ) == 1 ) {
			$sqlQuery = $sqlQueries[0];
			$queryParams['tables'] = implode( ',', $sqlQuery->mTableNames );
			if ( $sqlQuery->mJoinOnStr != '' ) {
				$queryParams['join on'] = $sqlQuery->mJoinOnStr;
			}
			if ( $sqlQuery->mFieldsStr != '' ) {
				$queryParams['fields'] = $sqlQuery->mFieldsStr;
			}
			if ( $sqlQuery->mWhere != '' ) {
				$queryParams['where'] = $sqlQuery->mWhere;
			}
			if ( $sqlQuery->mGroupBy != '' ) {
				$queryParams['group by'] = $sqlQuery->mGroupBy;
			}
			if ( $sqlQuery->mOrderBy != '' ) {
				$queryParams['order by'] = $sqlQuery->mOrderBy;
			}
			if ( $sqlQuery->mQueryLimit != '' ) {
				$queryParams['limit'] = $sqlQuery->mQueryLimit;
			}
		} else {
			foreach ( $sqlQueries as $i => $sqlQuery ) {
				$queryParams['tables'][] = implode( ',', $sqlQuery->mTableNames );
				$queryParams['join on'][] = $sqlQuery->mJoinOnStr;
				$queryParams['fields'][] = $sqlQuery->mFieldsStr;
				$queryParams['where'][] = $sqlQuery->mWhere;
				$queryParams['group by'][] = $sqlQuery->mGroupBy;
				$queryParams['order by'][] = $sqlQuery->mOrderBy;
				$queryParams['limit'][] = $sqlQuery->mQueryLimit;
			}
		}

		return $queryParams;
	}

	/**
	 * Must be defined for any class that inherits from this one.
	 */
	abstract function queryAndDisplay( $sqlQueries, $displayParams, $querySpecificParams = null );

}
