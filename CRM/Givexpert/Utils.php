<?php

class CRM_Givexpert_Utils {
  public static function stripTime($dateAsSting) {
    return substr($dateAsSting, 0, 10);
  }

  public static function addOneYearToDate($dateAsSting) {
    $newDate = new DateTime(CRM_Givexpert_Utils::stripTime($dateAsSting));
    $newDate->add(new DateInterval('P1Y')); // add 1 year and 2 days
    $newDate->add(new DateInterval('P2D'));
    return $newDate->format('Y-m-d');
  }
}
