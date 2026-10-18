# CODING AGENT PROMPT — PHASE 5 PART 6: DOCUMENTATION + FINAL VERIFICATION

## 1. Peran dan tujuan

Bertindak sebagai Senior Full-Stack Engineer dan QA Engineer yang bertanggung jawab menutup Phase 5 TTS pada proyek MochyFami Content Studio.

Stack:

- Backend: Laravel 13, PHP 8.4, MySQL, Sanctum.
- Frontend: React, Vite, TypeScript, Tailwind CSS.
- Media processing: FFmpeg/ffprobe.
- Audio assets disimpan di private storage.
- AI/TTS menggunakan arsitektur provider-agnostic.

Workflow produksi:

IDEA → SELECTED → RESEARCH → FACT CHECK → SCRIPT → VISUAL PLAN → ASSET COLLECTION → TTS → SUBTITLE → VIDEO RENDER → THUMBNAIL → QUALITY REVIEW → APPROVED → PUBLISHED → ANALYTICS

Phase 5 mencakup:

1. Persistence + Provider Foundation
2. Synthesis + Storage + API
3. TTS Readiness
4. TTS Production Manifest
5. Audio UI
6. Documentation + Final Verification

**Kerjakan hanya Part 6. Jangan menambah fitur produk baru.**

Tujuan:

- Memverifikasi implementasi Part 1–5 secara menyeluruh.
- Memeriksa keamanan dan konsistensi kontrak.
- Menjalankan backend/frontend verification yang tersedia.
- Melakukan atau mengoordinasikan browser smoke test secara jujur.
- Memperbarui dokumentasi final tanpa menimpa brief pengguna.
- Membuat satu commit final jika seluruh prasyarat commit terpenuhi.
- Tidak melakukan push.

## 2. Checkpoint yang dilaporkan

Commit sebelumnya:

- Part 1: `8283fa2` — `feat: add TTS domain and provider foundation`
- Part 2: `ed6febf` — `feat: add TTS synthesis and storage API`
- Part 3: `4e4c43f` — TTS Readiness
- Part 4: `5ab9664` — TTS Production Manifest
- Part 5: `fb8aad1` — `feat: add TTS audio UI`

Part 5 melaporkan:

- Audio tab pada `ProjectDetailPage`.
- TTS readiness dan manifest dari backend.
- Generate, regenerate, archive melalui aksi eksplisit pengguna.
- Playback menggunakan `assetService.fetchPreview` dan endpoint `assets/{asset}/preview` yang sudah ada.
- TypeScript check dan build berhasil.
- Backend suite: 1.608 tests / 5.039 assertions.
- `git diff --check` bersih.
- Browser interaction/playback belum diuji.
- Tidak ada test runner frontend yang terpasang.
- Empat brief pengguna `docs/PHASE_5_PART_{2,3,4,5}.md` tetap untracked.
- Branch `main` dilaporkan ahead 4, belum di-push.

Informasi tersebut adalah checkpoint dari laporan sebelumnya, bukan bukti kondisi aktual. Verifikasi semuanya sebelum mengambil tindakan.

## 3. Aturan mutlak

1. **Jangan menganggap laporan sebelumnya sebagai bukti final.** Periksa source code, Git, test output, dan dokumentasi aktual.
2. Jangan menghapus atau menimpa perubahan pengguna.
3. Jangan men-stage seluruh repository.
4. Jangan melakukan `git add .` atau `git add -A`.
5. Jangan push ke remote.
6. Jangan menambahkan fitur TTS, subtitle, rendering, queue, atau pipeline publikasi baru.
7. Jangan mengubah kontrak API tanpa blocker nyata dan justifikasi kuat.
8. Jangan membuat test pass palsu atau mengubah test untuk menutupi bug.
9. Jangan menyatakan browser smoke test PASS jika tidak benar-benar dilakukan.
10. Jangan menyatakan playback PASS hanya karena tombol atau elemen audio muncul.
11. Jangan menampilkan atau mencatat secret, bearer token, storage path, atau informasi sensitif.
12. Jika menemukan bug, perbaiki hanya jika koreksi berada dalam scope Phase 5 dan dapat diverifikasi dengan aman.
13. Jangan mengubah brief mentah pengguna, terutama `docs/PHASE_5_PART_2.md` sampai `docs/PHASE_5_PART_5.md`.
14. Jangan memulai Phase 6 atau fitur berikutnya.
15. Jika pemeriksaan penting tidak bisa dilakukan, laporkan sebagai NOT RUN atau BLOCKED, beserta alasannya.

