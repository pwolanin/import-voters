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
  exit("usage: {$scriptname} datafile.csv [UPDATE]");
}

$filename = array_shift($argv);
if (!file_exists($filename) || !is_readable($filename)) {
  exit("File {$filename} does not exist or cannot be read\n");
}
$update = (bool) array_shift($argv);

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
    'party_code' => array(
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

// Expect a camel case csv header like MailingState.
//$mapping = array_flip(array_keys($schema['vbm_info']['fields']));
//foreach ($mapping as $key => &$value) {
//  $parts = explode('_', $key);
//  $value = implode('', array_map('ucfirst', $parts));
//}

// Special case
//$mapping['voter_id'] = 'VoterID';

$handle = @fopen($filename, "r");
if (!$handle) {
  echo "Faile to open file\n";
  exit;
}

if (!$update) {
  if (db_table_exists('vbm_info')) {
    db_drop_table('vbm_info');
  }
  db_create_table('vbm_info', $schema['vbm_info']);
}

$vbm_fields = array_keys($schema['vbm_info']['fields']);
// Trivial mapping for manually fixed header row.
$mapping = array();
foreach ($vbm_fields as $key) {
  $mapping[$key] = $key;
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
  // Allow us to look up desired fields based on name in header.
  $idx = array_flip($header_fields);
  $expected_num_fields = count($header_fields);
}
echo "Header fields:\n";
print_r($header_fields);

$idx1 = array(
  'voter_id' => 0,
  'party_code' => 4,
  'application_received' => 5,
  'ballot_received' => 7,
  'ballot_status' => 11,
);
$idx2 = array(
  'voter_id' => 0,
  'voter_id' => 0,
  'party_code' => 4,
  'application_received' => 5,
  'ballot_received' => 6,
  'ballot_status' => 10,
);


while (($fields = fgetcsv($handle, 1000, $delimiter)) !== FALSE) {
  if (count($fields) < 3) {
    echo "Invalid line: {$line}\n";
    continue;
  }

  if (isset($fields[14]) && $fields[14] == '11/08/2016 GENERAL ELECTION') {
    $idx = $idx1;
  }
  else {
    $idx = $idx2;
  }
  $info = array();
  foreach($mapping as $sql => $key) {
    if (isset($idx[$key])) {
      $info[$sql] = $fields[$idx[$key]];
    }
    else {
      $info[$sql] = '';
    }
  }
  $info['application_received'] = voter_reformat_date($info['application_received']);
  //$info['ballot_mail_date'] = voter_reformat_date($info['ballot_mail_date']);
  $info['ballot_received'] = voter_reformat_date($info['ballot_received']);

  try {
    if ($update) {
      $query = db_merge('vbm_info')
      ->key(array('voter_id' => $info['voter_id']));
    }
    else {
      $query = db_insert('vbm_info');
    }

    $query->fields($info)
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
  $parts = array();
  if (preg_match('@([0-9]{1,2})/([0-9]{1,2})/([0-9]{4})@', $str, $parts)) {
    return sprintf("%d-%02d-%02d", $parts[3], $parts[1], $parts[2]);
  }
  return '';
}

