<?php
/*
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC. All rights reserved.                        |
 |                                                                    |
 | This work is published under the GNU AGPLv3 license with some      |
 | permitted exceptions and without any warranty. For full license    |
 | and copyright information, see https://civicrm.org/licensing       |
 +--------------------------------------------------------------------+
 */

/**
 * Class CRM_Civigiftaid_Utils_Contribution
 */
class CRM_Civigiftaid_Utils_Contribution {

  /**
   * Given an array of contributionIDs, add them to a batch
   *
   * @param array $contributionIDs
   * @param int $batchID
   *
   * @return array
   *           (total, added, notAdded) ids of contributions added to the batch
   * @throws \CRM_Extension_Exception
   * @throws \CiviCRM_API3_Exception
   */
  public static function addContributionToBatch($contributionIDs, $batchID) {
    $contributionsAdded = [];
    $contributionsNotAdded = [];

    // Get the batch name
    $batchName = civicrm_api3('Batch', 'getvalue', [
      'return' => "title",
      'id' => $batchID,
    ]);

    $batchNameGroup = civicrm_api3('OptionGroup', 'getsingle', ['name' => 'giftaid_batch_name']);
    if ($batchNameGroup['id']) {
      $groupId = $batchNameGroup['id'];
      $params = [
        'option_group_id' => $groupId,
        'value'           => $batchName,
        'label'           => $batchName
      ];
      civicrm_api3('OptionValue', 'create', $params);
    }

    // Get all contributions from found IDs that are not already in a batch
    $groupID = civicrm_api3('CustomGroup', 'getvalue', [
      'return' => "id",
      'name' => "gift_aid",
    ]);
    $contributionParams = [
      'id' => ['IN' => $contributionIDs],
      'return' => ['id', 'contact_id', 'contribution_status_id', 'receive_date', CRM_Civigiftaid_Utils::getCustomByName('batch_name', $groupID)],
      'options' => ['limit' => 0],
    ];
    $contributions = civicrm_api3('Contribution', 'get', $contributionParams);
    foreach (CRM_Utils_Array::value('values', $contributions) as $contribution) {
      // check if the selected contribution id already in a batch
      if (!empty($contribution[CRM_Civigiftaid_Utils::getCustomByName('batch_name', $groupID)])) {
        $contributionsNotAdded[] = $contribution['id'];
        continue;
      }

      // check if contribution is valid for gift aid
      if (self::isEligibleForGiftAid($contribution)
        && ($contribution['contribution_status_id'] == CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'contribution_status_id', 'Completed'))
      ) {
        civicrm_api3('EntityBatch', 'create', [
          'entity_id' => $contribution['id'],
          'batch_id' => $batchID,
          'entity_table' => 'civicrm_contribution',
        ]);

        self::updateGiftAidFields($contribution['id'], NULL, $batchName, $addToBatch = TRUE);

        $contributionsAdded[] = $contribution['id'];
      }
      else {
        $contributionsNotAdded[] = $contribution['id'];
      }
    }

    if (!empty($contributionsAdded)) {
      // if there is any extra work required to be done for contributions that are batched,
      // should be done via hook
      CRM_Civigiftaid_Utils_Hook::batchContributions(
        $batchID,
        $contributionsAdded
      );
    }

