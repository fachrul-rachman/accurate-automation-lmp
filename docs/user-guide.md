# User Guide Accurate Check

Panduan ini berisi cara menggunakan aplikasi Accurate Check untuk proses pengecekan penerimaan barang.

## 1. Login

1. Buka aplikasi Accurate Check.
2. Masukkan email dan password.
3. Klik **Login**.
4. Setelah masuk, menu yang tampil akan mengikuti role akun Anda.

Jika ingin keluar dari aplikasi, klik tombol **Logout** di sidebar.

## 2. Role di Aplikasi

Ada 4 role utama:

- **Admin**: mengelola user, lokasi, dan monitoring PB.
- **Warehouse**: mencari PB, membuat round pemeriksaan, melanjutkan PB parsial, dan download PDF final.
- **User**: memeriksa hasil input Warehouse lalu memberi tanda tangan.
- **Kepala Gudang**: mengetahui round yang sudah ditandatangani User.

## 3. Panduan Admin

### Mengelola Location

1. Buka menu **Locations**.
2. Isi nama lokasi.
3. Isi **Accurate URL** sesuai lokasi.
4. Pilih status **Active** jika lokasi masih digunakan.
5. Klik **Create Location**.

Untuk mengubah location:

1. Klik **Edit** pada location.
2. Ubah data yang diperlukan.
3. Klik **Update Location**.

### Mengelola User

1. Buka menu **Users**.
2. Isi nama, email, password, role, dan location.
3. User bisa memiliki lebih dari satu location.
4. Pilih status **Active** jika akun boleh digunakan.
5. Klik **Create User**.

Untuk mengubah user:

1. Klik **Edit** pada user.
2. Ubah data yang diperlukan.
3. Kosongkan password jika tidak ingin mengganti password.
4. Klik **Update User**.

Untuk menghapus user:

1. Klik **Delete**.
2. Konfirmasi penghapusan.

### Monitoring PB

1. Buka menu **Monitoring PB**.
2. Cari berdasarkan nomor PB, nomor PO, atau supplier.
3. Klik detail untuk melihat status dan riwayat round.
4. Jika PDF final sudah tersedia, Admin bisa melihat atau download dari halaman monitoring.

## 4. Panduan Warehouse

### Mencari PB Accurate

1. Buka menu **Cari PB**.
2. Pilih location.
3. Masukkan nomor PB.
4. Klik **Cari PB**.
5. Jika PB ditemukan, aplikasi akan menampilkan preview data dari Accurate.

Data belum tersimpan hanya karena PB dicari. Data baru tersimpan setelah Warehouse mengisi round dan klik **Simpan Draft Round**.

### Mengisi Item Checklist

1. Pada halaman hasil PB, buka bagian **Item Checklist**.
2. Centang **Datang** untuk barang yang benar-benar datang.
3. Untuk barang yang dicentang, lengkapi:
   - kualitas barang: **Baik** atau **Rusak**
   - quantity: **Sesuai**, **Tidak Sesuai**, atau **Sebagian**
   - received quantity jika quantity tidak sesuai atau sebagian
   - jumlah rusak jika ada barang rusak
   - foto item
   - foto kerusakan jika kualitas barang rusak
4. Barang yang tidak dicentang dianggap belum datang.

Tombol **Lanjut Detail Round** hanya bisa diklik jika item yang dicentang sudah lengkap.

### Mengisi Detail Round

1. Klik **Lanjut Detail Round**.
2. Pilih **User** yang akan menerima round.
3. Centang **Spesifikasi sesuai PO** jika sesuai.
4. Isi notes jika diperlukan.
5. Upload **Foto Surat Jalan**.
6. Upload **Foto Penerima**.
7. Klik **Simpan Draft Round**.

Setelah draft disimpan, round akan masuk ke User untuk tanda tangan.

### Melihat Round PB

1. Buka menu **Round PB**.
2. Lihat daftar PB dan statusnya.
3. Klik detail untuk melihat isi round.
4. Status **Parsial** berarti PB belum selesai sepenuhnya.

PB bisa menjadi parsial jika:

- ada item yang belum datang
- ada quantity item yang belum lengkap
- ada item rusak

### Melanjutkan PB Parsial

Jika PB parsial sudah selesai ditandatangani sampai Kepala Gudang, Warehouse bisa melanjutkan round dari daftar **Round PB**.

1. Buka menu **Round PB**.
2. Cari PB dengan chip **Parsial**.
3. Klik **Lanjut Round**.
4. Isi round berikutnya untuk barang yang baru datang atau perbaikan quantity.
5. Simpan round seperti biasa.

### Download PDF Final

PDF final hanya tersedia setelah PB selesai lengkap dan sudah diketahui Kepala Gudang.

1. Buka menu **Round PB**.
2. Cari PB yang sudah selesai.
3. Klik **Download PDF Final**.

## 5. Panduan User

### Melihat Round yang Menunggu Tanda Tangan

1. Login sebagai User.
2. Buka menu **Menunggu Tanda Tangan**.
3. Klik round yang ingin diperiksa.
4. Periksa informasi PB, item, quantity, foto item, foto surat jalan, dan foto penerima.
5. Jika ada chip **Parsial**, baca alasan parsial yang tampil di halaman.

### Memberi Tanda Tangan

1. Pastikan data sudah diperiksa.
2. Klik **Diterima Peminta**.
3. Setelah ditandatangani, round akan masuk ke Kepala Gudang.

User tidak melakukan approve/reject. Tombol tanda tangan hanya menandakan round sudah diterima/diperiksa.

### Melihat Riwayat

1. Buka menu **Riwayat**.
2. Lihat round yang sudah pernah ditandatangani.

## 6. Panduan Kepala Gudang

### Melihat Round yang Menunggu Tanda Tangan

1. Login sebagai Kepala Gudang.
2. Buka menu **Menunggu Tanda Tangan**.
3. Klik round yang ingin dilihat.
4. Periksa informasi PB, item, quantity, foto, dan tanda tangan User.
5. Jika ada chip **Parsial**, baca alasan parsial yang tampil di halaman.

### Memberi Tanda Tangan

1. Pastikan data sudah dilihat.
2. Klik **Diketahui**.
3. Setelah ditandatangani, round akan kembali terlihat oleh Warehouse sesuai status PB.

Jika PB sudah lengkap, sistem akan menyiapkan PDF final untuk Warehouse.

### Melihat Riwayat

1. Buka menu **Riwayat**.
2. Lihat round yang sudah pernah diketahui.

## 7. Arti Status Umum

- **Menunggu tanda tangan User**: Warehouse sudah mengirim round dan menunggu User.
- **Menunggu tanda tangan Kepala Gudang**: User sudah tanda tangan dan menunggu Kepala Gudang.
- **Parsial**: PB belum selesai karena ada barang belum datang, quantity kurang, atau barang rusak.
- **Selesai**: PB sudah lengkap dan proses tanda tangan sudah selesai.

## 8. Catatan Penggunaan

- Gunakan koneksi internet saat upload foto dan tanda tangan.
- Pastikan foto jelas sebelum menyimpan round.
- Jangan membuat round baru untuk PB yang sama jika round sebelumnya masih berjalan.
- Jika data Accurate terasa tidak sesuai, cek kembali location yang dipilih.
- Jika tombol tidak bisa diklik, biasanya ada data wajib yang belum lengkap.
