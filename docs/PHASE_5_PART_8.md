# Phase 5 Part 8 — Manual Dubbing Database & Model Foundation

## Konteks proyek

Kamu sedang mengembangkan Mochyfami Studio, aplikasi berbasis Laravel dan React/Vite/Tailwind.

Phase 5 sudah memiliki fondasi automated TTS, termasuk:

- Model `TtsTrack` sebagai riwayat audio per `VisualPlanItem`.
- Relasi `TtsTrack` dengan `Asset`.
- Status lifecycle track yang sudah digunakan oleh sistem TTS.
- Penyimpanan audio privat dan preview melalui route terautentikasi.
- TTS readiness dan production manifest.
- UI TTS dan rangkaian automated TTS tests.

Audit Phase 5 Part 7 telah selesai. Audit tersebut bersifat read-only dan tidak mengubah kode, database, maupun Git.

## Keputusan arsitektur yang wajib diikuti

1. Perluas model `TtsTrack` dengan `source` yang mendukung `tts` dan `manual`.
2. Pisahkan `approval_status` dari lifecycle `status`.
3. Setiap penggantian rekaman harus membuat track baru. Jangan menimpa riwayat track lama.
4. Pertahankan kompatibilitas dengan seluruh automated TTS yang sudah ada.

## Tujuan Part 8

Implementasikan **database dan model foundation saja** untuk mendukung Manual Dubbing.

Jangan implementasikan endpoint, upload audio, perekaman mikrofon, approval API, service Manual Dubbing, perubahan UI, atau perubahan perilaku TTS readiness/manifest pada part ini.

## Tahap 1 — Audit kondisi repository

Sebelum mengubah file:

1. Periksa `git status --short`, branch aktif, dan commit HEAD.
2. Baca model `TtsTrack`, model `Asset`, model `VisualPlanItem`, model user yang sebenarnya digunakan aplikasi, migration terkait, enum/status yang sudah ada, serta test TTS.
3. Baca `docs/PHASE_5_PART_7.md` jika tersedia.
4. Periksa perubahan lokal dan file untracked. Jangan mengasumsikan file tersebut milikmu.
5. Identifikasi konvensi proyek untuk enum PHP, migration, foreign key, factory, dan testing.

Jangan mengembalikan file ke versi sebelumnya atau menghapus perubahan yang sudah ada.

## Tahap 2 — Enum domain

Buat enum bertipe yang konsisten dengan konvensi proyek:

### `TtsTrackSource`

Nilai:
- `tts`
- `manual`

### `ManualDubbingApprovalStatus`

Nilai:
- `draft`
- `ready_for_review`
- `approved`
- `rejected`

Gunakan enum PHP backed string apabila sesuai dengan pola proyek.

Approval status adalah konsep terpisah dari lifecycle status. Jangan menambahkan status approval ke enum lifecycle `TtsTrack`.

## Tahap 3 — Migration additive

Buat migration baru untuk menambahkan kolom pada tabel `tts_tracks` yang sudah ada. Jangan mengubah migration lama.

Kolom yang perlu dipertimbangkan dan ditambahkan sesuai hasil pemeriksaan schema:

- `source`: string dengan default `tts`, untuk mempertahankan semantik semua track lama.
- `uploader_id`: nullable foreign key ke model/tabel user aplikasi yang benar.
- `recorded_at`: nullable timestamp atau datetime sesuai konvensi proyek.
- `approval_status`: nullable string atau representasi nullable yang sesuai dengan konvensi enum/database proyek.
- `approved_at`: nullable timestamp.
- `approved_by`: nullable foreign key ke user yang melakukan approval.
- `notes`: nullable text.

Persyaratan migration:

1. Seluruh baris TTS lama harus tetap memiliki `source = tts` setelah migration.
2. Jangan menghapus atau mengubah nilai lifecycle status yang sudah ada.
3. Jangan menetapkan approval status `draft` secara otomatis untuk semua track lama apabila hal tersebut membuat track TTS lama seolah membutuhkan approval manual.
4. Pilih nullability dan default secara eksplisit berdasarkan kebutuhan domain serta kompatibilitas.
5. Gunakan foreign key yang benar-benar sesuai dengan schema user aplikasi. Periksa kebijakan penghapusan dan gunakan perilaku aman agar penghapusan user tidak menghapus riwayat track.
6. Migration `down()` hanya boleh menghapus kolom dan constraint yang dibuat oleh migration ini.
7. Jangan membuat unique constraint yang menghalangi banyak riwayat track untuk satu `VisualPlanItem`.
8. Jangan membuat tabel track manual kedua.

