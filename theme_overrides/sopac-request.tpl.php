<?php

if ($request_result_msg) {
  print '<div class="req_result_msg">' . $request_result_msg . '</div>';
}

if ($request_error_msg) {
  print '<div class="req_error_msg">' . $request_error_msg . '</div>';
}

if ($item_form) { print $item_form; }

print '<div class="req_return_link>"<strong class="item-request">»</strong> <a href="/'. variable_get('sopac_url_prefix', 'cat/seek') . '/record/' . $bnum . '">' . t('Return to the record display') . '</a></div>';
if (sopac_prev_search_url(TRUE)){
  print '<div class="req_return_link>"<strong class="item-request">»</strong> <a href="' . sopac_prev_search_url(TRUE) . '">' . t('Return to your search') . '</a></div>';
}
print '<br />';
