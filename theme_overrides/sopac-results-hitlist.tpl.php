<?php
/*
 * Theme template for SOPAC hitlist
 *
 */

// Prep some stuff here

$new_author_str = sopac_author_format($locum_result['author'], $locum_result['addl_author']);
$url_prefix = variable_get('sopac_url_prefix', 'cat/seek');

if ($locum_result['cover_img'] && $locum_result['cover_img'] != 'CACHE') {
  $cover_img_url = $locum_result['cover_img'];
} else {
  $cover_img_url = '/' . drupal_get_path('module', 'sopac') . '/images/nocover.png';
}
?>
<div class="hitlist-item">



<table>
  <tr>
  <td class="hitlist-number" width="7%"><?php print $result_num; ?></td>
  <td width="13%">
    <a href="/<?php print $url_prefix . '/record/' . $locum_result['bnum'] ?>">
    <?php
    if (module_exists('covercache')) {
      print $cover_img;
    } else { ?>
      <img class="hitlist-cover" width="100" src="<?php print $cover_img_url; ?>">
    <?php } ?>
    </a>
    </td>
  <td width="<?php print $locum_result['review_links'] ? '50' : '100'; ?>%" valign="top">
    <ul class="hitlist-info">
      <li class="hitlist-title">
        <strong><a href="/<?php print $url_prefix . '/record/' . $locum_result['bnum'] ?>"><?php print ucwords($locum_result['title']);?></a></strong>
        <?php if ($locum_result['title_medium']) { print "[$locum_result[title_medium]]"; } ?>
      </li>
      <li>
        <?php print l($new_author_str, $url_prefix . '/search/author/' . urlencode($new_author_str) );?>
      </li>
      <li><?php print $locum_result['pub_info']; ?></li>
      <br />
      
      <li>
        <?php 
          echo '<div id="'.$locum_result['loc_code'].'">Location:  '
               .( ($locum_result['loc_code']=='multi')? 'Multiple Locations' :$locum_result['availability']['items'][0]['location'] )
           	   .'</div>';
        ?>
      </li>
      <?php if ($locum_result['callnum']) { 
        ?><li><?php print t('Call number: '); ?><strong><?php print $locum_result['callnum']; ?></strong></li><?php
      } else if (count($locum_result['avail_details'])) {
        ?><li><?php print t('Call number: '); ?><strong><?php print key($locum_result['avail_details']); ?></strong></li><?php
      } ?>
      <br />
      <li>
      <?php 
      print $locum_result['status']['avail'] . t(' of ') . $locum_result['status']['total'] . ' ';
      print ($locum_result['status']['total'] == 1) ? t('copy available') : t('copies available');
      ?>
      </li>
	  <li>
      	<?php print 'Status: ' .$locum_result['availability']['items'][0]['statusmsg'];?>
      </li>

      <?php 
      if (!in_array($locum_result['loc_code'], $no_circ)) {
        print '<li class="item-request"><strong>» ' . sopac_put_request_link($locum_result['bnum']) . '</strong></li>';
      }
      ?>
    </ul>
  </td>
  <?php
  if ($locum_result['review_links']) {
    print '<td width="50%" valign="top">';
    print '<ul class="hitlist-info">';
    print '<li class="hitlist-subtitle">Reviews &amp; Summaries</li>';
    foreach ($locum_result['review_links'] as $rev_title => $rev_link) {
      print '<li><a href="' . $rev_link . '" target="_new">' . $rev_title . '</a>';
    }
    print '</ul></td>';
  }
  ?>
  <td width="15%">
   <ul class="hitlist-format-icon">
    <?php if(file_exists($ucsf_img = drupal_get_path('theme', 'acquia_prosper') . '/theme_overrides/ucsf/' . $locum_result['mat_code'] . '.gif')){
      print "<li>". theme('image', $ucsf_img) ."</li>"; 
    }else{ ?>
      <li><img src="<?php print '/' . drupal_get_path('module', 'sopac') . '/images/' . $locum_result['mat_code'] . '.png' ?>"></li>
    <?php } ?>
    <li style="margin-top: -2px;"><?php print wordwrap($locum_config['formats'][$locum_result['mat_code']], 8, '<br />'); ?></li>
  </ul>

  </td>

  </tr>


</table>
</div>
