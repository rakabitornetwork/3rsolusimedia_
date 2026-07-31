# 3R Solusi Media

Aplikasi manajemen operasional **RT/RW Net** untuk **3R Solusi Media**: panel admin, landing page, pelanggan PPPoE, billing, hotspot MikroTik, integrasi GenieACS, serta pembaruan aplikasi dari GitHub.

Repositori: [github.com/rakabitornetwork/3rsolusimedia_](https://github.com/rakabitornetwork/3rsolusimedia_)

Versi saat ini mengikuti **git tag** di GitHub (contoh: `1.8`). Status versi dapat dilihat di panel admin **Sistem → Update**.

---

## Fitur utama

- Landing page & konten website (Inertia)
- Manajemen pengguna (Superadmin / Admin / Teknisi)
- Pelanggan PPPoE + titik GPS (Leaflet)
- Paket langganan & tagihan (prorata / jatuh tempo)
- Router MikroTik (RouterOS API): secret PPPoE, hotspot voucher/profile, sesi
- Integrasi GenieACS (NBI) untuk perangkat CPE/ONT
- Pengaturan situs & aplikasi (branding, billing, notifikasi)
- Menu **Update**: cek & pull dari GitHub tanpa `npm` di VPS

---

## Teknologi

| Lapisan | Teknologi |
|--------|-----------|
| Backend | PHP **8.3+**, **Laravel 13**, Inertia Laravel |
| Frontend | **React 19**, **Inertia.js**, **Vite 8**, **Tailwind CSS 4** |
| UI | Lucide icons, React Day Picker, Leaflet (peta GPS) |
| Database | MySQL/MariaDB (produksi) atau SQLite (pengembangan) |
| MikroTik | `evilfreelancer/routeros-api-php` |
| ACS | GenieACS NBI (HTTP API, biasanya port `7557`) |
| Lainnya | Ziggy (route helper), date-fns |

Aset frontend hasil `npm run build` disimpan di `public/build` dan **ikut di-commit** ke Git, agar VPS tidak perlu Node.js/npm.

---

## Persyaratan server (VPS)

- Ubuntu/Debian (disarankan) atau distro Linux setara
- **PHP 8.3+** dengan ekstensi: `cli`, `fpm`, `mbstring`, `xml`, `curl`, `zip`, `bcmath`, `tokenizer`, `pdo_mysql`, `openssl`, `intl` (disarankan)
- **Composer 2**
- **MySQL 8** / **MariaDB 10.6+**
- **Nginx** atau Apache (document root mengarah ke folder `public/`)
- **Git**
- Akses SSH
- **Tidak wajib** Node.js / npm di VPS

Opsional:

- `supervisor` / systemd untuk queue worker (`php artisan queue:work`) jika antrean aktif dipakai
- Sertifikat SSL (Let's Encrypt)

---

## Instalasi di VPS

Contoh path: `/var/www/3rsolusimedia`. Sesuaikan domain, user, dan kredensial database Anda.

### 1. Clone repositori

```bash
sudo mkdir -p /var/www
sudo git clone https://github.com/rakabitornetwork/3rsolusimedia_.git /var/www/3rsolusimedia
cd /var/www/3rsolusimedia
```

Pastikan folder `public/build/manifest.json` ada (hasil build Vite dari repositori).

### 2. Dependency PHP

```bash
composer install --no-dev --optimize-autoloader
```

### 3. Environment

```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env` minimal:

```env
APP_NAME="3R Solusi Media"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://domain-anda.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=3rsolusimedia
DB_USERNAME=user_db
DB_PASSWORD=password_db

SESSION_DRIVER=database
QUEUE_CONNECTION=database
CACHE_STORE=database
```

Buat database MySQL terlebih dahulu, lalu:

```bash
php artisan migrate --force
php artisan db:seed --force
php artisan storage:link
```

### 4. Izin folder

```bash
sudo chown -R www-data:www-data /var/www/3rsolusimedia
sudo find /var/www/3rsolusimedia -type f -exec chmod 644 {} \;
sudo find /var/www/3rsolusimedia -type d -exec chmod 755 {} \;
sudo chmod -R ug+rwx storage bootstrap/cache
```

Sesuaikan user web server (`www-data`, `nginx`, dll.).

### 5. Nginx (contoh)

```nginx
server {
    listen 80;
    server_name domain-anda.com;
    root /var/www/3rsolusimedia/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;
    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

Aktifkan site, uji konfigurasi, lalu reload Nginx. Pasang SSL dengan Certbot bila perlu.

### 6. Optimasi Laravel (produksi)

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 7. Login awal

Setelah `db:seed`, akun Superadmin default (dari seeder):

| Field | Nilai |
|-------|--------|
| Email | `amon@teslatech.my.id` |
| Password | `gantengmax` |

**Ganti password segera** setelah login pertama.

Panel admin: `https://domain-anda.com/admin/login`

---

## Update aplikasi di VPS

Karena `public/build` sudah ada di Git, update **tanpa npm**:

### Via panel admin

1. Login sebagai Superadmin/Admin
2. Buka **Sistem → Update**
3. **Cek update** → jika ada update, **Pull dari GitHub**
4. Jika ada migrasi baru, di SSH jalankan:

```bash
cd /var/www/3rsolusimedia
php artisan migrate --force
composer install --no-dev --optimize-autoloader
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### Via SSH

```bash
cd /var/www/3rsolusimedia
git pull origin main
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Jangan jalankan `npm run build` di VPS kecuali Anda sengaja memasang Node.js.

---

## Pengembangan lokal (Laragon / Windows)

```bash
git clone https://github.com/rakabitornetwork/3rsolusimedia_.git
cd 3rsolusimedia
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed
php artisan storage:link
npm install
npm run build
```

Untuk hot-reload frontend:

```bash
npm run dev
```

atau `composer run dev` (server + queue + vite bersamaan).

Setiap perubahan frontend yang akan di-deploy ke VPS:

```bash
npm run build
git add public/build
git commit -m "Build frontend assets"
git push origin main
```

---

## Integrasi eksternal

### MikroTik

Tambahkan router di **Jaringan → Router MikroTik** (host, port API, user/password). Pastikan API RouterOS dapat diakses dari VPS.

### GenieACS

Di **Jaringan → GenieACS**, isi URL NBI (contoh `http://IP-GENIEACS:7557`), aktifkan integrasi, lalu tes koneksi. API key / basic auth opsional sesuai konfigurasi GenieACS Anda.

---

## Struktur singkat

```
app/                 # Controllers, Models, Services
database/migrations  # Skema database
resources/js         # React + Inertia pages
resources/css        # Tailwind
public/              # Document root web (termasuk public/build)
routes/web.php       # Route web & admin
```

---

## Lisensi

Proyek ini memakai lisensi yang mengikuti repositori (lihat `composer.json` / kebijakan pemilik repositori). Digunakan untuk operasional 3R Solusi Media.
