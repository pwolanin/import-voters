#! /usr/bin/env php
<?php
/**
 * Portions of this code are copyrighted by the contributors to Drupal.
 * Additional code copyright 2011-2013 by Peter Wolanin.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, version 2 or any later version.
 */

require_once dirname(__FILE__) . '/db-helper.php';

$scriptname = array_shift($argv);

if (count($argv) < 1) {
  exit("usage: {$scriptname} DISTRICT\n\n");
}

$district = array_shift($argv);

$multiple=0;
$none=0;
$updated=0;

$headers = array('district', 'first_name', 'middle_name', 'last_name', 'street_address', 'street_name', 'apt_unit_no', 'party_code', 'status');
fputcsv(STDOUT, $headers);

foreach (db_query("SELECT * FROM {movers} WHERE district = :district ORDER BY district, street_name, street_num_int, last_name", array(':district' => $district)) as $row) {
  $args[':street_name'] = trim($row->street_pre_drct . ' ' . $row->street_name . ' ' . $row->street_suffix . ' ' . $row->street_post_drct) . '%';
  $args[':street_num'] = $row->street_numb;
  $res = db_query("SELECT district, first_name, middle_name, last_name, TRIM(CONCAT_WS(' ', street_number, suffix_a, suffix_b)) as street_address, street_name, apt_unit_no, party_code, status FROM {voters} WHERE street_name LIKE :street_name AND street_number = :street_num", $args)->fetchAll(PDO::FETCH_ASSOC);
  foreach ($res as $voter) {
    fputcsv(STDOUT, $voter);
  }
}


exit;



