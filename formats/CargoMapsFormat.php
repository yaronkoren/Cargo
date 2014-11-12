<?php
/**
 * @author Yaron Koren
 * @ingroup Cargo
 */

class CargoMapsFormat extends CargoDisplayFormat {

	public static $mappingService = "OpenLayers";
	static $mapNumber = 1;

	function allowedParameters() {
		return array( 'height', 'width', 'icon', 'zoom' );
	}

	/**
	 * Meant to be overloaded.
	 */
	public function getScripts() {
		return array();
	}

	/**
	 * Based on the Maps extension's getFileUrl().
	 */
	public static function getImageURL( $imageName ) {
		$title = Title::makeTitle( NS_FILE, $imageName );

		if ( $title == null || !$title->exists() ) {
			return null;
		}

		$imagePage = new ImagePage( $title );
		return $imagePage->getDisplayedFile()->getURL();
	}

	function display( $valuesTable, $formattedValuesTable, $fieldDescriptions, $displayParams ) {
		$coordinatesFields = array();
		foreach( $fieldDescriptions as $field => $description ) {
			if ( $description['type'] == 'Coordinates' ) {
				$coordinatesFields[] = $field;
			}
		}

		if ( count( $coordinatesFields ) == 0 ) {
			throw new MWException( "Error: no fields of type \"Coordinate\" were specified in this query; cannot display in a map." );
		}

		// @TODO - should this check be higher up, i.e. for all
		// formats?
		if ( count( $formattedValuesTable ) == 0 ) {
			throw new MWException( "No results found for this query; not displaying a map." );
		}

		// Add necessary JS scripts.
		$scripts = $this->getScripts();
		$scriptsHTML = '';
		foreach ( $scripts as $script ) {
			$scriptsHTML .= Html::linkedScript( $script );
		}
		$this->mOutput->addHeadItem( $scriptsHTML, $scriptsHTML );
		$this->mOutput->addModules( 'ext.cargo.maps' );

		// Construct the table of data we will display.
		$valuesForMap = array();
		foreach ( $formattedValuesTable as $i => $valuesRow ) {
			$displayedValuesForRow = array();
			foreach ( $valuesRow as $fieldName => $fieldValue ) {
				if ( !array_key_exists( $fieldName, $fieldDescriptions ) ) {
					continue;
				}
				$fieldType = $fieldDescriptions[$fieldName]['type'];
				if ( $fieldType == 'Coordinates' || $fieldType == 'Coordinates part' ) {
					// Actually, we can ignore these.
					continue;
				}
				if ( $fieldValue == '' ) {
					continue;
				}
				$displayedValuesForRow[$fieldName] = $fieldValue;
			}
			// There could potentially be more than one
			// coordinate for this "row".
			// @TODO - handle lists of coordinates as well.
			foreach ( $coordinatesFields as $coordinatesField ) {
				$latValue = $valuesRow[$coordinatesField . '  lat'];
				$lonValue = $valuesRow[$coordinatesField . '  lon'];
				// @TODO - enforce the existence of a field
				// besides the coordinates field(s).
				$firstValue = array_shift( $displayedValuesForRow );
				if ( $latValue != '' && $lonValue != '' ) {
					$valuesForMapPoint = array(
						// 'name' has no formatting
						// (like a link), while 'title'
						// might.
						'name' => array_shift( $valuesTable[$i] ),
						'title' => $firstValue,
						'lat' => $latValue,
						'lon' => $lonValue,
						'otherValues' => $displayedValuesForRow
					);
					if ( array_key_exists( 'icon', $displayParams ) && array_key_exists( $i, $displayParams['icon'] ) ) {
						$iconURL = self::getImageURL( $displayParams['icon'][$i] );
						if ( !is_null( $iconURL ) ) {
							$valuesForMapPoint['icon'] = $iconURL;
						}
					}
					$valuesForMap[] = $valuesForMapPoint;
				}
			}
		}

		$service = self::$mappingService;
		$jsonData = json_encode( $valuesForMap, JSON_NUMERIC_CHECK | JSON_HEX_TAG );
		$divID = "mapCanvas" . self::$mapNumber++;

		if ( array_key_exists( 'height', $displayParams ) ) {
			$height = $displayParams['height'];
		} else {
			$height = "400px";
		}
		if ( array_key_exists( 'width', $displayParams ) ) {
			$width = $displayParams['width'];
		} else {
			$width = "700px";
		}

		// The 'map data' div does double duty: it holds the full
		// set of map data, as well as, in the tag attributes,
		// settings related to the display, including the mapping
		// service to use.
		$mapDataAttrs = array(
			'class' => 'cargoMapData',
			'style' => 'display: none',
			'mappingService' => $service
		);
		if ( array_key_exists( 'zoom', $displayParams ) ) {
			$mapDataAttrs['zoom'] = $displayParams['zoom'];
		}
		$mapDataDiv = Html::element( 'div', $mapDataAttrs, $jsonData );

		$text =<<<END
	<div style="height: $height; width: $width" class="mapCanvas" id="$divID">
		$mapDataDiv
	</div>

END;
		return $text;
	}

}
