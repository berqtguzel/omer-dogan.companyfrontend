export const normalizeLocale = (locale = "de") =>
    String(locale || "de")
        .split("-")[0]
        .toLowerCase();

export const pickText = (...values) => {
    for (const value of values) {
        if (typeof value === "string" && value.trim() !== "") {
            return value.trim();
        }
    }

    return "";
};

export const findTranslation = (translations, locale) => {
    if (!Array.isArray(translations)) return {};

    return (
        translations.find(
            (item) =>
                normalizeLocale(item?.language_code || "") ===
                normalizeLocale(locale),
        ) ||
        translations[0] ||
        {}
    );
};

export const buildPageSeo = (page, locale = "de", fallback = {}) => {
    const normalized = normalizeLocale(locale);
    const tr = findTranslation(page?.translations, normalized);
    const seo = page?.seo || {};
    const fallbackTitle = fallback.title || page?.name || "Website";
    const title = pickText(tr.name, page?.name, fallbackTitle);
    const content = pickText(tr.content, page?.content);
    const descriptionFallback = content.replace(/<[^>]+>/g, "").slice(0, 160);

    return {
        title: pickText(tr.meta_title, seo.meta_title, page?.meta_title, title),
        description:
            pickText(
                tr.meta_description,
                seo.meta_description,
                page?.meta_description,
                fallback.description,
            ) || descriptionFallback,
        keywords: pickText(
            tr.meta_keywords,
            seo.meta_keywords,
            page?.meta_keywords,
        ),
        image: pickText(tr.image, page?.image, seo.image, fallback.image),
        ogTitle: pickText(
            tr.og_title,
            seo.og_title,
            page?.og_title,
            tr.meta_title,
            title,
        ),
        ogDescription: pickText(
            tr.og_description,
            seo.og_description,
            page?.og_description,
            tr.meta_description,
            fallback.description,
        ),
        canonical: pickText(
            tr.canonical_url,
            seo.canonical_url,
            page?.canonical_url,
            fallback.canonical,
        ),
        locale: normalized,
    };
};
