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
    require_once('patronapi.php');
  }

  /**
   * Grabs bib info from XRECORD and returns it in a Locum-ready array.
   *
   * @param int $bnum Bib number to scrape
   * @param boolean $skip_cover Forget about grabbing cover images.  Default: FALSE
   * @return boolean|array Will either return a Locum-ready array or FALSE
   */
  public function scrape_bib($bnum, $skip_cover = FALSE) {

    $iii_server_info = self::iii_server_info();

    $bnum = trim($bnum);

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

    $bib_info_record = $xrecord->RECORDINFO;
    $bib_info_local = $xrecord->TYPEINFO->BIBLIOGRAPHIC->FIXFLD;
    $bib_info_marc = self::parse_marc_subfields($xrecord->VARFLD);
    unset($xrecord);

    // Process record information
    $bib['bnum'] = $bnum;
    $bib['bib_created'] = self::fixdate($bib_info_record->CREATEDATE);
    $bib['bib_lastupdate'] = self::fixdate($bib_info_record->LASTUPDATEDATE);
    $bib['bib_prevupdate'] = self::fixdate($bib_info_record->PREVUPDATEDATE);
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

    // Process Author information
    $bib['author'] = '';
    $author_arr = self::prepare_marc_values($bib_info_marc['100'], array('a','b','c','d'));
    $bib['author'] = $author_arr[0];

    // In no author info, we'll go for the 110 field
    if (!$bib['author']) {
      $author_110 = self::prepare_marc_values($bib_info_marc['110'], array('a'));
      $bib['author'] = $author_110[0];
    }

    // Additional author information
    $bib['addl_author'] = '';
    $addl_author = self::prepare_marc_values($bib_info_marc['700'], array('a','b','c','d'));
    if (is_array($addl_author)) {
      $bib['addl_author'] = serialize($addl_author);
    }

    // In no additional author info, we'll go for the 710 field
    if (!$bib['addl_author']) {
      $author_710 = self::prepare_marc_values($bib_info_marc['710'], array('a'));
      if (is_array($author_710)) {
        $bib['addl_author'] = serialize($author_710);
      }
    }

    // Title information
    $bib['title'] = '';
    $title = self::prepare_marc_values($bib_info_marc['245'], array('a','b'));
    if (substr($title[0], -1) == '/') { $title[0] = trim(substr($title[0], 0, -1)); }
    $bib['title'] = trim($title[0]);

    // Title medium information
    $bib['title_medium'] = '';
    $title_medium = self::prepare_marc_values($bib_info_marc['245'], array('h'));
    if ($title_medium[0]) {
      if (preg_match('/\[(.*?)\]/', $title_medium[0], $medium_match)) {
        $bib['title_medium'] = $medium_match[1];
      }
    }
    
    // Edition information
    $bib['edition'] = '';
    $edition = self::prepare_marc_values($bib_info_marc['250'], array('a'));
    $bib['edition'] = trim($edition[0]);

    // Series information
    $bib['series'] = '';
    $series = self::prepare_marc_values($bib_info_marc['490'], array('a','v'));
    if (!$series[0]) { $series = self::prepare_marc_values($bib_info_marc['440'], array('a','v')); }
    if (!$series[0]) { $series = self::prepare_marc_values($bib_info_marc['400'], array('a','v')); }
    if (!$series[0]) { $series = self::prepare_marc_values($bib_info_marc['410'], array('a','v')); }
    if (!$series[0]) { $series = self::prepare_marc_values($bib_info_marc['730'], array('a','v')); }
    if (!$series[0]) { $series = self::prepare_marc_values($bib_info_marc['800'], array('a','v')); }
    if (!$series[0]) { $series = self::prepare_marc_values($bib_info_marc['810'], array('a','v')); }
    if (!$series[0]) { $series = self::prepare_marc_values($bib_info_marc['830'], array('a','v')); }
    $bib['series'] = $series[0];

    // Call number
    $callnum = '';
    $callnum_arr = self::prepare_marc_values($bib_info_marc['099'], array('a'));
    if (is_array($callnum_arr) && count($callnum_arr)) {
      foreach ($callnum_arr as $cn_sub) {
        $callnum .= $cn_sub . ' ';
      }
    }
    $bib['callnum'] = trim($callnum);
  
    // Publication information
    $bib['pub_info'] = '';
    $pub_info = self::prepare_marc_values($bib_info_marc['260'], array('a','b','c'));
    $bib['pub_info'] = $pub_info[0];

    // Publication year
    $bib['pub_year'] = '';
    $pub_year = self::prepare_marc_values($bib_info_marc['260'], array('c'));
    $c_arr = explode(',', $pub_year[0]);
    $c_key = count($c_arr) - 1;
    $bib['pub_year'] = substr(ereg_replace("[^0-9]", '', $c_arr[$c_key]), -4);

    // ISBN / Std. number
    $bib['stdnum'] = '';
    $stdnum = self::prepare_marc_values($bib_info_marc['020'], array('a'));
    $bib['stdnum'] = $stdnum[0];
    
    // UPC
    $bib['upc'] = '';
    $upc = self::prepare_marc_values($bib_info_marc['024'], array('a'));
    $bib['upc'] = $upc[0];
    if($bib['upc'] == '') { $bib['upc'] = "000000000000"; }

    // Grab the cover image URL if we're doing that
    $bib['cover_img'] = '';
    if ($skip_cover != TRUE) {
      if ($bib['stdnum']) { $bib['cover_img'] = locum_server::get_cover_img($bib['stdnum']); }
    }

    // LCCN
    $bib['lccn'] = '';
    $lccn = self::prepare_marc_values($bib_info_marc['010'], array('a'));
    $bib['lccn'] = $lccn[0];
    
    // Download Link (if it's a downloadable)
    $bib['download_link'] = '';
    $dl_link = self::prepare_marc_values($bib_info_marc['856'], array('u'));
    $bib['download_link'] = $dl_link[0];

    // Description
    $bib['descr'] = '';
    $descr = self::prepare_marc_values($bib_info_marc['300'], array('a','b','c'));
    $bib['descr'] = $descr[0];

    // Notes
    $notes = array();
    $bib['notes'] = '';
    $notes_tags = array('500','505','511','520');
    foreach ($notes_tags as $notes_tag) {
      $notes_arr = self::prepare_marc_values($bib_info_marc[$notes_tag], array('a'));
      if (is_array($notes_arr)) {
        foreach ($notes_arr as $notes_arr_val) {
          array_push($notes, $notes_arr_val);
        }
      }
    }
    if (count($notes)) { $bib['notes'] = serialize($notes); }

    // Subject headings
    $subjects = array();
    $subj_tags = array(
      '600', '610', '611', '630', '650', '651', 
      '653', '654', '655', '656', '657', '658', 
      '690', '691', '692', '693', '694', '695',
      '696', '697', '698', '699'
    );
    foreach ($subj_tags as $subj_tag) {
      $subj_arr = self::prepare_marc_values($bib_info_marc[$subj_tag], array('a','b','c','d','e','v','x','y','z'), ' -- ');
      if (is_array($subj_arr)) {
        foreach ($subj_arr as $subj_arr_val) {
          array_push($subjects, $subj_arr_val);
        }
      }
    }
    $bib['subjects'] = '';
    if (count($subjects)) { $bib['subjects'] = $subjects; }
    
    unset($bib_info_marc);
    return $bib;
  }

  /**
   * Parses item status for a particular bib item.
   *
   * @param string $bnum Bib number to query
   * @return array Returns a Locum-ready availability array
   */
  public function item_status($bnum) {
    
    $iii_server_info = self::iii_server_info();
    $avail_token = locum::csv_parser($this->locum_config['ils_custom_config']['iii_available_token']);
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

    $url = $iii_server_info['nosslurl'] . '/search~24/.b' . $bnum . '/.b' . $bnum . '/1,1,1,B/holdings~' . $bnum . '&FF=&1,0,';
    $avail_page_raw = utf8_encode(file_get_contents($url));

    // Holdings Regex
    $regex_h = '%field 1 -->&nbsp;(.*?)</td>(.*?)browse">(.*?)</a>(.*?)field \% -->&nbsp;(.*?)</td>%s';
    preg_match_all($regex_h, $avail_page_raw, $matches);

    foreach ($matches[1] as $i => $location) {
      // put the item details in the array
      $location = trim($location);
      $loc_code = $loc_codes_flipped[$location];
      $call = str_replace("'", "&apos;", trim($matches[3][$i]));
      $status = trim($matches[5][$i]);
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
    $iii_server_info = self::iii_server_info();
    $papi->iiiserver = $iii_server_info['server'];
    $papi_data = $papi->get_patronapi_data($pid);

    if (!$papi_data) { return FALSE; }

    $pdata['pnum'] = $papi_data['RECORDNUM'];
    $pdata['cardnum'] = $papi_data['PBARCODE'];
    $pdata['checkouts'] = $papi_data['CURCHKOUT'];
    $pdata['homelib'] = $papi_data['HOMELIBR'];
    $pdata['balance'] = (float) preg_replace('%\$%s', '', $papi_data['MONEYOWED']);
    $pdata['expires'] = $papi_data['EXPDATE'] ? self::date_to_timestamp($papi_data['EXPDATE'], 2000) : NULL;
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
    $iii = $this->get_tools($cardnum, $pin);
    $result = $iii ? $iii->get_patron_history_items() : FALSE;
    return $result;
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
  public function prepare_marc_values($value_arr, $subfields, $delimiter = ' ') {

    // Repeatable values can be returned as an array or a serialized value
    foreach ($subfields as $subfield) {
      if (is_array($value_arr[$subfield])) {

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
        }  
      }    
    }

    if (is_array($marc_values)) {
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
    return date('Y-m-d', self::date_to_timestamp($olddate));
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
    $iii->set_iiiserver(self::iii_server_info());
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
