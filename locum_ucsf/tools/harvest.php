#!/Applications/MAMP/bin/php5/bin/php -q
<?php

// You'll need to change these:
$first_record = 1246762; //<-- [journal], Annals of hematology, we want 852 & 866
$last_record = 1346762;
//$first_record = 1000000;
//$last_record = 1300000;
$large_record_split = 10;

// Init scripts, library locations, and binaries
$locum_lib_dir = '/usr/local/lib/locum';
$mysql_init_script = '/Applications/MAMP/bin/stopMysql.sh && /Applications/MAMP/bin/startMysql.sh #';
$sphinx_init_script = '/etc/init.d/sphinx';
$sphinx_indexer = '/usr/local/sphinx/bin/indexer';

// Include Locum libraries
require_once($locum_lib_dir . '/locum-server.php');

// Data Set repair
$locum = new locum_server;

// Older records tend to be much more weeded, so the child processes
// that handle the higher bib nums often work much harder and longer.
// This is a way around that.
if (($last_record - $first_record) > 1000) {
  $split_amount = ceil(($last_record - $first_record) / $large_record_split);
  $begin_at_bib = $first_record;
  for ($i = 0; $i < $large_record_split; $i++){
    $split_bib_arr[$i]['first'] = $begin_at_bib;
    $split_bib_arr[$i]['last'] = $begin_at_bib + $split_amount;
    $begin_at_bib = $begin_at_bib + $split_amount + 1;
  }
  foreach ($split_bib_arr as $split_bib) {
    $locum->harvest_bibs($split_bib['first'], $split_bib['last']);
  }
} else {
  $locum->harvest_bibs($first_record, $last_record);
}

$locum->rebuild_holds_cache();

// Restart services, reindex, etc.
shell_exec($mysql_init_script . ' restart');
sleep(2);
shell_exec($sphinx_indexer . '  --all --rotate');