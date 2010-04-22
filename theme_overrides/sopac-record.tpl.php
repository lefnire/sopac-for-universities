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
} else if ($item_status['avail'] == 0) {
  $class = "first";
  $reqtext = "There are no copies available.";
} else if($item_status['holds'] > 0) {
  $class = "holds";
  $reqtext = "There " . ($item_status['avail'] == 1 ? 'is' : 'are') . " currently $item_status[avail] available and " . $item_status['holds'] . " request" . ($item_status['holds'] == 1 ? '' : 's') . " on " . $item_status['total'] . ' ' . ($item_status['total'] == 1 ? 'copy' : 'copies');
} else {
  $class = "avail";
  $reqtext = "There " . ($item_status['avail'] == 1 ? 'is' : 'are') . " currently $item_status[avail] available.";
}

// Build the item availability array
if (count($item_status['items'])) {
  foreach ($item_status['items'] as $copy_status) {
    if ($copy_status['avail'] > 0) {
      $copy_tag = ($copy_status['avail'] == 1) ? t('copy available') : t('copies available');
      $status_msg = $copy_status['avail'] . ' ' . $copy_tag;
    } else if ($copy_status['due']) {
      $status_msg = t('Next copy due') . ' ' . date('n-j-Y', $copy_status['due']);
    } else {
      $status_msg = $copy_status['statusmsg'];
    }
    if (variable_get('sopac_multi_branch_enable', 0)) {
      $copy_status_array[] = array($copy_status['location'], $copy_status['callnum'], $locum_config['branches'][$copy_status['branch']], $status_msg);
    } else {
      $copy_status_array[] = array($copy_status['location'], $copy_status['callnum'], $status_msg);
    }
  }
}


//material image
if(file_exists($ucsf_img = drupal_get_path('theme', 'ucsf_theme') . '/sopac/images/' . $item['mat_code'] . '.gif')){
  $mat_image = theme('image', $ucsf_img); 
}else{
  $mat_image = theme('image', drupal_get_path('module', 'sopac') . '/images/' . $item['mat_code'] . '.png');
} 

if (sopac_prev_search_url(TRUE)) {
  print '<p><a href="' . sopac_prev_search_url() . '">&#171; Return to your search</a></p>';
}

?>

