@php
// Sampul post: gambar asli bila ada, SVG placeholder bila tidak.
// Variabel: $url, $title, $class, $style, $loading.
$coverSvg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 450">'
    .'<defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1">'
    .'<stop offset="0" stop-color="#16233a"/><stop offset="1" stop-color="#0b1220"/>'
    .'</linearGradient></defs>'
    .'<rect width="800" height="450" fill="url(#g)"/>'
    .'<g fill="none" stroke="#38bdf8" stroke-width="10" opacity="0.85">'
    .'<rect x="330" y="150" width="140" height="110" rx="14"/>'
    .'<circle cx="365" cy="185" r="12" fill="#38bdf8" stroke="none"/>'
    .'<path d="M330 240l40-40 30 30 25-25 45 45z" fill="#38bdf8" stroke="none" opacity="0.7"/>'
    .'</g>'
    .'<text x="400" y="310" text-anchor="middle" font-family="system-ui,sans-serif" font-size="30" fill="#94a3b8">Tanpa Gambar</text>'
    .'</svg>';
$coverSrc = ($url ?? null) ?: 'data:image/svg+xml;utf8,'.rawurlencode($coverSvg);
$coverAttrs = 'src="'.$coverSrc.'" alt="'.e($title ?? '').'"';
$coverAttrs .= ! empty($class) ? ' class="'.e($class).'"' : '';
$coverAttrs .= ! empty($style) ? ' style="'.e($style).'"' : '';
$coverAttrs .= ' loading="'.e($loading ?? 'lazy').'"';
@endphp
<img {!! $coverAttrs !!}>
