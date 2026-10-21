@extends('pages.pasak.layout', ['heading' => __('Threat Map'), 'subheading' => __('Klokan geografis serangan yang terdeteksi menuju server yang dipantau')])

@section('content')
@php($payloadData = $payload)
@php($s = $stats)

<div class="sec-card sec-card-flush threat-map-card" data-threat-map>
    <div class="threat-map-wrap">
        <div class="threat-map-canvas-holder">
            <canvas data-map-base></canvas>
            <canvas data-map-anim></canvas>
            <div class="threat-map-offline" data-map-offline hidden>
                <div>
                    <strong>{{ __('Globe tidak tersedia') }}</strong>
                    <p>{{ __('Pustaka globe gagal dimuat') }}</p>
                </div>
            </div>
        </div>

        <aside class="threat-map-side">
            <div class="threat-panel">
                <h3 class="threat-panel-title">{{ __('World status') }}</h3>
                <div class="threat-stat">
                    <span class="threat-stat-label">{{ __('Firewall status') }}</span>
                    <span class="threat-stat-value {{ empty($payloadData['enforcement']) ? 'is-muted' : 'is-success' }}">
                        ● {{ ($payloadData['enforcement'] ?? false) ? __('Blocking active') : __('Monitoring only') }}
                    </span>
                </div>
                <div class="threat-stat">
                    <span class="threat-stat-label">{{ __('Protected server') }}</span>
                    <span class="threat-stat-value is-cyan" data-map-target>{{ $payloadData['target']['name'] }}</span>
                </div>
                <div class="threat-stat">
                    <span class="threat-stat-label">{{ __('Attacks logged (today)') }}</span>
                    <span class="threat-stat-value is-danger" data-map-attacks>{{ number_format($s['logs_today'] ?? 0) }}</span>
                </div>
                <div class="threat-stat" style="margin-bottom: 0;">
                    <span class="threat-stat-label">{{ __('Active quarantines') }}</span>
                    <span class="threat-stat-value">{{ number_format($s['active_blocks'] ?? 0) }}</span>
                </div>
            </div>

            <div class="threat-panel threat-panel-log">
                <h3 class="threat-panel-title">{{ __('Recent attack sources') }}</h3>
                <div class="threat-log-scroll" data-map-logs></div>
            </div>
        </aside>
    </div>
</div>

