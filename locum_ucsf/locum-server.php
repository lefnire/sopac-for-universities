<?php
/**
 * Locum is a software library that abstracts ILS functionality into a
 * catalog discovery layer for use with such things as bolt-on OPACs like
 * SOPAC.
 * @package Locum
 * @author John Blyberg
 */

require_once('locum.php');

/**
 * This class is the server component of Locum.  It is separated from the client piece because the functionality
 * in this class should never need to be used in any front-end pieces.  This class does all the harvesting and
 * data preparation.
 */
class locum_server extends locum {

  /**
   * This function initiates the harvest of bib records from the catalog.
   *
   * @param int $start Bib number to start with
   * @param int $end Bib number to end with
   * @param boolean $quiet quietly harvest or not.  Default: TRUE
   */
  public function harvest_bibs($start, $end, $quiet = TRUE) {
    if (is_callable(array(__CLASS__ . '_hook', __FUNCTION__))) {
      eval('$hook = new ' . __CLASS__ . '_hook;');
      return $hook->{__FUNCTION__}($start, $end, $quiet);
    }
    
    if ($start > $end) { return 0; }

    $num_children = $this->locum_config['harvest_config']['max_children'];
    $num_to_process = $end - $start;
    $increment = ceil($num_to_process / $num_children);
    if (extension_loaded('pcntl') && $this->locum_config['harvest_config']['harvest_with_children'] && ($num_to_process >= (2 * $num_children))) {
      for ($i = 0; $i < $num_children; ++$i) {
        $end = $start + ($increment - 1);
        $new_start = $end + 1;
  
        $pid = pcntl_fork();
        if ($pid != -1) {
          if ($pid) {
            $this->putlog("Spawning child harvester to scan records $start - $end. PID is $pid ..");
          } else {
            sleep(1);
            ++$i;
            if ($i == $num_children) { $end++; }
            $result = $this->import_bibs($start, $end);
            $this->putlog("Child process complete.  Scanned records $start - $end.  Imported " . $result['imported'] . " records and skipped $result[skipped] ..", 2);
            exit($i);
          }
        } else {
          $this->putlog("Unable to spawn harvester: ($i)", 5);
        }
        $start = $new_start;
      }
      if ($pid) {
        while ($i > 0) {
          pcntl_waitpid(-1, &$status);
          $val = pcntl_wexitstatus($status);
          --$i;
          }
        $this->putlog("Harvest complete!", 3);
      }
    } else {
      $result = $this->import_bibs($start, $end);
    }
  }

