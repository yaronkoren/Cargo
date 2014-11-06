<?php
/**
 * Background job to populate the database table for one template using the
 * data from the call(s) to that template in one page.
 *
 * @ingroup Cargo
 * @author Yaron Koren
 */

class CargoPopulateTableJob extends Job {
	function __construct( $title, $params = '', $id = 0 ) {
		parent::__construct( 'cargoPopulateTable', $title, $params, $id );
	}

	/**
	 * Run a CargoPopulateTable job.
	 *
	 * @return boolean success
	 */
	function run() {
		wfProfileIn( __METHOD__ );

		if ( is_null( $this->title ) ) {
			$this->error = "cargoPopulateTable: Invalid title";
			wfProfileOut( __METHOD__ );
			return false;
		}

		$article = new Article( $this->title );

		// If it was requested, delete all the existing rows for
		// this page in this Cargo table. This is only necessary
		// if the table wasn't just dropped and recreated.
		if ( $this->params['replaceOldRows'] == true ) {
			$cdb = CargoUtils::getDB();
			$cdb->delete( $this->params['dbTableName'], array( '_pageID' => $article->getID() ) );
		}

		// All we need to do here is set some global variables based
		// on the parameters of this job, then parse the page -
		// the #cargo_store function will take care of the rest.
		CargoStore::$settings['origin'] = 'template';
		CargoStore::$settings['dbTableName'] = $this->params['dbTableName'];

		// @TODO - is there a "cleaner" way to get a page to be parsed?
		global $wgParser;
		// Special handling for the Approved Revs extension.
		$pageText = null;
		$approvedText = null;
		if ( class_exists( 'ApprovedRevs' ) ) {
			$approvedText = ApprovedRevs::getApprovedContent( $this->title );
		}
		if ( $approvedText != null ) {
			$pageText = $approvedText;
		} else {
			$pageText = $article->getContent();
		}
		$wgParser->parse( $pageText, $this->title, new ParserOptions() );

		wfProfileOut( __METHOD__ );
		return true;
	}
}
