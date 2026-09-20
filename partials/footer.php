<footer class="section">
<div class="wrap footer-inner">
<div class="foot-left">
<span class="mono" style="font-size:16px; color:var(--text);">javier<span style="color:var(--accent)">mx</span></span>
<span style="font-size:14px; color:var(--muted);">© <?= date('Y') ?> Javier · Built in Toronto.</span>
</div>
<div class="foot-links">
<a href="<?= e(url('index.php')) ?>" class="navlink" style="font-size:14px;">Home</a>
<a href="<?= e(url('blog.php')) ?>" class="navlink" style="font-size:14px;">Writing</a>
<a href="<?= e(url('index.php')) ?>#top" class="navlink" style="font-size:14px;">Top ↑</a>
</div>
</div>
</footer>
<script>
(function () {
  if (location.hash === '#contact') {
    window.addEventListener('load', function () {
      var el = document.getElementById('contact');
      if (el) el.scrollIntoView({ block: 'start' });
    });
  }
})();
</script>
</body>
</html>