## 4. Tahap A — Git pre-flight

Sebelum perubahan:

1. Jalankan `git status --short`.
2. Periksa branch aktif.
3. Jalankan `git log --oneline -8`.
4. Verifikasi commit Part 5 dan leluhur commit Part 1–4 jika tersedia.
5. Periksa jumlah commit ahead/behind terhadap remote tanpa melakukan fetch atau operasi remote yang mengubah repository, kecuali benar-benar diperlukan dan diizinkan.
6. Catat perubahan tracked dan untracked.
7. Identifikasi empat brief pengguna:
    - `docs/PHASE_5_PART_2.md`
    - `docs/PHASE_5_PART_3.md`
    - `docs/PHASE_5_PART_4.md`
    - `docs/PHASE_5_PART_5.md`
8. Catat semua perubahan lokal yang sudah ada sebelum pekerjaan dimulai.

Jangan mengasumsikan working tree bersih.

Jika ada perubahan pengguna yang tidak terkait, pertahankan sepenuhnya. Jangan memasukkannya ke commit final.

## 5. Tahap B — Audit spesifikasi dan dokumentasi

Baca:

- `docs/PHASE_5_TTS.md`
- `docs/PHASE_5_PART_2_IMPLEMENTATION.md`
- `docs/PHASE_5_PART_3_IMPLEMENTATION.md`
- `docs/PHASE_5_PART_4_IMPLEMENTATION.md`
- `docs/PHASE_5_PART_5_IMPLEMENTATION.md`

Baca juga dokumentasi Part 1 jika tersedia.

Bandingkan implementasi aktual dengan spesifikasi terkunci. Jangan hanya menyatukan klaim dari implementation report sebelumnya.

Buat checklist yang mencakup:

### Domain dan provider

- `TtsTrack` persistence dan status transitions.
- `NarrationHasher`.
- `TtsRequest`, `TtsResult`, dan `TtsProvider`.
- Fake provider menghasilkan output WAV valid sesuai implementasi.
- Provider registry dan konfigurasi.
- Penanganan provider yang tidak tersedia dan exception yang aman.

### Synthesis dan asset storage

- Synthesis dilakukan secara sinkron dan hanya atas tindakan eksplisit.
- Narasi snapshot dan hash ditangani sesuai spesifikasi.
- Durasi audio ditentukan dari hasil audio aktual melalui mekanisme yang ditentukan implementasi.
- File disimpan dalam private storage.
- Track history tetap tersedia setelah regenerate.
- Archive tidak menghapus asset atau file secara fisik.
- Batas panjang narasi dan validasi input sesuai kontrak.
- Kegagalan provider tidak membocorkan exception internal.

### Asset Requirement isolation

- TTS Assets tidak menjadi kandidat `AssetRequirement`.
- Guard di `AssetRequirementService::attachAsset` dan `selectAsset` tetap aktif.
- Asset audio biasa tetap berfungsi sesuai kontrak.
- Tidak ada regresi pada asset library.

### Readiness

- Endpoint read-only.
- Tidak ada synthesis atau mutasi database ketika readiness dibaca.
- Aturan audio terkini, blockers, warnings, dan info konsisten dengan spesifikasi.
- Status dan urutan deterministik sesuai kontrak.
- Scope project dan version diverifikasi.

### Manifest

- Endpoint read-only.
- Data berasal dari track/asset yang sesuai.
- Urutan item kanonis.
- Tidak ada file path, storage key, atau metadata internal yang tidak diizinkan.
- Tidak ada pemanggilan provider TTS saat manifest dibaca.

### Audio UI

- Tab Audio berada di tempat yang sesuai dengan arsitektur existing.
- Data readiness dan manifest berasal dari backend.
- Generate, regenerate, archive hanya dipanggil oleh aksi eksplisit pengguna.
- Request ganda dikendalikan.
- Archive memerlukan konfirmasi.
- Refresh setelah mutasi mempertahankan konsistensi UI.
- Error dan loading state ditangani.
- Tidak ada endpoint playback baru yang tidak disetujui.
- Playback memakai mekanisme existing yang terotorisasi.

