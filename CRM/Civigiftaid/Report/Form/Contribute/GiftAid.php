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
 * Class CRM_Civigiftaid_Report_Form_Contribute_GiftAid
 */
class CRM_Civigiftaid_Report_Form_Contribute_GiftAid extends CRM_Report_Form {
  protected $_addressField = FALSE;
  protected $_customGroupExtends = ['Contribution'];

  /**
   * Lazy cache for storing processed batches.
   *
   * @var array
   */
  private static $batches = [];

  public function __construct() {
    $this->_columns = [
      'civicrm_entity_batch' => [
        'dao' => 'CRM_Batch_DAO_EntityBatch',
        'filters' => [
          'batch_id' => [
            'title'        => ts('Batch'),
            'operatorType' => CRM_Report_Form::OP_MULTISELECT,
            'options'      => CRM_Civigiftaid_Utils_Contribution::getBatchIdTitle('id desc'),
            'type' => CRM_Utils_Type::T_INT,
          ],
        ],
        'fields' => [
          'batch_id' => [
            'name'       => 'batch_id',
            'title'      => ts('Batch ID'),
            'no_display' => TRUE,
            'required'   => TRUE,
          ]
        ]
      ],
      'civicrm_contact' => [
        'dao'    => 'CRM_Contact_DAO_Contact',
        'fields' => [
          'prefix_id' => [
            'name'       => 'prefix_id',
            'title'      => ts('Title'),
            'no_display' => FALSE,
            'required'   => TRUE,
          ],
          'first_name'      => [
            'name'       => 'first_name',
            'title'      => ts('First Name'),
            'no_display' => FALSE,
            'required'   => TRUE,
          ],
          'last_name'    => [
            'name'       => 'last_name',
            'title'      => ts('Last Name'),
            'no_display' => FALSE,
            'required'   => TRUE,
          ],
        ],
      ],
      'civicrm_contribution' => [
        'dao' => 'CRM_Contribute_DAO_Contribution',
        'fields' => [
          'contribution_id' => [
            'name' => 'id',
            'title' => ts('Contribution ID'),
            'required' => TRUE,
          ],
          'contact_id' => [
            'name' => 'contact_id',
            'title' => ts('Donor Name'),
            'required' => TRUE,
          ],
          'receive_date' => [
            'name' => 'receive_date',
            'title' => ts('Donation Date'),
            'type' => CRM_Utils_Type::T_STRING,
            'required' => TRUE,
          ],
          'contribution_amount' => [
            'name' => 'total_amount',
            'title' => ts('Donation Amount'),
            'type' => CRM_Utils_Type::T_INT,
            'required' => TRUE,
          ]
        ],
      ],
      'civicrm_address' => [
        'dao'      => 'CRM_Core_DAO_Address',
        'grouping' => 'contact-fields',
        'fields'   => [
          'street_address'    => [
            'name'       => 'street_address',
            'title'      => ts('Street Address'),
            'no_display' => FALSE,
            'required'   => TRUE,
          ],
          'postal_code'       => [
            'name'       => 'postal_code',
            'title'      => ts('Postcode'),
            'no_display' => FALSE,
            'required'   => TRUE,
          ],
        ],
      ],
    ];

    parent::__construct();

    // set defaults
    if (is_array($this->_columns['civicrm_value_gift_aid_submission'])) {
      foreach ($this->_columns['civicrm_value_gift_aid_submission']['fields'] as $field => $values) {
        $this->_columns['civicrm_value_gift_aid_submission']['fields'][$field]['default'] = TRUE;
        if ($values['dataType'] === 'Money') {
          $this->_columns['civicrm_value_gift_aid_submission']['fields'][$field]['dataType'] = 'Integer';
          $this->_columns['civicrm_value_gift_aid_submission']['fields'][$field]['type'] = CRM_Utils_Type::T_INT;
        }
      }
    }
  }

