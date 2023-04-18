<?php

use CRM_Givexpert_ExtensionUtil as E;

class CRM_Givexpert_Form_GivexpertSyncOrder extends CRM_Core_Form {
  public function buildQuickForm() {
    $this->setTitle('Synchroniser un don GiveXpert spćifique');

    $this->addFormFields();
    $this->addFormButtons();

    // export form elements
    $this->assign('elementNames', $this->getRenderableElementNames());
    parent::buildQuickForm();
  }

  public function postProcess() {
    try {
      $giftId = $this->getSubmittedGiftId();
      civicrm_api3('Givexpert','syncorders', [
        'id' => $giftId,
      ]);

      CRM_Core_Session::setStatus('OK', '', 'success');
    }
    catch (Exception $e) {
      CRM_Core_Session::setStatus($e->getMessage(), '', 'error');
    }

    parent::postProcess();
  }

  private function getSubmittedGiftId() {
    $values = $this->exportValues();
    return CRM_Utils_Array::value('gift_id', $values);
  }

  private function addFormFields() {
    $this->add('text', 'gift_id', 'Synchroniser un don spécifique :', ['placeholder' => 'p.e. 1500'], TRUE);
  }

  private function addFormButtons() {
    $this->addButtons([
      [
        'type' => 'submit',
        'name' => 'Importer',
        'isDefault' => TRUE,
      ],
    ]);
  }

  public function getRenderableElementNames() {
    $elementNames = [];
    foreach ($this->_elements as $element) {
      /** @var HTML_QuickForm_Element $element */
      $label = $element->getLabel();
      if (!empty($label)) {
        $elementNames[] = $element->getName();
      }
    }
    return $elementNames;
  }

}
