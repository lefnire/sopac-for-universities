<?php
/*
 * Hitlist Page template
 */

// Set some stuff up here

$getvars = sopac_parse_get_vars();
$sorted_by = $getvars['sort'];
$uri_arr = explode('?', $_SERVER['REQUEST_URI']);
$uri = $uri_arr[0];
$uri = $_GET['q'];

$perpage = $result_info['limit'];
$sortopts = sopac_search_form_sortopts();
$default_perpage = variable_get('sopac_results_per_page', 10);

?>

<?php if ($result_info['num_results'] > 0) { ?>
<div class="hitlist-top-bar">

<?php if ($locum_result['suggestion']) { ?>
<div class="hitlist-suggestions">
  Did you mean <i><a href="<?php print suggestion_link($locum_result); ?>"><?php 
    print $locum_result['suggestion']; 
  ?></a></i> ?
</div>
<br />
<?php } ?>

  <div class="hitlist-range">
    <span class="range">Showing results <strong><?php print $result_info['hit_lowest'] . '</strong> to <strong>' . $result_info['hit_highest'] . '</strong> of <strong>' . $result_info['num_results'] .'</strong>'; ?></span>
    <span class="pagination">Show: 
      <?php
        if ($perpage == $default_perpage || !$perpage) {
          print "<strong>" . $default_perpage . "</strong>";
        } else {
          $getvars['perpage'] = $default_perpage;
          $getvars['page'] = '';
          print l($default_perpage, $uri, array('query' => sopac_make_pagevars(sopac_parse_get_vars($getvars))));
        }
        print ' | ';
        if ($perpage == ($default_perpage * 3)) {
          print "<strong>" . ($default_perpage * 3) . "</strong>";
        } else {
          $getvars['perpage'] = ($default_perpage * 3);
          $getvars['page'] = '';
          print l(($default_perpage * 3), $uri, array('query' => sopac_make_pagevars(sopac_parse_get_vars($getvars))));
        }
        print ' | ';
        if ($perpage == ($default_perpage * 6)) {
          print "<strong>" . ($default_perpage * 6) . "</strong>";
        } else {
          $getvars['perpage'] = ($default_perpage * 6);
          $getvars['page'] = '';
          print l(($default_perpage * 6), $uri, array('query' => sopac_make_pagevars(sopac_parse_get_vars($getvars))));
        } 
      ?>
    </span>
    <span class="hitlist-sorter">
      <script>
        jQuery(document).ready(function() {$('#sortlist').change(function(){ location.href = $(this).val();});});
      </script>
      Sort by: <select name="sort" id="sortlist">
      <?php
        foreach($sortopts as $key => $value) {
          print '<option value="' . url($_GET['q'], array('query' => sopac_make_pagevars(sopac_parse_get_vars(array('page' => '', 'limit' => '', 'sort' => $key))))) . '" ';
          if ($sorted_by == $key) {
            print 'selected';
          }
          print '>' . $value . '</option>';
        }
      ?>
      </select>
    </span>
  </div>
  
</div>
<br />
<div class="hitlist-pager">
<?php print $hitlist_pager; ?>
</div>
<?php } ?>

<div class="hitlist-content">
<?php print $hitlist_content; ?>
</div>
<div class="hitlist-pager">
<?php print $hitlist_pager; ?>
</div>
