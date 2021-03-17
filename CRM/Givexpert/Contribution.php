<?php

class CRM_Givexpert_Contribution {
  public static function createDonationContribution($contactId, $giveXperId, $date, $amount, $currency, $customFieldId) {
    $params = [
      'sequential' => 1,
      'contact_id' => $contactId,
      'receive_date' => $date,
      'total_amount' => $amount,
      'currency' => $currency,
      'financial_type_id' => 1, // TODO check if this is always donation
    ];

    $result = civicrm_api3('Contribution', 'create', $params);

    $params = [
      'entity_id' => $result['values'][0]['id'],
      "custom_$customFieldId" => $giveXperId,
    ];
    civicrm_api3('CustomValue', 'create', $params);

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


}
