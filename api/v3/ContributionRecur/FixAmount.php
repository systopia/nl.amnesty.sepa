<?php

/**
 * ContributionRecur.FixAmount API
 *
 * @param array $params
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws API_Exception
 */
function civicrm_api3_contribution_recur_fixamount($params) {
  $logger =   $logger = new CRM_MigrateLogger("fix_amount_log");
  $returnValues = array();

  $querySDD = "SELECT r.contact_id, r.id AS recur_id, s.reference, c.external_identifier
    FROM civicrm_sdd_mandate s LEFT JOIN civicrm_contribution_recur r ON s.entity_id = r.id
    JOIN civicrm_contact c ON r.contact_id = c.id WHERE s.source = %1 AND s.entity_table = %2";
  $paramsSDD = array(
    1 => array("Migratie 2015", "String"),
    2 => array("civicrm_contribution_recur", "String"));
  $daoSDD = CRM_Core_DAO::executeQuery($querySDD, $paramsSDD);
  while ($daoSDD->fetch()) {
    $newAmount = _shouldAmountBeChanged($daoSDD);
    if ($newAmount) {
      $updateRecur = "UPDATE civicrm_contribution_recur SET amount = %1 WHERE id = %2";
      $paramsRecur = array(
        1 => array($newAmount, "Money"),
        2 => array($daoSDD->recur_id, "Integer")
      );
      CRM_Core_DAO::executeQuery($updateRecur, $paramsRecur);
      $logger->logMessage("INFO", "Bedrag in recurring ".$daoSDD->recur_id." op ".$newAmount." gezet (mandaat "
        .$daoSDD->reference." en externe Id ".$daoSDD->external_identifier.")");
    }
  }
  return civicrm_api3_create_success($returnValues, $params, 'ContributionRecur', 'FixAmount');
}
/**
 * Function to check if amount needs to be changed
 *
 * @param object $daoSDD
 * @return string|bool
 */
function _shouldAmountBeChanged($daoSDD) {
  $newAmount = FALSE;
  if (!empty($daoSDD->external_identifier)) {
    $query = "SELECT Bedrag FROM espadon_data WHERE ExterneId = %1";
    $params = array(1 => array($daoSDD->external_identifier, "String"));
    $espadonAmount = CRM_Core_DAO::singleValueQuery($query, $params);
    if ($espadonAmount) {
      $amountParts = explode(",", $espadonAmount);
      if (isset($amountParts[1])) {
        $newAmount = implode(".", $amountParts);
      }
    }
  }
  return $newAmount;
}
