/**
 * ticket.js — Blockchain Ticketing: Ticket purchase flow
 * Depends on: wallet.js, ethers (v6 UMD), qrcodejs, jQuery
 */
(function ($) {
  'use strict';

  $(document).ready(function () {
    var $container = $('#bt-event-container');
    if (!$container.length) return;

    var eventId     = $container.data('event-id');
    var $status     = $('#bt-status');
    var $spinner    = $('#bt-spinner');
    var $statusText = $('#bt-status-text');
    var $progress   = $('#bt-progress');
    var $progressBar = $('#bt-progress-bar');
    var $connectBtn = $('#bt-connect-wallet');
    var $buyBtn     = $('#bt-buy-ticket');
    var $result     = $('#bt-ticket-result');
    var eventChain  = 'polygon';  // set from event data once loaded
    var eventContractAddress = '';

    // ── Load event info ────────────────────────────────────────────────────────
    $.post(bt_ajax.url, { action: 'bt_get_event', nonce: bt_ajax.nonce, event_id: eventId })
      .done(function (res) {
        if (res.success && res.data) renderEvent(res.data);
        else showStatus('Could not load event details.', true);
      })
      .fail(function () {
        showStatus('Could not load event details. Please refresh the page.', true);
      });

    function renderEvent(event) {
      eventChain = event.chain || 'polygon';
      var $name = $('#bt-event-name');
      $name.text(event.name).removeAttr('aria-busy');
      $('#bt-event-date').text(
        event.date ? new Date(event.date).toLocaleDateString(undefined, { year: 'numeric', month: 'long', day: 'numeric' }) : ''
      );
      $('#bt-event-location').text(event.location || '');
      $('#bt-event-price').text(formatPrice(event.price, event.currency, event.chain));

      if (event.totalSupply > 0) {
        var remaining = event.totalSupply - (event.soldCount || 0);
        $('#bt-supply').text(remaining + ' of ' + event.totalSupply + ' remaining');
        if (remaining <= 0) {
          $buyBtn.prop('disabled', true).text('Sold Out');
          $connectBtn.prop('disabled', true);
        }
      } else {
        $('#bt-supply').text('Unlimited');
      }

      if (event.status !== 'active') {
        $buyBtn.prop('disabled', true).text(event.status === 'ended' ? 'Event Ended' : 'Not Available');
        $connectBtn.prop('disabled', true);
      }
    }

    function formatPrice(price, currency, chain) {
      try {
        if (currency === 'ERC20') {
          return (parseInt(price, 10) / 1e6).toFixed(2) + ' USDC';
        }
        var symbol = (chain && window.BT_Chains && window.BT_Chains[chain])
          ? window.BT_Chains[chain].nativeCurrency.symbol
          : 'POL';
        return parseFloat(ethers.formatEther(price)).toFixed(4) + ' ' + symbol;
      } catch (_) {
        return price;
      }
    }

    // ── Connect wallet ─────────────────────────────────────────────────────────
    $connectBtn.on('click', async function () {
      // MetaMask not installed — show link inline
      if (!window.ethereum) {
        $status.html(
          'MetaMask not found. <a href="https://metamask.io" target="_blank" rel="noopener noreferrer">Install MetaMask</a> to buy NFT tickets.'
        ).show().addClass('bt-error').removeClass('bt-success');
        setTimeout(function () { $status[0].focus(); }, 50);
        return;
      }

      try {
        showStatus('Connecting wallet...');
        var address = await BT_Wallet.connect();
        await BT_Wallet.ensureChain(eventChain);
        showStatus('Connected: ' + BT_Wallet.shortAddress(address), false, true);
        $connectBtn.text('Connected (' + BT_Wallet.shortAddress(address) + ')').prop('disabled', true);
        $buyBtn.prop('disabled', false);
      } catch (e) {
        showStatus(e.message || 'Connection failed.', true);
        setTimeout(function () { $status[0].focus(); }, 50);
      }
    });

    // ── Buy ticket ─────────────────────────────────────────────────────────────
    $buyBtn.on('click', async function () {
      if (!BT_Wallet.address) return;

      // Verify wallet is still connected
      try {
        var currentAddr = await BT_Wallet.signer.getAddress();
        if (currentAddr.toLowerCase() !== BT_Wallet.address.toLowerCase()) {
          throw new Error('address_changed');
        }
      } catch (_) {
        showStatus('Wallet disconnected. Please reconnect.', true);
        $connectBtn.text('Connect Wallet').prop('disabled', false);
        $buyBtn.prop('disabled', true);
        BT_Wallet.address = null;
        setTimeout(function () { $status[0].focus(); }, 50);
        return;
      }

      $buyBtn.prop('disabled', true).text('Processing...');
      setProgress(10);

      try {
        // 1. Get backend signature
        showStatus('Getting authorization from server...');
        var signRes = await $.post(bt_ajax.url, {
          action:        'bt_sign_ticket',
          nonce:         bt_ajax.nonce,
          event_id:      eventId,
          buyer_address: BT_Wallet.address,
        });

        if (!signRes.success) throw new Error(signRes.data || 'Server signing failed');

        var d = signRes.data;
        var { signature, tokenURI, price, currency, paymentToken, organizer, onChainEventId, contractAddress, chain } = d;
        eventChain = chain || eventChain;
        eventContractAddress = contractAddress || '';

        var MINT_ABI = [
          'function mintTicket(address organizer, uint256 eventId, string uri, uint256 price, bytes sig) external payable',
          'function mintTicketERC20(address organizer, uint256 eventId, string uri, uint256 price, address paymentToken, bytes sig) external',
        ];
        var ERC20_ABI = [
          'function approve(address spender, uint256 amount) external returns (bool)',
          'function allowance(address owner, address spender) external view returns (uint256)',
        ];

        var contract = new ethers.Contract(contractAddress, MINT_ABI, BT_Wallet.signer);
        var tx;

        if (currency === 'ERC20') {
          setProgress(25);
          showStatus('Approving token spend...');
          var tokenContract = new ethers.Contract(paymentToken, ERC20_ABI, BT_Wallet.signer);
          var allowance = await tokenContract.allowance(BT_Wallet.address, contractAddress);
          if (BigInt(allowance) < BigInt(price)) {
            var approveTx = await tokenContract.approve(contractAddress, price);
            setProgress(40);
            showStatus('Waiting for approval confirmation...');
            await approveTx.wait();
          }
          setProgress(55);
          showStatus('Confirm ticket purchase in MetaMask...');
          tx = await contract.mintTicketERC20(organizer, BigInt(onChainEventId), tokenURI, BigInt(price), paymentToken, signature);
          setProgress(70);
        } else {
          setProgress(30);
          showStatus('Confirm ticket purchase in MetaMask...');
          tx = await contract.mintTicket(organizer, BigInt(onChainEventId), tokenURI, BigInt(price), signature, { value: BigInt(price) });
          setProgress(55);
        }

        showStatus('Waiting for blockchain confirmation...');
        var receipt = await tx.wait();
        setProgress(80);

        // 2. Parse tokenId from TicketMinted event
        var tokenId = null;
        try {
          var iface = new ethers.Interface([
            'event TicketMinted(uint256 indexed tokenId, uint256 indexed eventId, address indexed buyer, address organizer, address paymentToken, uint256 grossPrice, uint256 platformFee)',
          ]);
          for (var i = 0; i < receipt.logs.length; i++) {
            try {
              var parsed = iface.parseLog(receipt.logs[i]);
              if (parsed && parsed.name === 'TicketMinted') {
                tokenId = parsed.args.tokenId.toString();
                break;
              }
            } catch (_) {}
          }
        } catch (_) {}

        if (!tokenId) {
          throw new Error('Ticket mint confirmed but token ID could not be read from logs. Transaction: ' + receipt.hash);
        }

        // 3. Record on backend
        showStatus('Recording your ticket...');
        var recordRes = await $.post(bt_ajax.url, {
          action:        'bt_record_sale',
          nonce:         bt_ajax.nonce,
          event_id:      eventId,
          token_id:      tokenId,
          tx_hash:       receipt.hash,
          buyer_address: BT_Wallet.address,
        });

        setProgress(100);

        if (!recordRes || !recordRes.success) {
          var errMsg = (recordRes && recordRes.data) || 'Ticket minted but sale record failed.';
          errMsg += ' Transaction: ' + receipt.hash;
          throw new Error(errMsg);
        }
        if (recordRes.data && recordRes.data.recorded === false) {
          throw new Error('Ticket minted but sale record was rejected as duplicate. Transaction: ' + receipt.hash);
        }

        // 4. Show success
        showTicketResult(tokenId, receipt.hash, eventId, eventContractAddress);

      } catch (e) {
        var msg = e.reason || e.shortMessage || e.message || 'Transaction failed';
        showStatus(msg, true);
        $buyBtn.prop('disabled', false).text('Buy Ticket');
        $progress.hide();
        setTimeout(function () { $status[0].focus(); }, 50);
      }
    });

    // ── Helpers ────────────────────────────────────────────────────────────────

    function showStatus(msg, isError, noSpinner) {
      var processing = !isError && !noSpinner;
      $spinner.toggle(processing);
      $statusText.text(msg);
      $status.show()
        .toggleClass('bt-error',   !!isError)
        .toggleClass('bt-success', !isError && !!noSpinner);
    }

    function setProgress(pct) {
      $progress.show();
      $progressBar.css('width', pct + '%');
      $('#bt-progress').attr('aria-valuenow', pct);
    }

    function showTicketResult(tokenId, txHash, backendEventId, contractAddress) {
      $buyBtn.hide();
      $progress.hide();
      $result.show();

      $('#bt-token-id').text(tokenId ? '#' + tokenId : '');

      var txLink = $('#bt-tx-link');
      if (txHash) {
        txLink.attr('href', BT_Wallet.getExplorerTxUrl(eventChain, txHash)).show();
      }

      // Generate QR payload: tokenId + wallet + backend event + contract.
      if (tokenId && typeof QRCode !== 'undefined') {
        var qrData = JSON.stringify({
          t: parseInt(tokenId, 10),
          w: BT_Wallet.address,
          e: backendEventId,
          c: contractAddress || ''
        });
        var el = document.getElementById('bt-qr-code');
        el.innerHTML = '';
        try {
          new QRCode(el, { text: qrData, width: 220, height: 220, correctLevel: QRCode.CorrectLevel.H });
          // Offer download after canvas renders
          setTimeout(function () {
            var canvas = el.querySelector('canvas');
            if (canvas) {
              $('#bt-qr-download')
                .attr('href', canvas.toDataURL('image/png'))
                .attr('download', 'ticket-' + tokenId + '.png');
              $('#bt-qr-download-wrap').show();
            }
          }, 100);
        } catch (_) {
          showStatus('Ticket purchased but QR code could not be generated. Note your Token #' + tokenId, true);
        }
      }

      showStatus('Ticket minted successfully!', false, true);

      // Move focus to the result area for keyboard / screen reader users
      setTimeout(function () { $result[0].focus(); }, 100);
    }
  });

})(jQuery);
