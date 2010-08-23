<?php
/**
 * Locum is a software library that abstracts ILS functionality into a
 * catalog discovery layer for use with such things as bolt-on OPACs like
 * SOPAC.
 *
 * This connector assumes that your Millennium webpac/webpac pro is set up
 * to display in UTF-8.  You may need to verify with III's helpdesk that
 * it is.  Additionally, you will need to verify that your III database
 * has been converted to unicode storage.  See:
 * http://csdirect.iii.com/documentation/unicodestorage.shtml
 *
 * This connector has been developed against the plain-vanilla WebPAC
 * Example Sets and Photoshop Files that can be downloaded for Release
 * 2007 from here:
 * http://csdirect.iii.com/downloads/webopac_custom_files.shtml
 * The only wwwoption change you'll need to make, will be to enable
 * (uncheck Inactive) FREEZE_HOLDS in the "Patron Record" section.
 *
 * @package Locum
 * @category Locum Connector
 * @author John Blyberg
 */

/**
 * The Locum connector class for III Mil. 2006.
 * Note the naming convention that is required by Locum: locum _ vendor _ version
 * Also, from a philisophical standpoint, I try to use only public-facing services here,
 * with the exception of the III patron API, which is a product we bought, along with 
 * practically every other III customer.
 */
class locum_iii_2007 {

  public $locum_config;

  /**
   * Prep this class
   */
  public function __construct() {
    require_once('tools/phpQuery/phpQuery/phpQuery.php'); // jQuery-like screen scraping library
    require_once('patronapi.php');
  }
  
