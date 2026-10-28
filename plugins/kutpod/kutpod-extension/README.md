# Complemento KutPod ➔ KutBlog

Este directorio contiene el complemento para instalar en **KutPod** (`/ruta_tu_carpeta/kutpod/plugins/KutBlog/`).

## 1. Instalación en KutPod

Copia esta carpeta al directorio de plugins de KutPod:

```bash
mkdir -p /ruta_tu_carpeta/kutpod/plugins/KutBlog
cp kutblog.php opciones.php plugin.json /ruta_tu_carpeta/kutpod/plugins/KutBlog/
```

Luego, en el panel de administración de KutPod ve a **Plugins** y activa **KutBlog Webhook Publisher**. En sus opciones, pega la URL del Webhook generada en KutBlog y el Token Secreto.

---

## 2. Timestamps Interactivos en el Reproductor Embebido (`embed.php`)

Para que al hacer clic en un minuto (ej. `04:15`) en un post de KutBlog el reproductor de KutPod salte directamente al audio sin recargar, añade estas líneas antes del cierre `})();` en `/ruta_tu_carpeta/kutpod/public/embed.php`:

```javascript
// Soporte de control remoto vía postMessage desde KutBlog
window.addEventListener('message', (event) => {
  if (!event.data || typeof event.data !== 'object') return;
  if (event.data.action === 'seek') {
    const time = parseFloat(event.data.time);
    if (!isNaN(time) && isFinite(time)) {
      audio.currentTime = Math.max(0, Math.min(audio.duration || time, time));
      if (audio.paused) audio.play().catch(() => {});
    }
  } else if (event.data.action === 'play') {
    audio.play().catch(() => {});
  } else if (event.data.action === 'pause') {
    audio.pause();
  }
});
```