  /**
   * Does the actual import of bib records.  Called by the harvester.
   * It uses start and end parameters because this function can potentially be called by a
   * child process.
   *
   * @param int $start Bib number to start with
   * @param int $end Bib number to end with
   * @return array Array of information about the bibs imported
   */
  public function import_bibs($start, $end) {
    if (is_callable(array(__CLASS__ . '_hook', __FUNCTION__))) {
      eval('$hook = new ' . __CLASS__ . '_hook;');
      return $hook->{__FUNCTION__}($start, $end);
    }
    
    $db =& MDB2::connect($this->dsn);

    $process_report['skipped'] = 0;
    $process_report['imported'] = 0;
    $utf = "SET NAMES 'utf8' COLLATE 'utf8_unicode_ci'";
    $utfprep = $db->query($utf);

    for ($i = $start; $i <= $end; $i++) {
      $sql = "SELECT bnum FROM locum_bib_items WHERE bnum = $i";
      $init_result = $db->query($sql);
      $init_bib_arr = $init_result->fetchAll(MDB2_FETCHMODE_ASSOC);
      if(empty($init_bib_arr)) {
        $bib = $this->locum_cntl->scrape_bib($i, $this->locum_config['api_config']['skip_covers']);

        if ($bib == FALSE || $bib == 'skip' || $bib['suppress'] == 1) {
          $process_report['skipped']++;
        } else {
          $subj = $bib['subjects'];
          $valid_vals = array('bib_created', 'bib_lastupdate', 'bib_prevupdate', 'bib_revs', 'lang', 'loc_code', 'mat_code', 'author', 'addl_author', 'title', 'title_medium', 'edition', 'series', 'callnum', 'pub_info', 'pub_year', 'stdnum', 'upc', 'lccn', 'descr', 'notes', 'bnum', 'cover_img', 'download_link');
          foreach ($bib as $bkey => $bval) {
            if (in_array($bkey, $valid_vals)) { $bib_values[$bkey] = $bval; }
          }
          $bib_values['subjects_ser'] = serialize($subj);
          $types = array('date', 'date', 'date', 'integer', 'text', 'text', 'text', 'text', 'text', 'text', 'text', 'text', 'text', 'text', 'text', 'integer', 'text', 'text', 'integer', 'text', 'text', 'text', 'integer', 'text', 'text');
          $sql_prep = $db->prepare('INSERT INTO locum_bib_items VALUES (:bnum, :author, :addl_author, :title, :title_medium, :edition, :series, :callnum, :pub_info, :pub_year, :stdnum, :upc, :lccn, :descr, :notes, :subjects_ser, :lang, :loc_code, :mat_code, :cover_img, :download_link, NOW(), :bib_created, :bib_lastupdate, :bib_prevupdate, :bib_revs, \'1\')');
          
          $affrows = $sql_prep->execute($bib_values);
          $this->putlog("Importing bib # $i - $bib[title]");
          $sql_prep->free();

          if (is_array($subj) && count($subj)) {
            foreach ($subj as $subj_heading) {
              $insert_data = array($bib['bnum'], $subj_heading);
              $types = array('integer', 'text');
              $sql_prep = $db->prepare('INSERT INTO locum_bib_items_subject VALUES (?, ?)', $types, MDB2_PREPARE_MANIP);
              $affrows = $sql_prep->execute($insert_data);
              $sql_prep->free();
            }
          }
          
          // Remember to run locum_university_init.sql          
          // Import university info ala Millennium's Journal & Reserves modules
//          if($bib['marc_code']=='j'){
            
            $university=array();
            $valid_vals = array('bnum', 'continues', 'link', 'alt_title', 'related_work', 'local_note', 'oclc', 'doc_number', 'holdings', 'cont_d_by', '__note__', 'hldgs_stat');
            $not_empty = false;
            foreach ($bib as $bkey => $bval) {
              if (in_array($bkey, $valid_vals)) {
                $university[$bkey] = $bval;
                if($bval && $bkey!='bnum') { $not_empty = true; } // don't insert blank items, and bnum doesn't count
              }
            }
            // Don't insert blank items
            if($not_empty){
              $implode = implode(', :', array_keys($valid_vals));
              $sql_prep = $db->prepare('INSERT INTO locum_bib_items_university VALUES (:'. implode(', :', $valid_vals) .')');
              $affrows = $sql_prep->execute($university);
              $sql_prep->free();
            }
            
//        }
          
          
          $process_report['imported']++;
        }
      }
    }
    $db->disconnect();
    return $process_report;
  }

