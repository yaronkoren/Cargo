<?php
/**
 * CargoHooks class
 *
 * @author Yaron Koren
 * @ingroup Cargo
 */

class CargoHooks {

	static function setGlobalJSVariables( &$vars ) {
		global $wgCargoMapClusteringMinimum;

		$vars['wgCargoMapClusteringMinimum'] = $wgCargoMapClusteringMinimum;

		// Date-related arrays for the 'calendar' and 'timeline'
		// formats.
		// Built-in arrays already exist for month names, but those
		// unfortunately are based on the language of the wiki, not
		// the language of the user.
		$vars['wgCargoMonthNames'] = array( wfMessage( 'january' )->text(), wfMessage( 'february' )->text(), wfMessage( 'march' )->text(), wfMessage( 'april' )->text(), wfMessage( 'may-long' )->text(), wfMessage( 'june' )->text(), wfMessage( 'july' )->text(), wfMessage( 'august' )->text(), wfMessage( 'september' )->text(), wfMessage( 'october' )->text(), wfMessage( 'november' )->text(), wfMessage( 'december' )->text() );
		$vars['wgCargoMonthNamesShort'] = array( wfMessage( 'jan' )->text(), wfMessage( 'feb' )->text(), wfMessage( 'mar' )->text(), wfMessage( 'apr' )->text(), wfMessage( 'may' )->text(), wfMessage( 'jun' )->text(), wfMessage( 'jul' )->text(), wfMessage( 'aug' )->text(), wfMessage( 'sep' )->text(), wfMessage( 'oct' )->text(), wfMessage( 'nov' )->text(), wfMessage( 'dec' )->text() );
		$vars['wgCargoWeekDays'] = array( wfMessage( 'sunday' )->text(), wfMessage( 'monday' )->text(), wfMessage( 'tuesday' )->text(), wfMessage( 'wednesday' )->text(), wfMessage( 'thursday' )->text(), wfMessage( 'friday' )->text(), wfMessage( 'saturday' )->text() );
		$vars['wgCargoWeekDaysShort'] = array( wfMessage( 'sun' )->text(), wfMessage( 'mon' )->text(), wfMessage( 'tue' )->text(), wfMessage( 'wed' )->text(), wfMessage( 'thu' )->text(), wfMessage( 'fri' )->text(), wfMessage( 'sat' )->text() );
		return true;
	}

	public static function addPurgeCacheTab( SkinTemplate &$skinTemplate, array &$links ) {
		// Only add this tab if Semantic MediaWiki (which has its
		// identical "refresh" tab) is not installed.
		if ( defined( 'SMW_VERSION' ) ) {
			return true;
		}

		if ( $skinTemplate->getUser()->isAllowed( 'purge' ) ) {
			$links['actions']['cargo-purge'] = array(
				'class' => false,
				'text' => $skinTemplate->msg( 'cargo-purgecache' )->text(),
				'href' => $skinTemplate->getTitle()->getLocalUrl( array( 'action' => 'purge' ) )
			);
		}

		return true;
	}

	/**
	 * @TODO - move this to a different class, like CargoUtils?
	 */
	public static function deletePageFromSystem( $pageID ) {
		// We'll delete every reference to this page in the
		// Cargo tables - in the data tables as well as in
		// cargo_pages. (Though we need the latter to be able to
		// efficiently delete from the former.)

		// Get all the "main" tables that this page is contained in.
		$dbr = wfGetDB( DB_MASTER );
		$cdb = CargoUtils::getDB();
		$res = $dbr->select( 'cargo_pages', 'table_name', array( 'page_id' => $pageID ) );
		while ( $row = $dbr->fetchRow( $res ) ) {
			$curMainTable = $row['table_name'];

			// First, delete from the "field" tables.
			$res2 = $dbr->select( 'cargo_tables', 'field_tables', array( 'main_table' => $curMainTable ) );
			$row2 = $dbr->fetchRow( $res2 );
			$fieldTableNames = unserialize( $row2['field_tables'] );
			foreach ( $fieldTableNames as $curFieldTable ) {
				// Thankfully, the MW DB API already provides a
				// nice method for deleting based on a join.
				$cdb->deleteJoin( $curFieldTable, $curMainTable, '_rowID', '_ID', array( '_pageID' => $pageID ) );
			}

			// Now, delete from the "main" table.
			$cdb->delete( $curMainTable, array( '_pageID' => $pageID ) );
		}

		// Finally, delete from cargo_pages.
		$dbr->delete( 'cargo_pages', array( 'page_id' => $pageID ) );

		// This call is needed to get deletions to actually happen.
		$cdb->close();
	}

