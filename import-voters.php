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
define('EXPECTED_NUM_FIELDS', 47);

// Optionally include the DB url from a file.
if (file_exists('./settings.php')) {
  include './settings.php';
}

if (count($argv) < 2 && isset($db_url)) {
  exit("usage: {$argv[0]} viewname");
}
elseif (count($argv) < 3 && !isset($db_url)) {
  exit("usage: {$argv[0]} viewname mysqli_connection_url\n\nA valid connection URL looks like 'mysqli://myname:pass@127.0.0.1:3306/voterdbname'\n");
}

if (isset($argv[2])) {
  $db_url = $argv[2];
}

$filename = $argv[1];
if (!file_exists($filename) || !is_readable($filename)) {
  exit("File {$filename} does not exist or cannot be read\n");
}

$active_db = db_connect($db_url);


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
  PRIMARY KEY (voter_id)
);
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
);
OESQL2;

$sql3 = <<<OESQL3
CREATE TABLE IF NOT EXISTS `voter_contact` (
  voter_id VARCHAR(9) NOT NULL,
  code VARCHAR(4),
  note TEXT,
  ts TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (voter_id)
);
OESQL3;

db_query($active_db, $sql1);
db_query($active_db, $sql2);
db_query($active_db, "CREATE INDEX party_code ON voters (party_code)");
db_query($active_db, "CREATE INDEX last_name ON voters (last_name)");
db_query($active_db, $sql3);

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
    continue;
  }
  // We write each voter to avoid exhausting memory by keeping
  // two copies of the data.
  if ($id != $fields[0]) {
    voter_write_data($active_db, $voter_rows);
    $id = $fields[0];
    $voter_rows = array();
  }
  // Index by voter ID and election date.
  $date = strtotime($fields[42]);
  $idx = $fields[0] . ':' . $date;
  $voter_rows[$idx] = $fields;
}
// Write the last one.
voter_write_data($active_db, $voter_rows);


exit;

function voter_write_data($active_db, $voter_rows) {
  // Sort on the keys so that the most recent data is last
  // for each voter.
  ksort($voter_rows);

  $fields = NULL;
  foreach ($voter_rows as $fields) {
    $vote_history = array_merge(array_slice($fields, 0, 1), array_slice($fields, 38, 8));
    $vote_history[5] = voter_reformat_date($vote_history[5]);
    db_query($active_db, "REPLACE INTO vote_history VALUES(" . db_placeholders($vote_history, 'varchar') . ")", $vote_history);
  }
  if (is_array($fields)) {
    // Write, the last, most recent, record into the voters table.
    $voter = array_slice($fields, 0, 38);
    $voter[32] = voter_reformat_date($voter[32]);
    $voter[33] = voter_reformat_date($voter[33]);
    db_query($active_db, "REPLACE INTO voters VALUES(" . db_placeholders($voter, 'varchar') . ")", $voter);
  }
}

function voter_reformat_date($str) {
  $parts = explode('/', $str);
  return "{$parts[2]}-{$parts[0]}-{$parts[1]}";
}

