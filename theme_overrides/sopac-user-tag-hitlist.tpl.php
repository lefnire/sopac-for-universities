<?php if (!isset($skip_header)) { ?>
	<div class="overview-title"> <?php print t('Items tagged with "') . $tag ?>"</div>
<?php } ?>
<br />
<?php print $pager_body ?>
<br />
<?php print $result_body ?>
<?php print $pager_body ?>