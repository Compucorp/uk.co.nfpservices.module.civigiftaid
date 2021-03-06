<?php

/*
 +--------------------------------------------------------------------+
 | CiviCRM version 4.3                                              |
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
require_once 'CRM/Report/Form.php';
require_once 'CRM/Civigiftaid/Utils/Contribution.php';

class CRM_Civigiftaid_Report_Form_Contribute_GiftAid extends CRM_Report_Form {
  protected $_addressField = FALSE;
  protected $_customGroupExtends = array('Contribution');

  /**
   * Lazy cache for storing processed batches.
   *
   * @var array
   */
  private static $batches = array();

  public function __construct() {
    $this->_columns =
      array(
        'civicrm_entity_batch'   => array(
          'dao'     => 'CRM_Batch_DAO_EntityBatch',
          'filters' =>
            array(
              'batch_id' => array(
                'title'        => ts('Batch'),
                'operatorType' => CRM_Report_Form::OP_MULTISELECT,
                'options'      => CRM_Civigiftaid_Utils_Contribution::getBatchIdTitle('id desc'),
              ),
            ),
          'fields'  => array(
            'batch_id' => array(
              'name'       => 'batch_id',
              'title'      => ts('Batch ID'),
              'no_display' => TRUE,
              'required'   => TRUE,
            )
          )
        ),
        'civicrm_contact'   =>
          array(
            'dao'    => 'CRM_Contact_DAO_Contact',
            'fields' => array(
              'prefix_id' => array(
                'name'       => 'prefix_id',
                'title'      => ts('Title'),
                'no_display' => FALSE,
                'required'   => TRUE,
              ),
              'first_name'      => array(
                'name'       => 'first_name',
                'title'      => ts('First Name'),
                'no_display' => FALSE,
                'required'   => TRUE,
              ),
              'last_name'    => array(
                'name'       => 'last_name',
                'title'      => ts('Last Name'),
                'no_display' => FALSE,
                'required'   => TRUE,
              ),
            ),
          ),
        'civicrm_contribution'   =>
          array(
            'dao'    => 'CRM_Contribute_DAO_Contribution',
            'fields' => array(
              'contribution_id' => array(
                'name'       => 'id',
                'title'      => ts('Payment No'),
              ),
              'contact_id'      => array(
                'name'       => 'contact_id',
                'title'      => ts('Donor Name'),
                'no_display' => FALSE,
                'required'   => TRUE,
              ),
              'receive_date'    => array(
                'name'       => 'receive_date',
                'title'      => ts('Donation Date'),
                'type'       => CRM_Utils_Type::T_STRING,
                'no_display' => FALSE,
                'required'   => TRUE,
              ),
            ),
          ),
        'civicrm_financial_type' =>
          array(
            'dao'    => 'CRM_Financial_DAO_FinancialType',
            'fields' => array(
              'financial_type_id' => array(
                'name'       => 'id',
                'title'      => ts('Financial Type No'),
                'no_display' => TRUE,
                'required'   => TRUE,
              ),
            ),
          ),
        'civicrm_address'        =>
          array(
            'dao'      => 'CRM_Core_DAO_Address',
            'grouping' => 'contact-fields',
            'fields'   =>
              array(
                'street_address'    => array(
                  'name'       => 'street_address',
                  'title'      => ts('Street Address'),
                  'no_display' => FALSE,
                  'required'   => TRUE,
                ),
                'city'              => array(
                  'name'       => 'city',
                  'title'      => ts('City'),
                ),
                'state_province_id' => array(
                  'name'       => 'state_province_id',
                  'title'      => ts('State/Province'),
                ),
                'country_id'        => array(
                  'name'       => 'country_id',
                  'title' => ts('Country'),
                ),
                'postal_code'       => array(
                  'name'       => 'postal_code',
                  'title'      => ts('Postcode'),
                  'no_display' => FALSE,
                  'required'   => TRUE,
                ),
              ),
          ),
        'civicrm_line_item'      =>
          array(
            'dao'    => 'CRM_Price_DAO_LineItem',
            'fields' => array(
              'id'           => array(
                'name'       => 'id',
                'title'      => ts('Line Item No'),
              ),
              'amount'       => array(
                'name'       => 'line_total',
                'title'      => ts('Amount'),
                'no_display' => FALSE,
                'required'   => TRUE,
                // HMRC requires only number
                //'type'       => CRM_Utils_Type::T_MONEY
              ),
              'quantity'     => array(
                'name'       => 'qty',
                'title'      => ts('Quantity'),
                'type'       => CRM_Utils_Type::T_INT
              ),
              'entity_table' => array(
                'name'       => 'entity_table',
                'title'      => ts('Item'),
              ),
              'label'        => array(
                'name'       => 'label',
                'title'      => ts('Description'),
              ),
            ),
          )
      );

    parent::__construct();

    // set defaults
    if (is_array($this->_columns['civicrm_value_gift_aid_submission'])) {
      foreach (
        $this->_columns['civicrm_value_gift_aid_submission']['fields']
        as $field => $values
      ) {
        if (in_array($this->_columns['civicrm_value_gift_aid_submission']['fields'][$field]['name'],
          array('amount', 'gift_aid_amount'))) {
          unset($this->_columns['civicrm_value_gift_aid_submission']['fields'][$field]);
          continue;
        }
        $this->_columns['civicrm_value_gift_aid_submission']['fields'][$field]['default'] =
          TRUE;
      }
    }

    $this->_settings = CRM_Civigiftaid_Form_Admin::getSettings();
  }

  public function select() {
    $select = array();

    $this->_columnHeaders = array();
    foreach ($this->_columns as $tableName => $table) {
      if (array_key_exists('fields', $table)) {
        foreach ($table['fields'] as $fieldName => $field) {
          if (CRM_Utils_Array::value('required', $field)
            || CRM_Utils_Array::value($fieldName, $this->_params['fields'])
          ) {
            if ($tableName == 'civicrm_address') {
              $this->_addressField = TRUE;
            }
            else {
              if ($tableName == 'civicrm_email') {
                $this->_emailField = TRUE;
              }
            }

            // only include statistics columns if set
            if (CRM_Utils_Array::value('statistics', $field)) {
              foreach ($field['statistics'] as $stat => $label) {
                switch (strtolower($stat)) {
                  case 'sum':
                    $select[] =
                      "SUM({$field['dbAlias']}) as {$tableName}_{$fieldName}_{$stat}";
                    $this->_columnHeaders["{$tableName}_{$fieldName}_{$stat}"]['title'] =
                      $label;
                    $this->_columnHeaders["{$tableName}_{$fieldName}_{$stat}"]['type'] =
                      $field['type'];
                    $this->_statFields[] = "{$tableName}_{$fieldName}_{$stat}";
                    break;

                  case 'count':
                    $select[] =
                      "COUNT({$field['dbAlias']}) as {$tableName}_{$fieldName}_{$stat}";
                    $this->_columnHeaders["{$tableName}_{$fieldName}_{$stat}"]['title'] =
                      $label;
                    $this->_statFields[] = "{$tableName}_{$fieldName}_{$stat}";
                    break;

                  case 'avg':
                    $select[] =
                      "ROUND(AVG({$field['dbAlias']}),2) as {$tableName}_{$fieldName}_{$stat}";
                    $this->_columnHeaders["{$tableName}_{$fieldName}_{$stat}"]['type'] =
                      $field['type'];
                    $this->_columnHeaders["{$tableName}_{$fieldName}_{$stat}"]['title'] =
                      $label;
                    $this->_statFields[] = "{$tableName}_{$fieldName}_{$stat}";
                    break;
                }
              }
            }
            else {
              $select[] = "{$field['dbAlias']} as {$tableName}_{$fieldName}";
              $this->_columnHeaders["{$tableName}_{$fieldName}"]['title'] =
                $field['title'];
              $this->_columnHeaders["{$tableName}_{$fieldName}"]['type'] =
                CRM_Utils_Array::value('type', $field);
            }
          }
        }
      }
    }

    $this->_columnHeaders['civicrm_line_item_gift_aid_amount'] = array(
      'title' => 'Gift Aid Amount',
      //'type'  => CRM_Utils_Type::T_MONEY
    );

    $this->reorderColumns();

    $this->_select = "SELECT " . implode(', ', $select) . " ";
  }

  public function from() {
    $this->_from = "
      FROM civicrm_entity_batch {$this->_aliases['civicrm_entity_batch']}
      INNER JOIN civicrm_contribution {$this->_aliases['civicrm_contribution']}
      ON {$this->_aliases['civicrm_entity_batch']}.entity_table = 'civicrm_contribution'
        AND {$this->_aliases['civicrm_entity_batch']}.entity_id = {$this->_aliases['civicrm_contribution']}.id
      INNER JOIN civicrm_contact {$this->_aliases['civicrm_contact']}
      ON {$this->_aliases['civicrm_contribution']}.contact_id = {$this->_aliases['civicrm_contact']}.id
      INNER JOIN civicrm_line_item {$this->_aliases['civicrm_line_item']}
      ON {$this->_aliases['civicrm_contribution']}.id = {$this->_aliases['civicrm_line_item']}.contribution_id
      INNER JOIN civicrm_financial_type {$this->_aliases['civicrm_financial_type']}
      ON {$this->_aliases['civicrm_line_item']}.financial_type_id = {$this->_aliases['civicrm_financial_type']}.id
      LEFT JOIN civicrm_address {$this->_aliases['civicrm_address']}
      ON ({$this->_aliases['civicrm_contribution']}.contact_id = {$this->_aliases['civicrm_address']}.contact_id
        AND {$this->_aliases['civicrm_address']}.is_primary = 1 )";
  }

  public function where() {
    parent::where();

    if (empty($this->_where)) {
      $this->_where =
        "WHERE value_gift_aid_submission_civireport.amount IS NOT NULL";
    }
    else {
      $this->_where .= " AND value_gift_aid_submission_civireport.amount IS NOT NULL";
    }
  }

  public function statistics(&$rows) {
    $statistics = parent::statistics($rows);

    $totalAmount = 0;
    $totalGiftAidAmount = 0;

    foreach ($rows as $row) {
      $totalAmount += $row['civicrm_line_item_amount'];
      $totalGiftAidAmount += $row['civicrm_line_item_gift_aid_amount'];
    }

    $totalAmount = round($totalAmount, 2);
    $totalGiftAidAmount = round($totalGiftAidAmount, 2);

    $statistics['counts']['amount'] = array(
      'value' => $totalAmount,
      'title' => 'Total Amount',
      'type'  => CRM_Utils_Type::T_MONEY
    );
    $statistics['counts']['giftaid'] = array(
      'value' => $totalGiftAidAmount,
      'title' => 'Total Gift Aid Amount',
      'type'  => CRM_Utils_Type::T_MONEY
    );

    return $statistics;
  }

  public function postProcess() {
    parent::postProcess();
  }

  /**
   * Alter the rows for display
   *
   * @param array $rows
   */
  public function alterDisplay(&$rows) {
    $entryFound = FALSE;
    require_once 'CRM/Contact/DAO/Contact.php';
    foreach ($rows as $rowNum => $row) {
      // i.e. remove row from report if it has financial type ineligible for Gift Aid
      if (FALSE === $this->hasEligibleFinancialType($row)) {
        unset($rows[$rowNum]);
        continue;
      }

      // handle contribution status id
      if (array_key_exists('civicrm_contribution_contact_id', $row)) {
        if ($value = $row['civicrm_contribution_contact_id']) {
          $contact = new CRM_Contact_DAO_Contact();
          $contact->id = $value;
          $contact->find(TRUE);
          $rows[$rowNum]['civicrm_contribution_contact_id'] =
            $contact->display_name;
          $url = CRM_Utils_System::url("civicrm/contact/view",
            'reset=1&cid=' . $value,
            $this->_absoluteUrl);
          $rows[$rowNum]['civicrm_contribution_contact_id_link'] = $url;
          $rows[$rowNum]['civicrm_contribution_contact_id_hover'] =
            ts("View Contact Summary for this Contact.");
        }
        if (isset($row['civicrm_line_item_amount'])) {
          $batch = $this->getBatchById($row['civicrm_entity_batch_batch_id']);
          $giftaidAmount = CRM_Civigiftaid_Utils_Contribution::calculateGiftAidAmt(
              $row['civicrm_line_item_amount'],
              $batch['basic_rate_tax']
          );
          $rows[$rowNum]['civicrm_line_item_gift_aid_amount'] = number_format((float)$giftaidAmount, 2, '.', '');
        }
        if (!empty($row['civicrm_line_item_entity_table'])) {
          $rows[$rowNum]['civicrm_line_item_entity_table'] =
            CRM_Civigiftaid_Utils_Contribution::getLineItemName(
              $row['civicrm_line_item_entity_table']
            );
        }
        if (isset($row['civicrm_line_item_quantity'])) {
          $rows[$rowNum]['civicrm_line_item_quantity'] = (int) $row['civicrm_line_item_quantity'];
        }

        $entryFound = TRUE;
      }

      // handle State/Province Codes
      if (array_key_exists('civicrm_address_state_province_id', $row)) {
        if ($value = $row['civicrm_address_state_province_id']) {
          $rows[$rowNum]['civicrm_address_state_province_id'] = CRM_Core_PseudoConstant::stateProvince($value, FALSE);
        }
        $entryFound = TRUE;
      }

      // handle Country Codes
      if (array_key_exists('civicrm_address_country_id', $row)) {
        if ($value = $row['civicrm_address_country_id']) {
          $rows[$rowNum]['civicrm_address_country_id'] = CRM_Core_PseudoConstant::country($value, FALSE);
        }
        $entryFound = TRUE;
      }

      // handle Contact Title
      if (array_key_exists('civicrm_contact_prefix_id', $row)) {
        if ($value = $row['civicrm_contact_prefix_id']) {
          $rows[$rowNum]['civicrm_contact_prefix_id'] = CRM_Core_PseudoConstant::getLabel('CRM_Contact_DAO_Contact', 'prefix_id', $value);
        }
        $entryFound = TRUE;
      }

      // handle donation date
      if (array_key_exists('civicrm_contribution_receive_date', $row)) {
        if ($value = $row['civicrm_contribution_receive_date']) {
          $rows[$rowNum]['civicrm_contribution_receive_date'] = date("d/m/y", strtotime($value));
        }
        $entryFound = TRUE;
      }

      // skip looking further in rows, if first row itself doesn't
      // have the column we need
      if (!$entryFound) {
        break;
      }
    }
  }

  /**
   * Return whether a row has financial type eligible for Gift Aid (i.e. has financial type which was enabled as
   * eligible for Gift Aid, at the time the contribution was added to the batch).
   *
   * @param $row
   *
   * @return bool
   */
  private function hasEligibleFinancialType($row) {
    if ((!$batch = $this->getBatchById($row['civicrm_entity_batch_batch_id']))
      || (!$batch['globally_enabled']
        && !in_array($row['civicrm_financial_type_financial_type_id'], $batch['financial_types_enabled']))
    ) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Get a batch by ID.
   *
   * @param $id
   *
   * @return mixed
   */
  private function getBatchById($id) {
    if (!isset(self::$batches[$id])) {
      if (($batch = CRM_Civigiftaid_BAO_BatchSettings::findByBatchId($id)) instanceof CRM_Core_DAO) {
        $batchArr = $batch->toArray();
        $batchArr['financial_types_enabled'] = unserialize($batchArr['financial_types_enabled']);

        self::$batches[$id] = $batchArr;
      }
      else {
        self::$batches[$id] = NULL;
      }
    }

    return self::$batches[$id];
  }

  private function reorderColumns() {
    $columnTitleOrder = array(
      'title',
      'first name',
      'last name',
      'street address',
      'city',
      'county',
      'postcode',
      'country',
      'donation date',
      'amount',
      'donor name',
      'item',
      'description',
      'quantity',
      'eligible for gift aid?',
      'batch name',
      'payment no',
      'line item no',
      'gift aid amount'
    );

    $compare = function ($a, $b) use (&$columnTitleOrder) {
      $titleA = strtolower($a['title']);
      $titleB = strtolower($b['title']);

      $posA = array_search($titleA, $columnTitleOrder);
      $posB = array_search($titleB, $columnTitleOrder);

      if ($posA === FALSE) {
        $columnTitleOrder[] = $titleA;
      }
      if ($posB === FALSE) {
        $columnTitleOrder[] = $titleB;
      }

      if ($posA > $posB || $posA === FALSE) {
        return 1;
      }
      if ($posA < $posB || $posB === FALSE) {
        return -1;
      }

      return 0;
    };

    $orderedColumnHeaders = $this->_columnHeaders;
    uasort($orderedColumnHeaders, $compare);

    $this->_columnHeaders = $orderedColumnHeaders;
  }
}

