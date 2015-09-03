<?php

/**
 * Pledge.FixStatus API
 * Migration Amnesty International Vlaanderen
 * - get mandate from tmp_espadon3 (latest espadon load) with Statuut_details (status) = 'Lopend' and then
 *   retrieve latest pledge for that mandate. If pledge status is not In Progess, set it to In Progress
 *
 * @author Erik Hommel (CiviCooP) <erik.hommel@civicoop.org>
 * @param array $params
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws API_Exception
 */
function civicrm_api3_pledge_fixstatus($params) {
  $countUpdated = 0;
  $logger = new CRM_MigrateLogger("pledge_status_update_log");

  $espadonQuery = "SELECT mandatestring FROM tmp_espadon3 WHERE Statuut_detail = %1";
  $espadonParams = array(1 => array("Lopend", "String"));
  $daoEspadon = CRM_Core_DAO::executeQuery($espadonQuery, $espadonParams);
  while ($daoEspadon->fetch()) {
    /*
     * now get latest pledge for mandate and update status if not In Progress
     */
    $pledgeQuery = "SELECT p.contact_id, p.id, p.status_id FROM civicrm_value_sepa_direct_debit_2 JOIN civicrm_pledge p on entity_id = p.id
      WHERE mandate_3 = %1 ORDER BY start_date DESC";
    $pledgeParams = array(1 => array($daoEspadon->mandatestring, "String"));
    $daoPledge = CRM_Core_DAO::executeQuery($pledgeQuery, $pledgeParams);
    if ($daoPledge->fetch()) {
      if ($daoPledge->status_id != 5) {
        $updateQuery = "UPDATE civicrm_pledge SET status_id = %1 WHERE id = %2";
        $updateParams = array(
          1 => array(5, "Integer"),
          2 => array($daoPledge->id, "Integer")
        );
        CRM_Core_DAO::executeQuery($updateQuery, $updateParams);
        $countUpdated++;
        $logger->logMessage("INFO", "Pledge ".$daoPledge->id." with mandate ".$daoEspadon->mandatestring." set to status In Progress (contact "
            .$daoPledge->contact_id.")");
      }
    }
  }
  $returnValues = "Pledges updated to In Progress: ".$countUpdated;
  return civicrm_api3_create_success($returnValues, $params, 'Pledge', 'FixStatus');
}