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
 * Imports a CSV file with information from voter contacts.
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

// This setting handles \r or \n line endings
ini_set('auto_detect_line_endings', 1);

if (($handle = fopen($argv[1], "r")) === FALSE) {
  exit("Couldn't open file {$filename}\n");
}

$header = fgetcsv($handle, 1000);

if (is_array($header)) {
  $idx = array_flip($header);
}
if (!$header || !isset($idx['voter_id']) || !isset($idx['code']) || !isset($idx['note'])) {
  exit("Invalid header row in file {$filename}\n");
}

$active_db = db_connect($db_url);

while (($data = fgetcsv($handle, 1000)) !== FALSE) {
  if (!isset($data[$idx['voter_id']])) {
    echo "Invalid row: " . print_r($data, TRUE);
    continue;
  }
  $id = $data[$idx['voter_id']];
  $code = strtoupper($data[$idx['code']]);
  $note = $data[$idx['note']];
  // Only write real contacts to the DB.
  if (strlen($code)) {
    if ($code == 'W' || $code == 'X') {
      // Bad phone number.
      db_query($active_db, "DELETE FROM van_info WHERE voter_id = '%s'", array($id));
    }
    if ($code != 'X') {
      db_query($active_db, "INSERT INTO voter_contact (voter_id, code, note) VALUES ('%s', '%s', '%s') ON DUPLICATE KEY UPDATE code = VALUES(code), note = CONCAT_WS(',', note, VALUES(note))", array($id, $code, $note));
    }
  }
}

exit;

