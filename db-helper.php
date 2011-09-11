<?php
/**
 * Portions of this code are copyrighted by the contributors to Drupal.
 * Additional code copyright 2011 by Peter Wolanin.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, version 2 or any later version.
 */

date_default_timezone_set('UTC');
ini_set('memory_limit', '256M');

/**
 * Indicates the place holders that should be replaced in _db_query_callback().
 */
define('DB_QUERY_REGEXP', '/(%d|%s|%%|%f|%n)/');

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
function _db_query_callback($match, $connection = FALSE) {
  static $args = NULL;
  static $active_db = NULL;
  if ($connection) {
    $args = $match;
    $active_db = $connection;
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
      return mysqli_real_escape_string($active_db, array_shift($args));
    case '%n':
      // Numeric values have arbitrary precision, so can't be treated as float.
      // is_numeric() allows hex values (0xFF), but they are not valid.
      $value = trim(array_shift($args));
      return is_numeric($value) && !preg_match('/x/i', $value) ? $value : '0';
    case '%%':
      return '%';
    case '%f':
      return (float) array_shift($args);
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

  _db_query_callback($args, $active_db);
  $query = preg_replace_callback(DB_QUERY_REGEXP, '_db_query_callback', $query);

  $result = mysqli_query($active_db, $query);


  if (mysqli_errno($active_db)) {
    throw new Exception(mysqli_error($active_db) ."\nquery: ". $query);
  }
  return $result;
}

/**
 * Fetch one result row from the previous query as an array.
 *
 * @param $result
 *   A database query result resource, as returned from db_query().
 * @return
 *   An associative array representing the next row of the result, or FALSE.
 *   The keys of this object are the names of the table fields selected by the
 *   query, and the values are the field values for this result row.
 */
function db_fetch_array($result) {
  if ($result) {
    $array = mysqli_fetch_array($result, MYSQLI_ASSOC);
    return isset($array) ? $array : FALSE;
  }
}

/**
 * Restrict a dynamic table, column or constraint name to safe characters.
 *
 * Only keeps alphanumeric and underscores.
 */
function db_escape_table($string) {
  return preg_replace('/[^A-Za-z0-9_]+/', '', $string);
}
