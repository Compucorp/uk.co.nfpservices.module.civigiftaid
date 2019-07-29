<?php
use CRM_Civigiftaid_ExtensionUtil as E;

/**
 * Collection of upgrade steps.
 */
class CRM_Civigiftaid_Upgrader extends CRM_Civigiftaid_Upgrader_Base {

  const REPORT_CLASS = 'CRM_Civigiftaid_Report_Form_Contribute_GiftAid';
  const REPORT_URL = 'civicrm/contribute/report/uk-giftaid';

  // By convention, functions that look like "function upgrade_NNNN()" are
  // upgrade tasks. They are executed in order (like Drupal's hook_update_N).

  /**
   * Example: Run an external SQL script when the module is installed
   */
  public function install() {
    //only for vesion 1.0 to 2.1
    //To remove the future
    self::migrateOneToTwo($this);
    //end step for upgrading version 1.0 t0 2.1

    $this->upgrade_3000();
    $this->upgrade_3101();
    $this->upgrade_3103();
  }

  /**
   * Example: Run an external SQL script when the module is uninstalled
   */
  public function uninstall() {
    $this->unsetSettings();
  }

  /**
   * Example: Run a simple query when a module is enabled
   */
  public function enable() {
    CRM_Core_DAO::executeQuery("UPDATE civicrm_option_group SET is_active = 1  WHERE name = 'giftaid_batch_name'");
    CRM_Core_DAO::executeQuery("UPDATE civicrm_option_group SET is_active = 1  WHERE name = 'giftaid_basic_rate_tax'");
    CRM_Core_DAO::executeQuery("UPDATE civicrm_option_group SET is_active = 1  WHERE name = 'reason_ended'");

    civicrm_api3('CustomGroup', 'update', [
      'is_active' => 1,
      'id' => CRM_Utils_Array::value('id',civicrm_api3('CustomGroup', 'getsingle', ['name' => 'Gift_Aid'])),
    ]);

    civicrm_api3('CustomGroup', 'update', [
      'is_active' => 1,
      'id' =>  CRM_Utils_Array::value('id',civicrm_api3('CustomGroup', 'getsingle', ['name' => 'Gift_Aid_Declaration'])),
    ]);

    civicrm_api3('UFGroup', 'update', [
      'is_active' => 1,
      'id' =>  CRM_Utils_Array::value('id',civicrm_api3('UFGroup', 'getsingle', ['name' => 'Gift_Aid_Declaration'])),
    ]);
  }

  /**
   * Example: Run a simple query when a module is disabled
   *
   */
  public function disable() {
    CRM_Core_DAO::executeQuery("UPDATE civicrm_option_group SET is_active = 0 WHERE name = 'giftaid_batch_name'");
    CRM_Core_DAO::executeQuery("UPDATE civicrm_option_group SET is_active = 0 WHERE name = 'giftaid_basic_rate_tax'");
    CRM_Core_DAO::executeQuery("UPDATE civicrm_option_group SET is_active = 0 WHERE name = 'reason_ended'");

    civicrm_api3('CustomGroup', 'update', [
      'is_active' => 0,
      'id' => CRM_Utils_Array::value('id',civicrm_api3('CustomGroup', 'getsingle', ['name' => 'Gift_Aid'])),
    ]);

    civicrm_api3('CustomGroup', 'update', [
      'is_active' => 0,
      'id' =>  CRM_Utils_Array::value('id',civicrm_api3('CustomGroup', 'getsingle', ['name' => 'Gift_Aid_Declaration'])),
    ]);

    civicrm_api3('UFGroup', 'update', [
      'is_active' => 0,
      'id' =>  CRM_Utils_Array::value('id',civicrm_api3('UFGroup', 'getsingle', ['name' => 'Gift_Aid_Declaration'])),
    ]);
  }

  /**
   * Perform upgrade to version 2.1
   *
   * @return TRUE on success
   * @throws Exception
   */
  public function upgrade_2100() {
    $this->log('Applying update 2100');
    self::removeLegacyRegisteredReport();
    return TRUE;
  }

  /**
   * Perform upgrade to version 3.0
   *
   * @return bool
   */
  public function upgrade_3000() {
    $this->log('Applying update 3000');

    // Set default settings.
    $this->setDefaultSettings();

    // Create database schema.
    $this->executeSqlFile('sql/upgrade_3000.sql');

    // Import existing batches.
    self::importBatches();

    return TRUE;
  }

