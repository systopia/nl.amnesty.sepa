<?php

global $debugMode;
$debugMode=1;

  function print_debug ($obj) {
    global $debugMode;
    if ($debugMode) {
      print_r($obj);
    }
}

function civicrm_api3_contribution_migrate($params)
{

  $errorFile = "CREATE TABLE IF NOT EXISTS `sepa_migrate_errors` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `migration_date` DATE DEFAULT NULL,
  `error_message` TEXT,
  `contact_id` INT(11) DEFAULT NULL,
  `pledge_id` INT(11) DEFAULT NULL,
  `recur_id` INT(11) DEFAULT NULL,
  `campaign_id` INT(11) DEFAULT NULL,
  `iban` VARCHAR(45) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id_UNIQUE` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
";
  CRM_Core_DAO::executeQuery($errorFile);

  global $debugMode;
  $migrated = array();

  if (!array_key_exists('option.limit', $params)) {
    echo "option.limit defaults to 1\n";
    $params["option.limit"] = 1;
  }
  if (!array_key_exists('debug', $params)) {
    echo "no debug mode. Set debug=0 if you don't want debug msg\n";
    $params["debug"] = 1;
  }
  $debugMode = $params["debug"];

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
      echo "skipping quarterly pledge ";
      print_r($c);
      continue;
    }

    print_debug($c);
    if (in_array($c->pledge_id, $migrated))
      continue;//this contribution is part of a pledge that has already been migrated in this batch
    $migrated[] = $c->pledge_id;

    if (CRM_Sepa_Logic_Verification::verifyIBAN($c->iban) == FALSE) {
      _logErrorRecord('IBAN is invalid', $c->contact_id, $c->pledge_id, $cr['id'], $c->campaign_id, $c->iban);
      $c->iban = "";
    }

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
    print_debug($cr);
    $mandate = array("contact_id" => $c->contact_id,
        "entity_table" => "civicrm_contribution_recur",
        "entity_id" => $cr["id"],
        "source" => "pledge:" . $c->pledge_id, "creditor_id" => 1,
        "type" => "RCUR", "status" => "RCUR", "creation_date" => $c->create_date,
        "validation_date" => $c->start_date,
        "iban" => $c->iban,
        "bic" => $c->bic,
        "reference" => $c->mandate,
        "bank" => $c->bank,
        "sequential" => 0
    );
    try {
      $r = civicrm_api3("SepaMandate", "create", $mandate);
    } catch (Exception $e) {
      echo "duplicate mandate {$c->mandate} for {$c->contact_id}\n";

      $mandate = array("contact_id" => $c->contact_id,
          "entity_table" => "civicrm_contribution_recur",
          "entity_id" => $cr["id"],
          "source" => "pledge:" . $c->pledge_id, "creditor_id" => 1,
          "type" => "RCUR", "status" => "RCUR", "creation_date" => $c->create_date, "validation_date" => $c->start_date,
          "iban" => $c->iban,
          "bic" => $c->bic,
          "reference" => "DUP " . $c->mandate . ":" . $cr["id"],
          "bank" => $c->bank,
          "sequential" => 0
      );
      $r = civicrm_api3("SepaMandate", "create", $mandate);
    }
    print_debug($r);
    $t = CRM_Core_DAO::executeQuery("UPDATE civicrm_contribution SET contribution_recur_id = %1 WHERE contact_id = %2 AND financial_type_id=4 AND total_amount = %3;", array(1 => array($cr["id"], 'Integer'), 2 => array($c->contact_id, 'Integer'), 3 => array($c->amount, 'Money')));

    echo "migrated pledge for {$c->contact_id}\n";

  }
}
function _logErrorRecord($message, $contactId = null, $pledgeId = null, $recurId = null, $campaignId = null, $iban = null) {
  $whereClauses = array();
  $whereClauses[] = 'error_message = %1';
  $params[1] = array($message, 'String');
  $whereClauses[] = 'migration_date = %2';
  $params[2] = array(date('Ymd'), 'Date');
  $count = 2;

  if (!empty($contactId)) {
    $count++;
    $whereClauses[] = 'contact_id = %'.$count;
    $params[$count] = array($contactId, 'Integer');
  }

  if (!empty($pledgeId)) {
    $count++;
    $whereClauses[] = 'pledge_id = %'.$count;
    $params[$count] = array($pledgeId, 'Integer');
  }

  if (!empty($recurId)) {
    $count++;
    $whereClauses[] = 'recur_id = %'.$count;
    $params[$count] = array($recurId, 'Integer');
  }

  if (!empty($campaignId)) {
    $count++;
    $whereClauses[] = 'campaign_id = %'.$count;
    $params[$count] = array($campaignId, 'Integer');
  }

  if (!empty($iban)) {
    $count++;
    $whereClauses[] = 'iban = %'.$count;
    $params[$count] = array($iban, 'String');
  }
  $query = "INSERT INTO sepa_migrate_errors SET ".implode(', ', $whereClauses);

  CRM_Core_DAO::executeQuery($query, $params);
}

