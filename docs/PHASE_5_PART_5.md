# CODING AGENT PROMPT — PHASE 5 PART 5: AUDIO UI

## 1. Peran dan konteks proyek

Kamu bertindak sebagai Senior Full-Stack Engineer yang mengerjakan proyek **MochyFami Content Studio**.

Stack proyek:

- Backend: Laravel 13, PHP 8.4, MySQL, Sanctum.
- Frontend: React, Vite, TypeScript, Tailwind CSS.
- Media processing: FFmpeg/ffprobe.
- Audio assets disimpan menggunakan private storage.
- Routing frontend menggunakan routing yang sudah ada dalam proyek.

Workflow produksi konten:

`IDEA → SELECTED → RESEARCH → FACT CHECK → SCRIPT → VISUAL PLAN → ASSET COLLECTION → TTS → SUBTITLE → VIDEO RENDER → THUMBNAIL → QUALITY REVIEW → APPROVED → PUBLISHED → ANALYTICS`

Phase 5 memiliki enam bagian. Bagian 1–4 sudah selesai. Sekarang kerjakan **hanya Phase 5 Part 5 — Audio UI**.

### Baseline dan checkpoint terakhir

- Phase 5 Part 1 commit: `8283fa2`
- Phase 5 Part 2 commit: `ed6febf`
- Phase 5 Part 3 commit: `4e4c43f`
- Phase 5 Part 4 commit: `5ab9664`
- Baseline terakhir yang dilaporkan: **1.608 backend tests, 5.039 assertions**.
- Tidak ada push ke remote yang diizinkan.

Anggap informasi tersebut sebagai laporan checkpoint, bukan bukti bahwa working tree saat ini bersih. Verifikasi kondisi aktual sebelum mengubah file.

## 2. Aturan wajib sebelum implementasi

### 2.1 Pre-flight inspection

Sebelum menulis atau mengubah kode:

1. Periksa `git status --short`.
2. Periksa branch aktif dan `git log -1 --oneline`.
3. Identifikasi perubahan tracked, untracked, dan file milik pengguna.
4. Jangan menghapus, menimpa, memindahkan, atau memasukkan file untracked milik pengguna tanpa alasan yang sah dan izin yang sesuai.
5. Baca spesifikasi terkunci `docs/PHASE_5_TTS.md` secara menyeluruh.
6. Baca implementation report yang tersedia untuk Part 2, Part 3, dan Part 4:
    - `docs/PHASE_5_PART_2_IMPLEMENTATION.md`
    - `docs/PHASE_5_PART_3_IMPLEMENTATION.md`
    - `docs/PHASE_5_PART_4_IMPLEMENTATION.md`
7. Periksa apakah brief mentah `docs/PHASE_5_PART_5.md` sudah ada. Jika ada, baca dan jangan menimpanya.
8. Periksa implementasi frontend dan backend yang relevan, minimal:
    - `ProjectDetailPage` dan sistem tab yang digunakan.
    - `pipelineSteps` atau representasi workflow pipeline.
    - Panel-panel proyek yang sudah ada sebagai referensi UI.
    - API client, konfigurasi base URL, authentication, dan pola error handling.
    - TypeScript types untuk project, visual plan, visual plan items, assets, dan TTS jika sudah tersedia.
    - Route TTS, controller, resource, service, dan response envelope backend.
    - TTS readiness dan TTS manifest.
    - Route atau mekanisme authorized playback/download audio yang sudah tersedia, jika ada.
    - Konvensi testing frontend dan backend.
9. Periksa apakah sudah ada komponen Audio/TTS parsial yang dapat dilanjutkan, bukan digandakan.

### 2.2 Buat rencana terlebih dahulu

Sebelum implementasi, laporkan secara ringkas:

- Kondisi Git awal.
- Arsitektur frontend yang ditemukan.
- Endpoint dan response shape yang benar-benar tersedia.
- Cara aman memutar file audio privat, jika didukung.
- Komponen/file yang akan dibuat atau diubah.
- Test yang akan ditambahkan atau dijalankan.
- Risiko dan batasan yang ditemukan.

Setelah laporan pre-flight, lanjutkan implementasi jika tidak ditemukan konflik dengan spesifikasi atau kondisi working tree.

