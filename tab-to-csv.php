#!/usr/bin/env php
<?php
/**
 * A simple CLI script to transform tab-delimited input
 * on stdin to csv output on stdout
 */

// Don't wait for input if there is none already.
stream_set_blocking(STDIN, 0);

while (($line = fgets(STDIN)) !== FALSE) {
  $line = rtrim($line, "\r\n");
  $parts = explode("\t", $line);
  fputcsv(STDOUT, $parts);
}