<!-- begin item record -->
<div class="itemrecord">

  <!-- begin left-hand column -->
  <div class="item-left">

    <!-- Cover Image -->
    <?php
    if (module_exists('covercache')) {
        print $cover_img;
    } else {
        $cover_img_url = ($item['cover_img'] && $locum_result['cover_img'] != 'CACHE') ? $item['cover_img'] : '/' . drupal_get_path('module', 'sopac') . '/images/nocover.png';
        print '<img class="item-cover" width="200" src="' . $cover_img_url . '" />';
    }
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
      if ($item['pub_info']) { print '<li><b>Published:</b> ' . $item['pub_info'] . '</li>';  }
      if ($item['pub_year']) { print '<li><b>Year Published:</b> ' . $item['pub_year'] . '</li>';  }
      if ($item['series']) { print '<li><b>Series:</b> <a href="/' . 
                           $url_prefix . '/search/series/' . urlencode($series) . '">' . $item['series'] . '</a></li>';  }
      if ($item['edition']) { print '<li><b>Edition:</b> ' . $item['edition'] . '</li>';  }
      if ($item['descr']) { print '<li><b>Description:</b> ' . nl2br($item['descr']) . '</li>';  }
      if ($item['stdnum']) { print '<li><b>ISBN/Standard #:</b>' . $item['stdnum'] . '</li>';  }
      if ($item['lang']) { print '<li><b>Language:</b> ' . $locum_config['languages'][$item['lang']] . '</li>';  }
      if ($item['mat_code']) { print '<li><b>Format:</b> ' . $locum_config['formats'][$item['mat_code']] . '</li>';  }
      ?>
    </ul>

    <!-- Additional Credits -->
    <?php
    if ($item['addl_author']) {
      print '<h3>Additional Credits</h3><ul>';
      $addl_author_arr = unserialize($item['addl_author']);
      if(!empty($addl_author_arr)){
        foreach ($addl_author_arr as $addl_author) {
          $addl_author_link = '/' . $url_prefix . '/search/author/%22' . urlencode($addl_author) .'%22';
          print '<li><a href="' . $addl_author_link . '">' . $addl_author . '</a></li>';
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
          $subjurl = '/' . $url_prefix . '/search/subject/%22' . urlencode($subj) . '%22';
          print '<li><a href="' . $subjurl . '">' . $subj . '</a></li>';
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
      <li><?php print $mat_image; ?></li>
      <li style="margin-top: -2px;"><?php print wordwrap($locum_config['formats'][$item['mat_code']], 8, '<br />'); ?></li>
    </ul>

    <!-- Item Author -->
    <?php 
    if ($item['author']) { 
      $authorurl = '/' . $url_prefix . '/search/author/' . $new_author_str;
      print '<h3>by <a href="' . $authorurl . '">' . $new_author_str . '</a></h3>';
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
      if ($item_status['callnums']) { 
        print '<p>Call number: <strong>' . $item['callnum'] . '</strong></p>';
//        print '<p>Call number: <strong>' . implode(", ", $item_status['callnums']) . '</strong></p>';
      }

      if (count($item_status['items']) && !$no_avail_mat_codes) {
//        print '<fieldset class="collapsible"><legend>Show All Copies (' . count($item_status['items']) . ')</legend>';
//        drupal_add_js('misc/collapse.js');
        drupal_add_js(drupal_get_path('theme', 'ucsf_theme').'/sopac/js/ucsf_sopac.js');
        $attributes = array('id' => 'sopac-status-location');
        if (variable_get('sopac_multi_branch_enable', 0)) {
          print theme('table', array("Location", "Call Number", "Branch", "Item Status"), $copy_status_array, $attributes);
        } else {
          print theme('table', array("Location", "Call Number", "Item Status"), $copy_status_array, $attributes);
        }
//        print '</fieldset>';
      } else if ($item['download_link']) {
        print '<div class="item-request">';
        print '<p><a href="' . $item['download_link'] . '" target="_new">Download this Title</a></p>';
        print '</div>';
      } else {
        if (!$no_avail_mat_codes) { print '<p>No copies found.</p>'; }
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
      print '</div>';
    }
    ?>
    
    <!-- UCSF/University Extras -->
    <div id="ucsf-extras">
    <h2>UCSF extra unformatted stuff</h2>
    <ul>
    <?php
      function print_serial($title, $item){
        $str = "<li><strong>{$title}</strong>: ";
        $unserialized = @unserialize($item);
        if($unserialized!==false) $item=$unserialized;
        if(is_array($item)){
          $str.='<ul>';
          foreach($item as $val){
            $str.="<li>{$val}</li>";
          }
          $str.='</ul>';
        }else{
          $str = $item;
        }        
        return $str.'</li>';
      }
    
      if($item['continues'])    { print print_serial('Continues', $item['continues']);}
      if($item['link'])         { print print_serial('Link', $item['link']); }
      if($item['alt_title'])    { print print_serial('Alt Title', $item['alt_title']); }
      if($item['related_work']) { print print_serial('Related Work', $item['related_work']); }
      if($item['local_note'])   { print print_serial('Local Notes', $item['local_note']); }
      if($item['oclc'])         { print print_serial('OCLC', $item['oclc']); }
      if($item['doc_number'])   { print print_serial('Doc Number', $item['doc_number']); }
      if($item['holdings'])     { print print_serial('Holdings', $item['holdings']); }
      if($item['cont_d_by'])    { print print_serial('Continued By', $item['cont_d_by']); }
      if($item['__note__'])     { print print_serial('* NOTE *', $item['__note__']); }
      if($item['hldgs_stat'])   { print print_serial('Holdings Status', $item['hldgs_stat']); }
    ?>
    </ul>
    </div>

    <!-- Syndetics / Review Links -->
    <?php
    if ($item['review_links']) {
      print '<div id="item-syndetics">';
      print '<h2>Reviews &amp; Summaries</h2>';
      print '<ul>';
      foreach ($item['review_links'] as $rev_title => $rev_link) {
        print '<li><a href="' . $rev_link . '" target="_new">' . $rev_title . '</a>';
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
            print '<h3 class="summary"><a href="/review/view/' . $rev_item['rev_id'] . '" class="fn url">' . $rev_item['rev_title'] . '</a></h3>';
            if ($rev_item['uid']) {
              $rev_user = user_load(array('uid' => $rev_item['uid']));
              print '<p class="review-byline">submitted by <span class="review-author"><a href="/review/user/' . $rev_item['uid'] . '">' . $rev_user->name . '</a> on <abbr class="dtreviewed" title="' . date("c", $rev_item['timestamp']) . '">' . date("F j, Y, g:i a", $rev_item['timestamp']) . '</abbr></span>';
              if ($user->uid == $rev_item['uid']) {
                print ' &nbsp; [ <a title="Delete this review" href="/review/delete/' . $rev_item['rev_id'] . '?ref=' . urlencode($_SERVER['REQUEST_URI']) . '">delete</a> ] [ <a title="Edit this review" href="/review/edit/' . $rev_item['rev_id'] . '?ref=' . urlencode($_SERVER['REQUEST_URI']) . '">edit</a> ]';
              }
              print '</p>';
            }
            print '<div class="review-body description">' . nl2br($rev_item['rev_body']) . '</div></div>';
          }
        } else {
          print '<p>No reviews have been written yet.  You could be the first!</p>';
        }
        print $rev_form ? $rev_form : '<p><a href="/user/login?destination=' . $_SERVER['REQUEST_URI'] . '">Login</a> to write a review of your own.</p>';
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

  <!-- end right-hand column -->
  </div>

<!-- end item record -->
</div>