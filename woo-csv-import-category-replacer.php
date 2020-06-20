<?php
/**
 * Plugin Name:       Woo CSV Import Category Replacer
 * Plugin URI:        https://wordpress.org/plugins/woo-csv-import-category-replacer/
 * Description:       This plugin replaces substrings in a CSV Categories field to required by rules.
 * Version:           0.0.1
 * Requires at least: 5.2
 * Requires PHP:      7.2
 * Author:            Fedor Melekhin <fedormelexin@gmail.com>
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       woocicr
 */
namespace WOOCICR;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'WOOCICR_MAIN_FILE', __FILE__ );
define( 'WOOCICR_DIR', plugin_dir_path( __FILE__ ) );

require_once( WOOCICR_DIR . '/autoloader.php' );

global $woocicr;
$woocicr = new Inc\Main();
?>