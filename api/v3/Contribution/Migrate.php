<?php
function civicrm_api3_contribution_migrate($params) {

  $logger = new CRM_MigrateLogger();
  $migrated = array();

  if (!array_key_exists('option.limit', $params)) {
    $logger->logMessage('Warning', 'option.limit defaults to 1');
    $params["option.limit"] = 1;
  } else {
    $logger->logMessage('Warning', 'option.limit set to '.$params['option.limit']);
  }


  $c = CRM_Core_DAO::executeQuery("SELECT c.contact_id, c.id, c.total_amount AS amount,
c.campaign_id, p.id AS pledge_id, p.status_id,p.frequency_unit, p.frequency_interval, p.frequency_day,p.start_date,
p.create_date,p.acknowledge_date,p.end_date,p.cancel_date, m.bankname_pledge_7 AS bank, m.mandate_3 AS mandate,
iban_pledge_5 AS iban,bic_pledge_6 AS bic
FROM civicrm_contribution AS c
LEFT JOIN civicrm_pledge AS p ON p.campaign_id=c.campaign_id AND  p.contact_id = c.contact_id
LEFT JOIN civicrm_campaign ON civicrm_campaign.id=c.campaign_id
LEFT JOIN civicrm_value_sepa_direct_debit_2 AS m ON m.entity_id=p.id
WHERE c.financial_type_id=4 AND p.original_installment_amount = c.total_amount AND c.total_amount > 0
AND contribution_recur_id IS NULL AND c.campaign_id IS NOT NULL ORDER BY receive_date DESC
LIMIT %1;", array(1 => array($params["option.limit"], 'Integer')));

  while ($c->fetch()) {
    if ($c->frequency_unit == "quarter") {
      $logger->logMessage("Warning", "Skipping quarterly pledge ".$c->pledge_id
          ." for contact ".$c->contact_id." with mandate ".$c->mandate);
      continue;
    }
   if (in_array($c->pledge_id, $migrated)) {
      continue;//this contribution is part of a pledge that has already been migrated in this batch
    }

    $logger->logMessage("Info", "Processing pledge ".$c->pledge_id." for contact "
        .$c->contact_id." with mandate ".$c->mandate);

    $migrated[] = $c->pledge_id;

    $cr = civicrm_api3("ContributionRecur", "create", array(
        "contact_id" => $c->contact_id,
        "amount" => $c->amount,
        "currency" => "EUR",
        "contribution_status_id" => $c->status_id,
        "frequency_interval" => $c->frequency_interval,
        "frequency_unit" => $c->frequency_unit,
        "cycle_day" => $c->frequency_day,
        "start_date" => $c->start_date,
        "create_date" => $c->create_date,
        "end_date" => $c->end_date,
        "cancel_date" => $c->cancel_date,
        "financial_type_id" => 4,
        "payment_instrument_id" => (int)CRM_Core_OptionGroup::getValue('payment_instrument', 'RCUR', 'name'),
        "status" => 'RCUR',
        "campaign_id" => $c->campaign_id,
        "sequential" => 0
    ));

    $logger->logMessage("Info", "Created recurring contribution ".$cr['id']." for pledge ".
        $c->pledge_id." and contact_id ".$c->contact_id);

    $verifyIBAN = CRM_Sepa_Logic_Verification::verifyIBAN($c->iban);
    if ($verifyIBAN) {
      $logger->logError("IBAN is invalid, emptied mandate IBAN and BIC", $c->contact_id, $c->pledge_id,
          $cr['id'], $c->campaign_id, "IBAN: ".$c->iban);
      $c->iban = "";
      $c->bic = "";
      $reference = "INVIBAN".$c->mandate.": ".$cr["id"];
    } else {
      $verifyBIC = CRM_Sepa_Logic_Verification::verifyBIC($c->bic);
      if ($verifyBIC == "BIC is not correct") {
        // try lookup with Iban
        try {
          $result = civicrm_api3('Bic', 'getfromiban', array('iban' => $c->iban));
        } catch (CiviCRM_API3_Exception $ex) {
          if (empty($result['bic'])) {
            $logger->logError("BIC was invalid, replaced with look up of BIC", $c->contact_id, $c->pledge_id, $cr['id'],
                $c->campaign_id, "BIC: " . $c->bic . " with IBAN: " . $c->iban);
            $c->bic = $result['bic'];
          } else {
            $logger->logError("BIC is invalid, emptied mandate BIC", $c->contact_id, $c->pledge_id, $cr['id'],
                $c->campaign_id, "BIC: " . $c->bic . " with IBAN: " . $c->iban);
            $c->bic = "";
            $reference = "INVBIC" . $c->mandate . ": " . $cr["id"];
          }
        }
      } else {
        $reference = $c->mandate;
      }
    }

    // add check on length of reference
    if (strlen($reference) > 35) {
      $logger->logError("Mandate was more than 35 characters, will be truncated to 35",
          $c->contact_id, $c->pledge_id, $cr['id'], $c->campaign_id, "Mandate was ".$reference
          ." and will be ".substr($reference,0,35));
      $reference = substr($reference,0,35);
    }
    $mandate = array("contact_id" => $c->contact_id,
        "entity_table" => "civicrm_contribution_recur",
        "entity_id" => $cr["id"],
        "source" => "pledge:" . $c->pledge_id, "creditor_id" => 1,
        "type" => "RCUR", "status" => "RCUR", "creation_date" => $c->create_date,
        "validation_date" => $c->start_date,
        "iban" => $c->iban,
        "bic" => $c->bic,
        "reference" => $reference,
        "bank" => $c->bank,
        "sequential" => 0
    );

    try {
      $r = civicrm_api3("SepaMandate", "create", $mandate);
      $logger->logMessage("Info", "Added mandate ".$reference." for contact ".$c->contact_id.
          " recurring contribution ".$cr['id']." and pledge ".$c->pledge_id);
    } catch (Exception $e) {

      $mandate = array("contact_id" => $c->contact_id,
          "entity_table" => "civicrm_contribution_recur",
          "entity_id" => $cr["id"],
          "source" => "pledge:" . $c->pledge_id, "creditor_id" => 1,
          "type" => "RCUR", "status" => "RCUR", "creation_date" => $c->create_date, "validation_date" => $c->start_date,
          "iban" => $c->iban,
          "bic" => $c->bic,
          "bank" => $c->bank,
          "sequential" => 0
      );
      if (empty($c->iban)) {
        $mandate['reference'] = "DUP ".$reference;
      } else {
        $mandate['reference'] = "DUP".$reference.": ".$cr["id"];
      }
      $logger->logError("Creating duplicate mandate", $c->contact_id, $c->pledge_id,
          $cr['id'], $c->campaign_id, "Original mandate is ".$c->mandate.", duplicate mandate is "
          .$mandate['reference']);
      $r = civicrm_api3("SepaMandate", "create", $mandate);
    }
    $t = CRM_Core_DAO::executeQuery("UPDATE civicrm_contribution SET contribution_recur_id = %1
WHERE contact_id = %2 AND financial_type_id=4 AND total_amount = %3;",
        array(1 => array($cr["id"], 'Integer'), 2 => array($c->contact_id, 'Integer'),
            3 => array($c->amount, 'Money')));

    $logger->logMessage("Info", "migrated pledge for ".$c->contact_id);
  }
}

