<?php
/**
 * Plugin Name:       Blockchain Ticketing
 * Plugin URI:        https://github.com/spntnhub/Blockchain-Ticketing-for-WordPress
 * Description:       Sell and verify NFT event tickets on Polygon. Each ticket is a unique ERC-721 token minted directly to the buyer's wallet. 3% protocol fee per sale.
 * Version:           1.0.1
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            spntn
 * Author URI:        https://spntn.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       blockchain-ticketing
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'BT_VERSION',     '1.0.1' );
define( 'BT_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'BT_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );
define( 'BT_OPTION_KEY',  'bt_settings' );

require_once BT_PLUGIN_DIR . 'includes/class-admin.php';
require_once BT_PLUGIN_DIR . 'includes/class-events.php';
require_once BT_PLUGIN_DIR . 'includes/class-tickets.php';
require_once BT_PLUGIN_DIR . 'includes/class-checkin.php';

// ─── Bootstrap ───────────────────────────────────────────────────────────────

define( 'BLOCTI_VERSION',     '1.0.1' );
define( 'BLOCTI_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'BLOCTI_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );
define( 'BLOCTI_OPTION_KEY',  'blocti_settings' );
add_action( 'admin_menu', [ 'BT_Admin', 'register_menu' ] );
require_once BLOCTI_PLUGIN_DIR . 'includes/class-admin.php';
require_once BLOCTI_PLUGIN_DIR . 'includes/class-events.php';
require_once BLOCTI_PLUGIN_DIR . 'includes/class-tickets.php';
require_once BLOCTI_PLUGIN_DIR . 'includes/class-checkin.php';
// ─── Shortcodes ───────────────────────────────────────────────────────────────
add_action( 'init', function () {
    BLOCTI_Events::register_post_type();
} );

add_action( 'admin_menu', [ 'BLOCTI_Admin', 'register_menu' ] );
add_action( 'admin_init', [ 'BLOCTI_Admin', 'register_settings' ] );
add_action( 'add_meta_boxes', [ 'BLOCTI_Events', 'add_meta_boxes' ] );
add_action( 'save_post_blocti_event', [ 'BLOCTI_Events', 'save_meta' ], 10, 2 );

add_shortcode( 'blockchain_event', [ 'BLOCTI_Tickets', 'render_shortcode' ] );
add_shortcode( 'blockchain_checkin', [ 'BLOCTI_Checkin', 'render_shortcode' ] );

add_action( 'wp_enqueue_scripts', 'blocti_enqueue_assets' );
function blocti_enqueue_assets() {
    wp_enqueue_style(
        'blocti-styles',
        BLOCTI_PLUGIN_URL . 'assets/styles.css',
        [],
        BLOCTI_VERSION
    );
    wp_enqueue_script(
        'blocti-ethers',
        BLOCTI_PLUGIN_URL . 'assets/ethers.umd.min.js',
        [],
        '6.13.2',
        true
    );
    wp_enqueue_script(
        'blocti-qrcodejs',
        BLOCTI_PLUGIN_URL . 'assets/qrcode.min.js',
        [],
        '1.0.0',
        true
    );
    wp_enqueue_script(
        'blocti-jsqr',
        BLOCTI_PLUGIN_URL . 'assets/jsQR.js',
        [],
        '1.4.0',
        true
    );
}

add_action( 'wp_ajax_bt_get_event',        [ 'BT_Tickets', 'ajax_get_event'    ] );
add_action( 'wp_ajax_nopriv_bt_get_event', [ 'BT_Tickets', 'ajax_get_event'    ] );
add_action( 'wp_ajax_bt_sign_ticket',        [ 'BT_Tickets', 'ajax_sign_ticket'  ] );
add_action( 'wp_ajax_nopriv_bt_sign_ticket', [ 'BT_Tickets', 'ajax_sign_ticket'  ] );
add_action( 'wp_ajax_bt_record_sale',        [ 'BT_Tickets', 'ajax_record_sale'  ] );
add_action( 'wp_ajax_nopriv_bt_record_sale', [ 'BT_Tickets', 'ajax_record_sale'  ] );
add_action( 'wp_ajax_bt_checkin',            [ 'BT_Checkin', 'ajax_checkin'      ] );
add_action( 'wp_ajax_nopriv_bt_checkin',     [ 'BT_Checkin', 'ajax_checkin'      ] );
