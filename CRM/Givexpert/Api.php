<?php

use CRM_Givexpert_ExtensionUtil as E;

class CRM_Givexpert_Api {
  private $settings;
  private $httpClient;

  public function __construct() {
    $this->httpClient = new CRM_Utils_HttpClient();
    $this->settings = new CRM_Givexpert_Settings();
  }

  public function getOrders($params) {
    $response = $this->sendRequest('orders', $params);
    return $response->orders;
  }

  private function sendRequest($apiFunc, $apiParams)
  {
    // generate uri
    $username = $this->settings->getUsername();
    $token = $this->settings->getToken();
    $url = $this->settings->getApiEndpoint() . "/$apiFunc?user=$username&key=$token";

    // add params (if needed)
    if (count($apiParams)) {
      $url .= '&' . implode('&', $apiParams);
    }

    // execute
    list($status, $response) = $this->httpClient->get($url);

    // return processed response
    return $this->processResponse($status, $response);
  }

  private function processResponse($status, $response) {
    if ($status == 'ok') {
      $decodedResponse = json_decode($response);

      if (!$this->isResponseValid($decodedResponse)) {
        throw new Exception(E::ts('GiveXpert API Request failed: response is empty or not a valid object'));
      }

      if ($decodedResponse->statut != 'success') {
        throw new Exception("GiveXpert API Request failed: statut = " . $decodedResponse->statut . ", httpcode = " . $decodedResponse->httpcode . ", message = " . $decodedResponse->message);
      }
    }
    else {
      throw new Exception("GiveXpert API Request failed: status = $status");
    }

    return $decodedResponse;
  }

  private function isResponseValid($decodedResponse) {
    if ($decodedResponse == NULL) {
      return FALSE;
    }

    if (!is_object($decodedResponse)) {
      return FALSE;
    }

    if (!property_exists($decodedResponse, 'statut')) {
      return FALSE;
    }

    if (!property_exists($decodedResponse, 'httpcode')) {
      return FALSE;
    }

    return TRUE;
  }

  private function isResponseStatusOK($decodedResponse) {
    if (is_object($decodedResponse) && property_exists($decodedResponse, 'statut')) {
      if ($decodedResponse->statut == 'success') {}
    }
  }


}
