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


/**
 * Indicates the place holders that should be replaced in _db_query_callback().
 */
define('DB_QUERY_REGEXP', '/(%d|%s|%%|%f|%b|%n)/');

define('EXPECTED_NUM_FIELDS', 47);

if (count($argv) < 3) {
  exit("usage: {$argv[0]} filename mysqli_connection_url\n");
}

$filename = $argv[1];
if (!file_exists($filename) || !is_readable($filename)) {
  exit("File {$filename} does not exist or cannot be read\n");
}

$db_url = $argv[2];

$active_db = db_connect($db_url);


$sql1 = <<<OESQL1

CREATE TABLE IF NOT EXISTS `voters` (
  voter_id VARCHAR(9) NOT NULL,
  status_code VARCHAR(1),
  party_code VARCHAR(5),
  ballot_type VARCHAR(2),
  last_name VARCHAR(40),
  first_name VARCHAR(30),
  middle_name VARCHAR(30),
  prefix VARCHAR(5),
  suffix VARCHAR(5),
  sex VARCHAR(1),
  street_number VARCHAR(6),
  suffix_a VARCHAR(8),
  suffix_b VARCHAR(3),
  street_name VARCHAR(50),
  apt_unit_no VARCHAR(8),
  street_name_2 VARCHAR(50),
  street_name_3 VARCHAR(50),
  city VARCHAR(30),
  state VARCHAR(2),
  zip5 VARCHAR(5),
  zip4 VARCHAR(4),
  mailing_street_number VARCHAR(6),
  mailing_suffix_a VARCHAR(8),
  mailing_suffix_b VARCHAR(3),
  mailing_street_name1 VARCHAR(50),
  mailing_apt_unit_no VARCHAR(8),
  mailing_street_name2 VARCHAR(50),
  mailing_street_name3 VARCHAR(50),
  mailing_city VARCHAR(30),
  mailing_state VARCHAR(2),
  mailing_country VARCHAR(30),
  mailing_zip_code VARCHAR(10),
  birth_date DATE NOT NULL,
  date_registered DATE NOT NULL,
  county_precinct VARCHAR(7),
  municipality VARCHAR(20),
  ward VARCHAR(2),
  district VARCHAR(2),
  PRIMARY KEY (voter_id),
  KEY party_code (party_code),
  KEY last_name (last_name)
);
OESQL1;

$sql2 = <<<OESQL2

CREATE TABLE IF NOT EXISTS `vote_history` (
  voter_id VARCHAR(9) NOT NULL,
  municipality_voted_in VARCHAR(20),
  ward_voted_in VARCHAR(2),
  district_voted_in VARCHAR(2),
  party_voted VARCHAR(3),
  election_date DATE NOT NULL,
  election_name VARCHAR(40),
  election_type VARCHAR(1),
  election_category VARCHAR(1),
  PRIMARY KEY (voter_id, election_date)
);
OESQL2;

db_query($active_db, $sql1);
db_query($active_db, $sql2);

$lines = file($filename);
if (!$lines) {
  exit("No data in file {$filename}\n");
}

$delimiter = ','; // CSV default
if (count(explode('|', $lines[0])) >= EXPECTED_NUM_FIELDS) {
  $delimiter = '|'; // Pipe delimited
}

foreach ($lines as $l) {
  $fields = explode($delimiter, $l);
  if (count($fields) != EXPECTED_NUM_FIELDS) {
    echo "Invalid line: {$l}\n";
    continue;
  }
}

/**
 * Initialise a database connection.
 *
 * Note that mysqli does not support persistent connections.
 */
function db_connect($url) {
  // Check if MySQLi support is present in PHP
  if (!function_exists('mysqli_init') && !extension_loaded('mysqli')) {
    throw new Exception('Unable to use the MySQLi database because the MySQLi extension for PHP is not installed. Check your <code>php.ini</code> to see how you can enable it.');
  }

  $url = parse_url($url);

  // Decode url-encoded information in the db connection string
  $url['user'] = urldecode($url['user']);
  // Test if database url has a password.
  $url['pass'] = isset($url['pass']) ? urldecode($url['pass']) : '';
  $url['host'] = urldecode($url['host']);
  $url['path'] = urldecode($url['path']);
  if (!isset($url['port'])) {
    $url['port'] = NULL;
  }

  $connection = mysqli_init();
  @mysqli_real_connect($connection, $url['host'], $url['user'], $url['pass'], substr($url['path'], 1), $url['port'], NULL, MYSQLI_CLIENT_FOUND_ROWS);

  if (mysqli_connect_errno() > 0) {
    throw new Exception(mysqli_connect_error());
  }

  // Force MySQL to use the UTF-8 character set. Also set the collation, if a
  // certain one has been set; otherwise, MySQL defaults to 'utf8_general_ci'
  // for UTF-8.
  if (!empty($GLOBALS['db_collation'])) {
    mysqli_query($connection, 'SET NAMES utf8 COLLATE ' . $GLOBALS['db_collation']);
  }
  else {
    mysqli_query($connection, 'SET NAMES utf8');
  }

  return $connection;
}

