<?php

/**
 * Espadon.Migrate API
 * Amnesty International Flanders Migrate Recurring Contribution/SDD Mandate from Espadon
 *
 * @author Erik Hommel (CiviCooP) <erik.hommel@civicoop.org>
 * @param array $params
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws API_Exception
 */
function civicrm_api3_espadon_migrate($params)
{
  $logger = new CRM_MigrateLogger();
  $errors = 0;
  $warnings = 0;
  $processed = 0;
  $migrated = 0;
  if (isset($params['limit'])) {
    $limit = $params['limit'];
  } else {
    $limit = 2500;
  }
  $queryEspadon = "SELECT * FROM espadon_data WHERE Statuut_detail = %1 AND Frequentie != %2 AND processed = %3 LIMIT ".$limit;
  $paramsEspadon = array(
      1 => array("Lopend", "String"),
      2 => array("OOFF", "String"),
      3 => array(0, "Integer")
  );
  $daoEspadon = CRM_Core_DAO::executeQuery($queryEspadon, $paramsEspadon);
  while ($daoEspadon->fetch()) {
    _setEspadonProcessed($daoEspadon->espadon_id);
    $processed++;
    if (empty($daoEspadon->DatumeersteBetaling) && empty($datumLaatsteBetaling)) {
      $logger->logMessage("Info", "Espadon mandaat ".$daoEspadon->Mandate
        ." overgeslagen omdat datum eerste betaling EN datum laatste betaling leeg");
    } else {
      $daoEspadon->campaignId = _getCampaignIdWithBron($daoEspadon->Bron, $logger, $warnings);
      $daoEspadon->Mandate = _correctMandate($daoEspadon->Mandate);
      $daoEspadon->Iban = _correctIban($daoEspadon->Iban);
      $daoEspadon->contactId = _getContactIdWithExternalId($daoEspadon->ExterneId, $logger, $warnings);
      if (!empty($daoEspadon->contactId)) {
        _processEspadonRecord($daoEspadon, $logger, $warnings, $errors);
        $migrated++;
      } else {
        $daoEspadon->contactId = _getContactIdWithMandate($daoEspadon->Mandate, $logger, $errors);
        if (!empty($daoEspadon->contactId)) {
          _processEspadonRecord($daoEspadon, $logger, $warnings, $errors);
          $migrated++;
        }
      }
    }
  }
  $returnValues = array("Migratie klaar, " . $processed . " records uit Espadon verwerkt met " . $errors
      . " fouten, " . $warnings . " waarschuwingen en " . $migrated . " records gemigreerd");
  return civicrm_api3_create_success($returnValues, $params, 'Espadon', 'Migrate');
}

/**
 * Function to get the campaign id with the bron from Espadon (should be the same as the first 4 digits
 * of the campaign title)
 *
 * @param string $bron
 * @param $logger
 * @param $warnings
 * @return int $campaignId
 */
function _getCampaignIdWithBron($bron, $logger, &$warnings) {
  $campaignId = null;
  $query = "SELECT id, title FROM civicrm_campaign WHERE SUBSTRING(title,1,4) = %1";
  $params = array(1 => array($bron, "String"));
  $dao = CRM_Core_DAO::executeQuery($query, $params);
  if ($dao->fetch()) {
    $campaignId = $dao->id;
  }
  if (empty($campaignId)) {
    $logger->logMessage("Warning", "Geen campagne gevonden met bron: " . $bron);
    $warnings++;
  }
  return $campaignId;
}

/**
 * Function to get the contact data with the externeId from Espadon. Return empty array if not found
 *
 * @param string $externeId
 * @param $logger
 * @param $warnings
 * @return int $contact
 */
function _getContactIdWithExternalId($externeId, $logger, &$warnings) {
  $contactId = null;
  if (!empty($externeId)) {
    $params = array(
      'external_identifier' => $externeId,
      'return' => "id"
    );
    try {
      $contactId= civicrm_api3("Contact", "Getvalue", $params);
    } catch (CiviCRM_API3_Exception $ex) {}
  }
  if (empty($contactId)) {
    $logger->logMessage("Waarschuwing", "Geen contact gevonden met extern id: " . $externeId);
    $warnings++;
  }
  return $contactId;
}

/**
 * Function to get contact with mandate (route via pledge with mandate custom field)
 *
 * @param string $mandate
 * @param $logger
 * @param $errors
 * @return array $contact
 */
