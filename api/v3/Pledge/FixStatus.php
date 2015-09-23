<?php

/**
 * Pledge.FixStatus API
 * Migration Amnesty International Vlaanderen
 * Set all pledges of correctly converted mandates to completed
 *
 * @author Erik Hommel (CiviCooP) <erik.hommel@civicoop.org>
 * @param array $params
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws API_Exception
 */
function civicrm_api3_pledge_fixstatus($params) {
  $querySDD = "SELECT contact_id FROM civicrm_sdd_mandate WHERE entity_table = %1 AND source = %2";
  $paramsSDD = array(
    1 => array("civicrm_contribution_recur", "String"),
    2 => array("Migratie 2015", "String")
  );
  $daoSDD = CRM_Core_DAO::executeQuery($querySDD, $paramsSDD);
  while ($daoSDD->fetch()) {
    $queryPledge = "UPDATE civicrm_pledge SET status_id = %1 WHERE contact_id = %2";
    $paramsPledge = array(
      1 => array(1, "Integer"),
      2 => array($daoSDD->contact_id, "Integer"));
    CRM_Core_DAO::executeQuery($queryPledge, $paramsPledge);
  }
  $returnValues = array();
  return civicrm_api3_create_success($returnValues, $params, 'Pledge', 'FixStatus');
}