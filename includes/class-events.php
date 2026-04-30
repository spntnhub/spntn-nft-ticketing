<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Convert human-readable price to on-chain smallest unit.
 *  - Native token (POL/ETH): 18 decimals (wei)
 *  - ERC-20 (USDC): 6 decimals
 */
function bt_to_wei( string $amount, string $currency ): string {
    if ( '' === $amount || '0' === $amount ) return '0';
    $decimals = ( 'ERC20' === $currency ) ? 6 : 18;
    if ( function_exists( 'bcmul' ) ) {
        return bcmul( $amount, bcpow( '10', (string) $decimals ), 0 );
    }
    $parts   = explode( '.', $amount );
    $integer = $parts[0] ?? '0';
    $decimal = isset( $parts[1] ) ? substr( $parts[1], 0, $decimals ) : '';
    $decimal = str_pad( $decimal, $decimals, '0', STR_PAD_RIGHT );
    return ltrim( $integer . $decimal, '0' ) ?: '0';
}

/**
 * Convert on-chain smallest unit back to human-readable price.
 */
function bt_from_wei( string $amount, string $currency ): string {
    if ( '' === $amount || '0' === $amount ) return '';
    $decimals = ( 'ERC20' === $currency ) ? 6 : 18;
    if ( function_exists( 'bcdiv' ) ) {
        $result = bcdiv( $amount, bcpow( '10', (string) $decimals ), $decimals );
        return rtrim( rtrim( $result, '0' ), '.' );
    }
    $padded  = str_pad( $amount, $decimals + 1, '0', STR_PAD_LEFT );
    $integer = ltrim( substr( $padded, 0, -$decimals ), '0' ) ?: '0';
    $decimal = rtrim( substr( $padded, -$decimals ), '0' );
    return $decimal ? "$integer.$decimal" : $integer;
}

/**
 * Validate an Ethereum address (0x + 40 hex chars).
 */
function bt_is_eth_address( string $address ): bool {
    return 1 === preg_match( '/^0x[a-fA-F0-9]{40}$/', $address );
}

class BT_Events {

    // ─── Admin script enqueue ─────────────────────────────────────────────────