  /*
   * Set up Past Year Submissions Job
   */
  public function upgrade_3101() {
    $this->log('Applying update 3101 - Add past year submissions job');

    $existing = civicrm_api3('Job', 'get', [
      'api_entity' => "gift_aid",
      'api_action' => "makepastyearsubmissions",
    ]);

    if (empty($existing['count'])) {
      $jobParams = [
        'domain_id' => CRM_Core_Config::domainID(),
        'run_frequency' => 'Daily',
        'name' => 'Make Past Year Submissions',
        'description' => 'Make Past Year Submissions',
        'api_entity' => 'gift_aid',
        'api_action' => 'makepastyearsubmissions',
        'is_active' => 0,
      ];
      civicrm_api3('Job', 'create', $jobParams);
    }
    return TRUE;
  }

  public function upgrade_3102() {
    $this->log('Applying update 3102');

    // Alter existing eligible_for_gift_aid columns
    CRM_Core_DAO::executeQuery("ALTER TABLE civicrm_value_gift_aid_declaration MODIFY COLUMN eligible_for_gift_aid int");
    CRM_Core_DAO::executeQuery("ALTER TABLE civicrm_value_gift_aid_submission MODIFY COLUMN eligible_for_gift_aid int");

    // Update custom field type from String to Int
    CRM_Core_DAO::executeQuery("UPDATE civicrm_custom_field SET data_type = 'Int' WHERE name = 'Eligible_for_Gift_Aid'");

    $og1 = civicrm_api3('OptionGroup', 'get', [
      'name' => "eligibility_declaration_options",
    ]);
    $og1Params = [
      'name' => "eligibility_declaration_options",
      'title' => "Eligibility Declaration Options",
      'label' => "Eligibility Declaration Options",
      'is_active' => 1,
      'is_reserved' => 1,
    ];
    if ($og1['id']) {
      $og1Params['id'] = $og1['id'];
    }
    // Add new option groups and options
    $og1 = civicrm_api3('OptionGroup', 'create', $og1Params);

    $og2 = civicrm_api3('OptionGroup', 'get', [
      'name' => "uk_taxpayer_options",
    ]);
    $og2Params = [
      'name' => "uk_taxpayer_options",
      'title' => "UK Taxpayer Options",
      'label' => "UK Taxpayer Options",
      'is_active' => 1,
      'is_reserved' => 1
    ];
    if ($og2['id']) {
      $og2Params['id'] = $og2['id'];
    }
    $og2 = civicrm_api3('OptionGroup', 'create', $og2Params);
    $og1Id = CRM_Utils_Array::value('id', $og1);
    $og2Id = CRM_Utils_Array::value('id', $og2);

    $optionValues = [
      [
        'option_group_id' => $og1Id,
        'label' => 'Yes',
        'value' => 1,
        'name' => 'eligible_for_giftaid',
      ],
      [
        'option_group_id' => $og1Id,
        'label' => 'No',
        'value' => 0,
        'name' => 'not_eligible_for_giftaid',
        'is_default' => 1,
      ],
      [
        'option_group_id' => $og1Id,
        'label' => 'Yes, in the Past 4 Years',
        'value' => 3,
        'name' => 'past_four_years',
      ],
      [
        'option_group_id' => $og2Id,
        'label' => 'Yes',
        'value' => 1,
        'name' => 'yes_uk_taxpayer',
      ],
      [
        'option_group_id' => $og2Id,
        'label' => 'No',
        'value' => 0,
        'name' => 'not_uk_taxpayer',
      ],
      [
        'option_group_id' => $og2Id,
        'label' => 'Yes, in the Past 4 Years',
        'value' => 3,
        'name' => 'uk_taxpayer_past_four_years',
      ],
    ];

    foreach($optionValues as $params) {
      $optionValue = civicrm_api3('OptionValue', 'get', [
        'option_group_id' => $params['option_group_id'],
        'name' => $params['name'],
      ]);
      if ($optionValue['id']) {
        $params['id'] = $optionValue['id'];
      }
      civicrm_api3('OptionValue', 'create', $params);
    }

    $declarationCustomGroupID = CRM_Utils_Array::value('id', civicrm_api3('CustomGroup', 'getsingle', ['name' => 'Gift_Aid_Declaration']));
    $submissionCustomGroupID = CRM_Utils_Array::value('id', civicrm_api3('CustomGroup', 'getsingle', ['name' => 'Gift_Aid']));

    CRM_Core_DAO::executeQuery("UPDATE civicrm_custom_field SET option_group_id = {$og1Id} WHERE name = 'Eligible_for_Gift_Aid' AND custom_group_id = {$declarationCustomGroupID}");
    CRM_Core_DAO::executeQuery("UPDATE civicrm_custom_field SET option_group_id = {$og2Id} WHERE name = 'Eligible_for_Gift_Aid' AND custom_group_id = {$submissionCustomGroupID}");

    return TRUE;
  }

