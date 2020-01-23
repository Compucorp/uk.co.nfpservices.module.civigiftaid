<?php

/*
 +--------------------------------------------------------------------+
 | CiviCRM version 4.3                                                |
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
 * @package   CRM
 * @copyright CiviCRM LLC (c) 2004-2011
 * $Id$
 *
 */
require_once 'CRM/Contribute/Form/Task.php';

/**
 * This class provides the functionality to add a group of contribution to a batch.
 */
require_once 'CRM/Utils/String.php';

class CRM_Civigiftaid_Form_Task_AddToBatch extends CRM_Contribute_Form_Task {

  const VALIDATION_QUEUE_BATCH_LIMIT = 10;

  protected $_id = NULL;

  /**
   * build all the data structures needed to build the form
   *
   * @return void
   * @access public
   */
  function preProcess() {
    parent::preProcess();

    $isStatsDone = CRM_Utils_Request::retrieve('processed', 'Boolean', $this, FALSE, 0);
    if (empty($isStatsDone)) {
      $runner = $this->getRunner($this->_contributionIds);
      if ($runner) {
        $runner->runAllViaWeb();
      }
    }
    list( $total, $added, $alreadyAdded, $notValid ) = $this->getValidationStats();
    if (in_array($this->controller->getButtonName(), array('_qf_AddToBatch_back', '_qf_AddToBatch_next'))) {
      // reset the flag, so it's revalidated next time.
      $this->set('processed', 0);
    }

    $this->assign('selectedContributions', $total);
    $this->assign('totalAddedContributions', count($added));
    $this->assign('alreadyAddedContributions', count($alreadyAdded));
    $this->assign('notValidContributions', count($notValid));

    $qfKey = CRM_Utils_Request::retrieve('qfKey', 'String', $this);

    // URL to view contributions that will be added to this batch
    $tobeAddedUrlParams = 'status=tobeadded&qfKey='.$qfKey;
    $contributionsTobeAddedUrl = CRM_Utils_System::url('civicrm/addtobatch/summary', $tobeAddedUrlParams);
    $this->assign('contributionsTobeAddedUrl', $contributionsTobeAddedUrl );

    // URL to view contributions that are already added to this batch
    $alreadyAddedUrlParams = 'status=alreadyadded&qfKey='.$qfKey;
    $contributionsAlreadyAddedUrl = CRM_Utils_System::url('civicrm/addtobatch/summary', $alreadyAddedUrlParams);
    $this->assign('contributionsAlreadyAddedUrl', $contributionsAlreadyAddedUrl );

    // URL to view contributions that are not valid for giftaid
    $invalidUrlParams = 'status=invalid&qfKey='.$qfKey;
    $contributionsInvalidUrl = CRM_Utils_System::url('civicrm/addtobatch/summary', $invalidUrlParams);
    $this->assign('contributionsInvalidUrl', $contributionsInvalidUrl );
  }

  /**
   * Build the form
   *
   * @access public
   * @return void
   */
  function buildQuickForm() {
    $attributes = CRM_Core_DAO::getAttribute('CRM_Batch_DAO_Batch');

    $this->add('text', 'title', ts('Batch Title'), $attributes['title'], TRUE);

    $this->addRule(
      'title',
      ts('Label already exists in Database.'),
      'objectExists',
      array('CRM_Batch_DAO_Batch', $this->_id, 'title')
    );

    $this->add(
      'textarea',
      'description',
      ts('Description:') . ' ',
      $attributes['description']
    );

    require_once 'CRM/Batch/BAO/Batch.php';
    $batchName = CRM_Batch_BAO_Batch::generateBatchName();
    $defaults = array('title' => ts('GiftAid ' . $batchName));

    $this->setDefaults($defaults);

    $this->addDefaultButtons(ts('Add to batch'));
  }

  /**
   * process the form after the input has been submitted and validated
   *
   * @access public
   * @return None
   */
  public function postProcess() {
    $params = $this->controller->exportValues();
    $batchParams = array();
    $batchParams['title'] = $params['title'];
    $batchParams['name'] = CRM_Utils_String::titleToVar($params['title'], 63);
    $batchParams['description'] = $params['description'];
    $batchParams['batch_type'] = "Gift Aid";

    $session = CRM_Core_Session::singleton();
    $batchParams['created_id'] = $session->get('userID');
    $batchParams['created_date'] = date("YmdHis");
    $batchParams['status_id'] = 0;

    $batchMode = CRM_Core_PseudoConstant::get(
      'CRM_Batch_DAO_Batch',
      'mode_id',
      array('labelColumn' => 'name')
    );
    $batchParams['mode_id'] = CRM_Utils_Array::key('Manual Batch', $batchMode);

    $batchParams['modified_date'] = date('YmdHis');
    $batchParams['modified_id'] = $session->get('userID');

    require_once 'CRM/Core/Transaction.php';
    $transaction = new CRM_Core_Transaction();

    //require_once 'CRM/Core/BAO/Batch.php'; //version 4.2
    require_once 'CRM/Batch/BAO/Batch.php';
    $createdBatch = CRM_Batch_BAO_Batch::create($batchParams);

    $batchID = $createdBatch->id;
    $batchLabel = $batchParams['title'];

    // Save current settings for the batch
    CRM_Civigiftaid_BAO_BatchSettings::create(array('batch_id' => $batchID));

    require_once 'CRM/Civigiftaid/Utils/Contribution.php';
    list($total, $added, $notAdded) =
      CRM_Civigiftaid_Utils_Contribution::addContributionToBatch(
        $this->_contributionIds,
        $batchID
      );

    if ($added <= 0) {
      // rollback since there were no contributions added, and we might not want to keep an empty batch
      $transaction->rollback();
      $status = ts(
        'Could not create batch "%1", as there were no valid contribution(s) to be added.',
        array(1 => $batchLabel)
      );
    }
    else {
      $status = array(
        ts('Added Contribution(s) to %1', array(1 => $batchLabel)),
        ts('Total Selected Contribution(s): %1', array(1 => $total))
      );
      if ($added) {
        $status[] = ts(
          'Total Contribution(s) added to batch: %1',
          array(1 => $added)
        );
      }
      if ($notAdded) {
        $status[] = ts(
          'Total Contribution(s) already in batch or not valid: %1',
          array(1 => $notAdded)
        );
      }
      $status = implode('<br/>', $status);
    }
    $transaction->commit();
    CRM_Core_Session::setStatus($status);
  }//end of function

