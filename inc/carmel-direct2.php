<?php
add_filter('simply_syncCustomer', 'simply_syncCustomer_func');
function simply_syncCustomer_func($json)
{

    unset($json["CUSTNAME"]);
    $json["CTYPECODE"] = "10";
    unset($json["STATEA"]);
    return $json;

}
