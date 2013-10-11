<?php

/*
 +--------------------------------------------------------------------+
 | CiviCRM version 4.3                                               |
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC (c) 2004-2011                                |
 +--------------------------------------------------------------------+
 | This file is a part of CiviCRM.                                    |
 |                                                                    |
 | CiviCRM is free software; you can copy, modify, and distribute it  |
 | under the terms of the GNU Affero General Public License           |
 | Version 3, 19 November 2007 and the CiviCRM Licensing Exception.   |
 |                                                                    |
 | CiviCRM is distributed in the hope that it will be useful, but     |
 | WITHOUT ANY WARRANTY; without even the implied warranty of         |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.               |
 | See the GNU Affero General Public License for more details.        |
 |                                                                    |
 | You should have received a copy of the GNU Affero General Public   |
 | License and the CiviCRM Licensing Exception along                  |
 | with this program; if not, contact CiviCRM LLC                     |
 | at info[AT]civicrm[DOT]org. If you have questions about the        |
 | GNU Affero General Public License or the licensing of CiviCRM,     |
 | see the CiviCRM license FAQ at http://civicrm.org/licensing        |
 +--------------------------------------------------------------------+
*/

/**
 *
 * @package CRM
 * @copyright CiviCRM LLC (c) 2004-2013
 * $Id$
 *
 */

/**
 *
 * @package CRM
 * @copyright CiviCRM LLC (c) 2004-2013
 * $Id$
 *
 */
class GiftAid_Utils_Contribution {

    
    
    /**
     * Given an array of contributionIDs, add them to a batch
     *
     * @param array  $contributionIDs (reference ) the array of contribution ids to be added
     * @param int    $batchID - the batchID to be added to
     *
     * @return array             (total, added, notAdded) ids of contributions added to the batch
     * @access public
     * @static
     */
    static function addContributionToBatch( $contributionIDs, $batchID ) {
        $date = date('YmdHis');
        $contributionsAdded    = array( );
        $contributionsNotAdded = array( );

        require_once "GiftAid/Utils/GiftAid.php";
        require_once "CRM/Contribute/BAO/Contribution.php";
        //require_once 'CRM/Core/DAO/EntityBatch.php';
        require_once 'CRM/Batch/DAO/EntityBatch.php';
        require_once "CRM/Core/BAO/Address.php";
        require_once "CRM/Contact/BAO/Contact.php";
        require_once "CRM/Utils/Address.php";
        
        
        // Get the batch name
        require_once 'CRM/Batch/DAO/Batch.php';
        $batch = new CRM_Batch_DAO_Batch( );
        $batch->id = $batchID;
        $batch->find(  true );
        $batchName = $batch->title;

        $batchNameGroup = civicrm_api('OptionGroup', 'getsingle', array(
            'version' => 3,
            'sequential' => 1,
            'name' => 'giftaid_batch_name ')
        );
        if($batchNameGroup['id']){
          $groupId = $batchNameGroup['id'];
          $params = array(
            'version' => 3,
            'sequential' => 1,
            'option_group_id' => $groupId,
            'value' => $batchName,
            'label' => $batchName
          );
          $result = civicrm_api('OptionValue', 'create', $params);
        }

        
        $charityColumnExists = CRM_Core_DAO::checkFieldExists( 'civicrm_value_gift_aid_submission', 'charity' );

        foreach ( $contributionIDs as $contributionID ) {
            //$batchContribution =& new CRM_Core_DAO_EntityBatch( );
            $batchContribution =& new CRM_Batch_DAO_EntityBatch();
            $batchContribution->entity_table = 'civicrm_contribution';
            $batchContribution->entity_id    = $contributionID;
    
      // check if the selected contribution id already in a batch
      // if not, add to batchContribution else keep the count of contributions that are not added
    
            if ( $batchContribution->find( true ) ) {
                $contributionsNotAdded[] = $contributionID;
                continue;
            }

                
            // get additional info
            // get contribution details from Contribution using contribution id
            $params    = array( 'id' => $contributionID );
            CRM_Contribute_BAO_Contribution::retrieve( $params, $contribution, $ids );
            $contactId = $contribution['contact_id'];

            // check if contribution is valid for gift aid
            if ( GiftAid_Utils_GiftAid::isEligibleForGiftAid( $contactId, $contribution['receive_date'], $contributionID ) ) {
                $batchContribution->batch_id = $batchID;
                $batchContribution->save( );
                
                // get gift aid amount
                $giftAidAmount = self::_calculateGiftAidAmt( $contribution['total_amount'] );


                // FIXME: check if there is customTable method
                $query = "
                          INSERT INTO civicrm_value_gift_aid_submission 
                          (entity_id, eligible_for_gift_aid, gift_aid_amount , amount , batch_name) 
                          VALUES 
                            ( %1, 1, %2, %3 , %4 )
                          ON DUPLICATE KEY UPDATE 
                          gift_aid_amount = %2 ,
                          amount = %3 ,
                          batch_name = %4
                          ";
                $sqlParams = array( 1 => array( $contributionID                 , 'Integer' ),
                                    2 => array( $giftAidAmount                  , 'Money'   ),
                                    3 => array( $contribution['total_amount']   , 'Money'   ),
                                    4 => array( $batchName                      , 'String'   ),
                                     );
                CRM_Core_DAO::executeQuery( $query, $sqlParams );

                $contributionsAdded[] = $contributionID;
            } else {
                $contributionsNotAdded[] = $contributionID;
            } 
        }

        if ( ! empty( $contributionsAdded ) ) {
            // if there is any extra work required to be done for contributions that are batched,
            // should be done via hook
            GiftAid_Utils_Hook::batchContributions( $batchID, $contributionsAdded );
        }

        return array( count($contributionIDs), 
                      count($contributionsAdded), 
                      count($contributionsNotAdded) );
    }


