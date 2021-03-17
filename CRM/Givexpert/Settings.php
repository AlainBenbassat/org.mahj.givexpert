<?php

class CRM_Givexpert_Settings {
  private $settingApiEndpoint = 'givexpert_api_endpoint';
  private $settingUsername = 'givexpert_username';
  private $settingToken = 'givexpert_token';
  private $customGroupIdContribution = 7;
  private $cachedCustomFieldIdGiveXpertId = null;

  public function getApiEndpoint() {
    return Civi::settings()->get($this->settingApiEndpoint);
  }

  public function setApiEndpoint($value) {
    Civi::settings()->set($this->settingApiEndpoint, $value);
  }

  public function getUsername() {
    return Civi::settings()->get($this->settingUsername);
  }

  public function setUsername($value) {
    Civi::settings()->set($this->settingUsername, $value);
  }

  public function getToken() {
    return Civi::settings()->get($this->settingToken);
  }

  public function setToken($value) {
    Civi::settings()->set($this->settingToken, $value);
  }

  public function getCustomFieldIdGiveXpertId() {
    // see if we have a cached version of the field
    if ($this->cachedCustomFieldIdGiveXpertId) {
      return $this->cachedCustomFieldIdGiveXpertId;
    }

    $params = [
      'custom_group_id' => $this->customGroupIdContribution,
      'name' => 'givexpert_id',
      'label' => 'Identifiant GiveXpert',
      'data_type' => 'Int',
      'html_type' => 'Text',
      'is_searchable' => '1',
      'is_search_range' => '1',
      'weight' => '5',
      'is_active' => '1',
      'text_length' => '255',
      'note_columns' => '60',
      'note_rows' => '4',
      'column_name' => 'givexpert_id',
      'in_selector' => '0'
    ];
    $this->cachedCustomFieldIdGiveXpertId = $this->createOrGetCustomField($params);
    return $this->cachedCustomFieldIdGiveXpertId;
  }

  private function createOrGetCustomField($params) {
    try {
      $customField = civicrm_api3('CustomField', 'getsingle', [
        'custom_group_id' => $params['custom_group_id'],
        'name' => $params['name'],
      ]);
    }
    catch (Exception $e) {
      $customField = civicrm_api3('CustomField', 'create', $params);
    }

    // add the table name
    $customField['table_name'] = CRM_Core_DAO::singleValueQuery("select table_name from civicrm_custom_group where id = " . $params['custom_group_id']);

    return $customField;
  }

}
