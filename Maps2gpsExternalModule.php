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
		$longitude = $this->getProjectSetting("longitude");
		$latitude = $this->getProjectSetting("latitude");
		$defaultZoom = $this->getProjectSetting("default-zoom");
		$defaultLatitude = $this->getProjectSetting("default-latitude");
		$defaultLongitude = $this->getProjectSetting("default-longitude");
		$import = $this->getProjectSetting("import-google-api");

		$key = $this->getProjectSetting("google-api-key");
		if ($longitude && $latitude) {
			$instrument = db_escape($instrument);

			$sql = "SELECT field_order, field_name
				FROM redcap_metadata
				WHERE project_id = $project_id
					AND form_name = '$instrument'
					AND field_name IN ('$longitude', '$latitude');";
			$q = db_query($sql);
			$fieldOrder = array();
			while ($row = db_fetch_assoc($q)) {
				$fieldOrder[$row['field_name']] = $row['field_order'];
			}

			if(count($fieldOrder) !== 2){
				// We must not be on the right instrument.
				return;
			}

			echo "<script>";
			if ($fieldOrder[$latitude] < $fieldOrder[$longitude]) {
				$first = $latitude;
			} else {
				$first = $longitude;
			}
			echo '
				console.log("Loading Maps2GPS")

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
				var DEFAULT_CENTER_LATITUDE = '.$defaultLatitude.';
				var DEFAULT_CENTER_LONGITUDE = '.$defaultLongitude.';
				var DEFAULT_ZOOM_WHEN_COORDINATE_EXISTS = 15;

				// This is the zoom level required to position the marker
				var REQUIRED_ZOOM = 15;
                if (typeof google.load == "undefined") {
                    $.getScript("https://www.google.com/jsapi?key='.$key.'", function() { console.log("Got JSAPI"); initMaps(); });
                } else {
                    console.log("Did not get JSAPI");
                    initMaps();
                }

                var map;
                var marker;
                // we have the google libraries
                function initMaps() {
                    // http://stackoverflow.com/questions/9519673/why-does-google-load-cause-my-page-to-go-blank
                    setTimeout(function(){ google.load("maps", "2.x", {
                        callback: function () {
				            // The google map variable
				            map = null;
				
				            // The marker variable, when it is null no marker has been added
				            marker = null;

                            initializeGoogleMap();
                        }
                    }); }, 2000);

                }

				function initializeGoogleMap() {
					map = new google.maps.Map2(document.getElementById(MAP_DIV_ELEMENT_ID));
					map.addControl(new GLargeMapControl());
					map.addControl(new GMapTypeControl());

					map.setMapType(G_NORMAL_MAP);

					var latitude = +document.getElementById(LATITUDE_ELEMENT_ID).value;
					var longitude = +document.getElementById(LONGITUDE_ELEMENT_ID).value;

					if(latitude != 0 && longitude != 0) {
						//We have some sort of starting position, set map center and marker
						map.setCenter(new google.maps.LatLng(latitude, longitude), DEFAULT_ZOOM_WHEN_COORDINATE_EXISTS);
						var point = new GLatLng(latitude, longitude);
						marker = new GMarker(point, {draggable:false});
						map.addOverlay(marker);
					} else {
						// Just set the default center, do not add a marker
						map.setCenter(new google.maps.LatLng(DEFAULT_CENTER_LATITUDE, DEFAULT_CENTER_LONGITUDE), DEFAULT_ZOOM_WHEN_NO_COORDINATE_EXISTS);
					}

					GEvent.addListener(map, "click", googleMapClickHandler);
				}

                $("#'.$latitude.'").change(function() { resetMarker(); });
                $("#'.$longitude.'").change(function() { resetMarker(); });

                function resetMarker() {
                    var latitude = $("#'.$latitude.'").val();
                    var longitude = $("#'.$longitude.'").val();
					if(latitude != 0 && longitude != 0) {
						//We have some sort of starting positon, set map center and marker
						map.setCenter(new google.maps.LatLng(latitude, longitude), DEFAULT_ZOOM_WHEN_COORDINATE_EXISTS);
						var point = new GLatLng(latitude, longitude);
					    if(marker == null) {
						    marker = new GMarker(point, {draggable:false});
						    map.addOverlay(marker);
                        } else {
						    marker.setLatLng(point);
                        }
					} else {
						// Just set the default center, do not add a marker
						map.setCenter(new google.maps.LatLng(DEFAULT_CENTER_LATITUDE, DEFAULT_CENTER_LONGITUDE), DEFAULT_ZOOM_WHEN_NO_COORDINATE_EXISTS);
					}
                }

				function googleMapClickHandler(overlay, latlng, overlaylatlng) {

					if(map.getZoom() < REQUIRED_ZOOM) {
						alert("You need to zoom in more to set the location accurately." );
						return;
					}
					if(marker == null) {
						marker = new GMarker(latlng, {draggable:false});
						map.addOverlay(marker);
					}
					else {
						marker.setLatLng(latlng);
					}

					document.getElementById(LATITUDE_ELEMENT_ID).value = latlng.lat();
					document.getElementById(LONGITUDE_ELEMENT_ID).value = latlng.lng();
				
				}
                ';
            echo '}';

			echo "</script>";
            if ($import) {
                echo "<script type=\"text/javascript\" src=\"https://maps.googleapis.com/maps/api/js?key=".$key."\"></script>";
            }
		}
	}
}
