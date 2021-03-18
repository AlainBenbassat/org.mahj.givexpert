<?php

class CRM_Givexpert_Membership {
  private $settings;

  public function __construct($settings) {
    $this->settings = $settings;
  }

  public function createOrUpdate($contactId, $membershipTypeId, $date) {
    if ($this->hasContactCurrentMembership($contactId)) {
      $membershipId = $this->update($contactId, $membershipTypeId, $date);
    }
    else {
      $membershipId = $this->create($contactId, $membershipTypeId, $date);
    }

    return $membershipId;
  }

  private function hasContactCurrentMembership($contactId) {
    // see if there is a membership with an end date greater than now (minus 30 days grace period, assuming people are late with renewing)
    // we ignore the status
    $sql = "
      select
        *
      from
        civicrm_membership m
      where
        m.contact_id = %1
      and
        ifnull(m.end_date, now()) > now() - interval 30 day
    ";
    $sqlParams = [
      1 => [$contactId, 'Integer']
    ];
    $dao = CRM_Core_DAO::executeQuery($sql, $sqlParams);
    If ($dao->fetch()) {
      return TRUE;
    }
    else {
      return FALSE;
    }
  }

  private function create($contactId, $membershipTypeId, $date) {
    $params = [
      'sequential' => 1,
      'contact_id' => $contactId,
      'membership_type_id' => $membershipTypeId,
      'join_date' => $date,
      'start_date' => $date,
    ];
    $result = civicrm_api3('Membership', 'create', $params);

    return $result['values'][0]['id'];
  }

  private function update($contactId, $membershipTypeId, $date) {

  }
}
