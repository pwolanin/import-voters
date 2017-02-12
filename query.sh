#!/bin/bash

DATABASE=$1
DISTRICT=$2

mysql -uroot "$DATABASE" -Be "SELECT voter_id, last_name, first_name, middle_name, suffix, street_number, suffix_a, suffix_b, street_name, apt_unit_no, municipality, zip5, SUBSTRING(date_of_birth,1,4) AS date_of_birth, party_code, district, status
FROM voters WHERE district = '$DISTRICT' ORDER BY street_name, street_num_int" \
 | $HOME/www/import-voters/tab-to-csv.php
