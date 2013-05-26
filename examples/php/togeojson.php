<?php

require_once('lib/ged2json.php');
require_once('lib/ged2geojson.php');

$ged2json = new ged2geojson('../moore.ged');

header("Content-Type: application/json; charset=utf-8");
print $ged2json;
