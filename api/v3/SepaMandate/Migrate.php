<?php 
function civicrm_api3_sepa_mandate_migrate($params) {

	if (!array_key_exists ('option.limit',$params)) {
	  echo "option.limit defaults to 1\n";
	  $params["option.limit"] = 1;
	}
	if (!array_key_exists ('debug',$params)) {
	  echo "no debug mode. Set debug=0 if you don't want debug msg\n";
	  $params["debug"] = 1;
	}
	$params['pledge_status_id'] = 5;
	$params['pledge_is_test'] = 0;

	$result = civicrm_api3('Pledge', 'getbasic', $params);

	foreach ($result["values"] as $pledge) {
	  if ($params["debug"]) print_r($pledge);
          if ($params["debug"]) {
	    $tmp = civicrm_api3('ContributionRecur', 'get', array("contact_id"  => $pledge["contact_id"] ));
            if ($tmp["count"] > 0) {
              echo "\n skipping pledge ".  $pledge["id"]. " for contact ".$pledge["contact_id"];
              continue;
            }
          }
          if (!$pledge["original_installment_amount"]) {
            print_r($pledge);
            die("error in a pledge");
          }
	  $cr = civicrm_api3 ("ContributionRecur","create", array(
	    "contact_id" => $pledge["contact_id"],
	    "amount" => $pledge["original_installment_amount"],
	    "currency" => $pledge["currency"],
            "contribution_status_id" => $pledge["status_id"],
	    "frequency_interval" => $pledge["frequency_interval"],
	    "frequency_unit" => $pledge["frequency_unit"],
	    "frequency_day" => $pledge["frequency_day"],
	    "start_date" => $pledge["start_date"],
	    "create_date" => $pledge["create_date"],
	    "financial_type_id" => $pledge["financial_type_id"],
	    "payment_instrument_id" => (int) CRM_Core_OptionGroup::getValue('payment_instrument', 'RCUR', 'name'),
	    "status" => 'RCUR',
	    "campaign_id" => $pledge["campaign_id"]
	  ));
	  $crid = $cr["id"];
	  $r = civicrm_api3 ("CustomValue","get", array("entity_id"=>$pledge["id"],"entity_table"=>"civicrm_pledge"));
	  $mandate = array ("contact_id" => $pledge["contact_id"], "entity_table" => "civicrm_contribution_recur", "entity_id" => $crid,
	     "source"=> "pledge:". $pledge["id"], "creditor_id" => 1,
	    "type"=>"RCUR", "status" => "RCUR", "creation_date" => $pledge["create_date"], "validation_date" => $pledge["start_date"]
	  );
	  foreach ($r["values"] as $cf) {
	     if ($cf[id] == 5 ) 
	       $mandate["iban"] = $cf["latest"];
	     if ($cf[id] == 6 ) 
	       $mandate["bic"] = $cf["latest"];
	     if ($cf[id] == 3 ) 
	       $mandate["reference"] = $cf["latest"];
	     if ($cf[id] == 7 ) 
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
	    $r=civicrm_api3("Pledge","createbasic",array ("id"=>$pledge["id"],"is_test" => 1));
	  }
	  
	}

}



