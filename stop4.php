<?php
if (isset($_REQUEST['stops'])) {
  $ch = curl_init();

  //curl_setopt($ch, CURLOPT_URL, 'https://opendata-api.stib-mivb.be/OperationMonitoring/1.0/PassingTimeByPoint/'.implode('%2C', $_REQUEST['stops']));
  curl_setopt($ch, CURLOPT_URL, 'https://opendata-api.stib-mivb.be/OperationMonitoring/1.0/PassingTimeByPoint/'.$_REQUEST['stops'][0]);
  curl_setopt($ch, CURLOPT_HEADER, 0);
  curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    'Authorization: Bearer a9243c8f98dcd5e11e1aee6b8dd4fdf8',
    'Accept: application/json'
  ));
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);

  $r = curl_exec($ch);

  header('Content-Type: application/json');
  echo $r;

  curl_close($ch);
}

exit();