<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * DEMO SENGAJA RENTAN (tanpa Pasak): menerima berkas apa pun tanpa validasi.
 * Dipakai untuk audit: membuktikan upload shell.php.jpg benar-benar masuk disk.
 * Setelah Pasak terpasang + middleware pasak.block, request ini akan 403 duluan.
 */
class DemoUploadController extends Controller
{
    public function form()
    {
        $files = collect(Storage::disk('public')->files('demo-uploads'))
            ->map(fn ($f) => ['name' => basename($f), 'url' => Storage::url($f), 'size' => Storage::disk('public')->size($f)])
            ->values();

        return view('demo-upload', ['files' => $files]);
    }

    public function store(Request $request)
    {
        if (! $request->hasFile('file')) {
            return response()->json(['ok' => false, 'message' => 'Tidak ada file (field: file).'], 422);
        }
        $file = $request->file('file');
        $name = $file->getClientOriginalName();
        $path = $file->storeAs('demo-uploads', $name, 'public');

        return response()->json([
            'ok' => true,
            'message' => 'BERHASIL DIUNGGAH — tanpa Pasak, double-extension lolos!',
            'filename' => $name,
            'path' => $path,
            'url' => Storage::url($path),
            'full_url' => url(Storage::url($path)),
            'size' => Storage::disk('public')->size($path),
            'proof' => 'Buka full_url di browser: file PHP tersimpan & bisa diakses publik. Inilah webshell.',
        ]);
    }
}
