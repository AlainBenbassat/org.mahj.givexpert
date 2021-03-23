<?php

class CRM_Givexpert_Utils {
  public static function stripTime($dateAsSting) {
    return substr($dateAsSting, 0, 10);
  }

  public static function addOneYearToDate($dateAsSting) {
    $newDate = new DateTime(CRM_Givexpert_Utils::stripTime($dateAsSting));
    $newDate->add(new DateInterval('P1Y'));
    return $newDate->format('Y-m-d');
  }
}
