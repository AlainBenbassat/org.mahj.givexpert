<?php

class CRM_Givexpert_OrderSync {
  public function execute($params) {
    $api = new CRM_Givexpert_Api();
    $orders = $api->getOrders($params);

    foreach ($orders as $order) {
      $this->processOrder($order);
    }
  }

  private function processOrder($order) {
    $contributionContactId = 0;
    $softContributionContactId = 0;

    if ($this->isOrderProcessed($order)) {
      return;
    }

    if ($this->isContactSameAsPayer($order)) {
      $contributionContactId = $this->getContactId($order, 'contact');
    }
    else {
      $contributionContactId = $this->getContactId($order, 'contact_payer');;
      $softContributionContactId = $this->getContactId($order, 'contact');;
    }

    $contributionId = $this->createContribution($order, $contributionContactId);
    if ($softContributionContactId) {
      $this->createSoftContribution($order, $contributionId, $softContributionContactId);
    }
  }

  private function isContactSameAsPayer($order) {
    if ($order->contact->email == $order->contact_payer->email
      && $order->contact->title == $order->contact_payer->title
      && $order->contact->firstname == $order->contact_payer->firstname
      && $order->contact->lastname == $order->contact_payer->lastname
      && $order->contact->organism == $order->contact_payer->organism
      && $order->contact->address_1 == $order->contact_payer->address_1
      && $order->contact->address_2 == $order->contact_payer->address_2
      && $order->contact->zip_code == $order->contact_payer->zip_code
      && $order->contact->city == $order->contact_payer->city
      && $order->contact->country == $order->contact_payer->country
      && $order->contact->phone == $order->contact_payer->phone
      && $order->contact->cell == $order->contact_payer->cell
      && $order->contact->note == $order->contact_payer->note
      && $order->contact->has_newsletter == $order->contact_payer->has_newsletter
      && $order->contact->is_anonymous == $order->contact_payer->is_anonymous
      && $order->contact->is_orga == $order->contact_payer->is_orga
      && $order->contact->birthday == $order->contact_payer->birthday
    ) {
      return TRUE;
    }

    return FALSE;
  }

  private function getContactId($order, $contactOrPayer) {
    $contactId = 0;

    if ($order->$contactOrPayer->is_orga) {
      $contactId = $this->getOrganizationId($order, $contactOrPayer);
    }
    else {
      if ($order->$contactOrPayer->title == 'MMME') {
        $contactId = $this->getHouseholdId($order, $contactOrPayer);
      }
      else {
        $contactId = $this->getIndividualId($order, $contactOrPayer);
      }
    }

    if (!$this->hasEmail($order, $contactOrPayer, $contactId)) {
      $this->addEmail($order, $contactOrPayer, $contactId);
    }

    if (!$this->hasPhone($order, $contactOrPayer, $contactId)) {
      $this->addPhone($order, $contactOrPayer, $contactId);
    }

    if (!$this->hasAddress($order, $contactOrPayer, $contactId)) {
      $this->addAddress($order, $contactOrPayer, $contactId);
    }

    // TODO
    /*
"cell": null,
"note": "",
*/
    return $contactId;
  }

  private function hasEmail($order, $contactOrPayer, $contactId) {
    if ($order->$contactOrPayer->email) {
      $sql = "select * from civicrm_email where contact_id = %1 and email = %2";
      $sqlParams = [
        1 => [$contactId, 'Integer'],
        2 => [$order->$contactOrPayer->email, 'String'],
      ];
      $dao = CRM_Core_DAO::executeQuery($sql, $sqlParams);
      if ($dao->fetch()) {
        return TRUE;
      }
    }
    else {
      return TRUE; // empty email
    }

    return FALSE;
  }

  private function addEmail($order, $contactOrPayer, $contactId) {
    $params = [
      'contact_id' => $contactId,
      'location_type_id' => $order->$contactOrPayer->is_orga ? 2 : 1,
      'email' => $order->$contactOrPayer->email,
    ];
    civicrm_api3('Email', 'create', $params);
  }

  private function hasPhone($order, $contactOrPayer, $contactId) {
    if ($order->$contactOrPayer->phone) {
      $sql = "select * from civicrm_phone where contact_id = %1 and phone = %2";
      $sqlParams = [
        1 => [$contactId, 'Integer'],
        2 => [$order->$contactOrPayer->phone, 'String'],
      ];
      $dao = CRM_Core_DAO::executeQuery($sql, $sqlParams);
      if ($dao->fetch()) {
        return TRUE;
      }
    }
    else {
      return TRUE; // empty phone
    }

    return FALSE;
  }

  private function addPhone($order, $contactOrPayer, $contactId) {
    $params = [
      'contact_id' => $contactId,
      'location_type_id' => $order->$contactOrPayer->is_orga ? 2 : 1,
      'phone_type_id' => 1,
      'phone' => $order->$contactOrPayer->phone,
    ];
    civicrm_api3('Phone', 'create', $params);
  }

