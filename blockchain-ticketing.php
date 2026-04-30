<?php
/**
 * Plugin Name:       SPNTN NFT Ticketing
 * Plugin URI:        https://github.com/spntnhub/spntn-nft-ticketing
 * Description:       Sell and verify NFT event tickets on Polygon. Each ticket is a unique ERC-721 token minted directly to the buyer's wallet. 3% protocol fee per sale.
 * Version:           1.0.3
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            spntn
 * Author URI:        https://spntn.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       spntn-nft-ticketing
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'BT_VERSION', '1.0.3' );
define( 'BT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'BT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'BT_OPTION_KEY', 'spntn_nft_settings' );

require_once BT_PLUGIN_DIR . 'includes/class-admin.php';
require_once BT_PLUGIN_DIR . 'includes/class-events.php';
require_once BT_PLUGIN_DIR . 'includes/class-tickets.php';
require_once BT_PLUGIN_DIR . 'includes/class-checkin.php';

add_action(
    'init',
    static function () {
        BT_Events::register_post_type();
    }
);

add_action( 'admin_menu', [ 'BT_Admin', 'register_menu' ] );
add_action( 'admin_init', [ 'BT_Admin', 'register_settings' ] );
add_action( 'admin_enqueue_scripts', [ 'BT_Admin', 'enqueue_scripts' ] );
add_action( 'add_meta_boxes', [ 'BT_Events', 'add_meta_boxes' ] );
add_action( 'save_post_bt_event', [ 'BT_Events', 'save_meta' ], 10, 2 );
add_action( 'admin_enqueue_scripts', [ 'BT_Events', 'enqueue_scripts' ] );

// Register both names to keep backward compatibility for existing pages.
add_shortcode( 'blockchain_event', [ 'BT_Tickets', 'render_shortcode' ] );
add_shortcode( 'spntn_nft_event', [ 'BT_Tickets', 'render_shortcode' ] );
add_shortcode( 'blockchain_checkin', [ 'BT_Checkin', 'render_shortcode' ] );
add_shortcode( 'spntn_nft_checkin', [ 'BT_Checkin', 'render_shortcode' ] );

add_action( 'wp_enqueue_scripts', 'bt_enqueue_assets' );
function bt_enqueue_assets(): void {
    wp_enqueue_style(
        'bt-styles',
        BT_PLUGIN_URL . 'assets/styles.css',
        [],
        BT_VERSION
    );

    wp_enqueue_script(
        'bt-ethers',
        BT_PLUGIN_URL . 'assets/ethers.umd.min.js',
        [],
        '6.13.2',
        true
    );
    wp_enqueue_script(
        'bt-qrcodejs',
        BT_PLUGIN_URL . 'assets/qrcode.min.js',
        [],
        '1.0.0',
        true
    );
    wp_enqueue_script(
        'bt-jsqr',
        BT_PLUGIN_URL . 'assets/jsQR.min.js',
        [],
        '1.4.0',
        true
    );
    wp_enqueue_script(
        'bt-wallet',
        BT_PLUGIN_URL . 'assets/wallet.js',
        [ 'bt-ethers' ],
        BT_VERSION,
        true
    );
    wp_enqueue_script(
        'bt-ticket',
        BT_PLUGIN_URL . 'assets/ticket.js',
        [ 'jquery', 'bt-wallet', 'bt-qrcodejs' ],
        BT_VERSION,
        true
    );
    wp_enqueue_script(
        'bt-checkin',
        BT_PLUGIN_URL . 'assets/checkin.js',
        [ 'jquery', 'bt-jsqr' ],
        BT_VERSION,
        true
    );

    $ajax_payload = [
        'url'   => admin_url( 'admin-ajax.php' ),
        'nonce' => wp_create_nonce( 'bt_nonce' ),
    ];

    wp_localize_script( 'bt-ticket', 'bt_ajax', $ajax_payload );
    wp_localize_script( 'bt-checkin', 'bt_checkin_ajax', $ajax_payload );
}

add_action( 'wp_ajax_bt_get_event', [ 'BT_Tickets', 'ajax_get_event' ] );
add_action( 'wp_ajax_nopriv_bt_get_event', [ 'BT_Tickets', 'ajax_get_event' ] );
add_action( 'wp_ajax_bt_sign_ticket', [ 'BT_Tickets', 'ajax_sign_ticket' ] );
add_action( 'wp_ajax_nopriv_bt_sign_ticket', [ 'BT_Tickets', 'ajax_sign_ticket' ] );
add_action( 'wp_ajax_bt_record_sale', [ 'BT_Tickets', 'ajax_record_sale' ] );
add_action( 'wp_ajax_nopriv_bt_record_sale', [ 'BT_Tickets', 'ajax_record_sale' ] );
add_action( 'wp_ajax_bt_checkin', [ 'BT_Checkin', 'ajax_checkin' ] );
add_action( 'wp_ajax_nopriv_bt_checkin', [ 'BT_Checkin', 'ajax_checkin' ] );

// Legacy action aliases for older JS versions already in the wild.
add_action( 'wp_ajax_spntn_nft_get_event', [ 'BT_Tickets', 'ajax_get_event' ] );
add_action( 'wp_ajax_nopriv_spntn_nft_get_event', [ 'BT_Tickets', 'ajax_get_event' ] );
add_action( 'wp_ajax_spntn_nft_sign_ticket', [ 'BT_Tickets', 'ajax_sign_ticket' ] );
add_action( 'wp_ajax_nopriv_spntn_nft_sign_ticket', [ 'BT_Tickets', 'ajax_sign_ticket' ] );
add_action( 'wp_ajax_spntn_nft_record_sale', [ 'BT_Tickets', 'ajax_record_sale' ] );
add_action( 'wp_ajax_nopriv_spntn_nft_record_sale', [ 'BT_Tickets', 'ajax_record_sale' ] );
add_action( 'wp_ajax_spntn_nft_checkin', [ 'BT_Checkin', 'ajax_checkin' ] );
add_action( 'wp_ajax_nopriv_spntn_nft_checkin', [ 'BT_Checkin', 'ajax_checkin' ] );
