<?php

/**
 * Created by PhpStorm.
 * User: erik
 * Date: 26-8-15
 * Time: 16:48
 */
class CRM_MigrateLogger {
  private $logFile = null;
  function __construct($fileName = "aivl_migrate_log") {
    $config = CRM_Core_Config::singleton();
    $runDate = new DateTime('now');
    $fileName = $config->configAndLogDir.$fileName."_".$runDate->format('YmdHis');
    $this->logFile = fopen($fileName, 'w');
    $this->createErrorTable();
  }

  public function logMessage($type, $message) {
    $this->addMessage($type, $message);
  }

  /**
   * Method to log the message
   *
   * @param $type
   * @param $message
   */
  private function addMessage($type, $message) {
    fputs($this->logFile, date('Y-m-d h:i:s'));
    fputs($this->logFile, ' ');
    fputs($this->logFile, $type);
    fputs($this->logFile, ' ');
    fputs($this->logFile, $message);
    fputs($this->logFile, "\n");
  }

  /**
   * Method to log error
   * @param $message
   * @param null $contactId
   * @param null $pledgeId
   * @param null $recurId
   * @param null $campaignId
   * @param string $details
   */
  public function logError($message, $contactId = null, $pledgeId = null, $recurId = null, $campaignId = null, $details = "") {
    if (!empty($message)) {
      $this->logMessage('Error', $message);
    }
    $errorParams = array();
    $errorParams['message'] = $message;
    if (!empty($contactId)) {
      $errorParams['contact_id'] = $contactId;
    }
    if (!empty($pledgeId)) {
      $errorParams['pledge_id'] = $pledgeId;
    }
    if (!empty($recurId)) {
      $errorParams['recur_id'] = $recurId;
    }
    if (!empty($campaignId)) {
      $errorParams['campaign_id'] = $campaignId;
    }
    if (!empty($details)) {
      $errorParams['details'] = $details;
    }
    $this->addErrorRecord($errorParams);
  }

  /**
   * Method to create error record in error table
   * @param $params
   */
  private function addErrorRecord($params) {
    $setValues = array();
    $setValues[] = 'error_message = %1';
    $setParams[1] = array($params['message'], 'String');
    $setValues[] = 'migration_date = %2';
    $migrationDate = new DateTime('now');
    $setParams[2] = array($migrationDate->format('Y-m-d H:i:s'), 'Date');
    $count = 2;

    if (!empty($params['contact_id'])) {
      $count++;
      $setValues[] = 'contact_id = %'.$count;
      $setParams[$count] = array($params['contact_id'], 'Integer');
    }

    if (!empty($params['pledge_id'])) {
      $count++;
      $setValues[] = 'pledge_id = %'.$count;
      $setParams[$count] = array($params['pledge_id'], 'Integer');
    }

    if (!empty($params['recur_id'])) {
      $count++;
      $setValues[] = 'recur_id = %'.$count;
      $setParams[$count] = array($params['recur_id'], 'Integer');
    }

    if (!empty($params['campaign_id'])) {
      $count++;
      $setValues[] = 'campaign_id = %'.$count;
      $setParams[$count] = array($params['campaign_id'], 'Integer');
    }

    if (!empty($params['details'])) {
      $count++;
      $setValues[] = 'details = %'.$count;
      $setParams[$count] = array($params['details'], 'String');
    }
    $query = "INSERT INTO sepa_migrate_errors SET ".implode(', ', $setValues);

    CRM_Core_DAO::executeQuery($query, $setParams);

  }

  /**
   * Method to create error table
   */
  private function createErrorTable() {
    $errorFile = "CREATE TABLE IF NOT EXISTS `sepa_migrate_errors` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `migration_date` DATE DEFAULT NULL,
  `error_message` TEXT,
  `contact_id` INT(11) DEFAULT NULL,
  `pledge_id` INT(11) DEFAULT NULL,
  `recur_id` INT(11) DEFAULT NULL,
  `campaign_id` INT(11) DEFAULT NULL,
  `details` VARCHAR(256) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id_UNIQUE` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
";
    CRM_Core_DAO::executeQuery($errorFile);
  }
}