  private function hasAddress($order, $contactOrPayer, $contactId) {
    if ($order->$contactOrPayer->address_1) {
      $sql = "select * from civicrm_address where contact_id = %1 and street_address = %2";
      $sqlParams = [
        1 => [$contactId, 'Integer'],
        2 => [$order->$contactOrPayer->address_1, 'String'],
      ];
      $dao = CRM_Core_DAO::executeQuery($sql, $sqlParams);
      if ($dao->fetch()) {
        return TRUE;
      }
    }
    else {
      return TRUE; // empty address
    }

    return FALSE;
  }

  private function addAddress($order, $contactOrPayer, $contactId) {
    $params = [
      'contact_id' => $contactId,
      'location_type_id' => $order->$contactOrPayer->is_orga ? 2 : 1,
      'street_address' => $order->$contactOrPayer->address_1,
      'supplemental_address_1' => $order->$contactOrPayer->address_2,
      'postal_code' => $order->$contactOrPayer->zip_code,
      'city' => $order->$contactOrPayer->city,
      'country_id' => $this->getCountryId($order->$contactOrPayer->country),
    ];
    civicrm_api3('Address', 'create', $params);
  }

  private function getCountryId($isoCode) {
    $sql = "select id from civicrm_country where iso_code = '$isoCode'";
    return CRM_Core_DAO::singleValueQuery($sql);
  }

  private function getOrganizationId($order, $contactOrPayer) {
    $params = [
      'sequential' => 1,
      'contact_type' => 'Organization',
      'organization_name' => $order->$contactOrPayer->organism,
    ];
    $result = civicrm_api3('Contact', 'get', $params);

    if ($result['count'] > 0) {
      return $result['values'][0]['id'];
    }
    else {
      // does not exist, create it
      $result = civicrm_api3('Contact', 'create', $params);
      return $result['values'][0]['id'];
    }
  }

  private function getHouseholdId($order, $contactOrPayer) {
    $houseHoldName = $order->$contactOrPayer->firstname . ' ' . $order->$contactOrPayer->lastname;

    $sql = "
      select
        c.id
      from
        civicrm_contact c
      inner join
        civicrm_email e on c.id = e.contact_id
      where
        c.household_name = %1
      and
        e.email = %2
      and
        is_deleted = 0
    ";
    $sqlParams = [
      1 => [$houseHoldName, 'String'],
      2 => [$order->$contactOrPayer->email, 'String'],
    ];

    $dao = CRM_Core_DAO::executeQuery($sql, $sqlParams);
    if ($dao->fetch()) {
      return $dao->id;
    }
    else {
      $params = [
        'sequential' => 1,
        'household_name' => $houseHoldName,
        'contact_type' => 'Household',
      ];

      $result = civicrm_api3('Contact', 'create', $params);
      return $result['values'][0]['id'];
    }
  }

  private function getIndividualId($order, $contactOrPayer) {
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
    ";
    $sqlParams = [
      1 => [$order->$contactOrPayer->firstname, 'String'],
      2 => [$order->$contactOrPayer->lastname, 'String'],
      3 => [$order->$contactOrPayer->email, 'String'],
    ];

    $dao = CRM_Core_DAO::executeQuery($sql, $sqlParams);
    if ($dao->fetch()) {
      return $dao->id;
    }
    else {
      $params = [
        'sequential' => 1,
        'first_name' => $order->$contactOrPayer->firstname,
        'last_name' => $order->$contactOrPayer->lastname,
        'contact_type' => 'Individual',
      ];

      if ($order->$contactOrPayer->title == 'M') {
        $params['prefix_id'] = 3;
      }
      elseif ($order->$contactOrPayer->title == 'MME' || $order->$contactOrPayer->title == 'MLLE') {
        $params['prefix_id'] = 2;
      }

      if ($order->$contactOrPayer->birthday) {
        $params['birth_date'] = $order->$contactOrPayer->birthday;
      }

      $result = civicrm_api3('Contact', 'create', $params);
      return $result['values'][0]['id'];
    }
  }

  private function createContribution($order, $contributionContactId) {
    $params = [
      'sequential' => 1,
      'contact_id' => $contributionContactId,
      'receive_date' => $order->date,
      'total_amount' => $order->amount,
      'currency' => $order->currency,
      'financial_type_id' => 1, // TODO check if this is always donation
      'trxn_id' => 'givexpert_' . $order->id,
    ];

    $result = civicrm_api3('Contribution', 'create', $params);
    return $result['values'][0]['id'];
  }

  private function createSoftContribution($order, $contributionId, $softContributionContactId) {
    $params = [
      'sequential' => 1,
      'contact_id' => $softContributionContactId,
      'contribution_id' => $contributionId,
      'amount' => $order->amount,
      'currency' => $order->currency,
    ];

    $result = civicrm_api3('ContributionSoft', 'create', $params);
    return $result['values'][0]['id'];
  }

  private function isOrderProcessed($order) {
    $result = civicrm_api3('Contribution', 'get', [
      'sequential' => 1,
      'receive_date' => $order->date,
      'trxn_id' => 'givexpert_' . $order->id,
    ]);

    if ($result['count'] == 0) {
      return FALSE;
    }
    else {
      return TRUE;
    }
  }
}
