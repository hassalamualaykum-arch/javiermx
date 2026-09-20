# javiermx.com — CMS propio (PHP + MariaDB)

Sitio público en inglés + panel de administración en español. Hecho a medida, sin WordPress.

## Qué puedes administrar
- **Posts** (blog) con categoría, etiquetas, extracto, contenido HTML, imagen de portada y campos SEO.
- **Proyectos** (imagen por link o subida, tipo, descripción, enlace, orden).
- **Currently** (las fotos de dónde estás).
- **Categorías** y **Etiquetas**.
- **Ajustes**: textos del hero, correo, redes, color de acento y SEO del sitio.

En cada imagen puedes **subir un archivo** o **pegar un link** — lo que prefieras.

---

## Instalación en Hostinger (paso a paso)

### 1) Crea la base de datos
hPanel → **Databases → MySQL Databases**. Crea una base de datos y un usuario.
Anota estos 4 datos (los necesitas en el paso 3):
- Host: normalmente `localhost`
- Nombre de la base de datos
- Usuario
- Contraseña

### 2) Importa las tablas
hPanel → **phpMyAdmin** → elige tu base de datos → pestaña **Import** → sube `schema.sql` → **Go**.

### 3) Pon tus credenciales
Abre `config.php` y edita solo el bloque de arriba:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'tu_base');
define('DB_USER', 'tu_usuario');
define('DB_PASS', 'tu_password');
```
Si el sitio vive en la raíz del dominio, deja `BASE_URL` vacío (`''`).

### 4) Sube los archivos
hPanel → **File Manager** (o FTP). Sube **todo el contenido** de esta carpeta a `public_html/`.
Deja la carpeta `uploads/` con permisos de escritura (**755** o **775**).

### 5) Crea tu usuario admin
Entra en tu navegador a: `https://javiermx.com/admin/setup.php`
Crea usuario y contraseña. **Después borra el archivo `admin/setup.php`** (te lo recordará).

### 6) Entra al panel
`https://javiermx.com/admin/login.php`
Desde ahí edita Ajustes, crea categorías, escribe posts, agrega proyectos y fotos.

---

## El dominio
Como el CMS necesita PHP, el dominio **javiermx.com** debe apuntar al **hosting de Hostinger**
(no a GitHub Pages, que solo sirve sitios estáticos). Si el dominio ya está en tu cuenta de
Hostinger y usas su hosting, normalmente ya queda conectado; si no, apunta los DNS al hosting.

## Seguridad incluida
- Contraseñas con `password_hash` (bcrypt).
- Consultas con *prepared statements* (PDO) → sin inyección SQL.
- Tokens CSRF en todos los formularios.
- Escape de HTML en todo lo que se imprime.
- Subidas validadas (JPG/PNG/WEBP/GIF, máx. 5 MB) y carpeta `uploads/` sin ejecución de scripts.

## Estructura
```
config.php            núcleo: BD, sesión, seguridad, helpers
schema.sql            tablas + datos iniciales
index.php             portada (dinámica)
blog.php  post.php    blog y post individual
partials/             cabecera y pie del sitio
assets/site.css       estilos del sitio público
admin/                panel: login, setup, posts, projects, currently,
                      categories, tags, settings
uploads/              imágenes subidas (dale permisos de escritura)
```
