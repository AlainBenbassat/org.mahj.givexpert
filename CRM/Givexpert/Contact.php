<?php

class CRM_Givexpert_Contact {
  private $NEWSLETTER_GROUP_ID = 10;
  private $LOCATION_TYPE_ID_MAIN = 3;
  private $PREFIX_ID_MR = 3;
  private $PREFIX_ID_MS = 1;

  public $mainContactId = null;
  public $secondContactId = null;

  public function __construct($order) {
    $this->mainContactId = $this->getMainContactId($order);
    if ($this->hasSecondContact($order)) {
      $this->secondContactId = $this->getSecondContactId($order);
    }
  }

  public function createRelationshipIfNotExists($contact1, $contact2, $relationshipTypeid) {
    if (!$this->hasRelationship($contact1, $contact2, $relationshipTypeid)) {
      $this->createRelationship($contact1, $contact2, $relationshipTypeid);
    }
  }

  public function updateGreetings($membershipGreetingMale, $membershipGreetingFemale) {
    $this->updateContactGreetings($this->mainContactId, $membershipGreetingMale, $membershipGreetingFemale);
    if ($this->secondContactId) {
      $this->updateContactGreetings($this->secondContactId, $membershipGreetingMale, $membershipGreetingFemale);
    }
  }

  private function updateContactGreetings($contactId, $membershipGreetingMale, $membershipGreetingFemale) {
    $gender = $this->getGender($contactId);
    $greeting = ($gender == 'female') ? $membershipGreetingFemale : $membershipGreetingMale;

    $params = [
      'id' => $contactId,
      'email_greeting_id' => 4, // custom
      'postal_greeting_id' => 4, // custom
      'email_greeting_custom' => $greeting,
      'postal_greeting_custom' => $greeting,
    ];
    civicrm_api3('Contact', 'create', $params);
  }

  private function hasRelationship($contact1, $contact2, $relationshipTypeId) {
    $sql = "
      select
        id
      from
        civicrm_relationship
      where
        relationship_type_id = %3
      and
      (
        (contact_id_a = %1 and contact_id_b = %2)
      or
        (contact_id_a = %2 and contact_id_b = %1)
      )
      and
        is_active = 1
    ";
    $sqlParams = [
      1 => [$contact1, 'Integer'],
      2 => [$contact2, 'Integer'],
      3 => [$relationshipTypeId, 'Integer'],
    ];
    $dao = CRM_Core_DAO::executeQuery($sql, $sqlParams);
    if ($dao->fetch()) {
      return TRUE;
    }
    else {
      return FALSE;
    }
  }

  private function createRelationship($contact1, $contact2, $relationshipTypeid) {
    $params = [
      'contact_id_a' => $contact1,
      'contact_id_b' => $contact2,
      'relationship_type_id' => $relationshipTypeid,
      'start_date' => date('Y-m-d'),
      'is_active' => 1,
    ];
    civicrm_api3('Relationship', 'create', $params);
  }

  private function hasSecondContact($order) {
    if ($this->isSecondContactEmpty($order)) {
      return FALSE;
    }

    if ($this->isSecondContactIdentical($order)) {
      return FALSE;
    }

    return TRUE;
  }

  private function isSecondContactEmpty($order) {
    if (strlen($order->contact_payer->email) == 0
      && strlen($order->contact_payer->firstname) == 0
      && strlen($order->contact_payer->lastname) == 0
      && strlen($order->contact_payer->organism) == 0
    ) {
      return TRUE;
    }

    return FALSE;
  }

  private function isSecondContactIdentical($order) {
    if ($order->contact->email == $order->contact_payer->email
      && $order->contact->firstname == $order->contact_payer->firstname
      && $order->contact->lastname == $order->contact_payer->lastname
      && $order->contact->organism == $order->contact_payer->organism
    ) {
      return TRUE;
    }

    return FALSE;
  }

