const form = document.querySelector('#festival-form');
const excitement = document.querySelector('#excitement');
const excitementValue = document.querySelector('#excitement-value');
const formStatus = document.querySelector('#form-status');

function updateSatisfaction() {
  excitementValue.value = String(excitement.value) + ' из 10';
}

function revealOnScroll(entries, observer) {
  for (const entry of entries) {
    if (entry.isIntersecting) {
      entry.target.classList.add('is-visible');
      observer.unobserve(entry.target);
    }
  }
}

function handleSubmit(event) {
  event.preventDefault();
  formStatus.hidden = false;
  formStatus.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

function handleReset() {
  formStatus.hidden = true;
  requestAnimationFrame(updateSatisfaction);
}

excitement.addEventListener('input', updateSatisfaction);
form.addEventListener('submit', handleSubmit);
form.addEventListener('reset', handleReset);
updateSatisfaction();

if ('IntersectionObserver' in window && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
  const observer = new IntersectionObserver(revealOnScroll, { threshold: 0.08 });

  for (const element of document.querySelectorAll('.intro, .section-heading, fieldset')) {
    element.classList.add('reveal');
    observer.observe(element);
  }
}
