const excitement = document.querySelector('#excitement');
const excitementValue = document.querySelector('#excitement-value');

function updateSatisfaction() {
  if (excitement && excitementValue) {
    excitementValue.value = String(excitement.value) + ' из 10';
  }
}

function revealOnScroll(entries, observer) {
  for (const entry of entries) {
    if (entry.isIntersecting) {
      entry.target.classList.add('is-visible');
      observer.unobserve(entry.target);
    }
  }
}

if (excitement) {
  excitement.addEventListener('input', updateSatisfaction);
  excitement.form.addEventListener('reset', () => requestAnimationFrame(updateSatisfaction));
  updateSatisfaction();
}

if ('IntersectionObserver' in window && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
  const observer = new IntersectionObserver(revealOnScroll, { threshold: 0.08 });
  for (const element of document.querySelectorAll('.intro, .section-heading, fieldset, .product-card')) {
    element.classList.add('reveal');
    observer.observe(element);
  }
}
