# 📐 Standar Arsitektur Visual Reader Engine & Anti-Regression Guide

Dokumen ini adalah referensi permanen untuk mencegah regresi logika pada pengembangan dan iterasi project **Marketing Visual Library** (maupun project visual reader serupa di masa mendatang).

---

## 1. ⚠️ Aturan Fondasi CSS Viewport (Kritis)

> **JANGAN PERNAH** menggunakan `height: 100%` pada `html, body` bersamaan dengan `overflow-x: hidden`.

### Mengapa Ini Berbahaya?
- Jika `html` dan `body` sama-sama diberi `height: 100%`, browser (Chrome/Edge/Safari) mengunci tinggi `html` setinggi viewport (misal 800px) dan memindahkan scrollbar ke dalam `document.body`.
- Akibatnya:
  - `window.scrollY` secara senyap **selalu bernilai 0**.
  - `window.scrollTo()` **mati total / diabaikan**.
  - `window.addEventListener('scroll')` tidak dapat membaca koordinat scroll yang sebenarnya.

### ✅ Standar CSS yang Benar:
```css
html {
    width: 100%;
    min-height: 100%;
    overflow-x: hidden;
    overflow-y: auto;
}

body {
    width: 100%;
    min-height: 100%;
    font-family: var(--font-main);
    color: var(--text-body);
    background-color: var(--bg-body);
    overflow-x: hidden;
    -webkit-font-smoothing: antialiased;
}
```

---

## 2. 🎯 Restorasi Posisi Membaca: Exact-Pixel vs. Element Snapping

Infografis visual memiliki resolusi tinggi (4K) dengan tinggi vertikal **ribuan pixel per gambar (misal 3.840 px)**.

| Skenario | Mekanisme yang Digunakan | Penjelasan |
| :--- | :--- | :--- |
| **Membuka Kembali Folder / Refresh / Back-Forward** | **`window.scrollTo({ top: pos.scrollY })`** | **Wajib Exact-Pixel**. Mengembalikan layar ke titik pixel persis tempat mata pembaca berhenti (misal pixel `36.875`), bukan ke puncak gambar. |
| **User Memilih Artikel dari Menu Dropdown** | **`targetEl.scrollIntoView({ block: 'start' })`** | **Element Snap**. Hanya digunakan jika user secara sengaja mengklik *"Artikel #5"* dari daftar isi. |

---

## 3. 🌐 Navigasi Browser & SPA History (`popstate`)

### A. Nonaktifkan Auto-Scroll Bawaan Browser
Browser secara default mencoba mereset scroll history secara otomatis yang bertabrakan dengan logika SPA kita:
```javascript
if ('scrollRestoration' in history) {
    history.scrollRestoration = 'manual';
}
```

### B. Sinkronisasi Hash Real-Time Saat Scrolling
Saat user men-scroll ke artikel berikutnya, perbarui URL hash tanpa reload:
```javascript
history.replaceState(null, '', '#baca=' + folder.rawName + '&art=' + activeIdx);
```

### C. *Synchronous Flush* Sebelum Keluar
Jangan pernah mengandalkan timer *debounce* saat berpindah halaman:
```javascript
function closeReader(updateHash = true) {
    if (currentActiveFolder) {
        saveCurrentPosition(true); // Wajib simpan seketika secara sinkron!
    }
    renderHome();
    if (updateHash) setRoute(null);
}

// Simpan juga saat tab ditutup/di-refresh
window.addEventListener('beforeunload', () => saveCurrentPosition(true));
window.addEventListener('pagehide', () => saveCurrentPosition(true));
```

---

## 4. 🗄️ Manajemen Dual-Storage Independen Multi-Folder

Setiap folder kategori wajib memiliki slot data posisi terpisah yang tidak saling menimpa:

```javascript
// Master Data Structure
folderPositions = {
    "01-psikologi-bias-kognitif": { artIdx: 10, scrollY: 36875, total: 118, ts: 1724123456789 },
    "02-framework-model-strategi": { artIdx: 25, scrollY: 92160, total: 93, ts: 1724123499999 },
    "06-tokoh-dan-pemikir": { artIdx: 5, scrollY: 15360, total: 70, ts: 1724123511111 }
};
```

1. **Dual Redundancy**: Simpan ke `localStorage` DAN `document.cookie` (masa aktif 365 hari, `SameSite=Lax`).
2. **Restoration Lock (`isRestoringScroll`)**: Kunci fungsi penyimpanan selama proses render awal dan restorasi scroll agar nilai `0` tidak menimpa posisi tersimpan.

---

## 5. 🧭 Logika Interaksi Reader Navigator

1. **Default State**: 100% lenyap (`.nav-hidden`).
2. **Tap Area Atas ($\le 90\text{px}$)**: Menampilkan/menyembunyikan navigator (*toggle*).
3. **Tap Area Lain ($> 90\text{px}$) atau Mulai Scrolling**: Langsung menyembunyikan navigator seketika.
4. **Tanpa Efek Samping**: Dilarang menggunakan `:hover` CSS yang memaksa navbar muncul saat kursor mouse lewat tanpa sengaja.

---

## 6. 🚀 Pipeline *Smart Proactive Preloader*

- **Deteksi Artikel Aktif**: Menggunakan `getBoundingClientRect()` native yang akurat pada semua rasio zoom dan ukuran layar.
- **Runway Buffer**: Selalu memuat **5 gambar ke depan dan 2 gambar ke belakang** di memori browser (`new Image()`) di setiap frame scroll via `requestAnimationFrame`.
- **Zero Gray Lag**: Tidak menggunakan blocking overlay pseudo-element pada `.article-frame`.