Catat setiap ketidaksesuaian dengan bukti file, fungsi, test, atau output command. Jangan memperbaiki sesuatu hanya karena nama atau bentuk implementasinya berbeda dari ekspektasi jika kontrak sebenarnya sudah dipenuhi.

## 6. Tahap C — Audit keamanan

Lakukan pemeriksaan terarah untuk:

### Autentikasi dan isolasi

- Endpoint TTS terlindungi sesuai pola autentikasi API.
- Project, script version, visual plan, item, track, dan asset tidak dapat diakses lintas project.
- Resource asing memberikan respons sesuai kontrak, umumnya 404 untuk resource yang tidak berada dalam scope.
- Operasi mutasi tidak dapat dilakukan oleh pengguna yang tidak berwenang.

### Private storage

- Audio tetap privat.
- Tidak ada `Storage::url()` yang mengekspos audio privat.
- Tidak ada file path atau storage key yang bocor melalui API, UI, log, atau dokumentasi.
- Preview memakai mekanisme otorisasi existing.
- Preview tidak menerima asset arbitrer tanpa verifikasi scope dan authorization.

### Input dan command execution

- Tidak ada shell execution yang menerima input pengguna secara tidak aman.
- Parameter provider, voice, speed, language, dan narasi divalidasi sesuai kontrak.
- Tidak ada secret dalam source, frontend bundle, log, atau dokumentasi.
- Tidak ada endpoint atau mekanisme status mutation yang melanggar spesifikasi.

### Read-only behavior

- GET readiness dan manifest tidak melakukan synthesis.
- Membuka tab Audio tidak memicu generate atau regenerate.
- Tidak ada queue, job, subtitle, rendering, atau Handoff integration baru.

Gunakan pencarian source code yang relevan dan test yang sudah ada. Bedakan temuan terverifikasi dari dugaan. Jangan mengklaim audit keamanan menyeluruh hanya dari pencarian teks.

## 7. Tahap D — Browser smoke test wajib

**Ini adalah bagian wajib dalam laporan final. Tidak boleh diasumsikan PASS dari hasil TypeScript, build, atau backend test.**

### 7.1 Periksa kemampuan testing

Cari tahu apakah environment memiliki browser dan mekanisme untuk menjalankan aplikasi secara interaktif.

Periksa:

- Apakah development server dapat dijalankan.
- Apakah database dan backend dapat dijalankan.
- Apakah tersedia akun/test user dan data fixture yang sah.
- Apakah browser automation sudah terpasang atau tersedia.
- Apakah aplikasi dapat diakses tanpa mengubah konfigurasi pengguna secara permanen.

Jangan menambahkan dependency browser automation besar hanya untuk menghindari laporan keterbatasan. Gunakan tooling yang memang tersedia jika memungkinkan.

Jika agent tidak memiliki kemampuan browser interaktif, jangan mengklaim sudah melakukan smoke test. Berikan checklist manual yang siap dijalankan pengguna dan tandai hasil `NOT RUN — REQUIRES HUMAN`.

### 7.2 Prasyarat test

Gunakan environment lokal dan data pengujian yang aman.

- Jangan menggunakan production credentials atau data pribadi.
- Jangan menampilkan token atau cookie.
- Jangan mengubah data production.
- Jika tidak tersedia test project yang sesuai, buat atau gunakan fixture lokal yang tidak mengganggu data pengguna.
- Jangan mengubah database pengguna secara destruktif.
- Jangan menjalankan migration reset atau `migrate:fresh` pada database yang belum dipastikan aman.
- Catat environment dan batasan yang relevan.

### 7.3 Checklist browser

Untuk setiap langkah, catat `PASS`, `FAIL`, `BLOCKED`, atau `NOT RUN`, beserta bukti yang aman.

**A. Navigasi dan initial load**