  //TODO: delete me
  public function get_all_marcs($xrecord){
    static $marcs = array();
    static $count=0;
    if( ($count++ % 500) == 0){ // every so often, store our results (incase it dies)
      variable_set('marc_subs', $marcs);
    }
    foreach($xrecord->VARFLD as $varfld){
      foreach($varfld->MARCSUBFLD as $subfield){
        $tag = (string)$varfld->HEADER->TAG;
        $marc = (string)$varfld->MARCINFO->MARCTAG;
        $sub = (string)$subfield->SUBFIELDINDICATOR;
        if( !(isset($marcs[$tag])) ) 
          $marcs[$tag]=array();
        if( !(isset($marcs[$tag][$marc])) )
          $marcs[$tag][$marc]=array();
        $marcs[$tag][$marc][$sub] = $sub;
      }
    }
  }
  /**
   * Grabs bib info from XRECORD and returns it in a Locum-ready array.
   *
   * @param int $bnum Bib number to scrape
   * @param boolean $skip_cover Forget about grabbing cover images.  Default: FALSE
   * @return boolean|array Will either return a Locum-ready array or FALSE
   */
  public function scrape_bib($bnum, $skip_cover = FALSE) {

    $iii_server_info = $this->iii_server_info();

    $bnum = trim($bnum);

//    $bnum=1008699; //drugs of choice
//    $bnum=1330465; // Clinical neuroanatomy
//    $bnum=1186294; // gut
//    $bnum=1340003; // Influenza A virus: A role for the RNA polymerase in viral particle assembly
    $xrecord = @simplexml_load_file($iii_server_info['nosslurl'] . '/xrecord=b' . $bnum);

    // If there is no record, return false (weeded or non-existent)
    if ($xrecord->NULLRECORD) {
      return FALSE;
    }
    if ($xrecord->VARFLD) {
      if (!$xrecord->VARFLD[0]->MARCINFO) { 
        return FALSE;
      }
    } else {
      return 'skip';
    }
    
    //TODO: delete me
    //    $this->get_all_marcs($xrecord);

    $bib_info_record = $xrecord->RECORDINFO;
    $bib_info_local = $xrecord->TYPEINFO->BIBLIOGRAPHIC->FIXFLD;
    $bib_info_marc = $this->parse_marc_subfields($xrecord->VARFLD);
    unset($xrecord);

    // Process record information
    $bib['bnum'] = $bnum;
    $bib['bib_created'] = $this->fixdate($bib_info_record->CREATEDATE);
    $bib['bib_lastupdate'] = $this->fixdate($bib_info_record->LASTUPDATEDATE);
    $bib['bib_prevupdate'] = $this->fixdate($bib_info_record->PREVUPDATEDATE);
    $bib['bib_revs'] = (int) $bib_info_record->REVISIONS;

    // Process local record data
    foreach ($bib_info_local as $bil_obj) {
      switch (trim($bil_obj->FIXLABEL)) {
        case 'LANG':
          $bib['lang'] = trim($bil_obj->FIXVALUE);
          break;
        case 'LOCATION':
          $bib['loc_code'] = trim($bil_obj->FIXVALUE);
          break;
        case 'MAT TYPE':
          $bib['mat_code'] = trim($bil_obj->FIXVALUE);
          break;
        case 'BCODE3':
          $bib['suppress'] = in_array(trim($bil_obj->FIXVALUE), locum::csv_parser($this->locum_config['ils_custom_config']['suppress_codes'])) ? 1 : 0;
          break;

      }
    }

    // Process MARC fields
    $marc = array();
    foreach($this->locum_config['marc_codes'] as $key=>$value)
      $marc[$key] = locum::csv_parser($value);
      
    // Process Author information
    $bib['author'] = self::_prepare_marc_single( $bib_info_marc, $marc['author'], $marc['author_sub'] );

    // Additional author information
    $bib['addl_author'] = self::_prepare_marc_single( $bib_info_marc, $marc['alt_author'], $marc['alt_author_sub'] );

    // Title information
    $title = self::_prepare_marc_single($bib_info_marc, $marc['title'], $marc['title_sub'] );
    if (substr($title, -1) == '/') { $title = trim(substr($title, 0, -1)); }
    $bib['title'] = trim($title);

    // Title medium information
    $bib['title_medium'] = '';
    $title_medium = self::_prepare_marc_single($bib_info_marc, $marc['title_medium'], $marc['title_medium_sub'] );
    if ($title_medium) {
      if (preg_match('/\[(.*?)\]/', $title_medium, $medium_match)) {
        $bib['title_medium'] = $medium_match[1];
      }
    }
    
    // Edition information
    $bib['edition'] = trim(self::_prepare_marc_single($bib_info_marc, $marc['edition'], $marc['edition_sub'] ));

    // Series information
    $bib['series'] = self::_prepare_marc_single($bib_info_marc, $marc['series'], $marc['series_sub'] );

    // Call number
    $callnum = '';
    // Journal callnum = 096a,b ; Book callnum = 050a,b, 90a,b
    foreach($marc['call_#'] as $call_marc_code){
      $callnum_arr = self::prepare_marc_values($bib_info_marc[$call_marc_code], $marc['call_#_sub']);
      if (is_array($callnum_arr) && count($callnum_arr)) {
        foreach ($callnum_arr as $cn_sub) {
          $callnum .= $cn_sub . ' ';
        }
        break;
      }
    }
    $bib['callnum'] = trim($callnum);
  
    $bib['pub_info'] = self::_prepare_marc_single($bib_info_marc, $marc['imprint'], $marc['imprint_sub'] );

    
    // Publication year
    $bib['pub_year'] = '';
    $pub_year = self::_prepare_marc_single($bib_info_marc, $marc['pub_year'], $marc['pub_year_sub'] );
    $c_arr = explode(',', $pub_year);
    $c_key = count($c_arr) - 1;
    $bib['pub_year'] = substr(ereg_replace("[^0-9]", '', $c_arr[$c_key]), -4);

    // ISBN / Std. number
    $bib['stdnum'] = self::_prepare_marc_single($bib_info_marc, $marc['isbn_issn'], $marc['isbn_issn_sub']);
    
    // UPC
    $bib['upc'] = self::_prepare_marc_single($bib_info_marc, $marc['upc'], $marc['upc_sub']);
    if($bib['upc'] == '') { $bib['upc'] = "000000000000"; }

    // LCCN (LC Card#)
    $bib['lccn'] = self::_prepare_marc_single($bib_info_marc, $marc['lc_card_#'], $marc['lc_card_#_subs']);
    
    // Download Link (if it's a downloadable)
    $bib['download_link'] = self::_prepare_marc_array($bib_info_marc, $marc['download_link'], $marc['download_link_sub'], ' ', true);

    // Description
    $bib['descr'] = self::_prepare_marc_single($bib_info_marc, $marc['descript'], $marc['descript_sub']);

    // Notes
    $bib['notes'] = self::_prepare_marc_array($bib_info_marc, $marc['note'], $marc['note_sub']);

    // Subject headings
    $subj = self::_prepare_marc_array($bib_info_marc, $marc['subject'], $marc['subject_sub'], '--');
    $bib['subjects'] = unserialize($subj); // see why: locum-server.php[109]
    
    /*-------- Additional university library items ----- */

    $bib['continues'] = self::_prepare_marc_array( $bib_info_marc, $marc['continues'], $marc['continues_sub'] );
    $bib['link'] = self::_prepare_marc_single( $bib_info_marc, $marc['link'], $marc['link_sub'] );
    $bib['alt_title'] = self::_prepare_marc_array( $bib_info_marc, $marc['alt_title'], $marc['alt_title_sub'] );
    $bib['related_work'] = self::_prepare_marc_array( $bib_info_marc, $marc['related_wrk'], $marc['related_wrk_sub'] ); 
    $bib['local_note'] = self::_prepare_marc_array( $bib_info_marc, $marc['local_note'], $marc['local_note_sub'] );
    $bib['oclc'] = self::_prepare_marc_single( $bib_info_marc, $marc['oclc'], $marc['oclc_sub'] );
    
    // left over: marc, doc_number, holdings, cont_d_by, __note__, hldgs_stat
    $bib['doc_number'] = self::_prepare_marc_single( $bib_info_marc, $marc['doc_number'], $marc['doc_number_sub'] );
    
    //TODO: store holdings as an array rather than string, so we can parse later.
    // something like [ 0=>[ a=>'UCSF', b=>'jnrl', j=>'W1 GU 821', 3=>'v.1(1960)-v.53(2004)' ] ]  
    $bib['holdings'] = self::_prepare_marc_array( $bib_info_marc, $marc['holdings'], $marc['holdings_sub'], ' ', true);
    $bib['cont_d_by'] = self::_prepare_marc_array( $bib_info_marc, $marc['cont_d_by'], $marc['cont_d_by_sub'] );
    $bib['__note__'] = self::_prepare_marc_array( $bib_info_marc, $marc['__note__'], $marc['__note___sub'] );
    $bib['hldgs_stat'] = self::_prepare_marc_array( $bib_info_marc, $marc['hldgs_stat'], $marc['hldgs_stat_sub'] );

    /*-------- /Additional university library items ----- */
    
  // Grab the cover image URL if we're doing that
    $bib['cover_img'] = '';
    if ($skip_cover != TRUE) {
      static $locum;
      $locum = new locum_server;
      if ($bib['stdnum']) { $bib['cover_img'] = $locum->get_cover_img($bib['stdnum']); }
      if ($bib['oclc'] && !$bib['cover_img']) {
      	
      	// Fall back on worldcat, we're using phpQuery library so no regex required
//        phpQuery::browserGet('http://ucsf.worldcat.org/oclc/'.$bib['oclc'], array($this, 'get_worldcat_cover_img'));
        // see comment on instance var for explaination
//        $bib['cover_img'] = $this->bibItems['cover_img']; 
      }
    }

    //TODO: remove me (find the largest items, so i can determind the type of database column for each)
    static $largest_marc = array();
    foreach($bib as $bkey => $bval){
      $length = strlen($bval);
      if(!$largest_marc[$bkey]) {$largest_marc[$bkey] = $length;}
      elseif ($largest_marc[$bkey]<$length) {$largest_marc[$bkey]=$length;}
    }
    static $count=0;
    if($count++ >= 500){
//      variable_set('largest_marc', $largest_marc);
    }
    
    unset($bib_info_marc);
    return $bib;
  }
  
