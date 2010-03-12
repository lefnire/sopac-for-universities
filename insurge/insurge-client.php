<?php
/**
 * Locum is a software library that abstracts bibliographic social catalog data
 * and functionality.  It can then be used in a variety of applications to both
 * consume and contribute data from the repository.
 * @package Insurge
 * @author John Blyberg
 */

require_once('insurge.php');

/**
 * The insurge client class provides the front-end functionality for accessing
 * and contributing social repository data within the context of the application
 * using this class.
 */
class insurge_client extends insurge {
  
  /**
   * Submits a bibliographic tags to the database.
   *
   * @param int $uid Unique user ID
   * @param array $bnum_arr Optional array of bib numbers to scope tag retrieval on.
   * @param string $tag_string A raw, unformatted string of tags to be processed.
   */
  public function submit_tags($uid, $bnum, $tag_string) {
    $db =& MDB2::connect($this->dsn);
    $group_id = $this->insurge_config['repository_info']['group_id'];
    $tag_arr = self::prepare_tag_string($tag_string);
    
    $dbq = $db->query('SELECT DISTINCT(tag) FROM insurge_tags WHERE bnum = ' . $bnum . ' AND uid = ' . $uid);
    $existing_tags = $dbq->fetchCol();
    foreach ($tag_arr as $tag) {
      if (!in_array($tag, $existing_tags)){
        $next_tid = $db->nextID('insurge_tags');
        $tag = $db->quote($tag, 'text');
        if ($group_id) {
          $repos_id = $group_id . '-' . $next_tid;
        }
        $sql = "INSERT INTO insurge_tags VALUES ($next_tid, '$repos_id', '$group_id', $uid, $bnum, $tag, NOW())";
        $res =& $db->exec($sql);
      }
    }
    
  }
  
  /**
   * Grabs an array of tags and their totals (weights).
   * 
   * @param int $uid Unique user ID
   * @param array $bnum_arr Optional array of bib numbers to scope tag retrieval on.
   * @param string $limit Limit the number of results returned.
   */
  public function get_tag_totals($uid = NULL, $bnum_arr = NULL, $tag_name = NULL, $rand = TRUE, $limit = 500, $offset = 0, $order = 'ORDER BY count DESC') {
    $db =& MDB2::connect($this->dsn);
    $group_id = $this->insurge_config['repository_info']['group_id'];
    $where_prefix = 'WHERE';
    if ($uid) { $where_str .= ' ' . $where_prefix . ' uid = ' . $uid . ' '; $where_prefix = 'AND'; }
    if ($group_id) { $where_str .= ' ' . $where_prefix . ' group_id = "' . $group_id . '" '; $where_prefix = 'AND'; }
    if ($tag_name) { $where_str .= ' ' . $where_prefix . ' tag = ' . $db->quote($tag_name, 'text'); $where_prefix = 'AND'; }
    if (count($bnum_arr)) { $where_str .= ' ' . $where_prefix . ' bnum IN (' . implode(', ', $bnum_arr) . ') '; $where_prefix = 'AND'; }
    $sql = 'SELECT tag, count(tag) AS count FROM insurge_tags ' . $where_str . ' GROUP BY tag ' . $order;
    if ($limit) { $sql .= " LIMIT $limit"; }
    if ($offset) { $sql .= " OFFSET $offset"; }
    $result =& $db->query($sql);
    $tag_result = $result->fetchAll(MDB2_FETCHMODE_ASSOC);
    if ($rand) { self::shuffle_with_keys(&$tag_result); }
    return $tag_result;
  }
  
  public function get_tagged_items($uid = NULL, $tag_name = NULL, $limit = 500, $offset = 0) {
    $db =& MDB2::connect($this->dsn);
    $group_id = $this->insurge_config['repository_info']['group_id'];
    $where_prefix = 'WHERE';
    if ($uid) { $where_str .= ' ' . $where_prefix . ' uid = ' . $uid . ' '; $where_prefix = 'AND'; }
    if ($group_id) { $where_str .= ' ' . $where_prefix . ' group_id = "' . $group_id . '" '; $where_prefix = 'AND'; }
    if ($tag_name) { $where_str .= ' ' . $where_prefix . ' tag = ' . $db->quote($tag_name, 'text'); $where_prefix = 'AND'; }
    
    $sql = 'SELECT count(*) FROM insurge_tags ' . $where_str;
    $dbq = $db->query($sql);
    $tag_result['total'] = $dbq->fetchOne();
    
    $sql = 'SELECT bnum FROM insurge_tags ' . $where_str;
    if ($limit) { $sql .= " LIMIT $limit"; }
    if ($offset) { $sql .= " OFFSET $offset"; }
    $result =& $db->query($sql);
    $tag_result['bnums'] = $result->fetchCol();
    return $tag_result;
  }
  
