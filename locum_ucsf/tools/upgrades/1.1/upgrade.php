#!/usr/bin/php5 -q
<?php

// You may want/need to change this
ini_set('memory_limit', '400M');

// Where is Locum?
$locum_lib_dir = '/usr/local/lib/locum';

// Include Locum libraries
require_once($locum_lib_dir . '/locum-server.php');

// Fire it up
$locum = new locum_server;

// Force all records to be updated
$db = $locum->db_query("UPDATE locum_bib_items SET bib_last_update = '1970-01-01' WHERE active = '1'");

// Rebuild Facet Heap - first pass
$locum->rebuild_facet_heap();

// Data maintenance
$locum->verify_bibs();
$locum->new_bib_scan();
$locum->rebuild_holds_cache();

// Rebuild Facet Heap - second pass
$locum->rebuild_facet_heap();

// Restart services, reindex, etc.
$locum->index();

// This can all be done in situ
$locum->verify_status();
$locum->verify_syndetics();