    return [
      count($contributionIDs),
      count($contributionsAdded),
      count($contributionsNotAdded)
    ];
  }

  /**
   * @param int $contributionID
   * @param int $eligibleForGiftAid - if this is NULL if will NOT be set, otherwise set it to eg CRM_Civigiftaid_Utils_GiftAid::DECLARATION_IS_YES
   * @param string $batchName - if this is set to NULL it will NOT be changed
   * @param bool $addToBatch - You must set this to TRUE to modify the batchName
   *
   * @throws \CRM_Extension_Exception
   * @throws \CiviCRM_API3_Exception
   */
  public static function updateGiftAidFields($contributionID, $eligibleForGiftAid = NULL, $batchName = '', $addToBatch = FALSE) {
    if (!empty($batchName) && !$addToBatch) {
      // Don't touch this contribution - it's already part of a batch
      return;
    }

    if ($batchName !== NULL) {
      $contributionParams[CRM_Civigiftaid_Utils::getCustomByName('batch_name', 'Gift_Aid')] = $batchName;
    }
    if (isset($eligibleForGiftAid)) {
      $eligibleForGiftAid = (int) $eligibleForGiftAid;
      $contributionParams[CRM_Civigiftaid_Utils::getCustomByName('Eligible_for_Gift_Aid', 'Gift_Aid')] = (int) $eligibleForGiftAid;
      if ($eligibleForGiftAid === 0) {
        $contributionParams[CRM_Civigiftaid_Utils::getCustomByName('gift_aid_amount', 'Gift_Aid')] = NULL;
        $contributionParams[CRM_Civigiftaid_Utils::getCustomByName('amount', 'Gift_Aid')] = NULL;
      }
      else {
        // Eligible - calculate gift aid amounts
        $totalAmount = (float) civicrm_api3('Contribution', 'getvalue', [
          'return' => "total_amount",
          'id' => $contributionID,
        ]);
        $giftAidableContribAmt = self::getGiftAidableContribAmt($totalAmount, $contributionID);
        $giftAidAmount = self::calculateGiftAidAmt($giftAidableContribAmt, self::getBasicRateTax());
        $contributionParams[CRM_Civigiftaid_Utils::getCustomByName('gift_aid_amount', 'Gift_Aid')] = $giftAidAmount;
        $contributionParams[CRM_Civigiftaid_Utils::getCustomByName('amount', 'Gift_Aid')] = $giftAidableContribAmt;
      }
    }
    $contributionParams['entity_id'] = $contributionID;
    // We use CustomValue.create instead of Contribution.create because Contribution.create is way too slow
    civicrm_api3('CustomValue', 'create', $contributionParams);
  }

  /**
   * @param array $contributionIDs
   *
   * @return array
   * @throws \CiviCRM_API3_Exception
   */
  public static function removeContributionFromBatch($contributionIDs) {
    $contributionRemoved = [];
    $contributionNotRemoved = [];

    list($total, $contributionsToRemove, $notInBatch, $alreadySubmitted) =
      self::validationRemoveContributionFromBatch($contributionIDs);

    $contributions = self::getContributionDetails($contributionsToRemove);

    if (!empty($contributions)) {
      foreach ($contributions as $contribution) {
        if (!empty($contribution['batch_id'])) {

          $batchContribution = new CRM_Batch_DAO_EntityBatch();
          $batchContribution->entity_table = 'civicrm_contribution';
          $batchContribution->entity_id = $contribution['contribution_id'];
          $batchContribution->batch_id = $contribution['batch_id'];
          $batchContribution->delete();

          $groupID = civicrm_api3('CustomGroup', 'getvalue', [
            'return' => "id",
            'name' => "gift_aid",
          ]);
          $contributionParams = [
            'id' => $contribution['contribution_id'],
            CRM_Civigiftaid_Utils::getCustomByName('batch_name', $groupID) => 'null',
          ];
          civicrm_api3('Contribution', 'create', $contributionParams);

          array_push($contributionRemoved, $contribution['contribution_id']);

        }
        else {
          array_push($contributedNotRemoved, $contribution['contribution_id']);
        }
      }
    }

    return [
      count($contributionIDs),
      count($contributionRemoved),
      count($contributionNotRemoved)
    ];
  }

  /**
   * Get the total amount for line items, for a contribution given by its ID,
   * having financial type which have been enabled in Gift Aid extension's
   * settings.
   *
   * @param int $contributionId
   *
   * @return float|int
   */
  public static function getContribAmtForEnabledFinanceTypes($contributionId) {
    $sql = "
      SELECT SUM(line_total) total
      FROM civicrm_line_item
      WHERE contribution_id = {$contributionId}";

    if (!(bool) CRM_Civigiftaid_Settings::getValue('globally_enabled')) {
      $enabledTypes = (array) CRM_Civigiftaid_Settings::getValue('financial_types_enabled');
      if (empty($enabledTypes)) {
        // if no financial types are selected
        return 0;
      }
      $enabledTypesStr = implode(', ', $enabledTypes);
      $sql .= " AND financial_type_id IN ({$enabledTypesStr})";
    }

    $dao = CRM_Core_DAO::executeQuery($sql);
    if ($dao->fetch()) {
      return (float) $dao->total;
    }

    return 0;
  }

  /**
   * This function calculate the gift aid amount.
   * Formula used is: (contributed amount * basic rate of year) / (100 - basic rate of year)
   * E.g. For a donation of £100 and basic rate of tax of 20%, gift aid amount = £100 * 20 / 80. In other words, £25
   * for every £100, or 25p for every £1.
   *
   * @param $contribAmt
   * @param $basicTaxRate
   *
   * @return float
   */
  public static function calculateGiftAidAmt($contribAmt, $basicTaxRate) {
    return (($contribAmt * $basicTaxRate) / (100 - $basicTaxRate));
  }

  /**
   * Get the basic tax rate currently defined in the settings.
   *
   * @return float
   * @throws \CRM_Extension_Exception
   * @throws \CiviCRM_API3_Exception
   */
  public static function getBasicRateTax() {
    if (!isset(Civi::$statics[__CLASS__]['basictaxrate'])) {
      $rate = NULL;

      $gResult = civicrm_api3('OptionGroup', 'getsingle', ['name' => 'giftaid_basic_rate_tax']);

      if ($gResult['id']) {
        $params = [
          'sequential' => 1,
          'option_group_id' => $gResult['id'],
          'name' => 'basic_rate_tax',
        ];
        $result = civicrm_api3('OptionValue', 'get', $params);

        if ($result['values']) {
          foreach ($result['values'] as $ov) {
            if ($result['id'] == $ov['id'] && $ov['value'] !== '') {
              $rate = $ov['value'];
            }
          }
        }
      }

      if (is_null($rate)) {
        throw new CRM_Extension_Exception(
          'Basic Tax Rate not currently set! Please set it in the Gift Aid extension settings.'
        );
      }

      Civi::$statics[__CLASS__]['basictaxrate'] = (float) $rate;
    }
    return Civi::$statics[__CLASS__]['basictaxrate'];
  }

  /**
   * @return bool
   */
  public static function isOnlineSubmissionExtensionInstalled() {
    try {
      civicrm_api3('Extension', 'getsingle', [
        'is_active' => 1,
        'full_name' => 'uk.co.vedaconsulting.module.giftaidonline',
      ]);
    }
    catch (Exception $e) {
      return FALSE;
    }
    return TRUE;
  }

  /**
   * @param array $contributionIDs
   *
   * @return array
   */
  public static function validationRemoveContributionFromBatch($contributionIDs) {
    $contributionsAlreadySubmited = [];
    $contributionsNotInBatch = [];
    $contributionsToRemove = [];

    foreach ($contributionIDs as $contributionID) {
      $batchContribution = new CRM_Batch_DAO_EntityBatch();
      $batchContribution->entity_table = 'civicrm_contribution';
      $batchContribution->entity_id = $contributionID;

      // check if the selected contribution id is in a batch
      if ($batchContribution->find(TRUE)) {
        if (self::isOnlineSubmissionExtensionInstalled()) {

          if (self::isBatchAlreadySubmitted($batchContribution->batch_id)) {
            $contributionsAlreadySubmited[] = $contributionID;
          }
          else {
            $contributionsToRemove[] = $contributionID;
          }
        }
        else {
          $contributionsToRemove[] = $contributionID;
        }
      }
      else {
        $contributionsNotInBatch[] = $contributionID;
      }
    }

    return [
      count($contributionIDs),
      $contributionsToRemove,
      $contributionsNotInBatch,
      $contributionsAlreadySubmited
    ];
  }

  /**
   * This function check contribution is valid for giftaid or not:
   * 1 - if contribution_id already inserted in batch_contribution
   * 2 - if contributions are not valid for gift aid
   *
   * @param array $contributionIDs
   *
   * @return array
   * @throws \CiviCRM_API3_Exception
   */
  public static function validateContributionToBatch($contributionIDs) {
    $contributionsAdded = [];
    $contributionsAlreadyAdded = [];
    $contributionsNotValid = [];

    // Get all contributions from found IDs that are not already in a batch
    $groupID = civicrm_api3('CustomGroup', 'getvalue', [
      'return' => "id",
      'name' => "gift_aid",
    ]);
    $contributionParams = [
      'id' => ['IN' => $contributionIDs],
      'return' => [
        'id',
        'contact_id',
        'contribution_status_id',
        'receive_date',
        CRM_Civigiftaid_Utils::getCustomByName('batch_name', 'Gift_Aid'),
        CRM_Civigiftaid_Utils::getCustomByName('Eligible_for_Gift_Aid', 'Gift_Aid'),
        CRM_Civigiftaid_Utils::getCustomByName('Gift_Aid_Amount', 'Gift_Aid'),
        CRM_Civigiftaid_Utils::getCustomByName('Amount', 'Gift_Aid'),
      ],
      'options' => ['limit' => 0],
    ];
    $contributions = civicrm_api3('Contribution', 'get', $contributionParams);

    foreach (CRM_Utils_Array::value('values', $contributions) as $contribution) {
      if (!empty($contribution[CRM_Civigiftaid_Utils::getCustomByName('batch_name', $groupID)])) {
        $contributionsAlreadyAdded[] = $contribution['id'];
      }
      elseif (self::isEligibleForGiftAid($contribution)
        && ($contribution['contribution_status_id'] == CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'contribution_status_id', 'Completed'))
      ) {
        $contributionsAdded[] = $contribution['id'];
        self::updateGiftAidFields($contribution['id']);
      }
      else {
        $contributionsNotValid[] = $contribution['id'];
      }
    }

    return [
      count($contributionIDs),
      $contributionsAdded,
      $contributionsAlreadyAdded,
      $contributionsNotValid
    ];
  }

  /**
   * Returns the array of batchID & title
   *
   * @param string $orderBy
   *
   * @return array
   */
  public static function getBatchIdTitle($orderBy = 'id') {
    $query = "SELECT * FROM civicrm_batch ORDER BY " . $orderBy;
    $dao = CRM_Core_DAO::executeQuery($query);

    $result = [];
    while ($dao->fetch()) {
      $result[$dao->id] = $dao->id . " - " . $dao->title;
    }
    return $result;
  }

  /*
   * Returns the array of contributions
   *
   * @param array $contributionIds
   *
   * @return array
   */
  public static function getContributionDetails($contributionIds) {
    $contributionDetails = [];

    if (empty($contributionIds)) {
      return $contributionDetails;
    }

    $contributionIdStr = implode(',', $contributionIds);
    $contributionDetails = self::addContributionDetails($contributionIdStr, $contributionDetails);

    return $contributionDetails;
  }

  /**
   * this function is to check if the batch is already submitted to HMRC using GiftAidOnline Module
   *
   * @param int $pBatchId a batchId
   *
   * @return true if already submitted and if not
   */
  public static function isBatchAlreadySubmitted($pBatchId) {
    if (!self::isOnlineSubmissionExtensionInstalled()) {
      return FALSE;
    }

    $onlineSubmission = new CRM_Giftaidonline_Page_OnlineSubmission();
    $bIsSubmitted = $onlineSubmission->is_submitted($pBatchId);
    return $bIsSubmitted;
  }

  /**
   * @param string $entityTable Entity table name
   *
   * @return string
   */
  public static function getLineItemName($entityTable) {
    switch ($entityTable) {
      case 'civicrm_participant':
        return 'Event';

      case 'civicrm_membership':
        return 'Membership';

      case 'civicrm_contribution':
        return 'Donation';

      case 'civicrm_participation':
        return 'Participation';

      default:
        return $entityTable;
    }
  }

  /**
   * @param string $contributionIdStr
   * @param array $contributionDetails
   *
   * @return array
   * @throws \CiviCRM_API3_Exception
   */
  private static function addContributionDetails($contributionIdStr, $contributionDetails) {
    // Get all contributions from found IDs that are not already in a batch
    $group = civicrm_api3('CustomGroup', 'getsingle', [
      'return' => ['id', 'table_name'],
      'name' => "gift_aid",
    ]);

    $query = "
      SELECT  contribution.id, contact.id contact_id, contact.display_name, contribution.total_amount, contribution.currency, giftaidsubmission.gift_aid_amount,
              financial_type.name, contribution.source, contribution.receive_date, batch.title, batch.id as batch_id
      FROM civicrm_contribution contribution
      LEFT JOIN civicrm_contact contact ON ( contribution.contact_id = contact.id )
      LEFT JOIN civicrm_financial_type financial_type ON ( financial_type.id = contribution.financial_type_id  )
      LEFT JOIN civicrm_entity_batch entity_batch ON ( entity_batch.entity_id = contribution.id )
      LEFT JOIN civicrm_batch batch ON ( batch.id = entity_batch.batch_id )
      LEFT JOIN {$group['table_name']} giftaidsubmission ON ( contribution.id = giftaidsubmission.entity_id )
      WHERE contribution.id IN (%1)";

    $queryParams[1] = [$contributionIdStr, 'CommaSeparatedIntegers'];
    $dao = CRM_Core_DAO::executeQuery($query, $queryParams);

    while ($dao->fetch()) {
      $contributionDetails[$dao->id]['contact_id'] = $dao->contact_id;
      $contributionDetails[$dao->id]['contribution_id'] = $dao->id;
      $contributionDetails[$dao->id]['display_name'] = $dao->display_name;
      $contributionDetails[$dao->id]['gift_aidable_amount'] = $dao->gift_aid_amount;
      $contributionDetails[$dao->id]['total_amount'] = $dao->total_amount;
      $contributionDetails[$dao->id]['currency'] = $dao->currency;
      $contributionDetails[$dao->id]['financial_account'] = $dao->name;
      $contributionDetails[$dao->id]['source'] = $dao->source;
      $contributionDetails[$dao->id]['receive_date'] = $dao->receive_date;
      $contributionDetails[$dao->id]['batch'] = $dao->title;
      $contributionDetails[$dao->id]['batch_id'] = $dao->batch_id;
      $contributionDetails[$dao->id]['line_items_count'] = 0;
    }

    if (count($contributionDetails)) {
      $contributionDetails = self::countLineItems($contributionIdStr, $contributionDetails);
    }
    return $contributionDetails;
  }

  /**
   * This gets a count of all lineitems for a contribution for display on the "Add to Batch" list.
   *
   * @param string $contributionIdStr
   * @param array $contributionDetails
   *
   * @return array
   */
  private static function countLineItems($contributionIdStr, $contributionDetails) {
    $query = "SELECT i.contribution_id as contribution_id, COUNT(i.contribution_id) as line_item_count
      FROM civicrm_line_item i
      WHERE i.contribution_id IN (%1)";
    $queryParams[1] = [$contributionIdStr, 'CommaSeparatedIntegers'];

    if (!(bool) CRM_Civigiftaid_Settings::getValue('globally_enabled')) {
      $enabledTypes = (array) CRM_Civigiftaid_Settings::getValue('financial_types_enabled');
      if (empty($enabledTypes)) {
        // if no financial types are selected
        return $contributionDetails;
      }
      $query .= " AND financial_type_id IN (%2)";
      $queryParams[2] = [implode(', ', $enabledTypes), 'CommaSeparatedIntegers'];
    }

    $query .= " GROUP BY i.contribution_id";
    $dao = CRM_Core_DAO::executeQuery($query, $queryParams);
    while ($dao->fetch()) {
      $contributionDetails[$dao->contribution_id]['line_items_count'] = $dao->line_item_count;
    }
    return $contributionDetails;
  }

  /**
   * @param float|int $contributionAmt
   * @param int $contributionID
   *
   * @return float|int
   */
  private static function getGiftAidableContribAmt($contributionAmt, $contributionID) {
    if ((bool) CRM_Civigiftaid_Settings::getValue('globally_enabled')) {
      return $contributionAmt;
    }
    return self::getContribAmtForEnabledFinanceTypes($contributionID);
  }

  /**
   * @param array $contribution
   *
   * @return bool
   * @throws \CiviCRM_API3_Exception
   */
  public static function isEligibleForGiftAid($contribution) {
    $isContributionEligible = self::isContributionEligible($contribution);

    // hook can alter the eligibility if needed
    CRM_Civigiftaid_Utils_Hook::giftAidEligible($isContributionEligible, $contribution['contact_id'], $contribution['receive_date'], $contribution['id']);
    return $isContributionEligible;
  }

  /**
   * @param array $declarations
   * @param int $limit
   *
   * @return array
   * @throws \CiviCRM_API3_Exception
   */
  public static function getContributionsByDeclarations($declarations = [], $limit = 100) {
    $contributionsToSubmit = [];

    foreach ($declarations as $declaration) {
      $dateRange = [];

      $contactId = $declaration['entity_id'];
      $startDate = $declaration['start_date'];
      $dateRange[0] = self::dateFourYearsAgo($startDate);
      $dateRange[1] = $startDate;
      $contributions = self::getContributionsByDateRange($contactId, $dateRange);
      $contributionsToSubmit = array_merge($contributions, $contributionsToSubmit);

      if (count($contributionsToSubmit) >= $limit) {
        $contributionsToSubmit = array_slice($contributionsToSubmit, 0, $limit);
        break;
      }
    }
    return $contributionsToSubmit;
  }

  /**
   * @param int $contactId
   * @param string $dateRange
   *
   * @return mixed
   * @throws \CiviCRM_API3_Exception
   */
  public static function getContributionsByDateRange($contactId, $dateRange) {
    if ((bool) CRM_Civigiftaid_Settings::getValue('globally_enabled')) {
      $result = civicrm_api3('Contribution', 'get', [
        'sequential' => 1,
        'return' => "financial_type_id,id",
        'contact_id' => $contactId,
        'id' => ['NOT IN' => self::submittedContributions()],
        'receive_date' => ['BETWEEN' => $dateRange],
      ]);
    }
    else {
      if ($financialTypes = (array) CRM_Civigiftaid_Settings::getValue('financial_types_enabled')) {
        $result = civicrm_api3('Contribution', 'get', [
          'sequential' => 1,
          'return' => "financial_type_id,id",
          'contact_id' => $contactId,
          'financial_type_id' => $financialTypes,
          'id' => ['NOT IN' => self::submittedContributions()],
          'receive_date' => ['BETWEEN' => $dateRange],
        ]);
      }
    }
    return $result['values'];
  }

  /**
   * @return array
   */
  public static function submittedContributions() {
    $submittedContributions = [];
    $sql = "
        SELECT entity_id
        FROM   civicrm_value_gift_aid_submission";

    $dao = CRM_Core_DAO::executeQuery($sql);
    foreach ($dao->fetchAll() as $row) {
      $submittedContributions[] = $row['entity_id'];
    }

    return $submittedContributions;
  }

  /**
   * Check if Eligibility criteria for Contribution is met.
   *
   * @param array $contribution
   *
   * @return bool
   * @throws \CiviCRM_API3_Exception
   */
  public static function isContributionEligible($contribution) {
    $declarations = CRM_Civigiftaid_Declaration::getAllDeclarations($contribution['contact_id']);
    if (empty($declarations)) {
      return FALSE;
    }

    $eligibleAmount = $contribution[CRM_Civigiftaid_Utils::getCustomByName('Amount', 'Gift_Aid')];
    if (!empty($eligibleAmount) && ($eligibleAmount == 0)) {
      // Contribution has 0 eligible amount.
      return FALSE;
    }
    $isEligible = $contribution[CRM_Civigiftaid_Utils::getCustomByName('Eligible_for_Gift_Aid', 'Gift_Aid')];
    if (!empty($isEligible) && ($isEligible == 0)) {
      // Contribution marked as not eligible
      return FALSE;
    };
    // If it is not set ('') it's not the same as DECLARATION_IS_NO
    if (!empty($contributionEligible) && ($contributionEligible == CRM_Civigiftaid_Declaration::DECLARATION_IS_NO)) {
      return FALSE;
    }

    foreach ($declarations as $declaration) {
      if ($declaration['eligible_for_gift_aid'] == CRM_Civigiftaid_Declaration::DECLARATION_IS_PAST_4_YEARS) {
        $declaration['start_date'] = self::dateFourYearsAgo($declaration['start_date']);
      }

      // Convert dates to timestamps.
      $startDateTS = strtotime(date('Ymd 00:00:00', strtotime($declaration['start_date'])));
      $endDateTS = !empty($declaration['end_date']) ? strtotime(date('Ymd 00:00:00', strtotime($declaration['end_date']))) : NULL;
      $contributionDateTS = strtotime($contribution['receive_date']);

      /**
       * Check between which date the contribution's receive date falls.
       */
      if (!empty($endDateTS)) {
        $contributionDeclarationDateMatchFound =
          ($contributionDateTS >= $startDateTS) && ($contributionDateTS < $endDateTS);
      }
      else {
        $contributionDeclarationDateMatchFound = ($contributionDateTS >= $startDateTS);
      }

      if ($contributionDeclarationDateMatchFound == TRUE) {
        return ((bool) $declaration['eligible_for_gift_aid']);
      }
    }
    return FALSE;
  }

  /**
   * @param array $params
   */
  public static function setSubmission($params) {
    $sql = "SELECT * FROM civicrm_value_gift_aid_submission where entity_id = %1";
    $sqlParams = [1 => [$params['entity_id'], 'Integer']];
    $dao = CRM_Core_DAO::executeQuery($sql, $sqlParams);
    $count = count($dao->fetchAll());
    if ($count == 0) {
      // Insert
      $sql = "
        INSERT INTO civicrm_value_gift_aid_submission (entity_id, eligible_for_gift_aid, amount, gift_aid_amount, batch_name)
        VALUES (%1, %2, NULL, NULL, NULL)";
    }
    else {
      // Update
      $sql = "
        UPDATE civicrm_value_gift_aid_submission
        SET eligible_for_gift_aid = %2
        WHERE entity_id = %1";
    }
    $queryParams = [
      1 => [$params['entity_id'], 'Integer'],
      2 => [$params['eligible_for_gift_aid'], 'Integer'],
    ];
    CRM_Core_DAO::executeQuery($sql, $queryParams);
  }

  /**
   * @param string $startDate
   *
   * @return string
   * @throws \Exception
   */
  public static function dateFourYearsAgo($startDate) {
    $date = new DateTime($startDate);
    $dateFourYearsAgo = $date->modify('-4 year')->format('Y-m-d H:i:s');
    return $dateFourYearsAgo;
  }

}
