<?php
require __DIR__ . '/config.php';

$projects = db()->query('SELECT * FROM projects ORDER BY sort_order, id')->fetchAll();
$currently = db()->query('SELECT * FROM currently ORDER BY sort_order, id')->fetchAll();
$posts = db()->query(
    "SELECT p.*, c.name AS cat_name FROM posts p
     LEFT JOIN categories c ON c.id = p.category_id
     WHERE p.status = 'published'
     ORDER BY p.created_at DESC LIMIT 3"
)->fetchAll();

$meta_title = setting('site_title');
$meta_desc  = setting('site_description');
$ON_HOME = true;
require __DIR__ . '/partials/header.php';
?>

<main id="top">

<!-- HERO -->
<section class="hero">
<div class="wrap hero-inner">
<div class="hero-text">
<div class="eyebrow mono"><?= e(setting('hero_eyebrow')) ?></div>
<h1 class="display"><?= e(setting('hero_heading')) ?></h1>
<p class="lead"><?= e(setting('hero_lead')) ?></p>
<div class="btns">
<a href="#contact" class="btn btn-primary">Get in touch</a>
<a href="<?= e(url('blog.php')) ?>" class="btn btn-ghost">Read the blog</a>
</div>
<div class="stack mono"><?= e(setting('stack_line')) ?></div>
</div>
<div class="term">
<div class="term-bar">
<div class="term-dots"><span></span><span></span><span></span></div>
<span class="term-title mono"><?= e(setting('term_title')) ?></span>
</div>
<div class="term-body mono">
<div><span class="p">$</span> <span class="g">whoami</span></div>
<div><?= e(setting('term_whoami')) ?></div>
<div class="sp"></div>
<div><span class="p">$</span> <span class="g">cat focus.txt</span></div>
<?php foreach (preg_split('/\R/', setting('term_focus')) as $line): if (trim($line) === '') continue; ?>
<div><span class="p">→</span> <?= e(trim($line)) ?></div>
<?php endforeach; ?>
<div class="sp"></div>
<div><span class="p">$</span> <span class="g">location --now</span></div>
<div><?= e(setting('term_location')) ?></div>
<div style="height:8px"></div>
<div><span class="p">$</span> <span class="cursor">&nbsp;</span></div>
</div>
</div>
</div>
</section>

<!-- FOCUS -->
<section id="focus" class="section">
<div class="wrap section-inner">
<div class="eyebrow mono"><?= e(setting('focus_eyebrow')) ?></div>
<h2 class="display"><?= e(setting('focus_heading')) ?></h2>
<p class="lead" style="margin:8px 0 0; font-size:16px;"><?= e(setting('focus_lead')) ?></p>
<div class="grid-focus">
<div class="card focus-card">
<div class="icon"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l7 2.5v5.5c0 4.4-3 7.4-7 8.5-4-1.1-7-4.1-7-8.5V5.5z"/><path d="M9 12l2 2 4-4"/></svg></div>
<div class="card-title display"><?= e(setting('focus_1_title')) ?></div>
<div class="card-desc"><?= e(setting('focus_1_desc')) ?></div>
</div>
<div class="card focus-card">
<div class="icon"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M8 8l-4 4 4 4"/><path d="M16 8l4 4-4 4"/><path d="M13.5 6l-3 12"/></svg></div>
<div class="card-title display"><?= e(setting('focus_2_title')) ?></div>
<div class="card-desc"><?= e(setting('focus_2_desc')) ?></div>
</div>
<div class="card focus-card">
<div class="icon"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="6" y="6" width="12" height="12" rx="2"/><rect x="9.5" y="9.5" width="5" height="5" rx="1"/><path d="M9 3v3M15 3v3M9 18v3M15 18v3M3 9h3M3 15h3M18 9h3M18 15h3"/></svg></div>
<div class="card-title display"><?= e(setting('focus_3_title')) ?></div>
<div class="card-desc"><?= e(setting('focus_3_desc')) ?></div>
</div>
<div class="card focus-card">
<div class="icon"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12h4l2-6 4 12 2-6h6"/></svg></div>
<div class="card-title display"><?= e(setting('focus_4_title')) ?></div>
<div class="card-desc"><?= e(setting('focus_4_desc')) ?></div>
</div>
</div>
</div>
</section>