Jika ada kontradiksi antara prompt ini dan `docs/PHASE_5_TTS.md`, spesifikasi terkunci menjadi acuan. Jika keputusan memerlukan endpoint atau perubahan backend baru yang tidak diizinkan spesifikasi, jangan mengarang solusi: jelaskan blocker dan hentikan bagian yang bergantung padanya.

## 3. Tujuan Part 5

Buat UI audio/TTS yang terintegrasi dengan workflow proyek yang sudah ada.

Pengguna harus dapat:

1. Membuka bagian Audio dari detail proyek.
2. Melihat Visual Plan items dan status audio masing-masing item.
3. Melihat track TTS yang tersedia beserta riwayat yang dikembalikan backend.
4. Membuat audio secara manual.
5. Meregenerasi audio secara manual.
6. Mengarsipkan track dengan konfirmasi.
7. Melihat readiness blockers, warnings, dan informasi ringkas.
8. Memeriksa manifest produksi TTS yang dibentuk backend.
9. Memutar audio hanya melalui mekanisme authorized playback yang sudah tersedia dan benar-benar aman.
10. Memahami status loading, keberhasilan, dan kegagalan tanpa harus membuka DevTools.

Semua perubahan harus mengikuti desain dan pola interaksi aplikasi yang sudah ada. Jangan memperkenalkan desain sistem baru jika komponen atau konvensi proyek saat ini sudah mencukupi.

## 4. Integrasi UI dan navigasi

### 4.1 Penempatan

Utamakan integrasi melalui tab atau panel Audio pada `ProjectDetailPage`, sesuai arsitektur aktual.

- Periksa cara tab yang sudah ada dirender dan bagaimana project ID serta script version ID diperoleh.
- Ikuti pola yang sudah digunakan oleh tab Research, Script, Visual Plan, dan Assets.
- Jika spesifikasi terkunci secara eksplisit menentukan penempatan lain, ikuti spesifikasi tersebut.
- Jangan membangun halaman terpisah atau mengubah `ProductionPage` yang masih berupa stub tanpa alasan yang didukung arsitektur dan spesifikasi.
- Jangan merombak routing yang tidak terkait.
- Jangan menambahkan tab ke workflow jika status pipeline yang sebenarnya belum mendukung perubahan itu; ikuti model workflow yang sudah ada.

### 4.2 Siklus hidup panel

Saat panel Audio dibuka:

- Ambil data melalui endpoint read-only yang sesuai.
- Tampilkan loading state dan error state.
- Hindari permintaan duplikat yang tidak perlu.
- Tangani pergantian project, script version, dan visual plan secara benar agar data lama tidak tampil sebagai data proyek baru.
- Pastikan respons request lama tidak menimpa data yang lebih baru.
- Jangan otomatis membuat atau meregenerasi audio saat panel dibuka.

Pembuatan audio harus selalu merupakan tindakan eksplisit pengguna.

## 5. Kontrak API

Gunakan endpoint yang telah diimplementasikan pada Phase 5 Part 2–4 berikut sebagai daftar pemeriksaan. **Verifikasi route dan response envelope aktual dari source code sebelum menggunakannya.** Jangan menebak nama field, struktur JSON, atau bentuk response.

### Track TTS per Visual Plan item

`GET /api/v1/projects/{project}/script/versions/{version}/visual-plan/items/{item}/tts`

Mengambil track yang tersedia untuk item tersebut.

`POST /api/v1/projects/{project}/script/versions/{version}/visual-plan/items/{item}/tts`

Membuat audio baru berdasarkan tindakan eksplisit pengguna.

`POST /api/v1/projects/{project}/script/versions/{version}/visual-plan/items/{item}/tts/{track}/regenerate`

Meregenerasi audio. Perhatikan bahwa regenerasi membuat track baru dan mempertahankan riwayat sebelumnya.

`DELETE /api/v1/projects/{project}/script/versions/{version}/visual-plan/items/{item}/tts/{track}`

Mengarsipkan track, bukan menghapus file audio secara fisik.

### TTS readiness

`GET /api/v1/projects/{project}/script/versions/{version}/visual-plan/tts-readiness`

Gunakan hasil backend sebagai sumber kebenaran untuk blockers, warnings, kesiapan item, dan ringkasan readiness.

### TTS manifest

`GET /api/v1/projects/{project}/script/versions/{version}/visual-plan/tts-manifest`

Gunakan manifest backend sebagai sumber kebenaran untuk audio yang tersedia dan metadata yang diizinkan.

