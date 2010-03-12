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
class locum_server_hook extends locum {
  
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
            parent::putlog("Spawning child harvester to scan records $start - $end. PID is $pid ..");
          } else {
            sleep(1);
            ++$i;
            if ($i == $num_children) { $end++; }
            $result = self::import_bibs($start, $end);
            parent::putlog("Child process complete.  Scanned records $start - $end.  Imported " . $result['imported'] . " records and skipped $result[skipped] ..", 2);
            exit($i);
          }
        } else {
          parent::putlog("Unable to spawn harvester: ($i)", 5);
        }
        $start = $new_start;
      }
      if ($pid) {
        while ($i > 0) {
          pcntl_waitpid(-1, &$status);
          $val = pcntl_wexitstatus($status);
          --$i;
          }
        parent::putlog("Harvest complete!", 3);
      }
    } else {
      $result = self::import_bibs($start, $end);
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
          parent::putlog("Importing bib # $i - $bib[title]");
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
          $process_report['imported']++;
        }
      }
    }
    $db->disconnect();
    return $process_report;
  }
  
}

 /**
  * Override class for locum_client
  */
class locum_client_hook extends locum {
  
 /**
   * Does an index search via Sphinx and returns the results
   *
   * @param string $type Search type.  Valid types are: author, title, series, subject, keyword (default)
   * @param string $term Search term/phrase
   * @param int $limit Number of results to return
   * @param int $offset Where to begin result set -- for pagination purposes
   * @param array $sort_array Numerically keyed array of sort parameters.  Valid options are: newest, oldest
   * @param array $location_array Numerically keyed array of location params.  NOT IMPLEMENTED YET
   * @param array $facet_args String-keyed array of facet parameters. See code below for array structure
   * @return array String-keyed result set
   */
  public function search($type, $term, $limit, $offset, $sort_opt = NULL, $format_array = array(), $location_array = array(), $facet_args = array(), $override_search_filter = FALSE, $limit_available = FALSE) {
    
    if (is_callable(array(__CLASS__ . '_hook', __FUNCTION__))) {
      eval('$hook = new ' . __CLASS__ . '_hook;');
      return $hook->{__FUNCTION__}($type, $term, $limit, $offset, $sort_opt, $format_array, $location_array, $facet_args, $override_search_filter, $limit_available);
    }
    
    
    require_once($this->locum_config['sphinx_config']['api_path'] . '/sphinxapi.php');
    $db =& MDB2::connect($this->dsn);
    
    $term_arr = explode('?', trim(preg_replace('/\//', ' ', $term)));
    $term = trim($term_arr[0]);
    
    if ($term == '*' || $term == '**') { 
      $term = ''; 
    } else {
      $term_prestrip = $term;
      //$term = preg_replace('/[^A-Za-z0-9*\- ]/iD', '', $term);
      $term = preg_replace('/\*\*/','*', $term);
    }
    $final_result_set['term'] = $term;
    $final_result_set['type'] = trim($type);

    $cl = new SphinxClient();
    
    $cl->SetServer($this->locum_config['sphinx_config']['server_addr'], (int) $this->locum_config['sphinx_config']['server_port']);

    // Defaults to 'keyword', non-boolean
    $bool = FALSE;
    $cl->SetMatchMode(SPH_MATCH_ALL);
    
    if(!$term) {
      // Searches for everything (usually for browsing purposes--Hot/New Items, etc..)
      $cl->SetMatchMode(SPH_MATCH_ANY); 
    } else {
      
      // Is it a boolean search?
      if(preg_match("/ \| /i", $term) || preg_match("/ \-/i", $term) || preg_match("/ \!/i", $term)) {
        $cl->SetMatchMode(SPH_MATCH_BOOLEAN); 
        $bool = TRUE;
      }
      if(preg_match("/ OR /i", $term)) {
        $cl->SetMatchMode(SPH_MATCH_BOOLEAN);
        $term = preg_replace('/ OR /i',' | ',$term);
        $bool = TRUE;
      }
      
      // Is it a phrase search?
      if(preg_match("/\"/i", $term) || preg_match("/\@/i", $term)) {
        $cl->SetMatchMode(SPH_MATCH_EXTENDED2);
        $bool = TRUE;
      }
    }
    
    // Set up for the various search types
    switch ($type) {
      case 'author':
        $cl->SetFieldWeights(array('author' => 50, 'addl_author' => 30));
        $idx = 'bib_items_author';
        break;
      case 'title':
        $cl->SetFieldWeights(array('title' => 50, 'title_medium' => 50, 'series' => 30));
        $idx = 'bib_items_title';
        break;
      case 'series':
        $cl->SetFieldWeights(array('title' => 5, 'series' => 80));
        $idx = 'bib_items_title';
        break;
      case 'subject':
        $idx = 'bib_items_subject';
        break;
      case 'callnum':
        $cl->SetFieldWeights(array('callnum' => 100));
        $idx = 'bib_items_callnum';
        //$cl->SetMatchMode(SPH_MATCH_ANY);
        break;
      case 'tags':
        $cl->SetFieldWeights(array('tag_idx' => 100));
        $idx = 'bib_items_tags';
        $cl->SetMatchMode(SPH_MATCH_PHRASE);
        break;
      case 'reviews':
        $cl->SetFieldWeights(array('review_idx' => 100));
        $idx = 'bib_items_reviews';
        break;
      case 'keyword':
      default:
        $cl->SetFieldWeights(array('title' => 50, 'title_medium' => 50, 'author' => 70, 'addl_author' => 40, 'tag_idx' =>35, 'series' => 25, 'review_idx' => 10, 'notes' => 10, 'subjects' => 5 ));
        $idx = 'bib_items_keyword';
        break;

    }

    // Filter out the records we don't want shown, per locum.ini
    if (!$override_search_filter) {
      if (trim($this->locum_config['location_limits']['no_search'])) {
        $cfg_filter_arr = parent::csv_parser($this->locum_config['location_limits']['no_search']);
        foreach ($cfg_filter_arr as $cfg_filter) {
          $cfg_filter_vals[] = parent::string_poly($cfg_filter);
        }
        $cl->SetFilter('loc_code', $cfg_filter_vals, TRUE);
      }
    }

    // Valid sort types are 'newest' and 'oldest'.  Default is relevance.
    switch($sort_opt) {
      case 'newest':
        $cl->SetSortMode(SPH_SORT_EXTENDED, 'pub_year DESC, @relevance DESC');
        break;
      case 'oldest':
        $cl->SetSortMode(SPH_SORT_EXTENDED, 'pub_year ASC, @relevance DESC');
        break;
      case 'catalog_newest':
        $cl->SetSortMode(SPH_SORT_EXTENDED, 'bib_created DESC, @relevance DESC');
        break;
      case 'catalog_oldest':
        $cl->SetSortMode(SPH_SORT_EXTENDED, 'bib_created ASC, @relevance DESC');
        break;
      case 'title':
        $cl->SetSortMode(SPH_SORT_ATTR_ASC, 'title_ord');
        break;
      case 'author':
        $cl->SetSortMode(SPH_SORT_EXTENDED, 'author_null ASC, author_ord ASC');
        break;
      case 'top_rated':
        $cl->SetSortMode(SPH_SORT_ATTR_DESC, 'rating_idx');
        break;
      case 'popular_week':
        $cl->SetSortMode(SPH_SORT_ATTR_DESC, 'hold_count_week');
        break;
      case 'popular_month':
        $cl->SetSortMode(SPH_SORT_ATTR_DESC, 'hold_count_month');
        break;
      case 'popular_year':
        $cl->SetSortMode(SPH_SORT_ATTR_DESC, 'hold_count_year');
        break;
      case 'popular_total':
        $cl->SetSortMode(SPH_SORT_ATTR_DESC, 'hold_count_total');
        break;
      case 'atoz':
        $cl->SetSortMode(SPH_SORT_ATTR_ASC, 'title_ord');
        break;
      case 'ztoa':
        $cl->SetSortMode(SPH_SORT_ATTR_DESC, 'title_ord');
        break;
      case 'format':
        $cl->SetSortMode(SPH_SORT_ATTR_DESC, 'mat_code');
        break;
      case 'loc_code':
        $cl->SetSortMode(SPH_SORT_ATTR_DESC, 'loc_code');
        break;
      default:
        if ($type == 'title') {
          // We get better results in title matches if we also rank by title length
          $cl->SetSortMode(SPH_SORT_EXTENDED, 'titlelength ASC, @relevance DESC');
        } else {
          $cl->SetSortMode(SPH_SORT_EXTENDED, '@relevance DESC');
        }
        break;
    }

    // Filter by material types
    if (is_array($format_array)) {
      foreach ($format_array as $format) {
        if (strtolower($format) != 'all') {
          $filter_arr_mat[] = parent::string_poly(trim($format));
        }
      }
      if (count($filter_arr_mat)) { $cl->SetFilter('mat_code', $filter_arr_mat); }
    }
    
    // Filter by location
    if (count($location_array)) {
      foreach ($location_array as $location) {
        if (strtolower($location) != 'all') {
          $filter_arr_loc[] = parent::string_poly(trim($location));
        }
      }
      if (count($filter_arr_loc)) { $cl->SetFilter('loc_code', $filter_arr_loc); }
    }

    $cl->SetRankingMode(SPH_RANK_WORDCOUNT);
    $cl->SetLimits(0, 5000, 5000);
    $sph_res_all = $cl->Query($term, $idx); // Grab all the data for the facetizer
    
    // If original match didn't return any results, try a proximity search
    if(empty($sph_res_all['matches']) && $bool == FALSE && $term != "*" && $type != "tags") {
      $term = '"' . $term . '"/1';
      $cl->SetMatchMode(SPH_MATCH_EXTENDED2);
      $sph_res_all = $cl->Query($term, $idx);
      $forcedchange = 'yes';
    }
    
    // Paging/browsing through the result set.
    $cl->SetLimits((int) $offset, (int) $limit);

    // And finally.... we search.
    $sph_res = $cl->Query($term, $idx);

    // Include descriptors
    $final_result_set['num_hits'] = $sph_res['total'];
    if ($sph_res['total'] <= $this->locum_config['api_config']['suggestion_threshold']) {
      if ($this->locum_config['api_config']['use_yahoo_suggest'] == TRUE) {
        $final_result_set['suggestion'] = self::yahoo_suggest($term_prestrip);
      }
    }
    
    if (is_array($sph_res['matches'])) {
      foreach ($sph_res['matches'] as $bnum => $attr) {
        $bib_hits[] = $bnum;
      }
    }
    if (is_array($sph_res_all['matches'])) {
      foreach ($sph_res_all['matches'] as $bnum => $attr) {
        $bib_hits_all[] = $bnum;
      }
    }
    
    // Limit list to available
    if ($limit_available && $final_result_set['num_hits'] && (array_key_exists($limit_available, $this->locum_config['branches']) || $limit_available == 'any')) {
      
      $limit_available = trim(strval($limit_available));
      
      // Remove bibs that we know are not available
      $cache_cutoff = date("Y-m-d H:i:00", time() - (60 * $this->locum_config['avail_cache']['cache_cutoff']));
      
      // Remove bibs that are not in this location
      $utf = "SET NAMES 'utf8' COLLATE 'utf8_unicode_ci'";
      $utfprep = $db->query($utf);

      $sql = "SELECT bnum, branch, count_avail FROM locum_avail_branches WHERE bnum IN (" . implode(", ", $bib_hits_all) . ") AND timestamp > '$cache_cutoff'";
      $init_result =& $db->query($sql);
      if ($init_result) {
        $branch_info_cache = $init_result->fetchAll(MDB2_FETCHMODE_ASSOC);
        $bad_bibs = array();
        $good_bibs = array();
        foreach ($branch_info_cache as $item_binfo) {
          if (($item_binfo['branch'] == $limit_available || $limit_available == 'any') && $item_binfo['count_avail'] > 0) {
            if (!in_array($item_binfo['bnum'], $good_bibs)) {
              $good_bibs[] = $item_binfo['bnum'];
            }
          } else {
            $bad_bibs[] = $item_binfo['bnum'];
          }
        }
      }
      $unavail_bibs = array_values(array_diff($bad_bibs, $good_bibs));
      $bib_hits_all = array_values(array_diff($bib_hits_all, $unavail_bibs));

      // rebuild from the full list
      unset($bib_hits);
      $available_count = 0;
      foreach ($bib_hits_all as $key => $bib_hit) {
        $bib_avail = self::get_item_status($bib_hit);
        if ($limit_available == 'any') {
          $available = $bib_avail['avail'];
        } else {
          $available = $bib_avail['branches'][$limit_available]['avail'];
        }
        if ($available) {
          $available_count++;
          if ($available_count > $offset) {
            $bib_hits[] = $bib_hit;
            if (count($bib_hits) == $limit) {
              //found as many as we need for this page
              break;
            }
          }
        } else {
          // remove the bib from the bib_hits_all array
          unset($bib_hits_all[$key]);
        }
      }
      
      // trim out the rest of the array based on *any* cache value
      if(!empty($bib_hits_all)) {
        $sql = "SELECT bnum FROM locum_avail_branches WHERE bnum IN (" . implode(",", $bib_hits_all) . ") AND count_avail > 0";
        $init_result =& $db->query($sql);
        if ($init_result) {
          $avail_bib_arr = $init_result->fetchCol();
          foreach ($bib_hits_all as $bnum_avail_chk) {
            if (in_array($bnum_avail_chk, $avail_bib_arr)) {
              $new_bib_hits_all[] = $bnum_avail_chk;
            }
          }
        }
        $bib_hits_all = $new_bib_hits_all;
        unset($new_bib_hits_all);
      }
    }

    // Refine by facets
    
    if (count($facet_args)) {
      $where = '';

      // Series
      if ($facet_args['facet_series']) {
        $where .= ' AND (';
        $or = '';
        foreach ($facet_args['facet_series'] as $series) {
          $where .= $or . ' series LIKE \'' . $db->escape($series, 'text') . '%\'';
          $or = ' OR';
        }
        $where .= ')';
      }

      // Language
      if ($facet_args['facet_lang']) {
        foreach ($facet_args['facet_lang'] as $lang) {
          $lang_arr[] = $db->quote($lang, 'text');
        }
        $where .= ' AND lang IN (' . implode(', ', $lang_arr) . ')';
      }
      
      // Pub. Year
      if ($facet_args['facet_year']) {
        $where .= ' AND pub_year IN (' . implode(', ', $facet_args['facet_year']) . ')';
      }
      
      // Pub. Decade
      if ($facet_args['facet_decade']) {
        $where .= ' AND pub_decade IN (' . implode(', ', $facet_args['facet_decade']) . ')';
      }
      
      // Ages
      if (count($facet_args['age'])) {
        $age_or = '';
        $age_sql_cond = '';
        foreach ($facet_args['age'] as $facet_age) {
          $age_sql_cond .= $age_or . "age = '$facet_age'";
          $age_or = ' OR ';
        }
        $sql = 'SELECT DISTINCT(bnum) FROM locum_avail_ages WHERE bnum IN (' . implode(', ', $bib_hits_all) . ") AND ($age_sql_cond)";
        $init_result =& $db->query($sql);
        $age_hits = $init_result->fetchCol();
        foreach ($bib_hits_all as $bnum_age_chk) {
          if (in_array($bnum_age_chk, $age_hits)) {
            $new_bib_hits_all[] = $bnum_age_chk;
          }
        }
        $bib_hits_all = $new_bib_hits_all;
        unset($new_bib_hits_all);
      }
      
      if(!empty($bib_hits_all)) {
        $sql1 = 'SELECT bnum FROM locum_facet_heap WHERE bnum IN (' . implode(', ', $bib_hits_all) . ')' . $where;
        $sql2 = 'SELECT bnum FROM locum_facet_heap WHERE bnum IN (' . implode(', ', $bib_hits_all) . ')' . $where . " LIMIT $offset, $limit";
        $utf = "SET NAMES 'utf8' COLLATE 'utf8_unicode_ci'";
        $utfprep = $db->query($utf);
        $init_result =& $db->query($sql1);
        $bib_hits_all = $init_result->fetchCol();
        $init_result =& $db->query($sql2);
        $bib_hits = $init_result->fetchCol();
      }

    }
    
    // Get the totals
    $facet_total = count($bib_hits_all);
    $final_result_set['num_hits'] = $facet_total;
    
    // First, we have to get the values back, unsorted against the Sphinx-sorted array
    if (count($bib_hits)) {
      $sql = 'SELECT * FROM locum_bib_items WHERE bnum IN (' . implode(', ', $bib_hits) . ')';
      $utf = "SET NAMES 'utf8' COLLATE 'utf8_unicode_ci'";
      $utfprep = $db->query($utf);
      $init_result =& $db->query($sql);
      $init_bib_arr = $init_result->fetchAll(MDB2_FETCHMODE_ASSOC);
      foreach ($init_bib_arr as $init_bib) {
        // Get availability
        $init_bib['availability'] = self::get_item_status($init_bib['bnum']);
        // Clean up the Stdnum
        $init_bib['stdnum'] = preg_replace('/[^\d]/','', $init_bib['stdnum']);
        $bib_reference_arr[(string) $init_bib['bnum']] = $init_bib;
      }

      // Now we reconcile against the sphinx result
      foreach ($sph_res_all['matches'] as $sph_bnum => $sph_binfo) {
        if (in_array($sph_bnum, $bib_hits)) {
          $final_result_set['results'][] = $bib_reference_arr[$sph_bnum];
        }
      }
    }
    
    $db->disconnect();
    $final_result_set['facets'] = self::facetizer($bib_hits_all);
    if($forcedchange == 'yes') { $final_result_set['changed'] = 'yes'; }
    
    return $final_result_set;

  }
  
}