  private function getMainContactId($order) {
    $contactId = 0;

    $addressParam = $this->extractAddressAsParam($order->contact);
    $individualParam = $this->extractIndividualAsParam($order->contact);
    $emailParam = $this->extractEmailAsParam($order->contact);
    $phoneParam = $this->extractPhoneAsParam($order->contact);

    if ($order->contact->is_orga) {
      $contactId = $this->getOrganizationId($order->contact->organism, $addressParam);

      $individualParam['employer_id'] = $contactId;
      $this->getIndividualId($individualParam, [], $emailParam, $phoneParam);
    }
    else {
      $contactId = $this->getIndividualId($individualParam, $addressParam, $emailParam, $phoneParam);
    }

    If ($order->contact->has_newsletter) {
      $this->addToNewsletterGroup($contactId);
    }

    return $contactId;
  }

  private function getSecondContactId($order) {
    $contactId = 0;

    $addressParam = $this->extractAddressAsParam($order->contact_payer);
    $individualParam = $this->extractIndividualAsParam($order->contact_payer);
    $emailParam = $this->extractEmailAsParam($order->contact_payer);
    $phoneParam = $this->extractPhoneAsParam($order->contact_payer);

    $contactId = $this->getIndividualId($individualParam, $addressParam, $emailParam, $phoneParam);

    return $contactId;
  }

  private function getOrganizationId($name, $addressParam) {
    $contactId = $this->findOrganizationIdByName($name);
    if ($contactId == 0) {
      $contactId = $this->createOrganization($name, $addressParam);
    }

    return $contactId;
  }

  private function getIndividualId($individualParam, $addressParam, $emailParam, $phoneParam) {
    $contactId = $this->findIndividualIdByNameAndEmail($individualParam, $emailParam);
    if ($contactId == 0) {
      $contactId = $this->findIndividualIdByNameAndEmailReversed($individualParam, $emailParam);
    }

    if ($contactId == 0) {
      $contactId = $this->createIndividual($individualParam, $addressParam, $emailParam, $phoneParam);
    }
    else {
      $this->createOrUpdateAddress($contactId, $addressParam);
    }

    return $contactId;
  }

  private function findOrganizationIdByName($name) {
    $sql = "select id from civicrm_contact where organization_name = %1 and is_deleted = 0 and contact_type = 'Organization'";
    $sqlParams = [
      1 => [$name, 'String'],
    ];
    $dao = CRM_Core_DAO::executeQuery($sql, $sqlParams);
    if ($dao->fetch()) {
      return $dao->id;
    }

    return 0;
  }

  private function createOrganization($name, $addressParam) {
    $params = [
      'contact_type' => 'Organization',
      'organization_name' => $name,
      'sequential' => 1,
    ];
    $contact = civicrm_api3('Contact', 'create', $params);
    $contactId = $contact['values'][0]['id'];

    $this->createAddress($contactId, $addressParam);

    return $contactId;
  }

  private function findIndividualIdByNameAndEmail($individualParam, $emailParam) {
    $sql = "
      select
        c.id
      from
        civicrm_contact c
      inner join
        civicrm_email e on c.id = e.contact_id
      where
        c.first_name = %1
      and
        c.last_name = %2
      and
        e.email = %3
      and
        is_deleted = 0
      and
        contact_type = 'Individual'
    ";
    $sqlParams = [
      1 => [$individualParam['first_name'], 'String'],
      2 => [$individualParam['last_name'], 'String'],
      3 => [$emailParam['email'], 'String'],
    ];

    $dao = CRM_Core_DAO::executeQuery($sql, $sqlParams);
    if ($dao->fetch()) {
      return $dao->id;
    }

    return 0;
  }

  private function findIndividualIdByNameAndEmailReversed($individualParam, $emailParam) {
    $sql = "
      select
        c.id
      from
        civicrm_contact c
      inner join
        civicrm_email e on c.id = e.contact_id
      where
        c.first_name = %1
      and
        c.last_name = %2
      and
        e.email = %3
      and
        is_deleted = 0
      and
        contact_type = 'Individual'
    ";
    $sqlParams = [
      1 => [$individualParam['last_name'], 'String'],
      2 => [$individualParam['first_name'], 'String'],
      3 => [$emailParam['email'], 'String'],
    ];

    $dao = CRM_Core_DAO::executeQuery($sql, $sqlParams);
    if ($dao->fetch()) {
      return $dao->id;
    }

    return 0;
  }

