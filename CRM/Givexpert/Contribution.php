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
      'financial_type_id' => 1, // donation
      'payment_instrument_id' => 1, // credit card
      'custom_' . $customField['id'] => $giveXperId,
    ];

    $result = civicrm_api3('Contribution', 'create', $params);
    $contribId = $result['values'][0]['id'];

    return $contribId;
  }

  public function createMembershipContribution($membershipId, $contactId, $giveXperId, $date, $amount, $currency) {
    $customField = $this->settings->getCustomFieldIdGiveXpertId();

    $params = [
      'sequential' => 1,
      'contact_id' => $contactId,
      'receive_date' => $date,
      'total_amount' => $amount,
      'currency' => $currency,
      'financial_type_id' => 2, // membership
      'payment_instrument_id' => 1, // credit card
      'custom_' . $customField['id'] => $giveXperId,
    ];
    $result = civicrm_api3('Contribution', 'create', $params);
    $contribId = $result['values'][0]['id'];

    $params = [
      'membership_id' => $membershipId,
      'contribution_id' => $contribId,
    ];
    civicrm_api3('MembershipPayment', 'create', $params);

    return $contribId;
  }

  /*
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
  */

}
