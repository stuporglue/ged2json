<?php

require_once('lib/ged2json.php');

$ged2json = new ged2json('../moore.ged');

header("Content-Type: application/json; charset=utf-8");
print $ged2json;
