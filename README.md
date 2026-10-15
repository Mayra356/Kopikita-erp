# ☕ KopiKita ERP - Coffee Shop & Roastery Management System

Sistem ERP & POS terintegrasi berbasis web untuk kedai kopi dan roastery modern. Dilengkapi katalog resep BOM (*Bill of Materials*), pengurangan stok bahan baku otomatis, manajemen pembelian (PO), kasir harian (*Z-Report*), dan pembukuan akuntansi *double-entry*.

---

## ⚡ Panduan Deploy Cepat ke Vercel (1-Click Ready)

Proyek ini telah dikonfigurasi dengan `vercel.json` dan serverless entrypoint `api/index.php` menggunakan runtime `vercel-php`. Database SQLite telah disiapkan agar otomatis siap pakai (*zero-config*) di lingkungan serverless Vercel.

### Cara 1: Menggunakan Vercel CLI (Paling Cepat)

1. Pastikan Anda memiliki Node.js dan install Vercel CLI di terminal:
   ```bash
   npm i -g vercel
   ```

2. Buka folder proyek di terminal:
   ```bash
   cd /Applications/XAMPP/xamppfiles/htdocs/kopikita
   ```

3. Jalankan perintah deploy:
   ```bash
   vercel
   ```
   * Jawab `Y` untuk *Set up and deploy*.
   * Pilih akun Vercel Anda.
   * Pilih *Link to existing project?* -> `N` (atau pilih project jika sudah ada).
   * Masukkan nama project (misal: `kopikita-erp`).
   * *In which directory is your code located?* -> Tekan **Enter** (`./`).
   * *Want to modify these settings?* -> `N`.

4. Vercel akan langsung mem-build dan memberikan URL live website Anda (misal: `https://kopikita-erp.vercel.app`)!

---

### Cara 2: Menggunakan GitHub & Vercel Dashboard

1. **Inisialisasi Git dan Push ke GitHub**:
   ```bash
   cd /Applications/XAMPP/xamppfiles/htdocs/kopikita
   git init
   git add .
   git commit -m "Initial commit KopiKita ERP"
   git branch -M main
   git remote add origin https://github.com/USERNAME/kopikita-erp.git
   git push -u origin main
   ```

2. **Deploy di Vercel Dashboard**:
   * Buka [https://vercel.com/new](https://vercel.com/new).
   * Pilih repositori `kopikita-erp` yang baru saja Anda push.
   * **Framework Preset**: Biarkan *Other*.
   * **Root Directory**: `./` (default).
   * Klik **Deploy**!

---

## 🖥️ Cara Menjalankan Lokal di Komputer (XAMPP)

### Opsi A: Server PHP Built-in
```bash
/Applications/XAMPP/xamppfiles/bin/php -S 127.0.0.1:8000 -t /Applications/XAMPP/xamppfiles/htdocs/kopikita
```
Lalu buka di browser: `http://localhost:8000`

### Opsi B: Apache XAMPP
Nyalakan Apache di XAMPP Control Panel, lalu buka di browser:
`http://localhost/kopikita`

---

## ⚙️ Cara Kerja di Vercel (Arsitektur Serverless)

1. **Runtime PHP**: Menggunakan `vercel-php@0.7.3` pada `vercel.json` untuk menjalankan kode PHP modern di lingkungan serverless AWS Lambda Vercel.
2. **Routing Otomatis**: Seluruh request dinamis (seperti `/pos.php`, `/orders.php`, `/finance.php`) diarahkan oleh `vercel.json` ke `api/index.php` yang mendistribusikan request secara transparan tanpa mengubah URL di browser.
3. **Database SQLite di Serverless**: Karena filesystem repositori di Vercel bersifat *read-only*, sistem di `includes/db.php` secara cerdas mendeteksi lingkungan Vercel dan menyalin database starter `kopikita.db` ke `/tmp/kopikita.db` (direktori yang dapat ditulis di serverless). Seluruh transaksi POS, perubahan stok, dan pencatatan kasir berjalan normal tanpa error *readonly database*.
4. **Static Assets**: Aset CSS dan JS di folder `/assets` dilayani langsung oleh Vercel Edge Global CDN untuk performa kilat.

---

## 📋 Daftar Modul

* **Executive Dashboard**: Ringkasan omzet, laba kotor, tren penjualan, dan top menu.
* **Point of Sale (POS)**: Kasir cepat, auto-deduct resep BOM, hitung pajak PB1 10%, struk termal.
* **Katalog & Resep BOM**: Komposisi takaran bahan baku per sajian menu dan kalkulasi HPP otomatis.
* **Bahan Baku & Persediaan**: Manajemen stok, notifikasi batas minimum (*safety stock*), dan valuasi aset.
* **Kartu Stok & Stock Opname**: Log mutasi masuk/keluar dan rekonsiliasi selisih fisik.
* **Produksi & Roastery**: Batch sangrai kopi green beans, ekstraksi Cold Brew, sirup gula aren.
* **Purchase Order (PO) & Supplier**: Pengadaan bahan baku dan penerimaan barang masuk gudang.
* **Cash Register**: Modal kas awal laci, pencatatan *petty cash*, dan penutupan shift (*Z-Report*).
* **Laporan Laba Rugi & Arus Kas**: Analisis Gross Margin, Net Profit, dan arus kas masuk/keluar.
* **Buku Jurnal Double-Entry**: Pencatatan pembukuan akuntansi standar (COA) seimbang (*balanced*).
* **Tim Barista & Shift**: Jadwal kerja harian dan absensi *Clock In / Clock Out*.
* **Member Loyalitas**: Poin belanja pelanggan dan diskon berjenjang (Gold 10%, Silver 5%).
* **Multi-Cabang (Outlets)**: Pengelolaan banyak kedai dengan pergantian cabang instan.
* **Pengaturan & Backup**: Konfigurasi nama kedai, format struk printer kasir, dan backup database 1-klik.
# Kopikita---erp
