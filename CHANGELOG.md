# Changelog

Todos los cambios notables de este proyecto están documentados en este archivo.

El formato se basa en [Keep a Changelog](https://keepachangelog.com/es-ES/1.1.0/)
y este proyecto se adhiere a [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [2.2.0] - 2026-10-27

### 🎙️ Integración Oficial con KutPod Podcast Studio

#### 📦 1. Nuevo Plugin Oficial: KutPod Studio (`plugins/kutpod/`)
- **Automatización de Publicaciones y Sincronización**:
  - **Receptor de Webhooks** (`/api/kutpod/webhook`): Endpoint seguro que escucha eventos en tiempo real desde KutPod (`episode.published`, `episode.updated`, `episode.deleted`) firmado con Token Secreto.
  - **Creación automática de borradores (Drafting)**: Al publicar un nuevo episodio en KutPod, se genera automáticamente un artículo en `content/articles/{slug}.json` con título, notas, carátula, extracto y metadatos (`podcast_meta`). Es posible configurar si entra como borrador para revisión o publicado directamente.
  - **Sincronización bidireccional continua**: Las actualizaciones de notas, título o audio en KutPod se reflejan automáticamente en el blog.
  - **Motor de sincronización directa** (`plugins/kutpod/sync.php`): Soporte dual con lectura directa ultrarrápida desde la base de datos SQLite de KutPod (`storage/kutpod.db`) o consultas a la API REST remota.
- **Experiencia de Usuario y Contenido Enriquecido**:
  - **Reproductor incrustado adaptable y Auto-Embed universal**: Detección inteligente de cualquier URL de KutPod (pegada en el cuerpo del texto, vía shortcode `[kutpod]`, o en el campo lateral `kutpod_url` del editor). Resuelve automáticamente el show y episodio correspondiente (`/embed/@{show}/{ep}`) evitando errores 404 de "Episodio no encontrado", con selección de tema (*Automático*, *Oscuro*, *Claro*) y ubicación configurable (*Arriba*, *Al pie*, o manual).
  - **Timestamps cliqueables (Capítulos interactivos)**: Parseo automático de marcas de tiempo (`04:15`, `[12:30]`, `1:05:20`) en las notas del post, transformándolas en botones `⏱️ MM:SS` que controlan el reproductor en tiempo real mediante `postMessage`.
  - **Transcripciones estructuradas y SEO Boost**: Renderizado de transcripciones completas en un bloque colapsable `<details class="kutpod-transcript-details">`, completamente indexable por buscadores.
  - **Microdatos Schema.org y OpenGraph**: Generación automática de etiquetas JSON-LD `PodcastEpisode` y `og:audio` para fragmentos enriquecidos en Google y redes sociales.
  - **Insignia de Estadísticas públicas**: Contador visible en el post con duración acumulada, temporada/episodio y número de escuchas directas desde OP3 (`🎧 1.4k escuchas`).
- **Descubrimiento y Navegación Dinámica**:
  - **Directorio público de podcasts** (`/podcasts`): Sección dedicada para explorar y escuchar todos los episodios con filtros dinámicos por temporada y buscador instantáneo.
  - **Buscador global unificado**: Búsqueda ampliada en `search_content()` que indexa notas y transcripciones de los episodios, marcándolos con la etiqueta `[🎙️ Podcast]`.
  - **Sección de "Episodios Relacionados"**: Inyección automática al pie de artículos normales del blog de tarjetas recomendando episodios de podcasts con temáticas o etiquetas afines.
- **Panel de Configuración Completo** (`admin/plugins.php?plugin=kutpod`):
  - Detección en tiempo real del estado de conexión SQLite o remoto.
  - Botón de *"Sincronizar episodios ahora"* con reporte inmediato de artículos creados y actualizados.
  - Copia rápida con un clic de la URL del webhook y generador de tokens aleatorios seguros.
  - Opciones de drafting, diseño del reproductor, directorio y buscador.

#### 🛠️ 2. Mejoras en el Núcleo de BrisaCMS
- **Motor de Temas (`core/theme.php`)**:
  - Inyección de ganchos en `render_theme()` para filtrado automático de contenido (`kutpod_filter_content`), inclusión de estilos en cabecera (`kutpod_theme_header`) y scripts interactivos en el pie (`kutpod_theme_footer`).
- **Enrutamiento Principal (`index.php`)**:
  - Enrutamiento transparente hacia el plugin KutPod para webhooks, sincronización y la ruta pública `/podcasts`.
- **Buscador (`core/content.php`)**:
  - Función `search_content()` optimizada para indexar el campo `podcast_meta.transcript` y marcar los resultados de audio.
- **Editor de Artículos (`admin/editor.php`)**:
  - Nuevo panel lateral *"🎙️ KutPod Podcast"* con resumen de episodio sincronizado y campo de URL para incrustación manual.
  - Preservación íntegra del objeto `podcast_meta` en las rutinas de guardado y autoguardado local.

---

### 📋 Guía de Instalación y Conexión con KutPod

#### 1. En BrisaCMS:
1. Ve a **Admin → Plugins → Configurar KutPod Studio**.
2. Verifica que el modo de conexión esté en **Local (SQLite)** si KutPod reside en el mismo servidor (ruta predeterminada: `/home/elav/Developer/KutStudio/kutpod/storage/kutpod.db`), o selecciona **Remoto** e indica la URL pública.
3. Haz clic en **"Sincronizar episodios ahora"** para importar todos los episodios existentes a tu blog.
4. Copia la **URL del Webhook** y el **Token Secreto**.

#### 2. En KutPod:
1. Copia la extensión complementaria incluida en `plugins/kutpod/kutpod-extension/` hacia KutPod:
   ```bash
   mkdir -p /tu_Carpeta/kutpod/plugins/brisacms
   cp -r /tu_Carpeta/BrisaCMS/plugins/kutpod/kutpod-extension/* /tu_Carpeta/kutpod/plugins/brisacms/
   ```
2. Entra al panel de KutPod (**Admin → Plugins**), activa **BrisaCMS Webhook Publisher** y pega la URL del Webhook y el Token Secreto.
3. En `/tu_Carpeta/kutpod/public/embed.php`, añade el receptor de `postMessage` antes del cierre `})();` para habilitar el salto de timestamps:
   ```javascript
   window.addEventListener('message', (event) => {
     if (!event.data || typeof event.data !== 'object') return;
     if (event.data.action === 'seek') {
       const time = parseFloat(event.data.time);
       if (!isNaN(time) && isFinite(time)) {
         audio.currentTime = Math.max(0, Math.min(audio.duration || time, time));
         if (audio.paused) audio.play().catch(() => {});
       }
     }
   });
   ```

#### 💡 ¿Cómo funciona la integración?

- **Los audios y carátulas se quedan siempre en KutPod:**  
  BrisaCMS nunca descarga ni almacena los archivos de audio; el reproductor simplemente transmite el streaming directamente desde KutPod.

- **Lo que se crea en el blog es una entrada de texto (artículo):**  
  Crea un archivo JSON en `content/articles/` con:
  - El título del episodio.
  - Las notas del programa (texto descriptivo formateado).
  - El reproductor embebido (arriba, abajo o manual).
  - El estado inicial como **Borrador (Draft)** para que tú decidas cuándo publicarlo.

- **¿Para qué sirve el botón de *"Sincronizar ahora"*?**  
  Solo es necesario si ya tienes episodios publicados anteriormente en KutPod y quieres que aparezcan como borradores en el blog de una sola vez, sin copiarlos a mano.

- **¿Y si solo quieres poner un episodio dentro de un post que escribes a mano?**  
  No necesitas sincronizar nada. Al redactar cualquier artículo en BrisaCMS:
  1. Pegas la URL del episodio (ej. `https://tupodcast.com/@mishow/mi-episodio`) en el texto o en el campo lateral **"🎙️ KutPod Podcast"**.
  2. Al guardar, el reproductor interactivo se inserta automáticamente en ese post puntual.

---

## [2.1.0] - 2026-10-22

### 🚀 Novedades y Características Principales

#### 👥 1. Sistema Robusto de Gestión de Usuarios y Roles
- **Almacenamiento multi-usuario** independiente y seguro en formato JSON estructurado (`content/users/{username}.json`).
- **Sistema de roles y permisos**:
  - `administrator`: Acceso total al sistema, configuración general, gestión de usuarios, plugins y temas.
  - `editor`: Gestión completa de contenidos (artículos, páginas, categorías, etiquetas) y biblioteca de medios.
  - `author`: Creación y edición de sus propios artículos y subida de archivos multimedia.
- **Control de capacidades granular**: función `user_can($capability, $user)` para validar permisos en cada sección del panel administrativo.
- **Panel de administración de usuarios** (`admin/users.php`) y **Sección Mi Perfil** (`admin/settings.php#profile`):
  - **Soporte completo de fotos de avatar**: subida directa de imágenes (JPG, PNG, WebP, GIF), previsualización en vivo y opción para eliminar el avatar.
  - Almacenamiento optimizado en `media/avatars/` con redimensionamiento y caché WebP integrado.
  - Visualización del avatar real del usuario en la barra lateral del panel (`admin/layout.php`) y en las listas de usuarios, con alternativa elegante de inicial cuando no hay foto.
  - Modal interactivo para crear nuevos usuarios con validación de contraseñas mediante Argon2id.
  - Modal para editar perfil, correo, rol, contraseña y avatar.
  - Protección de seguridad para evitar la eliminación o auto-desactivación del último administrador activo.
  - Migración y siembra transparente del administrador existente desde `config.json` hacia `content/users/`.

#### 🔐 2. Seguridad Reforzada y Autenticación en Dos Pasos (2FA / TOTP)
- **Implementación nativa de RFC 6238 (TOTP)** en PHP puro sin dependencias externas:
  - Generación de secretos en Base32 criptográficamente seguros (`totp_generate_secret()`).
  - Cálculo y verificación de códigos TOTP de 6 dígitos con ventana de tolerancia temporal (`totp_verify()`).
  - Compatible con cualquier aplicación de autenticación estándar: Google Authenticator, Authy, Microsoft Authenticator, 1Password, Bitwarden, etc.
- **Códigos de recuperación de un solo uso**:
  - Generación automática de 8 códigos de emergencia únicos al activar 2FA.
  - Almacenamiento con hash Argon2id / bcrypt; un código consumido queda inmediatamente invalidado.
- **Flujo de inicio de sesión en dos pasos** (`admin/login.php`):
  - Paso 1: Verificación de usuario y contraseña contra Argon2id.
  - Paso 2: Solicitud interactiva del código TOTP de 6 dígitos o código de recuperación si 2FA está habilitado.
  - Protección contra fuerza bruta con limitación de intentos y bloqueo temporal tras fallos consecutivos.
- **Asistente de configuración 2FA** (`admin/settings.php`):
  - Generador de códigos QR real y estandarizado ISO/IEC 18004 (`admin/assets/js/qrcode.min.js`) 100% offline y compatible con la cámara de iPhone (iOS Camera / Llavero de iCloud), Google Authenticator, Authy y 1Password.
  - Enlace directo `otpauth://` para abrir la app de autenticación con un solo toque desde dispositivos móviles.
  - Clave secreta visible en texto plano para configuración manual.
  - Confirmación obligatoria con código antes de activar el 2FA en la cuenta.
  - Modal para visualizar, copiar e imprimir los códigos de emergencia.

#### ⚙️ 3. Panel de Preferencias con Secciones Laterales
- **Rediseño completo de la interfaz de configuración** (`admin/settings.php`):
  - Navegación lateral por pestañas con iconos intuitivos y transiciones suaves:
    - **General**: Título, descripción del sitio, URL base y configuración de idioma.
    - **Apariencia**: Temas instalados, personalización de colores, logotipo y favicon.
    - **Seguridad & 2FA**: Estado de autenticación en dos pasos, asistente de activación/desactivación y gestión de sesiones.
    - **Usuarios**: Acceso directo y resumen del equipo editorial.
    - **Multimedia & Caché**: Configuración del motor de optimización WebP, límites de subida y estado de la caché en disco.
    - **Mastodon / Fediverso**: Configuración de integración con ActivityPub y cuentas federadas.
  - Soporte de anclaje directo por URL (`admin/settings.php#security`, etc.) para recordar la última sección visitada.

#### 🖼️ 4. Gestión Avanzada de Multimedia y Metadatos de Accesibilidad (SEO & Alt Text)
- **Almacenamiento de metadatos centralizado** (`content/media_metadata.json`, gestionado por `core/media.php`):
  - Registro de texto alternativo (`alt`), título/leyenda visible (`title`), pie de foto (`caption`) y descripción extendida (`description`).
- **Inspector interactivo de medios en modal/drawer** (`admin/media.php`):
  - Al hacer clic sobre cualquier archivo de la biblioteca, se abre una ventana modal con:
    - Vista previa a alta resolución, dimensiones, tipo MIME, tamaño y fecha.
    - Campos editables para Alt text, Título, Pie de foto y Descripción.
    - Botón de guardado asíncrono vía AJAX con confirmación visual instantánea sin recargar la página.
    - Acciones rápidas de un clic: Copiar URL, Copiar código Markdown (`![alt](url)`) y Copiar etiqueta HTML (`<img src="..." alt="...">`).
- **Indicadores visuales en biblioteca**:
  - Etiqueta verde distintiva `ALT ✓` en las imágenes que ya cuentan con texto alternativo configurado.
  - Indicador `Sin ALT` en aquellas pendientes de optimización accesible.
- **Integración inteligente con el analizador Markdown** (`core/markdown.php`):
  - Si una imagen insertada en Markdown no define texto alternativo manual, el analizador consulta automáticamente los metadatos almacenados para completar el atributo `alt` y `title`.

#### ⚡ 5. Optimización del Motor de Almacenamiento, Consultas y Caché
- **Índice de contenido en caché de alto rendimiento** (`core/content.php`):
  - Implementación de `get_content_index($type)`: almacena en `cache/content_index_{$type}.json` la metadata pre-ordenada de artículos y páginas.
  - **Eliminación del cuello de botella de E/S de disco**: en lugar de ejecutar `glob()` y abrir cientos de archivos `.json` en cada petición web o paginación, se consulta un índice consolidado con memoria estática.
  - **Aceleración de 10x a 50x** en la carga de listas de artículos, páginas de categorías, feeds RSS y paneles de control.
  - Invalidación atómica automática ante cambios en `save_content()`, `delete_content()` o cuando se detectan modificaciones directas en el directorio de contenido.
  - Búsqueda textual optimizada (`search_content()`) directamente sobre la estructura indizada.
- **Afinación de Base de Datos SQLite** (`plugins/fediverse/activitypub.php`):
  - Configuración de PRAGMAs para máximo rendimiento y seguridad:
    - `PRAGMA synchronous = NORMAL`: Incrementa drásticamente la velocidad de escritura manteniendo integridad en modo WAL.
    - `PRAGMA cache_size = -32000`: Reserva 32 MB de memoria para caché de páginas SQLite.
    - `PRAGMA temp_store = MEMORY`: Tablas e índices temporales operan en RAM.
    - `PRAGMA mmap_size = 67108864`: Acceso directo por mapeo en memoria de hasta 64 MB.
    - `PRAGMA busy_timeout = 5000`: Evita colisiones de bloqueo concurrentes.
  - Nuevos índices de base de datos de alta velocidad:
    - `idx_ap_comments_slug` en comentarios por post y fecha.
    - `idx_ap_comments_reply` para resolución rápida de hilos anidados.
    - `idx_ap_delivery_status_next` para la cola de envíos ActivityPub.
    - `idx_ap_interactions_note` para recuento instantáneo de favoritos y retoots.
    - `idx_ap_followers_inbox` y `idx_ap_outbox_published`.
- **Motor de Caché y Optimización de Imágenes** (`core/image_cache.php`, `api/img.php`):
  - Detección automática del motor GraphicsMagick instalado en el servidor (`/usr/bin/gm`).
  - Conversión automática a formato WebP moderno con compresión visual sin pérdida aparente.
  - Generación de miniaturas al vuelo con recorte centrado inteligente y redimensionamiento proporcionado.
  - Encabezados HTTP 304 Not Modified y ETags para reducir transferencias de red redundantes.
  - Reglas de expiración en `.htaccess` para almacenamiento en caché local del navegador cliente.

#### 🎨 6. Rediseño Visual Moderno del Panel de Administración
- Tipografía moderna cargada desde fuentes web de alta legibilidad (`Plus Jakarta Sans` y `Outfit`).
- Aumento de la escala tipográfica base (15.5px) y jerarquía de encabezados más espaciosa.
- Paleta cromática refinada con variables CSS unificadas para temas oscuro y claro.
- Bordes redondeados más suaves, sombras de elevación sutiles y micro-interacciones al pasar el cursor sobre tarjetas y botones.
- Formularios con mejor contraste, etiquetas en negrita clara y foco accesible con anillo de acento.

---

### 🌐 Cobertura de Idiomas (i18n)
- Traducciones completas en español (`core/lang/es.php`) e inglés (`core/lang/en.php`) para todos los nuevos módulos: gestión de usuarios, roles, 2FA, códigos de recuperación, secciones de ajustes e inspector multimedia.