  /**
   * Takes a string of raw tag input and parses it into an array, ready to be processed 
   * into the database.
   *
   * @param string $raw_tag_string Raw tag input from an application.
   * @return array Array of tags, processed accoring to our rules.
   */
  public function prepare_tag_string($raw_tag_string) {
    $arTags = array();
    $cPhraseQuote = NULL;
    $sPhrase = NULL;

    // Define some constants
    static $sTokens = " \r\n\t";  // Space, Return, Newline, Tab
    static $sQuotes = "'\"";    // Single and Double Quotes

    do {
      $sToken = isset($sToken)? strtok($sTokens) : strtok($raw_tag_string, $sTokens);

      if ($sToken === FALSE) {
        $cPhraseQuote = NULL;
      } else {    
        if ($cPhraseQuote !== NULL) {
          if (substr($sToken, -1, 1) === $cPhraseQuote) {
            if (strlen($sToken) > 1) $sPhrase .= ((strlen($sPhrase) > 0)? ' ' : NULL) . substr($sToken, 0, -1);
            $cPhraseQuote = NULL;
          } else {
            $sPhrase .= ((strlen($sPhrase) > 0)? ' ' : NULL) . $sToken;
          }
        } else {
          if (strpos($sQuotes, $sToken[0]) !== FALSE) {
            if ((strlen($sToken) > 1) && ($sToken[0] === substr($sToken, -1, 1))) {
              $sPhrase = substr($sToken, 1, -1);
            } else {
              $sPhrase = substr($sToken, 1);
              $cPhraseQuote = $sToken[0];
            }
          } else {
            $sPhrase = $sToken;
          }
        }
      }

      if (($cPhraseQuote === NULL) && ($sPhrase != NULL)) {
        $sPhrase = strtolower($sPhrase);
        if (!in_array($sPhrase, $arTags)) $arTags[] = preg_replace('/,/s', '', $sPhrase); {
          $sPhrase = NULL;
        }
      }
    }
    while ($sToken !== FALSE);
    return $arTags;
  }

  /**
   * Updates a tag to something else.
   */
  public function update_tag($oldtag, $newtag, $uid = NULL, $tid = NULL, $bnum = NULL) {
    if ($oldtag != $newtag) {
      $db =& MDB2::connect($this->dsn);
      $where_prefix = 'AND';
      if ($uid) { $where_str .= ' ' . $where_prefix . ' uid = ' . $uid . ' '; }
      if ($tid) { $where_str .= ' ' . $where_prefix . ' tid = ' . $tid . ' '; }
      if ($bnum) { $where_str .= ' ' . $where_prefix . ' bnum = ' . $bnum . ' '; }
      $tag = $db->quote($newtag, 'text');
      $oldtag = $db->quote($oldtag, 'text');
      $sql = "UPDATE insurge_tags SET tag = $tag WHERE tag = $oldtag " . $where_str;
      $db->exec($sql);
    }
  }

  function delete_user_tag($uid, $tag, $bnum = NULL) {
    if ($uid && $tag) {
      $group_id = $this->insurge_config['repository_info']['group_id'];
      $db =& MDB2::connect($this->dsn);
      $tag = $db->quote($tag);
      $sql = "DELETE FROM insurge_tags WHERE uid = $uid AND group_id = '$group_id' AND tag = $tag";
      if ($bnum) { $sql .= " AND bnum = '$bnum'"; }
      $db->exec($sql);
    }
  }
  
  /**
   * Submits a bibliographic rating to the database.
   *
   * @param int $uid Unique user ID
   * @param array $bnum_arr Optional array of bib numbers to scope tag retrieval on.
   * @param int $value The submitted rating.
   */
  function submit_rating($uid, $bnum, $value) {
    $db =& MDB2::connect($this->dsn);
    $group_id = $this->insurge_config['repository_info']['group_id'];
    if ($group_id) {
      $repos_id = $group_id . '-' . $next_tid;
    }
    $sql = 'SELECT COUNT(rate_id) FROM insurge_ratings WHERE bnum = ' . $bnum . ' AND uid = ' . $uid . ' AND group_id = "' . $group_id . '"';
    $dbq =& $db->query('SELECT COUNT(rate_id) FROM insurge_ratings WHERE bnum = ' . $bnum . ' AND uid = ' . $uid . ' AND group_id = "' . $group_id . '"');
    $is_update = $dbq->fetchOne();
    if ($is_update > 1) {
      $db->query('DELETE FROM insurge_ratings WHERE bnum = ' . $bnum . ' AND uid = ' . $uid . ' AND group_id = "' . $group_id . '"');
      $is_update = FALSE;
    }
    if ($is_update) {
      $sql = 'UPDATE insurge_ratings SET rating = ' . $value . ' WHERE bnum = ' . $bnum . ' AND uid = ' . $uid . ' AND group_id = "' . $group_id . '"';
    } else {
      $next_rid = $db->nextID('insurge_ratings');
      if ($group_id) {
        $repos_id = $group_id . '-' . $next_rid;
      }
      $sql = "INSERT INTO insurge_ratings VALUES ($next_rid, '$repos_id', '$group_id', $uid, $bnum, $value, NOW())";
    }
    $res =& $db->exec($sql);
  }

