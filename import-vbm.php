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

$schema['vbm_info'] = array(
  'fields' => array(
    'voter_id' => array(
      'type' => 'int',
      'not null' => TRUE,
    ),
    'application_received' => array(
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
    'ballot_status' => array(
      'type' => 'varchar',
      'length' => 20,
      'not null' => TRUE,
      'default' => '',
    ),
  ),
  'primary key' => array('voter_id'),
  'indexes' => array(
    // Indexing the 1st 3 letters is more than enough.
    'ballot_status' => array(array('ballot_status', 3)),
  ),
);

$handle = @fopen($filename, "r");
if (!$handle) {
  echo "Faile to open file\n";
  exit;
}

if (db_table_exists('vbm_info')) {
  db_drop_table('vbm_info');
}
db_create_table('vbm_info', $schema['vbm_info']);

$van_fields = array_keys($schema['vbm_info']['fields']);


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
  foreach(array('voter_id' => 'VoterID', 'ballot_mailed' => 'BallotMailed', 'application_received' => 'ApplicationReceived', 'ballot_received' => 'BallotReceived', 'ballot_status' => 'BallotStatus',) as $sql => $key) {
    if (isset($idx[$key])) {
      $info[$sql] = $fields[$idx[$key]];
    }
  }
  $info['application_received'] = voter_reformat_date($info['application_received']);
  $info['ballot_mailed'] = voter_reformat_date($info['ballot_mailed']);
  $info['ballot_received'] = voter_reformat_date($info['ballot_received']);

  try {
    db_insert('vbm_info')
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

