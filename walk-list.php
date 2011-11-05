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
  'code' => 'Y LY U LN N W R',
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
  'first_name' => 'first_name',
  'last_name' => 'last_name',
  'number' => 'street_number',
  'street_name' => 'street_name',
  'unit' => array('suffix_a', 'suffix_b', 'apt_unit_no'),
);

$result = db_query($active_db, "
SELECT v.*, vi.home_phone FROM voters v 
LEFT JOIN van_info vi ON v.voter_id = vi.voter_id
INNER JOIN voter_doors vd ON v.voter_id = vd.voter_id
WHERE vd.door IN (SELECT vd.door FROM $viewname v INNER JOIN voter_doors vd ON v.voter_id = vd.voter_id)
ORDER BY v.street_name ASC, v.street_number ASC, v.suffix_a, v.suffix_b, v.apt_unit_no ASC, v.last_name ASC");


$time = date('Y-m-d_h-i');
$html_fp = fopen("./{$viewname}_{$time}.html", 'w');
$csv_fp = fopen("./{$viewname}-update_{$time}.csv", 'w');

$head = <<<EOHEAD
<!DOCTYPE HTML>
<html>
<head>
<title>{$viewname}_{$time}</title>
<style>
table#walk-list {
  width: 68em;
}
#walk-list tr th.note {
  padding-right: 10em;
}
#walk-list tr td {
  background-color: #fff;
  padding-left: 0.5em;
  border-left: solid 1px;
}
#walk-list tr.odd td {
  background-color: #eee;
}
</style>
</head>
<body>
<p>List: {$viewname}_{$time}</p>
<table id="walk-list">
EOHEAD;

fwrite($html_fp, $head);

$html = '<tr>';
foreach ($html_columns as $key => $ref) {
  $html .= "<th class=\"$key\">$key</th>";
}
$html .= "</th></tr>\n";
fwrite($html_fp, $html);
fputcsv($csv_fp, array_keys($csv_columns));

$doors = array();
$zebra = 0;
while ($row = db_fetch_array($result)) {
  $html_row = build_row_cells($row, $html_columns);
  $address = $row['street_number'] . '|' . $row['street_name'] . '|' . $row['apt_unit_no'] . '|' . $row['suffix_a'] . '|' . $row['suffix_b'];
  $doors[$address] = TRUE;
  $html = '<td>' . implode('</td><td>', $html_row) . "</td></tr>\n";
  // Mark odd rows with a class.
  $html = ($zebra % 2 == 1) ? '<tr class="odd">' . $html : '<tr>' . $html;
  fwrite($html_fp, $html);
  $csv_row = build_row_cells($row, $csv_columns);
  fputcsv($csv_fp, $csv_row);
  $zebra++;
}

fwrite($html_fp, "</table>\n<p>" . count($doors) . " doors</p></body>\n</html>\n");
fclose($csv_fp);
fclose($html_fp);
exit;


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
