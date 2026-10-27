/**
 * KutPod Plugin - Interactive Reader JS for BrisaCMS
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