  private function createIndividual($individualParam, $addressParam, $emailParam, $phoneParam) {
    $contact = civicrm_api3('Contact', 'create', $individualParam);
    $contactId = $contact['values'][0]['id'];

    $this->createAddress($contactId, $addressParam);
    $this->createEmail($contactId, $emailParam);
    $this->createPhone($contactId, $phoneParam);

    return $contactId;
  }

  private function extractAddressAsParam($contact) {
    $address = [];

    $address['location_type_id'] = $this->LOCATION_TYPE_ID_MAIN;
    $address['street_address'] = $contact->address_1;
    $address['supplemental_address_1'] = $contact->address_2;
    $address['supplemental_address_2'] = '';
    $address['postal_code'] = $contact->zip_code;
    $address['city'] = $contact->city;
    $address['country_id'] = $this->getCountryId($contact->country);
    $address['sequential'] = 1;

    return $address;
  }

  private function extractIndividualAsParam($contact) {
    $individual = [];

    $individual['contact_type'] = 'Individual';

    if ($contact->title == 'M') {
      $individual['prefix_id'] = $this->PREFIX_ID_MR;
    }
    elseif ($contact->title == 'MME' || $contact->title == 'MLLE') {
      $individual['prefix_id'] = $this->PREFIX_ID_MS;
    }

    $individual['first_name'] = $contact->firstname;
    $individual['last_name'] = $contact->lastname;

    $individual['sequential'] = 1;

    return $individual;
  }

  private function extractEmailAsParam($contact) {
    $email = [];

    $email['email'] = $contact->email;
    $email['location_type_id'] = $this->LOCATION_TYPE_ID_MAIN;
    $email['sequential'] = 1;

    return $email;
  }

  private function extractPhoneAsParam($contact) {
    $phone = [];

    if ($contact->phone) {
      $phone['phone'] = $contact->phone;
      $phone['location_type_id'] = $this->LOCATION_TYPE_ID_MAIN;
      $phone['phone_type_id'] = 1;
      $phone['sequential'] = 1;
    }

    return $phone;
  }

  private function addToNewsletterGroup($contactId) {
    $params = [
      'sequential' => 1,
      'contact_id' => $contactId,
      'group_id' => $this->NEWSLETTER_GROUP_ID,
      'status' => 'Added',
    ];
    civicrm_api3('GroupContact', 'create', $params);
  }

  private function getCountryId($isoCode) {
    $sql = "select id from civicrm_country where iso_code = '$isoCode'";
    return CRM_Core_DAO::singleValueQuery($sql);
  }

  private function createAddress($contactId, $addressParam) {
    if ($addressParam) {
      $addressParam['contact_id'] = $contactId;
      civicrm_api3('Address', 'create', $addressParam);
    }
  }

  private function createOrUpdateAddress($contactId, $addressParam) {
    if ($addressParam) {
      // if the contact has a primary address, add the address id so the create will perform an update
      $addressId = $this->getPrimaryAddressId($contactId);
      if ($addressId) {
        $addressParam['id'] = $addressId;
      }
      $this->createAddress($contactId, $addressParam);
    }
  }

  private function getPrimaryAddressId($contactId) {
    $sql = "select id from civicrm_address where contact_id = $contactId and is_primary = 1";
    return CRM_Core_DAO::singleValueQuery($sql);
  }

  private function createEmail($contactId, $emailParam) {
    if ($emailParam) {
      $emailParam['contact_id'] = $contactId;
      civicrm_api3('Email', 'create', $emailParam);
    }
  }

  private function createPhone($contactId, $phoneParam) {
    if ($phoneParam) {
      $phoneParam['contact_id'] = $contactId;
      civicrm_api3('Phone', 'create', $phoneParam);
    }
  }

  private function getGender($contactId) {
    $prefixId = CRM_Core_DAO::singleValueQuery("select prefix_id from civicrm_contact where id = $contactId");
    if ($prefixId == $this->PREFIX_ID_MR) {
      return 'male';
    }
    elseif ($prefixId == $this->PREFIX_ID_MS) {
      return 'female';
    }
    else {
      return 'other';
    }
  }
}
