<?php
header('Location: test4.php');
exit();
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>STIB-MIVB API</title>
    <link rel="stylesheet" href="ol.css">
    <link rel="stylesheet" href="style.css">
  </head>
  <body>
<?php
$dblink = new MySQLi('localhost', 'root', 'thestedreqaphespudac3mezacedraZa', 'stib');

$d = date('N'); $dates = array(1 => 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday');

$services = array();
$q = $dblink->query("SELECT `service_id` FROM `calendar` WHERE `start_date` <= ".date('Ymd')." AND `end_date` >= ".date('Ymd')." /*AND `".$dates[$d]."` = 1*/");
while ($r = $q->fetch_row()) { $services[] = $r[0]; } $q->free();
?>
    <div id="map" class="map">
      <div class="options">
        <select id="line" style="width:100%;">
<?php
  $prev_type = NULL;
  $q = $dblink->query("SELECT `route_type`, `route_long_name`, `route_short_name`/*, `trip_headsign`*/, `route_color`, `trip_id`, `shape_id` FROM `trips` t LEFT JOIN `routes` r USING(`route_id`) WHERE `service_id` IN('".implode("','", $services)."') GROUP BY `route_id`/*, `direction_id`*/ ORDER BY `route_type`, `route_id`, `direction_id`") or trigger_error($dblink->error);
  while ($r = $q->fetch_assoc()) {
    if ($r['route_type'] != $prev_type) {
      switch($r['route_type']) {
        case 0: $t = 'Tram'; break;
        case 1: $t = 'Metro'; break;
        case 2: $t = 'Train'; break;
        case 3: $t = 'Bus'; break;
      }
      if (!is_null($prev_type)) echo '</optgroup>';
      echo '<optgroup label="'.$t.'">';
    }
    echo '<option value="'.$r['route_short_name'].'" data-color="'.$r['route_color'].'" data-shapeid="'.$r['shape_id'].'" data-tripid="'.$r['trip_id'].'">'.$r['route_short_name'].' :: '.$r['route_long_name']./*' :: '.$r['trip_headsign'].*/'</option>';
    $prev_type = $r['route_type'];
  }
  $q->free();
  echo '</optgroup>';
?>
        </select>
        <div id="time" style="width: 100%; color: #808080; text-align: right; font-size: small;"></div>
      </div>
    </div>
    <script src="https://code.jquery.com/jquery-3.1.0.min.js"></script>
    <script src="ol.js"></script>
    <script>
      var map;
      var stopsSource, stopsLayer, stopsActive = new Array();
      var lineSource, lineLayer;
      var intervalID;

      var me = this;
          me.apiToken = 'a9243c8f98dcd5e11e1aee6b8dd4fdf8';
          me.openDataBaseUrl = 'https://opendata-api.stib-mivb.be';

      $(document).ready(function() {
        map = new ol.Map({
          target: 'map',
          layers: [ new ol.layer.Tile({ source: new ol.source.OSM() }) ],
          view: new ol.View({
            center: ol.proj.fromLonLat([37.41, 8.82]),
            zoom: 4
          })
        });

        stopsSource = new ol.source.Vector({
          features: (new ol.format.GeoJSON()).readFeatures(<?= json_encode(json_decode(file_get_contents('stops.json'))) ?>, { featureProjection: 'EPSG:3857' })
        });

        stopsLayer = new ol.layer.Vector({
          source: stopsSource,
          style: styleFunction
        });

        map.addLayer(stopsLayer);
        map.getView().fit(stopsSource.getExtent(), map.getSize());

        $('#line').on('change', function() {
          var id = $(this).val();

          var tripid = $(this).find('option:selected').data('tripid');
          var shapeid = $(this).find('option:selected').data('shapeid');

          if (typeof(lineLayer) != 'undefined') map.removeLayer(lineLayer);
          $.getJSON('trip.php', { trip: tripid, shape: shapeid }, function(json) {
            lineSource = new ol.source.Vector({
              features: (new ol.format.GeoJSON()).readFeatures(json, { featureProjection: 'EPSG:3857' })
            });

            lineLayer = new ol.layer.Vector({
              source: lineSource,
              style: function() {
                var color = '#'+$('#line option:selected').data('color');
                return [
                  new ol.style.Style({
                    image: new ol.style.Circle({
                      radius: 6,
                      fill: new ol.style.Fill({ color: color }),
                      stroke: null
                    }),
                    stroke: new ol.style.Stroke({color: color, width: 3})
                  })
                ];
              }
            });

            map.addLayer(lineLayer);
          });

          window.clearInterval(intervalID);

          intervalID = window.setInterval(vehiclePosition, 60 * 1000);
          vehiclePosition();
        }).trigger('change');
      });

      var styleFunction = function(feature) {
        var color = '#'+$('#line option:selected').data('color');

        var properties = feature.getProperties();
        var active = (typeof(properties.active) != 'undefined' ? properties.active : false);
        var activeLine = (typeof(properties.activeLine) != 'undefined' ? properties.activeLine : false);

        if (active === true) {
          var style = [
            new ol.style.Style({
              image: new ol.style.Circle({
                radius: 8,
                fill: new ol.style.Fill({ color: color }),
                stroke: new ol.style.Stroke({color: 'black', width: 3})
              }),
              zIndex: Infinity
            })
          ];
        }
        else if (activeLine === true) {
          var style = [
            new ol.style.Style({
              image: new ol.style.Circle({
                radius: 5,
                fill: new ol.style.Stroke({color: color, width: 1}),
                stroke: null
              }),
              zIndex: Infinity
            })
          ];
        }
        else {
          var style = [
            new ol.style.Style({
              image: new ol.style.Circle({
                radius: 1,
                fill: null,
                stroke: new ol.style.Stroke({color: [80,80,80,0.5], width: 1})
              })
            })
          ];
        }

        return style;
      }

      var vehiclePosition = function() {
        var currentLine = $('#line').val();

        $.ajax({
          url: me.openDataBaseUrl + '/OperationMonitoring/1.0/VehiclePositionByLine/' + currentLine,
          type: 'GET',
          error: function (jqXHR, textStatus) {
            var json = $.parseJSON(jqXHR.responseText);
            if (typeof(json.message) !== 'undefined') {
              alert(json.message);
            } else {
              alert(textStatus);
            }
            window.clearInterval(intervalID);
          },
          beforeSend: function setHeader(xhr) {
            xhr.setRequestHeader('Accept', 'application/json');
            xhr.setRequestHeader('Authorization', 'Bearer ' + me.apiToken); // add the api token here
          },
          success: function (json) {
            if (typeof(json.message) !== 'undefined') {
              alert(json.message);
            } else {
              $('#time').text(new Date());

              data = json.lines[0];

              for (var i = 0; i < stopsActive.length; i++) {
                stopsSource.getFeatureById(stopsActive[i]).set('active', false);
              }

              stopsActive = [];
              for (var i = 0; i < data.vehiclePositions.length; i++) {
                var id = data.vehiclePositions[i].pointId,
                     p = stopsSource.getFeatureById(id);

                if (p !== null) {
                  stopsActive.push(id);
                  p.set('active', true);
                }
              }
            }
          }
        });
      }
    </script>
  </body>
</html>
<?php
$dblink->close();