function _getContactIdWithMandate($mandate, $logger, &$errors) {
  $contactId = null;
  if (!empty($mandate)) {
    $query = "SELECT entity_id FROM civicrm_value_sepa_direct_debit_2 WHERE mandate_3 = %1";
    $params = array(1 => array($mandate, "String"));
    $pledgeId = CRM_Core_DAO::singleValueQuery($query, $params);
    if (!empty($pledgeId)) {
      $pledgeParams = array(
        'id' => $pledgeId,
        'return' => "contact_id"
      );
      try {
        $contactId = civicrm_api3("Pledge", "Getvalue", $pledgeParams);
      } catch (CiviCRM_API3_Exception $ex) {}
    }
  }
  if (empty($contactId)) {
    $logger->logError("Geen contact in CiviCRM gevonden voor dit mandaat", 0, 0, 0, 0, "Mandaat uit Espadon: " . $mandate);
    $errors++;
  }
  return $contactId;
}

/**
 * Function to process a valid espadon record into recurring contribution and mandate
 *
 * @param $daoEspadon
 * @param $logger
 * @param $warnings
 * @param $errors
 */
function _processEspadonRecord($daoEspadon, $logger, &$warnings, &$errors) {
  if (!empty($daoEspadon->Startdatum)) {
    $startDate = _correctDate($daoEspadon->Startdatum);
  } else {
    $startDate = "";
  }
  if (!empty($daoEspadon->Inputdatum)) {
    $createDate = _correctDate($daoEspadon->Inputdatum);
  } else {
    $createDate = "";
  }
  if (!empty($daoEspadon->DatumeersteBetaling)) {
    $firstDate = _correctDate($daoEspadon->DatumeersteBetaling);
  } else {
    $firstDate = "";
  }
  if (!empty($daoEspadon->DatumLaatsteBetaling)) {
    $lastDate = _correctDate($daoEspadon->DatumLaatsteBetaling);
  } else {
    $lastDate = "";
  }
  /*
   * validate IBAN and only process if IBAN is valid. Validate BIC and lookup if not valid or empty
   */
  if (_validateIban($daoEspadon, $logger, $errors) == TRUE) {
    $bic = _lookupBic($daoEspadon->Iban, $logger, $warnings);
    $recurringParams = array(
      'contact_id' => $daoEspadon->contactId,
      'amount' => $daoEspadon->Bedrag,
      'currency' => "EUR",
      'contribution_status_id' => 5,
      'frequency_interval' => 1,
      'frequency_unit' => _convertFrequency($daoEspadon->Frequentie),
      'cycle_day' => _convertCycleDay($lastDate, $firstDate),
      'financial_type_id' => 4,
      'payment_instrument_id' => (int)CRM_Core_OptionGroup::getValue("payment_instrument", "RCUR", "name"),
      'status' => "RCUR",
      'sequential' => 0
    );
    if (!empty($daoEspadon->campaignId)) {
      $recurringParams['campaign_id'] = $daoEspadon->campaignId;
    }
    if (!empty($startDate)) {
      $recurringParams['start_date'] = $startDate;
    }
    if (!empty($createDate)) {
      $recurringParams['create_date'] = $createDate;
    }
    $recur = _createRecurringContribution($recurringParams, $logger, $errors);

    $mandateParams = array(
      'contact_id' => $daoEspadon->contactId,
      'entity_table' => "civicrm_contribution_recur",
      'entity_id' => $recur['id'],
      'source' => "Migratie 2015",
      'creditor_id' => 2,
      'type' => "RCUR",
      'status' => "RCUR",
      'iban' => $daoEspadon->Iban,
      'bic' => $bic,
      'reference' => $daoEspadon->Mandate,
      'sequential' => 0
    );
    if (!empty($startDate)) {
      $mandateParams['validation_date'] = $startDate;
    }
    if (!empty($createDate)) {
      $mandateParams['creation_date'] = $createDate;
    }
    $mandate = _createSDDMandate($mandateParams, $logger, $errors);
  }
}

/**
 * Function to create the recurring contribution
 *
 * @param $params
 * @param $logger
 * @param $errors
 * @return array
 */
function _createRecurringContribution($params, $logger, &$errors) {
  try {
    $recur = civicrm_api3("ContributionRecur", "Create", $params);
    return $recur;
  } catch (CiviCRM_API3_Exception $ex) {
    $logger->logError("API fout bij aanmaken recurring", $params['contact_id'], 0, 0, 0,
      "Params voor API ContributionRecur Create: ".implode(";", $params));
    $errors++;
    return array();
  }
}

/**
 * Function to create mandate
 *
 * @param $params
 * @param $logger
 * @param $errors
 * @return array
 */
