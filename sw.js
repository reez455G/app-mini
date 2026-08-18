// Service worker App-mini — SENGAJA minim: cuma menyimpan "kulit" aplikasi
// (HTML/JS/ikon) supaya bisa di-install & dibuka cepat sebagai app di HP.
// TIDAK PERNAH menyentuh backend/api/* -- transaksi kasir harus selalu ke
// jaringan langsung, tidak boleh ketinggalan data lama dari cache.
//
// Naikkan CACHE_NAME (misal jadi 'app-mini-v2') tiap kali mau memaksa HP
// yang sudah install mengambil ulang salinan baru -- kalau tidak, versi
// lama yang sudah tersimpan akan terus dipakai sampai cache-nya kedaluwarsa
// sendiri lewat pengecekan network-first di bawah.
const CACHE_NAME = 'app-mini-v2';
const SHELL_FILES = [
  'Dashboard.dc.html',
  'support.js',
  'vendor/react/react.production.min.js',
  'vendor/react/react-dom.production.min.js',
  'vendor/barcode/qrcode-generator.js',
  'vendor/barcode/jsbarcode.min.js',
  'manifest.json',
  'assets/icons/icon-192.png',
  'assets/icons/icon-512.png',
];

self.addEventListener('install', (event) => {
  event.waitUntil(caches.open(CACHE_NAME).then((cache) => cache.addAll(SHELL_FILES)));
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((names) => Promise.all(
      names.filter((n) => n !== CACHE_NAME).map((n) => caches.delete(n))
    )).then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  const url = new URL(event.request.url);
  // backend/api/* dan apa pun bukan GET (POST/PUT/DELETE transaksi) selalu
  // lewat jaringan langsung -- data stok/kasir tidak boleh basi dari cache.
  if (event.request.method !== 'GET' || url.pathname.includes('/backend/api/')) return;

  // Network-first untuk file kulit aplikasi: begitu online, versi terbaru
  // selalu diutamakan (jadi update kode tidak "nyangkut" di HP), cache cuma
  // jadi cadangan waktu offline/sinyal jelek.
  event.respondWith(
    fetch(event.request)
      .then((res) => {
        const copy = res.clone();
        caches.open(CACHE_NAME).then((cache) => cache.put(event.request, copy));
        return res;
      })
      .catch(() => caches.match(event.request))
  );
});