  /**
   * Does the actual update of the bib record if something has changed.
   * This function is called by verify_bibs()
   *
   * @param array $bib_arr Array of bibs like: key => val is bnum => last update date
   * @return array Array of # updated and # retired
   */
  public function update_bib($bib_arr) {
    if (is_callable(array(__CLASS__ . '_hook', __FUNCTION__))) {
      eval('$hook = new ' . __CLASS__ . '_hook;');
      return $hook->{__FUNCTION__}($bib_arr);
    }
    
    $db = MDB2::connect($this->dsn);
    $updated = 0;
    $retired = 0;
    $skipped = 0;

    foreach ($bib_arr as $bnum => $init_bib_date) {
      if(!$firstbib) {
        $firstbib = $bnum;
      }
      $lastbib = $bnum;

      $bib = $this->locum_cntl->scrape_bib($bnum, $this->locum_config['api_config']['skip_covers']);
      $utf = "SET NAMES 'utf8' COLLATE 'utf8_unicode_ci'";
      $utfprep = $db->query($utf);

      if ($bib == FALSE) {
        // Weed this record
        // TODO add a verification of weed in here somehow
        $sql_prep =& $db->prepare('UPDATE locum_bib_items SET active = ? WHERE bnum = ?', array('text', 'integer'));
        $sql_prep->execute(array('0', $bnum));
        $sql_prep =& $db->prepare('DELETE FROM locum_bib_items_subject WHERE bnum = ?', array('integer'));
        $sql_prep->execute(array($bnum));
        $sql_prep->free();
        $retired++;
      } else if ($bib == 'skip') {
        // Do nothing.  This might happen if the ILS server is down.
        $skipped++;
      } else if ($bib['bnum'] && $bib['bib_lastupdate'] != $init_bib_date) {
        $subj = $bib['subjects'];
        $valid_vals = array('bib_created', 'bib_lastupdate', 'bib_prevupdate', 'bib_revs', 'lang', 'loc_code', 'mat_code', 'author', 'addl_author', 'title', 'title_medium', 'edition', 'series', 'callnum', 'pub_info', 'pub_year', 'stdnum', 'upc', 'lccn', 'descr', 'notes', 'bnum', 'download_link');
        foreach ($bib as $bkey => $bval) {
          if (in_array($bkey, $valid_vals)) { $bib_values[$bkey] = $bval; }
        }
        
        $bib_values['subjects_ser'] = serialize($subj);
      
        $types = array('date', 'date', 'date', 'integer', 'text', 'text', 'text', 'text', 'text', 'text', 'text', 'text', 'text', 'text', 'text', 'integer', 'text', 'text', 'integer', 'text', 'text', 'text', 'text');
    
        $setlist = 
          "bib_created = :bib_created, " .
          "bib_lastupdate = :bib_lastupdate, " .
          "bib_prevupdate = :bib_prevupdate, " .
          "bib_revs = :bib_revs, " .
          "lang = :lang, " .
          "loc_code = :loc_code, " .
          "mat_code = :mat_code, " .
          "author = :author, " .
          "addl_author = :addl_author, " .
          "title = :title, " .
          "title_medium = :title_medium, " .
          "edition = :edition, " .
          "series = :series, " .
          "callnum = :callnum, " .
          "pub_info = :pub_info, " .
          "pub_year = :pub_year, " .
          "stdnum = :stdnum, " .
          "upc = :upc, " .
          "lccn = :lccn, " .
          "descr = :descr, " .
          "notes = :notes, " .
          "subjects = :subjects_ser, " .
          "download_link = :download_link, " .
          "modified = NOW()";
      
        $sql_prep =& $db->prepare('UPDATE locum_bib_items SET ' . $setlist . ' WHERE bnum = :bnum', $types, MDB2_PREPARE_MANIP);
        $res = $sql_prep->execute($bib_values);
        $sql_prep =& $db->prepare('DELETE FROM locum_bib_items_subject WHERE bnum = ?', array('integer'));
        $sql_prep->execute(array($bnum));
        $sql_prep->free();
      
        if (is_array($subj) && count($subj)) {
          foreach ($subj as $subj_heading) {
            $insert_data = array($bnum, $subj_heading);
            $types = array('integer', 'text');
            $sql_prep =& $db->prepare('INSERT INTO locum_bib_items_subject VALUES (?, ?)', $types, MDB2_PREPARE_MANIP);
            $affrows = $sql_prep->execute($insert_data);
            $sql_prep->free();
          }
        }
        
        $this->putlog("Updated record # $bnum - $bib[title]", 2, TRUE);
        $updated++;
      }
    }
    $db->disconnect();
    $this->putlog("Processed $firstbib - $lastbib");
    return array('retired' => $retired, 'updated' => $updated, 'skipped' => $skipped);
  }

  /**
   * Scans for newly cataloged bib records.
   * Uses the ini "harvest_reach" param to determine how far forward to seek.
   */
  public function new_bib_scan() {
    if (is_callable(array(__CLASS__ . '_hook', __FUNCTION__))) {
      eval('$hook = new ' . __CLASS__ . '_hook;');
      return $hook->{__FUNCTION__}();
    }
    
    $db = MDB2::connect($this->dsn);
    $sql = 'SELECT MAX(bnum) FROM locum_bib_items';
    $max_bib_result =& $db->query($sql);
    $max_bib = $max_bib_result->fetchOne();
    $next_bib = $max_bib + 1;
    $last_bib = $next_bib + $this->locum_config['harvest_config']['harvest_reach'];
    $db->disconnect();
    $this->putlog("Harvesting bibs # $next_bib - $last_bib", 2, TRUE);
    $this->harvest_bibs($next_bib, $last_bib);
  }
  