1. Login jika diperlukan.
2. Buka project detail.
3. Buka tab Audio.
4. Pastikan panel tampil tanpa runtime error.
5. Pastikan loading selesai atau empty state tampil dengan benar.
6. Pastikan membuka tab tidak memicu POST generate.
7. Pastikan readiness dan manifest tampil sesuai respons backend.

**B. Daftar item dan readiness**

1. Pastikan item mengikuti urutan backend.
2. Periksa narasi dan status audio.
3. Periksa blockers dan warnings jika tersedia.
4. Pastikan informasi readiness tidak dihitung ulang secara berbeda oleh frontend.
5. Pastikan manifest bisa dibuka dan ditutup.
6. Pastikan tabel segmen dan ringkasan manifest sesuai respons backend.

**C. Generate audio**

1. Pilih item yang sesuai.
2. Buka modal generate.
3. Pastikan voice wajib diisi.
4. Uji validasi speed pada rentang yang didukung, 0.5–2.0.
5. Isi language bila ingin menguji field opsional.
6. Kirim request.
7. Pastikan tombol menunjukkan loading dan tidak dapat memicu request ganda.
8. Pastikan hasil sukses hanya muncul setelah server merespons.
9. Pastikan track history, readiness, dan manifest diperbarui.

Jika provider fake merupakan satu-satunya provider yang tersedia, catat bahwa pengujian menggunakan fake provider. Jangan menyebutnya pengujian provider TTS eksternal.

**D. Playback**

1. Jika track audio tersedia, gunakan kontrol playback.
2. Pastikan aplikasi meminta preview melalui mekanisme terotorisasi yang sudah ada.
3. Pastikan browser benar-benar menerima audio dan dapat memutarnya.
4. Verifikasi hasil playback bukan sekadar tombol yang aktif atau request yang berhasil.
5. Pastikan URL preview/blob tidak membocorkan file path atau storage key.
6. Pastikan error preview ditampilkan dengan benar.

Jika tidak dapat menghasilkan atau memutar audio karena environment, tandai BLOCKED atau NOT RUN dan jelaskan alasannya.

**E. Regenerate**

1. Jalankan regenerate secara eksplisit.
2. Pastikan UI menjelaskan bahwa track lama tetap ada.
3. Pastikan request hanya terkirim sekali.
4. Pastikan track baru muncul setelah server mengonfirmasi.
5. Pastikan track sebelumnya tetap terlihat sesuai aturan history.
6. Pastikan readiness dan manifest diperbarui.

**F. Archive**

1. Buka aksi archive.
2. Pastikan dialog konfirmasi muncul.
3. Batalkan dan pastikan tidak ada mutasi.
4. Ulangi dan konfirmasi archive.
5. Pastikan UI berubah setelah respons server.
6. Pastikan asset dan file tidak dihapus secara fisik.
7. Pastikan readiness dan manifest diperbarui sesuai kontrak.

**G. Error handling**
Jika memungkinkan dengan cara yang aman, uji minimal satu error yang dapat direproduksi, misalnya validasi 422 atau preview 404.

- Pastikan error ditampilkan secara aman.
- Pastikan tidak ada stack trace atau path privat.
- Pastikan loading state berhenti.
- Pastikan data yang sebelumnya tampil tidak hilang secara tidak semestinya.

Jangan memaksa error dengan merusak database atau mengubah source code produksi hanya untuk mendapatkan screenshot.

### 7.4 Format hasil smoke test

Catat hasil menggunakan tabel seperti berikut:

| Skenario               | Status                    | Bukti/catatan |
| ---------------------- | ------------------------- | ------------- |
| Buka tab Audio         | PASS/FAIL/BLOCKED/NOT RUN | Bukti aktual  |
| Readiness dan manifest | PASS/FAIL/BLOCKED/NOT RUN | Bukti aktual  |
| Generate audio         | PASS/FAIL/BLOCKED/NOT RUN | Bukti aktual  |
| Playback audio         | PASS/FAIL/BLOCKED/NOT RUN | Bukti aktual  |
| Regenerate             | PASS/FAIL/BLOCKED/NOT RUN | Bukti aktual  |
| Archive                | PASS/FAIL/BLOCKED/NOT RUN | Bukti aktual  |
| Error handling         | PASS/FAIL/BLOCKED/NOT RUN | Bukti aktual  |

