<?php
use CRM_Givexpert_ExtensionUtil as E;

class CRM_Givexpert_Page_Run extends CRM_Core_Page {

  public function run() {
    CRM_Utils_System::setTitle(E::ts('Run'));

    $result = civicrm_api3('Givexpert', 'Syncorders', []);

    $this->assign('currentTime', date('Y-m-d H:i:s'));
    $this->assign('message', print_r($result, TRUE));

    parent::run();
  }

}
