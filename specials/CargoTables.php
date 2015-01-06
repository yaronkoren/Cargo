<?php
/**
 * Defines a special page that shows the contents of a single table in
 * the Cargo database.
 *
 * @author Yaron Koren
 * @ingroup Cargo
 */

class CargoTables extends IncludableSpecialPage {

	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct( 'CargoTables' );
	}

	function execute( $query ) {
		$out = $this->getOutput();
		$this->setHeaders();

		$tableName = $query;
		if ( $tableName == '' ) {
			$out->addHTML( $this->displayListOfTables() );
			return;
		}

		$pageTitle = wfMessage( 'cargo-cargotables-viewtable', $tableName )->parse();
		$out->setPageTitle( $pageTitle );

		$cdb = CargoUtils::getDB();

		// First, display a count.
		try {
			$res = $cdb->select( $tableName, 'COUNT(*)' );
		} catch ( Exception $e ) {
			$out->addHTML( Html::element( 'div', array( 'class' => 'error' ), wfMessage( 'cargo-cargotables-tablenotfound', $tableName )->parse() ) . "\n" );
			return;
		}
		$row = $cdb->fetchRow( $res );
		$out->addWikiText( wfMessage( 'cargo-cargotables-totalrows', "'''" . $row[0] . "'''" ) . "\n" );

		$sqlQuery = new CargoSQLQuery();
		$sqlQuery->mTablesStr = $tableName;
		$sqlQuery->mTableNames = array( $tableName );

		$tableSchemas = CargoUtils::getTableSchemas( array( $tableName ) );
		$sqlQuery->mTableSchemas = $tableSchemas;

		$aliasedFieldNames = array( wfMessage( 'nstab-main' )->parse() => '_pageName' );
		foreach( $tableSchemas[$tableName]->mFieldDescriptions as $fieldName => $fieldDescription ) {
			// Skip "hidden" fields.
			if ( array_key_exists( 'hidden', $fieldDescription ) ) {
				continue;
			}

			$fieldAlias = str_replace( '_', ' ', $fieldName );
			$fieldType = $fieldDescription->mType;
			// Special handling for URLs, to avoid them
			// overwhelming the page.
			if ( $fieldType == 'URL' ) {
				// Thankfully, there's a message in core
				// MediaWiki that seems to just be "URL".
				$fieldName = "CONCAT('[', $fieldName, ' " . wfMessage( 'version-entrypoints-header-url' )->parse() . "]')";
			}

			if ( $fieldDescription->mIsList ) {
				$aliasedFieldNames[$fieldAlias] = $fieldName . '__full';
			} elseif ( $fieldType == 'Coordinates' ) {
				$aliasedFieldNames[$fieldAlias] = $fieldName . '__full';
			} else {
				$aliasedFieldNames[$fieldAlias] = $fieldName;
			}
		}

		$sqlQuery->mAliasedFieldNames = $aliasedFieldNames;
		// Set mFieldsStr in case we need to show a "More" link
		// at the end.
		$fieldsStr = '';
		foreach ( $aliasedFieldNames as $alias => $fieldName ) {
			$fieldsStr .= "$fieldName=$alias,";
		}
		// Remove the comma at the end.
		$sqlQuery->mFieldsStr = trim( $fieldsStr, ',' );

		$sqlQuery->setDescriptionsForFields();
		$sqlQuery->handleDateFields();
		$sqlQuery->setOrderBy();
		$sqlQuery->mQueryLimit = 100;

		$queryResults = $sqlQuery->run();

		$queryDisplayer = CargoQueryDisplayer::newFromSQLQuery( $sqlQuery );
		$formattedQueryResults = $queryDisplayer->getFormattedQueryResults( $queryResults );

		$displayParams = array();

		$tableFormat = new CargoTableFormat( $this->getOutput() );
		$text = $tableFormat->display( $queryResults, $formattedQueryResults, $sqlQuery->mFieldDescriptions, $displayParams );

		// If there are (seemingly) more results than what we showed,
		// show a "View more" link that links to Special:ViewData.
		if ( count( $queryResults ) == $sqlQuery->mQueryLimit ) {
			$text .= $queryDisplayer->viewMoreResultsLink();
		}

		$out->addHTML( $text );
	}

	/**
	 * Returns HTML for a bulleted list of Cargo tables, with various
	 * links and information for each one.
	 */
	function displayListOfTables() {
		global $wgUser;

		$tableNames = CargoUtils::getTables();
		$templatesThatDeclareTables = self::getAllPageProps( 'CargoTableName' );
		$templatesThatAttachToTables = self::getAllPageProps( 'CargoAttachedTable' );

		$ctPage = SpecialPageFactory::getPage( 'CargoTables' );
		$ctURL = $ctPage->getTitle()->getFullURL();
		$text = Html::element( 'p', null, wfMessage( 'cargo-cargotables-tablelist' )->parse() ) . "\n";
		$text .= "<ul>\n";
		foreach ( $tableNames as $tableName ) {
			$actionLinks = Html::element( 'a', array( 'href' => "$ctURL/$tableName", ), wfMessage( 'view' )->text() );

			// Actions for this table - this display is modeled on
			// Special:ListUsers.
			$drilldownPage = SpecialPageFactory::getPage( 'Drilldown' );
			$drilldownURL = $drilldownPage->getTitle()->getLocalURL() . '/' . $tableName;
			$drilldownURL .= ( strpos( $drilldownURL, '?' ) ) ? '&' : '?';
			$drilldownURL .= "_single";
			$actionLinks .= ' | ' . Html::element( 'a', array( 'href' => $drilldownURL ), $drilldownPage->getDescription() );

			if ( $wgUser->isAllowed( 'deletecargodata' ) ) {
				$deleteTablePage = SpecialPageFactory::getPage( 'DeleteCargoTable' );
				$deleteTableURL = $deleteTablePage->getTitle()->getLocalURL() . '/' . $tableName;
				$actionLinks .= ' | ' . Html::element( 'a', array( 'href' => $deleteTableURL ), wfMessage( 'delete' )->text() );
			}

			// "Declared by" text
			$templatesThatDeclareThisTable = $templatesThatDeclareTables[$tableName];
			if ( count( $templatesThatDeclareThisTable ) == 0 ) {
				$declaringTemplatesText = wfMessage( 'cargo-cargotables-notdeclared' )->text();
			} else {
				$templateLinks = array();
				foreach( $templatesThatDeclareThisTable as $templateID ) {
					$templateTitle = Title::newFromID( $templateID );
					$templateLinks[] = Linker::link( $templateTitle );
				}
				$declaringTemplatesText = wfMessage( 'cargo-cargotables-declaredby', implode( $templateLinks ) )->text();
			}

			// "Attached by" text
			if ( array_key_exists( $tableName, $templatesThatAttachToTables ) ) {
				$templatesThatAttachToThisTable = $templatesThatAttachToTables[$tableName];
			} else {
				$templatesThatAttachToThisTable = array();
			}

			if ( count( $templatesThatAttachToThisTable ) == 0 ) {
				$attachingTemplatesText = '';
			} else {
				$templateLinks = array();
				foreach( $templatesThatAttachToThisTable as $templateID ) {
					$templateTitle = Title::newFromID( $templateID );
					$templateLinks[] = Linker::link( $templateTitle );
				}
				$attachingTemplatesText = wfMessage( 'cargo-cargotables-attachedby', implode( $templateLinks ) )->text();
			}

			$tableText = "$tableName ($actionLinks) ($declaringTemplatesText";
			if ( $attachingTemplatesText != '' ) {
				$tableText .= ", $attachingTemplatesText";
			}
			$tableText .= ')';

			$text .= Html::rawElement( 'li', null, $tableText );
		}
		$text .= "</ul>\n";
		return $text;
	}

	/**
	 * Similar to CargoUtils::getPageProp().
	 */
	 public static function getAllPageProps( $pageProp ) {
		$dbr = wfGetDB( DB_SLAVE );
		$res = $dbr->select( 'page_props',
			array(
				'pp_page',
				'pp_value'
			),
			array(
				'pp_propname' => $pageProp
			)
		);

		$pagesPerValue = array();
		while ( $row = $dbr->fetchRow( $res ) ) {
			$pageID = $row['pp_page'];
			$pageValue = $row['pp_value'];
			if ( array_key_exists( $pageValue, $pagesPerValue ) ) {
				$pagesPerValue[$pageValue][] = $pageID;
			} else {
				$pagesPerValue[$pageValue] = array( $pageID );
			}
		}

		return $pagesPerValue;
	}

}