function _createSDDMandate($params, $logger, &$errors) {
  try {
    $mandate = civicrm_api3("SepaMandate", "create", $params);
    return $mandate;
  } catch (CiviCRM_API3_Exception $ex) {
    $logger->logError("API fout bij aanmaken SDD", $params['contact_id'], 0, $params['entity_id'], 0,
      "Mandaat is ".$params['reference'].", melding van API: ".$ex->getMessage());
    $errors++;
    return array();
  }
}

/**
 * Function to validate IBAN and log error if invalid
 *
 * @param $daoEspadon
 * @param $logger
 * @param $errors
 * @return bool
 */
function _validateIban($daoEspadon, $logger, &$errors) {
  $validateIban = CRM_Sepa_Logic_Verification::verifyIBAN($daoEspadon->Iban);
  if ($validateIban == "IBAN is not correct") {
    $logger->logError("Iban afgekeurd", $daoEspadon->contactId, 0, 0,  0,
      "Iban is :".$daoEspadon->Iban." bij mandaat ".$daoEspadon->Mandate);
    $errors++;
    return FALSE;
  } else {
    return TRUE;
  }
}

/**
 * Function to lookup Bic or set empty if not found
 *
 * @param $iban
 * @param $logger
 * @param $warnings
 * @return array|string
 */
function _lookupBic($iban, $logger, &$warnings) {
  try {
    $bic = civicrm_api3("Bic", "Getfromiban", array('iban' => $iban));
    return $bic;
  } catch (CiviCRM_API3_Exception $ex) {
    $warnings++;
    $logger->logMessage("Waarschuwing", "Geen BIC gevonden voor Iban ".$iban.", mandaat aangemaakt met lege BIC");
    return "";
  }
}

/**
 * Function to set cycle day
 *
 * @param $laatsteDatum
 * @param $eersteDatum
 * @return int
 */
function _convertCycleDay($laatsteDatum, $eersteDatum) {
  if (!empty($laatsteDatum)) {
    $testDatum = $laatsteDatum;
  } else {
    $testDatum = $eersteDatum;
  }
  $datumParts = explode("/", $testDatum);
  if ($datumParts[0] < 16) {
    return 7;
  } else {
    return 21;
  }
}

/**
 * Function to convert frequency from espadon to civi terminology
 *
 * @param $sourceFrequency
 * @return string
 */
function _convertFrequency($sourceFrequency) {
  switch ($sourceFrequency) {
    case "Annuel":
      return "year";
    break;
    case "Trimestriel":
      return "quarter";
    break;
    default:
      return "month";
    break;
  }
}

/**
 * Function to remove spaces from mandate
 * and pad left with 0 if only numbers
 *
 * @param $mandate
 * @return string
 */
function _correctMandate($mandate) {
  $correctedMandate = array();
  if (!empty($mandate)) {
    $done = 0;
    while ($done < strlen($mandate)) {
      $digit = substr($mandate, $done, 1);
      if ($digit != " ") {
        $correctedMandate[$done] = $digit;
      }
      $done++;
    }
  }
  $correctedMandate = implode("", $correctedMandate);
  if (is_numeric($correctedMandate)) {
    $correctedMandate = str_pad($correctedMandate, 5, "0", STR_PAD_LEFT);
  }
  return (string) $correctedMandate;
}

/**
 * Function to remove spaces from iban
 *
 * @param $mandate
 * @return string
 */
function _correctIban($iban) {
  $correctedIban = array();
  if (!empty($iban)) {
    $done = 0;
    while ($done < strlen($iban)) {
      $digit = substr($iban, $done, 1);
      if ($digit != " ") {
        $correctedIban[$done] = $digit;
      }
      $done++;
    }
  }
  return (string) implode("", $correctedIban);
}

/**
 * Function to set espadon record as processed
 * @param $espadonId
 */
function _setEspadonProcessed($espadonId) {
  $query = "UPDATE espadon_data SET processed = %1 WHERE espadon_id = %2";
  $params = array(
    1 => array(1, "Integer"),
    2 => array($espadonId, "Integer")
  );
  CRM_Core_DAO::executeQuery($query, $params);
}

/**
 * Function to process csv date which throws errors
 *
 * @param $sourceDate
 * @return string
 */
function _correctDate($sourceDate) {
  $sourceDate = (string) $sourceDate;
  $dateParts = explode("/", $sourceDate);
  $cleanDate = $dateParts[2].$dateParts[1].$dateParts[0];
  $outDate = new DateTime($cleanDate);
  return $outDate->format("Ymd");
}