/**
 * Helper function for db_query().
 */
function _db_query_callback($match, $init = FALSE) {
  static $args = NULL;
  if ($init) {
    $args = $match;
    return;
  }

  switch ($match[1]) {
    case '%d': // We must use type casting to int to convert FALSE/NULL/(TRUE?)
      $value = array_shift($args);
      // Do we need special bigint handling?
      if ($value > PHP_INT_MAX) {
        $precision = ini_get('precision');
        @ini_set('precision', 16);
        $value = sprintf('%.0f', $value);
        @ini_set('precision', $precision);
      }
      else {
        $value = (int) $value;
      }
      // We don't need db_escape_string as numbers are db-safe.
      return $value;
    case '%s':
      return db_escape_string(array_shift($args));
    case '%n':
      // Numeric values have arbitrary precision, so can't be treated as float.
      // is_numeric() allows hex values (0xFF), but they are not valid.
      $value = trim(array_shift($args));
      return is_numeric($value) && !preg_match('/x/i', $value) ? $value : '0';
    case '%%':
      return '%';
    case '%f':
      return (float) array_shift($args);
    case '%b': // binary data
      return db_encode_blob(array_shift($args));
  }
}

/**
 * Generate placeholders for an array of query arguments of a single type.
 *
 * Given a Schema API field type, return correct %-placeholders to
 * embed in a query
 *
 * @param $arguments
 *  An array with at least one element.
 * @param $type
 *   The Schema API type of a field (e.g. 'int', 'text', or 'varchar').
 */
function db_placeholders($arguments, $type = 'int') {
  $placeholder = db_type_placeholder($type);
  return implode(',', array_fill(0, count($arguments), $placeholder));
}

/**
 * Given a Schema API field type, return the correct %-placeholder.
 *
 * Embed the placeholder in a query to be passed to db_query and and pass as an
 * argument to db_query a value of the specified type.
 *
 * @param $type
 *   The Schema API type of a field.
 * @return
 *   The placeholder string to embed in a query for that type.
 */
function db_type_placeholder($type) {
  switch ($type) {
    case 'varchar':
    case 'char':
    case 'text':
    case 'datetime':
      return "'%s'";

    case 'numeric':
      // Numeric values are arbitrary precision numbers.  Syntacically, numerics
      // should be specified directly in SQL. However, without single quotes
      // the %s placeholder does not protect against non-numeric characters such
      // as spaces which would expose us to SQL injection.
      return '%n';

    case 'serial':
    case 'int':
      return '%d';

    case 'float':
      return '%f';

    case 'blob':
      return '%b';
  }

  // There is no safe value to return here, so return something that
  // will cause the query to fail.
  return 'unsupported type '. $type .'for db_type_placeholder';
}


/**
 * Runs a basic query in the active database.
 *
 * User-supplied arguments to the query should be passed in as separate
 * parameters so that they can be properly escaped to avoid SQL injection
 * attacks.
 *
 * @param $query
 *   A string containing an SQL query.
 * @param $args
 *   Array with variable number of arguments which are substituted into the query
 *   using printf() syntax. Instead of a variable number of query arguments,
 *   you may also pass a single array containing the query arguments.
 *
 *   Valid %-modifiers are: %s, %d, %f, %b (binary data, do not enclose
 *   in '') and %%.
 *
 *   NOTE: using this syntax will cast NULL and FALSE values to decimal 0,
 *   and TRUE values to decimal 1.
 *
 * @return
 *   A database query result resource, or Exception if the query was not
 *   executed correctly.
 */
function db_query($active_db, $query, $args = array()) {

  _db_query_callback($args, TRUE);
  $query = preg_replace_callback(DB_QUERY_REGEXP, '_db_query_callback', $query);

  $result = mysqli_query($active_db, $query);


  if (mysqli_errno($active_db)) {
    throw new Exception(mysqli_error($active_db) ."\nquery: ". $query);
  }
  return $result;
}