  /**
   * Build a queue of tasks by dividing contributions in sets.
   */
  function getRunner($contributionIds) {
    $queue = CRM_Queue_Service::singleton()->create(array(
      'name'  => 'ADD_TO_GIFTAID',
      'type'  => 'Sql',
      'reset' => TRUE,
    ));
    $qfKey = CRM_Utils_Request::retrieve('qfKey', 'String', $this);
    $total = count($contributionIds);
    $batchLimit = self::VALIDATION_QUEUE_BATCH_LIMIT;
    for ($i = 0; $i < ceil($total/$batchLimit); $i++) {
      $start = $i * $batchLimit;
      $contribIds = array_slice($contributionIds, $start, $batchLimit, TRUE);
      $task  = new CRM_Queue_Task(
        array ('CRM_Civigiftaid_Form_Task_AddToBatch', 'validateContributionToBatchLimit'),
        array($contribIds, $qfKey),
        "Validated " . $i*$batchLimit . " contributions out of " . $total
      );
      $queue->createItem($task);
    }
    // Setup the Runner
    $url = CRM_Utils_System::url(CRM_Utils_System::currentPath(), "_qf_AddToBatch_display=1&qfKey={$this->controller->_key}&processed=1", FALSE, NULL, FALSE);
    $runner = new CRM_Queue_Runner(array(
      'title' => ts('Validating Contributions..'),
      'queue' => $queue,
      'errorMode'=> CRM_Queue_Runner::ERROR_ABORT,
      'onEndUrl' => $url
    ));
    // reset stats
    self::resetValidationStats($qfKey);

    return $runner;
  }

  /**
   * Get validation stats from cache.
   *
   * @return array
   */
  function getValidationStats() {
    $qfKey = CRM_Utils_Request::retrieve('qfKey', 'String', $this);
    $cache = self::getCache();
    $stats = $cache->get(self::getCacheKey($qfKey));
    return array(
      empty($stats['total']) ? 0 : $stats['total'],
      $stats['added'],
      $stats['alreadyAdded'],
      $stats['notValid']
    );
  }

  /**
   * Reset validation stats for giftaid
   *
   * @return array
   */
  static function resetValidationStats($qfKey) {
    $cache = self::getCache();
    $key   = self::getCacheKey($qfKey);
    $cache->set($key, CRM_Core_DAO::$_nullArray);
  }

  /**
   * Carry out batch validations.
   *
   * @param \CRM_Queue_TaskContext $ctx
   * @param array $contributionIds
   *
   * @return int
   */
  static function validateContributionToBatchLimit(CRM_Queue_TaskContext $ctx, $contributionIds, $qfKey)  {
    list( $total, $added, $alreadyAdded, $notValid ) =
      CRM_Civigiftaid_Utils_Contribution::validateContributionToBatch($contributionIds);
    $cache = self::getCache();
    $key   = self::getCacheKey($qfKey);
    $stats = $cache->get($key);
    if (empty($stats)) {
      $stats = array(
        'total'        => $total,
        'added'        => $added,
        'alreadyAdded' => $alreadyAdded,
        'notValid'     => $notValid,
      );
      $cache->set($key, $stats);
    } else {
      $stats = array(
        'total'        => $stats['total'] + $total,
        'added'        => array_merge($stats['added'], $added),
        'alreadyAdded' => array_merge($stats['alreadyAdded'], $alreadyAdded),
        'notValid'     => array_merge($stats['notValid'], $notValid),
      );
      $cache->set($key, $stats);
    }
    return CRM_Queue_Task::TASK_SUCCESS;
  }

  /**
   * Fetch cache object.
   *
   * @return object CRM_Utils_Cache_SqlGroup
   */
  static function getCache() {
    $cache   = new CRM_Utils_Cache_SqlGroup(array(
      'group' => 'Civigiftaid',
    ));
    return $cache;
  }

  /**
   * Fetch cache key.
   *
   * @return string cache key
   */
  static function getCacheKey($qfKey) {
    return "civigiftaid_addtobatch_stats_{$qfKey}";
  }

}