  /** 
  * using phpQuery here to retrieve the cover image.  We need an instance variable because this function returns
  * a Zend_Http_Client rather than a phpQuery object (aka, return $browser['image.cover']->attr('src') does nothing)
  */
  public $bibItems;
  public function get_worldcat_cover_img($browser) {
  	$this->bibItems['cover_img'] = $browser['img.cover']->attr('src');
  }
  
  /**
   * Returns either the value at the MARC code, or '' if nothing
   * @param array $bib_info_marc
   * @param array $tags MARC codes to try.  If multiple codes, it will try them from left to right & return the first value it finds
   * @param array $subfields MARC subfields
   */
  protected function _prepare_marc_single($bib_info_marc, $tags, $subfields, $delimiter = ' ', $first_val = TRUE){
    foreach($tags as $tag){
      if(isset($bib_info_marc[$tag])){
        $value = self::prepare_marc_values($bib_info_marc[$tag], $subfields, $delimiter);
        if(count($value)>0) {
          if($first_val) { return array_shift($value); }
          // otherwise, we want the whole array
          else { return serialize($value); }
        } // else try the next one
      }
    }
    return '';
  }
  
  /**
   * Returns either the aggregate values at the MARC codes, or '' if nothing
   * @param array $bib_info_marc
   * @param array $tags MARC codes to aggregate
   * @param array $subfields MARC subfields
   */
  protected function _prepare_marc_array($bib_info_marc, $tags, $subfields, $delimiter = ' ', $retain_subfields = FALSE){
    $arr = array();
    foreach ($tags as $tag) {
      if(isset($bib_info_marc[$tag])){
        $values = self::prepare_marc_values($bib_info_marc[$tag], $subfields, $delimiter, $retain_subfields);
        if (is_array($values)) {
          foreach ($values as $key=>$value) {
            $arr[$key] = $value;
          }
        }
      }
    }
    if (count($arr)) { return serialize($arr); }
    else { return ''; }
  }

