<?php
/*
 * Item record display template
 */

// Set the page title
drupal_set_title(ucwords($item['title']));

// Set up some variables.
$url_prefix = variable_get('sopac_url_prefix', 'cat/seek');
$new_author_str = sopac_author_format($item['author'], $item['addl_author']);
$dl_mat_codes = in_array($item['mat_code'], $locum->csv_parser($locum_config['format_special']['download']));
$no_avail_mat_codes = in_array($item['mat_code'], $locum->csv_parser($locum_config['format_special']['skip_avail']));
$location_label = $item['loc_code'] || ($item['loc_code'] != 'none') ? $locum_config['locations'][$item['loc_code']] : '';
$note_arr = unserialize($item['notes']);

$series = trim($item['series']);
if ($split_pos = max(strpos($series, ";"), strpos($series, ":"), strpos($series, "."), 0)) {
  $series = trim(substr($series, 0, $split_pos));
}

// Construct the availabilty summary.
if ($item_status['avail'] == 0 && $item_status['holds'] > 0) {
  $class = "holds";
  $reqtext = "There are no copies available. " . $item_status['holds'] . " request" .
  ($item_status['holds'] == 1 ? '' : 's') . " on " . $item_status['total'] . ($item_status['total'] == 1 ? ' copy' : ' copies') . '.';
}
elseif ($item_status['avail'] == 0) {
  $class = "first";
  $reqtext = "There are no copies available.";
}
elseif($item_status['holds'] > 0) {
  $class = "holds";
  $reqtext = "There " . ($item_status['avail'] == 1 ? 'is' : 'are') . " currently $item_status[avail] available and " . $item_status['holds'] . " request" . ($item_status['holds'] == 1 ? '' : 's') . " on " . $item_status['total'] . ' ' . ($item_status['total'] == 1 ? 'copy' : 'copies');
}
else {
  $class = "avail";
  $reqtext = "There " . ($item_status['avail'] == 1 ? 'is' : 'are') . " currently $item_status[avail] available.";
}

// Build the item availability array
if (count($item_status['items'])) {
  foreach ($item_status['items'] as $copy_status) {
    if ($copy_status['avail'] > 0) {
      $copy_tag = ($copy_status['avail'] == 1) ? t('copy available') : t('copies available');
      $status_msg = $copy_status['avail'] . ' ' . $copy_tag;
    }
    elseif ($copy_status['due']) {
      $status_msg = t('Next copy due') . ' ' . date('n-j-Y', $copy_status['due']);
    }
    else {
      $status_msg = $copy_status['statusmsg'];
    }
    if (variable_get('sopac_multi_branch_enable', 0)) {
      $copy_status_array[] = array($copy_status['location'], $copy_status['callnum'], $locum_config['branches'][$copy_status['branch']], $status_msg);
    }
    else {
      $copy_status_array[] = array($copy_status['location'], $copy_status['callnum'], $status_msg);
    }
  }
}

if (sopac_prev_search_url(TRUE)) {
  // changed because original version doesn't support absolute URL
  print '<p><a href="'.sopac_prev_search_url().'">&#171; Return to your search</a></p>';
}

?>

