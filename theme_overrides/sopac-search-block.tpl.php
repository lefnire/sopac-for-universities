<?php
/*
 * Search tracker Block template
 *
 */
$uri = $_GET['q'];
$getvars = sopac_parse_get_vars();
$sortopts = array(
  '' => t('Relevance'),
  'atoz' => t('Alphabetical A to Z'),
  'ztoa' => t('Alphabetical Z to A'),
  'catalog_newest' => t('Just Added'),
  'newest' => t('Pub date: Newest'),
  'oldest' => t('Pub date: Oldest'),
  'author' => t('Alphabetically by Author'),
//  'top_rated' => t('Top Rated Items'),
//  'popular_week' => t('Most Popular this Week'),
//  'popular_month' => t('Most Popular this Month'),
//  'popular_year' => t('Most Popular this Year'),
//  'popular_total' => t('All Time Most Popular'),
  'format' => t('Format'),
  'loc_code' => t('Location'),
//  'collections' => t('Collections'),
);
  
$sorted_by = $sortopts[$search['sortby']] ? $sortopts[$search['sortby']] : 'Relevance';
?>

You Searched For:
<div class="search-block-attr"><?php print $search['term']; ?></div>
<br />
By Search Type:
<div class="search-block-attr"><?php print ucfirst($search['type']); ?></div>

<?php
if ($getvars['limit_avail'] && ($locum_config['branches'][$getvars['limit_avail']] || $getvars['limit_avail'] == 'any')) {
  print '<br />Available at:';
  print '<div class="search-block-attr">';

  $getvars_tmp = $getvars;
  $getvars_tmp['limit_avail'] = '';
  $getvars_tmp['page'] = '';
  if ($getvars['limit_avail'] == 'any') {
    $limit_info = t('Any Location') . ' [' . l('x', $uri, array('query' => sopac_make_pagevars(sopac_parse_get_vars($getvars_tmp)))) . ']';
  }
  else {
    $limit_info = $locum_config['branches'][$getvars['limit_avail']] . ' [' . l('x', $uri, array('query' => sopac_make_pagevars(sopac_parse_get_vars($getvars_tmp)))) . ']';
  }
  print $limit_info . '</div>';
}
?>

<br />
By Format:
<div class="search-block-attr"><?php
  $search_format_flipped = is_array($getvars['search_format']) ? array_flip($getvars['search_format']) : array();
  if (count($search['format'])) {
    foreach ($search['format'] as $search_format) {
      $getvars_tmp = $getvars;
      unset($getvars_tmp['search_format'][$search_format_flipped[$search_format]]);
      $getvars_tmp['page'] = '';
      $search_format_arr[trim($search_format)] = $locum_config['formats'][trim($search_format)] . ' [' .
                                                 l('x', $uri, array('query' => sopac_make_pagevars(sopac_parse_get_vars($getvars_tmp)))) .
                                                 ']';
    }
    print implode('<br />', $search_format_arr);
  }
  else {
    print 'Everything';
  }
?></div>

<?php
if (is_array($getvars['age']) && count($getvars['age'])) {
  print '<br />In Age Group:';
  print '<div class="search-block-attr">';
  $age_flipped = array_flip($getvars['age']);
  foreach ($getvars['age'] as $age) {
    $getvars_tmp = $getvars;
    unset($getvars_tmp['age'][$age_flipped[$age]]);
    $getvars_tmp['page'] = '';
    $age_arr[$age] = $locum_config['ages'][$age] . ' [' . l('x', $uri, array('query' => sopac_make_pagevars(sopac_parse_get_vars($getvars_tmp)))) . ']';
  }
  print implode('<br />', $age_arr);
  print '</div>';
}
?>

<?php
if (is_array($getvars['collection']) && count($getvars['collection'])) {
  print '<br />In Collections:';
  print '<div class="search-block-attr">';
  foreach ($getvars['collection'] as $collection) {
    $getvars_tmp = $getvars;
    unset($getvars_tmp['collection'][$colection]);
    $getvars_tmp['page'] = '';
    $coll_arr[$collection] = $collection . ' [' . l('x', $uri, array('query' => sopac_make_pagevars(sopac_parse_get_vars($getvars_tmp)))) . ']';
  }
  print implode('<br />', $coll_arr);
  print '</div>';
}
?>

