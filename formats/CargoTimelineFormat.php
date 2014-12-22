<?php
/**
 * @author Yaron Koren
 * @ingroup Cargo
 */

class CargoTimelineFormat extends CargoDeferredFormat {
	function allowedParameters() {
		return array();
	}

	function queryAndDisplay( $sqlQueries, $displayParams, $querySpecificParams = null ) {
		$this->mOutput->addModules( 'ext.cargo.timeline' );
		$ce = SpecialPage::getTitleFor( 'CargoExport' );
		$queryParams = $this->sqlQueriesToQueryParams( $sqlQueries );
		$queryParams['format'] = 'timeline';
		//$queryParams['color'] = array();
		foreach ( $sqlQueries as $i => $sqlQuery ) {
			if ( $querySpecificParams != null ) {
				// Add any handling here.
			}
		}

		if ( array_key_exists( 'width', $displayParams ) ) {
			$width = $displayParams['width'];
		} else {
			$width = "100%";
		}

		$attrs = array(
			'class' => 'cargoTimeline',
			'dataurl' => $ce->getFullURL( $queryParams ),
			'style' => "width: $width; height: 350px; border: 1px solid #aaa;"
		);
		$text = Html::rawElement( 'div', $attrs, '' );

		return $text;
	}

}
