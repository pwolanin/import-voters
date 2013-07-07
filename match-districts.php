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
$multiple=0;
$none=0;
$updated=0;

foreach (db_query("SELECT * FROM {movers} WHERE district = '' OR district = '0' OR district IS NULL") as $row) {
  $args[':street_name'] = trim($row->street_pre_drct . ' ' . $row->street_name . ' ' . $row->street_suffix . ' ' . $row->street_post_drct);
  $args[':street_num'] = $row->street_numb;
  $args[':side'] = ($row->street_numb % 2 == 0) ? 'E' : 'O';
  $matches = db_query("SELECT DISTINCT(district) FROM {street_ranges} WHERE street_name = :street_name AND :street_num >= low_range AND :street_num <= high_range AND (side = 'A' OR side = :side)", $args)->fetchCol();
  if ($matches && count($matches) > 1) {
    echo "Invalid row - multiple matches:\n";
    print_r($row);
    $multiple++;
  }
  elseif (!$matches) {
    echo "Invalid row - zero matches:\n";
    print_r($row);
    $none++;
  }
  else {
    $updated++;
    $district = end($matches);
    db_update('movers')
      ->fields(array('district' => $district))
      ->condition('id', $row->id)
      ->execute();
  }
}

echo "Result: updated $updated, invalid multiple $multiple, no match $none\n\n";

exit;



