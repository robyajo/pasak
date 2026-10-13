<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Post;
use App\Models\Tag;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PostSeeder extends Seeder
{
    public function run(): void
    {
        $categories = Category::all();
        $tags = Tag::all();

        $posts = [
            [
                'title' => 'Mengenal WAF dan Cara Kerjanya',
                'excerpt' => 'Pengantar Web Application Firewall: apa, kenapa, dan bagaimana ia menyaring request jahat.',
                'body' => "Web Application Firewall (WAF) duduk di antara pengguna dan aplikasi, memeriksa setiap request terhadap pola serangan yang dikenal.\n\nPola seperti path traversal (../), injeksi skrip (<script>), dan probe berkas sensitif (.env) diblokir sebelum mencapai kode aplikasi.\n\nDi lab ini, Pasak berperan sebagai WAF-nya; tanpa Pasak semua vektor lolos dengan 200 OK.",
            ],
            [
                'title' => 'Panduan Upload Gambar yang Aman di Laravel',
                'excerpt' => 'Validasi MIME, ekstensi, ukuran, dan jebakan double-extension seperti shell.php.jpg.',
                'body' => "Selalu validasi unggahan dengan aturan image + daftar mimes eksplisit (jpg,jpeg,png,gif,webp) dan batas ukuran.\n\nWaspadai berkas double-extension (shell.php.jpg) dan null-byte (%00) yang dipakai menyelinapkan webshell.\n\nSimpan di disk public via store() dan hapus berkas lama saat thumbnail diganti atau post dihapus.",
            ],
            [
                'title' => 'Audit Keamanan Tanpa WAF: 4 Tahap Terbukti',
                'excerpt' => 'Alur audit lab: pindai permukaan, kirim vektor, buktikan upload, manfaatkan shell.',
                'body' => "Tahap 1: pindai permukaan dan catat temuan. Tahap 2: kirim vektor serangan dan amati status 200 (lolos).\n\nTahap 3: unggah shell.php.jpg dan verifikasi bisa diakses publik. Tahap 4: eksekusi perintah via /shell untuk membuktikan dampak.\n\nSetelah Pasak dipasang, ulangi: vektor instant-block harus menjadi 403.",
            ],
        ];

        foreach ($posts as $index => $data) {
            $post = Post::firstOrCreate(
                ['slug' => Str::slug($data['title'])],
                [
                    'category_id' => $categories->get($index % max($categories->count(), 1))?->id,
                    'title' => $data['title'],
                    'excerpt' => $data['excerpt'],
                    'body' => $data['body'],
                    'is_published' => true,
                ],
            );

            if ($tags->isNotEmpty()) {
                $post->tags()->syncWithoutDetaching(
                    $tags->shuffle()->take(2)->pluck('id')->all(),
                );
            }
        }
    }
}
