<?php
/*
 * Search fines page template
 *
 */

if ($notice) {
  print '<span class="fine-notice">' . $notice . '</span>';
}

if ($fine_table) {
  print $fine_table;
}

if ($payment_form) {
  print '<br /><br />' . $payment_form;
}