<?php

$data = file_get_contents('data.csv');

$rows = explode("\n", $data);

print_r($rows);