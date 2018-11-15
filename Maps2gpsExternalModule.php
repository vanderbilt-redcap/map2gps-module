<?php namespace Vanderbilt\Maps2gpsExternalModule;

use ExternalModules\AbstractExternalModule;
use ExternalModules\ExternalModules;

class Maps2gpsExternalModule extends AbstractExternalModule
{
	function hook_data_entry_form($project_id, $record, $instrument, $event_id, $group_id) {
		$this->placeMap($project_id, $record, $instrument, $event_id, $group_id);
	}

	function hook_survey_page($project_id, $record, $instrument, $event_id, $group_id) {
		$this->placeMap($project_id, $record, $instrument, $event_id, $group_id);
	}

	function placeMap($project_id, $record, $instrument, $event_id, $group_id) {
		$desiredInstrument = $this->getProjectSetting("instrument");
		if(is_array($desiredInstrument)){
			// This field used to be marked as repeatable.
			$desiredInstrument = $desiredInstrument[0];
		}

		$longitude = $this->getProjectSetting("longitude");
		$latitude = $this->getProjectSetting("latitude");
		$defaultZoom = $this->getProjectSetting("default-zoom");
		$defaultLatitude = $this->getProjectSetting("default-latitude");
		$defaultLongitude = $this->getProjectSetting("default-longitude");
		$import = $this->getProjectSetting("import-google-api");

		$recordData = $this->getData($project_id,$record,$event_id);
		$startLat = $recordData[$latitude];
		$startLong = $recordData[$longitude];

		$key = $this->getProjectSetting("google-api-key");
		if (($desiredInstrument == $instrument) && $longitude && $latitude) {
			$sql = "SELECT field_order, field_name
				FROM redcap_metadata
				WHERE project_id = $project_id
					AND field_name IN ('$longitude', '$latitude');";
			$q = db_query($sql);
			$fieldOrder = array();
			while ($row = db_fetch_assoc($q)) {
				$fieldOrder[$row['field_name']] = $row['field_order'];
			}

			echo "<script>";
			if ($fieldOrder[$latitude] < $fieldOrder[$longitude]) {
				$first = $latitude;
			} else {
				$first = $longitude;
			}
            echo '
                var oldBranching = doBranching;
                doBranching = function() {
                    console.log("Revised doBranching");
                    oldBranching();
                    if ($("#'.$latitude.'-tr").is(":visible")) {
                        $("#google_map-tr").show();
                    } else {
                        $("#google_map-tr").hide();
                    }
                }
                ';
            echo "window.onload=function() { ";
            echo "$('[name=\"$longitude\"]').attr('id', '$longitude');";
            echo "$('[name=\"$latitude\"]').attr('id', '$latitude');";
            $width = 600;
			echo "$('#$first').closest('tr').before(\"<tr id='google_map-tr'><td colspan='2' style='text-align: center;'><div style='width:".$width."px; font-size: 12px; font-style: italic;'>Use the controls to zoom. Double click to set the coordinates.</div><div id='google_map' style='width:".$width."px; height:450px;'></div></td></tr>\");";

            if (!$defaultLatitude) {
                $defaultLatitude = 40;
            }
            if (!$defaultLongitude) {
                $defaultLongitude = -100;
            }
            if (!$defaultZoom) {
                $defaultZoom = 15;
            }
			echo '
                doBranching();

				var LATITUDE_ELEMENT_ID = "'.$latitude.'";
				var LONGITUDE_ELEMENT_ID = "'.$longitude.'";
				var MAP_DIV_ELEMENT_ID = "google_map";

				var DEFAULT_ZOOM_WHEN_NO_COORDINATE_EXISTS = '.$defaultZoom.';
				var DEFAULT_CENTER_LATITUDE = '.(empty($startLat) ? $defaultLatitude : $startLat).';
				var DEFAULT_CENTER_LONGITUDE = '.(empty($startLong) ? $defaultLongitude : $startLong).';
				var DEFAULT_ZOOM_WHEN_COORDINATE_EXISTS = 15;

				// This is the zoom level required to position the marker
				var REQUIRED_ZOOM = 15;
                if (typeof google == "undefined" || typeof google.maps == "undefined") {
                    $.getScript("https://maps.googleapis.com/maps/api/js?key='.$key.'", function() { console.log("Got JSAPI"); initMaps(); });
                } else {
                    console.log("Did not get JSAPI");
                    initMaps();
                }

                var map;
                var marker;
                // we have the google libraries
                function initMaps() {
					initializeGoogleMap();
                }

				function initializeGoogleMap() {
					map = new google.maps.Map(document.getElementById(MAP_DIV_ELEMENT_ID),
						{
							zoom: DEFAULT_ZOOM_WHEN_COORDINATE_EXISTS,
							center: new google.maps.LatLng(DEFAULT_CENTER_LATITUDE,DEFAULT_CENTER_LONGITUDE),
							overviewMapControl:true,
							overviewMapControlOptions:(true),
							mapTypeId: google.maps.MapTypeId.ROADMAP,
							streetViewControl: false,
							fullscreenControl: false
						});
					marker = new google.maps.Marker({map:map,position: new google.maps.LatLng(DEFAULT_CENTER_LATITUDE,DEFAULT_CENTER_LONGITUDE)});

					google.maps.event.addListener(map, "click", googleMapClickHandler);
				}

                $("#'.$latitude.'").change(function() { resetMarker(); });
                $("#'.$longitude.'").change(function() { resetMarker(); });

                function resetMarker() {
                    var latitude = $("#'.$latitude.'").val();
                    var longitude = $("#'.$longitude.'").val();
					if(latitude != 0 && longitude != 0) {
						//We have some sort of starting positon, set map center and marker
						map.setCenter(new google.maps.LatLng(latitude, longitude), DEFAULT_ZOOM_WHEN_COORDINATE_EXISTS);
						var point = new google.maps.LatLng(latitude,longitude)
					    if(marker == null) {
						    marker = new google.maps.Marker({map:map,position: point,draggable:false});
                        } else {
						    marker.position = point;
                        }
                        marker.setMap(map);
					} else {
						// Just set the default center, do not add a marker
						map.setCenter(new google.maps.LatLng(DEFAULT_CENTER_LATITUDE, DEFAULT_CENTER_LONGITUDE), DEFAULT_ZOOM_WHEN_NO_COORDINATE_EXISTS);
					}
                }

				function googleMapClickHandler(event) {
					if(map.getZoom() < REQUIRED_ZOOM) {
						alert("You need to zoom in more to set the location accurately." );
						return;
					}
					if(marker == null) {
						marker.position = point;
					}
					else {
						marker.position = event.latLng;
					}
					marker.setMap(map);

					document.getElementById(LATITUDE_ELEMENT_ID).value = event.latLng.lat();
					document.getElementById(LONGITUDE_ELEMENT_ID).value = event.latLng.lng();

					$("#" + LATITUDE_ELEMENT_ID).change();
				}
                ';
            echo '}';

			echo "</script>";
            if ($import) {

				echo "<script type=\"text/javascript\" src=\"https://maps.googleapis.com/maps/api/js?key=".$key."&libraries=places\"></script>";}
		}
	}
}
