<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * DEMO AUDIT SENGAJA SANGAT RENTAN (tanpa Pasak): mengeksekusi berkas hasil
 * unggahan seolah-olah server salah konfigurasi (mis. .htaccess AddType
 * application/x-httpd-php .jpg atau LFI ke folder uploads).
 *
 * HANYA untuk lab lokal. Jangan pernah deploy ke server nyata.
 * Rute ini TIDAK ada di pasak-yes.
 */
class DemoShellController extends Controller
{
    public function exec(Request $request, string $name): Response
    {
        $name = basename($name);
        $disk = Storage::disk('public');
        $path = 'demo-uploads/'.$name;

        if (! $disk->exists($path)) {
            return response()->json([
                'ok' => false,
                'message' => "Berkas {$name} tidak ada. Unggah dulu via tahap 4.",
            ], 404);
        }

        $cmd = (string) $request->query('cmd', 'id');
        $_GET['cmd'] = $cmd;

        // Samakan dengan shell asli: perintah jalan dari root aplikasi.
        chdir(base_path());

        ob_start();

        try {
            include $disk->path($path);
        } catch (\Throwable $e) {
            echo '[include error] '.$e->getMessage();
        }

        $output = (string) ob_get_clean();

        return response()->json([
            'ok' => true,
            'message' => 'PERINTAH DIEKSEKUSI — inilah dampak webshell tanpa Pasak.',
            'file' => '/storage/'.$path,
            'cmd' => $cmd,
            'output' => $output,
        ]);
    }
}
