<?php
/*
 * Theme template for SOPAC RSS hitlist entry
 *
 */

// Prep some stuff here

$new_author_str = sopac_author_format($locum_result['author'], $locum_result['addl_author']);
$url_prefix = variable_get('sopac_url_prefix', 'cat/seek');
if (module_exists('covercache')) {
  $cover_img_url = covercache_image_url($locum_result['bnum']);
}
if (!$cover_img_url) {
  $cover_img_url = '/' . drupal_get_path('module', 'sopac') . '/images/nocover.png';
}
?>

    <entry>
      <title><?php print $locum_result['title'];?></title>
      <id>http://<?php print $_SERVER['SERVER_NAME'] . '/'. $url_prefix . '/record/' . $locum_result['bnum'] ?></id>
      <link rel="alternate" href="http://<?php print $_SERVER['SERVER_NAME'] . '/'. $url_prefix . '/record/' . $locum_result['bnum'] ?>"/>
      <updated><?php print date('Y-m-d'); ?>T00:00:00-05:00</updated>
      <published><?php print $locum_result['bib_created']; ?>T00:00:00-05:00</published>
      <author>
        <name><?php print $new_author_str; ?></name>
        <uri>http://<?php print $_SERVER['SERVER_NAME'] . $url_prefix . '/search/author/' . urlencode($new_author_str) ?></uri>
      </author>
      <content type="xhtml" xml:lang="en" xml:base="http://<?php print $_SERVER['SERVER_NAME']; ?>/">
        <div xmlns="http://www.w3.org/1999/xhtml">
          <p><img class="hitlist-cover" width="100" src="<?php print $cover_img_url ?>" /></p>
          <ul>
            <li id="publisher">Publisher: <?php print $locum_result['pub_info'] . ', ' . $locum_result['pub_year']; ?></li>
            <li id="added">Added on <?php print $locum_result['bib_created']; ?></li>
            <?php if ($locum_result['callnum']) { ?><li>Call number: <strong><?php print $locum_result['callnum']; ?></strong></li> <?php } ?>
            <li>
              <?php 
              print $locum_result['status']['avail'] . t(' of ') . $locum_result['status']['total'] . ' ';
              print ($locum_result['status']['total'] == 1) ? t('copy available') : t('copies available');
              ?>
            </li>
            <li id="item-request">» <a href="/<?php print $url_prefix . '/record/' . $locum_result['bnum'] ?>">Request this item</a></li>
          </ul>
        </div>
      </content>
    </entry>