<!-- begin item record -->
<div class="itemrecord">

  <!-- begin left-hand column -->
  <div class="item-left">

    <!-- Cover Image -->
    <?php
    if (!module_exists('covercache')) {
      if (strpos($item['cover_img'], 'http://') !== FALSE) {
        $cover_img = $item['cover_img'];
      }
      else {
        $cover_img = base_path() . drupal_get_path('module', 'sopac') . '/images/nocover.png';
      }
      $cover_img = '<img class="item-cover" width="152" src="' . $cover_img . '">';
    }
    print $cover_img;
    ?>

    <!-- Ratings -->
    <?php
    if (variable_get('sopac_social_enable', 1)) {
      print '<div class="item-rating">';
      print theme_sopac_get_rating_stars($item['bnum']);
      print '</div>';
    }
    ?>

    <!-- Item Details -->
    <ul>
      <?php
      if ($item['pub_info']) {
        print '<li><b>Published:</b> ' . $item['pub_info'] . '</li>';
      }
      if ($item['pub_year']) {
        print '<li><b>Year Published:</b> ' . $item['pub_year'] . '</li>';
      }
      if ($item['series']) {
        print '<li><b>Series:</b> ' . l($item['series'], $url_prefix . '/search/series/' . urlencode($series)) . '</li>';
      }
      if ($item['edition']) {
        print '<li><b>Edition:</b> ' . $item['edition'] . '</li>';
      }
      if ($item['descr']) {
        print '<li><b>Description:</b> ' . nl2br($item['descr']) . '</li>';
      }
      if ($item['stdnum']) {
        print '<li><b>ISBN/Standard #:</b>' . $item['stdnum'] . '</li>';
      }
      if ($item['lang']) {
        print '<li><b>Language:</b> ' . $locum_config['languages'][$item['lang']] . '</li>';
      }
      if ($item['mat_code']) {
        print '<li><b>Format:</b> ' . $locum_config['formats'][$item['mat_code']] . '</li>';
      }
      ?>
    </ul>

    <!-- Additional Credits -->
    <?php
    if ($item['addl_author']) {
      print '<h3>Additional Credits</h3><ul>';
      $addl_author_arr = unserialize($item['addl_author']);
      if(!empty($addl_author_arr)){
      foreach ($addl_author_arr as $addl_author) {
        $addl_author_link = $url_prefix . '/search/author/%22' . urlencode($addl_author) .'%22';
        print '<li>' . l($addl_author, $addl_author_link) . '</li>';
      }
      }
      print '</ul>';
    }
    ?>

    <!-- Subject Headings -->
    <?php
    if ($item['subjects']) {
      print '<h3>Subjects</h3><ul>';
      $subj_arr = unserialize($item['subjects']);
      if (is_array($subj_arr)) {
        foreach ($subj_arr as $subj) {
          $subjurl = $url_prefix . '/search/subject/%22' . urlencode($subj) . '%22';
          print '<li>' . l($subj, $subjurl) . '</li>';
        }
      }
      print '</ul>';
    }
    ?>

    <!-- Tags -->
    <?php
    if (variable_get('sopac_social_enable', 1)) {
      print '<h3>Tags</h3>';
      $block = module_invoke('sopac','block','view', 4);
      print $block['content'];
    }
    ?>

  <!-- end left-hand column -->
  </div>


  <!-- begin right-hand column -->
  <div class="item-right">

    <!-- Supressed record notification -->
    <?php
    if ($item['active'] == '0') {
      print '<div class="suppressed">This Record is Suppressed</div>';
    }
    ?>

    <!-- Item Title -->
    <h1>
      <?php
      print ucwords($item['title']);
      if ($item['title_medium']) {
        print " $item[title_medium]";
      }
      ?>
    </h1>

    <!-- Item Format Icon -->
    <ul class="item-format-icon">
      <li><img src="<?php print '/' . drupal_get_path('module', 'sopac') . '/images/' . $item['mat_code'] . '.gif' ?>"></li>
      <li style="margin-top: -2px;"><?php print wordwrap($locum_config['formats'][$item['mat_code']], 8, '<br />'); ?></li>
    </ul>

    <!-- Item Author -->
    <?php
    if ($item['author']) {
      $authorurl = $url_prefix . '/search/author/' . $new_author_str;
      print '<h3>by ' . l($new_author_str, $authorurl) . '</h3>';
    }
    ?>

    <!-- Request Link -->
    <?php
    if (!in_array($item['loc_code'], $no_circ) && !$item['download_link']) {
      print '<div class="item-request">';
      print '<p>' . sopac_put_request_link($item['bnum'], 1, 0, $locum_config['formats'][$item['mat_code']]) . '</p>';
      print '<h3>' . $reqtext . '</h3>';
      print '</div>';
    }
    ?>

    <!-- Where to find it -->
    <div class="item-avail-disp">
      <h2>Where To Find It</h2>
      <?php
      // http://vmubuntu02.ckm.ucsf.edu/library/node/105, I think we'll want it back
      /* if ($item_status['callnums']) {
        print '<p>Call number: <strong>' . $item['callnum'] . '</strong></p>'; 
        // print '<p>Call number: <strong>' . implode(", ", $item_status['callnums']) . '</strong></p>';
      }*/

      if ( (count($item_status['items']) && !$no_avail_mat_codes) || $item['download_link'] || $item['holdings'] ){
      if (count($item_status['items']) && !$no_avail_mat_codes) {
//        print '<div><fieldset class="collapsible collapsed"><legend>Show All Copies (' . count($item_status['items']) . ')</legend><div>';
        drupal_add_js(drupal_get_path('theme', 'ucsf_theme').'/sopac/js/ucsf_sopac.js');
        $attributes = array('id' => 'sopac-status-location');
        if (variable_get('sopac_multi_branch_enable', 0)) {
          print theme('table', array("Location", "Call Number", "Branch", "Item Status"), $copy_status_array, $attributes);
        }
        else {
          print theme('table', array("Location", "Call Number", "Item Status"), $copy_status_array, $attributes);
        }
//        print '</div></fieldset></div>';
      }
      if ($item['download_link']) {
//        print '<div class="item-request">';
//        print '<p>' . l(t('Download this Title'), $item['download_link'], array('attributes' => array('target' => '_new'))) . '</p>';
//        print '</div>';
          print "<h3>Available Online</h3>";
          print $item['download_link'];
      }
      
      
      //TODO: holdings and links should come from the marc record, temporarily screen-scraping. screen-scrape (see locum_iii_2007[352])
      print "<table>{$item_status['holdings_html']}</table>";
      
      
      
      }
      else {
        if (!$no_avail_mat_codes) {
          print '<p>No copies found.</p>';
        }
      }
      if (count($item_status['orders'])) {
        print '<p>' . implode("</p><p>", $item_status['orders']) . '</p>';
      }
      ?>
    </div>

    <!-- Notes / Additional Details -->
    <?php
    if (is_array($note_arr)) {
      print '<div id="item-notes">';
      print '<h2>Additional Details</h2>';
      foreach($note_arr as $note) {
        print '<p>' . $note . '</p>';
      }

      // UCSF Specific
      // see https://spreadsheets.google.com/ccc?key=0AtLAcO_9VGLPdGd1Zk9JajdsSGxIZzYzb3VNZ1o0anc&hl=en&pli=1#gid=0
      if($item['continues'])    { print "<strong>Previous Title</strong>: {$item['continues']}";}
      if($item['cont_d_by'])    { print "<strong>Superceded By</strong>: {$item['cont_d_by']}";}
      if($item['related_work']) { print "<strong>Related Work</strong>: {$item['related_work']}";}
      if($item['local_note'])   { print "<strong>Local Notes</strong>: {$item['local_note']}";}
      if($item['oclc'])         { print "<strong>OCLC</strong>: {$item['oclc']}";}
      if($item['doc_number'])   { print "<strong>Doc Number</strong>: {$item['doc_number']}";}
      if($item['hldgs_stat'])   { print "<strong>Holdings Status: {$item['hldgs_stat']}";}
      
      print '</div>';
    }
    ?>
    
    <!-- Subject Headings -->
    <?php
    if ($item['alt_title']) {
      print '<div id="alt-titles">';
      print '<h2>Alternative Titles</h2><p><ul>';
      $alt_titles = unserialize($item['alt_title']);
      if (is_array($alt_titles)) {
        foreach ($alt_titles as $alt_title) {
          $alt_title_url = '/' . $url_prefix . '/search/title/%22' . urlencode($alt_title) . '%22';
//          print '<li><a href="' . $alt_title_url. '">' . $alt_title . '</a></li>';
          print "<li>$alt_title</li>";
        }
      }
      print '</ul></p>';
      print '</div>';
    }
    ?>
    
    <!-- Syndetics / Review Links -->
    <?php
    if ($item['review_links']) {
      print '<div id="item-syndetics">';
      print '<h2>Reviews &amp; Summaries</h2>';
      print '<ul>';
      foreach ($item['review_links'] as $rev_title => $rev_link) {
        print '<li>' . l($rev_title, $rev_link, array('attributes' => array('target' => '_new'))) . '</li>';
      }
      print '</ul></div>';
    }
    ?>

    <!-- Community / SOPAC Reviews -->
    <?php if (variable_get('sopac_social_enable', 1)) : ?>
    <div id="item-reviews">
      <h2>Community Reviews</h2>
      <?php
      if (count($rev_arr)) {
        foreach ($rev_arr as $rev_item) {
          print '<div class="hreview">';
          print '<h3 class="summary">' . l($rev_item['rev_title'], 'review/view/' . $rev_item['rev_id'], array('attributes' => array('class' => 'fn url'))) . '</h3>';
          if ($rev_item['uid']) {
            $rev_user = user_load(array('uid' => $rev_item['uid']));
            print '<p class="review-byline">submitted by <span class="review-author">' . l($rev_user->name, 'review/user/' . $rev_item['uid']) . ' on <abbr class="dtreviewed" title="' . date("c", $rev_item['timestamp']) . '">' . date("F j, Y, g:i a", $rev_item['timestamp']) . '</abbr></span>';
            if ($user->uid == $rev_item['uid']) {
              print ' &nbsp; [ ' .
                    l(t('delete'), 'review/delete/' . $rev_item['rev_id'], array('attributes' => array('title' => 'Delete this review'), 'query' => array('ref' => $_GET['q']))) .
                    ' ] [ ' .
                    l(t('edit'), 'review/edit/' . $rev_item['rev_id'], array('attributes' => array('title' => 'Edit this review'), 'query' => array('ref' => $_GET['q']))) .
                    ' ]';
            }
            print '</p>';
          }
          print '<div class="review-body description">' . nl2br($rev_item['rev_body']) . '</div></div>';
        }
      }
      else {
        print '<p>No reviews have been written yet.  You could be the first!</p>';
      }
      print $rev_form ? $rev_form : '<p>' . l(t('Login'), 'user/login', array('query' => array('destination' => $_GET['q']))) . ' to write a review of your own.</p>';
      ?>
    </div>
    <?php endif; ?>

    <!-- Google Books Preview -->
    <div id="item-google-books">
      <div class="item-google-prev">
        <script type="text/javascript" src="http://books.google.com/books/previewlib.js"></script>
          <script type="text/javascript">
            var w=document.getElementById("item-google-books").offsetWidth;
            var h=(w*1.3);
            GBS_insertEmbeddedViewer('ISBN:<?php print $item['stdnum']; ?>',w,h);
          </script>
      </div>
    </div>
    <?php 
      $test=1;
    ?>

  <!-- end right-hand column -->
  </div>

<!-- end item record -->
</div>