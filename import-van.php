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
  exit("usage: {$scriptname} vanfile.csv");
}

$filename = array_shift($argv);
if (!file_exists($filename) || !is_readable($filename)) {
  exit("File {$filename} does not exist or cannot be read\n");
}

// Do we want to filter based on an existin voters table?
$voter_filter = array_shift($argv);

$schema['van_info'] = array(
  'fields' => array(
    'voter_id' => array(
      'type' => 'int',
      'not null' => TRUE,
    ),
    'home_phone' => array(
      'type' => 'varchar',
      'length' => 14,
      'not null' => TRUE,
      'default' => '',
    ),
    'van_id' => array(
      'type' => 'varchar',
      'length' => 25,
      'not null' => TRUE,
      'default' => '',
    ),
  ),
  'primary key' => array('voter_id'),
  'indexes' => array(),
);

$handle = @fopen($filename, "r");
if (!$handle) {
  echo "Faile to open file\n";
  exit;
}

if (db_table_exists('van_info')) {
  db_drop_table('van_info');
}
db_create_table('van_info', $schema['van_info']);

$van_fields = array_keys($schema['van_info']['fields']);


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
  $header_fields = explode($delimiter, $line);
  // Allow us to look up desired fields based on name in header.
  $idx = array_flip($header_fields);
  $expected_num_fields = count($header_fields);
}
echo "Header fields:\n";
print_r($header_fields);

while (($line = fgets($handle)) !== FALSE) {
  $fields = explode($delimiter, $line);
  if (count($fields) < 3) {
    echo "Invalid line: {$line}\n";
    continue;
  }

  $info = array();
  foreach(array('VoterID', 'HomePhone', 'VANID') as $key) {
    $info[$key] = $fields[$idx[$key]];
  }
  // For some reason, van has a prefix 'I0210' on the NJ voter ID.
  $info['VoterID'] = substr($info['VoterID'], 5);

  try {
    db_insert('van_info')
      ->fields($van_fields)
      ->values(array_values($info))
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


