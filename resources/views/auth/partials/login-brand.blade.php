{{--
    Brand panel of the login page (left column on wide screens), the same pattern as
    HPY Karyawan / HPYRental: a dark blue surface, a faint dot grid, one trend line
    that draws itself and then breathes, and three glass cards naming what is inside.
--}}
<div class="login-brand" aria-hidden="true">
  <canvas class="login-brand-chart" data-login-chart></canvas>

  <div class="login-brand-content">
    <div>
      <div class="login-brand-kicker">Crew Management</div>
      <h2 class="login-brand-title">Satu portal untuk<br>crew, kapal, dan dokumen pelaut.</h2>
      <p class="login-brand-sub">Crew Master · Sign On/Off · Candidate Pool · Sertifikat · Principal &amp; Vessel — terintegrasi dengan ERP HPY, real-time.</p>
    </div>

    <div class="login-brand-kpis">
      <div class="login-brand-kpi">
        <div class="login-brand-kpi-label">Crewing</div>
        <div class="login-brand-kpi-value">Crew &amp; Rank</div>
        <div class="login-brand-kpi-delta">Assignment · Sign On/Off</div>
      </div>
      <div class="login-brand-kpi">
        <div class="login-brand-kpi-label">Fleet</div>
        <div class="login-brand-kpi-value">Vessel</div>
        <div class="login-brand-kpi-delta">Principal · Profitability</div>
      </div>
      <div class="login-brand-kpi">
        <div class="login-brand-kpi-label">Compliance</div>
        <div class="login-brand-kpi-value">Dokumen</div>
        <div class="login-brand-kpi-delta">Sertifikat · Expiry</div>
      </div>
    </div>
  </div>

  <div class="login-brand-foot">Integrated · Smart · Efficient · Growth</div>
</div>

<script>
    (function () {
        var canvas = document.querySelector('[data-login-chart]');
        if (! canvas) return;

        var ctx = canvas.getContext('2d');
        var W, H, t = 0, raf;
        var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        

        function resize() {
            var dpr = Math.min(window.devicePixelRatio || 1, 2);
            W = canvas.clientWidth; H = canvas.clientHeight;
            canvas.width = W * dpr; canvas.height = H * dpr;
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
        }

        function signal(x, s) {
            return Math.sin(x * 0.9 + s) * 0.45 + Math.sin(x * 2.1 + s * 1.7) * 0.2 + Math.sin(x * 0.35 + s * 0.3) * 0.35;
        }

        function frame() {
            t += 1;
            if (W === 0 || H === 0) { raf = requestAnimationFrame(frame); return; }

            ctx.clearRect(0, 0, W, H);
            ctx.fillStyle = 'rgba(255,255,255,0.09)';
            for (var x = 24; x < W; x += 28) for (var y = 24; y < H; y += 28) ctx.fillRect(x, y, 1, 1);

            var pad = 56, base = H * 0.70, amp = H * 0.085;
            var progress = reduce ? 1 : Math.min(1, t / 150);
            var ease = 1 - Math.pow(1 - progress, 3);
            var span = (W - pad * 2) * ease;
            var drift = progress >= 1 ? (t - 150) * 0.08 : 0;
            var pts = [];
            for (var px = 0; px <= span; px += 5) {
                var k = (px + drift) * 0.009;
                var u = px / (W - pad * 2);
                var noise = signal(k, 2.4) * (1 - u * u * 0.85);
                var trend = u * 1.1 + u * u * u * 1.6;
                pts.push([pad + px, base - (noise + trend) * amp]);
            }

            if (pts.length > 1) {
                ctx.beginPath();
                pts.forEach(function (p, n) { n ? ctx.lineTo(p[0], p[1]) : ctx.moveTo(p[0], p[1]); });
                ctx.lineTo(pts[pts.length - 1][0], H); ctx.lineTo(pad, H); ctx.closePath();
                var grad = ctx.createLinearGradient(0, base - amp * 2, 0, H);
                grad.addColorStop(0, 'rgba(66,133,244,0.25)'); grad.addColorStop(1, 'rgba(66,133,244,0)');
                ctx.fillStyle = grad; ctx.fill();

                ctx.beginPath();
                pts.forEach(function (p, n) { n ? ctx.lineTo(p[0], p[1]) : ctx.moveTo(p[0], p[1]); });
                var lg = ctx.createLinearGradient(pad, 0, W - pad, 0);
                lg.addColorStop(0, 'rgba(138,180,248,0.35)'); lg.addColorStop(1, '#8ab4f8');
                ctx.strokeStyle = lg; ctx.lineWidth = 2; ctx.lineJoin = 'round'; ctx.stroke();

                ctx.strokeStyle = 'rgba(255,255,255,0.10)'; ctx.lineWidth = 1;
                ctx.beginPath(); ctx.moveTo(pad, base + amp * 1.6); ctx.lineTo(W - pad, base + amp * 1.6); ctx.stroke();

                var last = pts[pts.length - 1];
                ctx.fillStyle = '#8ab4f8';
                ctx.globalAlpha = 0.22 + 0.12 * Math.sin(t * 0.07);
                ctx.beginPath(); ctx.arc(last[0], last[1], 9, 0, Math.PI * 2); ctx.fill();
                ctx.globalAlpha = 1;
                ctx.beginPath(); ctx.arc(last[0], last[1], 3, 0, Math.PI * 2); ctx.fill();
            }

            if (! reduce || t < 200) raf = requestAnimationFrame(frame);
        }

        resize();
        window.addEventListener('resize', resize);
        frame();
    })();
</script>
