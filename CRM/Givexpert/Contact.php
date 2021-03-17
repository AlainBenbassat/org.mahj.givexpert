<?php

class CRM_Givexpert_Contact {
  private $mainContactId = null;
  private $secondContactId = null;

  public function __construct($order) {
    $this->mainContactId = $this->getMainContactId($order);
    if ($this->hasSecondContact($order)) {
      $this->secondContactId = $this->getSecondContactId($order);
    }
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

    if ($order->is_orga) {
      $contactId = $this->getOrganizationId($order->contact->organism, $addressParam);

      $individualParam['employer_id'] = $contactId;
      $this->getIndividualId($individualParam, [], $emailParam, $phoneParam);
    }
    else {
      $contactId = $this->getIndividualId($individualParam, $addressParam, $emailParam, $phoneParam);
    }

    return $contactId;
  }

  private function getSecondContactId($order) {
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
      $contactId = $this->createIndividual($individualParam, $addressParam, $emailParam, $phoneParam);
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

    $address['location_type_id'] = $contact->is_orga ? 2 : 1;
    $address['street_address'] = $contact->address_1;
    $address['supplemental_address_1'] = $contact->address_2;
    $address['postal_code'] = $contact->zip_code;
    $address['city'] = $contact->city;
    $address['country_id'] = $this->getCountryId($contact->country);
    $address['sequential'] = 1;

    return $address;
  }

  private function extractIndividualAsParam($contact) {
    $individual = [];

    if ($contact->title == 'M') {
      $individual['prefix_id'] = 3;
    }
    elseif ($contact->title == 'MME' || $contact->title == 'MLLE') {
      $individual['prefix_id'] = 2;
    }

    $individual['first_name'] = $contact->firstname;
    $individual['last_name'] = $contact->lastname;

    $individual['sequential'] = 1;

    return $individual;
  }

  private function extractEmailAsParam($contact) {
    $email = [];

    $email['email'] = $contact->email;
    $email['location_type_id'] = 1;
    $email['sequential'] = 1;

    return $email;
  }

  private function extractPhoneAsParam($contact) {
    $phone = [];

    if ($contact->phone) {
      $phone['phone'] = $contact->phone;
      $phone['location_type_id'] = 1;
      $phone['phone_type_id'] = 1;
      $phone['sequential'] = 1;
    }

    return $phone;
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
}
