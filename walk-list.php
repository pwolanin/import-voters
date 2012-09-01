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

if (count($argv) < 2) {
  exit("usage: {$argv[0]} viewname");
}

$viewname = db_escape_table($argv[1]);

$html_columns = array(
  ' ' => ' ',
  'code_obama' => 'Y LY U LN N W R',
  'code_local' => 'Y LY U LN N W R',
  'targeted' => 'targeted',
  'first_name' => 'first_name',
  'last_name' => 'last_name',
  'num' => 'street_number',
  'street_name' => 'street_name',
  'unit' => array('suffix_a', 'suffix_b', 'apt_unit_no'),
  'DOB' => 'date_of_birth',
  'party' => 'party_code',
  'note' => ' ',
);

$csv_columns = array(
  'voter_id' => 'voter_id',
  'code_obama' => ' ',
  'code_local' => ' ',
  'note' => ' ',
  'targeted' => 'targeted',
  'first_name' => 'first_name',
  'last_name' => 'last_name',
  'number' => 'street_number',
  'street_name' => 'street_name',
  'unit' => array('suffix_a', 'suffix_b', 'apt_unit_no'),
);

$result = db_query("
SELECT v.*, IF (target.voter_id, 'Y', '') AS targeted FROM voters v 
INNER JOIN voter_doors vd ON v.voter_id = vd.voter_id
LEFT JOIN $viewname target ON v.voter_id = target.voter_id
WHERE v.party_code != 'REP' AND vd.door IN (SELECT vd.door FROM $viewname v INNER JOIN voter_doors vd ON v.voter_id = vd.voter_id)
ORDER BY v.street_name ASC, v.street_num_int ASC, v.suffix_a, v.suffix_b, v.apt_unit_no ASC, v.last_name ASC")->fetchAll(PDO::FETCH_ASSOC);


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
  width: 85em;
}
#walk-list tr th.note {
  padding-right: 5em;
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
foreach ($result as $row) {
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
