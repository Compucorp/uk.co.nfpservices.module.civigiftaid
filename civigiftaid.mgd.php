<?php

return [
  0 => [
    'name' => 'Gift Aid Report',
    'entity' => 'ReportTemplate',
    'params' => [
      'version' => 3,
      'label' => 'Gift Aid Report',
      'description' => 'Gift Aid Report - For submitting Gift Aid reports to HMRC treasury.',
      'class_name' => CRM_Civigiftaid_Upgrader::REPORT_CLASS,
      'report_url' => CRM_Civigiftaid_Upgrader::REPORT_URL,
      'component' => 'CiviContribute',
    ],
  ],
];
