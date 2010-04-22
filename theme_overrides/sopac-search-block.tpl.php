<?php
/*
 * Search tracker Block template
 *
 */
$uri_arr = explode('?', $_SERVER['REQUEST_URI']);
$uri = $uri_arr[0];
$getvars = sopac_parse_get_vars();
$sortopts = sopac_search_form_sortopts();
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
    $pvars_tmp = trim(sopac_make_pagevars(sopac_parse_get_vars($getvars_tmp)));
    $gvar_indicator = $pvars_tmp ? '?' : '';
    $rem_link = $uri . $gvar_indicator . $pvars_tmp;
    if ($getvars['limit_avail'] == 'any') {
      $limit_info = t('Any Location') . ' [<a href="' . $rem_link . '">x</a>]';
    } else {
      $limit_info = $locum_config['branches'][$getvars['limit_avail']] . ' [<a href="' . $rem_link . '">x</a>]';
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
      $pvars_tmp = trim(sopac_make_pagevars(sopac_parse_get_vars($getvars_tmp)));
      $gvar_indicator = $pvars_tmp ? '?' : '';
      $rem_link = $uri . $gvar_indicator . $pvars_tmp;
      $search_format_arr[trim($search_format)] = $locum_config['formats'][trim($search_format)] . ' [<a href="' . $rem_link . '">x</a>]';
    }
    print implode('<br />', $search_format_arr);
  } else {
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
    $pvars_tmp = trim(sopac_make_pagevars(sopac_parse_get_vars($getvars_tmp)));
    $gvar_indicator = $pvars_tmp ? '?' : '';
    $rem_link = $uri . $gvar_indicator . $pvars_tmp;
    $age_arr[$age] = $locum_config['ages'][$age] . ' [<a href="' . $rem_link . '">x</a>]';
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
    $pvars_tmp = trim(sopac_make_pagevars(sopac_parse_get_vars($getvars_tmp)));
    $gvar_indicator = $pvars_tmp ? '?' : '';
    $rem_link = $uri . $gvar_indicator . $pvars_tmp;
    $coll_arr[$collection] = $collection . ' [<a href="' . $rem_link . '">x</a>]';
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
    $pvars_tmp = trim(sopac_make_pagevars(sopac_parse_get_vars($getvars_tmp)));
    $gvar_indicator = $pvars_tmp ? '?' : '';
    $rem_link = $uri . $gvar_indicator . $pvars_tmp;
    $location_arr[trim($location)] = $locum_config['locations'][$location] . ' [<a href="' . $rem_link . '">x</a>]';
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
    $pvars_tmp = trim(sopac_make_pagevars(sopac_parse_get_vars($getvars_tmp)));
    $gvar_indicator = $pvars_tmp ? '?' : '';
    $rem_link = $uri . $gvar_indicator . $pvars_tmp;
    $series_arr[trim($series)] = $series . ' [<a href="' . $rem_link . '">x</a>]';
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
    $pvars_tmp = trim(sopac_make_pagevars(sopac_parse_get_vars($getvars_tmp)));
    $gvar_indicator = $pvars_tmp ? '?' : '';
    $rem_link = $uri . $gvar_indicator . $pvars_tmp;
    $lang_arr[trim($lang)] = ucfirst($locum_config['languages'][$lang]) . ' [<a href="' . $rem_link . '">x</a>]';
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
    $pvars_tmp = trim(sopac_make_pagevars(sopac_parse_get_vars($getvars_tmp)));
    $gvar_indicator = $pvars_tmp ? '?' : '';
    $rem_link = $uri . $gvar_indicator . $pvars_tmp;
    $year_arr[trim($year)] = $year . ' [<a href="' . $rem_link . '">x</a>]';
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
    $pvars_tmp = trim(sopac_make_pagevars(sopac_parse_get_vars($getvars_tmp)));
    $gvar_indicator = $pvars_tmp ? '?' : '';
    $rem_link = $uri . $gvar_indicator . $pvars_tmp;
    $decade_arr[trim($decade)] = $decade . '-' . ($decade + 9) . ' [<a href="' . $rem_link . '">x</a>]';
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
    $pvars_tmp = trim(sopac_make_pagevars(sopac_parse_get_vars($getvars_tmp)));
    $gvar_indicator = $pvars_tmp ? '?' : '';
    $rem_link = $uri . $gvar_indicator . $pvars_tmp;
    $subject_arr[trim($subject)] = $subject . ' [<a href="' . $rem_link . '">x</a>]';
  }
  print implode('<br />', $subject_arr);
  print '</div>';
}
*/
?>

<br />
<div style="float: right;">» <a href="/research_help">Need help?</a></div>
<?php if ($user->uid) {
  print '<div style="float: right;">» <a href="' . sopac_savesearch_url() . '">Save this search</a></div>';
}
?>
<br />
