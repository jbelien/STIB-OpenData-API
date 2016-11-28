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
    <div id="map" class="map">
      <div class="options">
        <select id="line" style="width:100%;">
<?php
  $json = json_decode(file_get_contents('routes.json'));
  $routes = $json->features;
  foreach ($routes as $r) {
    echo '<option value="'.$r->id.'" data-color="'.$r->properties->color.'">'.$r->id.' :: '.$r->properties->name.'</option>';
  }
?>
        </select>
      </div>
    </div>
    <script src="https://code.jquery.com/jquery-3.1.0.min.js"></script>
    <script src="ol.js"></script>
    <script>
      var map;
      var stopsSource, stopsLayer, stopsActive = new Array();
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

        intervalID = window.setInterval(vehiclePosition, 60 * 1000);
        vehiclePosition();

        $('#line').on('change', function() {
          var id = $(this).val();

          window.clearInterval(intervalID);

          interval = window.setInterval(vehiclePosition, 60 * 1000);
          vehiclePosition();
        });
      });

      var styleFunction = function(feature) {
        var color = $('#line > option:selected').data('color');

        var properties = feature.getProperties();
        var active = (typeof(properties.active) != 'undefined' ? properties.active : false);
        var activeLine = (typeof(properties.activeLine) != 'undefined' ? properties.activeLine : false);

        if (active === true) {
          var style = [
            new ol.style.Style({
              image: new ol.style.Circle({
                radius: 5,
                fill: new ol.style.Fill({ color: color }),
                stroke: new ol.style.Stroke({color: 'white', width: 2})
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