  /**
   * Flushes the holds_count table and rebuilds it.  Useful for keeping popularity information
   * up-to-date.  It's needed in this format so that the sphinx index can be rebuilt with 
   * dortable popularity data.
   */
  public function rebuild_holds_cache() {
    if (is_callable(array(__CLASS__ . '_hook', __FUNCTION__))) {
      eval('$hook = new ' . __CLASS__ . '_hook;');
      return $hook->{__FUNCTION__}();
    }
    
    $db = MDB2::connect($this->dsn);
    $db->query('DELETE FROM locum_holds_count');
    $db->query('INSERT INTO locum_holds_count (bnum) SELECT locum_bib_items.bnum FROM locum_bib_items');

    $counts = array('week', 'month', 'year', 'total');
    $sql_week = 'SELECT bnum, COUNT(bnum) AS total FROM locum_holds_placed WHERE hold_date >= DATE_SUB(CURDATE(), INTERVAL 1 WEEK) GROUP BY bnum';
    $sql_month = 'SELECT bnum, COUNT(bnum) AS total FROM locum_holds_placed WHERE hold_date >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH) GROUP BY bnum';
    $sql_year = 'SELECT bnum, COUNT(bnum) AS total FROM locum_holds_placed WHERE hold_date >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR) GROUP BY bnum';
    $sql_total = 'SELECT bnum, COUNT(bnum) AS total FROM locum_holds_placed GROUP BY bnum';
    
    foreach ($counts as $count_type) {
      $dbq =& $db->query(${'sql_' . $count_type});
      $result_arr = $dbq->fetchAll(MDB2_FETCHMODE_ASSOC);
      foreach ($result_arr as $result) {
        $db->query('UPDATE locum_holds_count SET hold_count_' . $count_type . ' = ' . $result['total'] . ' WHERE bnum = ' . $result['bnum']);
      }
    }
  }

  public function rebuild_facet_heap() {
    if (is_callable(array(__CLASS__ . '_hook', __FUNCTION__))) {
      eval('$hook = new ' . __CLASS__ . '_hook;');
      return $hook->{__FUNCTION__}();
    }
    
    $db = MDB2::connect($this->dsn);
    $db->exec("DELETE FROM locum_facet_heap");
    $db->exec("INSERT INTO locum_facet_heap (bnum, series, mat_code, loc_code, lang, pub_year, pub_decade, bib_lastupdate) " .
      "SELECT locum_bib_items.bnum, series, mat_code, loc_code, lang, pub_year, TRUNCATE(pub_year/10,0)*10 AS pub_decade, bib_lastupdate " .
      "FROM locum_bib_items " .
      "LEFT JOIN locum_availability on locum_bib_items.bnum = locum_availability.bnum " .
      "WHERE active = '1'");
  }
  
  /**
   * Tells sphinx indexer to index
   * Supports specific indexes
   *
   * @param string $index index to index
   * @param boolean $new TRUE or FALSE on whether it is initial index. Default is FALSE - reindex
   * @return string Status Success or Failure
   */
  public function index($index = 'all', $new = FALSE) {
    if (is_callable(array(__CLASS__ . '_hook', __FUNCTION__))) {
      eval('$hook = new ' . __CLASS__ . '_hook;');
      return $hook->{__FUNCTION__}($index, $new);
    }
  
    $binpath = $this->locum_config['sphinx_config']['bin_path'];
    $server = $this->locum_config['sphinx_config']['server_addr'];
    $pubkey = $this->locum_config['sphinx_config']['pubkey_path'];
    $privkey = $this->locum_config['sphinx_config']['privkey_path'];
    $secret = $this->locum_config['sphinx_config']['key_pass'];
    $username = $this->locum_config['sphinx_config']['key_user'];
    
    switch ($index) {
      case "keyword":
        $options = "bib_items_keyword";
        break;
      case "author":
        $options = "bib_items_author";
        break;
      case "title":
        $options = "bib_items_title";
        break;
      case "callnum":
        $options = "bib_items_callnum";
        break;
      case "subject":
        $options = "bib_items_subject";
        break;
      case "tags":
        $options = "bib_items_tags";
        break;
      case "reviews":
        $options = "bib_items_reviews";
        break;
      case "social":
        $options = "bib_items_tags bib_items_reviews";
        break;
      case "bib":
        $options = "bib_items_keyword bib_items_author bib_items_title bib_items_callnum bib_items_subject";
        break;
      default:
        $options = "--all";
    }
    
    $command = $binpath . "/indexer " . $options;
    if(!$new) {
      $command .= " --rotate";
    }
    
    if($server == 'localhost' || $server == '127.0.0.1') {
      $cmdout = shell_exec($command);
    } else {
      $connection = ssh2_connect($server, 22, array('hostkey'=>'ssh-rsa'));
      if (ssh2_auth_pubkey_file($connection, $username, $pubkey, $privkey, $secret)) {
        $stream = ssh2_exec($connection, $command);
        stream_set_blocking($stream, true);
        $cmdout = stream_get_contents($stream);
      }
    }
    $success = "succesfully sent SIGHUP";
    $check = strpos($cmdout,$success);
    
    if ($pos === FALSE) {
      return FALSE;
    }
    else {
      return TRUE;
    }
    
  }
  
