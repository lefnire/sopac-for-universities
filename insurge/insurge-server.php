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
 * The insurge server class provides the back-end functionality for serving social
 * repository data to the insurge client pieces.
 */
class insurge_server extends insurge {

  /**
   * Creates the index table for Sphinx to do its discovery.
   */
  public function rebuild_index_table() {
    $db =& MDB2::connect($this->dsn);
    
    // Reset the index table
    $db->query('DELETE FROM insurge_index');
    $db->query('INSERT INTO insurge_index (bnum) SELECT locum_bib_items.bnum FROM locum_bib_items');
    
    // Do the ratings.
    
    // Get the average rating for all entries
    $dbq = $db->query("SELECT AVG(rating) FROM insurge_ratings");
    $avg_rating_total = $dbq->fetchOne();
    
    // Get totals and averages
    $dbq = $db->query("SELECT DISTINCT(bnum) AS bnum, COUNT(*) AS vote_count, AVG(rating) AS avg_rating FROM insurge_ratings GROUP BY bnum");
    $item_tot_avg_arr = $dbq->fetchAll(MDB2_FETCHMODE_ASSOC);
    $vote_count = 0;
    // First time through...
    foreach ($item_tot_avg_arr as $item_tot_avg) {
      $vote_count = $vote_count + $item_tot_avg['vote_count'];
    }
    if (count($item_tot_avg_arr)) {
      $vote_count_avg = $vote_count / count($item_tot_avg_arr);
    } else {
      $vote_count_avg = 0;
    }
    
    // Second time through... Create the baysian rank value
    // FYI, eq is: ( (avg_num_votes * avg_rating) + (this_num_votes * this_rating) ) / (avg_num_votes + this_num_votes)
    if ($vote_count_avg) {
      $ratings_rank = array();
      foreach ($item_tot_avg_arr as $item_tot_avg) {
        $ratings_rank[$item_tot_avg['bnum']] = (($vote_count_avg * $avg_rating_total) + ($item_tot_avg['vote_count'] * $item_tot_avg['avg_rating'])) / ($vote_count_avg + $item_tot_avg['vote_count']);
      }
      unset($item_tot_avg_arr); // Free up the memory
      foreach ($ratings_rank as $bnum => $rating) {
        $rating_prepped = round($rating, 6) * 1000000;
        $db->exec("UPDATE insurge_index SET rating_idx = '$rating_prepped' WHERE bnum = '$bnum'");
      }
    }
    
    /* TODO:
    <eby> jblyberg: tag and review indexing can be done in single sql statements i think
    <eby> UPDATE insurge_index, (SELECT bnum, group_concat(rev_title,' ',rev_body) as review FROM insurge_reviews GROUP BY bnum) as temprev
          SET insurge_index.review_idx = temprev.review WHERE insurge_index.bnum = temprev.bnum
    <eby> UPDATE insurge_index, (SELECT bnum, group_concat(tag SEPARATOR ' ') as tag FROM insurge_tags GROUP BY bnum) as tagtemp SET
          insurge_index.tag_idx = tagtemp.tag WHERE insurge_index.bnum = tagtemp.bnum
    */
    
    // Now for the tags.
    $dbq = $db->query("SELECT bnum, tag FROM insurge_tags");
    $tag_arr = $dbq->fetchAll(MDB2_FETCHMODE_ASSOC);
    $tags = array();
    foreach ($tag_arr as $tag_vals) {
      $tags[$tag_vals['bnum']] .= ' ' . $tag_vals['tag'];
    }
    unset($tag_arr); // Free up the memory
    foreach ($tags as $bnum => $tag) {
      $tag = $db->quote(trim($tag), 'text');
      $db->exec("UPDATE insurge_index SET tag_idx = $tag WHERE bnum = '$bnum'");
    }
    
    // And finally, the reviews.
    $dbq = $db->query("SELECT bnum, rev_title, rev_body FROM insurge_reviews");
    $rev_arr = $dbq->fetchAll(MDB2_FETCHMODE_ASSOC);
    $rev = array();
    foreach ($rev_arr as $rev_vals) {
      $rev[$rev_vals['bnum']] .= ' ' . $rev_vals['rev_title'] . ' ' . $rev_vals['rev_body'];
    }
    unset ($rev_arr); // Free up the memory
    foreach ($rev as $bnum => $rev_text) {
      $rev_text = $db->quote(trim($rev_text), 'text');
      $db->exec("UPDATE insurge_index SET review_idx = $rev_text WHERE bnum = '$bnum'");
    }
    
  }
  
}