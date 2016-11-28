<?php
if (isset($_REQUEST['route'])) {
  $dblink = new MySQLi('localhost', 'root', 'thestedreqaphespudac3mezacedraZa', 'stib');

  $json = array();
  $json['type'] = 'FeatureCollection';
  $json['features'] = array();

  $q = $dblink->query("SELECT `trip_id`, `shape_id` FROM `trips` WHERE `route_id` = '".$dblink->real_escape_string($_REQUEST['route'])."' LIMIT 5") or trigger_error($dblink->error);
  while ($r = $q->fetch_assoc()) {
    $stops = array();

    $_q = $dblink->query("SELECT `stop_lon`, `stop_lat` FROM `stop_times` st LEFT JOIN `stops` s USING(`stop_id`) WHERE `trip_id` = '".$dblink->real_escape_string($r['trip_id'])."' ORDER BY `stop_sequence` ASC");
    while ($_r = $_q->fetch_assoc()) { $stops[] = array( floatval($_r['stop_lon']), floatval($_r['stop_lat']) ); } $_q->free();

    $feature = array();
    $feature['type'] = 'Feature';
    $feature['properties'] = array();
    $feature['geometry'] = array(
      'type' => 'MultiPoint',
      'coordinates' => $stops
    );
    $json['features'][] = $feature;

    $line = array();

    $_q = $dblink->query("SELECT * FROM `shapes` s WHERE `shape_id` = '".$dblink->real_escape_string($r['shape_id'])."' ORDER BY `shape_pt_sequence` ASC");
    while ($_r = $_q->fetch_assoc()) { $line[] = array( floatval($_r['shape_pt_lon']), floatval($_r['shape_pt_lat']) ); } $_q->free();

    $feature = array();
    $feature['type'] = 'Feature';
    $feature['properties'] = array();
    $feature['geometry'] = array(
      'type' => 'LineString',
      'coordinates' => $line
    );
    $json['features'][] = $feature;
  }
  $q->free();

  $dblink->close();

  echo json_encode($json);
}
exit();