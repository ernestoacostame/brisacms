# Complemento KutPod ➔ BrisaCMS

Este directorio contiene el complemento para instalar en **KutPod** (`/home/elav/Developer/KutStudio/kutpod/plugins/brisacms/`).

## 1. Instalación en KutPod

Copia esta carpeta al directorio de plugins de KutPod:

```bash
mkdir -p /home/elav/Developer/KutStudio/kutpod/plugins/brisacms
cp brisacms.php opciones.php plugin.json /home/elav/Developer/KutStudio/kutpod/plugins/brisacms/
```

Luego, en el panel de administración de KutPod ve a **Plugins** y activa **BrisaCMS Webhook Publisher**. En sus opciones, pega la URL del Webhook generada en BrisaCMS y el Token Secreto.

---

## 2. Timestamps Interactivos en el Reproductor Embebido (`embed.php`)

Para que al hacer clic en un minuto (ej. `04:15`) en un post de BrisaCMS el reproductor de KutPod salte directamente al audio sin recargar, añade estas líneas antes del cierre `})();` en `/home/elav/Developer/KutStudio/kutpod/public/embed.php`:

```javascript
// Soporte de control remoto vía postMessage desde BrisaCMS
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
