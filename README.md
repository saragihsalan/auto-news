# AUTO NEWS — Set and Forget AI News Rewriter

Otomatis buat berita terbaru dari RSS portal berita, rewrite dengan AI (9Router / OpenAI-compatible), publish otomatis, dan SEO-optimized untuk mengejar top 1 Google.

> **Open Source** — silakan clone, ganti nama, dan deploy sendiri. Lisensi MIT.

## Instalasi dari Source (clone)

```bash
git clone https://github.com/saragihsalan/auto-news.git
cd auto-news
cp includes/config.example.php includes/config.php   # lalu isi kredensial DB kamu
```

> `includes/config.php` di-git-ignore (tidak ikut ke repo), jadi kredensial kamu aman. Template-nya ada di `includes/config.example.php`.

## Fitur

- **RSS Autopilot** — tarik item dari Detik, CNN Indonesia, Kompas, Antara (bisa tambah sendiri)
- **AI Rewrite** — judul + isi di-rewrite jadi 100% original, SEO-rich (pakai 9Router, model bisa diganti)
- **Set and Forget** — cron jalan sendiri, publish otomatis
- **SEO Lengkap** — meta title/desc, keywords, slug, schema.org (NewsArticle + FAQPage + Breadcrumb), OpenGraph, Twitter Card, canonical, sitemap.xml, robots.txt
- **Admin Panel** — login, ganti endpoint AI/model/key, kelola RSS, trigger manual, lihat statistik & log
- **Tampilan Profesional** — responsive, clean, cepat (cache + lazy image)
- **Kategori Topik Otomatis** — ambil `<category>` dari RSS, AI isi/tebak topik kalau RSS kosong, menu kategori + `related` berdasarkan kesamaan topik

## Struktur

```
index.php              -> Frontend router (homepage + artikel)
install.php            -> Installer sekali jalan (KEMUDIAN HAPUS)
database.sql           -> Schema MySQL
.htaccess              -> Pretty URL, security, gzip, cache
includes/
  config.php           -> Koneksi DB + settings loader
  functions.php        -> Helper (slug, truncate, http, dll)
  rss_fetcher.php      -> Ambil + normalisasi RSS
  ai_engine.php        -> AI rewrite (9Router chat)
  seo.php              -> sitemap, schema, internal link, cache
cron/run.php           -> Pipeline otomatis (set and forget)
admin/
  index.php            -> Dashboard
  auth.php             -> Login + guard
  settings.php         -> AI endpoint/model/key + info situs
  sources.php          -> Kelola RSS sources
  articles.php         -> Kelola artikel (publish/feature/hapus)
  run.php              -> Trigger pipeline manual
assets/css/           -> style.css (frontend), admin.css
```

## Instalasi (cPanel / Shared Hosting)

1. **Upload** semua file & folder ke `public_html/` (atau subfolder).
2. **Buat database MySQL** di cPanel (misal `auto_news`), beserta user + password.
3. **Edit** `includes/config.php`: isi `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`.
4. **Buka** `https://domain-kamu/install.php` di browser. Ini akan:
   - Membuat semua tabel
   - Membuat admin default (`admin` / `admin123`)
   - Mengisi setting default + 4 RSS sumber Indonesia
5. **HAPUS `install.php`** setelah selesai!
6. **Login Admin** di `https://domain-kamu/admin/` → ganti password.

## Upgrade dari Versi Lama (kategori topik)

Kalo kamu sudah install versi lama (sebelum fitur kategori ada), jalankan sekali dari CLI:

```bash
php cron/migrate.php      # nambah kolom `category` + index
php cron/backfill_category.php   # isi kategori untuk artikel lama yang masih kosong
```

`backfill_category.php` pakai klasifikasi kata kunci offline (instan, gratis). Bisa preview dengan `--dry-run`.

## Konfigurasi AI (9Router)

1. Login ke **Admin → Pengaturan**.
2. Isi **Endpoint Base URL** (default `http://localhost:20128` atau URL server 9Router kamu).
3. Isi **API Key** (opsional, kalau 9Router butuh auth).
4. Isi **Model** (contoh: `openai/gpt-4o-mini`, `google/gemini-flash`, dll).
5. Klik **Simpan**.
6. Cek daftar model: `curl http://SERVER:20128/v1/models`.

## Cron (Set and Forget)

Buat cron job di cPanel (Cron Jobs), setiap **5 menit**:

```
php /home/USER/public_html/cron/run.php
```

Atau via web (proteksi pakai secret yang ada di Admin → Pengaturan):

```
https://domain-kamu/cron/run.php?secret=YOUR_CRON_SECRET
```

> **Catatan:** `.htaccess` memblokir akses langsung ke `/cron/` via web, jadi pakai cron CLI. Kalau mau akses web, hapus baris block cron di `.htaccess`.

## Konfigurasi Penting (Admin → Pengaturan)

| Key | Deskripsi | Default |
|---|---|---|
| `site_name` | Nama situs | AUTO NEWS |
| `site_lang` | Bahasa utama | `id` |
| `ai_endpoint` | Base URL 9Router | `http://localhost:20128` |
| `ai_model` | Model chat | `openai/gpt-4o-mini` |
| `ai_temperature` | Kreativitas | `0.7` |
| `batch_limit` | Max artikel per cron | `5` |
| `cron_secret` | Proteksi cron web | auto |

## SEO Notes

- Pastikan `BASE_URL` benar (di `includes/config.php` atau auto-detect).
- Daftarkan di **Google Search Console** → submit `sitemap.xml`.
- Tambahkan situs ke **Bing Webmaster** juga.
- Halaman otomatis pakai: canonical, hreflang (multi-bahasa), schema NewsArticle + FAQPage + Breadcrumb, OpenGraph, Twitter Card.

## Keamanan

- **Hapus `install.php`** setelah install.
- Ganti password admin default segera.
- Atur `ai_api_key` hanya di panel (tersimpan di DB, jangan dibagikan).

---

## Lisensi

**MIT License** — bebas dipakai, dimodifikasi, didistribusikan. Sertakan copyright notice berikut:

```
Copyright (c) 2026 Saragih Salan
```

---

Made with ❤️ — Set & Forget.
