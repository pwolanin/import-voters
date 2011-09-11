#! /usr/bin/env php
<?php
/**
 * Portions of this code are copyrighted by the contributors to Drupal.
 * Additional code copyright 2011 by Peter Wolanin.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, version 2 or any later version.
 *
 * Imports a tab delimited file with additional info.
 */

require_once dirname(__FILE__) . '/db-helper.php';

// Optionally include the DB url from a file.
if (file_exists('./settings.php')) {
  include './settings.php';
}

if (count($argv) < 2 && isset($db_url)) {
  exit("usage: {$argv[0]} filename");
}
elseif (count($argv) < 3 && !isset($db_url)) {
  exit("usage: {$argv[0]} filename mysqli_connection_url\n\nA valid connection URL looks like 'mysqli://myname:pass@127.0.0.1:3306/voterdbname'\n");
}

if (isset($argv[2])) {
  $db_url = $argv[2];
}

$filename = $argv[1];
if (!file_exists($filename) || !is_readable($filename)) {
  exit("File {$filename} does not exist or cannot be read\n");
}

$active_db = db_connect($db_url);

$sql4 = <<<OESQL4
CREATE TABLE IF NOT EXISTS `van_info` (
  voter_id VARCHAR(9) NOT NULL,
  van_id VARCHAR(9) NOT NULL,
  home_phone VARCHAR(20),
  PRIMARY KEY (voter_id),
  UNIQUE KEY van_id (van_id)
);
OESQL4;

db_query($active_db, $sql4);

$lines = file($filename, FILE_IGNORE_NEW_LINES);
if (!$lines) {
  exit("No data in file {$filename}\n");
}

$delimiter = "\t"; // tab default
$header_line = array_shift($lines);
$header_fields = explode($delimiter, $header_line);
// Allow us to look up desired fileds based on name in header.
$idx = array_flip($header_fields);
$expected_num_fields = count($header_fields);

foreach ($lines as $l) {
  $fields = explode($delimiter, $l);

  if (count($fields) != $expected_num_fields) {
    echo "Invalid line: {$l}\n";
    continue;
  }
  // We write each voter to avoid exhausting memory by keeping
  // two copies of the data.
  $voter = array();
  foreach(array('VoterID', 'VoteBuilder ID', 'HomePhone') as $key) {
    $voter[$key] = $fields[$idx[$key]];
  }
  // For some reason, van has a prefix 'I0210' on the NJ voter ID.
  $voter['VoterID'] = substr($voter['VoterID'], 5);
  db_query($active_db, "REPLACE INTO van_info VALUES(" . db_placeholders($voter, 'varchar') . ")", $voter);
}



exit;