  /************ Verification / Maintenance Functions ************/
  
  
  /**
   * Scans existing imported bibs for changes or weeds and makes the appropriate changes.
   * 
   * @param boolean $quiet Run this function silently.  Default: TRUE
   */
  public function verify_bibs($quiet = TRUE) {
    if (is_callable(array(__CLASS__ . '_hook', __FUNCTION__))) {
      eval('$hook = new ' . __CLASS__ . '_hook;');
      return $hook->{__FUNCTION__}($quiet);
    }
    
    $limit = 1000;
    $offset = 0;
    
    $this->putlog("Collecting current data keys ..");
    $db = MDB2::connect($this->dsn);
    $sql = "SELECT bnum, bib_lastupdate FROM locum_bib_items WHERE active = '1' ORDER BY bnum LIMIT $limit";
    $init_result = $db->query($sql);
    $init_bib_arr = $init_result->fetchAll(MDB2_FETCHMODE_ASSOC);
    
    while(!empty($init_bib_arr)) {
      $num_children = $this->locum_config['harvest_config']['max_children'];
      $num_to_process = count($init_bib_arr);
      $bib_arr = array();
      foreach ($init_bib_arr as $init_bib_arr_vals) {
        $bib_arr[$init_bib_arr_vals['bnum']] = $init_bib_arr_vals['bib_lastupdate'];
      }
      $db->disconnect();
      $this->putlog("Finished collecting data keys.");

      if (extension_loaded('pcntl') && $this->locum_config['harvest_config']['harvest_with_children'] && ($num_to_process >= (2 * $num_children))) {
      
        $increment = ceil($num_to_process / $num_children);

        $split_offset = 0;
        for ($i = 0; $i < $num_children; ++$i) {
          $end = $start + ($increment - 1);
          $new_start = $end + 1;
  
          $pid = pcntl_fork();
          if ($pid != -1) {
            if ($pid) {
              $this->putlog("Spawning child harvester to verify records of $start - $end. PID is $pid ..");
            } else {
              sleep(1);
              ++$i;
              if ($i == $num_children) { $end++; }
              $bib_arr_sliced = array_slice($bib_arr, $split_offset, $increment, TRUE);
              $num_bibs = count($bib_arr_sliced);
              $tmp = $this->update_bib($bib_arr_sliced);
              $updated = $tmp['updated'];
              $retired = $tmp['retired'];
              $this->putlog("Child process complete.  Checked $num_bibs records, updated $updated records, retired $retired records.", 2);
              exit($i);
            }
          } else {
            $this->putlog("Unable to spawn harvester: ($i)", 5);
          }
          $start = $new_start;
          $split_offset = $split_offset + $increment;
        }
        if ($pid) {
          while ($i > 0) {
            pcntl_waitpid(-1, &$status);
            $val = pcntl_wexitstatus($status);
            --$i;
          }
          $this->putlog("Verification complete!", 3);
        }
      } else {
        // TODO - Bib verification for those poor saps w/o pcntl
      }
      
      $offset = $offset + $limit;
      $this->putlog("Collecting current data keys starting at $offset");
      $db = MDB2::connect($this->dsn);
      $sql = "SELECT bnum, bib_lastupdate FROM locum_bib_items WHERE active = '1' ORDER BY bnum LIMIT $limit OFFSET $offset";
      $init_result = $db->query($sql);
      $init_bib_arr = $init_result->fetchAll(MDB2_FETCHMODE_ASSOC);
    }
  }