Jangan mengubah status NOT RUN menjadi PASS hanya karena source code terlihat benar.

## 8. Tahap E — Regression tests dan static checks

Periksa scripts aktual di `composer.json`, `package.json`, dan konfigurasi test.

Jalankan test yang tersedia dan relevan. Prioritas:

1. Full backend test suite.
2. TTS-specific tests.
3. Asset Requirement regression tests.
4. Authorization/cross-project tests.
5. Frontend test jika test runner sudah tersedia.
6. TypeScript check.
7. Production build.
8. Formatting atau lint checks yang memang tersedia.
9. `git diff --check`.

Baseline laporan sebelumnya:

- 1.608 backend tests.
- 5.039 assertions.
- TypeScript check lulus.
- Frontend build lulus dengan warning chunk-size sebelumnya.

Baseline bukan hasil aktual. Catat hasil yang benar-benar diperoleh pada run final.

Jika full suite gagal:

- Identifikasi test dan penyebabnya.
- Bedakan regresi baru dengan kegagalan yang sudah ada, menggunakan bukti.
- Jangan mengubah test yang valid untuk memaksa hasil hijau.
- Jangan menyembunyikan kegagalan dengan hanya menjalankan subset.
- Catat setiap test yang tidak dijalankan dan alasannya.

Jika tidak tersedia test runner frontend, jangan menambah dependency hanya untuk klaim kelulusan. Laporkan bahwa validasi frontend terbatas pada static checks/build dan browser smoke test.

## 9. Tahap F — Koreksi bug yang ditemukan

Part 6 bukan kesempatan untuk refactor besar.

Jika audit menemukan bug:

1. Tentukan apakah bug berada dalam scope Phase 5.
2. Buat reproduksi atau test yang menunjukkan masalah.
3. Lakukan perubahan sekecil mungkin.
4. Jangan mengubah kontrak API yang terkunci tanpa justifikasi dan dokumentasi.
5. Jangan menghapus fitur atau test untuk menyamarkan masalah.
6. Jalankan kembali test yang relevan.
7. Ulangi browser smoke test untuk alur yang terdampak jika kemampuan tersedia.
8. Catat perubahan dalam final report.

Jika perbaikan berisiko mengubah behavior Part 1–5 atau memerlukan keputusan arsitektur baru, jangan menebak. Laporkan temuan sebagai BLOCKED dan jelaskan keputusan yang dibutuhkan.

## 10. Tahap G — Dokumentasi penutupan Phase 5

Buat dokumen baru:

`docs/PHASE_5_FINAL_VERIFICATION.md`

Jika file sudah ada, baca dan evaluasi isinya sebelum memperbarui. Jangan menimpa konten pengguna yang tidak berkaitan.

Dokumen harus memuat:

### A. Ringkasan

- Tujuan Phase 5.
- Ringkasan Part 1–5.
- Status akhir berdasarkan bukti.

### B. Audit spesifikasi

- Daftar requirement utama.
- Status PASS/PARTIAL/FAIL/BLOCKED.
- Referensi file, test, atau output yang mendukung.

### C. API dan arsitektur

- Endpoint TTS yang digunakan.
- Readiness dan manifest.
- Storage dan playback.
- Batasan yang belum terselesaikan.

### D. Keamanan

- Auth dan project scoping.
- Private asset storage.
- TTS asset exclusion dari AssetRequirement.
- Read-only endpoints.
- Error handling.
- Temuan dan risiko yang belum terselesaikan.

### E. Regression verification

- Command yang benar-benar dijalankan.
- Backend test count dan assertion count aktual.
- Frontend tests jika tersedia.
- TypeScript check.
- Production build.
- Lint/format check bila tersedia.
- `git diff --check`.

### F. Browser smoke test

Masukkan tabel status untuk setiap skenario pada §7.3.

Bedakan:

- PASS: benar-benar dijalankan dan berhasil.
- FAIL: dijalankan dan gagal.
- BLOCKED: percobaan terhalang kondisi tertentu.
- NOT RUN: tidak dicoba.

Jika membutuhkan tindakan manusia, tulis secara eksplisit `NOT RUN — REQUIRES HUMAN`. Sertakan langkah untuk menjalankannya secara manual.

