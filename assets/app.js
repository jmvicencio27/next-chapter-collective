'use strict';
const toggle = document.querySelector('.menu-toggle');
const navigation = document.getElementById('navigation');
if (toggle && navigation) {
  const closeMenu = () => { toggle.setAttribute('aria-expanded', 'false'); navigation.classList.remove('open'); };
  toggle.addEventListener('click', () => {
    const expanded = toggle.getAttribute('aria-expanded') === 'true';
    toggle.setAttribute('aria-expanded', String(!expanded));
    navigation.classList.toggle('open', !expanded);
  });
  navigation.addEventListener('click', event => { if (event.target.closest('a')) closeMenu(); });
  document.addEventListener('keydown', event => { if (event.key === 'Escape' && toggle.getAttribute('aria-expanded') === 'true') { closeMenu(); toggle.focus(); } });
}
document.querySelectorAll('[data-interest], [data-program]').forEach(link => {
  link.addEventListener('click', () => {
    const interest = document.getElementById('interest');
    const program = document.getElementById('program');
    if (interest && link.dataset.interest) interest.value = link.dataset.interest;
    if (program && link.dataset.program) program.value = link.dataset.program;
  });
});
const year = document.getElementById('year');
if (year) year.textContent = String(new Date().getFullYear());
// Each service page can carry a program and mentor into the Contact page.
const query = new URLSearchParams(window.location.search);
['interest', 'program', 'mentor'].forEach(name => {
  const select = document.getElementById(name);
  const value = query.get(name);
  if (select && value && Array.from(select.options).some(option => option.value === value)) select.value = value;
});
const navGroups = document.querySelectorAll('.nav-group');
navGroups.forEach(group => group.addEventListener('toggle', () => {
  if (group.open) navGroups.forEach(other => { if (other !== group) other.open = false; });
}));
document.addEventListener('click', event => {
  if (!event.target.closest('.nav-group')) navGroups.forEach(group => { group.open = false; });
});
document.addEventListener('keydown', event => {
  if (event.key === 'Escape') navGroups.forEach(group => {
    if (group.open) { group.open = false; group.querySelector('summary').focus(); }
  });
});
const heroSlides = [
  {heading:'Develop people.<br><em>Strengthen business.</em>', description:'Build the capability to move forward. We bring business, engineering, technology and leadership perspectives into practical training and mentoring for Filipino professionals and organizations.', image:'mentoring-session', label:'Discover our services', href:'training.html', alt:'Illustrative Filipino professionals discussing business priorities together'},
  {heading:'Make technology<br><em>work for your business.</em>', description:'Start with the business challenge. Explore digital workflows, useful tools and practical priorities with a fresh perspective on your next move.', image:'technology-guidance', label:'Explore technology advisory', href:'technology-advisory.html', alt:'Illustrative business owner and technology professional discussing a laptop'},
  {heading:'Your ambition.<br><em>A clearer direction.</em>', description:'Navigate a business decision, a career transition or greater leadership responsibility through focused mentoring with a founding member of the collective.', image:'corporate-workshop', label:'Explore mentoring', href:'mentoring.html', alt:'Illustrative Filipino professionals sharing perspectives in a workshop'}
];
const hero = document.querySelector('[data-home-hero]');
const savedFeatures = Array.from(document.querySelectorAll('[data-hero-library] [data-feature]'));
if (savedFeatures.length === 3) savedFeatures.forEach((node, index) => {
  heroSlides[index] = {heading:node.querySelector('h2').innerHTML, description:node.querySelector('p').innerHTML,
  image:node.querySelector('img').getAttribute('src'), alt:node.querySelector('img').alt,
  label:node.querySelector('a').innerHTML, href:node.querySelector('a').getAttribute('href'), saved:true};
});
const heroButtons = Array.from(document.querySelectorAll('[data-hero-slide]'));
const playback = document.querySelector('.hero-playback');
const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
let currentHeroSlide = 0;
let heroTimer;
let heroPaused = reducedMotion.matches;
// Keep rotating under the pointer and after mouse/touch slide selection.
// Pause for keyboard focus so a focused call-to-action never changes destination.
const canRotateHero = () => hero && !heroPaused && !document.hidden && !(hero.contains(document.activeElement) && document.activeElement.matches(':focus-visible'));
function scheduleHero() {
  clearTimeout(heroTimer);
  if (!canRotateHero()) return;
  heroTimer = setTimeout(() => {
    showHeroSlide((currentHeroSlide + 1) % heroSlides.length);
    scheduleHero();
  }, 6000);
}
function showHeroSlide(index) {
  currentHeroSlide = index;
  const slide = heroSlides[index];
  document.getElementById('hero-heading').innerHTML = slide.heading;
  document.getElementById('hero-description')[slide.saved ? 'innerHTML' : 'textContent'] = slide.description;
  const image = document.getElementById('hero-image');
  image.src = slide.saved ? slide.image : `assets/images/${slide.image}.webp`;
  image.alt = slide.alt;
  const link = document.getElementById('hero-primary-link');
  link.href = slide.href;
  link.innerHTML = `${slide.label} <span aria-hidden="true">↗</span>`;
  heroButtons.forEach(item => item.setAttribute('aria-pressed', String(Number(item.dataset.heroSlide) === index)));
}
function updateHeroPlayback() {
  if (playback) {
    playback.textContent = heroPaused ? 'Play slides' : 'Pause slides';
    playback.setAttribute('aria-label', heroPaused ? 'Resume automatic slides' : 'Pause automatic slides');
  }
  const heading = document.getElementById('hero-heading');
  if (heading) heading.setAttribute('aria-live', heroPaused ? 'polite' : 'off');
  scheduleHero();
}
if (hero) {
  if (savedFeatures.length === 3) showHeroSlide(0);
  heroButtons.forEach(button => button.addEventListener('click', () => {
    showHeroSlide(Number(button.dataset.heroSlide));
    scheduleHero();
  }));
  if (playback) playback.addEventListener('click', () => {
    heroPaused = !heroPaused;
    updateHeroPlayback();
  });
  hero.addEventListener('focusin', scheduleHero);
  hero.addEventListener('focusout', () => setTimeout(scheduleHero, 0));
  document.addEventListener('visibilitychange', scheduleHero);
  reducedMotion.addEventListener('change', event => {
    heroPaused = event.matches;
    updateHeroPlayback();
  });
  heroSlides.slice(1).forEach(slide => { const image = new Image(); image.src = slide.saved ? slide.image : `assets/images/${slide.image}.webp`; });
  updateHeroPlayback();
}
const serviceFilters = document.querySelectorAll('[data-filter]');
const serviceCards = document.querySelectorAll('[data-categories]');
const serviceCount = document.getElementById('service-count');
serviceFilters.forEach(button => button.addEventListener('click', () => {
  serviceFilters.forEach(filter => {
    const active = filter === button;
    filter.classList.toggle('active', active);
    filter.setAttribute('aria-pressed', String(active));
  });
  let visible = 0;
  serviceCards.forEach(card => {
    const matches = button.dataset.filter === 'all' || card.dataset.categories.split(' ').includes(button.dataset.filter);
    card.hidden = !matches;
    if (matches) visible++;
  });
  if (serviceCount) serviceCount.textContent = button.dataset.filter === 'all' ? 'Explore all 6 service areas' : `${visible} service areas for ${button.textContent.toLowerCase()}`;
}));
const form = document.getElementById('enquiry-form');
if (form) form.addEventListener('submit', async event => {
  event.preventDefault();
  if (!form.reportValidity()) return;
  const button = form.querySelector('button[type="submit"]');
  const status = document.getElementById('form-status');
  button.disabled = true;
  const originalButton = button.innerHTML;
  button.textContent = 'Sending your enquiry…';
  status.className = 'form-status';
  status.textContent = '';
  const controller = new AbortController();
  const timeout = setTimeout(() => controller.abort(), 20000);
  try {
    const response = await fetch(form.action, { method: 'POST', body: new FormData(form), headers: { Accept: 'application/json' }, signal: controller.signal });
    const result = await response.json();
    if (!response.ok || !result.success) throw new Error(result.message || 'Your enquiry could not be sent. Please try again later.');
    status.className = 'form-status success';
    status.textContent = result.message;
    form.reset();
  } catch (error) {
    status.className = 'form-status error';
    status.textContent = error.name === 'AbortError' ? 'The connection timed out. Please try again shortly.' : (error instanceof SyntaxError || error instanceof TypeError ? 'Online enquiries are temporarily unavailable. Please try again later.' : error.message);
  } finally {
    clearTimeout(timeout);
    button.disabled = false;
    button.innerHTML = originalButton;
  }
});
