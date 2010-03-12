#!/usr/bin/php5 -q
<?php

// Init scripts, library locations, and binaries
$locum_lib_dir = '/usr/local/lib/locum';
$mysql_init_script = '/etc/init.d/mysql';
$sphinx_init_script = '/etc/init.d/sphinx';
$sphinx_indexer = '/usr/local/sphinx/bin/indexer';

// Include Locum libraries
require_once($locum_lib_dir . '/locum-server.php');

// Data Set repair
$locum = new locum_server;
$db =& MDB2::connect($locum->dsn);

$min_bib_result =& $db->query('SELECT MIN(bnum) FROM locum_bib_items');
$min_bib = $min_bib_result->fetchOne();
$max_bib_result =& $db->query('SELECT MAX(bnum) FROM locum_bib_items');
$max_bib = $max_bib_result->fetchOne();

$bnum_result =& $db->query("SELECT bnum FROM locum_bib_items WHERE bnum >= $min_bib AND bnum <= $max_bib");
$bnum_arr = $bnum_result->fetchCol();

$bib_range = array();
for ($i = $min_bib; $i < $max_bib; $i++) {
  $bib_range[] = $i;
}
$empty_bibs = array_diff($bib_range, $bnum_arr);

foreach ($empty_bibs as $empty_bib) {
  $locum->harvest_bibs($empty_bib, $empty_bib);
}

$locum->rebuild_holds_cache();

// Restart services, reindex, etc.
shell_exec($mysql_init_script . ' restart');
sleep(2);
shell_exec($sphinx_indexer . '  --all --rotate');