### G. Git dan perubahan

- Commit yang menjadi bagian Phase 5.
- File yang diubah selama Part 6.
- Commit final jika dibuat.
- Status working tree.
- Jumlah ahead/behind jika dapat diverifikasi.
- Konfirmasi tidak ada push.

### H. Known limitations

Cantumkan:

- Tidak adanya frontend test runner jika masih benar.
- Keterbatasan browser automation.
- Status playback yang benar-benar diuji.
- Provider yang digunakan selama pengujian.
- Semua issue yang belum diselesaikan.

Jangan menulis klaim “production-ready”, “fully tested”, atau “semua aman” tanpa dasar yang memadai.

## 11. Git commit

Setelah audit, koreksi yang diperlukan, dokumentasi, dan verifikasi selesai:

1. Periksa `git diff`.
2. Periksa `git diff --check`.
3. Tinjau setiap file yang akan di-commit.
4. Pastikan empat brief pengguna tetap utuh dan tidak masuk staging.
5. Stage hanya file Part 6 yang memang milik task ini.
6. Jangan gunakan `git add .` atau `git add -A`.
7. Buat tepat satu commit final Part 6 dengan pesan:

`docs: finalize phase 5 TTS verification`

8. Catat hash commit.
9. Jalankan `git status --short`.
10. Verifikasi commit hanya berisi file yang semestinya.
11. **Jangan push.**

Jika terdapat blocker kritis, test gagal yang belum dipahami, atau kondisi working tree yang membuat commit tidak aman, jangan membuat commit final yang menyesatkan. Laporkan keadaan dan pekerjaan tersisa.

Jika browser smoke test membutuhkan tindakan manual pengguna tetapi semua verifikasi lain telah selesai, kamu boleh membuat commit dokumentasi yang secara eksplisit mencatat smoke test sebagai NOT RUN — REQUIRES HUMAN. Jangan menyebut Phase 5 fully verified.

## 12. Kriteria penerimaan

Part 6 dinyatakan selesai jika:

- Implementasi Part 1–5 telah diaudit terhadap spesifikasi.
- Regression tests dan static checks yang tersedia telah dijalankan.
- Temuan keamanan telah diperiksa dan dilaporkan.
- Browser smoke test benar-benar dijalankan jika environment mendukungnya; jika tidak, status setiap skenario dicatat secara jujur.
- Playback tidak dinyatakan PASS tanpa pengujian playback yang nyata.
- Dokumentasi final lengkap.
- Tidak ada brief pengguna yang tertimpa atau ikut di-stage.
- Tidak ada fitur baru di luar scope.
- Commit final dibuat jika aman.
- Tidak ada push.

Gunakan status akhir:

- `PASS`: requirement penting terpenuhi dan smoke test kritis yang relevan benar-benar lulus.
- `PASS WITH LIMITATIONS`: implementasi serta automated verification lulus, tetapi ada keterbatasan pengujian yang dinyatakan secara eksplisit.
- `PARTIAL`: ada bagian penting yang belum terverifikasi atau belum selesai.
- `BLOCKED`: kendala kritis mencegah penyelesaian yang aman.

Jangan memilih status yang lebih tinggi daripada bukti yang tersedia.

## 13. Format laporan akhir

Laporkan:

### Status akhir

PASS / PASS WITH LIMITATIONS / PARTIAL / BLOCKED.

### Audit Part 1–5

Requirement yang diperiksa dan temuan penting.

### Regression tests dan static checks

Command, hasil aktual, jumlah tests/assertions, build, dan pemeriksaan lain.

### Browser smoke test

Tabel skenario, status, dan bukti aktual. Jangan menghilangkan skenario yang NOT RUN.

### Security review

Temuan, risiko, dan keterbatasan audit.

### Dokumentasi

Daftar file yang dibuat atau diperbarui.

### Git

- Commit hash dan pesan.
- File yang masuk commit.
- Status working tree.
- Konfirmasi tidak ada push.
- Status empat brief pengguna.

### Pekerjaan tersisa

Tuliskan seluruh BLOCKED, NOT RUN, atau known limitations beserta langkah berikutnya.

**Berhenti setelah laporan akhir. Jangan mulai Phase 6 atau fitur baru.**
