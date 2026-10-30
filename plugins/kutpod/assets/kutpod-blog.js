/**
 * KutPod Plugin - Interactive Reader JS for KutBlog
 * Enables clickable timestamps, chapter seeking, and postMessage control
 */

(function () {
  'use strict';

  function initKutPodPlayer() {
    // 1. Vincular clics en timestamps y capítulos
    document.addEventListener('click', function (e) {
      const target = e.target.closest('.kp-timestamp, .kutpod-chapter-item, [data-kp-seek]');
      if (!target) return;

      e.preventDefault();
      const timeVal = target.getAttribute('data-time') || target.getAttribute('data-kp-seek');
      if (timeVal === null || timeVal === undefined) return;

      const seconds = parseFloat(timeVal);
      if (isNaN(seconds)) return;

      // Buscar el iframe del reproductor de KutPod
      const playerFrame = document.querySelector('.kutpod-player-frame, iframe[src*="/embed/"]');
      if (!playerFrame || !playerFrame.contentWindow) {
        console.warn('[KutPod] No se encontró el reproductor incrustado en esta página.');
        return;
      }

      // Enviar comando postMessage
      playerFrame.contentWindow.postMessage({
        action: 'seek',
        time: seconds
      }, '*');

      // Feedback visual en el botón pulsado
      target.classList.add('kp-active-seek');
      setTimeout(() => target.classList.remove('kp-active-seek'), 1200);

      // Si el reproductor no es visible en pantalla, hacer scroll suave hacia él
      const rect = playerFrame.getBoundingClientRect();
      const isVisible = (rect.top >= 0 && rect.bottom <= window.innerHeight);
      if (!isVisible && rect.top < 0) {
        playerFrame.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      }
    });

    // 2. Escuchar respuestas o estados emitidos desde el iframe (opcional)
    window.addEventListener('message', function (event) {
      if (!event.data || typeof event.data !== 'object') return;
      if (event.data.type === 'kutpod_timeupdate') {
        const currentSec = Math.floor(event.data.currentTime || 0);
        highlightActiveChapter(currentSec);
      }
    });

    // 3. Mini Playlist switcher para episodios relacionados
    document.addEventListener('click', function (e) {
      const item = e.target.closest('.kutpod-playlist-item');
      if (!item) return;
      if (e.target.closest('.kutpod-playlist-item-link')) return; // permitir abrir enlace externo directamente

      const card = item.closest('.kutpod-related-card');
      if (!card) return;

      const iframe = card.querySelector('iframe.kutpod-player-frame');
      const titleEl = card.querySelector('.kutpod-related-title a');
      const coverEl = card.querySelector('.kutpod-related-cover');
      const metaContainer = card.querySelector('.kutpod-related-meta');
      const showEl = card.querySelector('.kutpod-related-showname');

      // Marcar activo en la playlist
      card.querySelectorAll('.kutpod-playlist-item').forEach(it => it.classList.remove('is-active'));
      item.classList.add('is-active');

      const embedSrc = item.getAttribute('data-embed-src');
      const title = item.getAttribute('data-title');
      const url = item.getAttribute('data-url');
      const cover = item.getAttribute('data-cover');
      const meta = item.getAttribute('data-meta');
      const listenText = item.getAttribute('data-listen-text') || 'Escuchar en KutPod ↗';
      const showname = item.getAttribute('data-showname');

      if (iframe && embedSrc) {
        const sep = embedSrc.includes('?') ? '&' : '?';
        iframe.src = embedSrc + sep + 'autoplay=1';
      }
      if (titleEl && title) {
        titleEl.textContent = title;
        if (url) titleEl.href = url;
      }
      if (coverEl && cover) {
        coverEl.src = cover;
        coverEl.alt = title || '';
      }
      if (showEl && showname) {
        showEl.textContent = showname;
      }
      if (metaContainer && url) {
        metaContainer.innerHTML = (meta ? meta + ' · ' : '') + '<a href="' + url + '" target="_blank" rel="noopener noreferrer" style="color:var(--kp-accent, #ff5f7e); font-weight:600; text-decoration:none;">' + listenText + '</a>';
      }

      card.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    });
  }

  function highlightActiveChapter(currentSec) {
    const chapters = document.querySelectorAll('.kutpod-chapter-item[data-time]');
    let activeItem = null;
    chapters.forEach(item => {
      const t = parseFloat(item.getAttribute('data-time'));
      if (t <= currentSec) {
        activeItem = item;
      }
      item.classList.remove('kp-chapter-playing');
    });
    if (activeItem) {
      activeItem.classList.add('kp-chapter-playing');
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initKutPodPlayer);
  } else {
    initKutPodPlayer();
  }
})();
