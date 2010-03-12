<?php
/*
 * Hitlist Page RSS template
 */

print '<?xml version="1.0" encoding="utf-8"?>' . "\n";
?>
  <feed xmlns="http://www.w3.org/2005/Atom">
    <title type="text">Search for <?php print $search_term; ?></title>
    <updated><?php print date('Y-m-d') . 'T00:00:00-05:00'?></updated>
    <id>http://<?php print $_SERVER['SERVER_NAME']; ?>/</id>
    <link rel="alternate" type="text/html" hreflang="en" href="http://<?php print $_SERVER['SERVER_NAME']; ?>/"/>
    <link rel="self" type="application/atom+xml" href="http://<?php print urlencode($_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI']); ?>"/>
<?php print $hitlist_content ?>
  </feed>
