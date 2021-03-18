<?php

class CRM_Givexpert_OrderSync {
  private $settings;

  public function __construct() {
    $this->settings = new CRM_Givexpert_Settings();
  }

  public function execute($params) {
    $n = 0;

    // make sure we have a param
    if ($this->isEmptyParams($params)) {
      $params = $this->getDefaultMinIdParam();
    }

    $api = new CRM_Givexpert_Api($this->settings);

    $orders = $api->getOrders($params);
    foreach ($orders as $order) {
      $this->processOrder($order);
      $n++;
    }

    return $n;
  }

  private function isEmptyParams($params) {
    if (count($params) == 0) {
      return TRUE;
    }
    else {
      return FALSE;
    }
  }

  private function getDefaultMinIdParam() {
    $customField = $this->settings->getCustomFieldIdGiveXpertId();
    $table = $customField['table_name'];
    $column = $customField['column_name'];
    $sql = "select ifnull(max($column), 0) from $table";
    $param = [
      "min_id=" . CRM_Core_DAO::singleValueQuery($sql)
    ];
    return $param;
  }

  private function processOrder($order) {
    if ($this->isOrderProcessed($order)) {
      return;
    }

    // create the contact from the order data
    $contact = new CRM_Givexpert_Contact($order);

    // process the order according to its type
    // multiple types are possible in 1 order
    if ($this->isOrderGift($order)) {
      $this->processGift($contact, $order);
    }
    if ($this->isOrderRecurringGift($order)) {
      $this->processRecurringGift($contact, $order);
    }
    if ($this->isOrderMembership($order)) {
      $this->processMembership($contact, $order);
    }
  }

  private function isOrderProcessed($order) {
    $customField = $this->settings->getCustomFieldIdGiveXpertId();
    $table = $customField['table_name'];
    $column = $customField['column_name'];

    $sql = "select count(*) from $table where $column = %1";
    $sqlParams = [
      1 => [$order->id, 'Integer'],
    ];

    $n = CRM_Core_DAO::singleValueQuery($sql, $sqlParams);
    if ($n >= 1) {
      return TRUE;
    }
    else {
      return FALSE;
    }
  }

  private function isOrderGift($order) {
    // check  for a one-time gift
    if ($order->purpose == 'D') {
      if ($order->engagement == 'S') {
        return TRUE;
      }
      else {
        return FALSE;
      }
    }

    // check if the order contains a gift
    foreach ($order->items as $item) {
      if ($item->purpose == 'D') {
        return TRUE;
      }

      if (property_exists($item, 'deductible_amount') && $item->deductible_amount > 0) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /*
   * TODO
   *
   * Aussi, concernant Stripe, je viens de faire un test de transaction régulier.
   * L'initialisation de paiement est renvoyée dans l'API avec les informations suivantes :
   * - amount : montant du don
   * - engagement : M
   * Par la suite, les récurrences auront juste l'engagement qui passe de M à R.
   */
  private function isOrderRecurringGift($order) {
    if ($order->purpose == 'D' && $order->engagement == 'R') {
      return TRUE;
    }

    return FALSE;
  }

  private function isOrderMembership($order) {
    if ($order->purpose == 'R') {
      return TRUE;
    }

    return FALSE;
  }

  private function processGift($contact, $order) {
    $contrib = new CRM_Givexpert_Contribution($this->settings);

    foreach ($order->items as $item) {
      // check for regular donation
      if ($item->purpose == 'D') {
        $contrib->createDonationContribution($contact->mainContactId, $order->id, $order->date, $item->amount, $order->currency);
      }

      // check for donation embedded in membership
      if ($item->purpose == 'R' && property_exists($item, 'deductible_amount') && $item->deductible_amount > 0) {
        $contrib->createDonationContribution($contact->mainContactId, $order->id, $order->date, $item->deductible_amount, $order->currency);
      }
    }
  }

  private function processRecurringGift($contact, $order) {

  }

  private function processMembership($contact, $order) {
    $contrib = new CRM_Givexpert_Contribution($this->settings);
    $membership = new CRM_Givexpert_Membership($this->settings);

    foreach ($order->items as $item) {
      if ($item->purpose == 'R') {
        // TODO create method "checkRelationship" and call with contact1, contact2, code
        // based on relationship_type_id, create the relationhsip

        // create the membership
        $membershipId = $membership->createOrUpdate($contact->mainContactId, $item->code, $order->date);

        // create a contribution and link it to the membership
        $contrib->createMembershipContribution($membershipId, $contact->mainContactId, $order->id, $order->date, $this->getMembershipAmount($item), $order->currency);
      }
    }
  }

  private function getMembershipAmount($item) {
    if (property_exists($item, 'deductible_amount')) {
      $amount = $item->amount - $item->deductible_amount;
    }
    else {
      $amount = $item->amount;
    }

    return $amount;
  }

}
