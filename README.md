# MikroDash v.2

**MikroDash v.2** adalah dashboard manajemen MikroTik berbasis web yang terintegrasi dengan **Firebase Authentication** dan **Realtime Database**. Proyek ini mempermudah pemantauan dan pengelolaan user hotspot MikroTik secara aman dan efisien, dengan fitur verifikasi login berbasis token serta pengambilan data real-time dari perangkat MikroTik.

![Dashboard Screenshot](/frontend/Screenshot-MikroDash-v.2.png)

## 🔧 Fitur Utama

- 💻 Terminal Mikrotik, memudahkan untuk memasukan perintah langsung ke mikrotik.
- 🔐 Autentikasi pengguna menggunakan Firebase Authentication
- 🔄 Otomatis login ke router MikroTik jika konfigurasi tersimpan
- 📊 Menampilkan informasi router (uptime, versi, board name, dll)
- 👥 Menampilkan jumlah user hotspot dan user online secara real-time
- 🌐 Backend menggunakan PHP + PEAR2 RouterOS Library
- ☁️ Konfigurasi dan kredensial tersimpan aman di Firebase Realtime Database
- 🚧 Dan lainnya dalam tahap pembangunan.

### Untuk saat ini `UI` hanya tersedia di `DESKTOP / PC`.

## 📁 Struktur Direktori

```pgsql
mikrodash-v2/
├── backend/
├── secret/
│   └── firebase-adminsdk.json
├─── php/
│   ├── vendor/PEAR2/Net/RouterOS/...
│   ├── firebase_init.php
│   ├── device_info.php
│   ├── user_stats.php
│   └── ...
├──── vendor/
├── frontend/
│   ├── index.html
│   ├── dashboard.js
│   ├── assets/css/main.css
│   ├── desktop/
│   ├── mobile/
├── .env
└── README.md
```


![Dashboard Screenshot](/frontend/Screenshot-Terminal.png)


## ⚙️ Instalasi & Setup

### 1. Clone Repository

```bash
git clone https://github.com/daffadevhosting/mikrodash-v2.git
cd mikrodash-v2
```
### 2. Pasang Dependensi

PEAR2 RouterOS Library disimpan secara manual di ```backend/php/vendor/PEAR2```

Pastikan file ```firebase-adminsdk.json``` tersedia di folder ```/secret/```

### 3. Konfigurasi .env

Buat file ```.env``` di root atau ```php/```:
```ini
FIREBASE_API_KEY=...
FIREBASE_PROJECT_ID=...
FIREBASE_DB_URL=...
FIREBASE_CREDENTIAL_PATH=/path/ke/firebase-adminsdk.json
```
### 4. Jalankan dengan Localhost

Jalankan server lokal untuk mengakses backend:
```bash
composer install
php -S localhost:5000
```
Jalankan server lokal untuk mengakses frontend:
```bash
bundle install
bundle exec jekyll serve
```
Untuk menjalankan keduanya secara bersamaan di linux:
```bash
chmod +x run.sh
./run.sh
```

Pastikan PHP berjalan di backend untuk endpoint API.

# 🛠 Teknologi yang Digunakan

* 🔥 Firebase Authentication & Realtime Database

* 🐘 PHP 8+

* 📡 MikroTik RouterOS API (via PEAR2 RouterOS library)

* 🌐 Desktop; Bootstrap 5, HTML, JS (vanilla) / Mobile; @ionic-8, HTML, JS (vanilla)

# 📌 Catatan Penting

Data login MikroTik disimpan di Firebase pada path ```mikrotik_config/{uid}```

Hanya user yang sudah memiliki konfigurasi MikroTik yang bisa mengakses dashboard

Untuk user baru, modal input login MikroTik akan muncul otomatis

# 📃 Lisensi

MIT License

> 🚀 Powered by **MikroDash v.2** — traffic dashboard by Daffa  
> ✨ Distributed for [Putri Dinar](https://github.com/putridinar)

---
Dibuat dengan ❤️ untuk [Putri] — untuk kemudahan memantau MikroTik secara real-time.

Silakan modifikasi bagian `author`, `github repo`, atau catatan penting lainnya sesuai kebutuhanmu. Kalau kamu mau nanti ada logo, GIF demo, atau panduan lanjutan, bisa ditambah juga.
