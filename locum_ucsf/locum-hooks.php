<?php
/**
 * Locum is a software library that abstracts ILS functionality into a
 * catalog discovery layer for use with such things as bolt-on OPACs like
 * SOPAC.
 *
 * This file allows developers to hook in to the Locum framework and replace existing class
 * functions without altering the core project files.  This file is not required for operation
 * and can safely be removed if you wish.  For more inormation, read the Locum Developer's 
 * Guide on http://thesocialopac.net
 *
 * @package Locum
 * @author John Blyberg
 */
 
 /**
  * Override class for locum
  */
class locum_hook extends locum {
  
}

 /**
  * Override class for locum_server
  */
class locum_server_hook extends locum_server {
  
}

 /**
  * Override class for locum_client
  */
class locum_client_hook extends locum_client {
/**
   * Returns information about a bib title.
   *
   * @param string $bnum Bib number
   * @param boolean $get_inactive Return records whose active = 0
   * @return array Bib item information
   */
  public function get_bib_item($bnum, $get_inactive = FALSE) {
    $db = MDB2::connect($this->dsn);
    $utf = "SET NAMES 'utf8' COLLATE 'utf8_unicode_ci'";
    $utfprep = $db->query($utf);
    if ($get_inactive) {
      $sql = "SELECT * FROM locum_bib_items b JOIN locum_bib_items_university u ON b.bnum=u.bnum WHERE b.bnum = '$bnum' LIMIT 1";
    } else {
      $sql = "SELECT * FROM locum_bib_items b JOIN locum_bib_items_university u ON b.bnum=u.bnum WHERE b.bnum = '$bnum' AND active = '1' LIMIT 1";
    }
    $res = $db->query($sql);
    $item_arr = $res->fetchAll(MDB2_FETCHMODE_ASSOC);
    $db->disconnect();
    $item_arr[0]['stdnum'] = preg_replace('/[^\d]/','', $item_arr[0]['stdnum']);
    return $item_arr[0];
  }
}