<?php 
function civicrm_api3_sepa_mandate_migrate($params) {
$params = array(
  'sequential' => 1,
  'pledge_status' => 'In Progress',
  'option.limit' => 10,
);
$result = civicrm_api3('Pledge', 'get', $params);

print_r($result);
}



