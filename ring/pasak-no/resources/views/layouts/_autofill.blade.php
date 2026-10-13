{{-- Tombol "Isi Otomatis" untuk halaman create — Murni Blade + JS vanilla, tanpa NPM.
     Pakai: @include('layouts._autofill', ['presets' => [
         ['fields' => ['name' => 'Budi'], 'checks' => []],
     ]])
     Tiap klik memilih satu preset acak yang isinya selaras. Nilai khusus:
       '__RANDOM_OPTION__' → opsi <select> acak yang tidak kosong.
       '__RANDOM_PHONE__'  → nomor 08xx acak.
       checks ['tags[]' => ['take' => 2]] → centang 2 checkbox acak.
     Hanya mengisi field yang masih kosong agar tidak menimpa ketikan user. --}}
<span data-autofill-root data-presets='@json($presets ?? [])'>
    <button class="btn" type="button">✨ Isi Otomatis</button>
</span>
<script>
(function (root) {
    if (!root || root.dataset.autofillDone) return;
    root.dataset.autofillDone = '1';
    var btn = root.querySelector('button');
    if (!btn) return;

    function pick(arr) { return arr[Math.floor(Math.random() * arr.length)]; }

    function shuffled(arr) {
        arr = arr.slice();
        for (var i = arr.length - 1; i > 0; i--) {
            var j = Math.floor(Math.random() * (i + 1));
            var t = arr[i]; arr[i] = arr[j]; arr[j] = t;
        }
        return arr;
    }

    btn.addEventListener('click', function () {
        var form = btn.closest('form');
        if (!form) return;
        var presets = JSON.parse(root.dataset.presets || '[]');
        if (!presets.length) return;
        var p = pick(presets);

        Object.keys(p.fields || {}).forEach(function (name) {
            var el = form.querySelector('[name="' + name + '"]');
            if (!el || el.value) return;
            var val = p.fields[name];
            if (val === '__RANDOM_OPTION__' && el.tagName === 'SELECT') {
                var opts = Array.prototype.filter.call(el.options, function (o) { return o.value !== ''; });
                if (opts.length) el.value = pick(opts).value;
            } else if (val === '__RANDOM_PHONE__') {
                var digits = '';
                for (var i = 0; i < 10; i++) digits += Math.floor(Math.random() * 10);
                el.value = '08' + digits;
            } else {
                el.value = val;
            }
        });

        Object.keys(p.checks || {}).forEach(function (name) {
            var boxes = form.querySelectorAll('[name="' + name + '"]');
            var anyChecked = Array.prototype.some.call(boxes, function (b) { return b.checked; });
            if (anyChecked) return;
            var rule = p.checks[name];
            var targets = Array.isArray(rule)
                ? Array.prototype.filter.call(boxes, function (b) { return rule.indexOf(b.value) !== -1; })
                : shuffled(Array.prototype.slice.call(boxes)).slice(0, rule.take || 1);
            targets.forEach(function (b) { b.checked = true; });
        });
    });
})(document.currentScript ? document.currentScript.previousElementSibling : null);
</script>