<!-- PROJECTS -->
<section id="projects" class="section">
<div class="wrap section-inner">
<div class="eyebrow mono">// selected work</div>
<h2 class="display">Things I've built.</h2>
<p class="lead" style="margin:8px 0 0; font-size:16px;">A place for personal projects and the work I take on for clients.</p>
<div class="grid-2">
<?php foreach ($projects as $pr): $img = img_src($pr['image']); ?>
<div class="card proj">
<div class="proj-img mono"<?= $img ? ' style="background-image:url(\'' . e($img) . '\')"' : '' ?>><?= $img ? '' : '[ screenshot / photo ]' ?></div>
<div class="proj-body">
<div class="tag mono"><?= e($pr['kind']) ?></div>
<div class="proj-name display"><?= e($pr['title']) ?></div>
<div class="card-desc" style="margin-bottom:16px;"><?= e($pr['description']) ?></div>
<a href="<?= e($pr['link'] ?: '#') ?>">View project <span class="arrow">→</span></a>
</div>
</div>
<?php endforeach; ?>
</div>
</div>
</section>

<!-- CURRENTLY -->
<section id="currently" class="section">
<div class="wrap section-inner">
<div class="head-row">
<div>
<div class="eyebrow mono">// currently</div>
<h2 class="display">Where I am right now.</h2>
<p class="lead" style="margin:8px 0 0; font-size:16px;">A rolling strip of photos from wherever the work takes me.</p>
</div>
<span class="mono" style="font-size:12px; color:var(--muted); white-space:nowrap;"><?= e(setting('currently_now')) ?></span>
</div>
<div class="slider">
<button type="button" class="slide-btn prev" aria-label="Anterior"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 6l-6 6 6 6"/></svg></button>
<div class="gallery">
<?php foreach ($currently as $c): $img = img_src($c['image']); ?>
<div class="shot"<?= $img ? ' style="background-image:url(\'' . e($img) . '\')"' : '' ?>>
<?= $img ? '' : '<span class="shot-tag mono">[ photo ]</span>' ?>
<div class="shot-cap">
<div class="shot-city mono"><?= $c['is_now'] ? 'NOW · ' : '' ?><?= e(strtoupper($c['city'])) ?></div>
<div class="shot-note"><?= e($c['note']) ?></div>
</div>
</div>
<?php endforeach; ?>
</div>
<button type="button" class="slide-btn next" aria-label="Siguiente"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 6l6 6-6 6"/></svg></button>
</div>
<script>
document.querySelectorAll('.slider').forEach(function (sl) {
  var g = sl.querySelector('.gallery'), prev = sl.querySelector('.prev'), next = sl.querySelector('.next');
  function step() { var c = g.querySelector('.shot'); return c ? c.offsetWidth + 18 : g.clientWidth; }
  function update() {
    prev.disabled = g.scrollLeft <= 2;
    next.disabled = g.scrollLeft + g.clientWidth >= g.scrollWidth - 2;
    sl.classList.toggle('no-overflow', g.scrollWidth <= g.clientWidth + 2);
  }
  prev.addEventListener('click', function () { g.scrollBy({ left: -step(), behavior: 'smooth' }); });
  next.addEventListener('click', function () { g.scrollBy({ left: step(), behavior: 'smooth' }); });
  g.addEventListener('scroll', update, { passive: true });
  window.addEventListener('resize', update);
  window.addEventListener('load', update);
  update();
});
</script>
</div>
</section>

<!-- WRITING -->
<section id="writing" class="section">
<div class="wrap section-inner">
<div class="head-row">
<div>
<div class="eyebrow mono">// from the notebook</div>
<h2 class="display">Writing &amp; build logs.</h2>
<p class="lead" style="margin:8px 0 0; font-size:16px;">Where I am, what I'm building, and what I'm reading right now.</p>
</div>
<a href="<?= e(url('blog.php')) ?>" class="navlink" style="white-space:nowrap;">All posts →</a>
</div>
<div class="grid-3">
<?php if (!$posts): ?>
<p class="lead">No posts yet — write your first one from the admin panel.</p>
<?php endif; ?>
<?php foreach ($posts as $po): ?>
<div class="card post">
<div class="post-top">
<span class="post-cat mono"><?= e(strtoupper($po['cat_name'] ?? 'NOTE')) ?></span>
<span class="post-date mono"><?= e(date('M j, Y', strtotime($po['created_at']))) ?></span>
</div>
<div class="post-title display"><?= e($po['title']) ?></div>
<div class="card-desc" style="margin-bottom:18px;"><?= e($po['excerpt']) ?></div>
<a href="<?= e(url('post.php?slug=' . urlencode($po['slug']))) ?>" class="read">Read <span class="arrow">→</span></a>
</div>
<?php endforeach; ?>
</div>
</div>
</section>

