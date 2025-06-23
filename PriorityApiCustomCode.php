<?php
/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the
 * plugin admin area. This file also defines a function that starts the plugin.
 * Plugin Name:       Priority API Helper
 * Plugin URI:        https://simplyct.co.il
 * Description:       Custom Code for Priority WooCommerce API plugin. This plugin let the developer manipulate standard requests to and from Priority API, you must define constant in the Priority WooCommerce plugin with the same name of the client and correspond to the file name.
 * Version:           1.5.9.6
 * Author:            Roy BenMenachem
 * Author URI:        https://simplyct.co.il
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 */
// If this file is called directly, abort.
use PriorityWoocommerceAPI\WooAPI;
if ( ! defined( 'WPINC' ) ) {
    die;
}
// check for PriorityAPI
include_once(ABSPATH . 'wp-admin/includes/plugin.php');
if (is_plugin_active('WooCommercePriorityAPI/priority-woo-api.php')) {

} else {
    add_action('admin_notices', function () {
        printf('<div class="notice notice-error"><p>%s</p></div>', __('In order to use Priority Custom API extension, Priority WooCommerce API must be activated', 'p18a'));
    });

}
// const
define('SIMPLY_CUSTOM_PLUGIN_PATH',plugin_dir_url( __FILE__ ));
// Include the dependencies needed to instantiate the plugin.
// need to set config value of customcodefilename to the file name
add_action('init',function(){
    if (is_plugin_active('WooCommercePriorityAPI/priority-woo-api.php')&&is_plugin_active('PriorityAPI/priority18-api.php')) {
        $config = json_decode(stripslashes(WooAPI::instance()->option('setting-config')));
        $custom_filename = $config->customcodefilename;
        foreach (glob(plugin_dir_path(__FILE__) . 'inc/*.php') as $file) {
            $basename = explode('.', basename($file, ''))[0];
            if ($basename == $custom_filename) {
                include_once $file;
            }
        }
    }
});

