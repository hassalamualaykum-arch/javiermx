# javiermx.com

Personal site — security, software, Arduino &amp; longevity. Single static page, no build step.

## Files
- `index.html` — the whole site (HTML + CSS in one file, responsive).
- `.nojekyll` — tells GitHub Pages to publish files as-is (skip Jekyll).

## Edit
Everything is in `index.html`. Look for the `[ ... ]` placeholders and swap them:
- `hola@javiermx.com` → your real email
- `[ Project name ]`, `[ DATE ]`, `[ CITY ]`, `[ what you were up to ]`
- The `#` links on GitHub / LinkedIn / X → your real profile URLs
- To change the accent colour, edit `--accent` near the top of the `<style>`.

## Deploy (GitHub Pages)
1. Push these files to the repo **root** (not inside a folder).
2. Settings → Pages → Branch `main`, folder `/ (root)` → Save.
3. Live at `https://hassalamualaykum-arch.github.io/<repo-name>/`.
4. Custom domain (javiermx.com): Settings → Pages → Custom domain → add it, then point the DNS at Hostinger.