<!-- CONTACT -->
<section id="contact" class="section">
<div class="wrap contact-inner">
<div class="contact-left">
<div class="eyebrow mono">// say hello</div>
<h2 class="display">Wherever you are, let's talk.</h2>
<p class="lead" style="margin:22px 0 0; font-size:17px; line-height:1.6; max-width:46ch;">I work across time zones and I'm open to projects, collaborations and the occasional contract. Email is the fastest way to reach me.</p>
<div class="contact-rows">
<div class="crow">
<span class="ic"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/></svg></span>
<a href="mailto:<?= e(setting('contact_email')) ?>" class="mono" style="font-size:16px;"><?= e(setting('contact_email')) ?></a>
</div>
<div class="crow">
<span class="ic"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s-6-5.2-6-10a6 6 0 1 1 12 0c0 4.8-6 10-6 10z"/><circle cx="12" cy="11" r="2.2"/></svg></span>
<span style="font-size:15.5px; color:var(--muted);"><?= e(setting('location_line')) ?></span>
</div>
</div>
<div class="socials">
<a href="<?= e(setting('github_url')) ?>" class="social"><svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><path d="M12 .5C5.4.5 0 5.9 0 12.6c0 5.3 3.4 9.8 8.2 11.4.6.1.8-.3.8-.6v-2c-3.3.7-4-1.6-4-1.6-.6-1.4-1.3-1.8-1.3-1.8-1.1-.7.1-.7.1-.7 1.2.1 1.8 1.2 1.8 1.2 1.1 1.8 2.8 1.3 3.5 1 .1-.8.4-1.3.8-1.6-2.7-.3-5.5-1.3-5.5-5.9 0-1.3.5-2.4 1.2-3.2-.1-.3-.5-1.5.1-3.2 0 0 1-.3 3.3 1.2a11.5 11.5 0 0 1 6 0c2.3-1.5 3.3-1.2 3.3-1.2.6 1.7.2 2.9.1 3.2.8.8 1.2 1.9 1.2 3.2 0 4.6-2.8 5.6-5.5 5.9.4.4.8 1.1.8 2.2v3.3c0 .3.2.7.8.6A12 12 0 0 0 24 12.6C24 5.9 18.6.5 12 .5z"/></svg>GitHub</a>
<a href="<?= e(setting('linkedin_url')) ?>" class="social"><svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><path d="M4.98 3.5A2.5 2.5 0 1 0 5 8.5a2.5 2.5 0 0 0 0-5zM3 9h4v12H3zM9 9h3.8v1.7h.05c.53-1 1.8-2 3.7-2 4 0 4.7 2.6 4.7 6V21h-4v-5.3c0-1.3 0-3-1.8-3s-2.1 1.4-2.1 2.9V21H9z"/></svg>LinkedIn</a>
<a href="<?= e(setting('x_url')) ?>" class="social"><svg viewBox="0 0 24 24" width="17" height="17" fill="currentColor"><path d="M18.2 3h3.3l-7.2 8.3L23 21h-6.6l-5.2-6.8L5.3 21H2l7.7-8.9L1.6 3h6.8l4.7 6.2L18.2 3zm-1.2 16h1.8L7.1 4.9H5.2L17 19z"/></svg>X</a>
</div>
</div>
<div class="form">
<div class="mono" style="font-size:12px; letter-spacing:1px; color:var(--muted); text-transform:uppercase; margin-bottom:22px;">Send a message</div>
<div class="field"><label class="form-label" for="cname">Name</label><input class="input" id="cname" type="text" placeholder="Your name"></div>
<div class="field"><label class="form-label" for="cemail">Email</label><input class="input" id="cemail" type="email" placeholder="you@example.com"></div>
<div><label class="form-label" for="cmsg">Message</label><textarea class="input" id="cmsg" rows="4" placeholder="What's on your mind?"></textarea></div>
<button type="button" class="send">Send message</button>
</div>
</div>
</section>

</main>

<?php require __DIR__ . '/partials/footer.php'; ?>
