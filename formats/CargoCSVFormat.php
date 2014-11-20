<?php
/**
 * @author Yaron Koren
 * @ingroup Cargo
 */

class CargoCSVFormat extends CargoDeferredFormat {

	function queryAndDisplay( $sqlQueries, $displayParams, $querySpecificParams = null ) {
		$ce = SpecialPage::getTitleFor( 'CargoExport' );
		$queryParams = $this->sqlQueriesToQueryParams( $sqlQueries );
		$queryParams['format'] = 'csv';

		$linkAttrs = array(
			'href' => $ce->getFullURL( $queryParams ),
		);
		$text = Html::rawElement( 'a', $linkAttrs, wfMessage( 'cargo-viewcsv' )->text() );

		return $text;
	}

}
