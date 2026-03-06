/**
 * wallet.js — Blockchain Ticketing: Wallet connection helper
 * Depends on: ethers (v6 UMD)
 */
(function () {
  'use strict';

  window.BT_Wallet = {
    provider: null,
    signer:   null,
    address:  null,

    async connect() {
      if (!window.ethereum) {
        throw new Error('MetaMask not found. Please install MetaMask to buy NFT tickets.');
      }
      const provider = new ethers.BrowserProvider(window.ethereum);
      await provider.send('eth_requestAccounts', []);
      const signer  = await provider.getSigner();
      this.provider = provider;
      this.signer   = signer;
      this.address  = await signer.getAddress();
      return this.address;
    },

    async ensurePolygon() {
      try {
        await window.ethereum.request({
          method: 'wallet_switchEthereumChain',
          params: [{ chainId: '0x89' }], // 137 decimal = Polygon Mainnet
        });
      } catch (e) {
        // Chain not added yet — add it
        if (e.code === 4902) {
          await window.ethereum.request({
            method: 'wallet_addEthereumChain',
            params: [{
              chainId:         '0x89',
              chainName:       'Polygon Mainnet',
              rpcUrls:         ['https://polygon-rpc.com'],
              nativeCurrency:  { name: 'POL', symbol: 'POL', decimals: 18 },
              blockExplorerUrls: ['https://polygonscan.com'],
            }],
          });
        } else {
          throw e;
        }
      }
    },

    shortAddress(addr) {
      return addr ? addr.slice(0, 6) + '…' + addr.slice(-4) : '';
    },
  };
})();
