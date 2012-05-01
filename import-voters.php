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
      'length' => 4,
      'not null' => TRUE,
      'default' => '',
    ),
    'regional_school' => array(
      'type' => 'varchar',
      'length' => 4,
      'not null' => TRUE,
      'default' => '',
    ),
    'fire' => array(
      'type' => 'varchar',
      'length' => 4,
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
  $voter[15] = voter_reformat_date($voter[15]);
  db_insert('voters')
    ->fields($voter_fields)
    ->values($voter)
    ->execute();
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




$sql1 = <<<OESQL1

CREATE TABLE IF NOT EXISTS `voters` (
  voter_id VARCHAR(9) NOT NULL,
  status_code VARCHAR(1),
  party_code VARCHAR(5),
  ballot_type VARCHAR(2),
  last_name VARCHAR(40),
  first_name VARCHAR(30),
  middle_name VARCHAR(30),
  prefix VARCHAR(5),
  suffix VARCHAR(5),
  sex VARCHAR(1),
  street_number INT,
  suffix_a VARCHAR(8),
  suffix_b VARCHAR(3),
  street_name VARCHAR(50),
  apt_unit_no VARCHAR(8),
  street_name_2 VARCHAR(50),
  street_name_3 VARCHAR(50),
  city VARCHAR(30),
  state VARCHAR(2),
  zip5 VARCHAR(5),
  zip4 VARCHAR(4),
  mailing_street_number INT,
  mailing_suffix_a VARCHAR(8),
  mailing_suffix_b VARCHAR(3),
  mailing_street_name1 VARCHAR(50),
  mailing_apt_unit_no VARCHAR(8),
  mailing_street_name2 VARCHAR(50),
  mailing_street_name3 VARCHAR(50),
  mailing_city VARCHAR(30),
  mailing_state VARCHAR(2),
  mailing_country VARCHAR(30),
  mailing_zip_code VARCHAR(10),
  birth_date DATE NOT NULL,
  date_registered DATE NOT NULL,
  county_precinct VARCHAR(7),
  municipality VARCHAR(20),
  ward VARCHAR(2),
  district VARCHAR(2),
  PRIMARY KEY (voter_id),
  KEY party_code (party_code),
  KEY last_name (last_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
OESQL1;

$sql_doors = <<<OESQL1
CREATE TABLE IF NOT EXISTS `voter_doors` (
  voter_id VARCHAR(9) NOT NULL,
  door VARCHAR(255),
  PRIMARY KEY (voter_id),
  KEY last_name (door)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
OESQL1;

$sql2 = <<<OESQL2

CREATE TABLE IF NOT EXISTS `vote_history` (
  voter_id VARCHAR(9) NOT NULL,
  municipality_voted_in VARCHAR(20),
  ward_voted_in VARCHAR(2),
  district_voted_in VARCHAR(2),
  party_voted VARCHAR(3),
  election_date DATE NOT NULL,
  election_name VARCHAR(40),
  election_type VARCHAR(1),
  election_category VARCHAR(1),
  PRIMARY KEY (voter_id, election_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
OESQL2;

$sql3 = <<<OESQL3
CREATE TABLE IF NOT EXISTS `voter_contact` (
  voter_id VARCHAR(9) NOT NULL,
  code VARCHAR(4),
  note TEXT,
  litdrop INT,
  ts TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (voter_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
OESQL3;
