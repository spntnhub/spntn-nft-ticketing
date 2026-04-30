<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class BT_Checkin {

    // ─── Shortcode: [blockchain_checkin] ─────────────────────────────────────

    public static function render_shortcode( array $atts ): string {
        ob_start();
        ?>
        <div id="bt-checkin-container" class="bt-checkin-widget">
            <h2 class="bt-checkin-title"><?php esc_html_e( 'Ticket Check-In', 'spntn-nft-ticketing' ); ?></h2>

            <div class="bt-camera-wrap">
                <video id="bt-camera" autoplay playsinline muted aria-label="<?php esc_attr_e( 'Camera view for QR scanning', 'spntn-nft-ticketing' ); ?>"></video>
                <canvas id="bt-canvas" style="display:none;"></canvas>
            </div>

            <p id="bt-scan-status" class="bt-scan-status" role="status" aria-live="polite">
                <?php esc_html_e( 'Point camera at the ticket QR code.', 'spntn-nft-ticketing' ); ?>
            </p>

            <button id="bt-retry-camera" class="bt-btn bt-btn-secondary" style="display:none; margin-bottom:12px;">
                <?php esc_html_e( 'Retry Camera', 'spntn-nft-ticketing' ); ?>
            </button>

            <div id="bt-scan-result" class="bt-scan-result" aria-live="assertive" style="display:none;" tabindex="-1"></div>

            <button id="bt-scan-again" class="bt-btn bt-btn-secondary" style="display:none; margin-top:12px;">
                <?php esc_html_e( 'Scan Next Ticket', 'spntn-nft-ticketing' ); ?>
            </button>

            <div class="bt-manual-divider">
                <button id="bt-show-manual" class="bt-btn bt-btn-secondary" style="margin-top:16px; width:100%;">
                    <?php esc_html_e( 'Enter token manually', 'spntn-nft-ticketing' ); ?>
                </button>
                <div id="bt-manual-entry" class="bt-manual-entry" style="display:none;">
                    <label for="bt-manual-token" class="bt-manual-label"><?php esc_html_e( 'Token ID', 'spntn-nft-ticketing' ); ?></label>
                    <input type="number" id="bt-manual-token" class="bt-manual-input" placeholder="123" min="1" />
                    <label for="bt-manual-wallet" class="bt-manual-label"><?php esc_html_e( 'Wallet Address', 'spntn-nft-ticketing' ); ?></label>
                    <input type="text" id="bt-manual-wallet" class="bt-manual-input" placeholder="0x..." />
                    <label for="bt-manual-event" class="bt-manual-label"><?php esc_html_e( 'Event ID (optional)', 'spntn-nft-ticketing' ); ?></label>
                    <input type="text" id="bt-manual-event" class="bt-manual-input" placeholder="" />
                    <button id="bt-manual-verify" class="bt-btn bt-btn-primary" style="width:100%; margin-top:4px;">
                        <?php esc_html_e( 'Verify Ticket', 'spntn-nft-ticketing' ); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    // ─── AJAX: verify QR + mark used ─────────────────────────────────────────

    public static function ajax_checkin(): void {
        check_ajax_referer( 'bt_nonce', 'nonce' );

        $token_id         = isset( $_POST['token_id'] ) ? sanitize_text_field( wp_unslash( $_POST['token_id'] ) ) : '';
        $wallet           = isset( $_POST['wallet'] ) ? sanitize_text_field( wp_unslash( $_POST['wallet'] ) ) : '';
        $event_id         = isset( $_POST['event_id'] ) ? sanitize_text_field( wp_unslash( $_POST['event_id'] ) ) : '';
        $contract_address = isset( $_POST['contract_address'] ) ? sanitize_text_field( wp_unslash( $_POST['contract_address'] ) ) : '';

        if ( ! $token_id || ! $wallet ) wp_send_json_error( 'token_id and wallet required' );

        $opts    = get_option( BT_OPTION_KEY, [] );
        $api_key = $opts['api_key']     ?? '';
        $backend = rtrim( $opts['backend_url'] ?? 'https://nft-saas-production.up.railway.app', '/' );

        $response = wp_remote_post( $backend . '/api/v2/ticketing/checkin', [
            'headers' => [
                'Content-Type' => 'application/json',
                'X-API-Key'    => $api_key,
            ],
            'body'    => wp_json_encode( [
                'tokenId'         => $token_id,
                'wallet'          => $wallet,
                'eventId'         => $event_id,
                'contractAddress' => $contract_address,
            ] ),
            'timeout' => 10,
        ] );

        if ( is_wp_error( $response ) ) wp_send_json_error( $response->get_error_message() );

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        wp_send_json_success( $body );
    }
}
