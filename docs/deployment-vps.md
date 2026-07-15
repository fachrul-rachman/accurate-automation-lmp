# Deployment VPS

Dokumen ini menjelaskan kebutuhan deploy aplikasi Accurate Check di VPS.

## Service yang Wajib Jalan

- Web server: Nginx atau Apache, diarahkan ke folder `public/`.
- PHP-FPM: PHP `8.3` atau lebih baru sesuai `composer.json`.
- Database: MySQL/MariaDB/PostgreSQL/SQLite sesuai konfigurasi Laravel, disarankan MySQL/MariaDB untuk VPS.
- Storage object: Cloudflare R2 untuk foto dan PDF.

Service yang belum wajib untuk MVP:

- Queue worker belum wajib, karena belum ada job queue bisnis.
- Scheduler/cron Laravel belum wajib, karena belum ada scheduled task bisnis.
- Node.js tidak wajib jalan sebagai service runtime; Node hanya dipakai saat build asset.

## Informasi yang Perlu Disiapkan

### Domain dan App

```env
APP_NAME="Accurate Check"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://domain-kamu.com
APP_TIMEZONE=Asia/Jakarta
```

Generate `APP_KEY` sekali saja:

```bash
php artisan key:generate
```

### Database

Contoh MySQL/MariaDB:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=accurate_check
DB_USERNAME=accurate_check
DB_PASSWORD=isi_password_database
```

### Session, Cache, Queue

Konfigurasi sederhana:

```env
SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
```

Karena `SESSION_DRIVER=database`, migration wajib dijalankan.

### Accurate

Token Accurate disimpan di env per lokasi:

```env
ACCURATE_KARAWANG_API_TOKEN=isi_token_karawang
ACCURATE_KARAWANG_SIGNATURE_SECRET=isi_signature_secret_karawang

ACCURATE_TANGERANG_API_TOKEN=isi_token_tangerang
ACCURATE_TANGERANG_SIGNATURE_SECRET=isi_signature_secret_tangerang

ACCURATE_TIMEOUT=15
```

URL Accurate tetap diisi per location dari halaman admin, contoh:

- Karawang: `https://zeus.accurate.id`
- Tangerang: `https://iris.accurate.id`

Nama location dipakai sebagai key env. Jadi pastikan nama location tetap `Karawang` dan `Tangerang` untuk MVP ini.

### Cloudflare R2

Gunakan disk `r2` untuk file goods receipt:

```env
GOODS_RECEIPT_DISK=r2

CLOUDFLARE_R2_ACCESS_KEY_ID=isi_access_key
CLOUDFLARE_R2_SECRET_ACCESS_KEY=isi_secret_key
CLOUDFLARE_R2_BUCKET=isi_nama_bucket
CLOUDFLARE_R2_ENDPOINT=https://account_id.r2.cloudflarestorage.com
CLOUDFLARE_R2_URL=
CLOUDFLARE_R2_REGION=auto
CLOUDFLARE_R2_USE_PATH_STYLE_ENDPOINT=false
```

File foto dan PDF tetap diakses lewat route aplikasi, bukan langsung dari URL publik R2. Jadi bucket bisa tetap private.

## Langkah Deploy

1. Upload/pull source code ke VPS.
2. Buat file `.env` production.
3. Install dependency PHP:

```bash
composer install --no-dev --optimize-autoloader
```

4. Build asset frontend:

```bash
npm ci
npm run build
```

5. Jalankan migration:

```bash
php artisan migrate --force
```

6. Seed data awal jika database masih kosong:

```bash
php artisan db:seed --class=DatabaseSeeder --force
```

Seeder membuat admin awal:

- Email: `admin@x.com`
- Password: `password`

Ganti password admin setelah login pertama.

7. Optimasi Laravel:

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

8. Pastikan permission folder:

```bash
chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rw storage bootstrap/cache
```

Sesuaikan user `www-data` dengan user PHP-FPM di VPS.

## Contoh Nginx

```nginx
server {
    listen 80;
    server_name domain-kamu.com;
    root /var/www/accurate-check/public;

    index index.php index.html;

    client_max_body_size 20M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

Pasang HTTPS memakai Certbot atau SSL provider lain sebelum dipakai production.

## Jika Nanti Queue Dipakai

Saat ini queue worker belum wajib. Jika nanti ada job async, jalankan worker via Supervisor:

```ini
[program:accurate-check-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/accurate-check/artisan queue:work --sleep=3 --tries=3 --timeout=120
autostart=true
autorestart=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/accurate-check/storage/logs/worker.log
```

## Jika Nanti Scheduler Dipakai

Saat ini scheduler belum wajib. Jika nanti ada scheduled task, tambah cron:

```cron
* * * * * cd /var/www/accurate-check && php artisan schedule:run >> /dev/null 2>&1
```

## Checklist Setelah Deploy

- Halaman login bisa dibuka.
- Admin bisa login.
- Admin bisa membuat user dan location.
- Location memiliki URL Accurate yang benar.
- Warehouse bisa cari nomor PB sesuai location.
- Upload foto tersimpan tanpa error.
- User bisa melihat foto dan tanda tangan.
- Kepala gudang bisa tanda tangan.
- Warehouse bisa download PDF final.
- `/manifest.json` dan `/service-worker.js` bisa diakses.

## Log yang Dicek Saat Error

- Laravel: `storage/logs/laravel.log`
- Nginx: `/var/log/nginx/error.log`
- PHP-FPM: sesuai versi PHP di VPS, contoh `/var/log/php8.3-fpm.log`
