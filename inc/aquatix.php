<?php
    add_filter('simply_syncCustdes', 'syncCustdes_func');
    function syncCustdes_func ($meta) {
        // $custdes = !empty($meta['first_name'][0] . ' ' . $meta['last_name'][0]);
        $custdes = !empty($meta['first_name'][0]) ? $meta['first_name'][0] . ' ' . $meta['last_name'][0] : $meta['nickname'][0];
        return $custdes;
    };
?>