### Aturan implementasi API

- Gunakan API client dan pola autentikasi yang sudah ada.
- Jangan membangun URL API dengan asumsi yang bertentangan dengan konfigurasi proyek.
- Jangan menduplikasi endpoint di banyak komponen jika API service bersama lebih sesuai.
- Jangan membuat ulang aturan readiness secara manual di React.
- Jangan menganggap track `completed` otomatis merupakan audio terkini; gunakan informasi dan aturan aktual yang diberikan backend.
- Jangan menyamakan status archived dengan penghapusan file.
- Jangan mengubah atau memperluas kontrak backend hanya demi menyederhanakan frontend.
- Jangan menampilkan stack trace atau detail internal exception kepada pengguna.

## 6. Rancangan interaksi Audio UI

### 6.1 Ringkasan readiness

Tampilkan ringkasan readiness berdasarkan response backend, termasuk field yang memang tersedia.

UI harus membedakan dengan jelas:

- Siap atau belum siap.
- Blockers yang menghalangi kesiapan.
- Warnings yang perlu diperhatikan tetapi tidak selalu memblokir.
- Informasi ringkasan, misalnya jumlah item siap atau item yang belum memiliki audio terkini, jika field tersebut tersedia.

Gunakan istilah yang mudah dipahami pengguna berbahasa Indonesia.

Jangan mengarang status baru, menghitung readiness berbeda dari backend, atau menyembunyikan blocker.

Jika tidak ada visual plan atau belum ada data, tampilkan empty state yang jelas sesuai response aktual.

### 6.2 Daftar item audio

Tampilkan setiap Visual Plan item dengan informasi relevan yang benar-benar tersedia, seperti:

- Urutan item.
- Teks narasi.
- Status atau kondisi audio.
- Track audio terkini jika ada.
- Durasi aktual jika tersedia.
- Provider, model, voice, language, dan speed jika disediakan.
- Status atau riwayat track lain yang relevan.

Gunakan `order ASC` atau urutan kanonis yang ditetapkan backend. Jangan mengandalkan urutan array secara kebetulan jika kontrak mengharuskan pengurutan.

Pastikan perbedaan status berikut dapat dipahami pengguna:

- Belum memiliki audio.
- Audio sedang dibuat.
- Audio berhasil dibuat.
- Audio gagal dibuat.
- Track diarsipkan.
- Track lama tidak cocok dengan narasi saat ini, jika kondisi ini diberikan oleh backend.

Jangan memunculkan status yang tidak didukung model backend.

Jika metadata tidak tersedia, tampilkan fallback yang wajar; jangan mengarang nilai.

### 6.3 Membuat audio

Sediakan aksi eksplisit untuk membuat audio bagi item yang sesuai.

Ketika pengguna menekan tombol:

1. Validasi prasyarat UI yang memang dapat dipastikan dari data yang tersedia.
2. Kirim request ke endpoint generate yang benar.
3. Cegah pengiriman request ganda pada item yang sama selama request berjalan.
4. Tampilkan loading state pada aksi terkait.
5. Tampilkan hasil atau error yang aman dibaca pengguna.
6. Setelah berhasil, refresh track history, readiness, dan manifest yang terdampak.

Jangan meniru validasi backend secara berlebihan. Backend tetap merupakan sumber kebenaran.

### 6.4 Regenerasi audio

Sediakan aksi regenerasi untuk track yang sesuai.

- Gunakan endpoint regenerasi yang benar.
- Minta konfirmasi jika sesuai dengan pola UX aplikasi.
- Jelaskan bahwa regenerasi menghasilkan track baru dan riwayat sebelumnya tetap ada.
- Jangan mengganti atau menghapus track lama secara optimistis sebelum backend mengonfirmasi.
- Setelah berhasil, refresh data terkait.
- Jangan menjalankan regenerasi otomatis ketika narasi berubah atau panel dibuka.

### 6.5 Mengarsipkan track

Sediakan aksi archive dengan dialog konfirmasi yang jelas.

Konfirmasi harus menjelaskan bahwa tindakan ini mengarsipkan track, bukan menghapus file audio secara permanen.

