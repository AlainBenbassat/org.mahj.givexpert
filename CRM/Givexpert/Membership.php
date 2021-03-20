<?php

class CRM_Givexpert_Membership {
  private $settings;
  private $MEMBERSHIP_CARTE_BIBLIOTHEQUE = 11;
  private $MEMBERSHIP_STATUS_NEW = 1;
  private $MEMBERSHIP_STATUS_CURRENT = 2;
  private $MEMBERSHIP_STATUS_CANCELLED = 6;

  public function __construct($settings) {
    $this->settings = $settings;
  }

  public function createOrUpdate($contactId, $membershipTypeId, $date) {
    $membershipId = $this->getCurrentMembershipOfType($contactId, $membershipTypeId);
    if ($membershipId) {
      $this->update($membershipId, $date);
    }
    else {
      $this->terminateAllMembershipsButSpecified($contactId, $date, $membershipId);
      $membershipId = $this->create($contactId, $membershipTypeId, $date);
    }

    return $membershipId;
  }

  private function getCurrentMembershipOfType($contactId, $membershipTypeId) {
    // see if there is a membership with an end date greater than now (minus 30 days grace period, assuming people are late with renewing)
    // we ignore the status
    $sql = "
      select
        m.id
      from
        civicrm_membership m
      where
        m.contact_id = %1
      and
        ifnull(m.end_date, now()) > now() - interval 30 day
      and
        m.membership_type_id = %2
      and
        m.owner_membership_id IS NULL
      order by
        m.id desc
    ";
    $sqlParams = [
      1 => [$contactId, 'Integer'],
      2 => [$membershipTypeId, 'Integer'],
    ];
    $dao = CRM_Core_DAO::executeQuery($sql, $sqlParams);
    If ($dao->fetch()) {
      return $dao->id;
    }
    else {
      return 0;
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

  private function update($membershipId, $date) {
    $membership = $this->getMembership($membershipId);
    $newEndDate = $this->addOneYearToDate($membership['end_date']);

    $params = [
      'sequential' => 1,
      'id' => $membershipId,
      'start_date' => $date,
      'end_date' => $newEndDate,
      'status_id' => $this->MEMBERSHIP_STATUS_CURRENT,
    ];
    $result = civicrm_api3('Membership', 'create', $params);
  }

  private function getMembership($membershipId) {
    $membership = civicrm_api3('Membership', 'getsingle', [
      'sequential' => 1,
      'id' => $membershipId,
    ]);

    return $membership;
  }

  private function addOneYearToDate($dateAsSting) {
    $newDate = new DateTime(substr($dateAsSting, 0, 10));
    $newDate->add(new DateInterval('P1Y'));
    return $newDate->format('Y-m-d');
  }

  private function terminateAllMembershipsButSpecified($contactId, $date, $membershipId) {
    $sql = "
      update
        civicrm_membership
      set
        end_date = %1
        , status_id = {$this->MEMBERSHIP_STATUS_CANCELLED}
      where
        (contact_id = %2 or owner_membership_id = %2)
      and
        id <> %3
    ";
    $sqlParams = [
      1 => [$date, 'Date'],
      2 => [$contactId, 'Integer'],
      3 => [$membershipId, 'Integer'],
    ];
    CRM_Core_DAO::executeQuery($sql, $sqlParams);
  }
}