  /**
   * Parses item status for a particular bib item.
   *
   * @param string $bnum Bib number to query
   * @return array Returns a Locum-ready availability array
   */
  public function item_status($bnum) {
    
    $iii_server_info = $this->iii_server_info();
    $avail_token = locum::csv_parser($this->locum_config['iii_custom_config']['iii_available_token']);
    $default_age = $this->locum_config['iii_custom_config']['default_age'];
    $default_branch = $this->locum_config['iii_custom_config']['default_branch'];
    $loc_codes_flipped = array_flip($this->locum_config['iii_location_codes']);
    $bnum = trim($bnum);

    // Grab Hold Numbers
    $url = $iii_server_info['nosslurl'] . '/search~24/.b' . $bnum . '/.b' . $bnum . '/1,1,1,B/marc~' . $bnum . '&FF=&1,0,';
    $hold_page_raw = utf8_encode(file_get_contents($url));

    // Reserves Regex
    $regex_r = '/(?<hold_num>\d+) hold/';
    preg_match($regex_r, $hold_page_raw, $match_r);
    $avail_array['holds'] = $match_r['hold_num'] ? $match_r['hold_num'] : 0;

    // Order Entry Regex
    $avail_array['on_order'] = 0;
    $regex_o = '%bibOrderEntry(.*?)td(.*?)>(.*?)<%s';
    preg_match_all($regex_o, $hold_page_raw, $match_o);
    foreach($match_o[3] as $order) {
      $order_txt = trim($order);
      preg_match('%^(.*?)cop%s', $order_txt, $order_count);
      $avail_array['on_order'] = $avail_array['on_order'] + (int) trim($order_count[1]);
      $avail_array['orders'][] = $order_txt;
    }
    
    // Holdings
    //TODO: shouldn't be screen-scraping these, they're in the marc.  temporary solution
    $doc = phpQuery::newDocumentHTML($hold_page_raw);
    $avail_array['holdings_html'] = $doc->find('table.bibHoldings')->html();

    $url = $iii_server_info['nosslurl'] . '/search~24/.b' . $bnum . '/.b' . $bnum . '/1,1,1,B/holdings~' . $bnum . '&FF=&1,0,';
    $avail_page_raw = utf8_encode(file_get_contents($url));

    /*<tr  class="bibItemsEntry">
		<td width="30%" ><!-- field 1 -->&nbsp;<a href="http://www.library.ucsf.edu/locations/parnassus/floorplan/materials">Parnassus: Books - 4th Floor</a> 
		</td>
		<td width="45%" ><!-- field C -->&nbsp;<a href="/search~S0?/cRC905+.I5+1967/crc++905+i5+1967/-3,-1,,B/browse">RC905 .I5 1967</a> <!-- field v --><!-- field # --></td>
		<td width="25%" ><!-- field % -->&nbsp;NOT CHCKD OUT </td></tr>*/
    
		
    // Holdings Regex
//    $regex_h = '|field 1 -->&nbsp;(.*?)</td>(.*?)browse">(.*?)</a>(.*?)field % -->&nbsp;(.*?)</td>|s';
    $regex_h = '|field 1 -->&nbsp;(.*?)</td>.*?field C -->&nbsp;(.*?)</td>.*?field % -->&nbsp;(.*?)</td>|s';
    preg_match_all($regex_h, $avail_page_raw, $matches);

    foreach ($matches[1] as $i => $location) {
      // put the item details in the array
      $location = trim($location);
      $loc_code = $loc_codes_flipped[$location];
      $call = str_replace("'", "&apos;", trim($matches[2][$i]));
      $status = trim($matches[3][$i]);
      $age = $default_age;
      $branch = $default_branch;
      
      if (in_array($status, $avail_token)) { 
        $avail = 1;
        $due_date = 0;
      } else { 
        $avail = 0;
        if (preg_match('/DUE/i', $status)) {
          $due_arr = explode(' ', trim($status));
          $due_date_arr = explode('-', $due_arr[1]);
          $due_date = mktime(0, 0, 0, $due_date_arr[0], $due_date_arr[1], (2000 + (int) $due_date_arr[2]));
        } else {
          $due_date = 0;
        }
      }
      
      // Determine age from location
      if (count($this->locum_config['iii_record_ages'])) {
        foreach ($this->locum_config['iii_record_ages'] as $item_age => $match_crit) {
          if (preg_match('/^\//', $match_crit)) {
            if (preg_match($match_crit, $loc_code)) { $age = $item_age; }
          } else {
            if (in_array($loc_code, locum::csv_parser($match_crit))) { $age = $item_age; }
          }
        }
      }
      
      // Determine branch from location
      if (count($this->locum_config['branch_assignments'])) {
        foreach ($this->locum_config['branch_assignments'] as $branch_code => $match_crit) {
          if (preg_match('/^\//', $match_crit)) {
            if (preg_match($match_crit, $loc_code)) { $branch = $branch_code; }
          } else {
            if (in_array($loc_code, locum::csv_parser($match_crit))) { $branch = $branch_code; }
          }
        }
      }
      
      $avail_array['items'][] = array(
        'location' => $location,
        'loc_code' => $loc_code,
        'callnum' => $call,
        'statusmsg' => $status,
        'due' => $due_date,
        'avail' => $avail,
        'age' => $age,
        'branch' => $branch,
      );
    }
    
    return $avail_array;

  }
  
