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
  exit("usage: {$argv[0]} district [num streets per page max:3]");
}

$district = (int) $argv[1];

if ($district < 1 || $district > 22) {
  exit("Invalid district $district\n");
}

// Format for query.
$district = sprintf('%02d', $district);

$streets_per_page_max = 0;

if (!empty($argv[2])) {
  $streets_per_page_max = (int) $argv[2];
}

if ($streets_per_page_max <= 0) {
  $streets_per_page_max = 9;
}

echo "Max of {$streets_per_page_max} streets per page\n";

$html_columns = array(
  'phone' => 'phone',
  'dist' => 'district',
  'first_name' => 'first_name',
  'last_name' => 'last_name',
  'num' => 'street_number',
  'street' => 'street_name',
  'unit' => array('suffix_a', 'suffix_b', 'apt_unit_no'),
  'DOB' => 'date_of_birth',
  'party' => 'party_code',
);

$result = db_query("
SELECT v.district, v.first_name, v.last_name, v.street_number, v.street_name, v.suffix_a, v.suffix_b, v.apt_unit_no, SUBSTRING(date_of_birth,1,4) AS date_of_birth, v.party_code,
If(vi.home_phone AND vi.home_phone > '',CONCAT(SUBSTRING(vi.home_phone,1,3), '-',SUBSTRING(vi.home_phone,4,3), '-', SUBSTRING(vi.home_phone,7)), ' ') AS phone FROM voters v 
INNER JOIN voter_doors vd ON v.voter_id = vd.voter_id
LEFT JOIN vbm_info vb ON v.voter_id = vb.voter_id
INNER JOIN van_info vi ON v.voter_id = vi.voter_id
LEFT JOIN voter_contact vc ON vc.voter_id = v.voter_id
WHERE (vc.code_buono IS NULL OR vc.code_buono NOT IN ('N', 'LN', 'W', 'U', '4', ''))
AND v.status NOT LIKE 'Inactive%'
AND (vb.application_received IS NULL OR vb.application_received = '')
AND (vd.rep_exists = 0)
AND v.party_code = 'DEM'
AND vd.door IN (SELECT vdd.door FROM voter_doors vdd INNER JOIN van_info vii ON vdd.voter_id=vii.voter_id WHERE (vii.home_phone IS NOT NULL AND vii.home_phone > ''))
AND v.date_of_birth < '1989-09-01' 
AND v.district = :district 
ORDER BY v.street_name ASC, v.street_num_int ASC, v.suffix_a, v.suffix_b, v.apt_unit_no, v.last_name, v.first_name ASC", array(':district' => $district))->fetchAll(PDO::FETCH_ASSOC);

date_default_timezone_set('America/New_York');
$time = date('Y-m-d_H-i');
$base_name = "gotv_phone_dist_{$district}_{$time}";
$base_fn = "./$base_name";
$html_doc = '';

$html_doc = <<<EOHEAD
<!DOCTYPE HTML>
<html>
<head>
<title>{$base_name}</title>
<style>
body {
  font: Helvetica;
  font-size: 0.7em;
}
div.spacer {
  height: 0.5em;
}
table.walk-list {
  width: 65em;
  border-collapse: collapse;
}
.walk-list th.phone {
  width: 8em;
}
.walk-list th.DOB {
  width: 3em;
}
.walk-list th.dist {
  width: 3em;
}
.walk-list th.street {
  width: 14em;
}
.walk-list th.target, .walk-list th.unit, .walk-list th.party, .walk-list th.num, .walk-list th.knock  {
  width: 2em;
}
.walk-list tr td {
  background-color: #fff;
  padding-left: 0.5em;
  padding-right: 0.5em;
  padding-top: 0.2em;
  padding-bottom: 0.2em;
  border-left: solid 1px;
}
.walk-list tr.odd td {
  background-color: #eee;
}
</style>
</head>
<body>
EOHEAD;

$curr_street = 0;
$html_rows = array();

$last_street_name = NULL;

$doors = array();
$zebra = 0;
foreach ($result as $row) {
  if ($last_street_name != $row['street_name']) {
    $curr_street++;
    $zebra = 0;
  }
  $html_row = build_row_cells($row, $html_columns);
  $address = $row['street_number'] . '|' . $row['street_name'] . '|' . $row['apt_unit_no'] . '|' . $row['suffix_a'] . '|' . $row['suffix_b'];
  $last_street_name = $row['street_name'];
  $html = '<td>' . implode('</td><td>', $html_row) . "</td></tr>\n";
  if (!isset($doors[$address])) {
    $doors[$address] = TRUE;
    $zebra++;
  }
  // Mark odd rows with a class.
  $html = ($zebra % 2 == 1) ? '<tr class="odd">' . $html : '<tr>' . $html;
  $html_rows[$curr_street][] = $html;
}


$curr_street = 1;
$total = count($html_rows);

while ($html_rows) {
  $sum = 0;
  $num_streets = 0;
  $current_set = array();
  do {
    $next = array_shift($html_rows);
    $sum += count($next);
    $current_set[] = $next;
    $num_streets++;
  } while ($html_rows && ($sum < 25) && ($sum + count(reset($html_rows)) < 30) && $num_streets < $streets_per_page_max);

  // Add a page break except for with the 1st street.
  $pagebreak = TRUE && ($curr_street > 1);
  foreach ($current_set as $rows) {
    if (!$pagebreak && ($curr_street > 1)) {
      $html_doc .= '<div class="spacer"></div>';
    }
    $html_doc .= build_table_head($html_columns, $curr_street, $total, $pagebreak, $base_name);
    $html_doc .= implode('', $rows) . "</table>\n<p>" . count($rows) . " doors</p>\n";
    $pagebreak = FALSE;
    $curr_street++;
  }
}

// Build doc up from the end.
$html_doc .= "<p>" . count($doors) . " doors</p></body>\n</html>\n";

$mydir = dirname(__FILE__);
$verbose = FALSE;

if (file_exists("{$mydir}/dompdf/dompdf_config.inc.php")) {
  require_once("{$mydir}/dompdf/dompdf_config.inc.php");
  $dompdf = new DOMPDF();
  $dompdf->load_html($html_doc);
  $dompdf->set_paper('LETTER', 'portrait');
  $dompdf->render();
  file_put_contents("{$base_fn}.pdf", $dompdf->output(array("compress" => 0)));
  if ($verbose) {
    foreach ($GLOBALS['_dompdf_warnings'] as $msg) {
      echo $msg . "\n";
    }
    echo $dompdf->get_canvas()->get_cpdf()->messages;
    flush();
  }
  echo "wrote out {$base_fn}.pdf.\n";
}
else {
  file_put_contents("{$base_fn}.html", $html_doc);
  echo "dompdf not found - wrote out html.\n";
}

exit;

function build_table_head($html_columns, $curr_street, $total, $pagebreak, $base_name) {
$attr = ($pagebreak) ? ' style="page-break-before: always;"': '';
  $thead = <<<EOTHEAD
<p$attr>Street# {$curr_street} of {$total} | List: {$base_name}</p>
<table class="walk-list">
<tr>
EOTHEAD;
  foreach ($html_columns as $key => $ref) {
    $thead .= "<th class=\"$key\">$key</th>";
  }
  $thead .= "</tr>\n";
  return $thead;
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
//INSERT IGNORE INTO voter_contact SELECT vh.voter_id, '4' AS code_buono FROM voters v INNER JOIN vote_history vh ON v.voter_id=vh.voter_id WHERE v.status NOT LIKE 'In%' AND vh.election_date IN ('2012-11-06', '2011-11-08', '2010-11-02', '2009-11-03') GROUP BY vh.voter_id HAVING COUNT(vh.voter_id) >= 4;
