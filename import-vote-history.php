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

// The CSV files, at least, have an extra trailing delimiter. So the
// expected number of fields is one more than the real number we use.
define('EXPECTED_NUM_FIELDS', 44);

$scriptname = array_shift($argv);

if (count($argv) < 1) {
  exit("usage: {$scriptname} voterhistoryfile [municipal filter]\n\n");
}

$filename = array_shift($argv);
if (!file_exists($filename) || !is_readable($filename)) {
  exit("File {$filename} does not exist or cannot be read\n");
}

// Do we want to filter based on a municipality?
$municipal_filter = array_shift($argv);

$schema['vote_history'] = array(
  'fields' => array(
    'voter_id' => array(
      'type' => 'int',
      'not null' => TRUE,
    ),
    'status_code' => array(
      'type' => 'varchar',
      'length' => 10,
      'not null' => TRUE,
      'default' => '',
    ),
    'party_code' => array(
      'type' => 'varchar',
      'length' => 5,
      'not null' => TRUE,
      'default' => '',
    ),
    'date_registered' => array(
      'type' => 'varchar',
      'length' => 10,
      'not null' => TRUE,
    ),
    'county_precinct' => array(
      'type' => 'varchar',
      'length' => 10,
      'not null' => TRUE,
      'default' => '',
    ),
    'municipality' => array(
      'type' => 'varchar',
      'length' => 30,
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
      'length' => 10,
      'not null' => TRUE,
      'default' => '',
    ),
    'phone_number' => array(
      'type' => 'varchar',
      'length' => 20,
      'not null' => TRUE,
      'default' => '',
    ),
    'election_date' => array(
      'type' => 'varchar',
      'length' => 10,
      'not null' => TRUE,
      'default' => '',
    ),
    'election_name' => array(
      'type' => 'varchar',
      'length' => 40,
      'not null' => TRUE,
      'default' => '',
    ),
    'election_type' => array(
      'type' => 'varchar',
      'length' => 4,
      'not null' => TRUE,
      'default' => '',
    ),
    'election_category' => array(
      'type' => 'varchar',
      'length' => 4,
      'not null' => TRUE,
      'default' => '',
    ),
    'ballot_type' => array(
      'type' => 'varchar',
      'length' => 4,
      'not null' => TRUE,
      'default' => '',
    ),
  ),
  'primary key' => array('election_date', 'voter_id'),
  'indexes' => array(
    'voter_id' => array('voter_id'),
  ),
);

$handle = @fopen($filename, "r");
if (!$handle) {
  echo "Faile to open file\n";
  exit;
}

if (db_table_exists('vote_history')) {
  db_drop_table('vote_history');
}

$table = $schema['vote_history'];
// Do inserts without indexes for speed.
unset($table['indexes']);
db_create_table('vote_history', $table);

$vote_fields = array_keys($schema['vote_history']['fields']);

// State NJSVRS files are always pipe delimited.
$delimiter = '|';
$rows = 0;
$start = time();

while (($fields = fgetcsv($handle, 1000, $delimiter)) !== FALSE) {

  if (count($fields) != EXPECTED_NUM_FIELDS) {
    echo "Invalid line: " . implode($delimiter, $fields)  . "\n";
    continue;
  }
  if ($municipal_filter && $fields[34] != $municipal_filter) {
    continue;
  }

  $vote_history = array_merge(array_slice($fields, 0, 3), array_slice($fields, 32, 11));
  $vote_history[3] = voter_reformat_date($vote_history[3]);
  $vote_history[9] = voter_reformat_date($vote_history[9]);
  try {
    // In rare cases we can get 2 entries for the same ID + election, so
    // do a merge insert.
    $data = array_combine($vote_fields, $vote_history);
    db_merge('vote_history')
      ->key(array(
          'voter_id' => $vote_history[0],
          'election_date' => $vote_history[9],
        )
      )
      ->fields($data)
      ->execute();
  }
  catch (PDOException $e) {
    // Dump the bad data for inspection.
    print_r($data);
    throw $e;
  }
}

// Add indexes.
foreach ($schema as $table => $info) {
  foreach($info['indexes'] as $name => $fields) {
    db_add_index($table, $name, $fields);
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