  /**
   * Returns an array of patron information
   *
   * @param string $pid Patron barcode number or record number
   * @return boolean|array Array of patron information or FALSE if login fails
   */
  public function patron_info($pid) {
    $papi = new iii_patronapi;
    $iii_server_info = $this->iii_server_info();
    $papi->iiiserver = $iii_server_info['server'];
    $papi_data = $papi->get_patronapi_data($pid);

    if (!$papi_data) { return FALSE; }

    $pdata['pnum'] = $papi_data['RECORDNUM'];
    $pdata['cardnum'] = $papi_data['PBARCODE'];
    $pdata['checkouts'] = $papi_data['CURCHKOUT'];
    $pdata['homelib'] = $papi_data['HOMELIBR'];
    $pdata['balance'] = preg_replace('/[^0-9.]/', '', $papi_data['MONEYOWED']);
    $pdata['expires'] = $papi_data['EXPDATE'] ? $this->date_to_timestamp($papi_data['EXPDATE'], 2000) : NULL;
    $pdata['name'] = $papi_data['PATRNNAME'];
    $pdata['address'] = preg_replace('%\$%s', "\n", $papi_data['ADDRESS']);
    $pdata['tel1'] = $papi_data['TELEPHONE'];
    if ($papi_data['TELEPHONE2']) { $pdata['tel2'] = $papi_data['TELEPHONE2']; }
    $pdata['email'] = $papi_data['EMAILADDR'];

    return $pdata;
  }

  /**
   * Returns an array of patron checkouts
   *
   * @param string $cardnum Patron barcode/card number
   * @param string $pin Patron pin/password
   * @return boolean|array Array of patron checkouts or FALSE if login fails
   */
  public function patron_checkouts($cardnum, $pin = NULL) {
    $iii = $this->get_tools($cardnum, $pin);
    if ($iii->catalog_login() == FALSE) { return FALSE; }
    return $iii->get_patron_items();
  }