- Gunakan endpoint DELETE yang telah ditentukan.
- Jangan membuat DELETE endpoint baru.
- Jangan menghapus Asset dari UI secara optimistis sebelum server mengonfirmasi.
- Setelah berhasil, refresh track history, readiness, dan manifest yang terdampak.
- Tangani track yang sudah diarsipkan atau respons error dengan aman.
- Ikuti aturan status terminal dan transisi yang telah ditetapkan backend.

### 6.6 Audio playback: pemeriksaan keamanan wajib

Sebelum menambahkan kontrol pemutar audio, cari mekanisme playback atau streaming yang sudah tersedia.

Jika tersedia:

- Gunakan hanya mekanisme yang terautentikasi dan terotorisasi untuk project serta asset yang bersangkutan.
- Ikuti mekanisme token/cookie/header yang digunakan API client.
- Tangani error loading dan akses yang ditolak.
- Jangan menyimpan atau membagikan URL privat secara sembarangan.

Jika tidak tersedia:

- Jangan mengarang endpoint playback.
- Jangan menggunakan `Storage::url()` atau membuat asset privat menjadi public.
- Jangan menampilkan `file_path`, storage key, filesystem path, atau URL internal.
- Jangan menganggap `asset_id` dapat langsung dipakai sebagai URL audio.
- Jangan menambah endpoint backend baru tanpa dasar dari spesifikasi terkunci.
- Laporkan keterbatasan playback secara eksplisit dalam implementation report.
- Tetap selesaikan fitur UI lain yang tidak bergantung pada playback.

Jangan menganggap fitur playback selesai jika UI hanya memiliki tombol yang tidak berfungsi.

### 6.7 TTS manifest

Tampilkan informasi manifest dengan cara yang proporsional, misalnya bagian detail yang dapat dibuka pengguna.

Gunakan data manifest dari backend, bukan menyusun ulang manifest sendiri di frontend.

Jika UI menampilkan daftar segmen, ikuti urutan dan struktur response aktual.

Jangan menampilkan `file_path`, storage path, ukuran internal yang tidak diizinkan, atau data privat lain yang tidak diperlukan.

Jangan menambahkan timeline editor, subtitle editor, FFmpeg rendering, atau integrasi Handoff dalam Part 5.

## 7. Error handling dan state

UI harus memiliki state yang eksplisit dan konsisten.

Tangani setidaknya:

- Loading awal.
- Refresh.
- Empty state.
- Request berhasil.
- Validasi `422`.
- Unauthorized `401`.
- Resource tidak ditemukan atau scope tidak cocok `404`.
- Kegagalan provider `502`.
- Network error.
- Error tak terduga.

Persyaratan:

1. Gunakan pola notifikasi dan error handling aplikasi yang sudah ada.
2. Jangan menampilkan stack trace, secret, path privat, atau raw exception.
3. Jangan menganggap semua error sebagai kegagalan provider.
4. Jangan menghapus data yang sudah tampil hanya karena refresh gagal.
5. Beri pesan yang bisa ditindaklanjuti pengguna bila memungkinkan.
6. Pastikan tombol terkait dinonaktifkan saat request berjalan.
7. Gunakan loading state per item atau per aksi bila sesuai, bukan memblokir seluruh halaman tanpa alasan.
8. Pastikan aksi archive memiliki konfirmasi.
9. Pastikan tidak terjadi race condition saat pengguna berganti proyek atau versi.
10. Jangan menampilkan pesan sukses sebelum server mengonfirmasi keberhasilan.

## 8. Batasan arsitektur yang tidak boleh dilanggar

Phase 5 Part 5 merupakan pekerjaan UI dan integrasi API. Pertahankan batasan berikut:

- Tidak ada pemanggilan provider TTS otomatis ketika panel dibuka.
- Tidak ada auto-regeneration.
- Tidak ada queue atau background job baru.
- Tidak ada subtitle generation.
- Tidak ada video rendering.
- Tidak ada musik latar.
- Tidak ada auto-segmentation narasi.
- Tidak ada perubahan pipeline publikasi.
- Tidak ada integrasi Handoff.
- Tidak ada perubahan schema database kecuali spesifikasi secara eksplisit mengizinkannya dan ada blocker yang telah didokumentasikan serta disetujui.
- Tidak ada endpoint publik untuk asset privat.
- Tidak ada pembocoran path file atau storage key.
- Tidak ada penghapusan riwayat track.
- Tidak ada perubahan backend readiness/manifest hanya untuk memudahkan frontend.
- Tidak ada dependency baru tanpa kebutuhan yang jelas dan justifikasi.
- Tidak ada perubahan di luar scope Part 5.

