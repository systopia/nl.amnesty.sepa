<?php

global $debugMode;
$debugMode=1;

  function print_debug ($obj) {
    global $debugMode;
    if ($debugMode) {
      print_r($obj);
    }
}

function civicrm_api3_contribution_migrate($params) {

global $debugMode;
$migrated=array();

if (!array_key_exists ('option.limit',$params)) {
  echo "option.limit defaults to 1\n";
  $params["option.limit"] = 1;
}
if (!array_key_exists ('debug',$params)) {
  echo "no debug mode. Set debug=0 if you don't want debug msg\n";
  $params["debug"] = 1;
}
$debugMode = $params["debug"];

$c = CRM_Core_DAO::executeQuery("SELECT c.contact_id, c.id, c.total_amount as amount, c.campaign_id, p.id as pledge_id, p.status_id,p.frequency_unit, p.frequency_interval, p.frequency_day,p.start_date,p.create_date,p.acknowledge_date,p.end_date,p.cancel_date, m.bankname_pledge_7 as bank, m.mandate_3 as mandate, iban_pledge_5 as iban,bic_pledge_6 as bic      from civicrm_contribution as c left join civicrm_pledge as p on p.campaign_id=c.campaign_id and  p.contact_id = c.contact_id left join civicrm_campaign on civicrm_campaign.id=c.campaign_id  left join civicrm_value_sepa_direct_debit_2 as m on m.entity_id=p.id where c.financial_type_id=4 AND p.original_installment_amount = c.total_amount and c.total_amount > 0 AND contribution_recur_id is null AND c.campaign_id is not null order by receive_date desc limit %1;",array(1 => array($params["option.limit"], 'Integer')));

while ($c->fetch()) {
   if ($c->frequency_unit == "quarter") {
     echo "skipping quarterly pledge ";
         print_r ($c);
     continue;
   }    

         print_debug ($c);
         if (in_array($c->pledge_id,$migrated))
           continue;//this contribution is part of a pledge that has already been migrated in this batch
         $migrated[] = $c->pledge_id;
         $cr = civicrm_api3 ("ContributionRecur","create", array(
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
            "payment_instrument_id" => (int) CRM_Core_OptionGroup::getValue('payment_instrument', 'RCUR', 'name'),
            "status" => 'RCUR',
            "campaign_id" => $c->campaign_id,
            "sequential"=>0
          ));
          print_debug ($cr);
          $mandate = array ("contact_id" => $c->contact_id, "entity_table" => "civicrm_contribution_recur", "entity_id" => $cr["id"], 
             "source"=> "pledge:". $c->pledge_id, "creditor_id" => 1, 
            "type"=>"RCUR", "status" => "RCUR", "creation_date" => $c->create_date, "validation_date" => $c->start_date,
            "iban"=>$c->iban,
            "bic"=>$c->bic,
            "reference"=>$c->mandate,
            "bank"=>$c->bank,
            "sequential"=>0
          );
          try {
            $r = civicrm_api3 ("SepaMandate","create", $mandate);
          } catch (Exception $e) {
            echo "duplicate mandate {$c->mandate} for {$c->contact_id}\n";

            $mandate = array ("contact_id" => $c->contact_id, "entity_table" => "civicrm_contribution_recur", "entity_id" => $cr["id"], 
               "source"=> "pledge:". $c->pledge_id, "creditor_id" => 1, 
              "type"=>"RCUR", "status" => "RCUR", "creation_date" => $c->create_date, "validation_date" => $c->start_date,
              "iban"=>$c->iban,
              "bic"=>$c->bic,
              "reference"=>"DUP ".$c->mandate . ":".$cr["id"],
              "bank"=>$c->bank,
              "sequential"=>0
            );
            $r = civicrm_api3 ("SepaMandate","create", $mandate);
          }
          print_debug ($r);
          $t =CRM_Core_DAO::executeQuery("UPDATE civicrm_contribution set contribution_recur_id = %1 where contact_id = %2 and financial_type_id=4 and total_amount = %3;",array (1 => array($cr["id"], 'Integer'),2=>array($c->contact_id, 'Integer'),3=>array($c->amount, 'Money')));

          echo "migrated pledge for {$c->contact_id}\n";

  }

}
