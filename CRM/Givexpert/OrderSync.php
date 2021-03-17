<?php

class CRM_Givexpert_OrderSync {
  private $settings;

  public function __construct() {
    $this->settings = new CRM_Givexpert_Settings();
  }

  public function execute($params) {
    // make sure we have a param
    if ($this->isEmptyParams($params)) {
      $params = $this->getDefaultMinIdParam();
    }

    $api = new CRM_Givexpert_Api();
    $orders = $api->getOrders($params);

    foreach ($orders as $order) {
      $this->processOrder($order);
    }
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

      if (property_exists($item, 'deductable_amount') && $item->deductable_amount > 0) {
        return TRUE;
      }
    }

    return FALSE;
  }

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
    foreach ($order->items as $item) {
      if ($item->purpose == 'D') {
        CRM_Givexpert_Contribution::createDonationContribution($contact->mainContactId, $order->id, $order->date, $order->amount, $order->currency, $this->settings->getCustomFieldIdGiveXpertId());
      }
    }
  }

  private function processRecurringGift($contact, $order) {

  }

  private function processMembership($contact, $order) {

  }

}