Jangan melakukan pekerjaan Part 6 dalam task ini.

## 9. Struktur kode dan kualitas implementasi

Ikuti konvensi proyek yang ditemukan saat pre-flight.

Utamakan:

- Komponen React yang mengikuti pola panel existing.
- API service terpusat jika pola tersebut sudah digunakan.
- TypeScript types yang cocok dengan kontrak backend aktual.
- Pemisahan data fetching dari komponen presentasi bila sesuai arsitektur saat ini.
- Fungsi refresh yang terkontrol untuk data track, readiness, dan manifest.
- Komponen kecil yang dapat diuji.
- Aksesibilitas tombol, dialog, status, dan kontrol audio.
- Responsive layout yang konsisten dengan aplikasi.

Hindari:

- `any` yang tidak perlu.
- Duplikasi tipe response.
- Fetch logic tersebar tanpa pola.
- State global baru jika local state sudah cukup.
- Optimistic updates yang dapat merusak konsistensi riwayat track.
- Hard-coded status atau provider yang tidak berasal dari backend.
- Refactor besar yang tidak dibutuhkan Part 5.

## 10. Testing dan verifikasi

Periksa test setup frontend yang sebenarnya sebelum memutuskan strategi testing. Jangan mengklaim test UI berjalan jika test runner atau test tersebut tidak tersedia.

### 10.1 Frontend tests

Jika proyek memiliki test runner, tambahkan test yang sesuai dengan konvensinya. Prioritaskan:

1. Panel Audio merender readiness dari response backend.
2. Daftar item dan track history ditampilkan sesuai response.
3. Generate memanggil endpoint yang benar.
4. Regenerate memanggil endpoint yang benar dan mempertahankan riwayat dalam UI setelah refresh.
5. Archive memerlukan konfirmasi dan menggunakan endpoint yang benar.
6. Tidak ada generate otomatis ketika panel dibuka.
7. Tombol aksi dinonaktifkan selama request berjalan.
8. Blockers dan warnings dibedakan.
9. Error `401`, `404`, `422`, `502`, dan network ditangani dengan benar.
10. Respons request lama tidak menimpa data proyek atau versi yang lebih baru.
11. Manifest ditampilkan sesuai data backend.
12. Playback hanya memakai mekanisme privat yang telah diverifikasi.

Jika test runner belum tersedia, jangan langsung menambah dependency besar. Laporkan keterbatasan dan lakukan pemeriksaan alternatif yang relevan.

### 10.2 Backend regression

Walaupun perubahan utama frontend, jalankan backend regression yang relevan agar kontrak TTS Part 2–4 tetap utuh.

Baseline yang dilaporkan adalah **1.608 tests / 5.039 assertions**. Verifikasi angka aktual melalui hasil eksekusi; jangan sekadar mengulang angka baseline.

Jika full suite gagal:

- Identifikasi apakah kegagalan baru atau sudah ada sebelumnya.
- Jangan mengubah test atau kode backend yang tidak terkait untuk memaksa green.
- Laporkan nama test, error, dan bukti yang tersedia.
- Jangan mengklaim full suite PASS jika ada kegagalan.

### 10.3 Static checks dan build

Jalankan pemeriksaan yang memang tersedia di repository, minimal:

- TypeScript check.
- Frontend production build.
- Test frontend yang relevan.
- Backend tests yang relevan atau full suite sesuai waktu dan kemampuan.
- `git diff --check`.
- Pemeriksaan perubahan file dan status Git.

Gunakan command yang sesuai dengan `package.json`, composer scripts, dan konfigurasi aktual; jangan mengarang command yang tidak tersedia.

### 10.4 Interaction dan security checks

Lakukan pemeriksaan interaksi yang membuktikan:

- Generate hanya berjalan setelah tindakan pengguna.
- Generate tidak bisa dikirim berulang tanpa kendali saat request berlangsung.
- Regenerate membuat track baru sesuai kontrak backend.
- Archive tidak menghapus asset/file secara fisik.
- Data readiness dan manifest diperbarui setelah mutasi yang berhasil.
- Error tidak meninggalkan UI dalam loading state permanen.
- Tidak ada file path atau URL asset privat yang terekspos.
- Tidak ada endpoint playback yang dikarang.
- Tidak ada pemanggilan provider TTS dari operasi read-only.