  /**
   * Returns an array of patron checkouts for history
   *
   * @param string $cardnum Patron barcode/card number
   * @param string $pin Patron pin/password
   * @return boolean|array Array of patron checkouts or FALSE if login fails
   */
  public function patron_checkout_history($cardnum, $pin = NULL) {
    $iii = $this->get_tools($cardnum, $pin, $action);
    if ($iii->catalog_login() == FALSE) { return FALSE; }
    $result = $iii ? $iii->get_patron_history_items($action) : FALSE;
    $i = 0;
    foreach ($result as $item) {
      $hist_result[$i]['varname'] = $item['varname'];
      $hist_result[$i]['bnum'] = $item['bnum'];
      $hist_result[$i]['date'] = $this->date_to_timestamp($item['date']);
      $i++;
    }
    return $hist_result;
  }
  
  /**
   * Opts patron in or out of checkout history
   *
   * @param string $cardnum Patron barcode/card number
   * @param string $pin Patron pin/password
   * @return boolean|array Array of patron checkouts or FALSE if login fails
   */
  public function patron_checkout_history_toggle($cardnum, $pin = NULL, $action) {
    $iii = $this->get_tools($cardnum, $pin);
    if ($iii->catalog_login() == FALSE) { return FALSE; }
    $result = $iii ? $iii->toggle_patron_history($action) : FALSE;
    return $result;
  }
  
  /**
   * Returns an array of patron holds
   *
   * @param string $cardnum Patron barcode/card number
   * @param string $pin Patron pin/password
   * @return boolean|array Array of patron holds or FALSE if login fails
   */
  public function patron_holds($cardnum, $pin = NULL) {
    $iii = $this->get_tools($cardnum, $pin);
    if ($iii->catalog_login() == FALSE) { return FALSE; }
    return $iii->get_patron_holds();
  }
  
  /**
   * Renews items and returns the renewal result
   *
   * @param string $cardnum Patron barcode/card number
   * @param string $pin Patron pin/password
   * @param array Array of varname => item numbers to be renewed, or NULL for everything.
   * @return boolean|array Array of item renewal statuses or FALSE if it cannot renew for some reason
   */
  public function renew_items($cardnum, $pin = NULL, $items = NULL) {
    $iii = $this->get_tools($cardnum, $pin);
    if ($iii->catalog_login() == FALSE) { return FALSE; }
    return $iii->renew_material($items);
  }

  /**
   * Updates holds/reserves
   *
   * @param string $cardnum Patron barcode/card number
   * @param string $pin Patron pin/password
   * @param array $cancelholds Array of varname => item/bib numbers to be cancelled, or NULL for everything.
   * @param array $holdfreezes_to_update Array of updated holds freezes.
   * @param array $pickup_locations Array of pickup location changes.
   * @return boolean TRUE or FALSE if it cannot cancel for some reason
   */
  public function update_holds($cardnum, $pin = NULL, $cancelholds = array(), $holdfreezes_to_update = array(), $pickup_locations = array()) {
    $iii = $this->get_tools($cardnum, $pin);
    if ($iii->catalog_login() == FALSE) { return FALSE; }
    $iii->update_holds($cancelholds, $holdfreezes_to_update, $pickup_locations);
    return TRUE;
  }

  /**
   * Places holds
   *
   * @param string $cardnum Patron barcode/card number
   * @param string $bnum Bib item record number to place a hold on
   * @param string $inum Item number to place a hold on if required (presented as $varname in locum)
   * @param string $pin Patron pin/password
   * @param string $pickup_loc Pickup location value
   * @return boolean TRUE or FALSE if it cannot place the hold for some reason
   */
  public function place_hold($cardnum, $bnum, $inum = NULL, $pin = NULL, $pickup_loc = NULL) {
    $iii = $this->get_tools($cardnum, $pin);
    if ($iii->catalog_login() == FALSE) { return FALSE; }
    return $iii->place_hold($bnum, $inum, $pickup_loc);
  }
  
  /**
   * Returns an array of patron fines
   *
   * @param string $cardnum Patron barcode/card number
   * @param string $pin Patron pin/password
   * @return boolean|array Array of patron fines or FALSE if login fails
   */
  public function patron_fines($cardnum, $pin = NULL) {
    $iii = $this->get_tools($cardnum, $pin);
    if ($iii->catalog_login() == FALSE) { return FALSE; }
    $fines = $iii->get_patron_fines();
    return $fines['items'];
  }
  
