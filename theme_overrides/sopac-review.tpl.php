<?php
/**
 * SOPAC is The Social OPAC: a Drupal module that serves as a wholly integrated web OPAC for the Drupal CMS
 * This file contains the Drupal include functions for all the SOPAC admin pieces and configuration options
 * This file is called via hook_user
 *
 * @package SOPAC
 * @version 2.1
 * @author John Blyberg
 */
?>

<?php if ($title) { print '<div class="review-item-title">' . $title . '</div>'; } ?>
<?php if ($ratings) { print '<br />' . $ratings; } ?>
<?php if ($rev_form) { print '<br />' . $rev_form; } ?>

<?php
if (count($rev_arr)) {
  print '<div class="review-page">';
  foreach ($rev_arr as $rev_item) {
    print '<div class="review-block"><div class="review-header"><span class="review-title"><a href="/review/view/' . $rev_item['rev_id'] . '">' . $rev_item['rev_title'] . '</a></span><br />';
    if ($bib_info[$rev_item['bnum']]['title']) {
      print '<span class="item-request"><strong>»</strong></span> ';
      print '<span class="review-byline">' . t('Review for ') . '<a href="/' . variable_get('sopac_url_prefix', 'cat/seek') . '/record/' . $rev_item['bnum'] . '">' . $bib_info[$rev_item['bnum']]['title'] . '</a></span><br />';
    }
    if ($rev_item['uid']) {
      $rev_user = user_load(array('uid' => $rev_item['uid']));
      print '<span class="item-request"><strong>»</strong></span> ';
      print '<span class="review-byline">' . t('submitted by ') . '<span class="review-author"><a href="/review/user/' . $rev_item['uid'] . '">' . $rev_user->name . '</a></span> ';
      print ':: <span class="review-date">' . date("F j, Y, g:i a", $rev_item['timestamp']) . '</span></span>';
      if ($user->uid == $rev_item['uid']) {
        print ' [ <a title="' . t('Delete this review') . '" href="/review/delete/' . $rev_item['rev_id'] . '?ref=' . urlencode($_SERVER[REQUEST_URI]) . '">' . t('delete') . '</a> ] ';
        print ' [ <a title="' . t('Edit this review') . '" href="/review/edit/' . $rev_item['rev_id'] . '?ref=' . urlencode($_SERVER[REQUEST_URI]) . '">' . t('edit') . '</a> ] ';
      }
    }
    print '</div><div class="review-body">';
    print nl2br($rev_item['rev_body']);
    print '</div></div>';
  
  }
  print '</div>';
} else {
  print $no_rev_msg;
}