<style>
    .threat-map-card .threat-map-wrap {
        display: flex;
        min-height: 560px;
        background: #020813;
    }
    .threat-panel-log {
        flex: 1;
        min-height: 0;
    }
    .threat-map-canvas-holder {
        position: relative;
        flex: 1;
        min-width: 0;
        cursor: grab;
        overflow: hidden;
    }
    .threat-map-canvas-holder:active { cursor: grabbing; }
    .threat-map-canvas-holder canvas {
        position: absolute;
        inset: 0;
        width: 100%;
        height: 100%;
    }
    .threat-map-offline {
        position: absolute;
        inset: 0;
        z-index: 3;
        display: flex;
        align-items: center;
        justify-content: center;
        text-align: center;
        color: #94a3b8;
    }
    .threat-map-offline strong { color: #e2e8f0; font-size: 16px; }
    .threat-map-offline p { margin: 6px 0 0; font-size: 13px; }

    .threat-map-side {
        width: 330px;
        flex-shrink: 0;
        display: flex;
        flex-direction: column;
        gap: 12px;
        padding: 16px;
        border-left: 1px solid #1e293b;
        background: #030b17;
        overflow: hidden;
    }
    .threat-panel {
        border: 1px solid rgba(6, 182, 212, 0.3);
        border-radius: 8px;
        background: rgba(4, 15, 30, 0.85);
        padding: 14px;
    }
    .threat-panel-title {
        font-size: 11px;
        color: #06b6d4;
        text-transform: uppercase;
        letter-spacing: 1.5px;
        margin: 0 0 12px;
        border-bottom: 1px solid rgba(6, 182, 212, 0.3);
        padding-bottom: 6px;
    }
    .threat-stat { margin-bottom: 10px; }
    .threat-stat:last-child { margin-bottom: 0; }
    .threat-stat-label {
        display: block;
        font-size: 10px;
        color: #94a3b8;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin-bottom: 2px;
    }
    .threat-stat-value {
        font-size: 14px;
        font-weight: 700;
        color: #e2e8f0;
        word-break: break-word;
    }
    .threat-stat-value.is-cyan { color: #06b6d4; }
    .threat-stat-value.is-danger { color: #ef4444; }
    .threat-stat-value.is-success { color: #10b981; }
    .threat-stat-value.is-muted { color: #94a3b8; }

    .threat-log-scroll {
        flex: 1;
        min-height: 200px;
        max-height: 430px;
        overflow-y: auto;
        display: flex;
        flex-direction: column;
        gap: 8px;
        padding-right: 6px;
        scrollbar-width: thin;
        scrollbar-color: #06b6d4 rgba(0, 0, 0, 0.2);
    }
    .threat-log-scroll::-webkit-scrollbar { width: 4px; }
    .threat-log-scroll::-webkit-scrollbar-track { background: rgba(0, 0, 0, 0.2); }
    .threat-log-scroll::-webkit-scrollbar-thumb { background: #06b6d4; border-radius: 4px; }

    .threat-entry {
        background: rgba(255, 255, 255, 0.03);
        padding: 8px 10px;
        border-left: 3px solid #64748b;
        font-size: 11px;
        border-radius: 0 4px 4px 0;
        color: #cbd5e1;
        animation: threat-in 0.3s ease-out;
    }
    .threat-entry.l-critical { border-left-color: #ef4444; }
    .threat-entry.l-high { border-left-color: #f97316; }
    .threat-entry.l-medium { border-left-color: #eab308; }
    .threat-entry.l-low { border-left-color: #34d399; }
    .threat-entry .t-time { color: #94a3b8; }
    .threat-entry .t-place { color: #06b6d4; }
    .threat-entry .t-ip { font-weight: 700; color: #e2e8f0; }
    .threat-entry .t-desc { font-weight: 700; text-transform: uppercase; }
    .threat-entry.l-critical .t-desc { color: #ef4444; }
    .threat-entry.l-high .t-desc { color: #f97316; }
    .threat-entry.l-medium .t-desc { color: #eab308; }
    .threat-entry.l-low .t-desc { color: #34d399; }
    .threat-entry .t-blocked { color: #10b981; }
    .threat-entry .t-payload {
        font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
        word-break: break-all;
        color: #cbd5e1;
    }
    @keyframes threat-in {
        from { opacity: 0; transform: translateX(-10px); }
        to { opacity: 1; transform: translateX(0); }
    }

    @media (max-width: 900px) {
        .threat-map-card .threat-map-wrap { flex-direction: column; }
        .threat-map-side { width: 100%; order: -1; border-left: none; border-bottom: 1px solid #1e293b; }
        .threat-map-canvas-holder { min-height: 380px; }
    }
</style>

<script src="https://d3js.org/d3.v7.min.js"></script>
<script src="https://unpkg.com/topojson-client@3"></script>
<script>
    (function () {
        'use strict';

        var card = document.querySelector('[data-threat-map]');
        if (!card) {
            return;
        }

        var MAP = @json($payload);
        var COLORS = { critical: '#ef4444', high: '#f97316', medium: '#eab308', low: '#34d399' };
        var LEVEL_QUEUE = ['critical', 'high', 'medium', 'low'];
        var SCROLL = card.querySelector('[data-map-logs]');
        var originsIndex = {};
        var pendinglogs = [];
        var isGlobeReady = false;

        function esc(value) {
            var div = document.createElement('div');
            div.textContent = value === null || value === undefined ? '' : String(value);
            return div.innerHTML;
        }

        function placeOf(location) {
            var parts = [];
            if (location && location.place) {
                parts.push(location.place);
            }
            if (location && location.country && location.place !== location.country) {
                parts.push(location.country);
            }
            return parts.length === 0 ? '{{ __('Unknown origin') }}' : parts.join(', ');
        }

        function levelOf(entry) {
            return LEVEL_QUEUE.indexOf(entry.level) === -1 ? 'low' : entry.level;
        }

        function addLog(entry, location) {
            var el = document.createElement('div');
            var level = levelOf(entry);
            var blocked = entry.blocked
                ? ' · <span class="t-blocked">{{ __('BLOCKED') }}</span>'
                : '';
            var time = entry.time
                ? new Date(entry.time).toLocaleTimeString('id-ID', { hour12: false })
                : new Date().toLocaleTimeString('id-ID', { hour12: false });

            el.className = 'threat-entry l-' + level;
            el.setAttribute('data-entry-id', entry.id || '');
            el.innerHTML =
                '<span class="t-time">[' + esc(time) + ']</span> ' + blocked + '<br>' +
                '<span class="t-place">📍 ' + esc(placeOf(location)) + '</span><br>' +
                '<span class="t-ip">' + esc(entry.ip) + '</span><br>' +
                '<span class="t-desc">' + esc(entry.desc) + '</span><br>' +
                '<span class="t-payload">&gt; ' + esc((entry.method || '') + ' ' + (entry.path || '')) + '</span>';

            SCROLL.prepend(el);

            while (SCROLL.children.length > 30) {
                SCROLL.removeChild(SCROLL.lastChild);
            }
        }

        // Seed log panel + arcs dari payload awal (20 serangan terakhir 24 jam).
        MAP.origins.forEach(function (origin) {
            originsIndex[origin.ip] = origin;
            origin.samples.forEach(function (entry, index) {
                if (index < 2) {
                    pendinglogs.push({ level: entry.level, origin: origin.ip, entry: entry });
                }
            });
        });
        pendinglogs.forEach(function (item) {
            addLog(item.entry, originsIndex[item.origin]);
        });

        // Rotasi arc terjadwal untuk data awal saat globe siap.
        function scheduleInitial() {
            if (!isGlobeReady || pendinglogs.length === 0) {
                return;
            }
            var shot = pendinglogs.shift();
            window.__threatMapFire(MAP, originsIndex[shot.origin], shot.entry);
            setTimeout(scheduleInitial, 500 + Math.random() * 1200);
        }

        function poll() {
            if (isGlobeReady) {
                fetch(MAP.feedUrl, { credentials: 'same-origin' }).then(function (res) {
                    return res.ok ? res.json() : null;
                }).then(function (data) {
                    if (!data) {
                        return;
                    }
                    document.querySelector('[data-map-attacks]').innerText = Number(data.stats.logs_today || 0).toLocaleString('id-ID');
                    data.logs.forEach(function (entry) {
                        var location = data.locations[entry.ip];
                        if (location && location.lat !== null && !originsIndex[entry.ip]) {
                            originsIndex[entry.ip] = {
                                ip: entry.ip,
                                lat: location.lat,
                                lon: location.lon,
                                place: location.place,
                                country: location.country
                            };
                        }
                        addLog(entry, location || originsIndex[entry.ip]);
                        if (isGlobeReady && originsIndex[entry.ip]) {
                            window.__threatMapFire(MAP, originsIndex[entry.ip], entry);
                        }
                    });
                }).catch(function () { /* jaringan gagal: tunggu siklus berikutnya */ });
            }
            setTimeout(poll, (MAP.refresh || 8) * 1000);
        }
        poll();

        // ==================== Globe (butuh d3 + topojson) ====================
        if (typeof d3 === 'undefined' || typeof topojson === 'undefined') {
            document.querySelector('[data-map-offline]').hidden = false;
            return;
        }

        var holder = card.querySelector('.threat-map-canvas-holder');
        var baseCanvas = card.querySelector('[data-map-base]');
        var animCanvas = card.querySelector('[data-map-anim]');
        var baseCtx = baseCanvas.getContext('2d');
        var animCtx = animCanvas.getContext('2d');

        var width, height, projection, geoPath;
        var centerX, centerY;
        var worldData = null;
        var baseScale = 1;
        var currentZoom = 1;
        var activeAttacks = [];
        var ripples = [];

        var graticule = d3.geoGraticule();

        var zoom = d3.zoom()
            .scaleExtent([0.5, 5])
            .on('zoom', function (event) {
                currentZoom = event.transform.k;
                projection.scale(baseScale * currentZoom);
                drawBaseMap();
            });

        var drag = d3.drag()
            .on('drag', function (event) {
                var rot = projection.rotate();
                var k = 70 / projection.scale();
                projection.rotate([rot[0] + event.dx * k, rot[1] - event.dy * k, rot[2]]);
                drawBaseMap();
            });

        d3.select(holder).call(drag).call(zoom);

        function resize() {
            var rect = holder.getBoundingClientRect();
            width = rect.width;
            height = rect.height;
            var dpr = window.devicePixelRatio || 1;

            [baseCanvas, animCanvas].forEach(function (c) {
                c.width = width * dpr;
                c.height = height * dpr;
                c.getContext('2d').scale(dpr, dpr);
            });

            centerX = width / 2;
            centerY = height / 2;
            baseScale = Math.min(width, height) / 2.3;

            if (!projection) {
                projection = d3.geoOrthographic()
                    .translate([centerX, centerY])
                    .scale(baseScale)
                    .rotate([-MAP.target.lon, -MAP.target.lat, 0]);
            } else {
                projection.translate([centerX, centerY]).scale(baseScale * currentZoom);
            }

            geoPath = d3.geoPath().projection(projection).context(baseCtx);

            if (worldData) {
                drawBaseMap();
            }
        }

        window.addEventListener('resize', resize);

        function isVisible(coords) {
            var rot = projection.rotate();
            var centerLon = -rot[0];
            var centerLat = -rot[1];
            return d3.geoDistance(coords, [centerLon, centerLat]) < Math.PI / 2;
        }

        function getElevatedPoint(geoCoords, progress) {
            var pt = projection(geoCoords);
            if (!pt) {
                return null;
            }
            var dx = pt[0] - centerX;
            var dy = pt[1] - centerY;
            var dist = Math.hypot(dx, dy);
            var elevation = Math.sin(progress * Math.PI) * 60 * currentZoom;
            var scaleF = dist === 0 ? 1 : (dist + elevation) / dist;
            return [centerX + dx * scaleF, centerY + dy * scaleF];
        }

        function drawBaseMap() {
            baseCtx.clearRect(0, 0, width, height);

            baseCtx.beginPath();
            baseCtx.arc(centerX, centerY, projection.scale(), 0, Math.PI * 2);
            baseCtx.fillStyle = '#020a1c';
            baseCtx.fill();

            baseCtx.beginPath();
            geoPath(graticule());
            baseCtx.strokeStyle = 'rgba(255, 255, 255, 0.05)';
            baseCtx.lineWidth = 0.5;
            baseCtx.stroke();

            if (worldData) {
                baseCtx.beginPath();
                geoPath(worldData);
                baseCtx.fillStyle = '#0a1635';
                baseCtx.fill();
                baseCtx.strokeStyle = 'rgba(6, 182, 212, 0.4)';
                baseCtx.lineWidth = 0.8;
                baseCtx.lineJoin = 'round';
                baseCtx.stroke();
            }

            baseCtx.beginPath();
            baseCtx.arc(centerX, centerY, projection.scale(), 0, Math.PI * 2);
            baseCtx.shadowBlur = 40;
            baseCtx.shadowColor = 'rgba(6, 182, 212, 0.4)';
            baseCtx.strokeStyle = 'rgba(6, 182, 212, 0.6)';
            baseCtx.lineWidth = 1;
            baseCtx.stroke();
            baseCtx.shadowBlur = 0;

            var targetPoint = projection([MAP.target.lon, MAP.target.lat]);
            if (targetPoint && isVisible([MAP.target.lon, MAP.target.lat])) {
                baseCtx.beginPath();
                baseCtx.arc(targetPoint[0], targetPoint[1], 4 * currentZoom, 0, Math.PI * 2);
                baseCtx.fillStyle = '#ef4444';
                baseCtx.shadowBlur = 20;
                baseCtx.shadowColor = '#ef4444';
                baseCtx.fill();
                baseCtx.shadowBlur = 0;
            }
        }

        d3.json('https://unpkg.com/world-atlas@2/countries-110m.json').then(function (data) {
            worldData = topojson.feature(data, data.objects.countries);
            drawBaseMap();
            isGlobeReady = true;
            scheduleInitial();
        }).catch(function () {
            document.querySelector('[data-map-offline]').hidden = false;
        });

        window.__threatMapFire = function (map, origin, entry) {
            if (!origin || typeof origin.lat !== 'number' || typeof origin.lon !== 'number') {
                return;
            }
            var level = levelOf(entry);
            var jitter = 3;
            activeAttacks.push({
                sourceCoords: [origin.lon + (Math.random() - 0.5) * jitter, origin.lat + (Math.random() - 0.5) * jitter],
                targetCoords: [map.target.lon, map.target.lat],
                progress: 0,
                speed: 0.005 + Math.random() * 0.004,
                color: COLORS[level] || '#06b6d4'
            });
        };

        function animate() {
            animCtx.clearRect(0, 0, width, height);

            for (var r = ripples.length - 1; r >= 0; r--) {
                var ripple = ripples[r];
                ripple.radius += ripple.speed;
                ripple.alpha -= ripple.fade;
                if (ripple.alpha <= 0) {
                    ripples.splice(r, 1);
                    continue;
                }
                var targetPt = projection([MAP.target.lon, MAP.target.lat]);
                if (targetPt) {
                    animCtx.beginPath();
                    animCtx.arc(targetPt[0], targetPt[1], ripple.radius, 0, Math.PI * 2);
                    animCtx.strokeStyle = 'rgba(' + ripple.rgb + ', ' + ripple.alpha + ')';
                    animCtx.lineWidth = 2 * currentZoom;
                    animCtx.stroke();
                }
            }

            for (var i = activeAttacks.length - 1; i >= 0; i--) {
                var attack = activeAttacks[i];
                attack.progress += attack.speed;

                if (attack.progress >= 1) {
                    var rgb = {
                        '#ef4444': '239, 68, 68',
                        '#f97316': '249, 115, 22',
                        '#eab308': '234, 179, 8',
                        '#34d399': '52, 211, 153'
                    }[attack.color];
                    ripples.push({ radius: 2, speed: 1.5, alpha: 1, fade: 0.05, rgb: rgb || '234, 179, 8' });
                    activeAttacks.splice(i, 1);
                    continue;
                }

                var interpolator = d3.geoInterpolate(attack.sourceCoords, attack.targetCoords);
                var geo = interpolator(attack.progress);

                if (isVisible(geo)) {
                    var head = getElevatedPoint(geo, attack.progress);
                    if (head) {
                        animCtx.beginPath();
                        animCtx.arc(head[0], head[1], 3 * currentZoom, 0, Math.PI * 2);
                        animCtx.fillStyle = '#fff';
                        animCtx.shadowBlur = 15;
                        animCtx.shadowColor = attack.color;
                        animCtx.fillStyle = attack.color;
                        animCtx.fill();
                        animCtx.shadowBlur = 0;
                    }
                }

                for (var j = 0; j < 20; j++) {
                    var t1 = attack.progress - j * 0.005;
                    var t2 = attack.progress - (j + 1) * 0.005;
                    if (t1 < 0) { continue; }
                    if (t2 < 0) { t2 = 0; }

                    var geo1 = interpolator(t1);
                    var geo2 = interpolator(t2);
                    if (!isVisible(geo1) && !isVisible(geo2)) { continue; }

                    var p1 = getElevatedPoint(geo1, t1);
                    var p2 = getElevatedPoint(geo2, t2);
                    if (!p1 || !p2) { continue; }

                    animCtx.beginPath();
                    animCtx.moveTo(p1[0], p1[1]);
                    animCtx.lineTo(p2[0], p2[1]);
                    animCtx.strokeStyle = attack.color;
                    animCtx.lineWidth = Math.max(0.5, 3.5 - j * 0.15) * currentZoom;
                    animCtx.globalAlpha = 1 - j / 20;
                    if (j === 0) {
                        animCtx.shadowBlur = 10;
                        animCtx.shadowColor = attack.color;
                    } else {
                        animCtx.shadowBlur = 0;
                    }
                    animCtx.stroke();
                    animCtx.globalAlpha = 1;
                }
            }

            requestAnimationFrame(animate);
        }

        resize();
        animate();
    })();
</script>
@endsection
