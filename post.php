<?php
require __DIR__ . '/config.php';

$slug = trim($_GET['slug'] ?? '');
$st = db()->prepare(
    "SELECT p.*, c.name AS cat_name, c.slug AS cat_slug
     FROM posts p LEFT JOIN categories c ON c.id = p.category_id
     WHERE p.slug = ? AND p.status = 'published' LIMIT 1"
);
$st->execute([$slug]);
$post = $st->fetch();

if (!$post) {
    http_response_code(404);
    $meta_title = 'Not found — ' . setting('site_title');
    $meta_desc = '';
    require __DIR__ . '/partials/header.php';
    echo '<main class="wrap"><div class="article"><h1>404</h1><p class="lead">That post does not exist. <a href="' . e(url('blog.php')) . '">Back to writing →</a></p></div></main>';
    require __DIR__ . '/partials/footer.php';
    exit;
}

$tt = db()->prepare(
    'SELECT t.name, t.slug FROM tags t
     JOIN post_tags pt ON pt.tag_id = t.id WHERE pt.post_id = ? ORDER BY t.name'
);
$tt->execute([(int) $post['id']]);
$ptags = $tt->fetchAll();

$cover = img_src($post['cover_image']);
$meta_title = ($post['seo_title'] ?: $post['title']) . ' — ' . setting('site_title');
$meta_desc  = $post['seo_description'] ?: $post['excerpt'];
$og_image   = $cover;
require __DIR__ . '/partials/header.php';
?>
<main class="wrap">
<article class="article">
<a class="back" href="<?= e(url('blog.php')) ?>">← Writing</a>
<h1 class="display" style="margin-top:14px;"><?= e($post['title']) ?></h1>
<div class="meta mono">
<?php if ($post['cat_name']): ?><span style="color:var(--accent)"><?= e($post['cat_name']) ?></span> · <?php endif; ?>
<?= e(date('F j, Y', strtotime($post['created_at']))) ?>
</div>
<?php if ($cover): ?><img class="cover" src="<?= e($cover) ?>" alt="<?= e($post['title']) ?>"><?php endif; ?>
<div class="body">
<?= render_body($post['body']) /* HTML del autor + bloques ``` de código */ ?>
</div>
<?php if ($ptags): ?>
<div style="margin-top:34px;">
<?php foreach ($ptags as $t): ?>
<a class="pill" href="<?= e(url('blog.php?tag=' . urlencode($t['slug']))) ?>">#<?= e($t['name']) ?></a>
<?php endforeach; ?>
</div>
<?php endif; ?>
</article>
</main>
<!-- Bloques de código: colores (highlight.js) + botón de copiar -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/github-dark.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>
<script>
(function () {
  var ICON_COPY = '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15V6a2 2 0 0 1 2-2h9"/></svg>';
  var ICON_OK = '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12l5 5 9-10"/></svg>';

  function copyText(text) {
    if (navigator.clipboard && window.isSecureContext) return navigator.clipboard.writeText(text);
    return new Promise(function (ok, fail) {
      var ta = document.createElement('textarea');
      ta.value = text; ta.style.position = 'fixed'; ta.style.opacity = '0';
      document.body.appendChild(ta); ta.select();
      try { document.execCommand('copy') ? ok() : fail(); } catch (e) { fail(e); }
      document.body.removeChild(ta);
    });
  }

  document.querySelectorAll('.article .body pre').forEach(function (pre) {
    var code = pre.querySelector('code') || pre;
    if (window.hljs && code !== pre) { try { hljs.highlightElement(code); } catch (e) {} }

    var box = document.createElement('div');
    box.className = 'code-box';
    var bar = document.createElement('div');
    bar.className = 'code-bar';
    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'code-copy';
    btn.title = 'Copiar código';
    btn.setAttribute('aria-label', 'Copiar código');
    btn.innerHTML = ICON_COPY;
    var lang = document.createElement('span');
    lang.className = 'code-lang mono';
    lang.textContent = pre.getAttribute('data-lang') || 'code';
    bar.appendChild(btn);
    bar.appendChild(lang);

    pre.parentNode.insertBefore(box, pre);
    box.appendChild(bar);
    box.appendChild(pre);

    var timer;
    btn.addEventListener('click', function () {
      copyText(code.innerText).then(function () {
        btn.innerHTML = ICON_OK; btn.classList.add('ok');
        clearTimeout(timer);
        timer = setTimeout(function () { btn.innerHTML = ICON_COPY; btn.classList.remove('ok'); }, 1600);
      });
    });
  });
})();
</script>
<?php require __DIR__ . '/partials/footer.php'; ?>
