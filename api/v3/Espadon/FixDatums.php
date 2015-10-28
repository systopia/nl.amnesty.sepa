<?php
/**
 * Espadon.FixDatums API
 *
 * @param array $params
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws API_Exception
 */
function civicrm_api3_espadon_fixdatums($params) {
  $dao = CRM_Core_DAO::executeQuery("SELECT * FROM espadon_data");
  while ($dao->fetch()) {
    $query = "UPDATE espadon_data SET AanmaakdatumSchenker = %1, DatumeersteBetaling = %2, DatumLaatsteBetaling = %3,
      Einddatum = %4, Geboortedatum = %5, Inputdatum = %6, Startdatum = %7, Vervaldatum = %8
      WHERE espadon_id = %9";
    $queryParams = array(
      1 => array(fixMonth($dao->AanmaakdatumSchenker), "String"),
      2 => array(fixMonth($dao->DatumeersteBetaling), "String"),
      3 => array(fixMonth($dao->DatumLaatsteBetaling), "String"),
      4 => array(fixMonth($dao->Einddatum), "String"),
      5 => array(fixMonth($dao->Geboortedatum), "String"),
      6 => array(fixMonth($dao->Inputdatum), "String"),
      7 => array(fixMonth($dao->Startdatum), "String"),
      8 => array(fixMonth($dao->Vervaldatum), "String"),
      9 => array($dao->espadon_id, "Integer"),
    );
    CRM_Core_DAO::executeQuery($query, $queryParams);
  }
  return civicrm_api3_create_success(array(), $params, 'Espadon', 'FixDatums');
}
function fixMonth($sourceDate) {
  $parts = explode("-", $sourceDate);
  if (isset($parts[1])) {
    if (strlen($parts[1]) == 1) {
      $parts[1] = "0".$parts[1];
      return implode("-", $parts);
    }
  }
  return $sourceDate;
}

