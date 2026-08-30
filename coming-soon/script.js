const translations = {
  fr: {
    dir: 'ltr',
    htmlLang: 'fr',
    title: 'Hirfaty — Bientôt en ligne',
    eyebrow: 'Souk marocain',
    heading: "L'artisanat marocain arrive bientôt.",
    subtitle:
      "Nous préparons un marché où chaque pièce raconte l'histoire de la main qui l'a façonnée.",
    contact_label: 'Une question ? Écrivez-nous',
  },
  ar: {
    dir: 'rtl',
    htmlLang: 'ar',
    title: 'حرفتي — قريباً',
    eyebrow: 'سوق مغربي',
    heading: 'الحرف المغربية الأصيلة قريباً.',
    subtitle: 'نُحضّر لكم سوقاً تروي كل قطعة فيه حكاية اليد التي صنعتها.',
    contact_label: 'لديك سؤال؟ راسلنا',
  },
  en: {
    dir: 'ltr',
    htmlLang: 'en',
    title: 'Hirfaty — Coming soon',
    eyebrow: 'Moroccan souk',
    heading: 'Moroccan craftsmanship is coming soon.',
    subtitle:
      "We're building a marketplace where every piece carries the story of the hands that made it.",
    contact_label: 'Got a question? Reach out',
  },
};

const STORAGE_KEY = 'hirfaty_coming_soon_lang';

function applyLang(lang) {
  const t = translations[lang];
  if (!t) return;

  document.documentElement.lang = t.htmlLang;
  document.body.setAttribute('dir', t.dir);
  document.title = t.title;

  document.querySelectorAll('[data-i18n]').forEach((el) => {
    const key = el.getAttribute('data-i18n');
    if (t[key]) el.textContent = t[key];
  });

  document.querySelectorAll('.lang-switch button').forEach((btn) => {
    btn.classList.toggle('active', btn.dataset.lang === lang);
  });

  localStorage.setItem(STORAGE_KEY, lang);
}

document.querySelectorAll('.lang-switch button').forEach((btn) => {
  btn.addEventListener('click', () => applyLang(btn.dataset.lang));
});

const browserLang = (navigator.language || 'fr').slice(0, 2);
const initialLang =
  localStorage.getItem(STORAGE_KEY) ||
  (translations[browserLang] ? browserLang : 'fr');

applyLang(initialLang);
