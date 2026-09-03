const normalizeLocale = (locale = "de") => String(locale || "de").split("-")[0].toLowerCase();
const pickText = (...values) => {
  for (const value of values) {
    if (typeof value === "string" && value.trim() !== "") {
      return value.trim();
    }
  }
  return "";
};
const findTranslation = (translations, locale) => {
  if (!Array.isArray(translations)) return {};
  return translations.find(
    (item) => normalizeLocale((item == null ? void 0 : item.language_code) || "") === normalizeLocale(locale)
  ) || translations[0] || {};
};
const buildPageSeo = (page, locale = "de", fallback = {}) => {
  const normalized = normalizeLocale(locale);
  const tr = findTranslation(page == null ? void 0 : page.translations, normalized);
  const seo = (page == null ? void 0 : page.seo) || {};
  const fallbackTitle = fallback.title || (page == null ? void 0 : page.name) || "Website";
  const title = pickText(tr.name, page == null ? void 0 : page.name, fallbackTitle);
  const content = pickText(tr.content, page == null ? void 0 : page.content);
  const descriptionFallback = content.replace(/<[^>]+>/g, "").slice(0, 160);
  return {
    title: pickText(tr.meta_title, seo.meta_title, page == null ? void 0 : page.meta_title, title),
    description: pickText(
      tr.meta_description,
      seo.meta_description,
      page == null ? void 0 : page.meta_description,
      fallback.description
    ) || descriptionFallback,
    keywords: pickText(
      tr.meta_keywords,
      seo.meta_keywords,
      page == null ? void 0 : page.meta_keywords
    ),
    image: pickText(tr.image, page == null ? void 0 : page.image, seo.image, fallback.image),
    ogTitle: pickText(
      tr.og_title,
      seo.og_title,
      page == null ? void 0 : page.og_title,
      tr.meta_title,
      title
    ),
    ogDescription: pickText(
      tr.og_description,
      seo.og_description,
      page == null ? void 0 : page.og_description,
      tr.meta_description,
      fallback.description
    ),
    canonical: pickText(
      tr.canonical_url,
      seo.canonical_url,
      page == null ? void 0 : page.canonical_url,
      fallback.canonical
    ),
    locale: normalized
  };
};
export {
  buildPageSeo as b,
  findTranslation as f,
  normalizeLocale as n,
  pickText as p
};