  /**
   * Scans existing imported bibs for changes to the availability cache.
   * 
   * @param boolean $quiet Run this function silently.  Default: TRUE
   */
  public function verify_status($quiet = TRUE) {
    if (is_callable(array(__CLASS__ . '_hook', __FUNCTION__))) {
      eval('$hook = new ' . __CLASS__ . '_hook;');
      return $hook->{__FUNCTION__}($quiet);
    }
    
    require_once('locum-client.php');
    
    $limit = 1000;
    $offset = 0;

    $this->putlog("Collecting current data keys ..");
    $db = MDB2::connect($this->dsn);
    $sql = "SELECT bnum, bib_lastupdate FROM locum_bib_items WHERE active = '1' ORDER BY bnum LIMIT $limit";
    $init_result = $db->query($sql);
    $init_bib_arr = $init_result->fetchAll(MDB2_FETCHMODE_ASSOC);
    $locumclient = new locum_client;
    
    while(!empty($init_bib_arr)) {
      $num_children = $this->locum_config['harvest_config']['max_children'];
      $num_to_process = count($init_bib_arr);
      $bib_arr = array();
      foreach ($init_bib_arr as $init_bib_arr_vals) {
        $bib_arr[$init_bib_arr_vals['bnum']] = $init_bib_arr_vals['bib_lastupdate'];
      }
      $db->disconnect();
      $this->putlog("Finished collecting data keys.");

      if (extension_loaded('pcntl') && $this->locum_config['harvest_config']['harvest_with_children'] && ($num_to_process >= (2 * $num_children))) {
      
        $increment = ceil($num_to_process / $num_children);
      
        $split_offset = 0;
        for ($i = 0; $i < $num_children; ++$i) {
          $end = $start + ($increment - 1);
          $new_start = $end + 1;
  
          $pid = pcntl_fork();
          if ($pid != -1) {
            if ($pid) {
              $this->putlog("Spawning child harvester to verify records. PID is $pid ..");
            } else {
              sleep(1);
              ++$i;
              if ($i == $num_children) { $end++; }
              $bib_arr_sliced = array_slice($bib_arr, $split_offset, $increment, TRUE);
              $num_bibs = count($bib_arr_sliced);
              foreach ($bib_arr_sliced as $bnum => $init_bib_date) {
                $locumclient->get_item_status($bnum, TRUE);
              }
              $this->putlog("Child process complete.  Checked $num_bibs records", 2);
              exit($i);
            }
          } else {
            $this->putlog("Unable to spawn harvester: ($i)", 5);
          }
          $start = $new_start;
          $split_offset = $split_offset + $increment;
        }
        if ($pid) {
          while ($i > 0) {
            pcntl_waitpid(-1, &$status);
            $val = pcntl_wexitstatus($status);
            --$i;
          }
          $this->putlog("Verification complete!", 3);
        }
      } else {
        // TODO - Bib verification for those poor saps w/o pcntl
      }
      $offset = $offset + $limit;
      $this->putlog("Collecting current data keys starting at $offset");
      $db = MDB2::connect($this->dsn);
      $sql = "SELECT bnum, bib_lastupdate FROM locum_bib_items WHERE active = '1' ORDER BY bnum LIMIT $limit OFFSET $offset";
      $init_result = $db->query($sql);
      $init_bib_arr = $init_result->fetchAll(MDB2_FETCHMODE_ASSOC);
    }
  }
  
  /**
   * Scans existing imported bibs for changes to the syndetics links.
   * 
   * @param boolean $quiet Run this function silently.  Default: TRUE
   */
  public function verify_syndetics($quiet = TRUE) {
    if (is_callable(array(__CLASS__ . '_hook', __FUNCTION__))) {
      eval('$hook = new ' . __CLASS__ . '_hook;');
      return $hook->{__FUNCTION__}($quiet);
    }
    
    $limit = 1000;
    $offset = 0;
    
    $this->putlog("Collecting current data keys ..");
    $db = MDB2::connect($this->dsn);
    $sql = "SELECT stdnum,bib_lastupdate FROM locum_bib_items WHERE stdnum IS NOT NULL ORDER BY bib_lastupdate DESC LIMIT $limit";
    $init_result = $db->query($sql);
    $init_bib_arr = $init_result->fetchAll(MDB2_FETCHMODE_ASSOC);
    
    while(!empty($init_bib_arr)) {
      $num_children = $this->locum_config['harvest_config']['max_children'];
      $num_to_process = count($init_bib_arr);
      $bib_arr = array();
      foreach ($init_bib_arr as $init_bib_arr_vals) {
        $bib_arr[$init_bib_arr_vals['stdnum']] = $init_bib_arr_vals['bib_lastupdate'];
      }
      $db->disconnect();
      $this->putlog("Finished collecting data keys.");

      if (extension_loaded('pcntl') && $this->locum_config['harvest_config']['harvest_with_children'] && ($num_to_process >= (2 * $num_children))) {
      
        $increment = ceil($num_to_process / $num_children);
      
        $split_offset = 0;
        for ($i = 0; $i < $num_children; ++$i) {
          $end = $start + ($increment - 1);
          $new_start = $end + 1;
  
          $pid = pcntl_fork();
          if ($pid != -1) {
            if ($pid) {
              $this->putlog("Spawning child harvester to verify records. PID is $pid ..");
            } else {
              sleep(1);
              ++$i;
              if ($i == $num_children) { $end++; }
              $bib_arr_sliced = array_slice($bib_arr, $split_offset, $increment, TRUE);
              $num_bibs = count($bib_arr_sliced);
              foreach ($bib_arr_sliced as $stdnum => $init_bib_date) {
                if (preg_match('/ /', $stdnum)) {
                $stdnum_arr = explode(' ', $stdnum);
                $stdnum = $stdnum_arr[0];
                } else {
                  $stdnum = $stdnum;
                }
                $this->putlog("Checking syndetics for $stdnum", 2);
                $tmp = $this->get_syndetics($stdnum);
              }

              $this->putlog("Child process complete.  Checked $num_bibs records", 2);
              exit($i);
            }
          } else {
            $this->putlog("Unable to spawn harvester: ($i)", 5);
          }
          $start = $new_start;
          $split_offset = $split_offset + $increment;
        }
        if ($pid) {
          while ($i > 0) {
            pcntl_waitpid(-1, &$status);
            $val = pcntl_wexitstatus($status);
            --$i;
          }
          $this->putlog("Verification complete!", 3);
        }
      } else {
        // TODO - Bib verification for those poor saps w/o pcntl
      }
      $offset = $offset + $limit;
      $this->putlog("Collecting current data keys starting at $offset");
      $db = MDB2::connect($this->dsn);
      $sql = "SELECT stdnum,bib_lastupdate FROM locum_bib_items WHERE stdnum IS NOT NULL ORDER BY bib_lastupdate DESC LIMIT $limit OFFSET $offset";
      $init_result = $db->query($sql);
      $init_bib_arr = $init_result->fetchAll(MDB2_FETCHMODE_ASSOC);
    }
  }
  
  
  /************ External Content Functions ************/
  

