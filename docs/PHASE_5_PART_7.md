# Phase 5 Part 7 — Audit Struktur TTS dan Asset Management untuk Manual Dubbing

## 1. Konteks Proyek

Kamu sedang mengembangkan **Mochyfami Studio**, aplikasi content production berbasis Laravel dan React/Vite/Tailwind.

Proyek sudah memiliki fondasi TTS otomatis pada Phase 5, termasuk:

- TTS provider contract dan provider registry.
- TTS generation service.
- TTS track management.
- Audio asset storage.
- TTS readiness report.
- TTS production manifest.
- UI untuk mengelola audio TTS.

Beberapa komponen yang diketahui tersedia:

- `app/Services/TTS/Contracts/TtsProvider.php`
- `app/Services/TTS/DTO/TtsRequest.php`
- `app/Services/TTS/DTO/TtsResult.php`
- `app/Services/TTS/TtsGenerationService.php`
- `app/Services/TTS/TtsProviderRegistry.php`
- `app/Services/TTS/TtsManifestService.php`
- `app/Services/TTS/TtsReadinessService.php`
- `app/Models/TtsTrack.php`
- `app/Models/Asset.php`
- `app/Models/ScriptVersion.php`
- `app/Http/Controllers/Api/V1/TtsTrackController.php`
- `app/Http/Controllers/Api/V1/AssetController.php`

Daftar tersebut merupakan petunjuk awal. Periksa kondisi aktual repository sebelum menarik kesimpulan.

### Kondisi Git yang diketahui

Branch aktif: `main`

HEAD terakhir yang diketahui:

`bbdf0db docs: finalize phase 5 TTS verification`

File berikut sudah berubah atau belum dilacak pada pemeriksaan terakhir:

- Modified: `config/tts.php`
- Untracked: `app/Services/TTS/Providers/AudexumTtsProvider.php`
- Untracked: `chrome.pid`
- Untracked: `docs/PHASE_5_PART_2.md` sampai `docs/PHASE_5_PART_6.md`

Status tersebut dapat berubah. Selalu periksa kondisi Git terkini sebelum melakukan pekerjaan.

**Jangan menghapus, menimpa, mengubah, atau memasukkan file-file tersebut ke staging tanpa instruksi eksplisit dari pengguna.**

## 2. Tujuan Part 1

Lakukan audit teknis terhadap implementasi TTS, model audio, asset management, script versioning, migration database, endpoint API, dan frontend yang sudah ada.

Tujuan utamanya adalah menentukan desain implementasi fitur **Manual Dubbing** yang memanfaatkan arsitektur saat ini sebanyak mungkin.

Part ini merupakan tahap investigasi dan perencanaan, bukan implementasi fitur.

## 3. Gambaran Fitur Manual Dubbing

Manual Dubbing memungkinkan pengguna merekam narasi menggunakan mikrofon atau mengunggah file audio dari perangkat mereka, kemudian menyimpan dan mengelola rekaman tersebut di dalam proyek.

Rencana fitur mencakup:

1. Memilih proyek dan versi skrip.
2. Merekam suara melalui mikrofon browser atau mengunggah audio.
3. Memutar ulang dan mengevaluasi hasil rekaman.
4. Mengganti rekaman sebelum disetujui.
5. Menyimpan rekaman sebagai aset audio.
6. Menghubungkan rekaman dengan proyek dan versi skrip yang tepat.
7. Menandai status rekaman, misalnya draft, ready for review, approved, atau status lain yang sesuai dengan konvensi proyek.
8. Mempertahankan riwayat dan integritas rekaman yang sudah disetujui.
9. Memastikan Manual Dubbing tidak merusak atau menggantikan pipeline TTS otomatis.

Status di atas adalah kebutuhan konseptual. Jangan langsung menambahkan enum, kolom, atau tabel sebelum memeriksa struktur yang sudah ada.

## 4. Area Audit Wajib

### A. Model dan relasi

Periksa:

- `app/Models/TtsTrack.php`
- `app/Models/Asset.php`
- `app/Models/Script.php`
- `app/Models/ScriptVersion.php`
- `app/Models/ContentProject.php`
- Model lain yang berkaitan langsung dengan produksi audio.

Identifikasi:

- Kolom yang tersedia.
- Relasi Eloquent.
- Foreign key.
- Status dan enum yang digunakan.
- Cara TTS track mengacu pada proyek, skrip, versi skrip, dan aset audio.
- Apakah model sudah mendukung sumber audio yang berbeda, seperti TTS otomatis dan rekaman manual.
- Apakah ada mekanisme versioning atau penggantian aset.
- Apakah terdapat unique constraint yang membatasi satu track per versi skrip.