  public function upgrade_3103() {
    $this->log('Applying update 3103 - delete old report templates');
    $this->removeLegacyRegisteredReport();
    return TRUE;
  }

  /**
   * Remove report templates created by older versions
   */
  private static function removeLegacyRegisteredReport(){
    $report1 = civicrm_api3('OptionValue', 'get', [
      'option_group_id' => "report_template",
      'name' => 'GiftAid_Report_Form_Contribute_GiftAid',
    ]);
    $report2 = civicrm_api3('OptionValue', 'get', [
      'option_group_id' => "report_template",
      'value' => 'civicrm/contribute/uk-giftaid',
    ]);

    $reports = [];
    if (!empty($report1['count'])) {
      $reports[] = CRM_Utils_Array::first($report1['values']);
    }
    if (!empty($report2['count'])) {
      $reports[] = CRM_Utils_Array::first($report2['values']);
    }
    foreach ($reports as $report) {
      civicrm_api3('OptionValue', 'delete', ['id' => $report['id']]);
    }
  }

  private static function migrateOneToTwo($ctx){
    $ctx->executeSqlFile('sql/upgrade_20.sql');
    $query = "SELECT DISTINCT batch_name
              FROM civicrm_value_gift_aid_submission
             ";
    $batchNames = [];
    $dao = CRM_Core_DAO::executeQuery($query);
    while ($dao->fetch()) {
      array_push($batchNames, $dao->batch_name);
    }
    $gId = CRM_Utils_Array::value('id',civicrm_api3('OptionGroup', 'getsingle', ['name' => 'giftaid_batch_name']));
    if ($gId) {
      foreach ($batchNames as $name) {
        $params = [
          'option_group_id' => $gId,
          'name' => $name,
        ];
        $existing = civicrm_api3('OptionValue', 'get', $params);
        if (empty($existing['count'])) {
          $params['label'] = $name;
          $params['value'] = $name;
          $params['is_active'] = 1;
          civicrm_api3('OptionValue', 'create', $params);
        }
      }
    }
  }

  /**
   * Set the default admin settings for the extension.
   */
  private function setDefaultSettings() {
    Civi::settings()->set(E::SHORT_NAME . 'globally_enabled', 1);
    Civi::settings()->set(E::SHORT_NAME . 'financial_types_enabled', []);
  }

  /**
   * Remove the admin settings for the extension.
   */
  private function unsetSettings() {
    Civi::settings()->revert(E::SHORT_NAME . 'globally_enabled');
    Civi::settings()->revert(E::SHORT_NAME . 'financial_types_enabled');
  }

  /**
   * Create default settings for existing batches, for which settings don't already exist.
   */
  private static function importBatches() {
    $sql = "
      SELECT id
      FROM civicrm_batch
      WHERE name LIKE 'GiftAid%'
    ";

    $dao = CRM_Core_DAO::executeQuery($sql);

    $basicRateTax = CRM_Civigiftaid_Utils_Contribution::getBasicRateTax();

    while ($dao->fetch()) {
      // Only add settings for batches for which settings don't exist already
      if (CRM_Civigiftaid_BAO_BatchSettings::findByBatchId($dao->id) === FALSE) {
        // Set globally enabled to TRUE by default, for existing batches
        CRM_Civigiftaid_BAO_BatchSettings::create([
          'batch_id' => (int) $dao->id,
          'financial_types_enabled' => [],
          'globally_enabled' => TRUE,
          'basic_rate_tax' => $basicRateTax
        ]);
      }
    }
  }

  private function log($message) {
    Civi::log()->info($message);
  }
}