    public static function enqueue_scripts( string $hook ): void {
        if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) {
            return;
        }
        $screen = get_current_screen();
        if ( ! $screen || 'bt_event' !== $screen->post_type ) {
            return;
        }
        wp_register_script( 'bt-admin-events', false, [], BT_VERSION, true );
        wp_enqueue_script( 'bt-admin-events' );
        wp_add_inline_script( 'bt-admin-events', self::meta_box_inline_js() );
    }

    private static function meta_box_inline_js(): string {
        return <<<'JS'
(function() {
    var USDC_BY_CHAIN = {
        polygon:  '0x3c499c542cEF5E3811e1192ce70d8cC03d5c3359',
        base:     '0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913',
        arbitrum: '0xaf88d065e77c8cC2239327C5EDb3A432268e5831',
        optimism: '0x0b2C639c533813f4Aa9D7837CAf62653d097Ff85'
    };
    var BT_NATIVE_SYMBOL = { polygon: 'POL', base: 'ETH', arbitrum: 'ETH', optimism: 'ETH' };

    function updatePriceUnit() {
        var currency = document.getElementById('bt_currency').value;
        var chain    = document.getElementById('bt_chain').value;
        document.getElementById('bt_price_unit').textContent =
            currency === 'ERC20' ? 'USDC' : (BT_NATIVE_SYMBOL[chain] || 'POL');
    }

    document.getElementById('bt_currency').addEventListener('change', function() {
        document.getElementById('bt_token_row').style.display = this.value === 'ERC20' ? '' : 'none';
        if (this.value === 'ERC20') {
            var chain = document.getElementById('bt_chain').value;
            var field = document.getElementById('bt_payment_token');
            if (!field.value && USDC_BY_CHAIN[chain]) {
                field.value = USDC_BY_CHAIN[chain];
            }
        }
        updatePriceUnit();
    });

    document.getElementById('bt_chain').addEventListener('change', function() {
        var nativeLabels = { polygon: 'POL (native)', base: 'ETH (native)', arbitrum: 'ETH (native)', optimism: 'ETH (native)' };
        document.querySelector('#bt_currency option[value="POL"]').textContent = nativeLabels[this.value] || 'POL (native)';
        if (document.getElementById('bt_currency').value === 'ERC20' && USDC_BY_CHAIN[this.value]) {
            document.getElementById('bt_payment_token').value = USDC_BY_CHAIN[this.value];
        }
        updatePriceUnit();
    });
})();
JS;
    }

    // ─── Register custom post type ────────────────────────────────────────────

    public static function register_post_type(): void {
        register_post_type( 'bt_event', [
            'labels' => [
                'name'          => __( 'Events',     'spntn-nft-ticketing' ),
                'singular_name' => __( 'Event',      'spntn-nft-ticketing' ),
                'add_new_item'  => __( 'Add New Event', 'spntn-nft-ticketing' ),
                'edit_item'     => __( 'Edit Event',    'spntn-nft-ticketing' ),
            ],
            'public'             => true,
            'show_in_menu'       => true,
            'menu_icon'          => 'dashicons-tickets-alt',
            'supports'           => [ 'title', 'editor', 'thumbnail' ],
            'has_archive'        => true,
            'rewrite'            => [ 'slug' => 'event' ],
            'show_in_rest'       => true,
        ] );
    }

    // ─── Meta boxes ───────────────────────────────────────────────────────────

    public static function add_meta_boxes(): void {
        add_meta_box(
            'bt_event_details',
            __( 'Ticket Details', 'spntn-nft-ticketing' ),
            [ __CLASS__, 'render_meta_box' ],
            'bt_event',
            'normal',
            'high'
        );

        add_meta_box(
            'bt_event_sync',
            __( 'Blockchain Sync', 'spntn-nft-ticketing' ),
            [ __CLASS__, 'render_sync_box' ],
            'bt_event',
            'side',
            'high'
        );
    }

    public static function render_meta_box( WP_Post $post ): void {
        wp_nonce_field( 'bt_save_event', 'bt_event_nonce' );
        $opts = get_option( BT_OPTION_KEY, [] );

        $date             = get_post_meta( $post->ID, '_bt_date',             true );
        $location         = get_post_meta( $post->ID, '_bt_location',         true );
        $total_supply     = get_post_meta( $post->ID, '_bt_total_supply',     true );
        $price            = get_post_meta( $post->ID, '_bt_price',            true );
        $currency         = get_post_meta( $post->ID, '_bt_currency',         true ) ?: 'POL';
        $payment_token    = get_post_meta( $post->ID, '_bt_payment_token',    true );
        $organizer_wallet = get_post_meta( $post->ID, '_bt_organizer_wallet', true ) ?: ( $opts['organizer_wallet'] ?? '' );
        $contract_address = get_post_meta( $post->ID, '_bt_contract_address', true ) ?: ( $opts['contract_address'] ?? '' );
        $chain            = get_post_meta( $post->ID, '_bt_chain',             true ) ?: ( $opts['chain']             ?? 'polygon' );
        $chain_symbols    = [ 'polygon' => 'POL', 'base' => 'ETH', 'arbitrum' => 'ETH', 'optimism' => 'ETH' ];
        $display_price    = bt_from_wei( $price ?: '', $currency );
        $price_unit       = 'ERC20' === $currency ? 'USDC' : ( $chain_symbols[ $chain ] ?? 'POL' );
        $allowed_chains   = [
            'polygon'  => 'Polygon (POL)',
            'base'     => 'Base (ETH)',
            'arbitrum' => 'Arbitrum One (ETH)',
            'optimism' => 'Optimism (ETH)',
        ];        ?>
        <table class="form-table" style="margin-top:0;">
            <tr>
                <th><label for="bt_date"><?php esc_html_e( 'Event Date & Time', 'spntn-nft-ticketing' ); ?></label></th>
                <td><input type="datetime-local" id="bt_date" name="bt_date" value="<?php echo esc_attr( $date ? date( 'Y-m-d\TH:i', strtotime( $date ) ) : '' ); ?>" required /></td>
            </tr>
            <tr>
                <th><label for="bt_location"><?php esc_html_e( 'Location', 'spntn-nft-ticketing' ); ?></label></th>
                <td><input type="text" id="bt_location" name="bt_location" value="<?php echo esc_attr( $location ); ?>" class="regular-text" placeholder="City, Venue" /></td>
            </tr>
            <tr>
                <th><label for="bt_price"><?php esc_html_e( 'Ticket Price', 'spntn-nft-ticketing' ); ?></label></th>
                <td>
                    <div style="display:flex; align-items:center; gap:8px;">
                        <input type="number" id="bt_price" name="bt_price" value="<?php echo esc_attr( $display_price ); ?>"
                               step="any" min="0" style="width:140px;" placeholder="0" />
                        <strong id="bt_price_unit"><?php echo esc_html( $price_unit ); ?></strong>
                    </div>
                    <p class="description"><?php esc_html_e( 'Enter in full units — e.g. 5 for 5 POL, or 10 for 10 USDC.', 'spntn-nft-ticketing' ); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="bt_currency"><?php esc_html_e( 'Currency', 'spntn-nft-ticketing' ); ?></label></th>
                <td>
                    <select id="bt_currency" name="bt_currency">
                        <option value="POL"   <?php selected( $currency, 'POL'   ); ?>>POL (native)</option>
                        <option value="ERC20" <?php selected( $currency, 'ERC20' ); ?>>ERC-20 (USDC, etc.)</option>
                    </select>
                </td>
            </tr>
            <tr id="bt_token_row" style="<?php echo $currency !== 'ERC20' ? 'display:none;' : ''; ?>">
                <th><label for="bt_payment_token"><?php esc_html_e( 'ERC-20 Token Address', 'spntn-nft-ticketing' ); ?></label></th>
                <td>
                    <input type="text" id="bt_payment_token" name="bt_payment_token" value="<?php echo esc_attr( $payment_token ); ?>" class="regular-text" placeholder="0x3c499c... (USDC)" />
                    <p class="description"><?php esc_html_e( 'Auto-filled with USDC when you switch to ERC-20 currency. You can change to any ERC-20 token.', 'spntn-nft-ticketing' ); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="bt_total_supply"><?php esc_html_e( 'Total Supply', 'spntn-nft-ticketing' ); ?></label></th>
                <td>
                    <input type="number" id="bt_total_supply" name="bt_total_supply" value="<?php echo esc_attr( $total_supply ?: '0' ); ?>" min="0" style="width:100px;" />
                    <p class="description"><?php esc_html_e( '0 = unlimited.', 'spntn-nft-ticketing' ); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="bt_organizer_wallet"><?php esc_html_e( 'Organizer Wallet', 'spntn-nft-ticketing' ); ?></label></th>
                <td>
                    <input type="text" id="bt_organizer_wallet" name="bt_organizer_wallet" value="<?php echo esc_attr( $organizer_wallet ); ?>" class="regular-text" placeholder="0x..." />
                    <p class="description"><?php esc_html_e( 'Receives 97% of each ticket sale. Defaults to plugin settings.', 'spntn-nft-ticketing' ); ?></p>
                </td>
            </tr>

            <tr>
                <th><label for="bt_chain"><?php esc_html_e( 'Chain', 'spntn-nft-ticketing' ); ?></label></th>
                <td>
                    <select id="bt_chain" name="bt_chain">
                        <?php foreach ( $allowed_chains as $slug => $label ) : ?>
                            <option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $chain, $slug ); ?>>
                                <?php echo esc_html( $label ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description">
                        <?php esc_html_e( 'USDC per chain:', 'spntn-nft-ticketing' ); ?>
                        Polygon: <code>0x3c499c...3359</code> &nbsp;
                        Base: <code>0x833589...2913</code> &nbsp;
                        Arbitrum: <code>0xaf88d0...5831</code> &nbsp;
                        Optimism: <code>0x0b2C63...7Ff85</code>
                    </p>
                </td>
            </tr>
        </table>
        <?php
    }

    public static function render_sync_box( WP_Post $post ): void {
        $backend_id = get_post_meta( $post->ID, '_bt_backend_event_id', true );
        $slug       = get_post_meta( $post->ID, '_bt_slug',             true );
        ?>
        <?php if ( $backend_id ) : ?>
            <p><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span> <?php esc_html_e( 'Synced to backend', 'spntn-nft-ticketing' ); ?></p>
            <p><strong><?php esc_html_e( 'Backend ID:', 'spntn-nft-ticketing' ); ?></strong><br>
                <code style="word-break:break-all;"><?php echo esc_html( $backend_id ); ?></code></p>
            <?php if ( $slug ) : ?>
                <p><strong><?php esc_html_e( 'Slug:', 'spntn-nft-ticketing' ); ?></strong> <code><?php echo esc_html( $slug ); ?></code></p>
            <?php endif; ?>
            <p class="description"><?php esc_html_e( 'Re-save to sync changes.', 'spntn-nft-ticketing' ); ?></p>
        <?php else : ?>
            <p class="description"><?php esc_html_e( 'Will sync to backend when published.', 'spntn-nft-ticketing' ); ?></p>
        <?php endif; ?>
        <hr>
        <p><strong><?php esc_html_e( 'Shortcode:', 'spntn-nft-ticketing' ); ?></strong><br>
            <code>[blockchain_event id="<?php echo esc_html( $post->ID ); ?>"]</code></p>
        <?php
    }

    // ─── Save meta + sync to backend ─────────────────────────────────────────

    public static function save_meta( int $post_id, WP_Post $post ): void {
        if ( ! isset( $_POST['bt_event_nonce'] ) ) return;
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bt_event_nonce'] ) ), 'bt_save_event' ) ) return;
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;
        if ( $post->post_status === 'auto-draft' ) return;

        $fields = [
            '_bt_date'             => 'sanitize_text_field',
            '_bt_location'         => 'sanitize_text_field',
            '_bt_currency'         => 'sanitize_text_field',
            '_bt_payment_token'    => 'sanitize_text_field',
            '_bt_total_supply'     => 'absint',
            '_bt_organizer_wallet' => 'sanitize_text_field',
            '_bt_contract_address' => 'sanitize_text_field',
            '_bt_chain'            => 'sanitize_text_field',
        ];

        foreach ( $fields as $key => $sanitizer ) {
            // Strip only the leading underscore to produce the form field name.
            // e.g. '_bt_total_supply' → 'bt_total_supply'
            // NOTE: ltrim($key, '_bt_') must NOT be used here — it treats the
            //       second arg as a character mask, not a prefix, and strips the
            //       't' from 'total', yielding 'bt_otal_supply'.
            $post_key = ltrim( $key, '_' );
            if ( isset( $_POST[ $post_key ] ) ) {
                update_post_meta( $post_id, $key, call_user_func( $sanitizer, wp_unslash( $_POST[ $post_key ] ) ) );
            }
        }

        // Convert human-readable price to wei before storing.
        if ( isset( $_POST['bt_price'] ) && '' !== $_POST['bt_price'] ) {
            $currency_for_price = sanitize_text_field( wp_unslash( $_POST['bt_currency'] ?? 'POL' ) );
            $price_human        = sanitize_text_field( wp_unslash( $_POST['bt_price'] ) );
            update_post_meta( $post_id, '_bt_price', bt_to_wei( $price_human, $currency_for_price ) );
        }

        // Convert datetime-local to ISO string for backend
        if ( ! empty( $_POST['bt_date'] ) ) {
            $dt = sanitize_text_field( wp_unslash( $_POST['bt_date'] ) );
            update_post_meta( $post_id, '_bt_date', $dt );
        }

        // Validate Ethereum address fields — clear any value that is not a valid address.
        foreach ( [ '_bt_organizer_wallet', '_bt_contract_address', '_bt_payment_token' ] as $addr_key ) {
            $val = get_post_meta( $post_id, $addr_key, true );
            if ( '' !== $val && ! bt_is_eth_address( $val ) ) {
                delete_post_meta( $post_id, $addr_key );
            }
        }

        // Sync to backend (only if published)
        if ( $post->post_status === 'publish' ) {
            BT_Admin::sync_event_to_backend( $post_id );
        }
    }
}
