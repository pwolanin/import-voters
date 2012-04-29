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
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

define('DRUPAL_ROOT', dirname(__FILE__));

/**
 * No-op function to avoid needing to alter the DBTNG source.
 */
function drupal_alter() {
}

$cwd = getcwd();

if (file_exists("$cwd/settings.php")) {
  $settings_file = "$cwd/settings.php";
}
elseif (file_exists(DRUPAL_ROOT . '/settings.php')) {
  $settings_file = DRUPAL_ROOT . '/settings.php';
}
else {
  echo "You must define a database connection in a settings.php file in the CWD or in the script directory";
  exit(1);
}

include $settings_file;

require_once DRUPAL_ROOT . '/includes/unicode.inc';
// Initialize the database system. Note that the connection
// won't be initialized until it is actually requested.
require_once DRUPAL_ROOT . '/includes/database/database.inc';

