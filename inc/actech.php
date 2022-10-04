<?php
add_filter('simply_personnel_url', 'simply_modify_url');
function simply_modify_url($params)
{
    $url        = $params['url_addition'];
    $bod        = $params['args']['bod'];
    $statusdate = $params['args']['statusdate'];
    $url_addition_config = $params['args']['url_addition_config'];
    // manipulate url...
    $new_url = $url_addition = 'CUSTOMERS?$filter=EMAIL ne \'\' and ' . $statusdate . ' ge ' . $bod . ' ' . $url_addition_config . '&$select=CUSTNAME,MCUSTNAME,ADDRESS,ADDRESS2,STATE,ZIP,PHONE,SPEC1,SPEC2&$expand=CUSTPLIST_SUBFORM($select=PLNAME),CUSTDISCOUNT_SUBFORM($select=PERCENT),CUSTPERSONNEL_SUBFORM($filter=PERP_ONLINEACCESS eq \'Y\';$select=NAME,EMAIL)';
    //
    $params['url_addition'] = $new_url;
    return $params;
}
?>