  static function removeContributionFromBatch($contributionIDs){
      $contributionRemoved = array();
      $contributionNotRemoved = array();
      
      list( $total, $contributionsToRemove, $notInBatch, $alreadySubmited) = self::_validationRemoveContributionFromBatch( $contributionIDs );

      require_once 'CRM/Batch/BAO/Batch.php';
      dprint_r('called before remove');
      $contributions = self::getContributionDetails($contributionsToRemove);

      if(!empty($contributions)){
        foreach($contributions  as $contribution){

          if(!empty($contribution['batch_id'])){

            $batchContribution =& new CRM_Batch_DAO_EntityBatch();
            $batchContribution->entity_table = 'civicrm_contribution';
            $batchContribution->entity_id    = $contribution['contribution_id'];
            $batchContribution->batch_id =  $contribution['batch_id'];
            $batchContribution->delete( );

            // FIXME: check if there API to user
            $query = "UPDATE civicrm_value_gift_aid_submission
                      SET gift_aid_amount = NULL,
                          amount = NULL,
                          batch_name = NULL
                      WHERE entity_id = %1";

            $sqlParams = array( 1 => array( $contribution['contribution_id']  , 'Integer' ));
            CRM_Core_DAO::executeQuery( $query, $sqlParams );

            array_push($contributionRemoved, $contribution['contribution_id']);

          }else{
            array_push($contributedNotRemoved, $contribution['contribution_id']);
          }
              
        }
      }
      return array( count($contributionIDs), 
                    count($contributionRemoved), 
                    count($contributionNotRemoved) );

    }  


    /*
  this function calculate the gift aid amount
  formula used is: (basic rate of year*contributed amount)/(100-basic rate of year)
  */
  function _calculateGiftAidAmt( $contributionAmount ){ 

    $gResult = civicrm_api('OptionGroup', 'getsingle', array(
      'version' => 3,
      'name' => 'giftaid_basic_rate_tax',
   ));
   if($gResult['id']){
     $params = array(
      'version' => 3,
      'sequential' => 1,
      'option_group_id' => $gResult['id'],
      'name' => 'basic_rate_tax',
     );
     $result = civicrm_api('OptionValue', 'get', $params);
     if($result['values']){
      $basicRate = null;
      foreach ($result['values'] as $ov) {
        if($result['id'] == $ov['id']){
         $basicRate = $ov['value'];
        }
      }
     }
   }

    //$basicRate = CIVICRM_GIFTAID_PERCENTAGE;
    //return (($contributionAmount * $basicRate) / 100);
    return (( $basicRate * $contributionAmount ) / ( 100- $basicRate ));
  }


  static function _isOnlineSubmissionExtensionInstalled(){
    $extensions = CRM_Core_PseudoConstant::getModuleExtensions();
    foreach ($extensions as $key => $extension) {
      if($extension['prefix'] == 'giftaidonline'){
        return true;
      }
    }
    return false;

  }


  static function _validationRemoveContributionFromBatch(&$contributionIDs ){
    $contributionsAlreadySubmited = array();
    $contributionsNotInBatch = array();
    $contributionsToRemove = array();

    foreach ($contributionIDs as $contributionID) {

      $batchContribution =& new CRM_Batch_DAO_EntityBatch( );
      $batchContribution->entity_table = 'civicrm_contribution';
      $batchContribution->entity_id    = $contributionID;

      // check if the selected contribution id is in a batch
      if (  $batchContribution->find( true ) ) {
        if(self::_isOnlineSubmissionExtensionInstalled()){
          //require_once 'CRM/Giftaidonline/Page/OnlineSubmission.php';
          //$onlineSubmission = new CRM_Giftaidonline_Page_OnlineSubmission();
          //$isSubmited = $onlineSubmission->is_submitted($batchContribution->batch_id);
          if(self::isBatchAlreadySubmited($batchContribution->batch_id)){
            $contributionsAlreadySubmited[] = $contributionID;
          }else{
           $contributionsToRemove[] = $contributionID;
          }
        }else{
          $contributionsToRemove[] = $contributionID;
        }

      } else {
        $contributionsNotInBatch[] = $contributionID;
      }
    }
      
     return array( count($contributionIDs), 
                   $contributionsToRemove,
                   $contributionsNotInBatch,
                   $contributionsAlreadySubmited);


  }

