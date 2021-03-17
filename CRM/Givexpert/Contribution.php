<?php

class CRM_Givexpert_Contribution {
  private $settings;

  public function __construct($settings) {
    $this->settings = $settings;
  }

  public function createDonationContribution($contactId, $giveXperId, $date, $amount, $currency) {
    $customField = $this->settings->getCustomFieldIdGiveXpertId();

    $params = [
      'sequential' => 1,
      'contact_id' => $contactId,
      'receive_date' => $date,
      'total_amount' => $amount,
      'currency' => $currency,
      'financial_type_id' => 1, // TODO check if this is always donation
      'custom_' . $customField['id'] => $giveXperId,
    ];

    $result = civicrm_api3('Contribution', 'create', $params);
    $contribId = $result['values'][0]['id'];

    return $contribId;
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

  private function saveGiveXpertId($contribId, $giveXperId) {

  }
}
