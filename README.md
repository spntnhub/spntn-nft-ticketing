# SPNTN NFT Ticketing

![Version](https://img.shields.io/badge/version-1.0.3-blue)
![License](https://img.shields.io/badge/license-GPL--2.0-green)
![PHP](https://img.shields.io/badge/PHP-8.0%2B-777BB4)
![Chain](https://img.shields.io/badge/chain-Polygon-8247e5)

Sell and verify NFT event tickets directly from your WordPress site.  
Each ticket is a unique ERC-721 token — impossible to duplicate, easy to verify at the door.

---

## How it works

```
Organizer creates event in WP Admin
         ↓
Publish → syncs to backend automatically
         ↓
Add [blockchain_event id="POST_ID"] to any page
         ↓
Buyer connects MetaMask → clicks Buy Ticket
         ↓
ERC-721 NFT minted to buyer's wallet (3% fee → protocol, 97% → organizer)
         ↓
Buyer receives QR code → can save as PNG
         ↓
Staff opens [blockchain_checkin] on mobile browser → scans QR
         ↓
On-chain ownership verified → entry granted or denied
```

---

## Quick start

### 1. Install

Upload the plugin folder to `/wp-content/plugins/` and activate it in **Plugins → Installed Plugins**.

### 2. Get an API key (free)

**Settings → Blockchain Ticketing** → click **Get API Key** → enter your email.  
The key appears instantly. Paste it into the API Key field and save.

### 3. Create an event

**Events → Add New** in WP Admin.

| Field | What to enter |
|---|---|
| Title | Event name |
| Content | Event description (optional) |
| Featured Image | Used as NFT artwork |
| Event Date & Time | Date and time of the event |
| Location | Venue name and city |
| Ticket Price | Full units — e.g. `5` for 5 POL or `10` for 10 USDC |
| Currency | POL (native) or ERC-20 (USDC auto-filled per chain) |
| Total Supply | Max tickets — enter `0` for unlimited |
| Organizer Wallet | Address that receives 97% of each sale |
| Chain | Polygon, Base, Arbitrum, or Optimism |

Click **Publish**. The event syncs to the backend automatically. The **Blockchain Sync** sidebar box confirms the connection.

### 4. Add the ticket widget

Add this shortcode to any page:

```
[blockchain_event id="POST_ID"]
```

Replace `POST_ID` with the WordPress post ID of the event (visible in the URL when editing).

### 5. Add the check-in scanner

Add this shortcode to a staff-only page:

```
[blockchain_checkin]
```

Open this page on a mobile browser at the event door. The camera starts automatically and scans QR codes.

---

## Shortcodes

| Shortcode | Purpose |
|---|---|
| `[blockchain_event id="POST_ID"]` | Ticket purchase widget |
| `[blockchain_checkin]` | QR scanner for staff |
| `[spntn_nft_event id="POST_ID"]` | Alias for blockchain_event |
| `[spntn_nft_checkin]` | Alias for blockchain_checkin |

---

## Buyer flow

1. Buyer opens the ticket page.
2. Clicks **Connect Wallet** — MetaMask opens and asks for account approval.
3. If the wallet is on the wrong network, MetaMask prompts a network switch automatically.
4. Clicks **Buy Ticket** — a progress bar shows each step.
5. For USDC payments: MetaMask asks for token spend approval first, then the purchase.
6. For POL payments: one MetaMask signature, then the transaction.
7. After on-chain confirmation (usually under 10 seconds on Polygon), the QR code appears.
8. Buyer clicks **Save Ticket QR** to download the image.

---

## Check-in flow

1. Staff opens the check-in page on any mobile browser.
2. Camera starts and scans continuously.
3. Point the camera at the buyer's QR code.
4. Result appears immediately:
   - **Valid** — entry granted, ticket marked as used
   - **Already Used** — ticket was scanned before, deny entry
   - **Invalid** — QR is not a valid ticket, deny entry
5. Click **Scan Next Ticket** to continue.

**No camera?** Click **Enter token manually**, enter the Token ID and wallet address, and click **Verify Ticket**.

---

## Supported chains and currencies

| Chain | Native token | USDC contract |
|---|---|---|
| Polygon | POL | `0x3c499c542cEF5E3811e1192ce70d8cC03d5c3359` |
| Base | ETH | `0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913` |
| Arbitrum One | ETH | `0xaf88d065e77c8cC2239327C5EDb3A432268e5831` |
| Optimism | ETH | `0x0b2C639c533813f4Aa9D7837CAf62653d097Ff85` |

---

## Pricing

| | |
|---|---|
| Plugin | Free |
| API key | Free |
| Protocol fee | 3% per ticket sale (on-chain, automatic) |
| Organizer receives | 97% of each ticket price |

No subscriptions. No monthly fees. The protocol only earns when you sell tickets.

---

## Requirements

* WordPress 6.0 or higher
* PHP 8.0 or higher
* HTTPS on the site (required for MetaMask)
* Buyers need MetaMask or a compatible EVM browser wallet

---

## Smart contract

The shared TicketNFT contract on Polygon is used by default — no deployment required.

| | |
|---|---|
| Standard | ERC-721 |
| Deployed on Polygon | `0x20C8c6e569c00A5C0E660165f4d513D61424541E` |
| Protocol fee | 3% per sale |
| Payment | Native token or ERC-20 |
| Minting | Lazy — minted at purchase time, not in advance |

To deploy your own contract instance, contact info@spntn.com.

---

## External services

The plugin connects to:

* **NFT SaaS backend** (`https://nft-saas-production.up.railway.app`) — event sync, signature generation, purchase recording, check-in verification.
* **Polygon Mainnet** (and Base, Arbitrum, Optimism) — mint transactions are submitted directly by the buyer's wallet via MetaMask.
* **Block explorers** — after purchase, a link to the transaction on Polygonscan (or equivalent) is shown.

API keys are stored server-side and never sent to the browser.

---

## Bundled libraries

All libraries are loaded locally via `wp_enqueue_script()`.

| Library | Version | License |
|---|---|---|
| ethers.js | 6.13.2 | MIT |
| qrcodejs | 1.0.0 | MIT |
| jsQR | 1.4.0 | Apache-2.0 |

---

## Support

Email: info@spntn.com  
Plugin URI: https://github.com/spntnhub/spntn-nft-ticketing  
WordPress.org: https://wordpress.org/plugins/spntn-nft-ticketing

---

## License

GPL v2 or later — https://www.gnu.org/licenses/gpl-2.0.html
