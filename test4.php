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
  $q = $dblink->query("SELECT `route_id`, `route_type`, `route_long_name`, `route_short_name`/*, `trip_headsign`*/, `route_color`/*, `trip_id`, `shape_id`*/ FROM `trips` t LEFT JOIN `routes` r USING(`route_id`) WHERE `service_id` IN('".implode("','", $services)."') GROUP BY `route_id`/*, `direction_id`*/ ORDER BY `route_type`, `route_id`, `direction_id`") or trigger_error($dblink->error);
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
    echo '<option value="'.$r['route_short_name'].'" data-color="'.$r['route_color'].'" data-routeid="'.htmlentities($r['route_id']).'">'.$r['route_short_name'].' :: '.$r['route_long_name']./*' :: '.$r['trip_headsign'].*/'</option>';
    $prev_type = $r['route_type'];
  }
  $q->free();
  echo '</optgroup>';
?>
        </select>
        <button id="btn-locate" style="width: 100%; margin-top: 5px;">Localisez moi !</button>
        <div id="time" style="width: 100%; color: #808080; text-align: right; font-size: small; margin-top: 5px;"></div>
        <hr>
        <div id="stopinfo">
          Cliquez sur un arrêt pour connaître le temps d'attente à cet arrêt !
        </div>
      </div>
    </div>
    <script src="https://code.jquery.com/jquery-3.1.0.min.js"></script>
    <script src="ol.js"></script>
    <script>
      var map;
      var geolocation, accuracyFeature, positionFeature, center = null;
      var stopsSource, stopsLayer, stopsActive = new Array();
      var lineSource, lineLayer;
      var activeSource, activeLayer;
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
          style: function() {
            return [
              new ol.style.Style({
                image: new ol.style.Circle({
                  radius: 3,
                  fill: new ol.style.Stroke({color: [80,80,80,0.1], width: 1}),
                  stroke: null
                })
              })
            ];
          }
        });
        stopsLayer.setZIndex(1);

        lineSource = new ol.source.Vector({
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
        lineLayer.setZIndex(5);

        activeSource = new ol.source.Vector({
        });
        activeLayer = new ol.layer.Vector({
          source: activeSource,
          style: function() {
            var color = '#'+$('#line option:selected').data('color');

            return [
              new ol.style.Style({
                image: new ol.style.Circle({
                  radius: 8,
                  fill: new ol.style.Fill({ color: color }),
                  stroke: new ol.style.Stroke({color: 'black', width: 3})
                })
              })
            ];
          }
        });
        activeLayer.setZIndex(10);

        map.addLayer(stopsLayer);
        map.addLayer(lineLayer);
        map.addLayer(activeLayer);
        map.getView().fit(stopsSource.getExtent(), map.getSize());

        map.on('click', function(event) {
          var stops = new Array();
          map.forEachFeatureAtPixel(map.getEventPixel(event.originalEvent), function(feature) {
            stops.push(feature.getId());
          }, null, function(layer) {
            return layer === stopsLayer;
          });

          $('#stopinfo').empty();

          if (stops.length > 0) {
            $.ajax({
              //url: me.openDataBaseUrl + '/OperationMonitoring/1.0/PassingTimeByPoint/' + stops.join("%2C"),
              url: 'stop4.php',
              data: { stops: stops },
              type: 'GET',
              error: function (jqXHR, textStatus) {
                // process error
              },
              //beforeSend: function setHeader(xhr) {
              //  xhr.setRequestHeader('Accept', 'application/json');
              //  xhr.setRequestHeader('Authorization', 'Bearer ' + me.apiToken);
              //},
              success: function (json) {
                // process the result here
                console.log(json);

                if (json.points != null) {
                  var s = json.points[0].pointId;
                  if (s !== null) {
                    var stop = stopsSource.getFeatureById(s);
                    $('#stopinfo').append('<strong>'+stop.getProperties().name+'</strong>');
                    var ul = document.createElement('ul'); $(ul).appendTo('#stopinfo');
                    for (var i = 0; i < json.points[0].passingTimes.length; i++) {
                      var li = document.createElement('li'); $(li).appendTo(ul);
                      $(li).append('<strong>Ligne '+json.points[0].passingTimes[i].lineId+' :</strong> ');
                      var t1 = new Date(json.points[0].passingTimes[i].expectedArrivalTime);
                      var t2 = new Date();
                      $(li).append(Math.round((t1 - t2) / 1000 / 60) + ' min.');
                    }
                  }
                }
              }
            });
          }
        });

        $('#line').on('change', function() {
          var id = $(this).val();

          var routeid = $(this).find('option:selected').data('routeid');

          lineSource.clear();
          $.getJSON('trip4.php', { route: routeid }, function(json) {
            lineSource.addFeatures((new ol.format.GeoJSON()).readFeatures(json, { featureProjection: 'EPSG:3857' }));
            map.getView().fit(lineSource.getExtent(), map.getSize());
          });

          activeSource.clear();
          window.clearInterval(intervalID);

          intervalID = window.setInterval(vehiclePosition, 60 * 1000);
          vehiclePosition();
        }).trigger('change');

        geolocation = new ol.Geolocation({
          projection: map.getView().getProjection()
        });

        accuracyFeature = new ol.Feature();
        geolocation.on('change:accuracyGeometry', function() {
          accuracyFeature.setGeometry(geolocation.getAccuracyGeometry());
        });

        positionFeature = new ol.Feature();
        positionFeature.setStyle(new ol.style.Style({
          image: new ol.style.Circle({
            radius: 6,
            fill: new ol.style.Fill({
              color: '#3399CC'
            }),
            stroke: new ol.style.Stroke({
              color: '#fff',
              width: 2
            })
          })
        }));

        geolocation.on('change:position', function() {
          var coordinates = geolocation.getPosition();
          positionFeature.setGeometry(coordinates ? new ol.geom.Point(coordinates) : null);
          if (center === null) {
            center = coordinates;
            map.getView().setZoom(16);
            map.getView().setCenter(coordinates);
          }
        });

        new ol.layer.Vector({
          map: map,
          source: new ol.source.Vector({
            features: [accuracyFeature, positionFeature]
          })
        });

        $('#btn-locate').on('click', function() {
          geolocation.setTracking(true);
        });
     });

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

              activeSource.clear();
              for (var i = 0; i < json.lines[0].vehiclePositions.length; i++) {
                var stop = stopsSource.getFeatureById(json.lines[0].vehiclePositions[i].pointId);
                if (stop !== null) { activeSource.addFeature(stop); } /*else { console.log(json.lines[0].vehiclePositions[i].pointId); }*/
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