<?php


/**
 * ContributionRecur.LinkEspadon API
 * Specific AIVL API for migration purposes
 *
 * Selects all contacts from mandates with Migratie 2015 and then:
 * - if contact has more than 1 recurring with same campaign, log and do nothing
 * - if contact has 1 recurring contribution with campaign, find all contributions where:
 *   - financial_type_id = 4
 *   - contact_id is correct
 *   - is_test = 0
 *   - campaign_id = campaign of recurring
 *   - receive_date >= start date recurring
 *   and update the recurring id in the contributions
 *
 * @author Erik Hommel (CiviCooP) <erik.hommel@civicoop.org>
 * @param array $params
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws API_Exception
 */
function civicrm_api3_contribution_recur_linkespadon($params) {
  $logger =   $logger = new CRM_MigrateLogger("link_contrib_log");
  $returnValues = array();

  $querySDD = "SELECT r.contact_id, r.id AS recur_id, r.start_date, r.campaign_id
    FROM civicrm_sdd_mandate s LEFT JOIN civicrm_contribution_recur r ON s.entity_id = r.id
    WHERE s.source = %1 AND entity_table = %2";
  $paramsSDD = array(
    1 => array("Migratie 2015", "String"),
    2 => array("civicrm_contribution_recur", "String"));
  $daoSDD = CRM_Core_DAO::executeQuery($querySDD, $paramsSDD);
  while ($daoSDD->fetch()) {
    if (_canRecurBeProcessed($daoSDD) == TRUE) {
      $contribQuery = "UPDATE civicrm_contribution SET contribution_recur_id = %1 WHERE contact_id = %2
        AND financial_type_id = %3 AND campaign_id = %4 AND is_test = %5 AND receive_date >= %6";
      $contribParams = array(
        1 => array($daoSDD->recur_id, "Integer"),
        2 => array($daoSDD->contact_id, "Integer"),
        3 => array(4, "Integer"),
        4 => array($daoSDD->campaign_id, "Integer"),
        5 => array(0, "Integer"),
        6 => array(date("Ymd", strtotime($daoSDD->start_date)), "Date")
      );
      CRM_Core_DAO::executeQuery($contribQuery, $contribParams);
    } else {
      $logger->logMessage("Waarschuwing", "Contact ".$daoSDD->contact_id
        ." heeft meer dan 1 recurring contribution met dezelfde campaign, niet verwerkt!");
    }
  }
  return civicrm_api3_create_success($returnValues, $params, 'ContributionRecur', 'LinkEspadon');
}

/**
 * Function to check if there is more than 1 recurring contribution for contact and campaign. If so, return FALSE else TRUE
 *
 * @param $daoSDD
 * @return bool
 */
function _canRecurBeProcessed($daoSDD) {
  $query = "SELECT COUNT(*) FROM civicrm_contribution_recur
    WHERE contact_id = %1 AND financial_type_id = %2 AND campaign_id = %3";
  $params = array(
    1 => array($daoSDD->contact_id, "Integer"),
    2 => array(4, "Integer"),
    3 => array($daoSDD->campaign_id, "Integer")
  );
  $countRecur = CRM_Core_DAO::singleValueQuery($query, $params);
  if ($countRecur > 1) {
    return FALSE;
  } else {
    return TRUE;
  }
}

