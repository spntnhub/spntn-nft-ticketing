=== SPNTN NFT Ticketing ===
Contributors:      spntn
Tags:              nft, blockchain, ticketing, polygon, web3
Requires at least: 6.0
Tested up to:      6.9
Requires PHP:      8.0
Stable tag:        1.0.3
License:           GPL v2 or later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

Sell and verify NFT event tickets on Polygon. Buyers mint ERC-721 tickets via MetaMask; staff verify with a mobile QR scanner.

== Description ==

**SPNTN NFT Ticketing** lets event organizers sell blockchain-based tickets directly from their WordPress site. Each ticket is a unique ERC-721 NFT minted to the buyer's wallet on Polygon — impossible to duplicate or forge.

= How it works =

1. Create an event in WP Admin and set price, supply, and organizer wallet.
2. Publish — the event syncs to the backend automatically.
3. Add `[blockchain_event id="POST_ID"]` to a page. Buyers connect MetaMask and purchase.
4. Each ticket is minted as an ERC-721 NFT on Polygon. The buyer receives a QR code.
5. At the door, staff open `[blockchain_checkin]` on any mobile browser, point the camera at the QR, and the system verifies ownership on-chain. Entry granted or denied in seconds.

= Features =

* On-chain NFT tickets — ERC-721, lazily minted at purchase time
* Accepts POL (native) and USDC/ERC-20 payment
* QR code generated after purchase — can be saved as PNG
* Mobile-friendly QR scanner for event staff
* Fraud-proof: on-chain ownership verified at check-in
* Double-entry prevention — each ticket can only be scanned once
* 3% protocol fee, 97% goes directly to the organizer wallet
* Supports Polygon, Base, Arbitrum, and Optimism
* MetaMask wallet connection with automatic network switching

= Buyer experience =

The buyer opens the ticket page, clicks **Connect Wallet**, and MetaMask opens. After approving the connection, they click **Buy Ticket**. If the correct network is not active, it is switched automatically. The transaction is signed in MetaMask and submitted to the blockchain. Once confirmed (usually under 10 seconds on Polygon), a QR code appears. The buyer can save the QR as an image file.

= Staff check-in =

Staff open the check-in page on any mobile browser (no app required). The camera starts automatically. Point it at the buyer's QR code. The result appears immediately:

* **Valid** — entry granted, ticket marked as used
* **Already Used** — ticket was scanned before, entry denied
* **Invalid** — QR does not correspond to a valid ticket

If the camera is unavailable, staff can enter the Token ID and wallet address manually.

= Supported chains =

* Polygon (POL)
* Base (ETH)
* Arbitrum One (ETH)
* Optimism (ETH)

Each chain also supports USDC as an ERC-20 payment option.

= Pricing =