  /**
   * Returns the average value and ratings count of a bib's rating.
   *
   * @param int $bnum Bib number
   * @param boolean $local_only If set to TRUE, this function will return results for your institution only.
   * @return array ratings count and average value if there are ratings.
   */
  function get_rating($bnum, $local_only = FALSE) {
    $db =& MDB2::connect($this->dsn);
    $group_id = $this->insurge_config['repository_info']['group_id'];
    $sql = 'SELECT AVG(rating) AS rating, COUNT(rate_id) AS rate_count FROM insurge_ratings WHERE bnum = ' . $bnum;
    if ($local_only) {
      $sql .= ' AND group_id = "' . $group_id . '"';
    }
    $dbq =& $db->query($sql);
    $avg_rating = $dbq->fetchRow(MDB2_FETCHMODE_ASSOC);
    $rating['count'] = $avg_rating['rate_count'];
    
    if($avg_rating['rating'] >= ($half = ($ceil = ceil($avg_rating['rating']))- 0.5) + 0.25) {
      $rating['value'] = $ceil;
    } else if ($avg_rating['rating'] < $half - 0.25) {
      $rating['value'] = floor($avg_rating['rating']);
    } else {
      $rating['value'] = $half;
    }
    return $rating;
  }
  
  function get_rating_list($uid = NULL, $bnum = NULL, $limit = 20, $offset = 0, $order = 'ORDER BY rating DESC') {
    $db =& MDB2::connect($this->dsn);
    $offset = $offset ? $offset : 0;
    $group_id = $this->insurge_config['repository_info']['group_id'];
    if ($uid) { $where_str .= ' ' . $where_prefix . ' uid = ' . $uid . ' '; $where_prefix = 'AND'; }
    if ($bnum) { $where_str .= ' ' . $where_prefix . ' bnum = ' . $bnum . ' '; $where_prefix = 'AND'; }
    if ($group_id) { $where_str .= ' ' . $where_prefix . ' group_id = "' . $group_id . '" '; $where_prefix = 'AND'; }
    $sql = 'SELECT count(*) FROM insurge_ratings WHERE ' . $where_str;
    $dbq = $db->query($sql);
    $ratings_arr['total'] = $dbq->fetchOne();
    $sql = 'SELECT rating, rate_id, bnum, UNIX_TIMESTAMP(rate_date) AS rate_date FROM insurge_ratings WHERE ' . $where_str . ' ' . $order . ' LIMIT ' . $limit . ' OFFSET ' . $offset;
    $dbq =& $db->query($sql);
    $ratings_arr['ratings'] = $dbq->fetchAll(MDB2_FETCHMODE_ASSOC);
    return $ratings_arr;
  }

  /**
   * Submits a review for insertion into the database.
   *
   * @param string|int $uid user ID
   * @param int $bnum Bib num
   * @param string $rev_title Title of the review
   * @param string $rev_body The review text
   */
  function submit_review($uid, $bnum, $rev_title, $rev_body) {
    $group_id = $this->insurge_config['repository_info']['group_id'];
    if ($uid && $bnum && $rev_title && $rev_body) {
      $db =& MDB2::connect($this->dsn);
      $next_rid = $db->nextID('insurge_reviews');
      if ($group_id) {
        $repos_id = $group_id . '-' . $next_rid;
      }
      $title_ready = $db->quote($rev_title, 'text');
      $rev_body = strip_tags($rev_body, '<b><i><u><strong>');
      $body_ready = $db->quote($rev_body, 'text');
      $sql = "INSERT INTO insurge_reviews VALUES ($next_rid, '$repos_id', '$group_id', '$uid', $bnum, $title_ready, $body_ready, NOW(), NOW())";
      $db->exec($sql);
    }
  }