Jangan membuat klaim mutation testing jika mutation test tidak benar-benar dijalankan.

## 11. Dokumentasi

Buat implementation report baru:

`docs/PHASE_5_PART_5_IMPLEMENTATION.md`

Jangan menimpa brief mentah `docs/PHASE_5_PART_5.md` jika file tersebut sudah ada.

Dokumentasi harus memuat:

1. Ringkasan implementasi.
2. Kondisi Git sebelum dan sesudah.
3. Daftar file yang diubah dan alasan perubahan.
4. Integrasi endpoint dan response contract yang digunakan.
5. Perilaku generate, regenerate, archive, readiness, dan manifest.
6. Status dukungan playback beserta bukti endpoint yang digunakan atau alasan belum didukung.
7. Error handling dan security review.
8. Daftar test dan command yang benar-benar dijalankan.
9. Hasil test, assertions, TypeScript check, dan build.
10. Masalah yang ditemukan atau batasan yang belum terselesaikan.
11. Hasil verifikasi tidak adanya perubahan pada schema/backend yang tidak diizinkan.
12. Commit hash setelah commit dibuat.

Dokumentasikan fakta yang terverifikasi. Jangan menyatakan fitur berhasil jika hanya berupa placeholder.

## 12. Git workflow

Setelah implementasi dan seluruh pemeriksaan selesai:

1. Periksa `git diff`.
2. Periksa `git diff --check`.
3. Periksa semua file yang diubah.
4. Pastikan file untracked milik pengguna tetap aman.
5. Stage hanya file yang memang menjadi bagian Phase 5 Part 5.
6. Jangan menggunakan `git add .` atau `git add -A`.
7. Buat tepat satu commit untuk Part 5 dengan pesan:

`feat: add TTS audio UI`

8. Catat hash commit.
9. Periksa `git status --short` setelah commit.
10. **Jangan push ke remote.**

Jika implementasi terhambat blocker yang tidak dapat diselesaikan tanpa melanggar spesifikasi, jangan membuat commit yang menyesatkan. Laporkan blocker dan status kerja yang tersisa.

## 13. Kriteria penerimaan

Part 5 dinyatakan PASS jika:

- UI Audio terintegrasi dengan pola frontend yang ada.
- Data berasal dari endpoint dan response contract aktual.
- Readiness menggunakan hasil backend.
- Pengguna dapat membuat audio secara manual.
- Regenerate dan archive menggunakan endpoint yang benar.
- Riwayat track tetap dipertahankan sesuai kontrak.
- Manifest dapat diperiksa melalui data backend.
- Playback hanya dinyatakan tersedia jika mekanisme privat yang aman memang ditemukan dan diuji.
- Tidak ada auto-generation atau perubahan scope Part 6.
- Tidak ada kebocoran path/storage privat.
- Test dan static checks yang tersedia telah dijalankan dan hasilnya dilaporkan jujur.
- Implementation report selesai.
- Tepat satu commit Part 5 dibuat jika semua pekerjaan dapat diselesaikan.
- Tidak ada push ke remote.

Jika fitur utama selesai tetapi playback belum didukung backend, nilai hasil secara jujur sebagai PASS WITH LIMITATIONS atau PARTIAL sesuai dampaknya. Jangan menyamarkan keterbatasan tersebut.

## 14. Format laporan akhir

Berikan laporan dengan struktur:

### Status

`PASS`, `PASS WITH LIMITATIONS`, `PARTIAL`, atau `BLOCKED`.

### Implementasi

- Tab/panel Audio.
- Readiness dan manifest.
- Generate, regenerate, archive.
- Playback.
- Error handling dan security.

### File yang diubah

Daftar file dan fungsi utamanya.

### Hasil verifikasi

- Backend tests: hasil aktual.
- Frontend tests: hasil aktual atau alasan tidak tersedia.
- TypeScript check.
- Production build.
- `git diff --check`.
- Interaction/security checks.

### Git

- Commit hash dan pesan.
- Status working tree.
- Konfirmasi bahwa tidak ada push.

### Batasan dan pekerjaan tersisa

Catat semua hal yang belum didukung atau belum dapat diverifikasi.

**Setelah laporan akhir, berhenti. Jangan mulai Phase 5 Part 6 dan jangan menambahkan fitur lain di luar scope.**