<?php
if (is_array($getvars['location']) && count($getvars['location'])) {
  print '<br />In Locations:';
  print '<div class="search-block-attr">';
  $location_flipped = array_flip($getvars['location']);
  foreach ($getvars['location'] as $location) {
    $getvars_tmp = $getvars;
    unset($getvars_tmp['location'][$location_flipped[$location]]);
    $getvars_tmp['page'] = '';
    $location_arr[trim($location)] = $locum_config['locations'][$location] . ' [' . l('x', $uri, array('query' => sopac_make_pagevars(sopac_parse_get_vars($getvars_tmp)))) . ']';
  }
  print implode('<br />', $location_arr);
  print '</div>';
}
?>

<br />
Sorted by:
<div class="search-block-attr"><?php print $sorted_by; ?></div>

<?php
if (is_array($getvars['facet_series']) && count($getvars['facet_series'])) {
  print '<br />Refined by Series:';
  print '<div class="search-block-attr">';
  $series_flipped = array_flip($getvars['facet_series']);
  foreach ($search['series'] as $series) {
    $getvars_tmp = $getvars;
    unset($getvars_tmp['facet_series'][$series_flipped[$series]]);
    $getvars_tmp['page'] = '';
    $series_arr[trim($series)] = $series . ' [' . l('x', $uri, array('query' => sopac_make_pagevars(sopac_parse_get_vars($getvars_tmp)))) . ']';
  }
  print implode('<br />', $series_arr);
  print '</div>';
}
?>

<?php
if (is_array($getvars['facet_lang']) && count($getvars['facet_lang'])) {
  print '<br />Refined by Language:';
  print '<div class="search-block-attr">';
  $lang_flipped = array_flip($getvars['facet_lang']);
  foreach ($search['lang'] as $lang) {
    $getvars_tmp = $getvars;
    unset($getvars_tmp['facet_lang'][$lang_flipped[$lang]]);
    $getvars_tmp['page'] = '';
    $lang_arr[trim($lang)] = ucfirst($locum_config['languages'][$lang]) . ' [' . l('x', $uri, array('query' => sopac_make_pagevars(sopac_parse_get_vars($getvars_tmp)))) . ']';
  }
  print implode('<br />', $lang_arr);
  print '</div>';
}
?>

<?php
if (is_array($getvars['facet_year']) && count($getvars['facet_year'])) {
  print '<br />Refined by Pub. Year:';
  print '<div class="search-block-attr">';
  $year_flipped = array_flip($getvars['facet_year']);
  foreach ($search['year'] as $year) {
    $getvars_tmp = $getvars;
    unset($getvars_tmp['facet_year'][$year_flipped[$year]]);
    $getvars_tmp['page'] = '';
    $year_arr[trim($year)] = $year . ' [' . l('x', $uri, array('query' => sopac_make_pagevars(sopac_parse_get_vars($getvars_tmp)))) . ']';
  }
  print implode('<br />', $year_arr);
  print '</div>';
}
?>

<?php
if (is_array($getvars['facet_decade']) && count($getvars['facet_decade'])) {
  print '<br />Refined by Decade:';
  print '<div class="search-block-attr">';
  $decade_flipped = array_flip($getvars['facet_decade']);
  foreach ($search['decade'] as $decade) {
    $getvars_tmp = $getvars;
    unset($getvars_tmp['facet_decade'][$decade_flipped[$decade]]);
    $getvars_tmp['page'] = '';
    $decade_arr[trim($decade)] = $decade . '-' . ($decade + 9) . ' [' . l('x', $uri, array('query' => sopac_make_pagevars(sopac_parse_get_vars($getvars_tmp)))) . ']';
  }
  print implode('<br />', $decade_arr);
  print '</div>';
}
?>

<?php
/* Uncomment for subjects facet
if (is_array($getvars['facet_subject']) && count($getvars['facet_subject'])) {
  print '<br />Refined by Subject:';
  print '<div class="search-block-attr">';
  $subject_flipped = array_flip($getvars['facet_subject']);
  foreach ($search['subject'] as $subject) {
    $getvars_tmp = $getvars;
    unset($getvars_tmp['facet_subject'][$subject_flipped[$subject]]);
    $getvars_tmp['page'] = '';
    $subject_arr[trim($subject)] = $subject . ' [' . l('x', $uri, array('query' => sopac_make_pagevars(sopac_parse_get_vars($getvars_tmp)))) . ']';
  }
  print implode('<br />', $subject_arr);
  print '</div>';
}
*/
?>
<br />
<?php
print '<div style="float: right;">» ' . l(t('Need help?'), 'http://www.library.ucsf.edu/askus') . '</div>';
if ($user->uid) {
  print '<div style="float: right;">» ' . sopac_savesearch_link() . '&nbsp;</div>';
}
?>
<br />
