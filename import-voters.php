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

require_once dirname(__FILE__) . '/db-helper.php';

// The CSV files, at least, have an extra trailing delimiter. So the
// expected number of fileds is on more than the real number we use.
define('EXPECTED_NUM_FIELDS', 26);


if (count($argv) < 2) {
  exit("usage: {$argv[0]} voterfile\n\n");
}

$filename = $argv[1];
if (!file_exists($filename) || !is_readable($filename)) {
  exit("File {$filename} does not exist or cannot be read\n");
}


$schema['voters'] = array(
  'fields' => array(
    'county' => array(
      'type' => 'varchar',
      'length' => 30,
      'not null' => TRUE,
      'default' => '',
    ),
    'voter_id' => array(
      'type' => 'varchar',
      'length' => 9,
      'not null' => TRUE,
    ),
    'legacy_id' => array(
      'type' => 'varchar',
      'length' => 9,
      'not null' => TRUE,
      'default' => '',
    ),
    'last_name' => array(
      'type' => 'varchar',
      'length' => 40,
      'not null' => TRUE,
      'default' => '',
    ),
    'first_name' => array(
      'type' => 'varchar',
      'length' => 30,
      'not null' => TRUE,
      'default' => '',
    ),
    'middle_name' => array(
      'type' => 'varchar',
      'length' => 30,
      'not null' => TRUE,
      'default' => '',
    ),
    'suffix' => array(
      'type' => 'varchar',
      'length' => 5,
      'not null' => TRUE,
      'default' => '',
    ),
    'street_number' => array(
      'type' => 'int',
      'length' => 20,
    ),
    'suffix_a' => array(
      'type' => 'varchar',
      'length' => 8,
      'not null' => TRUE,
      'default' => '',
    ),
    'suffix_b' => array(
      'type' => 'varchar',
      'length' => 4,
      'not null' => TRUE,
      'default' => '',
    ),
    'street_name' => array(
      'type' => 'varchar',
      'length' => 50,
      'not null' => TRUE,
      'default' => '',
    ),
    'apt_unit_no' => array(
      'type' => 'varchar',
      'length' => 8,
      'not null' => TRUE,
      'default' => '',
    ),
    'city' => array(
      'type' => 'varchar',
      'length' => 30,
      'not null' => TRUE,
      'default' => '',
    ),
    'municipality' => array(
      'type' => 'varchar',
      'length' => 30,
      'not null' => TRUE,
      'default' => '',
    ),
    'zip5' => array(
      'type' => 'varchar',
      'length' => 5,
      'not null' => TRUE,
      'default' => '',
    ),
    'date_of_birth' => array(
      'type' => 'varchar',
      'length' => 10,
      'not null' => TRUE,
    ),
    'party_code' => array(
      'type' => 'varchar',
      'length' => 5,
      'not null' => TRUE,
      'default' => '',
    ),
    'ward' => array(
      'type' => 'varchar',
      'length' => 2,
      'not null' => TRUE,
      'default' => '',
    ),
    'district' => array(
      'type' => 'varchar',
      'length' => 2,
      'not null' => TRUE,
      'default' => '',
    ),
    'status' => array(
      'type' => 'varchar',
      'length' => 30,
      'not null' => TRUE,
      'default' => '',
    ),
    'congressional' => array(
      'type' => 'varchar',
      'length' => 4,
      'not null' => TRUE,
      'default' => '',
    ),
    'legislative' => array(
      'type' => 'varchar',
      'length' => 4,
      'not null' => TRUE,
      'default' => '',
    ),
    'freeholder' => array(
      'type' => 'varchar',
      'length' => 4,
      'not null' => TRUE,
      'default' => '',
    ),
    'school' => array(
      'type' => 'varchar',
      'length' => 8,
      'not null' => TRUE,
      'default' => '',
    ),
    'regional_school' => array(
      'type' => 'varchar',
      'length' => 8,
      'not null' => TRUE,
      'default' => '',
    ),
    'fire' => array(
      'type' => 'varchar',
      'length' => 8,
      'not null' => TRUE,
      'default' => '',
    ),
  ),
  'primary key' => array('voter_id'),
  'indexes' => array(
    'party_code' => array('party_code'), 
    'last_name' => array('last_name'),
    'municipality' => array('municipality'),
   ),
);

$voter_fields = array_keys($schema['voters']['fields']);


if (!db_table_exists('voters')) {
  db_create_table('voters', $schema['voters']);
}

$schema['voter_doors'] = array(
  'fields' => array(
    'voter_id' => array(
      'type' => 'varchar',
      'length' => 9,
      'not null' => TRUE,
    ),
    'door' => array(
      'type' => 'varchar',
      'length' => 255,
      'not null' => TRUE,
      'default' => '',
    ),
  ),
  'primary key' => array('voter_id'),
  'indexes' => array(
    'door' => array('door'),
  ),
);

if (!db_table_exists('voter_doors')) {
  db_create_table('voter_doors', $schema['voter_doors']);
}

$handle = @fopen($filename, "r");
if (!$handle) {
  echo "Faile to open file\n";
  exit;
}

db_truncate("voters")->execute();

$delimiter = NULL;

while (($line = fgets($handle)) !== FALSE) {

  if (!isset($delimiter)) {
    $delimiter = ','; // CSV default
    if (count(explode('|', $line)) >= EXPECTED_NUM_FIELDS) {
      $delimiter = '|'; // Pipe delimited
    }
  }
  $voter = explode($delimiter, $line);
  if (count($voter) != EXPECTED_NUM_FIELDS) {
    echo "Invalid line: {$line}\n";
    continue;
  }
  foreach ($voter as $idx => $field) {
    $voter[$idx] = trim($field);
  }
  // Truncate zip5 to actually 5 digits (some looked like '085423347').
  $voter[14] = substr($voter[14], 0, 5);
  $voter[15] = voter_reformat_date($voter[15]);
  try {
    db_insert('voters')
      ->fields($voter_fields)
      ->values($voter)
      ->execute();
  }
  catch (PDOException $e) {
    // Dump the bad data for inspection.
    print_r($voter);
    throw $e;
  }
}
if (!feof($handle)) {
  echo "Error: unexpected fgets() fail\n";
}
fclose($handle);

db_truncate("voter_doors")->execute();
db_query("INSERT INTO voter_doors (voter_id, door) SELECT voter_id, CONCAT(street_name, '@', street_number, '@', suffix_a, '@', suffix_b, '@', apt_unit_no, '@', zip5) FROM voters");

exit;

function voter_reformat_date($str) {
  $parts = explode('/', $str);
  return "{$parts[2]}-{$parts[0]}-{$parts[1]}";
}


