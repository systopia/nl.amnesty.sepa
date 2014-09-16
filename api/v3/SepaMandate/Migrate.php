<?php 
function civicrm_api3_sepa_mandate_migrate($params) {

	if (!array_key_exists ('option.limit',$params)) {
	  echo "option.limit defaults to 1\n";
	  $params["option.limit"] = 1;
	}
	if (!array_key_exists ('debug',$params)) {
	  echo "no debug mode\n";
	  $params["debug"] = 0;
	}
	$params['pledge_status'] = '"In Progress"';
	$params['is_test'] = 0;

	$result = civicrm_api3('Pledge', 'get', $params);

	foreach ($result["values"] as $pledge) {
	  if ($params["debug"]) print_r($pledge);
	  $cr = civicrm_api3 ("ContributionRecur","create", array(
	    "contact_id" => $pledge["contact_id"],
	    "amount" => $pledge["pledge_amount"],
	    "currency" => $pledge["pledge_currency"],
	    "frequency_interval" => $pledge["pledge_frequency_interval"],
	    "frequency_unit" => $pledge["pledge_frequency_unit"],
	    "start_date" => $pledge["pledge_create_date"],
	    "create_date" => $pledge["pledge_create_date"],
	    "next_sched_contribution_date" => $pledge["pledge_next_pay_date"],
	    "financial_type_id" => $pledge["pledge_financial_type"],
	    "payment_instrument_id" => (int) CRM_Core_OptionGroup::getValue('payment_instrument', 'RCUR', 'name'),
	    "status" => 'RCUR',
	    "campaign_id" => $pledge["pledge_campaign_id"]
	  ));
	  $crid = $cr["id"];
	  $r = civicrm_api3 ("CustomValue","get", array("entity_id"=>$pledge["contact_id"]));
	  $mandate = array ("contact_id" => $pledge["contact_id"], "entity_table" => "civicrm_contribution_recur", "entity_id" => $crid,
	     "source"=> "pledge:". $pledge["id"], "creditor_id" => 1,
	    "type"=>"RCUR", "status" => "RCUR", "creation_date" => $pledge["pledge_create_date"], "validation_date" => $pledge["pledge_create_date"]
	  );
	  foreach ($r["values"] as $cf) {
	     if ($cf[id] == 1 ) 
	       $mandate["iban"] = $cf["latest"];
	     if ($cf[id] == 2 ) 
	       $mandate["bic"] = $cf["latest"];
	     if ($cf[id] == 4 ) 
	       $mandate["bank"] = $cf["latest"];
	  }
	  $r = civicrm_api3 ("SepaMandate","create", $mandate);
	 
	  $pp= civicrm_api3 ("PledgePayment","get", array ("pledge_id" => $pledge["id"], "status_id" => 1));
	  foreach ($pp["values"] as $c) {
            if (!array_key_exists("contribution_id",$c)) {
              echo "pledge payment in problem";
              print_r ($c);
              continue;
            }
	    $t= civicrm_api3 ("Contribution","create", array ("id" => $c["contribution_id"],  "contribution_recur_id"=> $crid, "contact_id"=>$pledge["contact_id"]));
	  }
	   
	  if (!$r["is_error"]) {
	    echo "\nok pledge ".$pledge["id"]. " for ".  $pledge["display_name"].":". $pledge["contact_id"]. "-> contrib recur ". $crid;
	    $r=civicrm_api3("Pledge","create",array ("id"=>$pledge["id"],"is_test" => 1));
	  }
	  
	}

}



