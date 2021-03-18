<?php
use CRM_Givexpert_ExtensionUtil as E;

function _civicrm_api3_givexpert_Syncorders_spec(&$spec) {
  $spec['from']['api.required'] = 0;
  $spec['to']['api.required'] = 0;
  $spec['id']['api.required'] = 0;
  $spec['min_id']['api.required'] = 0;
}

function civicrm_api3_givexpert_Syncorders($params) {
  try {
    $orderSynchronizer = new CRM_Givexpert_OrderSync();
    $convertedParams = givexpert_convertParams($params);
    $n = $orderSynchronizer->execute($convertedParams);

    $returnValues = "Nombre de dons / adhésions: $n";
    return civicrm_api3_create_success($returnValues, $params, 'Givexpert', 'Syncorders');
  }
  catch (Exception $e)  {
    throw new API_Exception('Error in ' . $e->getFile() . ' on line ' . $e->getLine() . ': ' . $e->getMessage(), $e->getCode());
  }
}

function givexpert_convertParams($params) {
  $paramArr = [];

  $from = CRM_Utils_Array::value('from', $params);
  if ($from) {
    $paramArr[] = "from=$from";
  }

  $to = CRM_Utils_Array::value('to', $params);
  if ($to) {
    $paramArr[] = "to=$to";
  }

  $id = CRM_Utils_Array::value('id', $params);
  if ($id) {
    $paramArr[] = "id=$id";
  }

  $min_id = CRM_Utils_Array::value('min_id', $params);
  if ($min_id) {
    $paramArr[] = "min_id=$min_id";
  }

  return $paramArr;
}



