import i18n from "i18next";
import { initReactI18next } from "react-i18next";

import de from "./locales/de.json";
import en from "./locales/en.json";
import tr from "./locales/tr.json";
import fr from "./locales/fr.json";
import es from "./locales/es.json";
import it from "./locales/it.json";
import pt from "./locales/pt.json";
import ro from "./locales/ro.json";
import ru from "./locales/ru.json";
import pl from "./locales/pl.json";
import cs from "./locales/cs.json";
import sk from "./locales/sk.json";
import bg from "./locales/bg.json";
import hr from "./locales/hr.json";


const allSupportedLngs = ["de", "en", "tr", "fr", "es", "it", "pt", "ro", "ru", "pl", "cs", "sk", "bg", "hr"];


/**
 * URL /de/... veya sunucunun data-locale değeri kaynak olmalı;
 * localStorage (i18nextLng) önce okunursa /de sayfasında İngilizce kalır.
 */
const localeFromPathname = () => {
  if (typeof window === "undefined") return null;
  const m = window.location.pathname.match(/^\/([a-z]{2})(?:\/|$)/);
  if (!m) return null;
  const code = m[1].toLowerCase();
  return allSupportedLngs.includes(code) ? code : null;
};

const detectInitialLng = () => {
  if (typeof window === "undefined") {
    return "de";
  }

  const fromPath = localeFromPathname();
  if (fromPath) {
    return fromPath;
  }

  const fromAttr = document.documentElement.getAttribute("data-locale");
  if (fromAttr) {
    const attrCode = fromAttr.toLowerCase().slice(0, 2);
    if (allSupportedLngs.includes(attrCode)) {
      return attrCode;
    }
  }

  const fromStorage =
    localStorage.getItem("locale") || localStorage.getItem("i18nextLng");
  if (fromStorage) {
    const langCode = fromStorage.toLowerCase().slice(0, 2);
    if (allSupportedLngs.includes(langCode)) {
      return langCode;
    }
  }

  return "de";
};

i18n
  .use(initReactI18next)
  .init({
    resources: {
      de: { translation: de },
      en: { translation: en },
      tr: { translation: tr },
      fr: { translation: fr },
      es: { translation: es },
      it: { translation: it },
      pt: { translation: pt },
      ro: { translation: ro },
      ru: { translation: ru },
      pl: { translation: pl },
      cs: { translation: cs },
      sk: { translation: sk },
      bg: { translation: bg },
      hr: { translation: hr },
    },


    lng: detectInitialLng(),

    fallbackLng: "de",
    supportedLngs: allSupportedLngs,

    interpolation: {
      escapeValue: false,
    },

    react: {
      useSuspense: false,
    },
  });

export default i18n;
