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

$viewname = db_escape_table($argv[1]);
if (isset($argv[2])) {
  $db_url = $argv[2];
}

$active_db = db_connect($db_url);

$html_columns = array(
  ' ' => ' ',
  'code' => 'Y LY U LN N W V R',
  'phone' => 'home_phone',
  'first_name' => 'first_name',
  'last_name' => 'last_name',
  'num' => 'street_number',
  'street_name' => 'street_name',
  'unit' => array('suffix_a', 'suffix_b', 'apt_unit_no'),
  'birth_date' => 'birth_date',
  'party' => 'party_code',
  'note' => ' ',
);

$csv_columns = array(
  'voter_id' => 'voter_id',
  'code' => ' ',
  'note' => ' ',
  'phone' => 'home_phone',
  'first_name' => 'first_name',
  'last_name' => 'last_name',
  'number' => 'street_number',
  'street_name' => 'street_name',
  'unit' => array('suffix_a', 'suffix_b', 'apt_unit_no'),
);


$result = db_query($active_db, "
SELECT v.*, IF(vi.home_phone IS NOT NULL, vi.home_phone, '') AS home_phone FROM voters v 
LEFT JOIN van_info vi ON v.voter_id = vi.voter_id
LEFT JOIN voter_contact vc ON vc.voter_id = v.voter_id
INNER JOIN voter_doors vd ON v.voter_id = vd.voter_id
WHERE vd.door IN (SELECT vd2.door FROM $viewname v2 INNER JOIN voter_doors vd2 ON v2.voter_id = vd2.voter_id)
AND vc.code IS NULL
ORDER BY v.street_name ASC, v.street_number ASC, v.suffix_a ASC, v.suffix_b ASC, v.apt_unit_no ASC, vi.home_phone DESC, v.last_name ASC, v.first_name ASC");

$all_doors = array();
while ($row = db_fetch_array($result)) {
  $address = $row['street_number'] . '|' . $row['street_name'] . '|' . $row['apt_unit_no'] . '|' . $row['suffix_a'] . '|' . $row['suffix_b'];
  $all_doors[$address][] = $row;
}

$all_doors = array_filter($all_doors, 'address_has_home_phone');

$chunks = array_chunk($all_doors, 15);

foreach ($chunks as $idx => $doors) {
  list($html_fp, $csv_fp) = open_files($idx + 1, $viewname, $html_columns, $csv_columns);
  $zebra = 0;
  foreach ($doors as $h) {
    foreach ($h as $row) {
      $html_row = build_row_cells($row, $html_columns);
      $html = '<td>' . implode('</td><td>', $html_row) . "</td></tr>\n";
      // Mark odd rows with a class.
      $html = ($zebra % 2 == 1) ? '<tr class="odd">' . $html : '<tr>' . $html;
      fwrite($html_fp, $html);
      $csv_row = build_row_cells($row, $csv_columns);
      fputcsv($csv_fp, $csv_row);
      $zebra++;
    }
  }
  close_files($doors, $html_fp, $csv_fp);
}

exit;

function address_has_home_phone($address) {
  foreach ($address as $row) {
    if (!empty($row['home_phone'])) {
      return TRUE;
    }
  }
  return FALSE;
}

function open_files($file_no, $viewname, $html_columns, $csv_columns) {

  $file = sprintf('%02d', $file_no);
  $time = date('Y-m-d_h-i');
  $html_fp = fopen("./phone-{$viewname}_{$file}_{$time}_.html", 'w');
  $csv_fp = fopen("./phone-{$viewname}-{$file}_update_{$time}.csv", 'w');

  $head = <<<EOHEAD
<!DOCTYPE HTML>
<html>
<head>
<title>{$viewname}_{$file}_{$time}</title>
<style>
table#phone-list {
  width: 68em;
}
#phone-list tr th.note {
  padding-right: 5em;
}
#phone-list tr td {
  background-color: #fff;
  padding-left: 0.5em;
  border-left: solid 1px;
}
#phone-list tr.odd td {
  background-color: #eee;
}
</style>
</head>
<body>
<p>List: {$viewname}_{$file}_{$time}</p>
<table id="phone-list">
EOHEAD;

  fwrite($html_fp, $head);
  
  $html = '<tr>';
  foreach ($html_columns as $key => $ref) {
    $html .= "<th class=\"$key\">$key</th>";
  }
  $html .= "</th></tr>\n";
  fwrite($html_fp, $html);
  fputcsv($csv_fp, array_keys($csv_columns));
  return array($html_fp, $csv_fp);
}

function close_files($doors, $html_fp, $csv_fp) {
  fwrite($html_fp, "</table>\n<p>" . count($doors) . " doors</p></body>\n</html>\n");
  fclose($csv_fp);
  fclose($html_fp);
}

function build_row_cells($data, $columns) {
  $row = array();
  foreach($columns as $ref) {
    if (is_array($ref)) {
      $combined = '';
      foreach ($ref as $sub) {
        if (strlen($data[$sub])) {
          $combined = $data[$sub] . ' ';
        }
      }
      $row[] = trim($combined);
    }
    else {
      $row[] = isset($data[$ref]) ? $data[$ref] : $ref;
    }
  }
  return $row;
}
