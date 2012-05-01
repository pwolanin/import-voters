#! /usr/bin/env php
<?php
/**
 * Portions of this code are copyrighted by the contributors to Drupal.
 * Additional code copyright 2011 by Peter Wolanin.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, version 2 or any later version.
 */

date_default_timezone_set('UTC');
ini_set('memory_limit', '256M');

// The CSV files, at least, have an extra trailing delimiter. So the
// expected number of fileds is one more than the real number we use.
define('EXPECTED_NUM_FIELDS', 26);

if (count($argv) < 2) {
  exit("usage: {$argv[0]} filename\n");
}

$filename = $argv[1];
if (!file_exists($filename) || !is_readable($filename)) {
  exit("File {$filename} does not exist or cannot be read\n");
}



$lines = file($filename, FILE_IGNORE_NEW_LINES);
if (!$lines) {
  exit("No data in file {$filename}\n");
}

// This makes sure all rows for the same voter are together.
sort($lines);

$delimiter = ','; // CSV default
if (count(explode('|', $lines[0])) >= EXPECTED_NUM_FIELDS) {
  $delimiter = '|'; // Pipe delimited
}

$id = '';
$voter_rows = array();

foreach ($lines as $i => $l) {
  $fields = explode($delimiter, $l);
  $lines[$i] = NULL;
  if (count($fields) != EXPECTED_NUM_FIELDS) {
    echo "Invalid line: {$l}\n";
  }
}

