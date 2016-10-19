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
    'preferred_phone' => array(
      'type' => 'varchar',
      'length' => 14,
      'not null' => TRUE,
      'default' => '',
    ),
    'preferred_email' => array(
      'type' => 'varchar',
      'length' => 255,
      'not null' => TRUE,
      'default' => '',
    ),
    'request_received' => array(
      'type' => 'varchar',
      'length' => 10,
      'not null' => TRUE,
      'default' => '',
    ),
    'ballot_mailed' => array(
      'type' => 'varchar',
      'length' => 10,
      'not null' => TRUE,
      'default' => '',
    ),
    'ballot_received' => array(
      'type' => 'varchar',
      'length' => 10,
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
    elseif (count(explode("\t", $line)) >= 2) {
      $delimiter = "\t"; // tab delimited
    }
  }
  $header_fields = str_getcsv($line, $delimiter);
  foreach ($header_fields as $idx => $value) {
    $header_fields[$idx] = trim($value);
  }
  // Allow us to look up desired fields based on name in header.
  $idx = array_flip($header_fields);
  $expected_num_fields = count($header_fields);
}
echo "Header fields:\n";
print_r($header_fields);

while ($fields = fgetcsv($handle, 1000, $delimiter)) {
  if (count($fields) < 3) {
    echo "Invalid line: {$line}\n";
    continue;
  }
                                                                                                                           
  $info = array();
  foreach(array('voter_id' => 'AffNo', 'preferred_phone' => 'Preferred Phone', 'preferred_email' => 'PreferredEmail', 'request_received' => 'RequestReceived', 'ballot_mailed' => 'BallotMailed', 'ballot_received' => 'BallotReceived') as $sql => $key) {
    $info[$sql] = $fields[$idx[$key]];
  }

  $info['ballot_mailed'] = voter_reformat_date($info['ballot_mailed']);
  $info['request_received'] = voter_reformat_date($info['request_received']);
  $info['ballot_received'] = voter_reformat_date($info['ballot_received']);

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


function voter_reformat_date($str) {
  $parts = explode('/', $str);
  if (count($parts) == 3) {
    return "{$parts[2]}-{$parts[0]}-{$parts[1]}";
  }
  return '';
}

