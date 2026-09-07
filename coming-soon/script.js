/* Trilingual copy for the teaser. Keys map to [data-i18n] attributes in
   index.html; `dir`/`htmlLang` drive the RTL rules in style.css. */
const translations = {
  fr: {
    dir: 'ltr',
    htmlLang: 'fr',
    docTitle: 'Hirfaty — Bientôt en ligne',
    eyebrow: 'Souk marocain',
    title_a: "L'artisanat marocain",
    title_b: 'arrive bientôt.',
    lede: "Nous préparons un marché où chaque pièce porte la trace de la main qui l'a façonnée — et le nom de l'atelier d'où elle vient.",
    status: 'En cours de préparation',
    caption: 'Atelier de poterie · Fès',
  },
  ar: {
    dir: 'rtl',
    htmlLang: 'ar',
    docTitle: 'حرفتي — قريباً',
    eyebrow: 'سوق مغربي',
    title_a: 'الحرف المغربية الأصيلة',
    title_b: 'قريباً بين أيديكم.',
    lede: 'نُحضّر لكم سوقاً تحمل فيه كل قطعة أثر اليد التي صنعتها، واسم الورشة التي خرجت منها.',
    status: 'قيد التحضير',
    caption: 'ورشة الفخار · فاس',
  },
  en: {
    dir: 'ltr',
    htmlLang: 'en',
    docTitle: 'Hirfaty — Coming soon',
    eyebrow: 'Moroccan souk',
    title_a: 'Moroccan craftsmanship',
    title_b: 'is coming soon.',
    lede: "We're building a marketplace where every piece carries the mark of the hand that made it — and the name of the workshop it came from.",
    status: 'Currently in the making',
    caption: 'Pottery workshop · Fès',
  },
};

const STORAGE_KEY = 'hirfaty_coming_soon_lang';

function applyLang(lang) {
  const t = translations[lang];

  if (!t) {
return;
}

  document.documentElement.lang = t.htmlLang;
  document.body.setAttribute('dir', t.dir);
  document.title = t.docTitle;

  document.querySelectorAll('[data-i18n]').forEach((el) => {
    const value = t[el.getAttribute('data-i18n')];

    if (value) {
el.textContent = value;
}
  });

  document.querySelectorAll('.lang button').forEach((btn) => {
    const isActive = btn.dataset.lang === lang;
    btn.classList.toggle('active', isActive);
    btn.setAttribute('aria-pressed', String(isActive));
  });

  try {
    localStorage.setItem(STORAGE_KEY, lang);
  } catch {
    /* private mode — the choice just won't persist */
  }
}

document.querySelectorAll('.lang button').forEach((btn) => {
  btn.addEventListener('click', () => applyLang(btn.dataset.lang));
});

let stored = null;

try {
  stored = localStorage.getItem(STORAGE_KEY);
} catch {
  /* ignore */
}

const browserLang = (navigator.language || 'fr').slice(0, 2);
applyLang(stored || (translations[browserLang] ? browserLang : 'fr'));
