#! /usr/bin/env php
<?php
/**
 * Portions of this code are copyrighted by the contributors to Drupal.
 * Additional code copyright 2011-2012 by Peter Wolanin.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, version 2 or any later version.
 */

require_once dirname(__FILE__) . '/db-helper.php';
ini_set('auto_detect_line_endings', 1);

$scriptname = array_shift($argv);

if (count($argv) < 1) {
  exit("usage: {$scriptname} TABLENAME data.csv\n\n");
}

$table = array_shift($argv);

$filename = array_shift($argv);
if (!file_exists($filename) || !is_readable($filename)) {
  exit("File {$filename} does not exist or cannot be read\n");
}

$handle = @fopen($filename, "r");
if (!$handle) {
  echo "Faile to open file\n";
  exit;
}

$delimiter = NULL;
$rows = 0;
$start = time();

// Get the header row.
if (($line = fgets($handle)) !== FALSE) {

  if (!isset($delimiter)) {
    $delimiter = ','; // CSV default
    if (count(explode('|', $line)) >= 2) {
      $delimiter = '|'; // Pipe delimited
    }
  }
  $header_fields = str_getcsv($line, $delimiter);
  foreach($header_fields as $idx => $field) {
    // Normalize to lower case.
    $header_fields[$idx] = strtolower($field);
  }
  // Allow us to look up desired fields based on name in header.
  $idx = array_flip($header_fields);
  $expected_num_fields = count($header_fields);
}
echo "Header fields:\n";
print_r($header_fields);


if (!db_table_exists($table)) {
  // TODO: create the table.
  $table_fields = $header_fields;
  echo "TODO - right now you need to pre-create the table\n";
  exit(1);
}
else {
  $table_fields = array();
  $etable = db_escape_table($table);
  $r = db_query("DESCRIBE {{$etable}}");
  foreach ($r as $row) {
    $name = $row->Field;
    // If the column name was one of the header fields, we'll use it.
    if (isset($idx[$name])) {
      $table_fields[] = $name;
    }
  }
}
echo "Table fields to use:\n";
print_r($table_fields);

while (($fields = fgetcsv($handle, $delimiter)) !== FALSE) {
  if (count($fields) < 3) {
    echo "Invalid line: {$line}\n";
    continue;
  }

  $info = array();
  foreach($table_fields as $key) {
    $info[] = $fields[$idx[$key]];
  }

  try {
    db_insert($table)
      ->fields($table_fields)
      ->values($info)
      ->execute();
  }
  catch (PDOException $e) {
    // Dump the bad data for inspection.
    print_r($info);
    throw $e;
  }
}

if (!feof($handle)) {
  echo "Error: unexpected fgets() fail\n";
}
fclose($handle);

exit;