  /**
   * Grabs the cover image URL for caching (much faster on the front-end to do it this way).
   * Will try amazon if the ini says so.
   *
   * @param string $stdnum_raw - stdnum/ISBN from the bib record
   * @return string Image URL or NULL
   */
  public function get_cover_img($stdnum_raw) {
    if (is_callable(array(__CLASS__ . '_hook', __FUNCTION__))) {
      eval('$hook = new ' . __CLASS__ . '_hook;');
      return $hook->{__FUNCTION__}($stdnum_raw);
    }

    // Format stdnum as best we can
    if (preg_match('/ /', $stdnum_raw)) {
      $stdnum_arr = explode(' ', $stdnum_raw);
      $stdnum = trim($stdnum_arr[0]);
    } else {
      $stdnum = trim($stdnum_raw);
    }
    $api_cfg = $this->locum_config['api_config'];
    $image_url = '';
    if ($api_cfg['use_amazon_images'] && $api_cfg['use_syndetic_images']) {
      if ($api_cfg['amazon_img_prio'] >= $api_cfg['syndetic_img_prio']) {
        $image_url = $this->get_amazon_image($stdnum, $api_cfg['amazon_access_key']);
        if (!$image_url) { $image_url = $this->get_syndetic_image($stdnum, $api_cfg['syndetic_custid']); }
      } else {
        $image_url = $this->get_syndetic_image($stdnum, $api_cfg['syndetic_custid']);
        if (!$image_url) { $image_url = $this->get_amazon_image($stdnum, $api_cfg['amazon_access_key']); }

      }
    } else if ($api_cfg['use_amazon_images']) {
      $image_url = $this->get_amazon_image($stdnum, $api_cfg['amazon_access_key']);
    } else if ($api_cfg['use_syndetic_images']) {
      $image_url = $this->get_syndetic_image($stdnum, $api_cfg['syndetic_custid']);
    }
    return $image_url;
  }

  /**
   * Used by get_cover_img to get the Amazon cover image URL.
   * You'll need to put in your own Amazon API key into the ini.
   *
   * @param string $stdnum Stdnum/ISBN
   * @param string $api_key Amazon API key - they're free.  Go git one.
   * @return string Cover image URL
   */
  public function get_amazon_image($stdnum, $api_key) {
    if (is_callable(array(__CLASS__ . '_hook', __FUNCTION__))) {
      eval('$hook = new ' . __CLASS__ . '_hook;');
      return $hook->{__FUNCTION__}($stdnum, $api_key);
    }
    
    $url =  'http://webservices.amazon.com/onca/xml?Service=AWSECommerceService';
    $url.=  "&AWSAccessKeyId=$api_key";
    $url.=  "&Operation=ItemLookup&IdType=ASIN&ItemId=$stdnum";
    $url.=  '&ResponseGroup=Medium,OfferFull';

    $az_dl = @file_get_contents($url);
    list($version, $status_code, $msg) = explode(' ', $http_response_header[0], 3);
    if ($status_code == '200') {
      $az = simplexml_load_string($az_dl);
      if (is_object($az->Items)) {
        if ($az->Items->Item->MediumImage->URL) {
          $image_url = trim($az->Items->Item->MediumImage->URL);
        }
      }
    }
    return $image_url;
  }

