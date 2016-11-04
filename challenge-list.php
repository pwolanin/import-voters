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
  exit("usage: {$argv[0]} district [rows per page]");
}

$district = (int) $argv[1];

if ($district < 1 || $district > 22) {
  exit("Invalid district $district\n");
}

$rows_per_page = !empty($argv[2]) ? (int) $argv[2] : 35;

if ($rows_per_page < 10 || $rows_per_page > 100) {
  exit("Invalid rows per page $rows_per_page\n");
}

// Format for query.
$district = sprintf('%02d', $district);


echo "District {$district}\n";

$html_columns = array(
  'phone' => 'phone',
  'last_name' => 'last_name',
  'suffix' => 'suffix',
  'first_name' => 'first_name',
  'middle_name' => 'middle_name',
  'num' => 'street_number',
  'street' => 'street_name',
  'unit' => array('suffix_a', 'suffix_b', 'apt_unit_no'),
  'DOB' => 'date_of_birth',
  'party' => 'party_code',
  'status' => 'status',
);

$result = db_query("
SELECT v.first_name, v.last_name, v.middle_name, v.suffix, v.street_number, v.street_name, v.suffix_a, v.suffix_b, v.apt_unit_no, v.party_code, SUBSTRING(v.status,1,16) as status,
  SUBSTRING(v.date_of_birth, 1, 4) as date_of_birth,
  If(vi.preferred_phone AND vi.preferred_phone > '',CONCAT(SUBSTRING(vi.preferred_phone,1,3), '-',SUBSTRING(vi.preferred_phone,4,3), '-', SUBSTRING(vi.preferred_phone,7)), ' ') AS phone,
  IF(vb.ballot_status IS NOT NULL,vb.ballot_status,'') as ballot_status,
  vc.code_clinton
FROM {voters} v 
LEFT JOIN {vbm_info} vb ON v.voter_id = vb.voter_id
LEFT JOIN {van_info} vi ON v.voter_id = vi.voter_id
LEFT JOIN {voter_contact} vc ON v.voter_id = vc.voter_id
WHERE v.district = '$district'
ORDER BY v.last_name ASC, v.first_name ASC, v.middle_name, v.street_name ASC, v.street_num_int ASC")->fetchAll(PDO::FETCH_ASSOC);

date_default_timezone_set('America/New_York');
$time = date('Y-m-d_H-i');
$base_fn = "./2016-challlenge-{$district}_{$time}";
$html_doc = '';

$html_doc = <<<EOHEAD
<!DOCTYPE HTML>
<html>
<head>
<title>{$district}_{$time}</title>
<style>
body {
  font: Helvetica;
  font-size: 0.7em;
}
div.spacer {
  height: 1.4em;
}
table.walk-list {
  width: 85em;
}
.walk-list th.phone {
  width: 10em;
}
.walk-list th.DOB {
  width: 3em;
}

.walk-list th.street, th.phone {
  width: 14em;
}
.walk-list th.first_name, .walk-list th.middle_name {
  width: 10em;
}
.walk-list th.last_name {
  width: 14em;
}

.walk-list th.status {
  text-align: left;
}

.walk-list th.suffix, .walk-list th.unit, .walk-list th.party, .walk-list th.num {
  width: 2em;
}
.walk-list tr td {
  background-color: #fff;
  padding-left: 0.5em;
  border-left: solid 1px;
}
.walk-list tr.odd td {
  background-color: #eee;
}
</style>
</head>
<body>
EOHEAD;


$html_rows = array();
$zebra = 0;
foreach ($result as $row) {
  $html_row = build_row_cells($row, $html_columns);
  $html = '<td>' . implode('</td><td>', $html_row) . "</td></tr>\n";
  // Mark odd rows with a class.
  $html = ($zebra % 2 == 1) ? '<tr class="odd">' . $html : '<tr>' . $html;
  $html_rows[] = $html;
  $zebra++;
}

  $count_voters = count($html_rows);
  $curr_page = 1;
  $total = ceil(count($html_rows) / $rows_per_page);

  while ($html_rows) {
    $sum = 0;
    $current_set = array();
    do {
      $next = array_shift($html_rows);
      $sum++;
      $current_set[] = $next;
    } while ($html_rows && ($sum < $rows_per_page));
  


    $html_doc .= build_table_head($html_columns, $curr_page, $total, count($current_set), $district, $count_voters);
    $html_doc .= implode('', $current_set) . "</table>\n";
    $curr_page++;
  }

// End the html doc.
$html_doc .= "\n</body>\n</html>\n";


$mydir = dirname(__FILE__);
$verbose = FALSE;

if (file_exists("{$mydir}/dompdf/dompdf_config.inc.php")) {
  require_once("{$mydir}/dompdf/dompdf_config.inc.php");
  $dompdf = new DOMPDF();
  $dompdf->load_html($html_doc);
  $dompdf->set_paper('LETTER', 'landscape');
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

function build_table_head($html_columns, $curr_page, $total, $page_count, $district, $count_voters) {
  // Add a page break except for with the 1st page.
  $pagebreak = ($curr_page > 1);
  $attr = ($pagebreak) ? ' style="page-break-before: always;"': '';
  $thead = <<<EOTHEAD
<p$attr>District: {$district} (Page# {$curr_page} of {$total}) {$page_count} voters this page of {$count_voters} total. Vote-by-mail (VBM) data as of 2016-11-03.</p>
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
  if (!in_array($data['party_code'], array('DEM', 'UNA')) || (isset($data['code_clinton']) && $data['code_clinton'] != 'Y')) {
    // Only call likely supporters.
    $data['phone'] = '---';
  }
  elseif ($data['ballot_status']) {
    // Don't call people with VBM ballots.
    $data['phone'] = 'VBM: ' . $data['ballot_status'];
  }
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