  /**
   * Pays patron fines.
   * @param string $cardnum Patron barcode/card number
   * @param string $pin Patron pin/password
   * @param array payment_details
   * @return array Payment result
   */
  public function pay_patron_fines($cardnum, $pin = NULL, $payment_details) {
    $iii = $this->get_tools($cardnum, $pin);
    if ($iii->catalog_login() == FALSE) { return FALSE; }
    $iii_payment_details['varnames'] = $payment_details['varnames'];
    $iii_payment_details['amount'] = '$' . number_format($payment_details['total'], 2);
    $iii_payment_details['name'] = $payment_details['name'];
    $iii_payment_details['address1'] = $payment_details['address1'];
    $iii_payment_details['city'] = $payment_details['city'];
    $iii_payment_details['state'] = $payment_details['state'];
    $iii_payment_details['zip'] = $payment_details['zip'];
    $iii_payment_details['email'] = $payment_details['email'];
    $iii_payment_details['ccnum'] = $payment_details['ccnum'];
    $iii_payment_details['ccexp_month'] = $payment_details['ccexpmonth'];
    $iii_payment_details['ccexp_year'] = $payment_details['ccexpyear'];
    $iii_payment_details['cvv'] = $payment_details['ccseccode'];

    $payment_result = $iii->pay_fine($iii_payment_details);
    return $payment_result;
  }
  
  /**
   * This is an internal function used to parse MARC values.
   * This function is called by scrape_bib()
   *
   * @param array $value_arr SimpleXML values from XRECORD for that MARC item
   * @param array $subfields An array of MARC subfields to parse
   * @param string $delimiter Delimiter to use for storage and indexing purposes.  A space seems to work fine
   * @return array An array of processed MARC values
   */
  public function prepare_marc_values($value_arr, $subfields, $delimiter = ' ', $retain_subfields = FALSE) {
    $result = array();
    // Repeatable values can be returned as an array or a serialized value
    foreach ($subfields as $subfield) {
      if (isset($value_arr[$subfield]) && is_array($value_arr[$subfield])) {

        foreach ($value_arr[$subfield] as $subkey => $subvalue) {

          if (is_array($subvalue)) {
            foreach ($subvalue as $sub_subvalue) {
              if ($i[$subkey]) { $pad[$subkey] = $delimiter; }
              $sv_tmp = trim($sub_subvalue);
              $matches = array();
              preg_match_all('/\{u[0-9a-fA-F][0-9a-fA-F][0-9a-fA-F][0-9a-fA-F]\}/', $sv_tmp, $matches);
              foreach ($matches[0] as $match_string) {
                $code = hexdec($match_string);
                $character = html_entity_decode("&#$code;", ENT_NOQUOTES, 'UTF-8');
                $sv_tmp = str_replace($match_string, $character, $sv_tmp);
              }
              if (trim($sub_subvalue)) { $marc_values[$subkey] .= $pad[$subkey] . $sv_tmp; }
              $i[$subkey] = 1;
            }
          } else {
            if ($i[$subkey]) { $pad[$subkey] = $delimiter; }
            
            // Process unicode for diacritics
            $sv_tmp = trim($subvalue);
            $matches = array();
            preg_match_all('/\{u[0-9a-fA-F][0-9a-fA-F][0-9a-fA-F][0-9a-fA-F]\}/', $sv_tmp, $matches);
            foreach ($matches[0] as $match_string) {
              $code = hexdec($match_string);
              $character = html_entity_decode("&#$code;", ENT_NOQUOTES, 'UTF-8');
              $sv_tmp = str_replace($match_string, $character, $sv_tmp);
            }

            if (trim($subvalue)) { $marc_values[$subkey] .= $pad[$subkey] . $sv_tmp; }
            $i[$subkey] = 1;
          }
          
          // note: putting it up here because we want $result[$subfield], like array(a=>..., 3=>...), so can work with in tpl.php's
          if($retain_subfields){
            $result[$subkey][$subfield] = $sv_tmp;
          }
        }  
      }

      
      
    }

    if (!$retain_subfields && is_array($marc_values)) {
      foreach ($marc_values as $mv) {
        $result[] = $mv;
      }
    }
    return $result;
  }

