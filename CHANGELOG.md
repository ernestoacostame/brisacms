# Changelog

Todos los cambios notables de este proyecto están documentados en este archivo.

El formato se basa en [Keep a Changelog](https://keepachangelog.com/es-ES/1.1.0/)
y este proyecto se adhiere a [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
- **Panel de administración de usuarios** (`admin/users.php`):
  - Listado con tarjetas y avatares, roles tipificados con etiquetas de color, estado de 2FA y fecha de último acceso.
  - Modal interactivo para crear nuevos usuarios con validación de fortaleza de contraseñas mediante Argon2id.
  - Modal para editar perfil, correo, rol y cambiar contraseñas.
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
  - Generador de códigos QR renderizado directamente en `<canvas>` HTML5 sin librerías pesadas ni servicios de terceros que comprometan la privacidad.
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
