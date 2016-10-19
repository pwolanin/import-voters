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


  $viewname = "phone_vbm";
  
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
  'requested' => 'application_received',
  'status' => 'ballot_status',
);
  
$result = db_query("
SELECT v.district, v.first_name, v.last_name, v.street_number, v.street_name, v.suffix_a, v.suffix_b, v.apt_unit_no, SUBSTRING(date_of_birth,1,4) AS date_of_birth, v.party_code,
If(vi.preferred_phone AND vi.preferred_phone > '',CONCAT(SUBSTRING(vi.preferred_phone,1,3), '-',SUBSTRING(vi.preferred_phone,4,3), '-', SUBSTRING(vi.preferred_phone,7)), ' ') AS phone, ballot_status, application_received FROM voters v 
INNER JOIN voter_doors vd ON v.voter_id = vd.voter_id
LEFT JOIN vbm_info vb ON v.voter_id = vb.voter_id
LEFT JOIN van_info vi ON v.voter_id = vi.voter_id
WHERE v.status NOT LIKE 'Inactive%'
AND (vb.application_received IS NOT NULL AND vb.application_received > '')
AND vb.ballot_status != 'Received'
AND (vd.rep_exists = 0)
AND v.party_code IN ('DEM', 'UNA')
  ORDER BY v.district, v.street_name ASC, v.street_num_int ASC, v.suffix_a, v.suffix_b, v.apt_unit_no ASC, v.last_name ASC")->fetchAll(PDO::FETCH_ASSOC);
  
  date_default_timezone_set('America/New_York');
  $time = date('Y-m-d_H-i');
  $base_fn = "./{$viewname}_{$time}";
  $html_doc = '';
  
  $html_head = <<<EOHEAD
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

.walk-list th.phone {
  width: 8em;
}
.walk-list th.requested {
  width: 6em;
}
.walk-list th.status {
  width: 7em;
}
.walk-list th.DOB {
  width: 3em;
}
.walk-list th.street {
  width: 11em;
}
.walk-list th.first_name, .walk-list th.last_name {
  width: 9em;
}
.walk-list th.num {
  width: 2.5em;
}
.walk-list th.target, .walk-list th.unit, .walk-list th.party {
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
  
  $doors = array();
  $zebra = 1;
  foreach ($result as $row) {
    $address = $row['street_number'] . '|' . $row['street_name'] . '|' . $row['apt_unit_no'] . '|' . $row['suffix_a'] . '|' . $row['suffix_b'];
    if (!isset($doors[$address])) {
      $zebra++;
    }
    $doors[$address] = TRUE;
    $html_row = build_row_cells($row, $html_columns);
    $html = '<td>' . implode('</td><td>', $html_row) . "</td></tr>\n";
    // Mark odd rows with a class.
    $html = ($zebra % 2 == 1) ? '<tr class="odd">' . $html : '<tr>' . $html;
    $html_rows[] = $html;
  }
  
  $curr_page = 1;
  $total = ceil(count($html_rows) / 40);
  
  while ($html_rows) {
    $sum = 0;
    $current_set = array();
    do {
      $next = array_shift($html_rows);
      $sum++;
      $current_set[] = $next;
    } while ($html_rows && ($sum < 40));
  
    // Add a page break except for with the 1st street.
    $pagebreak = TRUE && ($curr_page > 1);

      $html_doc .= build_table_head($html_columns, $curr_page, $total, $pagebreak, $viewname, $time);
      $html_doc .= implode('', $current_set) . "</table>\n";
    $curr_page++;
  }
  
  // Build doc up from the end.
  $html_doc = $html_head . "<p>PHONE VBM PRINCETON</p>\n" . $html_doc ."\n</body>\n</html>\n";

  
  $mydir = dirname(__FILE__);
//  file_put_contents($mydir . '/out.html', $html_doc);
  
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
    echo "wrote out {$base_fn}.pdf\n";
  }
  else {
    file_put_contents("{$base_fn}.html", $html_doc);
    echo "dompdf not found - wrote out html.\n";
  }


exit;

function build_table_head($html_columns, $curr_page, $total, $pagebreak, $viewname, $time) {
$attr = ($pagebreak) ? ' style="page-break-before: always;"': '';
  $thead = <<<EOTHEAD
<p$attr>Page# {$curr_page} of {$total} | List: {$viewname}_{$time}</p>
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
