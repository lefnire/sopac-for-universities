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

<table width="100%">
  <tr>
  <td width="50%">
    <div class="overview-title"><?php print t("Top %n Ratings", array('%n' => count($ratings_chunk['top']['ratings']) ? count($ratings_chunk['top']['ratings']) : NULL)); ?></div>
    <table class="overview-ratings">
    <?php
    if (count($ratings_chunk['top']['ratings'])) {
      foreach ($ratings_chunk['top']['ratings'] as $rating) {
        print '<tr><td style="width: 100px;">';
        print theme_sopac_get_rating_stars($rating['bnum'], $rating['rating'], FALSE, TRUE, 'top') . ' ';
        print '</td><td>';
        print '<a href="/' . variable_get('sopac_url_prefix', 'cat/seek') . '/record/' . $rating['bnum'] . '">' .
          $ratings_chunk['bibs'][$rating['bnum']]['title'] . '</a>';
        print '</td></tr>';
      }
    } else {
        print $ratings_chunk['nodata'];
    }
    ?>
    </table>
  </td>
  <td>
    <div class="overview-title"><?php print t("Latest %n Ratings", array('%n' => count($ratings_chunk['latest']['ratings']) ? count($ratings_chunk['latest']['ratings']) : NULL)); ?></div>
    <table class="overview-ratings">
    <?php
    if (count($ratings_chunk['latest']['ratings'])) {
      foreach ($ratings_chunk['latest']['ratings'] as $rating) {
        print '<tr><td style="width: 100px;">';
        print theme_sopac_get_rating_stars($rating['bnum'], $rating['rating'], FALSE, TRUE, 'latest') . ' ';
        print '</td><td>';
        print '<a href="/' . variable_get('sopac_url_prefix', 'cat/seek') . '/record/' . $rating['bnum'] . '">' .
          $ratings_chunk['bibs'][$rating['bnum']]['title'] . '</a>';
        print '</td></tr>';
      }
    } else {
      print $ratings_chunk['nodata'];
    }
    ?>
    </table>
    <div class="overview-more-info">[ <a href="/user/library/ratings"><?php print t('See All Your Ratings'); ?></a> ]</div>
  </td>
  </tr>
  <tr>
  <td colspan="2">
    <div class="overview-title"><?php print t('Top Tags'); ?></div>
    <?php print '<div class="overview-tag-cloud">' . $tag_cloud . '</div>'; ?>
    <br />
    <div class="overview-more-info">[ <a href="/user/library/tags"><?php print t('See All Your Tags'); ?></a> ]</div>
  </td>
  </tr>
  <tr>
  <td colspan="2">
    <div class="overview-title"><?php print t('Recent Reviews'); ?></div>
    <?php print $review_display; ?>
    <div class="overview-more-info">[ <a href="/user/library/reviews"><?php print t('See All Your Reviews'); ?></a> ]</div>
  </td>
  </tr>
</table>