## Tahap 4 — Update model `TtsTrack`

Perbarui model agar mendukung kolom baru.

Persyaratan:

- Tambahkan kolom yang sesuai ke `$fillable` atau mekanisme mass assignment proyek.
- Tambahkan casts enum untuk `source` dan `approval_status` apabila sesuai dengan pola proyek.
- Tambahkan casts datetime untuk timestamp yang relevan.
- Tambahkan relasi uploader dan approver menuju model user yang benar.
- Pertahankan seluruh relasi, casts, scopes, helper, dan perilaku TTS yang sudah ada.
- Tambahkan scopes/helper yang sederhana dan konsisten, misalnya untuk memfilter track berdasarkan sumber `tts` atau `manual`, hanya apabila sesuai dengan gaya model yang sudah ada.
- Pastikan source default `tts` bekerja dengan baik untuk record lama dan pembuatan track TTS yang sudah ada.
- Jangan mengubah kontrak API atau resource pada part ini kecuali perubahan minimal benar-benar diperlukan agar model tetap kompatibel.

Jangan menganggap semua track `manual` otomatis selesai atau disetujui. Jangan menggabungkan `approval_status` dengan lifecycle `status`.

## Tahap 5 — Testing

Tambahkan atau sesuaikan test yang relevan untuk memverifikasi:

1. Enum source mendukung `tts` dan `manual`.
2. Enum approval mendukung empat status yang ditentukan.
3. Record baru mengikuti default source yang aman.
4. Track manual dapat menyimpan metadata uploader, waktu rekaman, approval status, waktu approval, approver, dan notes.
5. Relasi uploader dan approver berfungsi.
6. Track lama tetap kompatibel dengan lifecycle dan relasi TTS yang sudah ada.
7. Beberapa track dapat tetap dimiliki oleh satu `VisualPlanItem`.
8. Migration tidak mengubah atau menghapus data track lama secara destruktif.

Ikuti pola test dan factory yang benar-benar digunakan proyek. Jangan menulis test yang hanya mengulang implementasi tanpa memverifikasi perilaku.

## Batasan pekerjaan

Pada Part 8, JANGAN:

- Membuat controller atau endpoint Manual Dubbing.
- Mengimplementasikan upload atau rekaman mikrofon.
- Membuat service approval atau service penggantian rekaman.
- Mengubah TTS readiness atau manifest.
- Mengubah `Asset::isTtsGenerated()` kecuali ditemukan kebutuhan blocking untuk integritas model; jika perlu perubahan lintas tahap, laporkan dan minta persetujuan.
- Mengubah UI React.
- Mengubah `.env`, API keys, credentials, atau konfigurasi provider milik pengguna.
- Mengubah migration lama.
- Menghapus file untracked atau mengembalikan perubahan lokal pengguna.
- Menjalankan migration terhadap database development/production atau melakukan operasi database destruktif.
- Menjalankan `git add`, membuat commit, atau melakukan push.
- Menggunakan `git add .`, `git add -A`, `git clean`, atau `git reset`.

Jangan menjalankan formatter yang menulis ulang file tidak terkait. Jika menjalankan Pint, gunakan mode pemeriksaan `--test`.

## Verifikasi

Jalankan pemeriksaan yang aman dan relevan dengan lingkungan proyek, misalnya:

- Syntax check untuk file PHP baru/berubah.
- Test baru untuk enum, model, relasi, dan migration sesuai pola proyek.
- Test regresi TTS yang relevan.
- `git diff --check`.
- Pemeriksaan `git diff --stat` dan `git status --short`.

Jangan mengklaim test berhasil apabila belum benar-benar dijalankan. Jika test terhambat oleh konfigurasi database atau kondisi environment, laporkan secara spesifik.

## Output laporan akhir

Laporkan:

1. Ringkasan perubahan.
2. Daftar file baru dan file yang diubah.
3. Kolom migration dan alasan nullability/default masing-masing.
4. Enum, casts, relasi, dan helper yang ditambahkan.
5. Test yang dijalankan beserta hasil aktual.
6. Risiko atau keputusan yang masih perlu ditinjau.
7. Hasil akhir `git status --short` dan konfirmasi bahwa tidak ada commit atau push.
8. Rekomendasi ruang lingkup Part 9, tanpa mulai mengimplementasikannya.

**Kriteria selesai:** fondasi database dan model Manual Dubbing tersedia, data TTS lama tetap kompatibel, riwayat track tidak dibatasi secara keliru, dan tidak ada perubahan di luar ruang lingkup Part 8.