  /**
   * Used by get_cover_img to get the Syndetics cover image URL.
   * You'll need to put in your own customer ID into the ini.
   *
   * @param string $stdnum Stdnum/ISBN
   * @param string $cust_id Your syndetics ID - it's overpriced.  Go git one.
   * @return string Cover image URL
   */
  public function get_syndetic_image($stdnum, $cust_id) {
    if (is_callable(array(__CLASS__ . '_hook', __FUNCTION__))) {
      eval('$hook = new ' . __CLASS__ . '_hook;');
      return $hook->{__FUNCTION__}($stdnum, $cust_id);
    }
    
    $image_url = '';
    $url = 'http://www.syndetics.com/index.aspx?isbn=' . $stdnum . '/index.xml&client=' . $cust_id . '&type=xw10';
    $syn_dl = @file_get_contents($url);
    list($version, $status_code, $msg) = explode(' ', $http_response_header[0], 3);
    if (preg_match('/xml/', $syn_dl) && $status_code == '200') {
      $syn = simplexml_load_string($syn_dl);
      if ($syn->SC == 'SC.GIF') {
        $image_url = 'http://www.syndetics.com/index.php?type=hw7&isbn=' . $stdnum . '/SC.GIF&client=' . $cust_id;
        $img_size = @getimagesize($image_url);
        if ($img_size[0] == 1) { $image_url = ''; }
      }
    }
    return $image_url;
  }
  
  public function get_syndetics($isbn) {
    if (is_callable(array(__CLASS__ . '_hook', __FUNCTION__))) {
      eval('$hook = new ' . __CLASS__ . '_hook;');
      return $hook->{__FUNCTION__}($isbn);
    }
    
    $valid_hits = array(
      'TOC'         => 'Table of Contents',
      'BNATOC'      => 'Table of Contents',
      'FICTION'     => 'Fiction Profile',
      'SUMMARY'     => 'Summary / Annotation',
      'DBCHAPTER'   => 'Excerpt',
      'LJREVIEW'    => 'Library Journal Review',
      'PWREVIEW'    => 'Publishers Weekly Review',
      'SLJREVIEW'   => 'School Library Journal Review',
      'CHREVIEW'    => 'CHOICE Review',
      'BLREVIEW'    => 'Booklist Review',
      'HORNBOOK'    => 'Horn Book Review',
      'KIRKREVIEW'  => 'Kirkus Book Review',
      'ANOTES'      => 'Author Notes'
    );
    
    $cust_id = $this->locum_config['api_config']['syndetic_custid'];
    if (!$cust_id) { 
      return NULL;
    }
    
    $db =& MDB2::connect($this->dsn);
    $res = $db->query("SELECT links FROM locum_syndetics_links WHERE isbn = '$isbn' AND updated > DATE_SUB(NOW(), INTERVAL 2 MONTH) LIMIT 1");
    $dbres = $res->fetchAll(MDB2_FETCHMODE_ASSOC);
    
    if ($dbres[0]['links']) {
      $links = explode('|', $dbres[0]['links']);
    } else {
      $xmlurl = "http://www.syndetics.com/index.aspx?isbn=$isbn/index.xml&client=$cust_id&type=xw10";
      $xmlraw = file_get_contents($xmlurl);
      if (!preg_match('/error/', $xmlraw)) {
        // record found
        $xmlobj = (array) simplexml_load_string($xmlraw);
        $delimit = '';
        foreach ($xmlobj as $xkey => $xval) {
          if (array_key_exists($xkey, $valid_hits)) {
            $sqlfield .= $delimit . $xkey;
            $delimit = '|';
            $links[] = $xkey;
          }
        }
        if ($sqlfield) {
          $res = $db->query("INSERT INTO locum_syndetics_links VALUES ('$isbn', '$sqlfield', NOW())");
        }
      }
    }
    
    if ($links) {
      foreach ($links as $link) {
        $link_result[$valid_hits[$link]] = 'http://www.syndetics.com/index.aspx?isbn=' . $isbn . '/' . $link . '.html&client=' . $cust_id;
      }
    }
    $db->disconnect();
    return $link_result;
  }
  

}
