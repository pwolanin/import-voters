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


//$viewname = db_escape_table($argv[1]);

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
  $streets_per_page_max = 2;
}

echo "Max of {$streets_per_page_max} streets per page\n";

foreach (array(0,1) as $odd) {
  $viewname = "district_{$district}_" . ($odd ? 'odd' : 'even');
  echo "$viewname\n";
  
  $html_columns = array(
    'target' => 'target',
    'clinton' => 'Y&nbsp;LY&nbsp;U&nbsp;LN&nbsp;N&nbsp;W&nbsp;R',
    'first_name' => 'first_name',
    'last_name' => 'last_name',
    'num' => 'street_number',
    'street' => 'street_name',
    'unit' => array('suffix_a', 'suffix_b', 'apt_unit_no'),
    'DOB' => 'date_of_birth',
    'party' => 'party_code',
    'note' => ' ',
  );
  
  $csv_columns = array(
    'voter_id' => 'voter_id',
    'code_clinton' => ' ',
    'note' => ' ',
    'target' => 'target',
    'first_name' => 'first_name',
    'last_name' => 'last_name',
    'number' => 'street_number',
    'street' => 'street_name',
    'unit' => array('suffix_a', 'suffix_b', 'apt_unit_no'),
  );
  
  $result = db_query("
  SELECT v.voter_id, v.first_name, v.last_name, v.street_number, v.street_name, v.suffix_a, v.suffix_b, v.apt_unit_no, v.party_code,
  SUBSTRING(v.date_of_birth, 1, 4) as date_of_birth, IF (tv.voter_id, 'Y', '') AS target FROM voters v 
  INNER JOIN voter_doors vd ON v.voter_id = vd.voter_id
  LEFT JOIN target_voters tv ON v.voter_id = tv.voter_id
  WHERE v.status NOT LIKE 'Inactive%'
  AND vd.door IN (SELECT door FROM target_voters)
  AND v.district = '$district'
  AND v.street_num_int % 2 = $odd
  AND vd.rep_exists <> 1
  ORDER BY v.street_name ASC, v.street_num_int ASC, v.suffix_a, v.suffix_b, v.apt_unit_no ASC, target DESC, v.last_name ASC")->fetchAll(PDO::FETCH_ASSOC);
  
  date_default_timezone_set('America/New_York');
  $time = date('Y-m-d_H-i');
  $base_fn = "./{$viewname}_{$time}";
  $csv_fp = fopen("./{$base_fn}-update.csv", 'w');
  $html_doc = '';
  
  $html_doc = <<<EOHEAD
<!DOCTYPE HTML>
<html>
<head>
<title>{$viewname}_{$time}</title>
<style>
body {
  font: Helvetica;
  font-size: 0.7em;
}
div.spacer {
  height: 1.4em;
}
table.walk-list {
  width: 7.5in;
  border-collapse: collapse;
}
.walk-list th.note {
  padding-right: 5em;
}
.walk-list th.DOB {
  width: 3em;
}
.walk-list th.buono, {
  width: 9em;
}
.walk-list th.street {
  width: 11em;
}
.walk-list th.first_name, .walk-list th.last_name {
  width: 11em;
}
.walk-list th.num {
  width: 2.5em;
}
.walk-list th.target, .walk-list th.unit, .walk-list th.party, .walk-list th.knock {
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
body {
  padding-top: 5em;
}
</style>
</head>
<body>
EOHEAD;
  
  $curr_street = 0;
  $html_rows = array();
  
  $last_street_name = NULL;
  
  fputcsv($csv_fp, array_keys($csv_columns));
  
  $doors = array();
  $zebra = 1;
  foreach ($result as $row) {
    if ($last_street_name != $row['street_name']) {
      $curr_street++;
      $zebra = 1;
    }
    $address = $row['street_number'] . '|' . $row['street_name'] . '|' . $row['apt_unit_no'] . '|' . $row['suffix_a'] . '|' . $row['suffix_b'];
    if (!isset($doors[$address])) {
      $zebra++;
    }
    $doors[$address] = TRUE;
    $html_row = build_row_cells($row, $html_columns);
    $last_street_name = $row['street_name'];
    $html = '<td>' . implode('</td><td>', $html_row) . "</td></tr>\n";
    // Mark odd rows with a class.
    $html = ($zebra % 2 == 1) ? '<tr class="odd">' . $html : '<tr>' . $html;
    $html_rows[$curr_street][] = $html;
    $csv_row = build_row_cells($row, $csv_columns);
    fputcsv($csv_fp, $csv_row);
  }
  
  // Close CSV file.
  fclose($csv_fp);
  
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
    } while ($html_rows && ($sum < 39) && ($sum + count(reset($html_rows)) < 44) && $num_streets < $streets_per_page_max);
  
    // Add a page break except for with the 1st street.
    $pagebreak = TRUE && ($curr_street > 1);
    foreach ($current_set as $rows) {
      if (!$pagebreak && ($curr_street > 1)) {
        $html_doc .= '<div class="spacer"></div>';
      }
      $html_doc .= build_table_head($html_columns, $curr_street, $total, $pagebreak, $viewname, $time);
      $html_doc .= implode('', $rows) . "</table>\n";
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
}

exit;

function build_table_head($html_columns, $curr_street, $total, $pagebreak, $viewname, $time) {
$attr = ($pagebreak) ? ' style="page-break-before: always;"': '';
  $thead = <<<EOTHEAD
<p$attr>Street# {$curr_street} of {$total} | List: {$viewname}_{$time}</p>
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