The plugin and API key are free. The protocol charges a 3% fee per ticket sold on-chain; 97% is sent directly to the organizer wallet. There are no subscriptions and no monthly fees.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/` and activate it.
2. Go to **Settings → Blockchain Ticketing**.
3. Click **Get API Key**, enter your email, and copy the key that appears.
4. Paste the key into the API Key field and save settings.
5. Go to **Events → Add New** and fill in the event details (see field reference below).
6. Publish the event — it syncs to the backend automatically.
7. Add `[blockchain_event id="POST_ID"]` to any page to display the ticket purchase widget.
8. Add `[blockchain_checkin]` to a staff-only page for the QR check-in scanner.

= Event field reference =

* **Title** — Event name shown in the ticket widget.
* **Content** — Event description (optional).
* **Featured Image** — Used as the NFT ticket artwork.
* **Event Date & Time** — Date and time of the event.
* **Location** — Venue name and city.
* **Ticket Price** — Enter in full units: `5` for 5 POL, `10` for 10 USDC.
* **Currency** — POL (native chain token) or ERC-20 (USDC, auto-filled per chain).
* **Total Supply** — Maximum tickets. Enter `0` for unlimited.
* **Organizer Wallet** — Ethereum address that receives 97% of each sale.
* **Chain** — Blockchain to use (Polygon, Base, Arbitrum, Optimism).

After publishing, the **Blockchain Sync** meta box shows the backend ID and a confirmation. Re-save any time to push changes.

== Frequently Asked Questions ==

= What wallets are supported? =

MetaMask and any EVM browser wallet that injects `window.ethereum` (Brave Wallet, Rabby, etc.).

= Does the buyer need a crypto wallet? =

Yes. Buyers need MetaMask or a compatible browser wallet to purchase a ticket. The wallet holds the NFT after purchase.

= Which blockchain is used by default? =

Polygon Mainnet. It has low gas fees (usually under $0.01 per transaction) and fast confirmation times. You can switch to Base, Arbitrum, or Optimism per event.

= What is the ticket QR code? =

The QR code encodes the token ID, buyer wallet address, backend event ID, and contract address. It is generated in the browser after a successful purchase and can be saved as a PNG file. It does not expire.

= What happens if the buyer closes the page before saving the QR? =

The NFT is still in their wallet. Contact support at info@spntn.com with the transaction hash to retrieve ticket details.

= Can tickets be resold? =

Yes. NFTs live in the buyer's wallet and can be transferred to another address. If a ticket is transferred, the new wallet owner can present the updated QR at check-in.

= What happens if the buyer is on the wrong network? =

The plugin automatically requests a network switch via MetaMask before the purchase is processed. The buyer approves the switch once and the transaction proceeds.

= Can I accept USDC instead of POL? =

Yes. Set Currency to ERC-20 in the event settings. The USDC contract address is auto-filled based on the selected chain. The buyer approves the USDC spend in MetaMask before the ticket is minted.

= Can I set a supply limit? =

Yes. Enter the maximum number of tickets in the Total Supply field. When all tickets are sold, the Buy Ticket button shows "Sold Out". Set to 0 for unlimited.

= Is the API key stored securely? =

Yes. The API key is stored server-side in WordPress options and sent in HTTP headers to the backend. It is never exposed to the browser.

= Can I use my own smart contract? =

The shared contract on Polygon is used by default — no configuration required. If you want to deploy your own instance, contact info@spntn.com.

= Is there a way to check in tickets without a camera? =

Yes. The check-in page has an "Enter token manually" option. Staff can type in the Token ID and wallet address to verify a ticket without scanning a QR.

= What is the protocol fee? =

3% of each ticket price is sent to the protocol on-chain at the time of minting. The remaining 97% goes to the organizer wallet. This happens automatically in the smart contract — you do not need to do anything.

== Screenshots ==

1. Ticket purchase widget on the event page — connected wallet, price display, and Buy Ticket button.
2. QR code displayed after successful purchase, with Save Ticket QR button.
3. Mobile check-in scanner with result display (valid, already used, or invalid).
4. Event creation form in WP Admin with all ticket detail fields.
5. Plugin settings page with API key, chain, and organizer wallet fields.

== External services ==

This plugin connects to external services to provide blockchain minting and verification.

= NFT SaaS Backend API =
URL: https://nft-saas-production.up.railway.app
Purpose: Handles event sync, signature generation for minting, purchase recording, and check-in verification.

Data sent:
* On API key activation: email address, site URL.
* On event publish or update: event title, description, date, location, supply, price, currency, payment token address, organizer wallet, chain, featured image URL.
* On ticket purchase: event ID, buyer wallet address, token ID, transaction hash.
* On check-in: token ID, wallet address, event ID, contract address.

= Polygon Mainnet (and Base, Arbitrum, Optimism) =
Purpose: The buyer wallet signs and submits the mint transaction directly to the blockchain via MetaMask. No transaction data passes through the plugin's server.

= Block explorers =
Purpose: After purchase, a link to the transaction on Polygonscan (or equivalent) is shown to the buyer. No data is sent by the plugin; the link is opened in the browser.

= Bundled JavaScript libraries =
This plugin bundles the following open-source libraries locally:
* ethers.js v6.13.2 (MIT) — Ethereum interactions
* qrcodejs v1.0.0 (MIT) — QR code generation
* jsQR v1.4.0 (Apache-2.0) — QR code scanning

All scripts and styles are loaded via wp_enqueue_script() and wp_enqueue_style(). API keys and credentials are stored server-side and never sent to the browser.

== Changelog ==

= 1.0.3 =
* Fixed PHP compatibility: minimum PHP version raised to 8.0 (union return types require 8.0+).
* Moved all admin JavaScript to admin_enqueue_scripts hook — removed inline scripts from render callbacks.
* Added Ethereum address format validation for wallet and contract address fields.
* Added loading spinner and progress bar to ticket purchase flow.
* Added ARIA live regions and focus management for screen reader support.
* Added "Save Ticket QR" download button after purchase.
* Added "Enter token manually" fallback for check-in when camera is unavailable.
* Added camera retry button when camera permission is denied.
* Added MetaMask install link when no browser wallet is detected.
* Added wallet disconnect detection before purchase.
* Replaced emoji icons with CSS-based icons throughout.
* Added mobile-responsive breakpoints and touch-friendly button sizing.
* Added keyboard focus outlines for all buttons.

= 1.0.2 =
* Fixed PHP syntax and duplicate source tree issues.
* Unified script/style loading with wp_enqueue_* and wp_localize_script.
* Improved frontend AJAX compatibility and legacy action aliases.
* Added locally bundled JS dependencies (ethers, qrcodejs, jsQR).
* Clarified external services disclosure.

= 1.0.1 =
* Price input now accepts human-readable values (5 for 5 POL, 10 for 10 USDC).
* USDC token address auto-filled when ERC-20 currency is selected.
* USDC address updates automatically when the chain is changed.

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.3 =
Recommended update. Improves accessibility, mobile UX, error recovery, and PHP 8.0 compatibility.