	/**
	 * Called by the MediaWiki 'PageContentSaveComplete' hook.
	 *
	 * We use that hook, instead of 'PageContentSave', because we need
	 * the page ID to have been set already for newly-created pages.
	 */
	public static function onPageContentSaveComplete( $article, $user, $content, $summary, $isMinor, $isWatch, $section, $flags, $status ) {
		// First, delete the existing data.
		$pageID = $article->getID();
		self::deletePageFromSystem( $pageID );

		// Now parse the page again, so that #cargo_store will be
		// called.
		// Even though the page will get parsed again after the save,
		// we need to parse it here anyway, for the settings we
		// added to remain set.
		CargoStore::$settings['origin'] = 'page save';
		global $wgParser;
		$title = $article->getTitle();

		// Special handling for the Approved Revs extension.
		$pageText = null;
		$approvedText = null;
		if ( class_exists( 'ApprovedRevs' ) ) {
			$approvedText = ApprovedRevs::getApprovedContent( $title );
		}
		if ( $approvedText != null ) {
			$pageText = $approvedText;
		} else {
			$pageText = $content->getNativeData();
		}

		$wgParser->parse( $pageText, $title, new ParserOptions() );
		return true;
	}

	/**
	 * Called by a hook in the Approved Revs extension.
	 */
	public static function onARRevisionApproved( $parser, $title, $revID ) {
		$pageID = $title->getArticleID();
		self::deletePageFromSystem( $pageID );
		// In an unexpected surprise, it turns out that simply adding
		// this setting will be enough to get the correct revision of
		// this page to be saved by Cargo, since the page will be
		// parsed right after this.
		CargoStore::$settings['origin'] = 'Approved Revs revision approved';
		return true;
	}

	/**
	 * Called by a hook in the Approved Revs extension.
	 */
	public static function onARRevisionUnapproved( $parser, $title ) {
		$pageID = $title->getArticleID();
		self::deletePageFromSystem( $pageID );
		// This is all we need - see onARRevisionApproved(), above.
		CargoStore::$settings['origin'] = 'Approved Revs revision unapproved';
		return true;
	}

	public static function onTitleMoveComplete( Title &$title, Title &$newtitle, User &$user, $oldid, $newid, $reason ) {
		// For each main data table to which this page belongs, change
		// the page name.
		$newPageName = $newtitle->getPrefixedText();
		$dbr = wfGetDB( DB_MASTER );
		$cdb = CargoUtils::getDB();
		// We use $oldid, because that's the page ID - $newid is the
		// ID of the redirect page.
		// @TODO - do anything with the redirect?
		$res = $dbr->select( 'cargo_pages', 'table_name', array( 'page_id' => $oldid ) );
		while ( $row = $dbr->fetchRow( $res ) ) {
			$curMainTable = $row['table_name'];
			$cdb->update( $curMainTable, array( '_pageName' => $newPageName ), array( '_pageID' => $oldid ) );
		}

		return true;
	}

	/**
	 * Deletes all Cargo data about a page, if the page has been deleted.
	 */
	public static function onArticleDeleteComplete( &$article, User &$user, $reason, $id, $content, $logEntry ) {
		self::deletePageFromSystem( $id );
		return true;
	}

	public static function describeDBSchema( $updater = null ) {
		$dir = dirname( __FILE__ );

		// DB updates
		// For now, there's just a single SQL file for all DB types.
		if ( $updater === null ) {
			global $wgExtNewTables, $wgDBtype;
			//if ( $wgDBtype == 'mysql' ) {
				$wgExtNewTables[] = array( 'cargo_tables', "$dir/Cargo.sql" );
				$wgExtNewTables[] = array( 'cargo_pages', "$dir/Cargo.sql" );
			//}
		} else {
			//if ( $updater->getDB()->getType() == 'mysql' ) {
				$updater->addExtensionUpdate( array( 'addTable', 'cargo_tables', "$dir/Cargo.sql", true ) );
				$updater->addExtensionUpdate( array( 'addTable', 'cargo_pages', "$dir/Cargo.sql", true ) );
			//}
		}
		return true;
	}

	public static function addToAdminLinks( &$adminLinksTree ) {
		$browseSearchSection = $adminLinksTree->getSection( wfMessage( 'adminlinks_browsesearch' )->text() );
		$cargoRow = new ALRow( 'cargo' );
		$cargoRow->addItem( ALItem::newFromSpecialPage( 'CargoTables' ) );
		$cargoRow->addItem( ALItem::newFromSpecialPage( 'Drilldown' ) );
		$browseSearchSection->addRow( $cargoRow );

		return true;
	}

}
