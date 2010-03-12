#!/usr/bin/php -q
<?php

/*
Parses location codes from the "Branch code" list in the Millennium telnet client.  Get to it by navigating to M (Management) > I (Information about system) > C (Codes used) > B (Branch codes).  Since III only allows you to print the list, you'll have to copy and paste it into a text file and make sure the format matches up to like the following example:

 001 > a         Adult Collection
 002 > adabi     Adult Playaway Biographies
 003 > adadm     Adult Playaway Detective Mysteries
 004 > adaf      Adult Playaway Fiction
 005 > adamh     Adult Holiday Music Display
 006 > adanf     Adult Playaway Nonfiction

... and so on.

Then run this script and pass the filename as an argument.  Paste the output into locum_iii_config.ini and put it in the config directory of the III locum connector.

*/

$loc_list_raw = file_get_contents($argv[1]);
$codes_raw = split("[\n|\r]", $loc_list_raw);
$config_string = '';

foreach ($codes_raw as $code_raw) {
  $loc = '';
  $string_tmp = trim(substr($code_raw, 6));
  $code_arr_tmp = explode(' ', $string_tmp);
  $key = array_shift($code_arr_tmp);
  foreach ($code_arr_tmp as $code_tmp) {
    if (trim($code_tmp)) {
      $loc .= trim($code_tmp) . ' ';
    }
  }
  $locations_arr[$key] = trim($loc);
  
}

foreach ($locations_arr as $code => $location) {
  $config_string .= $code . "\t\t\t = " . '"' . $location . '"' . "\n";
}

$output = "\n\n[iii_location_codes]\n\n" . $config_string;

print $output;