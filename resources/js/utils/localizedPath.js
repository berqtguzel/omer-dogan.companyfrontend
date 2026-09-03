const normalizeLang = (code) =>
    String(code || "")
        .toLowerCase()
        .split("-")[0];

const SUPPORTED_LOCALES = new Set([
    "de", "en", "tr", "ru", "fr", "es", "it", "pt", "ro", "pl",
    "cs", "cz", "sk", "bg", "hr",
]);

export function buildLocalizedPath(pathname, language) {
    const requested = normalizeLang(language);
    const code = requested === "cz" ? "cs" : requested;
    const segments = String(pathname || "/").split("/").filter(Boolean);

    while (segments.length && SUPPORTED_LOCALES.has(normalizeLang(segments[0]))) {
        segments.shift();
    }

    return `/${[code, ...segments].filter(Boolean).join("/")}${segments.length ? "" : "/"}`;
}