  public function select() {
    $select = [];

    $this->_columnHeaders = [];
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

    $this->_columnHeaders['civicrm_address_house_number'] = [
      'title' => 'House name or number',
    ];

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
      LEFT JOIN civicrm_address {$this->_aliases['civicrm_address']}
      ON ({$this->_aliases['civicrm_contribution']}.contact_id = {$this->_aliases['civicrm_address']}.contact_id
        AND {$this->_aliases['civicrm_address']}.location_type_id = " . CRM_Civigiftaid_Declaration::getAddressLocationID() . ")";
  }

  public function where() {
    $this->_whereClauses[] = "{$this->_aliases['civicrm_value_gift_aid_submission']}.amount IS NOT NULL";
    $this->_whereClauses[] = "{$this->_aliases['civicrm_contact']}.contact_type = 'Individual'";
    parent::where();
  }

  public function statistics(&$rows) {
    $statistics = parent::statistics($rows);

    $totals = [
      'contribution' => 0,
      'eligibleAmount' => 0,
      'giftAidAmount' => 0,
    ];
    $giftAidEligibleAmountField = 'civicrm_value_gift_aid_submission_' . CRM_Civigiftaid_Utils::getCustomByName('amount', 'Gift_Aid');
    $giftAidAmountField = 'civicrm_value_gift_aid_submission_' . CRM_Civigiftaid_Utils::getCustomByName('gift_aid_amount', 'Gift_Aid');

    foreach ($rows as $row) {
      $totals['contribution'] += $row['civicrm_contribution_contribution_amount'];
      $totals['eligibleAmount'] += $row[$giftAidEligibleAmountField];
      $totals['giftAidAmount'] += $row[$giftAidAmountField];
    }

    foreach ($totals as $key => $value) {
      $totals[$key] = number_format($value, 2);
    }

    $statistics['counts']['amount'] = [
      'value' => $totals['contribution'],
      'title' => 'Total Donation Amount',
      'type'  => CRM_Utils_Type::T_MONEY
    ];
    $statistics['counts']['eligibleamount'] = [
      'value' => $totals['eligibleAmount'],
      'title' => 'Total Eligible Amount',
      'type'  => CRM_Utils_Type::T_MONEY
    ];
    $statistics['counts']['giftaidamount'] = [
      'value' => $totals['giftAidAmount'],
      'title' => 'Total Gift Aid Amount',
      'type'  => CRM_Utils_Type::T_MONEY
    ];

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
    foreach ($rows as $rowNum => $row) {
      if (array_key_exists('civicrm_contact_first_name', $row)) {
        list($contactName, $errors) = CRM_Civigiftaid_Declaration::getFilteredDonorName($row['civicrm_contact_first_name'], $row['civicrm_contact_last_name']);
        $rows[$rowNum]['civicrm_contact_first_name'] = $contactName[0];
        $rows[$rowNum]['civicrm_contact_last_name'] = $contactName[1];
      }
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
        $entryFound = TRUE;
      }
      if (array_key_exists('civicrm_contribution_contribution_id', $row)) {
        $url = CRM_Utils_System::url("civicrm/contact/view/contribution",
          "reset=1&cid={$row['civicrm_contribution_contact_id']}&id={$row['civicrm_contribution_contribution_id']}&action=view&context=contribution",
          $this->_absoluteUrl
        );
        $rows[$rowNum]['civicrm_contribution_contribution_id_link'] = $url;
        $rows[$rowNum]['civicrm_contribution_contribution_id_hover'] = ts('View contribution');
      }


      if (array_key_exists('civicrm_address_street_address', $row)) {
        $address = CRM_Civigiftaid_Declaration::getDonorAddress($row['civicrm_contribution_contact_id'], $row['civicrm_contribution_contribution_id'], CRM_Utils_Date::isoToMysql($row['civicrm_contribution_receive_date']));
        $rows[$rowNum]['civicrm_address_house_number'] = $address['house_number'];
        $rows[$rowNum]['civicrm_address_street_address']
          = $address['address'];
        $rows[$rowNum]['civicrm_address_postal_code'] = $address['postcode'];
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

  private function reorderColumns() {
    $columnTitleOrder = [
      'title',
      'first name',
      'last name',
      'house name or number',
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
      'donation amount',
      'eligible amount',
      'gift aid amount',
      'batch name',
      'contribution id',
      'line item id',
    ];

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

