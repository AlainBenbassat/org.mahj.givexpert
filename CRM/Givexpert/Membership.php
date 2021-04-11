<?php

class CRM_Givexpert_Membership {
  private $settings;
  private $MEMBERSHIP_AMI_DUO = 2;
  private $MEMBERSHIP_AMI_COUPLE = 3;
  private $MEMBERSHIP_AMI_DONATEUR = 6;
  private $MEMBERSHIP_AMI_DONATEUR_DUO = 7;
  private $MEMBERSHIP_AMI_BIENFAITEUR = 8;
  private $MEMBERSHIP_AMI_MECENE = 9;
  private $MEMBERSHIP_CARTE_BIBLIOTHEQUE = 11;
  private $MEMBERSHIP_STATUS_NEW = 1;
  private $MEMBERSHIP_STATUS_CURRENT = 2;
  private $MEMBERSHIP_STATUS_CANCELLED = 6;
  private $RELATIONSHIP_TYPE_SPOUSE = 2;
  private $RELATIONSHIP_TYPE_FRIEND = 12;

  public $membershipGreetingMale = '';
  public $membershipGreetingFemale = '';

  public function __construct($settings) {
    $this->settings = $settings;
  }

  public function createOrUpdate($contactId, $relatedContactId, $membershipTypeId, $date) {
    // see if we have a primary membership on contact 1
    $membershipId = $this->getCurrentMembership($contactId);

    // not found on contact 1, check contact 2 (if applicable)
    if (!$membershipId && !empty($relatedContactId)) {
      $membershipId = $this->getCurrentMembership($relatedContactId);
      if ($membershipId) {
        // found, make the related contact the main contact
        $contactId = $relatedContactId;
      }
    }

    if ($membershipId) {
      $this->update($membershipId, $membershipTypeId, $date);
    }
    else {
      $this->terminateAllMembershipsButSpecified($contactId, $date, $membershipId);
      $membershipId = $this->create($contactId, $membershipTypeId, $date);
    }

    $this->fillMembershipGreeting($membershipTypeId);

    return $membershipId;
  }

  public function getMembershipRelationshipTypeId($membershipTypeId) {
    $relTypeId = 0;

    switch ($membershipTypeId) {
      case $this->MEMBERSHIP_AMI_DUO:
      case $this->MEMBERSHIP_AMI_DONATEUR_DUO:
        $relTypeId = $this->RELATIONSHIP_TYPE_FRIEND;
        break;
      case $this->MEMBERSHIP_AMI_COUPLE:
        $relTypeId = $this->RELATIONSHIP_TYPE_SPOUSE;
        break;
      default:
    }

    return $relTypeId;
  }

  private function getCurrentMembership($contactId) {
    // see if there is a membership with an end date greater than now (minus 30 days grace period, assuming people are late with renewing)
    // we ignore the status and type
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
        m.membership_type_id <> {$this->MEMBERSHIP_CARTE_BIBLIOTHEQUE}
      and
        m.owner_membership_id IS NULL
      order by
        m.id desc
    ";
    $sqlParams = [
      1 => [$contactId, 'Integer'],
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
    $joinDate = $this->getJoinDate($contactId, $date);

    $params = [
      'sequential' => 1,
      'contact_id' => $contactId,
      'membership_type_id' => $membershipTypeId,
      'join_date' => $joinDate,
      'start_date' => $date,
    ];
    $result = civicrm_api3('Membership', 'create', $params);

    return $result['values'][0]['id'];
  }

  private function update($membershipId, $membershipTypeId, $date) {
    $membership = $this->getMembership($membershipId);

    $newStartDate = CRM_Givexpert_Utils::stripTime($date);
    $newEndDate = CRM_Givexpert_Utils::addOneYearToDate($membership['end_date']);

    $params = [
      'sequential' => 1,
      'id' => $membershipId,
      'membership_type_id' => $membershipTypeId,
      'start_date' => $newStartDate,
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

  private function terminateAllMembershipsButSpecified($contactId, $date, $membershipId) {
    $dateWithoutTime = CRM_Givexpert_Utils::stripTime($date);

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
      1 => [$dateWithoutTime, 'String'],
      2 => [$contactId, 'Integer'],
      3 => [$membershipId, 'Integer'],
    ];
    CRM_Core_DAO::executeQuery($sql, $sqlParams);
  }

  private function getJoinDate($contactId, $date) {
    $dateWithoutTime = CRM_Givexpert_Utils::stripTime($date);

    // see if there is an old membership for this contact
    // return $date if not found
    $sql = "
      select
        ifnull(min(join_date), %2)
      from
        civicrm_membership
      where
        contact_id = %1
    ";
    $sqlParams = [
      1 => [$contactId, 'Integer'],
      2 => [$dateWithoutTime, 'String'],
    ];
    return CRM_Core_DAO::singleValueQuery($sql, $sqlParams);
  }

  private function fillMembershipGreeting($membershipTypeId) {
    switch ($membershipTypeId) {
      case $this->MEMBERSHIP_AMI_DONATEUR:
      case $this->MEMBERSHIP_AMI_DONATEUR_DUO:
        $this->membershipGreetingMale = 'Cher Ami donateur';
        $this->membershipGreetingFemale = 'Chère Amie donatrice';
        break;
      case $this->MEMBERSHIP_AMI_BIENFAITEUR:
        $this->membershipGreetingMale = 'Cher Ami bienfaiteur';
        $this->membershipGreetingFemale = 'Chère Amie bienfaitrice';
        break;
      case $this->MEMBERSHIP_AMI_MECENE:
        $this->membershipGreetingMale = 'Cher Ami mécène';
        $this->membershipGreetingFemale = 'Chère Amie mécène';
        break;
      default:
        $this->membershipGreetingMale = 'Cher Ami du mahJ';
        $this->membershipGreetingFemale = 'Chère Amie du mahJ';
    }
  }
}
