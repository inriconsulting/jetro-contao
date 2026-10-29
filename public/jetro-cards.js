// Pfeile für die Termin-Karten; Wischen und Tastatur übernimmt der Browser
const jetroCards = () => document.querySelectorAll('.jetro-cards').forEach((slider) => {
  const track = slider.querySelector('.jetro-cards__track');
  const prev = slider.querySelector('.jetro-cards__nav--prev');
  const next = slider.querySelector('.jetro-cards__nav--next');
  if (!track || !prev || !next) return;

  const step = () => (track.querySelector('.jetro-card')?.offsetWidth || 220) + 16;
  const smooth = window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth';
  const update = () => {
    prev.hidden = track.scrollLeft < 5;
    next.hidden = track.scrollLeft + track.clientWidth >= track.scrollWidth - 5;
  };

  prev.addEventListener('click', () => track.scrollBy({ left: -step(), behavior: smooth }));
  next.addEventListener('click', () => track.scrollBy({ left: step(), behavior: smooth }));
  track.addEventListener('scroll', update, { passive: true });
  window.addEventListener('resize', update);
  update();
});

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', jetroCards);
} else {
  jetroCards();
}
