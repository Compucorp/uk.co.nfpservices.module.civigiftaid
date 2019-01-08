<?php

class CRM_Civigiftaid_Page_AddToBatchSummary extends CRM_Core_Page {

  public function run() {

  	$status = CRM_Utils_Array::value('status', $_GET);

  	// get validated stats
  	list( $total, $added, $alreadyAdded, $notValid ) = CRM_Civigiftaid_Form_Task_AddToBatch::getValidationStats();

  	// get title & contributionIds depends on the status
  	switch ($status) {
  		case 'tobeadded':
  			$contributionIds = $added;
  			$title = 'Contributions that will be added to this batch';
  			break;

  		case 'alreadyadded':
  			$contributionIds = $alreadyAdded;
  			$title = 'Contributions already in a batch';
  			break;

  		case 'invalid':
  			$contributionIds = $notValid;
  			$title = 'Contributions not valid for gift aid';
  			break;

  		default:
  			$contributionIds = array();
  			$title = '';
  			break;
  	}

  	CRM_Utils_System::setTitle(ts($title));

  	$contributionsRows = array();
  	$errorMessage = '';
  	if (!empty($contributionIds)) {

  		$contributionsRows = CRM_Civigiftaid_Utils_Contribution::getContributionDetails ($contributionIds);

  	} else {
  		$errorMessage = 'No contribution records to display. Please contact admin!';
  		CRM_Core_Error::debug_var('No contribution Ids received in CRM_Civigiftaid_Page_AddToBatchSummary : ', $_GET);
  	}

  	$this->assign('contributionsRows', $contributionsRows);
  	$this->assign('errorMessage', $errorMessage);

	 	parent::run();
  }

}