  /*
     * this function check contribution is valid for giftaid or not:
     * 1 - if contribution_id already inserted in batch_contribution
     * 2 - if contributions are not valid for gift aid
     */
  static function _validateContributionToBatch( &$contributionIDs )  {
    $contributionsAdded        = array( );
    $contributionsAlreadyAdded = array( );
    $contributionsNotValid     = array( );
                
        require_once "GiftAid/Utils/GiftAid.php";
        //require_once "CRM/Core/DAO/EntityBatch.php";
        require_once "CRM/Batch/DAO/EntityBatch.php";
        require_once "CRM/Contribute/BAO/Contribution.php";
        
        foreach ( $contributionIDs as $contributionID ) {
            $batchContribution =& new CRM_Batch_DAO_EntityBatch( );
            $batchContribution->entity_table = 'civicrm_contribution';
      $batchContribution->entity_id    = $contributionID;
            
      // check if the selected contribution id already in a batch
      // if not, increment $numContributionsAdded else keep the count of contributions that are already added
      if ( ! $batchContribution->find( true ) ) {
                // get contact_id, & contribution receive date from Contribution using contribution id
                $params = array( 'id' => $contributionID);
                CRM_Contribute_BAO_Contribution::retrieve( $params, $defaults, $ids );
                
                // check if contribution is not valid for gift aid, increment $numContributionsNotValid
                if ( GiftAid_Utils_GiftAid::isEligibleForGiftAid( $defaults['contact_id'], $defaults['receive_date'], $contributionID ) AND $defaults['contribution_status_id'] == 1 ) {
                    $contributionsAdded[] = $contributionID;
                } else {
                    $contributionsNotValid[] = $contributionID;
                }
      } else {
                $contributionsAlreadyAdded[] = $contributionID;
            }
    }
              
        return array( count($contributionIDs), 
                      $contributionsAdded, 
                      $contributionsAlreadyAdded, 
                      $contributionsNotValid );
    }

  /*
     * this function returns the array of batchID & title
     */
  static function getBatchIdTitle( $orderBy = 'id' ){
        $query = "SELECT * FROM civicrm_batch ORDER BY " . $orderBy;
        $dao   =& CRM_Core_DAO::executeQuery( $query);
       
        $result = array();
        while ( $dao->fetch( ) ) {
            $result[$dao->id] = $dao->id." - ".$dao->title;
        }
        return $result;
  }

    /*
     * this function returns the array of contribution
     * @param array  $contributionIDs an array of contribution ids
     * @return array $result an array of contributions
     */
    static function getContributionDetails( $contributionIds ) { 

        if ( empty( $contributionIds ) ) {
            return;
        } 

        $query = " SELECT contribution.id, contact.id contact_id, contact.display_name, contribution.total_amount, financial_type.name,
                          contribution.source, contribution.receive_date, batch.title, batch.id as batch_id FROM civicrm_contribution contribution
                   LEFT JOIN civicrm_contact contact ON ( contribution.contact_id = contact.id )
                   LEFT JOIN civicrm_financial_type financial_type ON ( financial_type.id = contribution.financial_type_id  )
                   LEFT JOIN civicrm_entity_batch entity_batch ON ( entity_batch.entity_id = contribution.id ) 
                   LEFT JOIN civicrm_batch batch ON ( batch.id = entity_batch.batch_id ) 
                   WHERE contribution.id IN (" . implode(',', $contributionIds ) . ")" ; 

        $dao    = CRM_Core_DAO::executeQuery( $query );
        $result = array( );
        while ( $dao->fetch( ) ) {
            $result[$dao->id]['contact_id']        = $dao->contact_id;
            $result[$dao->id]['contribution_id']   = $dao->id;
            $result[$dao->id]['display_name']      = $dao->display_name;
            $result[$dao->id]['total_amount']      = $dao->total_amount;
            $result[$dao->id]['financial_account'] = $dao->name;
            $result[$dao->id]['source']            = $dao->source;
            $result[$dao->id]['receive_date']      = $dao->receive_date;
            $result[$dao->id]['batch']             = $dao->title;
            $result[$dao->id]['batch_id']          = $dao->batch_id;
        } 

        return $result;
    }

    /*
     * this function is to check if the batch is already submited to HMRC using GiftAidOnline Module
     * @param integer  $batchId a batchId
     * @return true if already submited and if not
     */
    static function isBatchAlreadySubmited( $pBatchId )   {

      $bIsSubmitted = false;
      $cQuery = " SELECT submission.batch_id                    AS batch_id " .
                " ,      submission.response_status             AS status   " .
                " FROM   civicrm_gift_aid_submission submission             " .
                " WHERE  submission.batch_id = %1                           " .
                " AND    submission.response_status IS NOT NULL             ";
      $queryParam = array( 1 => array( $pBatchId, 'Integer' ) );
      $oDao     = CRM_Core_DAO::executeQuery( $cQuery, $queryParam );
      while ( $oDao->fetch() ) {
          return true;
      }
     return $bIsSubmitted;
  }
}