Jangan mengasumsikan nama kolom atau relasi sebelum memeriksa implementasinya.

### B. TTS generation service

Periksa:

- `app/Services/TTS/TtsGenerationService.php`
- `app/Services/TTS/TtsProviderRegistry.php`
- `app/Services/TTS/TtsManifestService.php`
- `app/Services/TTS/TtsReadinessService.php`
- `app/Services/TTS/Contracts/TtsProvider.php`
- `app/Services/TTS/DTO/TtsRequest.php`
- `app/Services/TTS/DTO/TtsResult.php`

Identifikasi:

- Bagaimana audio dihasilkan dan disimpan.
- Bagaimana `TtsTrack` dibuat dan diperbarui.
- Bagaimana `Asset` dibuat dan dihubungkan.
- Bagaimana metadata audio dianalisis.
- Bagaimana status generation ditentukan.
- Apakah manifest dan readiness report bergantung pada provider TTS atau dapat menggunakan audio manual.
- Komponen mana yang dapat digunakan kembali tanpa membuat coupling yang tidak diperlukan.

Manual Dubbing tidak boleh dipaksakan melalui provider TTS otomatis jika arsitektur tersebut tidak sesuai dengan karakteristik rekaman manual.

### C. Asset management dan penyimpanan

Periksa:

- `app/Http/Controllers/Api/V1/AssetController.php`
- `app/Models/Asset.php`
- Seluruh migration terkait tabel `assets`.
- Service yang menangani upload, penyimpanan, validasi, metadata, dan penghapusan aset.
- Konfigurasi filesystem yang digunakan untuk aset privat.
- Mekanisme download atau playback aset audio.

Tentukan:

- Apakah upload audio sudah didukung.
- Apakah ada validasi MIME type dan ukuran file.
- Apakah penyimpanan aset menggunakan disk privat atau publik.
- Bagaimana aplikasi mengizinkan pengguna memutar audio tanpa membuka akses publik yang tidak diperlukan.
- Apakah pipeline metadata mendukung format audio manual.
- Apakah diperlukan service baru atau cukup menggunakan service yang tersedia.

### D. Controller, routes, dan API

Periksa:

- `app/Http/Controllers/Api/V1/TtsTrackController.php`
- `app/Http/Controllers/Api/V1/AssetController.php`
- `routes/api.php`
- Form Request terkait TTS atau asset upload.
- Middleware autentikasi dan otorisasi yang digunakan.

Dokumentasikan:

- Endpoint TTS yang sudah ada.
- Endpoint asset upload yang tersedia.
- Format request dan response.
- Pola validasi.
- Cara memastikan pengguna hanya mengakses proyek dan aset yang berhak mereka kelola.
- Endpoint baru yang mungkin diperlukan untuk Manual Dubbing.

Jangan menambahkan endpoint selama Part 1.

### E. Migration dan database

Periksa migration terkait:

- `tts_tracks`
- `assets`
- `script_versions`
- Tabel lain yang berhubungan langsung dengan audio production.

Identifikasi:

- Struktur tabel.
- Foreign key dan unique constraint.
- Index.
- Kolom status.
- Strategi penghapusan dan cascading.
- Apakah desain saat ini dapat mendukung beberapa rekaman untuk satu versi skrip.
- Apakah model data memungkinkan menyimpan rekaman baru tanpa menghapus rekaman lama.

Jangan membuat atau mengubah migration pada Part 1.

### F. Frontend

Temukan halaman dan komponen React yang digunakan untuk:

- TTS UI.
- TTS track management.
- Audio playback.
- Asset management.
- Project detail.
- Script version management.

Periksa pola API client, autentikasi, state management, error handling, dan komponen UI yang dapat digunakan kembali.

Tentukan lokasi integrasi Manual Dubbing yang paling konsisten dengan arsitektur frontend saat ini. Jangan membuat halaman atau komponen baru pada tahap audit ini.

### G. Pengujian

Periksa test yang sudah tersedia untuk:

- TTS generation.
- TTS track.
- Asset upload dan storage.
- Script versioning.
- API authorization.
- Audio metadata.
- Frontend TTS jika tersedia.

Identifikasi test yang harus dipertahankan dan test baru yang nantinya diperlukan untuk Manual Dubbing.

## 5. Batasan Pengerjaan

Pada Part 1:

- Jangan mengubah kode produksi.
- Jangan membuat migration.
- Jangan mengubah skema database.
- Jangan menambah endpoint.
- Jangan mengubah UI.
- Jangan mengubah konfigurasi provider TTS.
- Jangan menjalankan formatter yang mengubah file.
- Jangan memperbaiki masalah lain yang tidak terkait dengan audit.
- Jangan menghapus file untracked.
- Jangan menjalankan `git add`, `git commit`, `git reset`, `git clean`, atau `git push`.
- Jangan menampilkan nilai rahasia dari `.env`, API key, token, atau kredensial.
- Jangan menganggap file yang belum dilacak sebagai file yang boleh diubah.

Semua aktivitas pada part ini harus bersifat read-only, kecuali jika pengguna memberikan izin terpisah.

## 6. Format Laporan

Buat laporan akhir dengan struktur berikut.

### 1. Executive Summary

Ringkasan kondisi arsitektur TTS dan asset management serta kesimpulan awal tentang pendekatan Manual Dubbing.

### 2. Existing Architecture

Jelaskan alur aktual dari script version ke TTS track, asset storage, metadata, dan playback berdasarkan kode yang benar-benar ditemukan.

### 3. File Inventory

Buat tabel berisi:

| File | Tanggung jawab aktual | Relevansi terhadap Manual Dubbing |
| ---- | --------------------- | --------------------------------- |

Cantumkan file aktual yang telah diperiksa. Jangan mengarang nama file atau fungsi.

### 4. Database and Relationship Analysis

Jelaskan struktur tabel, relasi, unique constraint, serta keterbatasan yang ditemukan.

### 5. Existing API and Frontend Analysis

Dokumentasikan endpoint, request/response, komponen frontend, dan pola integrasi yang relevan.

### 6. Gap Analysis

Gunakan tabel:

| Kebutuhan | Dukungan saat ini | Gap | Rekomendasi |
| --------- | ----------------- | --- | ----------- |

Bedakan fakta yang ditemukan dari asumsi atau rekomendasi.

### 7. Recommended Architecture

Berikan desain minimal yang memungkinkan:

- Rekam atau unggah audio manual.
- Preview rekaman.
- Penyimpanan privat.
- Relasi dengan versi skrip.
- Penggantian rekaman dengan aman.
- Status persetujuan.
- Penggunaan kembali aset dan metadata.
- Kompatibilitas dengan TTS otomatis.

Bandingkan pilihan memperluas `TtsTrack` yang sudah ada dengan membuat entitas baru hanya jika memang dibutuhkan. Pilih desain berdasarkan bukti dari kode, bukan preferensi pribadi.

### 8. Proposed API Contract

Jika endpoint baru diperlukan, berikan rancangan endpoint, metode HTTP, validasi, authorization, dan contoh response JSON. Tandai secara jelas bahwa endpoint tersebut baru berupa proposal dan belum dibuat.

### 9. Implementation Plan

Susun tahapan implementasi setelah Part 1, dengan dependency, file yang kemungkinan akan terdampak, dan strategi pengujian untuk setiap tahap.

### 10. Risks and Decisions

Catat keputusan arsitektur yang masih perlu dikonfirmasi, risiko kehilangan rekaman, risiko akses file, batasan format audio, dan isu browser microphone permissions.

### 11. Verification

Laporkan perintah pemeriksaan yang dijalankan beserta hasilnya. Jangan mengklaim test lulus jika tidak dijalankan.

### 12. Git Status

Laporkan apakah audit mengubah file. Idealnya, working tree tetap sama seperti sebelum audit. Jangan mengubah atau menafsirkan file untracked sebagai bagian dari implementasi.

## 7. Definition of Done

Part 1 dinyatakan selesai apabila:

- Struktur TTS dan asset management telah dipahami berdasarkan kode aktual.
- Relasi `TtsTrack`, `Asset`, dan `ScriptVersion` telah dikonfirmasi.
- Alur upload dan playback aset telah diketahui.
- Kemungkinan penggunaan kembali komponen telah dinilai.
- Gap dan risiko telah didokumentasikan.
- Arsitektur Manual Dubbing yang direkomendasikan memiliki alasan teknis yang jelas.
- Rencana implementasi berikutnya tersedia.
- Tidak ada perubahan kode produksi atau database.
- Tidak ada file pengguna yang dihapus atau dimasukkan ke staging.
- Tidak ada rahasia yang terungkap.

## Instruksi Akhir

Mulai dengan memeriksa repository dan kondisi Git. Lakukan audit secara sistematis, gunakan hasil pemeriksaan kode sebagai dasar setiap kesimpulan, lalu berikan laporan akhir sesuai format di atas.

**Jangan melanjutkan ke implementasi Part 8 sebelum pengguna meninjau dan menyetujui hasil audit Part 7.**
