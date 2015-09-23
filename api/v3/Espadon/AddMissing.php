<?php
/**
 * Espadon.AddMissing API
 *
 * @param array $params
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws API_Exception
 */
function civicrm_api3_espadon_addmissing($params) {
  $returnValues = array();
  $columns = array();
  $daoColumns = CRM_Core_DAO::executeQuery("SHOW COLUMNS FROM espadon_extra_data");
  while ($daoColumns->fetch()) {
    $columns[] = $daoColumns->Field;
  }
  $query = "SELECT extra.* FROM espadon_extra_data extra WHERE extra.Mandate NOT IN(
    SELECT DISTINCT(Mandate) FROM espadon_data)";
  $dao = CRM_Core_DAO::executeQuery($query);
  while ($dao->fetch()) {
    $insertFields = array("processed = %1");
    $insertParams = array(1 => array(0, "Integer"));
    $count = 1;
    foreach ($columns as $fieldName) {
      $count++;
      $insertFields[] = $fieldName." = %".$count;
      $insertParams[$count] = array($dao->$fieldName, 'String');
    }
    $insertQuery = "INSERT INTO espadon_data SET ".implode(", ", $insertFields);
    CRM_Core_DAO::executeQuery($insertQuery, $insertParams);
  }
  return civicrm_api3_create_success($returnValues, $params, 'Espadon', 'AddMissing');
}

