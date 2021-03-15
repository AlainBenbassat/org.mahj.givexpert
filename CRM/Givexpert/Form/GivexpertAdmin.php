<?php

use CRM_Givexpert_ExtensionUtil as E;

class CRM_Givexpert_Form_GivexpertAdmin extends CRM_Core_Form {
  private $settings;

  public function __construct($state = NULL, $action = CRM_Core_Action::NONE, $method = 'post', $name = NULL) {
    $this->settings = new CRM_Givexpert_Settings();

    parent::__construct($state, $action, $method, $name);
  }

  public function buildQuickForm() {
    $this->setTitle(E::ts('GiveXpert API Settings'));

    $this->addFormFields();
    $this->setFormFieldsDefaultValues();
    $this->addFormButtons();

    // export form elements
    $this->assign('elementNames', $this->getRenderableElementNames());
    parent::buildQuickForm();
  }

  public function postProcess() {
    $values = $this->exportValues();
    $this->saveFormFieldValues($values);

    CRM_Core_Session::setStatus(E::ts('Saved'), '', 'success');
    parent::postProcess();
  }

  private function addFormFields() {
    $this->add('text', 'givexpert_api_url', E::ts('GiveXpert API Endpoint (URL)'));
    $this->add('text', 'givexpert_username', E::ts('User Name'));
    $this->add('text', 'givexpert_token', E::ts('Password (token)'));
  }

  private function addFormButtons() {
    $this->addButtons([
      [
        'type' => 'submit',
        'name' => E::ts('Save'),
        'isDefault' => TRUE,
      ],
    ]);
  }

  private function setFormFieldsDefaultValues() {
    $defaults = [];
    $defaults['givexpert_api_url'] = $this->settings->getApiEndpoint();
    $defaults['givexpert_username'] = $this->settings->getUsername();
    $defaults['givexpert_token'] = $this->settings->getToken();
    $this->setDefaults($defaults);
  }

  private function saveFormFieldValues($values) {
    $v = CRM_Utils_Array::value('givexpert_api_url', $values);
    if ($v) {
      $this->settings->setApiEndpoint($v);
    }

    $v = CRM_Utils_Array::value('givexpert_username', $values);
    if ($v) {
      $this->settings->setUsername($v);
    }

    $v = CRM_Utils_Array::value('givexpert_token', $values);
    if ($v) {
      $this->settings->setToken($v);
    }

    $this->settings->getCustomFieldIdGiveXpertId();
  }

  private function getRenderableElementNames() {
    $elementNames = array();
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
