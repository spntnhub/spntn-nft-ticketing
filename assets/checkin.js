/**
 * checkin.js — Blockchain Ticketing: QR scanner + check-in interface
 * Depends on: jsQR, jQuery
 * Uses the device camera to scan ticket QR codes and verify them with the backend.
 */
(function ($) {
  'use strict';

  $(document).ready(function () {
    var $container = $('#bt-checkin-container');
    if (!$container.length) return;

    var video        = document.getElementById('bt-camera');
    var canvas       = document.getElementById('bt-canvas');
    var context      = canvas ? canvas.getContext('2d') : null;
    var $scanStatus  = $('#bt-scan-status');
    var $result      = $('#bt-scan-result');
    var $scanAgain   = $('#bt-scan-again');
    var $retryCamera = $('#bt-retry-camera');
    var scanning     = false;
    var animFrame    = null;
    var stream       = null;

    // ── Camera init ───────────────────────────────────────────────────────────

    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
      $scanStatus.text('Camera not supported on this device or browser.');
      $retryCamera.hide();
      return;
    }

    function startCamera() {
      navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } })
        .then(function (s) {
          stream = s;
          video.srcObject = stream;
          video.setAttribute('playsinline', true);
          video.play();
          scanning = true;
          $scanStatus.text('Scanning... point camera at QR code.');
          tick();
        })
        .catch(function (err) {
          $scanStatus.text('Camera access denied. Check browser permissions and try again.');
          $retryCamera.show();
        });
    }

    startCamera();

    $retryCamera.on('click', function () {
      $retryCamera.hide();
      startCamera();
    });

    // ── QR scan loop ──────────────────────────────────────────────────────────

    function tick() {
      if (!scanning) return;
      if (video.readyState === video.HAVE_ENOUGH_DATA) {
        canvas.height = video.videoHeight;
        canvas.width  = video.videoWidth;
        context.drawImage(video, 0, 0, canvas.width, canvas.height);

        var imageData = context.getImageData(0, 0, canvas.width, canvas.height);
        var code = jsQR(imageData.data, imageData.width, imageData.height, {
          inversionAttempts: 'dontInvert',
        });

        if (code) {
          scanning = false;
          cancelAnimationFrame(animFrame);
          processQR(code.data);
          return;
        }
      }
      animFrame = requestAnimationFrame(tick);
    }

    // ── QR processing ─────────────────────────────────────────────────────────

    function processQR(rawData) {
      $scanStatus.text('Verifying ticket...');

      var parsed;
      try {
        parsed = JSON.parse(rawData);
      } catch (_) {
        showResult('invalid', 'QR code is not a valid ticket.');
        return;
      }

      var tokenId         = parsed.t;
      var wallet          = parsed.w;
      var eventId         = parsed.e || '';
      var contractAddress = parsed.c || '';

      if (!tokenId || !wallet) {
        showResult('invalid', 'QR code data is incomplete.');
        return;
      }

      verifyTicket(tokenId, wallet, eventId, contractAddress);
    }

    function verifyTicket(tokenId, wallet, eventId, contractAddress) {
      $scanStatus.text('Verifying ticket...');

      $.post(bt_checkin_ajax.url, {
        action:           'bt_checkin',
        nonce:            bt_checkin_ajax.nonce,
        token_id:         tokenId,
        wallet:           wallet,
        event_id:         eventId,
        contract_address: contractAddress,
      })
        .done(function (res) {
          if (res.success && res.data) {
            var d = res.data;
            if (d.result === 'valid') {
              showResult('valid', null, d.data);
            } else if (d.result === 'already_used') {
              showResult('already_used', d.reason || 'Ticket already scanned.');
            } else {
              showResult('invalid', d.reason || 'Invalid ticket.');
            }
          } else {
            showResult('invalid', res.data || 'Verification failed.');
          }
        })
        .fail(function () {
          showResult('invalid', 'Network error. Please try again.');
        });
    }

    // ── Result display ────────────────────────────────────────────────────────

    function showResult(type, reason, ticketData) {
      $result.show();
      $scanAgain.show();
      $result.removeClass('bt-result-valid bt-result-invalid bt-result-used');

      var html = '';

      if (type === 'valid') {
        $result.addClass('bt-result-valid');
        html += '<span class="bt-result-icon bt-icon-valid" aria-hidden="true"></span>';
        html += '<strong class="bt-result-title">Valid Ticket</strong>';
        if (ticketData) {
          html += '<ul class="bt-ticket-details">';
          if (ticketData.eventName)   html += '<li><b>Event:</b> '  + escHtml(ticketData.eventName) + '</li>';
          if (ticketData.eventDate)   html += '<li><b>Date:</b> '   + escHtml(new Date(ticketData.eventDate).toLocaleDateString()) + '</li>';
          if (ticketData.buyerWallet) {
            var short = ticketData.buyerWallet.slice(0, 8) + '...' + ticketData.buyerWallet.slice(-6);
            html += '<li><b>Wallet:</b> ' + escHtml(short) + '</li>';
          }
          if (ticketData.tokenId) html += '<li><b>Token #</b>' + escHtml(String(ticketData.tokenId)) + '</li>';
          html += '</ul>';
        }
        $scanStatus.text('Entry granted.');
      } else if (type === 'already_used') {
        $result.addClass('bt-result-used');
        html += '<span class="bt-result-icon bt-icon-used" aria-hidden="true"></span>';
        html += '<strong class="bt-result-title">Already Used</strong>';
        html += '<p>' + escHtml(reason) + '</p>';
        $scanStatus.text('Ticket was already scanned.');
      } else {
        $result.addClass('bt-result-invalid');
        html += '<span class="bt-result-icon bt-icon-invalid" aria-hidden="true"></span>';
        html += '<strong class="bt-result-title">Invalid Ticket</strong>';
        html += '<p>' + escHtml(reason || 'This ticket is not valid for entry.') + '</p>';
        $scanStatus.text('Entry denied.');
      }

      $result.html(html);

      // Move focus to result for keyboard / screen reader users
      setTimeout(function () { $result[0].focus(); }, 50);
    }

    // ── Scan again ────────────────────────────────────────────────────────────

    $scanAgain.on('click', function () {
      $result.hide().html('');
      $scanAgain.hide();
      $scanStatus.text('Scanning...');
      scanning = true;
      tick();
    });

    // ── Manual entry ──────────────────────────────────────────────────────────

    $('#bt-show-manual').on('click', function () {
      $('#bt-manual-entry').toggle();
    });

    $('#bt-manual-verify').on('click', function () {
      var tokenId = $('#bt-manual-token').val().trim();
      var wallet  = $('#bt-manual-wallet').val().trim();
      var eventId = $('#bt-manual-event').val().trim();

      if (!tokenId || !wallet) {
        $scanStatus.text('Token ID and wallet address are required.');
        return;
      }

      scanning = false;
      cancelAnimationFrame(animFrame);
      verifyTicket(parseInt(tokenId, 10), wallet, eventId, '');
    });

    // ── HTML escape ───────────────────────────────────────────────────────────

    function escHtml(str) {
      return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
    }
  });

})(jQuery);
