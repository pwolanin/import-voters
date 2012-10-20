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

if (count($argv) < 2 ) {
  exit("usage: {$argv[0]} filename");
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
if (!$header || !isset($idx['voter_id']) || !isset($idx['code_obama']) || !isset($idx['code_menendez']) || !isset($idx['note'])) {
  exit("Invalid header row in file {$filename}\n");
}

while (($data = fgetcsv($handle, 1000)) !== FALSE) {
  if (!isset($data[$idx['voter_id']])) {
    echo "Invalid row: " . print_r($data, TRUE);
    continue;
  }
  $id = $data[$idx['voter_id']];
  $obama = strtoupper($data[$idx['code_obama']]);
  $menendez = strtoupper($data[$idx['code_menendez']]);
  $note = preg_replace('/[^A-Za-z0-9_. ]+/', ' ', $data[$idx['note']]);
  // Only write real contacts to the DB.
  if (strlen($obama)) {
    db_merge('voter_contact')
      ->key(array('voter_id' => $id))
      ->fields(array('code_obama' => $obama, 'code_menendez' => $menendez, 'note' => $note))
      ->execute();
    //db_query($active_db, "INSERT INTO voter_contact (voter_id, code, note) VALUES ('%s', '%s', '%s', 1) ON DUPLICATE KEY UPDATE code = VALUES(code), note = CONCAT_WS(',', note, VALUES(note)), litdrop = 1", array($id, $code, $note));
  }
}

exit;