  /**
   * Does review retrieval from the database.
   *
   * @param string|int $uid user ID
   * @param array $bnum_arr Array of bib nums to match
   * @param array $rev_id_arr Array of review ID to match
   * @param int $limit Result limiter
   * @param int $offset Result offset for purposes of paging
   */
  function get_reviews($uid = NULL, $bnum_arr = NULL, $rev_id_arr = NULL, $limit = 10, $offset = 0, $order = 'ORDER BY rev_create_date DESC') {
    $db =& MDB2::connect($this->dsn);
    $group_id = $this->insurge_config['repository_info']['group_id'];
    if ($uid) { $where_str .= ' ' . $where_prefix . ' uid = ' . $uid . ' '; $where_prefix = 'AND'; }
    if ($group_id) { $where_str .= ' ' . $where_prefix . ' group_id = "' . $group_id . '" '; $where_prefix = 'AND'; }
    if (count($bnum_arr)) { $where_str .= ' ' . $where_prefix . ' bnum IN (' . implode(', ', $bnum_arr) . ') '; $where_prefix = 'AND'; }
    if (count($rev_id_arr)) { $where_str .= ' ' . $where_prefix . ' rev_id IN (' . implode(', ', $rev_id_arr) . ') '; $where_prefix = 'AND'; }
    
    $sql = 'SELECT count(*) FROM insurge_reviews WHERE ' . $where_str;
    $dbq = $db->query($sql);
    $reviews['total'] = $dbq->fetchOne();
    
    $sql = 'SELECT rev_id, group_id, uid, bnum, rev_title, rev_body, UNIX_TIMESTAMP(rev_last_update) AS rev_last_update, UNIX_TIMESTAMP(rev_create_date) AS rev_create_date FROM insurge_reviews WHERE ' . $where_str . ' ' . $order . ' LIMIT ' . $limit . ' OFFSET ' . $offset;
    $dbq = $db->query($sql);
    $reviews['reviews'] = $dbq->fetchAll(MDB2_FETCHMODE_ASSOC);
    
    return $reviews;
  }

  function update_review($uid, $rev_id, $rev_title, $rev_body) {
    $db =& MDB2::connect($this->dsn);
    if ($rev_id) {
      $rev_title = $db->quote($rev_title, 'text');
      $rev_body = $db->quote($rev_body, 'text');
      if ($uid) { $where_str = ' AND uid = ' . $uid; }
      $sql = "UPDATE insurge_reviews SET rev_title = $rev_title, rev_body = $rev_body WHERE rev_id = $rev_id" . $where_str;
      $db->exec($sql);
    }
  }
  
  function delete_review($uid, $rev_id) {
    $db =& MDB2::connect($this->dsn);
    if ($uid && $rev_id) {
      if ($uid) { $where_str = ' AND uid = ' . $uid; }
      $sql = "DELETE FROM insurge_reviews WHERE rev_id = $rev_id" . $where_str;
      $db->exec($sql);
    }
  }

  /**
   * Checks to see if a $bnum has already been reviewed by $uid
   *
   * @param string|int $uid user ID
   * @param int $bnum Bib num
   * @return int Number of reviews that users has written for $bnum
   */
  function check_reviewed($uid, $bnum) {
    $db =& MDB2::connect($this->dsn);
    $group_id = $this->insurge_config['repository_info']['group_id'];
    $dbq = $db->query("SELECT COUNT(*) FROM insurge_reviews WHERE group_id = '$group_id' AND bnum = '$bnum' AND uid = '$uid'");
    return $dbq->fetchOne();
  }
  
  function add_checkout_history($uid, $bnum, $co_date, $title, $author) {
    $group_id = $this->insurge_config['repository_info']['group_id'];
    if ($uid && $bnum && $co_date) {
      $db =& MDB2::connect($this->dsn);
      $next_hist_id = $db->nextID('insurge_reviews');
      if ($group_id) {
        $repos_id = $group_id . '-' . $next_hist_id;
      }
      $title_txt = $db->quote($rev_body, 'text');
      $author_txt = $db->quote($rev_body, 'text');
      $sql = "INSERT INTO insurge_history VALUES ($next_hist_id, '$repos_id', '$group_id', $uid, $bnum, '$co_date', $title_txt, $author_txt)";
      $db->exec($sql);
    }
  }
  
  function get_checkout_history($uid = NULL, $limit = NULL, $offset = NULL) {
    $group_id = $this->insurge_config['repository_info']['group_id'];
    if ($uid) { $where_str .= ' ' . $where_prefix . ' uid = ' . $uid . ' '; $where_prefix = 'AND'; }
    if ($group_id) { $where_str .= ' ' . $where_prefix . ' group_id = "' . $group_id . '" '; $where_prefix = 'AND'; }
  }
  
  /**
   * Takes a reference to an array and shuffles it, preserving keys.
   *
   * @param array Reference to the array in question.
   */
  function shuffle_with_keys(&$array) {
    $aux = array();
    $keys = array_keys($array);
    shuffle($keys);
    foreach($keys as $key) {
      $aux[$key] = $array[$key];
      unset($array[$key]);
    }
    $array = $aux;
  }

}