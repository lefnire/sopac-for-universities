<?php
/**
 * Locum is a software library that abstracts ILS functionality into a
 * catalog discovery layer for use with such things as bolt-on OPACs like
 * SOPAC.
 * @package Locum
 * @category Locum Connector
 * @author John Blyberg
 */

/**
 * This is a standalone class that interacts with the III webpac.
 * It provides a PHP interface to all interactive functions within the III webpac.
 *
 * In order to be actively logged in, you must set either $cardnum or $pnum as well 
 * as $pin, even if your library doesn't use pins.  If that's the case, then $pin
 * can be set to anything.
 */
class iiitools {

  public $iii_server_info;
  public $cardnum;
  public $pnum;
  public $cookie;
  public $patroninfo;
  protected $ch;
  protected $papi;
  protected $pin;

  /**
   * Class constructor.
   * Initializes the requisite variables and classes.
   */
  public function __construct() {
    $this->cookie = self::set_cookie_file();
    $this->papi = new iii_patronapi;
    $this->ch = curl_init();
    // Set all the CURL options
    curl_setopt ($this->ch, CURLOPT_USERAGENT, $agent);
    curl_setopt ($this->ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt ($this->ch, CURLOPT_COOKIEJAR, $this->cookie);
    curl_setopt ($this->ch, CURLOPT_COOKIEFILE, $this->cookie);
    curl_setopt ($this->ch, CURLOPT_COOKIESESSION, TRUE);
    curl_setopt ($this->ch, CURLOPT_HEADER, 1);
    curl_setopt ($this->ch, CURLOPT_TIMEOUT, $curl_timeout);
    curl_setopt ($this->ch, CURLE_OPERATION_TIMEOUTED, 2);
    curl_setopt ($this->ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt ($this->ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt ($this->ch, CURLOPT_AUTOREFERER, 1);
    curl_setopt ($this->ch, CURLOPT_FOLLOWLOCATION, 1);
  }

  /**
   * Class destructor.
   * Logs the process out and deletes the cookie file.
   */
  public function __destruct() {
    self::catalog_logout();
    if (is_file($this->cookie)) {
      curl_close($this->ch);
      unset($this->ch); 
      unlink($this->cookie); 
    }
  }

  /**
   * Sets the cardnumber for the active instansiation
   *
   * @param string $cardnum Library card number
   */
  public function set_cardnum($cardnum) {
    $this->cardnum = $cardnum;
    self::load_patroninfo($cardnum);
    self::set_cookie_file($cardnum, NULL);
    curl_setopt ($this->ch, CURLOPT_COOKIEJAR, $this->cookie);
    curl_setopt ($this->ch, CURLOPT_COOKIEFILE, $this->cookie);
    $this->pnum = $this->patroninfo['RECORDNUM'];
  }

  /**
   * Sets the III server for the active instansiation
   *
   * @param array $iii_server_info
   */
  public function set_iiiserver($iii_server_info) {
    $this->iii_server_info = $iii_server_info;
    $this->papi->iiiserver = $iii_server_info['server'];
  }
  
  /**
   * Sets the pin for the current $pnum or $cardnum within the active instansiation
   *
   * @param string $pin Pin/password
   */
  public function set_pin($pin = 'unused') {
    $this->pin = $pin ? $pin : 'unused';
  }

  /**
   * Populates the patroninfo object with patron info via the Patron API class
   *
   * @param string $pid Patron ID: can be either cardnum or pnum
   */
  public function load_patroninfo($pid) {
    if (!$this->papi->iiiserver) { exit('No servers set'); }
    $this->patroninfo = $this->papi->get_patronapi_data($pid);
  }

  /**
   * Sets the cookie file for the class.
   * Used in the class constructor as well as the login routine.
   *
   * @param string $cardnum Library card number (optional)
   * @param string $pnum Patron ID number (optional)
   */
  public function set_cookie_file($cardnum = NULL, $pnum = NULL) {
    
    $cookie_dir = '/tmp/cookies_iii';
    if (!is_dir($cookie_dir)) {
      if (is_file($cookie_dir)) {
        if (!@unlink($cookie_dir)) { exit('Unable to create cookie directory: ' . $cookie_dir); }
      }
      if (!@mkdir($cookie_dir)) { exit('Unable to create cookie directory: ' . $cookie_dir); }
    }

    if (!$cardnum && !$pnum) {
      $id = rand(1,1000000);
    } else {
      $id = $cardnum ? $cardnum : $pnum;
    }
    $this->cookie = $cookie_dir . '/cookie.txt.' . $id;
  }

  /**
   * Logs the process in to the webcat interface
   *
   * @return boolean TRUE if logged in, FALSE if not
   */
  public function catalog_login() { // TODO add boolean result
    if (!isset($this->patroninfo)) { exit('Patron Info not yet initialized'); }
    if (!$this->pin) { exit('PIN not yet set'); }
    $form_url = "patroninfo/";
    $postvars = 'name=' . $this->patroninfo['PATRNNAME'] . '&code=' . $this->cardnum . '&pin=' . $this->pin;
    return self::my_curl_exec($form_url, $postvars, NULL, NULL, TRUE);
    return TRUE;
  }

  /**
   * Logs the process out of the webcat interface
   */
  public function catalog_logout() {
    $url = "logout/";
    return self::my_curl_exec($form_url);
  }

  /**
   * Returns an array of checked-out items.
   *
   * @param boolean $sort_by_due Sort items by due date (optional)
   * @return array An array of items checked out.
   */
  public function get_patron_items($sort_by_due = TRUE) {
    if ($sort_by_due) {
      $url_suffix = 'patroninfo~S3/' . $this->pnum . '/sorteditems';
    } else {
      $url_suffix = 'patroninfo~S3/' . $this->pnum . '/items';
    }
    $result = self::my_curl_exec($url_suffix);
    return self::parse_patron_items($result['body']);
  }

  /**
   * Returns an array of checked-out items for history.
   *
   * @return array An array of items checked out.
   */
  public function get_patron_history_items() {
    $url_suffix = 'patroninfo~S3/' . $this->pnum . '/readinghistory';
    $result = self::my_curl_exec($url_suffix);
    $result = self::parse_patron_history_items($result['body']);
    return $result;
  }
  /**
   * Parses through the raw return from cURL to formulate the array passed back by get_patron_history_items()
   *
   * @param string $itemlist_raw Raw output from cURL
   * @return array An array of items checked out.
   */
  public function parse_patron_history_items($itemslist_raw) {
    $regex = '%<input type="checkbox" name="(.+?)" />.+?patFuncTitle"><a href="/record=b(.+?)~S3">(.+?)</a>.+?patFuncAuthor">(.+?)</td>.+?patFuncDate">(.+?)</td>.+?"patFuncDetails">(.+?)</td>%s';
    $count = preg_match_all($regex, $itemslist_raw, $rawmatch);
    for ($i=0; $i < $count; $i++) {
      $items[$i]['varname'] = trim($rawmatch[1][$i]);
      $items[$i]['bnum'] = trim($rawmatch[2][$i]);
      $items[$i]['title'] = trim($rawmatch[3][$i]);
      $items[$i]['author'] = trim($rawmatch[4][$i]);
      $items[$i]['date'] = trim($rawmatch[5][$i]);
      $items[$i]['details'] = trim($rawmatch[6][$i]);
    }
    if (!$count) {
      // return whether user is opted in or out of checkout history feature
      $items = strpos($itemslist_raw, 'readinghistory/OptOut') !== FALSE ? 'in' : 'out';
    }
    return $items;
  }
  /**
   * Opts user in or out of checkout history feature.
   * Opting out deletes the existing history.
   *
   * @param string $action whether to opt in or out
   * @return array An array of items checked out.
   */
  public function toggle_patron_history($action) {
    if ($action == 'in') {
      $action = 'OptIn';
      $goal = 'OptOut';
    }
    elseif ($action == 'out') {
      $action = 'OptOut';
      $goal = 'OptIn';
      if (!$this->delete_patron_history(array('all'))) {
        return FALSE;
      }
    }
    else { return FALSE; }
    $url_suffix = 'patroninfo~S3/' . $this->pnum . '/readinghistory/' . $action;
    $result = self::my_curl_exec($url_suffix);
    $success = strpos($result['body'], 'readinghistory/' . $goal) !== FALSE;
    return $success;
  }
  /**
   * Clear checked-out items from history.
   *
   * @param array $which list of items to sort (optional)
   * @return array An array of items checked out.
   */
  public function delete_patron_history($which = array()) {
    if (!count($which)) { return FALSE; }
    $url_suffix = 'patroninfo~S3/' . $this->pnum . '/readinghistory/';
    if ($which[0] == 'all') {
      $result = self::my_curl_exec($url_suffix . 'rah');
      $success = strpos($result['body'], 'No Reading History Available' . $goal) !== FALSE;
    }
    // TODO: add handling for individual items
    return $success;
  }
  /**
   * Parses through the raw return from cURL to formulate the array passed back by get_patron_items()
   *
   * @param string $itemlist_raw Raw output from cURL
   * @return array An array of items checked out.
   */
  public function parse_patron_items($itemlist_raw) {
    
    $regex = '%patFuncEntry(.+?)name="(.+?)" value="i(.+?)"(.+?)patFuncTitle"><a href="/patroninfo~S3/(.+?)/item&(.+?)">(.+?)</a>(.+?)DUE(.+?)<(.+?)CallNo">(.+?)</td>%s';
    $count = preg_match_all($regex, $itemlist_raw, $rawmatch);

  
    for ($i=0; $i < $count; $i++) {
      $item[$i]['varname'] = trim($rawmatch[2][$i]);
    
      $item[$i]['inum'] = trim($rawmatch[3][$i]);
      $item[$i]['bnum'] = trim($rawmatch[6][$i]);
      $item[$i]['title'] = trim($rawmatch[7][$i]);

      // Todo - Talk with AADL about doing this differently.  For now, it's hard-coded to no ILL.
      $item[$i]['ill'] = 0;

      if (trim($rawmatch[10][$i])) {
        preg_match('%Renewed (.+?) time%s', $rawmatch[10][$i], $num_renews_raw);
        $item[$i]['numrenews'] = trim($num_renews_raw[1]) ? trim($num_renews_raw[1]) : 0;
      } else {
        $item[$i]['numrenews'] = 0;
      }

      $item[$i]['duedate'] = self::date_to_timestamp(trim($rawmatch[9][$i]));
      $item[$i]['callnum'] = trim($rawmatch[11][$i]);
    }
    return $item;

  }
  
  /**
   * Returns an array of on-hold items.
   *
   * @return array An array of on-hold items.
   */
  public function get_patron_holds() {

    $url_suffix = 'patroninfo/' . $this->pnum . '/holds';
    $result = self::my_curl_exec($url_suffix);
    
    /*
    patFuncMark(.+?)name="(.+?)"(.+?)/patroninfo~S3/(.+?)/item&(.+?)">(.+?)</a>(.+?)patFuncStatus">(.+?)</td>(.+?)patFuncPickup">(.+?)</td>(.+?)patFuncCancel">(.+?)</td>(.+?)patFuncFreeze(.+?)name="(.+?)"(.+?)/></td>
    */

    $regex = '%patFuncMark(.+?)name="(.+?)"(.+?)/patroninfo~S3/(.+?)/item&(.+?)">(.+?)</a>(.+?)patFuncStatus">(.+?)</td>(.+?)patFuncPickup">(.+?)</td>(.+?)patFuncCancel">(.+?)</td>(.+?)patFuncFreeze(.+?)</td>%s';
  
    $count = preg_match_all($regex, $result['body'], $rawmatch);
    for ($i=0; $i < $count; $i++) {
      $item[$i]['varname'] = trim($rawmatch[2][$i]);
      $item[$i]['bnum'] = trim($rawmatch[5][$i]);
      $item[$i]['title'] = trim($rawmatch[6][$i]);

      // Check with AADL to see if this work properly
      if (preg_match('%@%s', $item[$i]['varname'])) {
        $item[$i]['ill'] = 1;
      } else {
        $item[$i]['ill'] = 0;
      }
      
      $status = trim($rawmatch[8][$i]);
      if ((!preg_match('/of/i', $status)) && (!preg_match('/ready/i', $status)) && (!preg_match('/RECEIVED/i', $status))) { 
        $status = "In Transit";
      }
      $item[$i]['status'] = $status;
      
      $pickup_select = trim($rawmatch[10][$i]);
      preg_match('/select name=(.+?)>/is', $pickup_select, $pickup_var_match);
      $select_count = preg_match_all('/option value="(.+?)"(.+?)>(.+?)<\/option>/is', $pickup_select, $pickup_select_var_match);
      $item[$i]['pickuploc']['selectid'] = trim($pickup_var_match[1]);
      $item[$i]['pickuploc']['options'] = array();
      for ($j=0; $j < $select_count; $j++) {
        $item[$i]['pickuploc']['options'][trim($pickup_select_var_match[1][$j])] = trim($pickup_select_var_match[3][$j]);
        if (trim($pickup_select_var_match[2][$j])) {
          $item[$i]['pickuploc']['selected'] = trim($pickup_select_var_match[1][$j]);
        }
      }

      $canceldate = trim(str_replace('&nbsp;', '', $rawmatch[12][$i]));
      if ($canceldate) {
        $item[$i]['canceldate'] = $canceldate;
      }
      
      if (preg_match('%type="(.+?)" name="(.+?)"(.+?)/>%s', $rawmatch[14][$i], $freezematch)) {
        $item[$i]['is_frozen'] = (trim($freezematch[3]) == 'checked') ? 1 : 0;
        $item[$i]['can_freeze'] = (trim($freezematch[1]) == 'checkbox') ? 1 : 0;
        $item[$i]['freezevar'] = trim($freezematch[2]);
      } else {
        $item[$i]['is_frozen'] = 0;
        $item[$i]['can_freeze'] = 0;
        $item[$i]['freezevar'] = NULL;
      }
    }
    return $item;
  }

  /**
   * Place a hold on a particular bib item.
   *
   * @param string $bnum Bib number
   * @param string $pickup_loc Pickup location (optional).  //TODO
   * @return array my_curl_exec result array
   */
  public function place_hold($bnum = NULL, $inum = NULL, $pickup_loc = NULL) {

    $url_suffix = 'search~S3/.b' . $bnum . '/.b' . $bnum . '/1,1,1,B/request~b' . $bnum;
    $postvars[] = 'name=' . urlencode($this->patroninfo['PATRNNAME']);
    $postvars[] = 'code=' . $this->cardnum;
    $postvars[] = 'pin=' . $this->pin;
    $postvars[] = 'neededby_Month=' . date('m');
    $postvars[] = 'neededby_Day=' . date('d');
    $postvars[] = 'neededby_Year=' . (int)(date('Y') + 1);
    if ($inum) {
      $postvars[] = 'submit=SUBMIT';
      $postvars[] = 'radio=' . $inum;
    }
    if ($pickup_loc) { $postvars[] = 'loc=' . $pickup_loc; }
    $post = implode('&', $postvars);
    
    // To make sure the record has been freed.  Otherwise we run in to a race condition.
    usleep(300000);
    
    $result = self::my_curl_exec($url_suffix, $post);
    
    if (preg_match('/Your request for(.*?)was successful/is', $result['body'])) {
      $result['success'] = 1;
    } else {
      $result['success'] = 0;
    }
    
    if (preg_match('/<font color="red" size="(.+?)">(.+?)<\/font>/is', $result['body'], $error_match)) {
      $result['error'] = trim($error_match[2]);
    }
    if (preg_match('/Choose one item from the list below/is', $result['body'])) {
      preg_match_all('/<tr  class="bibItemsEntry">(.+?)<\/td>(.+?)<!-- field 1 -->&nbsp; (.+?)<\/td>(.+?)<!-- field C -->&nbsp;(.+?)&nbsp; <!-- field v -->(.*?)&nbsp;(.+?)field \% -->&nbsp;(.+?)</is', $result['body'], $items_match_raw);
      $num_items = count($items_match_raw[0]);
      for ($i = 0; $i < $num_items; $i++) {
        preg_match('/value="(.+?)"/is', $items_match_raw[1][$i], $inum_match);
        $result['selection'][$i]['varname'] = trim($inum_match[1]);
        $result['selection'][$i]['location'] = trim($items_match_raw[3][$i]);
        $result['selection'][$i]['callnum'] = trim($items_match_raw[5][$i]) . ' ' . trim($items_match_raw[6][$i]);
        $result['selection'][$i]['status'] = trim($items_match_raw[8][$i]);
      }
    }
    // handle if user needs to select a location to pickup item
    $result['choose_location'] = NULL;
    if (preg_match('/select name=loc(.*?)\<\/form\>/is', $result['body'], $location_form)) {
      //get options
      preg_match_all('/\<option (.*?)\<\/option/is', $location_form[1], $found_options);
      $num_items = count($found_options[0]);
      $options = array();
      for ($i = 0; $i < $num_items; $i++) {
        $value = preg_match('/value="(.*?)"/is', $found_options[1][$i], $found_value);
        $name = preg_match('/\>(.*$)/is', $found_options[1][$i], $found_name);
        if ($value && $name && preg_match('/\w/', $found_value[1])) {
          $options[$found_value[1]] = $found_name[1];
        }
      }
      if (count($options)) {
        $result['choose_location'] = array('options' => $options);
      }
    }

    return $result;
  }

  /**
   * Cancel a hold on a particular item or list of items.
   *
   * @param array $holdvars Array of hold variables to cancel.  Holdvars come from get_patron_holds().
   * @return array my_curl_exec result array
   */
  public function update_holds($cancelholds = array(), $holdfreezes_to_update, $pickup_locations = array()) {
    $url_suffix = 'patroninfo/' . $this->pnum . '/holds?updateholdssome=TRUE';

    $holds = self::get_patron_holds();
    
    $freeze_arr = array();
    $pickup_arr = array();
    $cancel_arr = array();

    foreach ($holds as $hold) {
      if (isset($holdfreezes_to_update[$hold['bnum']])) {
        $freeze_arr[$hold['bnum']] = $holdfreezes_to_update[$hold['bnum']];
      } else {
        $freeze_arr[$hold['bnum']] = $hold['is_frozen'];
      }
      
      if (isset($pickup_locations[$hold['bnum']])) {
        $pickup_arr[$hold['bnum']] = array('selectid' => $pickup_locations[$hold['bnum']]['selectid'], 'selected' => $pickup_locations[$hold['bnum']]['selected']);
      } else if (isset($hold['pickuploc']['selectid']) && isset($hold['pickuploc']['selected'])) {
        $pickup_arr[$hold['bnum']] = array('selectid' => $hold['pickuploc']['selectid'], 'selected' => $hold['pickuploc']['selected']);
      }
      
      if (isset($cancelholds[$hold['bnum']])) {
        $cancel_arr[$hold['varname']] = trim($cancelholds[$hold['bnum']]);
      }
    }
    
    // Queue up cancelations
    foreach ($cancel_arr as $cancelvar => $cancelval) {
      if ($cancelval) { $getvars[] = $cancelvar . '=1'; }
    }
    
    // Queue up hold freezes
    foreach ($freeze_arr as $bnum => $freeze) {
      if (!isset($cancelholds[$bnum])) {
        $getvars[] = 'freezeb' . $bnum . '=' . ((int) $freeze ? '1' : '0');
      }
    }
    
    // Queue up pickup location changes
    if (count($pickup_arr)) {
      foreach ($pickup_arr as $bnum => $pickup_sel_arr) {
        if (!isset($cancelholds[$bnum])) {
          $getvars[] = $pickup_sel_arr['selectid'] . '=' . $pickup_sel_arr['selected'];
        }
      }
    }
    
    $url_suffix .= '&' . implode('&', $getvars);
    usleep(300000); // To make sure the record has been freed.
    $result = self::my_curl_exec($url_suffix);
    usleep(300000); // To make sure the changes have taken.
    return $result; // TODO make the return info a little more useful - Handle errors, etc
  }

  /**
   * Cancel a hold on a particular item or list of items.
   *
   * @param array $holdvars Array of hold variables to cancel.  Holdvars come from get_patron_holds().
   * @return array my_curl_exec result array
   */
  public function cancel_holds($holdvars) {
    $url_suffix = 'patroninfo/' . $this->pnum . '/holds?updateholdssome=TRUE';
    foreach ($holdvars as $var1 => $var2) {
      $getvars[] = $var2 . '=1';
    }
    $cancelations = implode('&', $getvars);
    $url_suffix .= '&' . $cancelations;
    usleep(300000); // To make sure the record has been freed.
    $result = self::my_curl_exec($url_suffix);
    return $result; // TODO make the return info a little more useful - Handle errors, etc
  }
  
  /**
   * Cancel a hold on a particular item or list of items.
   *
   * @param array $holdvars Array of hold variables to cancel. Holdvars come from get_patron_holds().
   * @return array my_curl_exec result array
   */
  public function update_holdfreezes($holdfreezes_to_update) {
    $url_suffix = 'patroninfo~S13/' . $this->pnum . '/holds?updateholdssome=TRUE';
    foreach ($holdfreezes_to_update as $bnum => $freeze) {
      $getvars[] = 'freezeb' . $bnum . '=' . ((int) $freeze ? '1' : '0');
    }
    $updates = implode('&', $getvars);
    $url_suffix .= '&' . $updates;
    usleep(300000); // To make sure the record has been freed.
    $result = self::my_curl_exec($url_suffix);
    return $result; // TODO make the return info a little more useful - Handle errors, etc
  }

  /**
   * Renew an item or list of items or everything.
   *
   * @param array $renew_arg Array of varname and item numbers to renew (optional).  If not given, it renews everything.
   * @return boolean|array Array of checked-out items, their renewal status, and new due date if applicable.
   */
  public function renew_material($renew_arg = 'all') {

    if (is_array($renew_arg)) {
      foreach ($renew_arg as $inum => $varname) {
        if ($inum[0] != 'i') { $inum = 'i' . $inum; }
        $get_args[] = $varname . '=' . $inum;
      }
      $args = implode('&', $get_args);
      $url_suffix = 'patroninfo/' . $this->pnum . '/sorteditems?renewsome=TRUE&' . $args;
    } else if (strtolower($renew_arg) == 'all') {
      $url_suffix = 'patroninfo/' . $this->pnum . '/sorteditems?renewall';
    }
    usleep(300000); // To make sure the record has been freed.
    $result = self::my_curl_exec($url_suffix);
    return self::parse_patron_renews($result['body'], $renew_arg);
  
  }

  /**
   * Returns an array of checked-out items, their renewal status, and new due date if applicable.
   *
   * @param string $renewlist_raw HTTP body from cURL execution
   * @param array $renew_arg Array of varname and item numbers to renew (optional).  Assumes remew-all if not given.
   * @return boolean|array Array of checked-out items, their renewal status, and new due date if applicable.
   */
  public function parse_patron_renews($renewlist_raw, $renew_arg = NULL) {

    // These are subject to change at any time
    $regex_indiv = '%%<input type="checkbox" name="%s" value="%s" \/>(.*?)DUE(.*?)<(.+?)td%%s';
    $regex_rnall = '%<input type="checkbox" name="(.*?)" value="i(.*?)" \/>(.*?)DUE(.*?)<(.+?)td%s';

    // If renewing individual items
    if (is_array($renew_arg)) {
      foreach ($renew_arg as $inum => $varname) {
        if ($inum[0] != 'i') { $inum_reg = 'i' . $inum; } else { $inum_reg = $inum; }
        $regex = sprintf($regex_indiv, $varname, $inum_reg);
        preg_match($regex, $renewlist_raw, $rawmatch);
        $extra = $rawmatch[3];
        if (preg_match('/Renewed(.*?)time/i', $extra, $renew_match)) { 
          $renew_res[$inum]['num_renews'] = (int) trim($renew_match[1]);
        } else {
          $renew_res[$inum]['num_renews'] = 0;
        }
        if (preg_match('/color=\"red\">(.*?)</i', $extra, $error_match)) {
          $renew_res[$inum]['error'] = ucwords(strtolower(trim($error_match[1])));
        }
        $renew_res[$inum]['varname'] = $varname;
        $renew_res[$inum]['new_duedate'] = self::date_to_timestamp($rawmatch[2]);
      }
    // If remewing all items
    } else {
      if (strtolower($renew_arg) == 'all') {
        $regex = $regex_rnall;
        preg_match_all($regex, $renewlist_raw, $rawmatch);
        $varnames = $rawmatch[1];
        $inums = $rawmatch[2];
        foreach ($rawmatch[5] as $key => $extra) {
          if (preg_match('/Renewed(.*?)time/i', $extra, $renew_match)) {
            $renew_res[$inums[$key]]['num_renews'] = (int) trim($renew_match[1]);
          } else {
            $renew_res[$inums[$key]]['num_renews'] = 0;
          }
          if (preg_match('/color=\"red\">(.*?)</i', $extra, $error_match)) {
            $renew_res[$inums[$key]]['error'] = ucwords(strtolower(trim($error_match[1])));
          }
        }
        foreach ($rawmatch[2] as $key => $inum) {
          $renew_res[$inum]['varname'] = trim($varnames[$key]);
        }
        foreach ($rawmatch[4] as $key => $due) {
          $renew_res[$inums[$key]]['new_duedate'] = self::date_to_timestamp($due);
        }
      } else {
        return FALSE;
      }
    }
    return $renew_res;
  }

  /**
  * Returns current fines
  *
  * @return string HTML body from the fine payment screen
  */
  function get_patron_fines() {
    $url_suffix = 'webapp/iii/ecom/pay.do?scope=3&ptype=' . $this->patroninfo['PTYPE'] . '&tty=300';
    $result = self::my_curl_exec($url_suffix);
    return self::parse_patron_fines($result['body']);
  }

  /**
  * Parses current fines result
  *
  * @return array of fine details
  */
  function parse_patron_fines($body) {
    $regex = '%type="checkbox" name="selectedFees" value="(.+?)"(.+?)>(.+?)\$(.+?)<%s';
    $fines = array();
    preg_match('%name="key" value="(.+?)"%s', $body, $keymatch);
    $fines['sessionkey'] = trim($keymatch[1]);
    $count = preg_match_all($regex, $body, $rawmatch);
    for ($i=0; $i < $count; $i++) {
      $fines['items'][$i]['varname'] = trim($rawmatch[1][$i]);
      $fines['items'][$i]['desc'] = trim($rawmatch[3][$i]);
      $fines['items'][$i]['amount'] = (float) trim($rawmatch[4][$i]);
    }
    return $fines;
  }
  
  /**
  * Pays fines for whichever fines are passed through to the function.
  * $payment_arr looks like:
  * [{varnames}]   = 'on' :: This tells III which fines are being paid.
  * [amount]    = payment amount.
  * [ccname]    = Name on the credit card.
  * [address1]  = Billing address.
  * [city]    = Billing address city.
  * [state]    = Billing address state.
  * [zip]      = Billing address zip.
  * [emailaddr]  = Cardholder email address.
  * [ccnum]    = Credit card number.
  * [ccexpmonth]  = Credit card expiration date.
  * [ccexpyear]  = Credit card expiration year.
  * [cc_cvv2]    = Credit card verification number.
  *
  * @param array Payment details array.
  * @return array Payment results array.
  */
  function pay_fine($payment_arr) {
    
    $fines = self::get_patron_fines();
    $sessionkey = $fines['sessionkey'];
    $url_suffix_stage1 = 'webapp/iii/ecom/validatePay.do';
    $url_suffix_stage2 = 'webapp/iii/ecom/submitPay.do';

    $postvars = 'action=confirmInfo&key=' . $sessionkey . '&parsedMoneyfmt=,.2&currencySymbol=$&serviceCharge=0&amount=0';
    foreach ($payment_arr as $pkey => $pval) {
      if ($pkey == 'varnames') {
        foreach ($pval as $pid) {
          $postvars .= '&selectedFees=' . trim($pid);
        }
      } else {
        $postvars .= '&' . $pkey . '=' . urlencode($pval);        
      }

    }
    $result = self::my_curl_exec($url_suffix_stage1, $postvars);
    $postvars = 'action=submitData&key=' . $sessionkey;
    $pay_result = self::my_curl_exec($url_suffix_stage2, $postvars);
    usleep(500000); // To make sure the record has been freed.

    // may vary depending on how OPAC/ILS is set up (TODO: turn into config setting?)
    if ((preg_match('%Your payment has been approved%s', $pay_result['body'])) ||
      (preg_match('%Your payment has been accepted%s', $pay_result['body']))) {
      $result_arr['approved'] = 1;
    } else {
      $result_arr['approved'] = 0;
      $is_msg = preg_match('%key="creditForm.error"\/-->(.+?)<(.+?)error">(.+?)<%s', $pay_result['body'], $err_match);
      $result_arr['error'] = trim($err_match[3]);
      $result_arr['reason'] = trim($err_match[1]);
    }
    return $result_arr;
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
    if (count($date_arr) != 3) { return $date_orig; }
    $month = (int) $date_arr[0];
    $day = (int) $date_arr[1];
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
    if (is_numeric($date_arr[0]) && is_numeric($date_arr[0]) && is_numeric($year))
    $time = mktime(0, 0, 0, (int) $date_arr[0], (int) $date_arr[1], $year);
    return $time;
  }

  /**
   * Executes a cURL request while handling all the session business.
   *
   * @param string $url_suffix The URL to query.  Everything after the 'http://my.addr.org/'
   * @param string $postvars POST variables to pass in GET format. Ex:  var1=foo&var2=bar
   * @param boolean $no_loop Overrides this functions default to loop through 10 times to get a result
   * @param int $curl_timeout Timeout, in seconds, before cURL gives up curl_exec.  (optional).  Default: 6
   * @return array Array of parsed components from the cURL result as provided by parse_response()
   */
  public function my_curl_exec($url_suffix, $postvars = NULL, $no_loop = FALSE, $curl_timeout = 6, $login_query = FALSE, $ssl = TRUE) {

    $iii_url = $ssl ? $this->iii_server_info['sslurl'] : $this->iii_server_info['nosslurl'];
    $agent = "Mozilla/5.0 (Macintosh; U; Intel Mac OS X 10.4; en-US; rv:1.9.0.7-locum) Gecko/2009021906 Firefox/3.5.3";
    if ($url_suffix[0] == '/') { $url_suffix = substr($url_suffix, 1); }
    $curl_url = $iii_url . '/' . $url_suffix;

    // If we have POST variables to send, initializr them here
    if ($postvars) {
      curl_setopt ($this->ch, CURLOPT_POST, 1);
      curl_setopt ($this->ch, CURLOPT_POSTFIELDS, $postvars);
    }
    curl_setopt ($this->ch, CURLOPT_URL, $curl_url);

    // Execute the CURL query.  Loop 10 times if needed.  Sometimes it's needed.  Really.
    $curl_loop = 0;
    while (!$body) {
      $body = curl_exec($this->ch);
      if ($no_loop) {
        return self::parse_response($body); 
      }
      $curl_loop++;
      if ($curl_loop == 10) { 
        return "Unable to contact catalog. ($curl_url) Please try again later.<br/><br/>";
      }
    }
    return self::parse_response($body);
  }

  /**
   * Parses the cURL result into response code, header, and body.
   *
   * @param string $this_response cURL response
   * @return array Array of response components, keyed by 'code', 'header', and 'body'
   */
  function parse_response($this_response) {

    // Split response into header and body sections
    list($response_headers, $response_body) = explode("\n\n", $this_response, 2);
    $response_header_lines = explode("\n", $response_headers);

    // First line of headers is the HTTP response code
    $http_response_line = array_shift($response_header_lines);
    if(preg_match('@^HTTP/[0-9]\.[0-9] ([0-9]{3})@',$http_response_line, $matches)) { $response_code = $matches[1]; }

    // put the rest of the headers in an array
    $response_header_array = array();
    foreach($response_header_lines as $header_line) {
      list($header,$value) = explode(': ', $header_line, 2);
      $response_header_array[$header] .= $value."\n";
    }

    return array("code" => $response_code, "header" => $response_header_array, "body" => $response_body);
  }

}