  /**
   * Does the initial job of creating an array out of the SimpleXML content from XRECORD.
   * This function is called by scrape_bib() and the data is ultimately used by prepare_marc_values()
   *
   * @param array $bib_info_marc VARFLD value tree from XRECORD via SimpleXML
   * @return array A normalized array of marc and subfield info
   */
  public function parse_marc_subfields($bib_info_marc) {
    $bim_item = 0;
    foreach ($bib_info_marc as $bim_obj) {
      // We need to treat MARC tag numbers as a string, or things would be a mess
      $marc_num = (string) $bim_obj->MARCINFO->MARCTAG;
      if (count($bim_obj->MARCSUBFLD) == 1) {
        // Only one subfield value
        $subfld = get_object_vars($bim_obj->MARCSUBFLD);
        $marc_sub[$marc_num][trim($subfld['SUBFIELDINDICATOR'])][$bim_item] = trim($subfld['SUBFIELDDATA']);
      } else if (count($bim_obj->MARCSUBFLD) > 1) {
        // Multiple subfield values
        for ($i = 0; $i < count($bim_obj->MARCSUBFLD); $i++) {
          $subfld = get_object_vars($bim_obj->MARCSUBFLD[$i]);
          $marc_sub[$marc_num][trim($subfld['SUBFIELDINDICATOR'])][$bim_item][] = trim($subfld['SUBFIELDDATA']);
        }
      } else if ($bim_obj->MARCFIXDATA) {// 0 MARCSUBFLDs, as in OCLC# (http://ucsfcat.ucsf.edu:2082/xrecord=b1209053)
        // TODO: Because MARCFIXDATA don't contain subfields, it is technically incorrect to assign it to 'a'
        // however, it's the most convenient because of how the rest of the code works, so fix if it bothers you
        $marc_sub[$marc_num]['a'][$bim_item] = (string) $bim_obj->MARCFIXDATA;
      }
      $bim_item++;
    }

    return $marc_sub;
  }

  /**
   * Fixes a non-standard date format.
   *
   * @param string $olddate Date string in MM-DD-YY format
   * @param string Date string in YYYY-MM-DD format
   */
  public function fixdate($olddate) {
    return date('Y-m-d', $this->date_to_timestamp($olddate));
  }
  
  /**
   * Converts MM-DD-YY to unix timestamp
   *
   * @param string $date_orig Original date in MM-DD-YY format
   * @param int Optional century to use as a baseline.  Fix for III's Y2K issues.
   * @return timestamp
   */
  public function date_to_timestamp($date_orig, $default_century = NULL) {
    $date_arr = explode('-', trim($date_orig));
    if (strlen(trim($date_arr[2])) == 2) {
      if ($default_century) {
        $year = $default_century + (int) trim($date_arr[2]);
      } else {
        $year = $default_century + (int) trim($date_arr[2]);
        if (date('Y') < $year) { $year = 1900 + (int) trim($date_arr[2]); }
      }
    } else {
      $year = trim($date_arr[2]);
    }
    $time = mktime(0, 0, 0, $date_arr[0], $date_arr[1], $year);
    return $time;
  }

  /**
   * Instantiates III tools class and returns a usable object.
   */
  private function get_tools($cardnum, $pin) {
    require_once('iiitools_2007.php');
    $iii = new iiitools;
    $iii->set_iiiserver($this->iii_server_info());
    $iii->set_cardnum($cardnum);
    $iii->set_pin($pin);
    return $iii;
  }

  private function iii_server_info() {
    $server_select = strtolower(trim($this->locum_config['ils_config']['server_select']));
    $iii_server_info['server'] = $this->locum_config['ils_config']['ils_server'];
    $iii_server_info['nosslport'] = $this->locum_config['ils_config']['ils_' . $server_select . '_port'];
    $iii_server_info['nosslurl'] = 'http://' . $iii_server_info['server'] . ':' . $iii_server_info['nosslport'];
    $iii_server_info['sslport'] = $this->locum_config['ils_config']['ils_' . $server_select . '_port_ssl'];
    $iii_server_info['sslurl'] = 'https://' . $iii_server_info['server'] . ':' . $iii_server_info['sslport'];
    return $iii_server_info;
  }















}
