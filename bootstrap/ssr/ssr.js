import { jsx } from "react/jsx-runtime";
import { createContext, useMemo } from "react";
import ReactDOMServer from "react-dom/server";
import { createInertiaApp } from "@inertiajs/react";
import createServer from "@inertiajs/react/server";
import { initReactI18next, I18nextProvider } from "react-i18next";
import i18n from "i18next";
async function resolvePageComponent(path, pages) {
  for (const p of Array.isArray(path) ? path : [path]) {
    const page = pages[p];
    if (typeof page === "undefined") {
      continue;
    }
    return typeof page === "function" ? page() : page;
  }
  throw new Error(`Page not found: ${path}`);
}
const ThemeContext = createContext({
  theme: "light",
  setTheme: () => {
  }
});
const ThemeProvider = ({ children, initial = "light" }) => {
  const value = useMemo(
    () => ({ theme: initial, setTheme: () => {
    } }),
    [initial]
  );
  return /* @__PURE__ */ jsx(ThemeContext.Provider, { value, children });
};
const hero$d = {
  title: "Ihr zuverlässiger Partner für Beherbergung und Gebäudemanagement",
  subtitle: "Mit über 25 Jahren Erfahrung bieten wir maßgeschneiderte integrierte Lösungen für Reinigung, Wartung und Facility Management",
  button_services: "Unsere Services entdecken",
  button_contact: "Jetzt Kontakt aufnehmen"
};
const services$d = {
  section_title: "Unser breites Leistungsspektrum",
  section_subtitle: "Wir bieten schlüsselfertige Lösungen für alle Bedürfnisse Ihrer Anlagen und Gebäude – mit deutscher Gründlichkeit und Qualität.",
  learn_more: "Details erfahren",
  categories: {
    hotel: {
      title: "Hotelreinigung & Housekeeping",
      description: "Von der Zimmerreinigung bis zur Spülküche – makellose Hygiene und effiziente Prozesse in jedem Bereich Ihres Hotels."
    },
    building: {
      title: "Professionelle Gebäudereinigung",
      description: "Büros, Gewerbeflächen, Bauendreinigung und Spezialreinigungen – wir bringen Ihre Immobilien zum Glänzen."
    },
    renovation: {
      title: "Sanierung, Reparatur & Wartung",
      description: "Maler-, Putz- und Trockenbauarbeiten, Bodenverlegung und Kleinreparaturen."
    }
  },
  card: {
    aria: "Mehr Informationen über {{service}} erhalten",
    button: "Details"
  }
};
const servicesList$d = {
  title: "Dienstleistungen",
  subtitle: "Entdecken Sie unsere umfassenden Dienstleistungen für Ihr Zuhause und Ihr Unternehmen",
  meta_title: "Unsere Dienstleistungen - O&I CLEAN group GmbH",
  meta_description: "Professionelle Reinigung, Sanierung und Gebäudemanagement.",
  loading: "Wird geladen…",
  contact_cta: "Kontaktieren Sie uns",
  contact_cta_aria: "Jetzt Kontakt aufnehmen",
  no_services: "Keine Dienstleistungen verfügbar."
};
const contact$d = {
  page_title: "Kontakt",
  intro_static_text: "Kostenlos und unverbindlich –",
  intro_dynamic_text: "wir melden uns schnellstmöglich bei Ihnen",
  title: "Kontaktieren Sie uns",
  description: "Professionelle Reinigungsdienstleistungen für Ihr Unternehmen. Wir beraten Sie gerne individuell.",
  submit_label: "Nachricht senden",
  submit_failed: "Nachricht konnte nicht gesendet werden.",
  select_placeholder: "Bitte auswählen",
  success_title: "Erfolgreich!",
  success_message: "Ihre Nachricht wurde erfolgreich gesendet. Wir werden uns in Kürze bei Ihnen melden.",
  redirecting: "Seite wird aktualisiert...",
  phone_label: "Telefon",
  submitting: "Wird gesendet…",
  success_sub: "Wir melden uns in Kürze bei Ihnen.",
  email_label: "E-Mail",
  hours_label: "Öffnungszeiten",
  hours_value: "Mo. - Fr.: 08:00 - 17:00 Uhr",
  phone_number: "",
  email_address: "",
  form: {
    name: "Name",
    phone: "Telefon",
    email: "E-Mail",
    message: "Nachricht",
    other: "Branche"
  },
  required: {
    name: "Name ist erforderlich.",
    phone: "Telefon ist erforderlich.",
    email: "E-Mail ist erforderlich.",
    message: "Nachricht ist erforderlich."
  },
  error_generic: "Es ist ein Fehler aufgetreten. Bitte versuchen Sie es erneut."
};
const footer$d = {
  description: "Wir stehen Ihnen mit deutscher Gründlichkeit und Zuverlässigkeit in der professionellen Reinigung, Wartung und im Gebäudemanagement zur Seite.",
  address: "",
  phone: "",
  phone_raw: "",
  email: "",
  links_title: "Links",
  contact_title: "Kontakt",
  link_about: "Über uns",
  link_contact: "Kontakt",
  link_contact_href: "/kontakt",
  link_faq: "FAQ",
  link_privacy: "Datenschutz",
  link_imprint: "Impressum",
  link_privacy_bottom: "Datenschutz",
  copyright: "Alle Rechte vorbehalten.",
  back_to_top: "Zum Seitenanfang",
  region_label: "Footer und Kontaktinformationen",
  home_aria: "O&I CLEAN - Startseite",
  linkedin_aria: "LinkedIn - O&I CLEAN",
  instagram_aria: "Instagram - O&I CLEAN",
  facebook_aria: "Facebook - O&I CLEAN",
  quicklinks_aria: "Quicklinks",
  phone_aria: "Telefonnummer",
  email_aria: "E-Mail",
  services: "Leistungen"
};
const header$d = {
  topbar_tagline: "Reinigung, der Sie vertrauen können — 24/7 Service",
  cta_label: "Termin vereinbaren",
  cta_href: "/kontakt",
  hide_topbar: "Topbar ausblenden",
  home_aria: "Startseite",
  main_nav: "Hauptnavigation",
  menu_open: "Menü öffnen",
  menu_close: "Menü schließen",
  language: "Sprache",
  impressum: "Impressum",
  contact_button: "Kontakt"
};
const nav$d = {
  home: "Startseite"
};
const staticPage$d = {
  breadcrumbs_home: "Startseite",
  breadcrumbs_page: "Seite",
  empty_content: "Inhalt wird in Kürze hinzugefügt."
};
const offerDock$d = {
  title: "Angebot",
  subtitle: "Kostenlos & unverbindlich",
  button: "Anfordern",
  aria_open: "Angebotsleiste öffnen",
  aria_close: "Angebotsleiste schließen"
};
const quote_modal$d = {
  title: "Angebot anfordern",
  scrim_aria: "Modal schließen",
  close_aria: "Schließen",
  field_name: "Name",
  field_email: "E-Mail",
  field_phone: "Telefon",
  field_service: "Dienstleistung",
  field_message: "Nachricht",
  service_placeholder: "Bitte auswählen…",
  service_hotel_cleaning: "Hotelreinigung",
  service_building_cleaning: "Gebäudereinigung",
  service_window_cleaning: "Fensterreinigung",
  service_maintenance_cleaning: "Unterhaltsreinigung",
  service_basic_cleaning: "Grundreinigung",
  service_carpet_cleaning: "Teppichreinigung",
  service_other: "Sonstiges",
  service_general: "Allgemein",
  message_placeholder: "Was können wir für Sie tun?",
  consent_prefix: "Ich stimme der Verarbeitung meiner Daten gemäß der ",
  consent_link: "Datenschutzerklärung",
  consent_suffix: " zu.",
  cancel: "Abbrechen",
  submit: "Anfordern",
  sending: "Wird gesendet…",
  error_consent: "Bitte stimmen Sie der Datenverarbeitung zu.",
  error_generic: "Etwas ist schiefgelaufen. Bitte versuchen Sie es erneut.",
  subject_prefix: "Angebotsanfrage",
  thank_you_title: "Vielen Dank!",
  thank_you_text: "Ihre Anfrage ist bei uns eingegangen, wir werden uns in Kürze bei Ihnen melden.",
  close_button: "Schließen"
};
const errors$5 = {
  notFound: {
    "404": "Seite wurde nicht gefunden oder existiert nicht mehr.",
    "500": "Ein Serverfehler ist aufgetreten — bitte versuchen Sie es erneut.",
    home: "Zur Startseite",
    contact: "Kontakt",
    reload: "Neu laden"
  }
};
const ui$3 = {
  loading: {
    message: "Inhalt wird geladen…"
  }
};
const cookies$d = {
  title: "Cookie-Einstellungen",
  message: "Wir verwenden Cookies, um Ihnen das beste Erlebnis auf unserer Website zu ermöglichen.",
  cat_necessary: "Notwendige Cookies",
  cat_analytics: "Analyse-Cookies",
  cat_marketing: "Marketing-Cookies",
  desc_necessary: "Diese Cookies sind für den Betrieb der Website erforderlich.",
  desc_analytics: "Diese Cookies sammeln Informationen darüber, wie Sie unsere Website nutzen.",
  desc_marketing: "Diese Cookies werden verwendet, um Ihnen relevante Werbung anzuzeigen.",
  required: "Erforderlich",
  accept_all: "Alle akzeptieren",
  reject: "Ablehnen",
  save: "Auswahl speichern",
  settings: "Einstellungen",
  hide_details: "Details ausblenden",
  privacy_policy: "Datenschutzerklärung",
  imprint: "Impressum"
};
const common$3 = {
  ok: "OK",
  demo: "Demo"
};
const required$d = {
  name: "Name ist erforderlich.",
  phone: "Telefon ist erforderlich.",
  email: "E-Mail ist erforderlich.",
  message: "Nachricht ist erforderlich."
};
const submit_error$8 = "Fehler! Bitte erneut versuchen.";
const reviews$2 = {
  title: "Das sagen unsere Kunden",
  subtitle: "Echte Rückmeldungen aus Hotels, Büros und Objekten, die wir täglich betreuen.",
  eyebrow: "Kundenstimmen",
  rating_label: "Bewertung",
  count: "{{count}} Bewertungen",
  anonymous: "Kunde"
};
const faq$2 = {
  title: "Häufige Fragen",
  subtitle: "Die Antworten, nach denen am häufigsten gefragt wird. Ihre Frage ist nicht dabei? Melden Sie sich einfach.",
  eyebrow: "FAQ"
};
const de = {
  hero: hero$d,
  services: services$d,
  servicesList: servicesList$d,
  contact: contact$d,
  footer: footer$d,
  header: header$d,
  nav: nav$d,
  staticPage: staticPage$d,
  offerDock: offerDock$d,
  quote_modal: quote_modal$d,
  errors: errors$5,
  ui: ui$3,
  cookies: cookies$d,
  common: common$3,
  required: required$d,
  submit_error: submit_error$8,
  reviews: reviews$2,
  faq: faq$2
};
const hero$c = {
  title: "Your reliable partner in accommodation and facility management",
  subtitle: "With over 25 years of experience, we offer custom-integrated solutions for cleaning, maintenance, and facility management",
  button_services: "Discover our services",
  button_contact: "Contact us now"
};
const services$c = {
  section_title: "Our wide range of services",
  section_subtitle: "We provide turnkey solutions for all the needs of your facilities and buildings – with German precision and quality.",
  learn_more: "Detailed information",
  categories: {
    hotel: {
      title: "Hotel cleaning & housekeeping",
      description: "From room cleaning to the dish room – impeccable hygiene and efficient processes in every area of your hotel."
    },
    building: {
      title: "Professional building cleaning",
      description: "Offices, commercial areas, post-construction cleaning, and special cleanings – we make your properties shine."
    },
    renovation: {
      title: "Renovation, repair & maintenance",
      description: "Painting, plaster and drywall work, flooring, and small repairs."
    }
  },
  card: {
    aria: "Get more information about {{service}}",
    button: "Details"
  }
};
const servicesList$c = {
  title: "Services",
  subtitle: "Discover our comprehensive services for your home and business",
  meta_title: "Our Services - O&I CLEAN group GmbH",
  meta_description: "Professional cleaning, renovation, and facility management.",
  loading: "Loading…",
  contact_cta: "Contact us",
  contact_cta_aria: "Contact us now"
};
const contact$c = {
  page_title: "Contact",
  intro_static_text: "Free and non-binding –",
  intro_dynamic_text: "we will get back to you as soon as possible",
  title: "Contact us",
  description: "Professional cleaning services for your company. We are happy to inform you individually.",
  submit_label: "Send message",
  submit_failed: "Message could not be sent.",
  select_placeholder: "Please select",
  success_title: "Success!",
  success_message: "Your message has been sent successfully. We will get back to you shortly.",
  redirecting: "Page is reloading...",
  phone_label: "Phone",
  submitting: "Submitting...",
  success_sub: "We will get back to you shortly.",
  email_label: "Email",
  hours_label: "Opening hours",
  hours_value: "Mon. - Fri.: 08:00 - 17:00",
  phone_number: "",
  email_address: "",
  form: {
    name: "Name",
    phone: "Phone",
    email: "Email",
    message: "Message",
    other: "Field"
  },
  required: {
    name: "Name is required.",
    phone: "Phone is required.",
    email: "Email is required.",
    message: "Message is required."
  },
  error_generic: "An error occurred. Please try again."
};
const footer$c = {
  description: "We stand by you with German thoroughness and reliability in professional cleaning, maintenance, and facility management.",
  address: "",
  phone: "",
  phone_raw: "",
  email: "",
  links_title: "Links",
  contact_title: "Contact",
  link_about: "About us",
  link_contact: "Contact",
  link_contact_href: "/contact",
  link_faq: "FAQ",
  link_privacy: "Privacy",
  link_imprint: "Imprint",
  link_privacy_bottom: "Privacy",
  copyright: "All rights reserved.",
  back_to_top: "Back to top",
  region_label: "Footer and contact information",
  home_aria: "O&I CLEAN - Home page",
  linkedin_aria: "LinkedIn - O&I CLEAN",
  instagram_aria: "Instagram - O&I CLEAN",
  facebook_aria: "Facebook - O&I CLEAN",
  quicklinks_aria: "Quick links",
  phone_aria: "Phone number",
  email_aria: "Email",
  services: "Services"
};
const header$c = {
  topbar_tagline: "Cleaning you can trust — 24/7 service",
  cta_label: "Book an appointment",
  cta_href: "/contact",
  hide_topbar: "Hide top bar",
  home_aria: "Home page",
  main_nav: "Main navigation",
  menu_open: "Open menu",
  menu_close: "Close menu",
  language: "Language",
  impressum: "Imprint",
  contact_button: "Contact"
};
const nav$c = {
  home: "Home"
};
const staticPage$c = {
  breadcrumbs_home: "Home",
  breadcrumbs_page: "Page",
  empty_content: "Content will be added soon."
};
const offerDock$c = {
  title: "Offer",
  subtitle: "Free & non-binding",
  button: "Request",
  aria_open: "Open offer panel",
  aria_close: "Close offer panel"
};
const quote_modal$c = {
  title: "Request a quote",
  scrim_aria: "Close modal",
  close_aria: "Close",
  field_name: "Name",
  field_email: "Email",
  field_phone: "Phone",
  field_service: "Service",
  field_message: "Message",
  service_placeholder: "Please select…",
  service_hotel_cleaning: "Hotel cleaning",
  service_building_cleaning: "Building cleaning",
  service_window_cleaning: "Window cleaning",
  service_maintenance_cleaning: "Maintenance cleaning",
  service_basic_cleaning: "General/deep cleaning",
  service_carpet_cleaning: "Carpet cleaning",
  service_other: "Other",
  service_general: "General",
  message_placeholder: "What can we do for you?",
  consent_prefix: "I agree to the processing of my data in accordance with the ",
  consent_link: "privacy policy",
  consent_suffix: ".",
  cancel: "Cancel",
  submit: "Request",
  sending: "Sending…",
  error_consent: "Please provide consent for data processing.",
  error_generic: "Something went wrong. Please try again.",
  subject_prefix: "Quote request",
  thank_you_title: "Thank you!",
  thank_you_text: "Your request has been received, we will contact you shortly.",
  close_button: "Close"
};
const errors$4 = {
  notFound: {
    "404": "The requested page could not be found.",
    "500": "A server error occurred — please try again later.",
    home: "Back to Home",
    contact: "Contact Us",
    reload: "Reload Page"
  }
};
const ui$2 = {
  loading: {
    message: "Content is loading…"
  }
};
const cookies$c = {
  title: "Cookie Preferences",
  message: "We use cookies to ensure you have the best experience on our website.",
  cat_necessary: "Necessary Cookies",
  cat_analytics: "Analytics Cookies",
  cat_marketing: "Marketing Cookies",
  desc_necessary: "These cookies are required for the website to function.",
  desc_analytics: "These cookies collect information about how you use our site.",
  desc_marketing: "These cookies are used to show you relevant advertisements.",
  required: "Required",
  accept_all: "Accept All",
  reject: "Reject",
  save: "Save Selection",
  settings: "Preferences",
  hide_details: "Hide Details",
  privacy_policy: "Privacy Policy",
  imprint: "Imprint"
};
const common$2 = {
  ok: "OK",
  demo: "Demo"
};
const required$c = {
  name: "Name is required.",
  phone: "Phone is required.",
  email: "Email is required.",
  message: "Message is required."
};
const submit_error$7 = "Error! Please try again.";
const reviews$1 = {
  title: "What our clients say",
  subtitle: "Real feedback from the hotels, offices and properties we look after every day.",
  eyebrow: "Testimonials",
  rating_label: "Rating",
  count: "{{count}} reviews",
  anonymous: "Client"
};
const faq$1 = {
  title: "Frequently asked questions",
  subtitle: "The answers we are asked for most often. Question not covered? Just get in touch.",
  eyebrow: "FAQ"
};
const en = {
  hero: hero$c,
  services: services$c,
  servicesList: servicesList$c,
  contact: contact$c,
  footer: footer$c,
  header: header$c,
  nav: nav$c,
  staticPage: staticPage$c,
  offerDock: offerDock$c,
  quote_modal: quote_modal$c,
  errors: errors$4,
  ui: ui$2,
  cookies: cookies$c,
  common: common$2,
  required: required$c,
  submit_error: submit_error$7,
  reviews: reviews$1,
  faq: faq$1
};
const hero$b = {
  title: "Konaklama ve bina yönetiminde güvenilir iş ortağınız",
  subtitle: "25 yılı aşkın deneyimimizle temizlik, bakım ve tesis yönetimi için ihtiyaca özel entegre çözümler sunuyoruz",
  button_services: "Hizmetlerimizi keşfedin",
  button_contact: "Hemen iletişime geçin"
};
const services$b = {
  section_title: "Geniş hizmet yelpazemiz",
  section_subtitle: "Tesisleriniz ve binalarınızın tüm ihtiyaçları için anahtar teslim çözümler sunuyoruz – Alman titizliği ve kalitesiyle.",
  learn_more: "Detaylı bilgi",
  categories: {
    hotel: {
      title: "Otel temizliği & housekeeping",
      description: "Oda temizliğinden bulaşıkhaneye kadar – otelinizin her alanında kusursuz hijyen ve verimli süreçler."
    },
    building: {
      title: "Profesyonel bina temizliği",
      description: "Ofisler, ticari alanlar, inşaat sonu temizlik ve özel temizlikler – mülklerinizi parlatıyoruz."
    },
    renovation: {
      title: "Tadilat, onarım & bakım",
      description: "Boyama, alçı ve alçıpan işleri, zemin döşeme ve küçük onarımlar."
    }
  },
  card: {
    aria: "{{service}} hakkında daha fazla bilgi al",
    button: "Detaylar"
  }
};
const servicesList$b = {
  title: "Hizmetler",
  subtitle: "Eviniz ve işiniz için sunduğumuz kapsamlı hizmetleri keşfedin",
  meta_title: "Hizmetlerimiz - O&I CLEAN group GmbH",
  meta_description: "Profesyonel temizlik, tadilat ve bina yönetimi.",
  loading: "Yükleniyor…",
  contact_cta: "Bizimle iletişime geçin",
  contact_cta_aria: "Hemen iletişime geçin"
};
const contact$b = {
  page_title: "İletişim",
  intro_static_text: "Ücretsiz ve bağlayıcı değildir –",
  intro_dynamic_text: "en kısa sürede size dönüş yapacağız",
  title: "Bizimle iletişime geçin",
  description: "Şirketiniz için profesyonel temizlik hizmetleri. Sizi memnuniyetle bireysel olarak bilgilendiririz.",
  submit_label: "Mesaj gönder",
  submit_failed: "Mesaj gönderilemedi.",
  select_placeholder: "Lütfen seçin",
  success_title: "Başarılı!",
  success_message: "Mesajınız başarıyla gönderildi. En kısa sürede size geri dönüş yapacağız.",
  redirecting: "Sayfa yenileniyor...",
  phone_label: "Telefon",
  submitting: "Gönderiliyor...",
  success_sub: "Size en kısa sürede geri dönüş yapacağız.",
  email_label: "E-posta",
  hours_label: "Çalışma saatleri",
  hours_value: "Pzt. - Cum.: 08:00 - 17:00",
  phone_number: "",
  email_address: "",
  form: {
    name: "İsim",
    phone: "Telefon",
    email: "E-posta",
    message: "Mesaj",
    other: "Alan"
  },
  error_generic: "Bir hata oluştu. Lütfen tekrar deneyin.",
  required: {
    name: "İsim gerekli.",
    phone: "Telefon gerekli.",
    email: "E-posta gerekli.",
    message: "Mesaj gerekli."
  }
};
const footer$b = {
  description: "Profesyonel temizlik, bakım ve bina yönetiminde Alman titizliği ve güvenilirliğiyle yanınızdayız.",
  address: "",
  phone: "",
  phone_raw: "",
  email: "",
  links_title: "Bağlantılar",
  contact_title: "İletişim",
  link_about: "Hakkımızda",
  link_contact: "İletişim",
  link_contact_href: "/contact",
  link_faq: "SSS",
  link_privacy: "Gizlilik",
  link_imprint: "İmpressum",
  link_privacy_bottom: "Gizlilik",
  copyright: "Tüm hakları saklıdır.",
  back_to_top: "Başa dön",
  region_label: "Alt bilgi ve iletişim bilgileri",
  home_aria: "O&I CLEAN - Ana sayfa",
  linkedin_aria: "LinkedIn - O&I CLEAN",
  instagram_aria: "Instagram - O&I CLEAN",
  facebook_aria: "Facebook - O&I CLEAN",
  quicklinks_aria: "Hızlı bağlantılar",
  phone_aria: "Telefon numarası",
  email_aria: "E-posta",
  services: "Hizmetler"
};
const header$b = {
  topbar_tagline: "Güvenebileceğiniz temizlik — 7/24 hizmet",
  cta_label: "Randevu al",
  cta_href: "/kontakt",
  hide_topbar: "Üst barı gizle",
  home_aria: "Ana sayfa",
  main_nav: "Ana navigasyon",
  menu_open: "Menüyü aç",
  menu_close: "Menüyü kapat",
  language: "Dil",
  impressum: "Damga",
  contact_button: "İletişim"
};
const nav$b = {
  home: "Ana sayfa"
};
const staticPage$b = {
  breadcrumbs_home: "Ana sayfa",
  breadcrumbs_page: "Sayfa",
  empty_content: "İçerik yakında eklenecek."
};
const offerDock$b = {
  title: "Teklif",
  subtitle: "Ücretsiz & bağlayıcı değil",
  button: "Talep et",
  aria_open: "Teklif panelini aç",
  aria_close: "Teklif panelini kapat"
};
const quote_modal$b = {
  title: "Teklif talep et",
  scrim_aria: "Modalı kapat",
  close_aria: "Kapat",
  field_name: "İsim",
  field_email: "E-posta",
  field_phone: "Telefon",
  field_service: "Hizmet",
  field_message: "Mesaj",
  service_placeholder: "Lütfen seçin…",
  service_hotel_cleaning: "Otel temizliği",
  service_building_cleaning: "Bina temizliği",
  service_window_cleaning: "Cam/pencere temizliği",
  service_maintenance_cleaning: "Sürekli temizlik",
  service_basic_cleaning: "Genel/derin temizlik",
  service_carpet_cleaning: "Halı temizliği",
  service_other: "Diğer",
  service_general: "Genel",
  message_placeholder: "Sizin için ne yapabiliriz?",
  consent_prefix: "Verilerimin işlenmesini ",
  consent_link: "gizlilik bildirimine",
  consent_suffix: " uygun olarak kabul ediyorum.",
  cancel: "İptal",
  submit: "Talep et",
  sending: "Gönderiliyor…",
  error_consent: "Lütfen veri işleme onayını verin.",
  error_generic: "Bir şeyler ters gitti. Lütfen tekrar deneyin.",
  subject_prefix: "Teklif talebi",
  thank_you_title: "Teşekkürler!",
  thank_you_text: "Talebiniz bize ulaştı, en kısa sürede sizinle iletişime geçeceğiz.",
  close_button: "Kapat"
};
const errors$3 = {
  notFound: {
    "404": "Aradığınız sayfa bulunamadı.",
    "500": "Sunucu hatası oluştu — lütfen tekrar deneyin.",
    home: "Ana Sayfaya Dön",
    contact: "İletişime Geç",
    reload: "Sayfayı Yenile"
  }
};
const ui$1 = {
  loading: {
    message: "İçerik yükleniyor…"
  }
};
const cookies$b = {
  title: "Çerez Tercihleri",
  message: "Web sitemizde en iyi deneyimi yaşamanız için çerezleri kullanıyoruz.",
  cat_necessary: "Zorunlu Çerezler",
  cat_analytics: "Analiz Çerezleri",
  cat_marketing: "Pazarlama Çerezleri",
  desc_necessary: "Bu çerezler web sitesinin çalışması için gereklidir.",
  desc_analytics: "Bu çerezler sitemizi nasıl kullandığınız hakkında bilgi toplar.",
  desc_marketing: "Bu çerezler size uygun reklamlar göstermek için kullanılır.",
  required: "Zorunlu",
  accept_all: "Tümünü Kabul Et",
  reject: "Reddet",
  save: "Seçimi Kaydet",
  settings: "Tercihler",
  hide_details: "Gizle",
  privacy_policy: "Gizlilik Politikası",
  imprint: "Künye"
};
const common$1 = {
  ok: "Tamam",
  demo: "Demo"
};
const required$b = {
  name: "İsim gerekli.",
  phone: "Telefon gerekli.",
  email: "E-posta gerekli.",
  message: "Mesaj gerekli."
};
const submit_error$6 = "Hata! Lütfen tekrar deneyin.";
const reviews = {
  title: "Müşterilerimiz ne diyor",
  subtitle: "Her gün hizmet verdiğimiz oteller, ofisler ve tesislerden gerçek geri bildirimler.",
  eyebrow: "Müşteri yorumları",
  rating_label: "Puan",
  count: "{{count}} değerlendirme",
  anonymous: "Müşteri"
};
const faq = {
  title: "Sıkça sorulan sorular",
  subtitle: "En çok merak edilenlerin yanıtları. Sorunuz burada yoksa bize ulaşmanız yeterli.",
  eyebrow: "SSS"
};
const tr = {
  hero: hero$b,
  services: services$b,
  servicesList: servicesList$b,
  contact: contact$b,
  footer: footer$b,
  header: header$b,
  nav: nav$b,
  staticPage: staticPage$b,
  offerDock: offerDock$b,
  quote_modal: quote_modal$b,
  errors: errors$3,
  ui: ui$1,
  cookies: cookies$b,
  common: common$1,
  required: required$b,
  submit_error: submit_error$6,
  reviews,
  faq
};
const hero$a = {
  title: "Votre partenaire fiable en hébergement et gestion d'installations",
  subtitle: "Avec plus de 25 ans d'expérience, nous offrons des solutions intégrées sur mesure pour le nettoyage, la maintenance et la gestion technique.",
  button_services: "Découvrez nos services",
  button_contact: "Contactez-nous maintenant"
};
const services$a = {
  section_title: "Notre large gamme de services",
  section_subtitle: "Nous fournissons des solutions clés en main pour tous les besoins de vos installations et bâtiments – avec la précision et la qualité allemandes.",
  learn_more: "Informations détaillées",
  categories: {
    hotel: {
      title: "Nettoyage d'hôtel et entretien ménager",
      description: "Du nettoyage des chambres à la plonge – une hygiène impeccable et des processus efficaces dans chaque zone de votre hôtel."
    },
    building: {
      title: "Nettoyage professionnel de bâtiments",
      description: "Bureaux, zones commerciales, nettoyage après construction et nettoyages spéciaux – nous faisons briller vos propriétés."
    },
    renovation: {
      title: "Rénovation, réparation et entretien",
      description: "Peinture, travaux de plâtre et cloisons sèches, revêtement de sol et petites réparations."
    }
  },
  card: {
    aria: "Obtenez plus d'informations sur {{service}}",
    button: "Détails"
  }
};
const servicesList$a = {
  title: "Services",
  subtitle: "Découvrez nos services complets pour votre domicile et votre entreprise",
  meta_title: "Nos Services - O&I CLEAN group GmbH",
  meta_description: "Nettoyage professionnel, rénovation et gestion d'installations.",
  loading: "Chargement…",
  contact_cta: "Contactez-nous",
  contact_cta_aria: "Contactez-nous maintenant"
};
const contact$a = {
  page_title: "Contact",
  intro_static_text: "Gratuit et sans engagement –",
  intro_dynamic_text: "nous vous répondrons dès que possible",
  title: "Contactez-nous",
  description: "Services de nettoyage professionnel pour votre entreprise. Nous sommes heureux de vous informer individuellement.",
  submit_label: "Envoyer le message",
  submit_failed: "Le message n'a pas pu être envoyé.",
  select_placeholder: "Veuillez sélectionner",
  success_title: "Succès !",
  success_message: "Votre message a été envoyé avec succès. Nous vous recontacterons sous peu.",
  redirecting: "La page se recharge...",
  phone_label: "Téléphone",
  submitting: "Envoi en cours...",
  success_sub: "Nous vous recontacterons sous peu.",
  email_label: "E-mail",
  hours_label: "Heures d'ouverture",
  hours_value: "Lun. - Ven. : 08:00 - 17:00",
  phone_number: "",
  email_address: "",
  form: {
    name: "Nom",
    phone: "Téléphone",
    email: "E-mail",
    message: "Message",
    other: "Champ"
  },
  required: {
    name: "Le nom est requis.",
    phone: "Le téléphone est requis.",
    email: "L'e-mail est requis.",
    message: "Le message est requis."
  },
  error_generic: "Une erreur est survenue. Veuillez réessayer."
};
const footer$a = {
  description: "Nous sommes à vos côtés avec la rigueur et la fiabilité allemandes en matière de nettoyage professionnel, de maintenance et de gestion d'installations.",
  address: "",
  phone: "",
  phone_raw: "",
  email: "",
  links_title: "Liens",
  contact_title: "Contact",
  link_about: "À propos de nous",
  link_contact: "Contact",
  link_contact_href: "/contact",
  link_faq: "FAQ",
  link_privacy: "Confidentialité",
  link_imprint: "Mentions légales",
  link_privacy_bottom: "Confidentialité",
  copyright: "Tous droits réservés.",
  back_to_top: "Retour en haut",
  region_label: "Pied de page et informations de contact",
  home_aria: "O&I CLEAN - Accueil",
  linkedin_aria: "LinkedIn - O&I CLEAN",
  instagram_aria: "Instagram - O&I CLEAN",
  facebook_aria: "Facebook - O&I CLEAN",
  quicklinks_aria: "Liens rapides",
  phone_aria: "Numéro de téléphone",
  email_aria: "E-mail"
};
const header$a = {
  topbar_tagline: "Un nettoyage en qui vous pouvez avoir confiance — Service 24/7",
  cta_label: "Prendre rendez-vous",
  cta_href: "/contact",
  hide_topbar: "Masquer la barre supérieure",
  home_aria: "Page d'accueil",
  main_nav: "Navigation principale",
  menu_open: "Ouvrir le menu",
  menu_close: "Fermer le menu",
  language: "Langue",
  impressum: "Mentions légales",
  contact_button: "Contact"
};
const nav$a = {
  home: "Accueil"
};
const staticPage$a = {
  breadcrumbs_home: "Accueil",
  breadcrumbs_page: "Page",
  empty_content: "Le contenu sera ajouté bientôt."
};
const offerDock$a = {
  title: "Offre",
  subtitle: "Gratuit et sans engagement",
  button: "Demander",
  aria_open: "Ouvrir le panneau d'offre",
  aria_close: "Fermer le panneau d'offre"
};
const quote_modal$a = {
  title: "Demander un devis",
  scrim_aria: "Fermer la fenêtre",
  close_aria: "Fermer",
  field_name: "Nom",
  field_email: "E-mail",
  field_phone: "Téléphone",
  field_service: "Service",
  field_message: "Message",
  service_placeholder: "Veuillez sélectionner…",
  service_hotel_cleaning: "Nettoyage d'hôtel",
  service_building_cleaning: "Nettoyage de bâtiment",
  service_window_cleaning: "Nettoyage de vitres",
  service_maintenance_cleaning: "Nettoyage d'entretien",
  service_basic_cleaning: "Nettoyage général/approfondi",
  service_carpet_cleaning: "Nettoyage de tapis",
  service_other: "Autre",
  service_general: "Général",
  message_placeholder: "Que pouvons-nous faire pour vous ?",
  consent_prefix: "J'accepte le traitement de mes données conformément à la ",
  consent_link: "politique de confidentialité",
  consent_suffix: ".",
  cancel: "Annuler",
  submit: "Demander",
  sending: "Envoi en cours…",
  error_consent: "Veuillez donner votre consentement pour le traitement des données.",
  error_generic: "Une erreur est survenue. Veuillez réessayer.",
  subject_prefix: "Demande de devis",
  thank_you_title: "Merci !",
  thank_you_text: "Votre demande a été reçue, nous vous contacterons prochainement.",
  close_button: "Fermer"
};
const errors$2 = {
  notFound: {
    "404": "La page demandée est introuvable.",
    "500": "Une erreur serveur est survenue.",
    home: "Retour à l'accueil",
    contact: "Contactez-nous",
    reload: "Recharger la page"
  }
};
const ui = {
  loading: {
    message: "Chargement du contenu…"
  }
};
const cookies$a = {
  title: "Préférences de Cookies",
  message: "Nous utilisons des cookies pour vous garantir la meilleure expérience.",
  cat_necessary: "Cookies Nécessaires",
  cat_analytics: "Cookies d'Analyse",
  cat_marketing: "Cookies Marketing",
  desc_necessary: "Requis pour le fonctionnement du site.",
  desc_analytics: "Collectent des informations sur l'utilisation.",
  desc_marketing: "Utilisés pour des publicités pertinentes.",
  required: "Requis",
  accept_all: "Tout accepter",
  reject: "Refuser",
  save: "Enregistrer la sélection",
  settings: "Préférences",
  hide_details: "Masquer les détails",
  privacy_policy: "Politique de confidentialité",
  imprint: "Mentions légales"
};
const common = {
  ok: "OK"
};
const required$a = {
  name: "Le nom est requis.",
  phone: "Le téléphone est requis.",
  email: "L'e-mail est requis.",
  message: "Le message est requis."
};
const submit_error$5 = "Erreur ! Veuillez réessayer.";
const fr = {
  hero: hero$a,
  services: services$a,
  servicesList: servicesList$a,
  contact: contact$a,
  footer: footer$a,
  header: header$a,
  nav: nav$a,
  staticPage: staticPage$a,
  offerDock: offerDock$a,
  quote_modal: quote_modal$a,
  errors: errors$2,
  ui,
  cookies: cookies$a,
  common,
  required: required$a,
  submit_error: submit_error$5
};
const hero$9 = {
  title: "Su socio confiable en alojamiento y gestión de instalaciones",
  subtitle: "Con más de 25 años de experiencia, ofrecemos soluciones integradas a medida para limpieza, mantenimiento y gestión de instalaciones.",
  button_services: "Descubra nuestros servicios",
  button_contact: "Contáctenos ahora"
};
const services$9 = {
  section_title: "Nuestra amplia gama de servicios",
  section_subtitle: "Ofrecemos soluciones llave en mano para todas las necesidades de sus instalaciones y edificios, con precisión y calidad alemanas.",
  learn_more: "Información detallada",
  categories: {
    hotel: {
      title: "Limpieza de hoteles y ama de llaves",
      description: "Desde la limpieza de habitaciones jusqu'à la zona de lavado: higiene impecable y procesos eficientes en cada área de su hotel."
    },
    building: {
      title: "Limpieza profesional de edificios",
      description: "Oficinas, áreas comerciales, limpieza post-construcción y limpiezas especiales: hacemos que sus propiedades brillen."
    },
    renovation: {
      title: "Renovación, reparación y mantenimiento",
      description: "Pintura, trabajos de yeso y paneles de yeso, suelos y pequeñas reparaciones."
    }
  },
  card: {
    aria: "Obtenga más información sobre {{service}}",
    button: "Detalles"
  }
};
const servicesList$9 = {
  title: "Servicios",
  subtitle: "Descubra nuestros servicios integrales para su hogar y negocio",
  meta_title: "Nuestros Servicios - O&I CLEAN group GmbH",
  meta_description: "Limpieza profesional, renovación y gestión de instalaciones.",
  loading: "Cargando…",
  contact_cta: "Contáctenos",
  contact_cta_aria: "Contáctenos ahora"
};
const contact$9 = {
  page_title: "Contacto",
  intro_static_text: "Gratis y sin compromiso –",
  intro_dynamic_text: "le responderemos lo antes posible",
  title: "Contáctenos",
  description: "Servicios de limpieza profesional para su empresa. Estaremos encantados de informarle individualmente.",
  submit_label: "Enviar mensaje",
  submit_failed: "No se pudo enviar el mensaje.",
  select_placeholder: "Por favor seleccione",
  success_title: "¡Éxito!",
  success_message: "Su mensaje ha sido enviado con éxito. Nos pondremos en contacto con usted en breve.",
  redirecting: "La página se está recargando...",
  phone_label: "Teléfono",
  submitting: "Enviando...",
  success_sub: "Nos pondremos en contacto con usted en breve.",
  email_label: "Correo electrónico",
  hours_label: "Horario de apertura",
  hours_value: "Lun. - Vie.: 08:00 - 17:00",
  phone_number: "",
  email_address: "",
  form: {
    name: "Nombre",
    phone: "Teléfono",
    email: "Correo electrónico",
    message: "Mensaje",
    other: "Campo"
  },
  required: {
    name: "El nombre es obligatorio.",
    phone: "El teléfono es obligatorio.",
    email: "El correo electrónico es obligatorio.",
    message: "El mensaje es obligatorio."
  },
  error_generic: "Ocurrió un error. Por favor, inténtelo de nuevo."
};
const footer$9 = {
  description: "Estamos a su lado con la minuciosidad y confiabilidad alemanas en limpieza profesional, mantenimiento y gestión de instalaciones.",
  address: "",
  phone: "",
  phone_raw: "",
  email: "",
  links_title: "Enlaces",
  contact_title: "Contacto",
  link_about: "Sobre nosotros",
  link_contact: "Contacto",
  link_contact_href: "/contact",
  link_faq: "FAQ",
  link_privacy: "Privacidad",
  link_imprint: "Aviso legal",
  link_privacy_bottom: "Privacidad",
  copyright: "Todos los derechos reservados.",
  back_to_top: "Volver arriba",
  region_label: "Pie de página e información de contacto",
  home_aria: "O&I CLEAN - Inicio",
  linkedin_aria: "LinkedIn - O&I CLEAN",
  instagram_aria: "Instagram - O&I CLEAN",
  facebook_aria: "Facebook - O&I CLEAN",
  quicklinks_aria: "Enlaces rápidos",
  phone_aria: "Número de teléfono",
  email_aria: "Correo electrónico"
};
const header$9 = {
  topbar_tagline: "Limpieza en la que puede confiar — Servicio 24/7",
  cta_label: "Reservar una cita",
  cta_href: "/contact",
  hide_topbar: "Ocultar barra superior",
  home_aria: "Página de inicio",
  main_nav: "Navegación principal",
  menu_open: "Abrir menú",
  menu_close: "Cerrar menú",
  language: "Idioma",
  impressum: "Aviso legal",
  contact_button: "Contacto"
};
const nav$9 = {
  home: "Inicio"
};
const staticPage$9 = {
  breadcrumbs_home: "Inicio",
  breadcrumbs_page: "Página",
  empty_content: "El contenido se agregará pronto."
};
const offerDock$9 = {
  title: "Oferta",
  subtitle: "Gratis y sin compromiso",
  button: "Solicitar",
  aria_open: "Abrir panel de oferta",
  aria_close: "Cerrar panel de oferta"
};
const quote_modal$9 = {
  title: "Solicitar presupuesto",
  scrim_aria: "Cerrar modal",
  close_aria: "Cerrar",
  field_name: "Nombre",
  field_email: "Correo electrónico",
  field_phone: "Teléfono",
  field_service: "Servicio",
  field_message: "Mensaje",
  service_placeholder: "Por favor seleccione…",
  service_hotel_cleaning: "Limpieza de hoteles",
  service_building_cleaning: "Limpieza de edificios",
  service_window_cleaning: "Limpieza de ventanas",
  service_maintenance_cleaning: "Limpieza de mantenimiento",
  service_basic_cleaning: "Limpieza general/profunda",
  service_carpet_cleaning: "Limpieza de alfombras",
  service_other: "Otro",
  service_general: "General",
  message_placeholder: "¿Qué podemos hacer por usted?",
  consent_prefix: "Acepto el procesamiento de mis datos de acuerdo con la ",
  consent_link: "política de privacidad",
  consent_suffix: ".",
  cancel: "Cancelar",
  submit: "Solicitar",
  sending: "Enviando…",
  error_consent: "Por favor, dé su consentimiento para el procesamiento de datos.",
  error_generic: "Algo salió mal. Por favor, inténtelo de nuevo.",
  subject_prefix: "Solicitud de presupuesto",
  thank_you_title: "¡Gracias!",
  thank_you_text: "Hemos recibido su solicitud, nos pondremos en contacto con usted en breve.",
  close_button: "Cerrar"
};
const errors$1 = {
  notFound: {
    "404": "No se pudo encontrar la página solicitada.",
    home: "Volver al inicio",
    contact: "Contáctenos"
  }
};
const cookies$9 = {
  title: "Preferencias de Cookies",
  message: "Utilizamos cookies para asegurar que tenga la mejor experiencia.",
  accept_all: "Aceptar todo",
  reject: "Rechazar",
  save: "Guardar selección"
};
const required$9 = {
  name: "El nombre es obligatorio.",
  phone: "El teléfono es obligatorio.",
  email: "El correo electrónico es obligatorio.",
  message: "El mensaje es obligatorio."
};
const es = {
  hero: hero$9,
  services: services$9,
  servicesList: servicesList$9,
  contact: contact$9,
  footer: footer$9,
  header: header$9,
  nav: nav$9,
  staticPage: staticPage$9,
  offerDock: offerDock$9,
  quote_modal: quote_modal$9,
  errors: errors$1,
  cookies: cookies$9,
  required: required$9
};
const hero$8 = {
  title: "Il tuo partner affidabile nell'alloggio e nella gestione delle strutture",
  subtitle: "Con oltre 25 anni di esperienza, offriamo soluzioni integrate su misura per pulizia, manutenzione e facility management.",
  button_services: "Scopri i nostri servizi",
  button_contact: "Contattaci ora"
};
const services$8 = {
  section_title: "La nostra ampia gamma di servizi",
  section_subtitle: "Forniamo soluzioni chiavi in mano per tutte le necessità delle vostre strutture e edifici – con precisione e qualità tedesca.",
  learn_more: "Informazioni dettagliate",
  categories: {
    hotel: {
      title: "Pulizia hotel e housekeeping",
      description: "Dalla pulizia delle camere alla zona lavaggio – igiene impeccabile e processi efficienti in ogni area del vostro hotel."
    },
    building: {
      title: "Pulizia professionale di edifici",
      description: "Uffici, aree commerciali, pulizia post-costruzione e pulizie speciali – facciamo risplendere le vostre proprietà."
    },
    renovation: {
      title: "Ristrutturazione, riparazione e manutenzione",
      description: "Pittura, lavori in cartongesso, pavimentazione e piccole riparazioni."
    }
  },
  card: {
    aria: "Ottieni più informazioni su {{service}}",
    button: "Dettagli"
  }
};
const servicesList$8 = {
  title: "Servizi",
  subtitle: "Scopri i nostri servizi completi per la casa e il business",
  meta_title: "I Nostri Servizi - O&I CLEAN group GmbH",
  meta_description: "Pulizia professionale, ristrutturazione e facility management.",
  loading: "Caricamento…",
  contact_cta: "Contattaci",
  contact_cta_aria: "Contattaci ora"
};
const contact$8 = {
  page_title: "Contatti",
  intro_static_text: "Gratuito e senza impegno –",
  intro_dynamic_text: "ti risponderemo il prima possibile",
  title: "Contattaci",
  description: "Servizi di pulizia professionale per la tua azienda. Siamo lieti di informarti individualmente.",
  submit_label: "Invia messaggio",
  submit_failed: "Impossibile inviare il messaggio.",
  select_placeholder: "Per favore seleziona",
  success_title: "Successo!",
  success_message: "Il tuo messaggio è stato inviato con successo. Ti ricontatteremo a breve.",
  redirecting: "Ricaricamento della pagina...",
  phone_label: "Telefono",
  submitting: "Invio in corso...",
  success_sub: "Ti ricontatteremo a breve.",
  email_label: "Email",
  hours_label: "Orari di apertura",
  hours_value: "Lun. - Ven.: 08:00 - 17:00",
  phone_number: "",
  email_address: "",
  form: {
    name: "Nome",
    phone: "Telefono",
    email: "Email",
    message: "Messaggio",
    other: "Campo"
  },
  required: {
    name: "Il nome è obbligatorio.",
    phone: "Il telefono è obbligatorio.",
    email: "L'email è obbligatoria.",
    message: "Il messaggio è obbligatorio."
  },
  error_generic: "Si è verificato un errore. Riprova."
};
const footer$8 = {
  description: "Siamo al tuo fianco con la precisione e l'affidabilità tedesca nella pulizia professionale, manutenzione e gestione delle strutture.",
  address: "",
  phone: "",
  phone_raw: "",
  email: "",
  links_title: "Link",
  contact_title: "Contatto",
  link_about: "Chi siamo",
  link_contact: "Contatto",
  link_contact_href: "/contact",
  link_faq: "FAQ",
  link_privacy: "Privacy",
  link_imprint: "Note legali",
  link_privacy_bottom: "Privacy",
  copyright: "Tutti i diritti riservati.",
  back_to_top: "Torna su",
  region_label: "Piè di pagina e informazioni di contatto",
  home_aria: "O&I CLEAN - Home",
  linkedin_aria: "LinkedIn - O&I CLEAN",
  instagram_aria: "Instagram - O&I CLEAN",
  facebook_aria: "Facebook - O&I CLEAN",
  quicklinks_aria: "Link rapidi",
  phone_aria: "Numero di telefono",
  email_aria: "Email"
};
const header$8 = {
  topbar_tagline: "Pulizia di cui ti puoi fidare — Servizio 24/7",
  cta_label: "Prenota un appuntamento",
  cta_href: "/contact",
  hide_topbar: "Nascondi barra superiore",
  home_aria: "Home page",
  main_nav: "Navigazione principale",
  menu_open: "Apri menu",
  menu_close: "Chiudi menu",
  language: "Lingua",
  impressum: "Note legali",
  contact_button: "Contatto"
};
const nav$8 = {
  home: "Home"
};
const staticPage$8 = {
  breadcrumbs_home: "Home",
  breadcrumbs_page: "Pagina",
  empty_content: "I contenuti saranno aggiunti presto."
};
const offerDock$8 = {
  title: "Offerta",
  subtitle: "Gratuita e senza impegno",
  button: "Richiedi",
  aria_open: "Apri pannello offerta",
  aria_close: "Chiudi pannello offerta"
};
const quote_modal$8 = {
  title: "Richiedi un preventivo",
  scrim_aria: "Chiudi modale",
  close_aria: "Chiudi",
  field_name: "Nome",
  field_email: "Email",
  field_phone: "Telefono",
  field_service: "Servizio",
  field_message: "Messaggio",
  service_placeholder: "Per favore seleziona…",
  service_hotel_cleaning: "Pulizia hotel",
  service_building_cleaning: "Pulizia edifici",
  service_window_cleaning: "Pulizia finestre",
  service_maintenance_cleaning: "Pulizia di manutenzione",
  service_basic_cleaning: "Pulizia generale/profonda",
  service_carpet_cleaning: "Pulizia tappeti",
  service_other: "Altro",
  service_general: "Generale",
  message_placeholder: "Cosa possiamo fare per te?",
  consent_prefix: "Acconsento al trattamento dei miei dati in conformità con la ",
  consent_link: "politica sulla privacy",
  consent_suffix: ".",
  cancel: "Annulla",
  submit: "Richiedi",
  sending: "Invio in corso…",
  error_consent: "Si prega di fornire il consenso per il trattamento dei dati.",
  error_generic: "Qualcosa è andato storto. Riprova.",
  subject_prefix: "Richiesta preventivo",
  thank_you_title: "Grazie!",
  thank_you_text: "La tua richiesta è stata ricevuta, ti contatteremo a breve.",
  close_button: "Chiudi"
};
const cookies$8 = {
  title: "Preferenze Cookie",
  message: "Utilizziamo i cookie per assicurarti la migliore esperienza.",
  accept_all: "Accetta tutto",
  reject: "Rifiuta",
  save: "Salva selezione"
};
const required$8 = {
  name: "Il nome è obbligatorio.",
  phone: "Il telefono è obbligatorio.",
  email: "L'email è obbligatoria.",
  message: "Il messaggio è obbligatorio."
};
const it = {
  hero: hero$8,
  services: services$8,
  servicesList: servicesList$8,
  contact: contact$8,
  footer: footer$8,
  header: header$8,
  nav: nav$8,
  staticPage: staticPage$8,
  offerDock: offerDock$8,
  quote_modal: quote_modal$8,
  cookies: cookies$8,
  required: required$8
};
const hero$7 = {
  title: "Seu parceiro confiável em hospedagem e gestão de instalações",
  subtitle: "Com mais de 25 anos de experiência, oferecemos soluções integradas sob medida para limpeza, manutenção e gestão de instalações.",
  button_services: "Descubra nossos serviços",
  button_contact: "Contate-nos agora"
};
const services$7 = {
  section_title: "Nossa ampla gama de serviços",
  section_subtitle: "Fornecemos soluções completas para todas as necessidades das suas instalações e edifícios – com precisão e qualidade alemãs.",
  learn_more: "Informações detalhadas",
  categories: {
    hotel: {
      title: "Limpeza de hotéis e housekeeping",
      description: "Da limpeza dos quartos à área de lavagem – higiene impecável e processos eficientes em todas as áreas do seu hotel."
    },
    building: {
      title: "Limpeza profissional de edifícios",
      description: "Escritórios, áreas comerciais, limpeza pós-obra e limpezas especiais – fazemos as suas propriedades brilharem."
    },
    renovation: {
      title: "Renovação, reparação e manutenção",
      description: "Pintura, trabalhos em gesso, pisos e pequenas reparações."
    }
  },
  card: {
    aria: "Obter mais informações sobre {{service}}",
    button: "Detalhes"
  }
};
const servicesList$7 = {
  title: "Serviços",
  subtitle: "Descubra nossos serviços abrangentes para sua casa e empresa",
  meta_title: "Nossos Serviços - O&I CLEAN group GmbH",
  meta_description: "Limpeza profissional, renovação e gestão de instalações.",
  loading: "Carregando…",
  contact_cta: "Contate-nos",
  contact_cta_aria: "Contate-nos agora"
};
const contact$7 = {
  page_title: "Contato",
  intro_static_text: "Gratuito e sem compromisso –",
  intro_dynamic_text: "retornaremos o mais breve possível",
  title: "Contate-nos",
  description: "Serviços de limpeza profissional para sua empresa. Temos prazer em informá-lo individualmente.",
  submit_label: "Enviar mensagem",
  submit_failed: "Não foi possível enviar a mensagem.",
  select_placeholder: "Por favor selecione",
  success_title: "Sucesso!",
  success_message: "Sua mensagem foi enviada com sucesso. Entraremos em contato em breve.",
  redirecting: "A página está recarregando...",
  phone_label: "Telefone",
  submitting: "Enviando...",
  success_sub: "Entraremos em contato em breve.",
  email_label: "E-mail",
  hours_label: "Horário de funcionamento",
  hours_value: "Seg. - Sex.: 08:00 - 17:00",
  phone_number: "",
  email_address: "",
  form: {
    name: "Nome",
    phone: "Telefone",
    email: "E-mail",
    message: "Mensagem",
    other: "Campo"
  },
  required: {
    name: "Nome é obrigatório.",
    phone: "Telefone é obrigatório.",
    email: "E-mail é obrigatório.",
    message: "Mensagem é obrigatória."
  },
  error_generic: "Ocorreu um erro. Por favor, tente novamente."
};
const footer$7 = {
  description: "Estamos ao seu lado com o rigor e a confiabilidade alemães em limpeza profissional, manutenção e gestão de instalações.",
  address: "",
  phone: "",
  phone_raw: "",
  email: "",
  links_title: "Links",
  contact_title: "Contato",
  link_about: "Sobre nós",
  link_contact: "Contato",
  link_contact_href: "/contact",
  link_faq: "FAQ",
  link_privacy: "Privacidade",
  link_imprint: "Aviso legal",
  link_privacy_bottom: "Privacidade",
  copyright: "Todos os direitos reservados.",
  back_to_top: "Voltar ao topo",
  region_label: "Rodapé e informações de contato",
  home_aria: "O&I CLEAN - Início",
  linkedin_aria: "LinkedIn - O&I CLEAN",
  instagram_aria: "Instagram - O&I CLEAN",
  facebook_aria: "Facebook - O&I CLEAN",
  quicklinks_aria: "Links rápidos",
  phone_aria: "Número de telefone",
  email_aria: "E-mail"
};
const header$7 = {
  topbar_tagline: "Limpeza em que você pode confiar — Serviço 24/7",
  cta_label: "Marcar um horário",
  cta_href: "/contact",
  hide_topbar: "Ocultar barra superior",
  home_aria: "Página inicial",
  main_nav: "Navegação principal",
  menu_open: "Abrir menu",
  menu_close: "Fechar menu",
  language: "Idioma",
  impressum: "Aviso legal",
  contact_button: "Contato"
};
const nav$7 = {
  home: "Início"
};
const staticPage$7 = {
  breadcrumbs_home: "Início",
  breadcrumbs_page: "Página",
  empty_content: "O conteúdo será adicionado em breve."
};
const offerDock$7 = {
  title: "Oferta",
  subtitle: "Grátis e sem compromisso",
  button: "Solicitar",
  aria_open: "Abrir painel de oferta",
  aria_close: "Fechar painel de oferta"
};
const quote_modal$7 = {
  title: "Solicitar orçamento",
  scrim_aria: "Fechar modal",
  close_aria: "Fechar",
  field_name: "Nome",
  field_email: "E-mail",
  field_phone: "Telefone",
  field_service: "Serviço",
  field_message: "Mensagem",
  service_placeholder: "Por favor selecione…",
  service_hotel_cleaning: "Limpeza de hotéis",
  service_building_cleaning: "Limpeza de edifícios",
  service_window_cleaning: "Limpeza de janelas",
  service_maintenance_cleaning: "Limpeza de manutenção",
  service_basic_cleaning: "Limpeza geral/profunda",
  service_carpet_cleaning: "Limpeza de carpetes",
  service_other: "Outro",
  service_general: "Geral",
  message_placeholder: "O que podemos fazer por você?",
  consent_prefix: "Concordo com o processamento dos meus dados de acordo com a ",
  consent_link: "política de privacidade",
  consent_suffix: ".",
  cancel: "Cancelar",
  submit: "Solicitar",
  sending: "Enviando…",
  error_consent: "Por favor, forneça o consentimento para o processamento de dados.",
  error_generic: "Algo deu errado. Por favor, tente novamente.",
  subject_prefix: "Solicitação de orçamento",
  thank_you_title: "Obrigado!",
  thank_you_text: "Seu pedido foi recebido, entraremos em contato em breve.",
  close_button: "Fechar"
};
const cookies$7 = {
  title: "Preferências de Cookies",
  message: "Usamos cookies para garantir que você tenha a melhor experiência.",
  accept_all: "Aceitar tudo",
  reject: "Rejeitar",
  save: "Salvar seleção"
};
const required$7 = {
  name: "Nome é obrigatório.",
  phone: "Telefone é obrigatório.",
  email: "E-mail é obrigatório.",
  message: "Mensagem é obrigatória."
};
const pt = {
  hero: hero$7,
  services: services$7,
  servicesList: servicesList$7,
  contact: contact$7,
  footer: footer$7,
  header: header$7,
  nav: nav$7,
  staticPage: staticPage$7,
  offerDock: offerDock$7,
  quote_modal: quote_modal$7,
  cookies: cookies$7,
  required: required$7
};
const hero$6 = {
  title: "Partenerul tău de încredere în cazare și managementul facilităților",
  subtitle: "Cu peste 25 de ani de experiență, oferim soluții integrate personalizate pentru curățenie, întreținere și managementul facilităților",
  button_services: "Descoperă serviciile noastre",
  button_contact: "Contactează-ne acum"
};
const services$6 = {
  section_title: "Gama noastră largă de servicii",
  section_subtitle: "Oferim soluții la cheie pentru toate nevoile facilităților și clădirilor dumneavoastră – cu precizie și calitate germană.",
  learn_more: "Informații detaliate",
  categories: {
    hotel: {
      title: "Curățenie hotelieră și housekeeping",
      description: "De la curățarea camerelor până la spălătorie – igienă impecabilă și procese eficiente în fiecare zonă a hotelului dumneavoastră."
    },
    building: {
      title: "Curățenie profesională a clădirilor",
      description: "Birouri, zone comerciale, curățenie după constructor și curățenii speciale – facem proprietățile dumneavoastră să strălucească."
    },
    renovation: {
      title: "Renovare, reparații și întreținere",
      description: "Zugrăveli, lucrări de gips-carton, pardoseli și reparații mici."
    }
  },
  card: {
    aria: "Obține mai multe informații despre {{service}}",
    button: "Detalii"
  }
};
const servicesList$6 = {
  title: "Servicii",
  subtitle: "Descoperiți serviciile noastre complete pentru casa și afacerea dumneavoastră",
  meta_title: "Serviciile Noastre - O&I CLEAN group GmbH",
  meta_description: "Curățenie profesională, renovare și managementul facilităților.",
  loading: "Se încarcă…",
  contact_cta: "Contactează-ne",
  contact_cta_aria: "Contactează-ne acum"
};
const contact$6 = {
  page_title: "Contact",
  intro_static_text: "Gratuit și fără obligații –",
  intro_dynamic_text: "vă vom răspunde în cel mai scurt timp posibil",
  title: "Contactează-ne",
  description: "Servicii profesionale de curățenie pentru compania dumneavoastră. Suntem bucuroși să vă informăm individual.",
  submit_label: "Trimite mesaj",
  submit_failed: "Mesajul nu a putut fi trimis.",
  select_placeholder: "Vă rugăm selectați",
  success_title: "Succes!",
  success_message: "Mesajul dumneavoastră a fost trimis cu succes. Vă vom contacta în curând.",
  redirecting: "Pagina se reîncarcă...",
  phone_label: "Telefon",
  submitting: "Se trimite...",
  success_sub: "Vă vom contacta în curând.",
  email_label: "Email",
  hours_label: "Program de lucru",
  hours_value: "Lun. - Vin.: 08:00 - 17:00",
  phone_number: "",
  email_address: "",
  form: {
    name: "Nume",
    phone: "Telefon",
    email: "Email",
    message: "Mesaj",
    other: "Câmp"
  },
  required: {
    name: "Numele este obligatoriu.",
    phone: "Telefonul este obligatoriu.",
    email: "Email-ul este obligatoriu.",
    message: "Mesajul este obligatoriu."
  },
  error_generic: "A apărut o eroare. Vă rugăm să încercați din nou."
};
const footer$6 = {
  description: "Suntem alături de dumneavoastră cu temeinicia și fiabilitatea germană în curățenie profesională, întreținere și managementul facilităților.",
  address: "",
  phone: "",
  phone_raw: "",
  email: "",
  links_title: "Link-uri",
  contact_title: "Contact",
  link_about: "Despre noi",
  link_contact: "Contact",
  link_contact_href: "/contact",
  link_faq: "FAQ",
  link_privacy: "Confidențialitate",
  link_imprint: "Amprentă legală",
  link_privacy_bottom: "Confidențialitate",
  copyright: "Toate drepturile rezervate.",
  back_to_top: "Înapoi sus",
  region_label: "Subsol și informații de contact",
  home_aria: "O&I CLEAN - Pagina principală",
  linkedin_aria: "LinkedIn - O&I CLEAN",
  instagram_aria: "Instagram - O&I CLEAN",
  facebook_aria: "Facebook - O&I CLEAN",
  quicklinks_aria: "Link-uri rapide",
  phone_aria: "Număr de telefon",
  email_aria: "Email"
};
const header$6 = {
  topbar_tagline: "Curățenie în care poți avea încredere — Serviciu 24/7",
  cta_label: "Programați o întâlnire",
  cta_href: "/contact",
  hide_topbar: "Ascunde bara de sus",
  home_aria: "Pagina principală",
  main_nav: "Navigație principală",
  menu_open: "Deschide meniul",
  menu_close: "Închide meniul",
  language: "Limbă",
  impressum: "Amprentă legală",
  contact_button: "Contact"
};
const nav$6 = {
  home: "Acasă"
};
const staticPage$6 = {
  breadcrumbs_home: "Acasă",
  breadcrumbs_page: "Pagină",
  empty_content: "Conținutul va fi adăugat în curând."
};
const offerDock$6 = {
  title: "Ofertă",
  subtitle: "Gratuit și fără obligații",
  button: "Solicită",
  aria_open: "Deschide panoul de ofertă",
  aria_close: "Închide panoul de ofertă"
};
const quote_modal$6 = {
  title: "Solicită o ofertă",
  scrim_aria: "Închide fereastra",
  close_aria: "Închide",
  field_name: "Nume",
  field_email: "Email",
  field_phone: "Telefon",
  field_service: "Serviciu",
  field_message: "Mesaj",
  service_placeholder: "Vă rugăm selectați…",
  service_hotel_cleaning: "Curățenie hotelieră",
  service_building_cleaning: "Curățenie clădiri",
  service_window_cleaning: "Curățenie geamuri",
  service_maintenance_cleaning: "Curățenie de întreținere",
  service_basic_cleaning: "Curățenie generală/profundă",
  service_carpet_cleaning: "Curățare covoare",
  service_other: "Altele",
  service_general: "General",
  message_placeholder: "Ce putem face pentru dumneavoastră?",
  consent_prefix: "Sunt de acord cu prelucrarea datelor mele în conformitate cu ",
  consent_link: "politica de confidențialitate",
  consent_suffix: ".",
  cancel: "Anulează",
  submit: "Solicită",
  sending: "Se trimite…",
  error_consent: "Vă rugăm să vă dați consimțământul pentru prelucrarea datelor.",
  error_generic: "Ceva nu a mers bine. Vă rugăm să încercați din nou.",
  subject_prefix: "Solicitare ofertă",
  thank_you_title: "Mulțumim!",
  thank_you_text: "Solicitarea dumneavoastră a fost primită, vă vom contacta în curând.",
  close_button: "Închide"
};
const cookies$6 = {
  title: "Preferințe Cookie",
  message: "Folosim cookie-uri pentru a vă asigura cea mai bună experiență pe site-ul nostru.",
  accept_all: "Acceptă tot",
  reject: "Refuză",
  save: "Salvează selecția"
};
const required$6 = {
  name: "Numele este obligatoriu.",
  phone: "Telefonul este obligatoriu.",
  email: "Email-ul este obligatoriu.",
  message: "Mesajul este obligatoriu."
};
const ro = {
  hero: hero$6,
  services: services$6,
  servicesList: servicesList$6,
  contact: contact$6,
  footer: footer$6,
  header: header$6,
  nav: nav$6,
  staticPage: staticPage$6,
  offerDock: offerDock$6,
  quote_modal: quote_modal$6,
  cookies: cookies$6,
  required: required$6
};
const hero$5 = {
  title: "Ваш надежный партнер в сфере размещения и управления объектами",
  subtitle: "Обладая более чем 25-летним опытом, мы предлагаем индивидуальные комплексные решения для уборки, технического обслуживания и управления объектами.",
  button_services: "Наши услуги",
  button_contact: "Связаться с нами"
};
const services$5 = {
  section_title: "Наш широкий спектр услуг",
  section_subtitle: "Мы предлагаем решения «под ключ» для всех нужд ваших объектов и зданий – с немецкой точностью и качеством.",
  learn_more: "Подробная информация",
  categories: {
    hotel: {
      title: "Уборка отелей и гостиничный сервис",
      description: "От уборки номеров до мойки посуды – безупречная гигиена и эффективные процессы в каждой зоне вашего отеля."
    },
    building: {
      title: "Профессиональная уборка зданий",
      description: "Офисы, коммерческие площади, послестроительная уборка и специальные виды клининга – мы заставим вашу недвижимость сиять."
    },
    renovation: {
      title: "Ремонт и техническое обслуживание",
      description: "Малярные работы, гипсокартон, напольные покрытия и мелкий ремонт."
    }
  },
  card: {
    aria: "Получить больше информации о {{service}}",
    button: "Подробнее"
  }
};
const servicesList$5 = {
  title: "Услуги",
  subtitle: "Откройте для себя наши комплексные услуги для дома и бизнеса",
  meta_title: "Наши услуги - O&I CLEAN group GmbH",
  meta_description: "Профессиональная уборка, ремонт и управление объектами.",
  loading: "Загрузка…",
  contact_cta: "Связаться с нами",
  contact_cta_aria: "Связаться с нами сейчас"
};
const contact$5 = {
  page_title: "Контакты",
  intro_static_text: "Бесплатно и без обязательств –",
  intro_dynamic_text: "мы ответим вам как можно скорее",
  title: "Связаться с нами",
  description: "Профессиональные клининговые услуги для вашей компании. Мы будем рады проконсультировать вас индивидуально.",
  submit_label: "Отправить сообщение",
  submit_failed: "Не удалось отправить сообщение.",
  select_placeholder: "Пожалуйста, выберите",
  success_title: "Успех!",
  success_message: "Ваше сообщение успешно отправлено. Мы свяжемся с вами в ближайшее время.",
  redirecting: "Страница перезагружается...",
  phone_label: "Телефон",
  submitting: "Отправка...",
  success_sub: "Мы скоро с вами свяжемся.",
  email_label: "Email",
  hours_label: "Часы работы",
  hours_value: "Пн. - Пт.: 08:00 - 17:00",
  phone_number: "",
  email_address: "",
  form: {
    name: "Имя",
    phone: "Телефон",
    email: "Email",
    message: "Сообщение",
    other: "Поле"
  },
  required: {
    name: "Имя обязательно.",
    phone: "Телефон обязателен.",
    email: "Email обязателен.",
    message: "Сообщение обязательно."
  },
  error_generic: "Произошла ошибка. Пожалуйста, попробуйте еще раз."
};
const footer$5 = {
  description: "Мы обеспечиваем немецкую тщательность и надежность в профессиональной уборке и управлении объектами.",
  address: "",
  phone: "",
  phone_raw: "",
  email: "",
  links_title: "Ссылки",
  contact_title: "Контакт",
  link_about: "О нас",
  link_contact: "Контакт",
  link_contact_href: "/contact",
  link_faq: "FAQ",
  link_privacy: "Конфиденциальность",
  link_imprint: "Выходные данные",
  link_privacy_bottom: "Конфиденциальность",
  copyright: "Все права защищены.",
  back_to_top: "Наверх",
  region_label: "Подвал и контактная информация",
  home_aria: "O&I CLEAN - Главная",
  linkedin_aria: "LinkedIn - O&I CLEAN",
  instagram_aria: "Instagram - O&I CLEAN",
  facebook_aria: "Facebook - O&I CLEAN",
  quicklinks_aria: "Быстрые ссылки",
  phone_aria: "Номер телефона",
  email_aria: "Email"
};
const header$5 = {
  topbar_tagline: "Уборка, которой можно доверять — сервис 24/7",
  cta_label: "Записаться на прием",
  cta_href: "/contact",
  hide_topbar: "Скрыть верхнюю панель",
  home_aria: "Главная страница",
  main_nav: "Главная навигация",
  menu_open: "Открыть меню",
  menu_close: "Закрыть меню",
  language: "Язык",
  impressum: "Выходные данные",
  contact_button: "Контакт"
};
const nav$5 = {
  home: "Главная"
};
const staticPage$5 = {
  breadcrumbs_home: "Главная",
  breadcrumbs_page: "Страница",
  empty_content: "Контент будет добавлен в ближайшее время."
};
const offerDock$5 = {
  title: "Предложение",
  subtitle: "Бесплатно и без обязательств",
  button: "Запросить",
  aria_open: "Открыть панель предложений",
  aria_close: "Закрыть панель предложений"
};
const quote_modal$5 = {
  title: "Запросить предложение",
  scrim_aria: "Закрыть окно",
  close_aria: "Закрыть",
  field_name: "Имя",
  field_email: "Email",
  field_phone: "Телефон",
  field_service: "Услуга",
  field_message: "Сообщение",
  service_placeholder: "Пожалуйста, выберите…",
  service_hotel_cleaning: "Уборка отеля",
  service_building_cleaning: "Уборка зданий",
  service_window_cleaning: "Мойка окон",
  service_maintenance_cleaning: "Поддерживающая уборка",
  service_basic_cleaning: "Генеральная уборка",
  service_carpet_cleaning: "Чистка ковров",
  service_other: "Другое",
  service_general: "Общее",
  message_placeholder: "Что мы можем для вас сделать?",
  consent_prefix: "Я согласен на обработку моих данных в соответствии с ",
  consent_link: "политикой конфиденциальности",
  consent_suffix: ".",
  cancel: "Отмена",
  submit: "Запросить",
  sending: "Отправка…",
  error_consent: "Пожалуйста, дайте согласие на обработку данных.",
  error_generic: "Что-то пошло не так. Попробуйте еще раз.",
  subject_prefix: "Запрос КП",
  thank_you_title: "Спасибо!",
  thank_you_text: "Ваш запрос получен, мы свяжемся с вами в ближайшее время.",
  close_button: "Закрыть"
};
const cookies$5 = {
  title: "Настройки файлов cookie",
  message: "Мы используем файлы cookie, чтобы обеспечить вам наилучшее взаимодействие с нашим сайтом.",
  accept_all: "Принять все",
  reject: "Отклонить",
  save: "Сохранить выбор"
};
const required$5 = {
  name: "Имя обязательно.",
  phone: "Телефон обязателен.",
  email: "Email обязателен.",
  message: "Сообщение обязательно."
};
const ru = {
  hero: hero$5,
  services: services$5,
  servicesList: servicesList$5,
  contact: contact$5,
  footer: footer$5,
  header: header$5,
  nav: nav$5,
  staticPage: staticPage$5,
  offerDock: offerDock$5,
  quote_modal: quote_modal$5,
  cookies: cookies$5,
  required: required$5
};
const hero$4 = {
  title: "Twój niezawodny partner w zakwaterowaniu i zarządzaniu obiektami",
  subtitle: "Dzięki ponad 25-letniemu doświadczeniu oferujemy zintegrowane rozwiązania w zakresie sprzątania, konserwacji i zarządzania obiektami",
  button_services: "Odkryj nasze usługi",
  button_contact: "Skontaktuj się z nami"
};
const services$4 = {
  section_title: "Nasza szeroka gama usług",
  section_subtitle: "Zapewniamy kompleksowe rozwiązania dla wszystkich potrzeb Twoich obiektów i budynków – z niemiecką precyzją i jakością.",
  learn_more: "Szczegółowe informacje",
  categories: {
    hotel: {
      title: "Sprzątanie hoteli i housekeeping",
      description: "Od sprzątania pokoi po zmywalnię – nienaganna higiena i wydajne procesy w każdym obszarze Twojego hotelu."
    },
    building: {
      title: "Profesjonalne sprzątanie budynków",
      description: "Biura, powierzchnie handlowe, sprzątanie pobudowlane i specjalistyczne – sprawimy, że Twoje nieruchomości zabłysną."
    },
    renovation: {
      title: "Renowacja, naprawa i konserwacja",
      description: "Malowanie, prace tynkarskie i gipsowo-kartonowe, układanie podłóg oraz drobne naprawy."
    }
  },
  card: {
    aria: "Uzyskaj więcej informacji o {{service}}",
    button: "Szczegóły"
  }
};
const servicesList$4 = {
  title: "Usługi",
  subtitle: "Odkryj nasze kompleksowe usługi dla domu i firmy",
  meta_title: "Nasze Usługi - O&I CLEAN group GmbH",
  meta_description: "Profesjonalne sprzątanie, renowacja i zarządzanie obiektami.",
  loading: "Ładowanie…",
  contact_cta: "Skontaktuj się z nami",
  contact_cta_aria: "Skontaktuj się teraz"
};
const contact$4 = {
  page_title: "Kontakt",
  intro_static_text: "Bezpłatnie i niezobowiązująco –",
  intro_dynamic_text: "odpowiemy tak szybko, jak to możliwe",
  title: "Skontaktuj się z nami",
  description: "Profesjonalne usługi sprzątania dla Twojej firmy. Chętnie udzielimy indywidualnych informacji.",
  submit_label: "Wyślij wiadomość",
  submit_failed: "Nie udało się wysłać wiadomości.",
  select_placeholder: "Proszę wybrać",
  success_title: "Sukces!",
  success_message: "Twoja wiadomość została wysłana pomyślnie. Skontaktujemy się z Tobą wkrótce.",
  redirecting: "Strona przeładowuje się...",
  phone_label: "Telefon",
  submitting: "Wysyłanie...",
  success_sub: "Skontaktujemy się z Tobą wkrótce.",
  email_label: "Email",
  hours_label: "Godziny otwarcia",
  hours_value: "Pn. - Pt.: 08:00 - 17:00",
  phone_number: "",
  email_address: "",
  form: {
    name: "Imię i nazwisko",
    phone: "Telefon",
    email: "Email",
    message: "Wiadomość",
    other: "Pole"
  },
  required: {
    name: "Imię i nazwisko jest wymagane.",
    phone: "Telefon jest wymagany.",
    email: "Email jest wymagany.",
    message: "Wiadomość jest wymagana."
  },
  error_generic: "Wystąpił błąd. Proszę spróbować ponownie."
};
const footer$4 = {
  description: "Wspieramy Cię niemiecką dokładnością i niezawodnością w profesjonalnym sprzątaniu, konserwacji i zarządzaniu obiektami.",
  address: "",
  phone: "",
  phone_raw: "",
  email: "",
  links_title: "Linki",
  contact_title: "Kontakt",
  link_about: "O nas",
  link_contact: "Kontakt",
  link_contact_href: "/contact",
  link_faq: "FAQ",
  link_privacy: "Prywatność",
  link_imprint: "Nota prawna",
  link_privacy_bottom: "Prywatność",
  copyright: "Wszelkie prawa zastrzeżone.",
  back_to_top: "Powrót na górę",
  region_label: "Stopka i informacje kontaktowe",
  home_aria: "O&I CLEAN - Strona główna",
  linkedin_aria: "LinkedIn - O&I CLEAN",
  instagram_aria: "Instagram - O&I CLEAN",
  facebook_aria: "Facebook - O&I CLEAN",
  quicklinks_aria: "Szybkie linki",
  phone_aria: "Numer telefonu",
  email_aria: "Email"
};
const header$4 = {
  topbar_tagline: "Sprzątanie, któremu możesz zaufać — serwis 24/7",
  cta_label: "Umów spotkanie",
  cta_href: "/contact",
  hide_topbar: "Ukryj pasek górny",
  home_aria: "Strona główna",
  main_nav: "Nawigacja główna",
  menu_open: "Otwórz menu",
  menu_close: "Zamknij menu",
  language: "Język",
  impressum: "Nota prawna",
  contact_button: "Kontakt"
};
const nav$4 = {
  home: "Strona główna"
};
const staticPage$4 = {
  breadcrumbs_home: "Strona główna",
  breadcrumbs_page: "Strona",
  empty_content: "Treść zostanie dodana wkrótce."
};
const offerDock$4 = {
  title: "Oferta",
  subtitle: "Bezpłatnie i niezobowiązująco",
  button: "Zapytaj",
  aria_open: "Otwórz panel oferty",
  aria_close: "Zamknij panel oferty"
};
const quote_modal$4 = {
  title: "Zapytaj o ofertę",
  scrim_aria: "Zamknij okno",
  close_aria: "Zamknij",
  field_name: "Imię i nazwisko",
  field_email: "Email",
  field_phone: "Telefon",
  field_service: "Usługa",
  field_message: "Wiadomość",
  service_placeholder: "Proszę wybrać…",
  service_hotel_cleaning: "Sprzątanie hoteli",
  service_building_cleaning: "Sprzątanie budynków",
  service_window_cleaning: "Mycie okien",
  service_maintenance_cleaning: "Sprzątanie konserwacyjne",
  service_basic_cleaning: "Sprzątanie gruntowne",
  service_carpet_cleaning: "Czyszczenie dywanów",
  service_other: "Inne",
  service_general: "Ogólne",
  message_placeholder: "W czym możemy pomóc?",
  consent_prefix: "Wyrażam zgodę na przetwarzanie moich danych zgodnie z ",
  consent_link: "polityką prywatności",
  consent_suffix: ".",
  cancel: "Anuluj",
  submit: "Zapytaj",
  sending: "Wysyłanie…",
  error_consent: "Proszę wyrazić zgodę na przetwarzanie danych.",
  error_generic: "Coś poszło nie tak. Proszę spróbować ponownie.",
  subject_prefix: "Zapytanie ofertowe",
  thank_you_title: "Dziękujemy!",
  thank_you_text: "Twoje zapytanie zostało odebrane, skontaktujemy się z Tobą wkrótce.",
  close_button: "Zamknij"
};
const errors = {
  notFound: {
    "404": "Żądana strona nie została znaleziona.",
    "500": "Wystąpił błąd serwera — spróbuj ponownie później.",
    home: "Powrót do strony głównej",
    contact: "Skontaktuj się z nami",
    reload: "Odśwież stronę"
  }
};
const cookies$4 = {
  title: "Preferencje dotyczące plików cookie",
  message: "Używamy plików cookie, aby zapewnić najlepsze doświadczenia na naszej stronie.",
  accept_all: "Akceptuj wszystko",
  reject: "Odrzuć",
  save: "Zapisz wybór",
  privacy_policy: "Polityka prywatności",
  imprint: "Nota prawna"
};
const required$4 = {
  name: "Imię i nazwisko jest wymagane.",
  phone: "Telefon jest wymagany.",
  email: "Email jest wymagany.",
  message: "Wiadomość jest wymagana."
};
const submit_error$4 = "Błąd! Proszę spróbować ponownie.";
const pl = {
  hero: hero$4,
  services: services$4,
  servicesList: servicesList$4,
  contact: contact$4,
  footer: footer$4,
  header: header$4,
  nav: nav$4,
  staticPage: staticPage$4,
  offerDock: offerDock$4,
  quote_modal: quote_modal$4,
  errors,
  cookies: cookies$4,
  required: required$4,
  submit_error: submit_error$4
};
const hero$3 = {
  title: "Váš spolehlivý partner v ubytování a správě budov",
  subtitle: "S více než 25 lety zkušeností nabízíme integrovaná řešení na míru pro úklid, údržbu a správu budov",
  button_services: "Objevte naše služby",
  button_contact: "Kontaktujte nás"
};
const services$3 = {
  section_title: "Naše široká škála služeb",
  section_subtitle: "Poskytujeme řešení na klíč pro všechny potřeby vašich zařízení a budov – s německou precizností a kvalitou.",
  learn_more: "Podrobné informace",
  categories: {
    hotel: {
      title: "Úklid hotelů a housekeeping",
      description: "Od úklidu pokojů až po umývárnu nádobí – dokonalá hygiena a efektivní procesy v každé části vašeho hotelu."
    },
    building: {
      title: "Profesionální úklid budov",
      description: "Kanceláře, komerční prostory, úklid po stavbě a speciální čištění – rozzáříme vaše nemovitosti."
    },
    renovation: {
      title: "Renovace, opravy a údržba",
      description: "Malování, omítkářské a sádrokartonářské práce, pokládka podlah a drobné opravy."
    }
  },
  card: {
    aria: "Získejte více informací o {{service}}",
    button: "Detaily"
  }
};
const servicesList$3 = {
  title: "Služby",
  subtitle: "Objevte naše komplexní služby pro váš domov i firmu",
  meta_title: "Naše služby - O&I CLEAN group GmbH",
  meta_description: "Profesionální úklid, renovace a správa budov.",
  loading: "Načítání…",
  contact_cta: "Kontaktujte nás",
  contact_cta_aria: "Kontaktujte nás nyní"
};
const contact$3 = {
  page_title: "Kontakt",
  intro_static_text: "Zdarma a nezávazně –",
  intro_dynamic_text: "ozveme se vám co nejdříve",
  title: "Kontaktujte nás",
  description: "Profesionální úklidové služby pro vaši firmu. Rádi vás budeme informovat individuálně.",
  submit_label: "Odeslat zprávu",
  submit_failed: "Zprávu se nepodařilo odeslat.",
  select_placeholder: "Prosím vyberte",
  success_title: "Úspěch!",
  success_message: "Vaše zpráva byla úspěšně odeslána. Brzy vás budeme kontaktovat.",
  redirecting: "Stránka se znovu načítá...",
  phone_label: "Telefon",
  submitting: "Odesílání...",
  success_sub: "Brzy se vám ozveme.",
  email_label: "Email",
  hours_label: "Otevírací doba",
  hours_value: "Po. - Pá.: 08:00 - 17:00",
  phone_number: "",
  email_address: "",
  form: {
    name: "Jméno",
    phone: "Telefon",
    email: "Email",
    message: "Zpráva",
    other: "Pole"
  },
  required: {
    name: "Jméno je povinné.",
    phone: "Telefon je povinný.",
    email: "Email je povinný.",
    message: "Zpráva je povinná."
  },
  error_generic: "Došlo k chybě. Prosím zkuste to znovu."
};
const footer$3 = {
  description: "Stojíme při vás s německou důkladností a spolehlivostí v profesionálním úklidu, údržbě a správě budov.",
  address: "",
  phone: "",
  phone_raw: "",
  email: "",
  links_title: "Odkazy",
  contact_title: "Kontakt",
  link_about: "O nás",
  link_contact: "Kontakt",
  link_contact_href: "/contact",
  link_faq: "FAQ",
  link_privacy: "Soukromí",
  link_imprint: "Impresum",
  link_privacy_bottom: "Soukromí",
  copyright: "Všechna práva vyhrazena.",
  back_to_top: "Zpět nahoru",
  region_label: "Zápatí a kontaktní informace",
  home_aria: "O&I CLEAN - Domovská stránka",
  linkedin_aria: "LinkedIn - O&I CLEAN",
  instagram_aria: "Instagram - O&I CLEAN",
  facebook_aria: "Facebook - O&I CLEAN",
  quicklinks_aria: "Rychlé odkazy",
  phone_aria: "Telefonní číslo",
  email_aria: "Email"
};
const header$3 = {
  topbar_tagline: "Úklid, kterému můžete věřit — servis 24/7",
  cta_label: "Domluvit si schůzku",
  cta_href: "/contact",
  hide_topbar: "Skrýt horní panel",
  home_aria: "Domovská stránka",
  main_nav: "Hlavní navigace",
  menu_open: "Otevřít menu",
  menu_close: "Zavřít menu",
  language: "Jazyk",
  impressum: "Impresum",
  contact_button: "Kontakt"
};
const nav$3 = {
  home: "Domů"
};
const staticPage$3 = {
  breadcrumbs_home: "Domů",
  breadcrumbs_page: "Stránka",
  empty_content: "Obsah bude brzy přidán."
};
const offerDock$3 = {
  title: "Nabídka",
  subtitle: "Zdarma a nezávazně",
  button: "Vyžádat",
  aria_open: "Otevřít panel nabídky",
  aria_close: "Zavřít panel nabídky"
};
const quote_modal$3 = {
  title: "Vyžádat cenovou nabídku",
  scrim_aria: "Zavřít okno",
  close_aria: "Zavřít",
  field_name: "Jméno",
  field_email: "Email",
  field_phone: "Telefon",
  field_service: "Služba",
  field_message: "Zpráva",
  service_placeholder: "Prosím vyberte…",
  service_hotel_cleaning: "Úklid hotelů",
  service_building_cleaning: "Úklid budov",
  service_window_cleaning: "Mytí oken",
  service_maintenance_cleaning: "Běžný úklid",
  service_basic_cleaning: "Generální úklid",
  service_carpet_cleaning: "Čištění koberců",
  service_other: "Jiné",
  service_general: "Obecné",
  message_placeholder: "Co pro vás můžeme udělat?",
  consent_prefix: "Souhlasím se zpracováním mých údajů v souladu se ",
  consent_link: "zásadami ochrany osobních údajů",
  consent_suffix: ".",
  cancel: "Zrušit",
  submit: "Vyžádat",
  sending: "Odesílání…",
  error_consent: "Prosím udělte souhlas se zpracováním údajů.",
  error_generic: "Něco se nepovedlo. Prosím zkuste to znovu.",
  subject_prefix: "Žádost o nabídku",
  thank_you_title: "Děkujeme!",
  thank_you_text: "Vaše žádost byla přijata, brzy vás budeme kontaktovat.",
  close_button: "Zavřít"
};
const cookies$3 = {
  title: "Předvolby souborů cookie",
  message: "Soubory cookie používáme k zajištění nejlepšího zážitku na našem webu.",
  accept_all: "Přijmout vše",
  reject: "Odmítnout",
  save: "Uložit výběr",
  privacy_policy: "Zásady ochrany osobních údajů",
  imprint: "Impresum"
};
const required$3 = {
  name: "Jméno je povinné.",
  phone: "Telefon je povinný.",
  email: "Email je povinný.",
  message: "Zpráva je povinná."
};
const submit_error$3 = "Chyba! Prosím zkuste to znovu.";
const cs = {
  hero: hero$3,
  services: services$3,
  servicesList: servicesList$3,
  contact: contact$3,
  footer: footer$3,
  header: header$3,
  nav: nav$3,
  staticPage: staticPage$3,
  offerDock: offerDock$3,
  quote_modal: quote_modal$3,
  cookies: cookies$3,
  required: required$3,
  submit_error: submit_error$3
};
const hero$2 = {
  title: "Váš spoľahlivý partner v ubytovaní a správe budov",
  subtitle: "S viac ako 25-ročnými skúsenosťami ponúkame integrované riešenia na mieru pre upratovanie, údržbu a správu budov",
  button_services: "Objavte naše služby",
  button_contact: "Kontaktujte nás"
};
const services$2 = {
  section_title: "Naša široká škála služieb",
  section_subtitle: "Poskytujeme riešenia na kľúč pre všetky potreby vašich zariadení a budov – s nemeckou precíznosťou a kvalitou.",
  learn_more: "Podrobné informácie",
  categories: {
    hotel: {
      title: "Upratovanie hotelov a housekeeping",
      description: "Od upratovania izieb až po umyváreň riadu – dokonalá hygiena a efektívne procesy v každej časti vášho hotela."
    },
    building: {
      title: "Profesionálne upratovanie budov",
      description: "Kancelárie, komerčné priestory, upratovanie po stavbe a špeciálne čistenie – rozžiarime vaše nehnuteľnosti."
    },
    renovation: {
      title: "Renovácia, opravy a údržba",
      description: "Maľovanie, omietkárske a sadrokartonárske práce, pokládka podláh a drobné opravy."
    }
  },
  card: {
    aria: "Získajte viac informácií o {{service}}",
    button: "Detaily"
  }
};
const servicesList$2 = {
  title: "Služby",
  subtitle: "Objavte naše komplexné služby pre váš domov aj firmu",
  meta_title: "Naše služby - O&I CLEAN group GmbH",
  meta_description: "Profesionálne upratovanie, renovácia a správa budov.",
  loading: "Načítava sa…",
  contact_cta: "Kontaktujte nás",
  contact_cta_aria: "Kontaktujte nás teraz"
};
const contact$2 = {
  page_title: "Kontakt",
  intro_static_text: "Zadarmo a nezáväzne –",
  intro_dynamic_text: "ozveme sa vám čo najskôr",
  title: "Kontaktujte nás",
  description: "Profesionálne upratovacie služby pre vašu firmu. Radi vás budeme informovať individuálne.",
  submit_label: "Odoslať správu",
  submit_failed: "Správu sa nepodarilo odoslať.",
  select_placeholder: "Prosím vyberte",
  success_title: "Úspech!",
  success_message: "Vaša správa bola úspešne odoslaná. Čoskoro vás budeme kontaktovať.",
  redirecting: "Stránka sa znova načítava...",
  phone_label: "Telefón",
  submitting: "Odosielanie...",
  success_sub: "Čoskoro sa vám ozveme.",
  email_label: "Email",
  hours_label: "Otváracie hodiny",
  hours_value: "Po. - Pia.: 08:00 - 17:00",
  phone_number: "",
  email_address: "",
  form: {
    name: "Meno",
    phone: "Telefón",
    email: "Email",
    message: "Správa",
    other: "Pole"
  },
  required: {
    name: "Meno je povinné.",
    phone: "Telefón je povinný.",
    email: "Email je povinný.",
    message: "Správa je povinná."
  },
  error_generic: "Vyskytla sa chyba. Prosím skúste to znovu."
};
const footer$2 = {
  description: "Stojíme pri vás s nemeckou dôkladnosťou a spoľahlivosťou v profesionálnom upratovaní, údržbe a správe budov.",
  address: "",
  phone: "",
  phone_raw: "",
  email: "",
  links_title: "Odkazy",
  contact_title: "Kontakt",
  link_about: "O nás",
  link_contact: "Kontakt",
  link_contact_href: "/contact",
  link_faq: "FAQ",
  link_privacy: "Súkromie",
  link_imprint: "Impresum",
  link_privacy_bottom: "Súkromie",
  copyright: "Všetky práva vyhradené.",
  back_to_top: "Späť nahor",
  region_label: "Päta a kontaktné informácie",
  home_aria: "O&I CLEAN - Domovská stránka",
  linkedin_aria: "LinkedIn - O&I CLEAN",
  instagram_aria: "Instagram - O&I CLEAN",
  facebook_aria: "Facebook - O&I CLEAN",
  quicklinks_aria: "Rýchle odkazy",
  phone_aria: "Telefónne číslo",
  email_aria: "Email"
};
const header$2 = {
  topbar_tagline: "Upratovanie, ktorému môžete veriť — servis 24/7",
  cta_label: "Dohodnúť si stretnutie",
  cta_href: "/contact",
  hide_topbar: "Skryť horný panel",
  home_aria: "Domovská stránka",
  main_nav: "Hlavná navigácia",
  menu_open: "Otvoriť menu",
  menu_close: "Zatvoriť menu",
  language: "Jazyk",
  impressum: "Impresum",
  contact_button: "Kontakt"
};
const nav$2 = {
  home: "Domov"
};
const staticPage$2 = {
  breadcrumbs_home: "Domov",
  breadcrumbs_page: "Stránka",
  empty_content: "Obsah bude čoskoro pridaný."
};
const offerDock$2 = {
  title: "Ponuka",
  subtitle: "Zadarmo a nezáväzne",
  button: "Vyžiadať",
  aria_open: "Otvoriť panel ponuky",
  aria_close: "Zatvoriť panel ponuky"
};
const quote_modal$2 = {
  title: "Vyžiadať cenovú ponuku",
  scrim_aria: "Zatvoriť okno",
  close_aria: "Zatvoriť",
  field_name: "Meno",
  field_email: "Email",
  field_phone: "Telefón",
  field_service: "Služba",
  field_message: "Správa",
  service_placeholder: "Prosím vyberte…",
  service_hotel_cleaning: "Upratovanie hotelov",
  service_building_cleaning: "Upratovanie budov",
  service_window_cleaning: "Umývanie okien",
  service_maintenance_cleaning: "Bežné upratovanie",
  service_basic_cleaning: "Generálne upratovanie",
  service_carpet_cleaning: "Čistenie kobercov",
  service_other: "Iné",
  service_general: "Všeobecné",
  message_placeholder: "Čo pre vás môžeme urobiť?",
  consent_prefix: "Súhlasím so spracovaním mojich údajov v súlade so ",
  consent_link: "zásadami ochrany osobných údajov",
  consent_suffix: ".",
  cancel: "Zrušiť",
  submit: "Vyžiadať",
  sending: "Odosiela sa…",
  error_consent: "Prosím udeľte súhlas so spracovaním údajov.",
  error_generic: "Niečo sa nepodarilo. Prosím skúste to znovu.",
  subject_prefix: "Žiadosť o ponuku",
  thank_you_title: "Ďakujeme!",
  thank_you_text: "Vaša žiadosť bola prijatá, čoskoro vás budeme kontaktovať.",
  close_button: "Zatvoriť"
};
const cookies$2 = {
  title: "Predvoľby súborov cookie",
  message: "Súbory cookie používame na zabezpečenie najlepšieho zážitku na našom webe.",
  accept_all: "Prijať všetko",
  reject: "Odmietnuť",
  save: "Uložiť výber",
  privacy_policy: "Zásady ochrany osobných údajov",
  imprint: "Impresum"
};
const required$2 = {
  name: "Meno je povinné.",
  phone: "Telefón je povinný.",
  email: "Email je povinný.",
  message: "Správa je povinná."
};
const submit_error$2 = "Chyba! Prosím skúste to znovu.";
const sk = {
  hero: hero$2,
  services: services$2,
  servicesList: servicesList$2,
  contact: contact$2,
  footer: footer$2,
  header: header$2,
  nav: nav$2,
  staticPage: staticPage$2,
  offerDock: offerDock$2,
  quote_modal: quote_modal$2,
  cookies: cookies$2,
  required: required$2,
  submit_error: submit_error$2
};
const hero$1 = {
  title: "Вашият надежден партньор в настаняването и управлението на обекти",
  subtitle: "С над 25 години опит предлагаме индивидуални интегрирани решения за почистване, поддръжка и управление на обекти",
  button_services: "Открийте нашите услуги",
  button_contact: "Свържете се с нас"
};
const services$1 = {
  section_title: "Нашата широка гама от услуги",
  section_subtitle: "Ние предлагаме решения до ключ за всички нужди на вашите съоръжения и сгради – с немска прецизност и качество.",
  learn_more: "Подробна информация",
  categories: {
    hotel: {
      title: "Почистване на хотели и камериерски услуги",
      description: "От почистване на стаи до миялното помещение – безупречна хигиена и ефективни процеси във всяка част на вашия хотел."
    },
    building: {
      title: "Професионално почистване на сгради",
      description: "Офиси, търговски площи, почистване след строителство и специални почиствания – ние правим вашите имоти да блестят."
    },
    renovation: {
      title: "Реновиране, ремонт и поддръжка",
      description: "Боядисване, мазилки и сухо строителство, подови настилки и малки ремонти."
    }
  },
  card: {
    aria: "Получете повече информация за {{service}}",
    button: "Детайли"
  }
};
const servicesList$1 = {
  title: "Услуги",
  subtitle: "Открийте нашите всеобхватни услуги за вашия дом и бизнес",
  meta_title: "Нашите услуги - O&I CLEAN group GmbH",
  meta_description: "Професионално почистване, реновиране и управление на обекти.",
  loading: "Зареждане…",
  contact_cta: "Свържете се с нас",
  contact_cta_aria: "Свържете се сега"
};
const contact$1 = {
  page_title: "Контакт",
  intro_static_text: "Безплатно и необвързващо –",
  intro_dynamic_text: "ще се свържем с вас възможно най-скоро",
  title: "Свържете се с нас",
  description: "Професионални почистващи услуги за вашата компания. Радваме се да ви информираме индивидуално.",
  submit_label: "Изпрати съобщение",
  submit_failed: "Съобщението не можа да бъде изпратено.",
  select_placeholder: "Моля, изберете",
  success_title: "Успех!",
  success_message: "Вашето съобщение беше изпратено успешно. Ще се свържем с вас скоро.",
  redirecting: "Страницата се презарежда...",
  phone_label: "Телефон",
  submitting: "Изпращане...",
  success_sub: "Ще се свържем с вас скоро.",
  email_label: "Имейл",
  hours_label: "Работно време",
  hours_value: "Пон. - Пет.: 08:00 - 17:00",
  phone_number: "",
  email_address: "",
  form: {
    name: "Име",
    phone: "Телефон",
    email: "Имейл",
    message: "Съобщение",
    other: "Поле"
  },
  required: {
    name: "Името е задължително.",
    phone: "Телефонът е задължителен.",
    email: "Имейлът е задължителен.",
    message: "Съобщението е задължително."
  },
  error_generic: "Възникна грешка. Моля, опитайте отново."
};
const footer$1 = {
  description: "Ние сме до вас с немска прецизност и надеждност в професионалното почистване, поддръжка и управление на обекти.",
  address: "",
  phone: "",
  phone_raw: "",
  email: "",
  links_title: "Връзки",
  contact_title: "Контакт",
  link_about: "За нас",
  link_contact: "Контакт",
  link_contact_href: "/contact",
  link_faq: "ЧЗВ",
  link_privacy: "Поверителност",
  link_imprint: "Импресум",
  link_privacy_bottom: "Поверителност",
  copyright: "Всички права запазени.",
  back_to_top: "Обратно в началото",
  region_label: "Долен колонтитул и информация за контакт",
  home_aria: "O&I CLEAN - Начална страница",
  linkedin_aria: "LinkedIn - O&I CLEAN",
  instagram_aria: "Instagram - O&I CLEAN",
  facebook_aria: "Facebook - O&I CLEAN",
  quicklinks_aria: "Бързи връзки",
  phone_aria: "Телефонен номер",
  email_aria: "Имейл"
};
const header$1 = {
  topbar_tagline: "Почистване, на което можете да се доверите — 24/7 обслужване",
  cta_label: "Запишете час",
  cta_href: "/contact",
  hide_topbar: "Скрий горната лента",
  home_aria: "Начална страница",
  main_nav: "Основна навигация",
  menu_open: "Отвори меню",
  menu_close: "Затвори меню",
  language: "Език",
  impressum: "Импресум",
  contact_button: "Контакт"
};
const nav$1 = {
  home: "Начало"
};
const staticPage$1 = {
  breadcrumbs_home: "Начало",
  breadcrumbs_page: "Страница",
  empty_content: "Съдържанието ще бъде добавено скоро."
};
const offerDock$1 = {
  title: "Оферта",
  subtitle: "Безплатно и необвързващо",
  button: "Заяви",
  aria_open: "Отвори панела за оферти",
  aria_close: "Затвори панела за оферти"
};
const quote_modal$1 = {
  title: "Заявете оферта",
  scrim_aria: "Затвори модала",
  close_aria: "Затвори",
  field_name: "Име",
  field_email: "Имейл",
  field_phone: "Телефон",
  field_service: "Услуга",
  field_message: "Съобщение",
  service_placeholder: "Моля, изберете…",
  service_hotel_cleaning: "Почистване на хотели",
  service_building_cleaning: "Почистване на сгради",
  service_window_cleaning: "Почистване на прозорци",
  service_maintenance_cleaning: "Поддържащо почистване",
  service_basic_cleaning: "Основно почистване",
  service_carpet_cleaning: "Почистване на килими",
  service_other: "Друго",
  service_general: "Общи",
  message_placeholder: "Какво можем да направим за вас?",
  consent_prefix: "Съгласен съм с обработката на моите данни в съответствие с ",
  consent_link: "политиката за поверителност",
  consent_suffix: ".",
  cancel: "Отказ",
  submit: "Заяви",
  sending: "Изпращане…",
  error_consent: "Моля, дайте съгласие за обработка на данните.",
  error_generic: "Нещо се обърка. Моля, опитайте отново.",
  subject_prefix: "Запитване за оферта",
  thank_you_title: "Благодарим ви!",
  thank_you_text: "Запитването ви беше получено, ще се свържем с вас скоро.",
  close_button: "Затвори"
};
const cookies$1 = {
  title: "Предпочитания за бисквитки",
  message: "Използваме бисквитки, за да гарантираме, че имате най-доброто изживяване на нашия уебсайт.",
  accept_all: "Приеми всички",
  reject: "Откажи",
  save: "Запази избора",
  privacy_policy: "Политика за поверителност",
  imprint: "Импресум"
};
const required$1 = {
  name: "Името е задължително.",
  phone: "Телефонът е задължителен.",
  email: "Имейлът е задължителен.",
  message: "Съобщението е задължително."
};
const submit_error$1 = "Грешка! Моля, опитайте отново.";
const bg = {
  hero: hero$1,
  services: services$1,
  servicesList: servicesList$1,
  contact: contact$1,
  footer: footer$1,
  header: header$1,
  nav: nav$1,
  staticPage: staticPage$1,
  offerDock: offerDock$1,
  quote_modal: quote_modal$1,
  cookies: cookies$1,
  required: required$1,
  submit_error: submit_error$1
};
const hero = {
  title: "Vaš pouzdan partner u smještaju i upravljanju objektima",
  subtitle: "S više od 25 godina iskustva, nudimo prilagođena integrirana rješenja za čišćenje, održavanje i upravljanje objektima",
  button_services: "Otkrijte naše usluge",
  button_contact: "Kontaktirajte nas"
};
const services = {
  section_title: "Naš široki spektar usluga",
  section_subtitle: "Pružamo rješenja 'ključ u ruke' za sve potrebe vaših objekata i zgrada – uz njemačku preciznost i kvalitetu.",
  learn_more: "Detaljne informacije",
  categories: {
    hotel: {
      title: "Čišćenje hotela i housekeeping",
      description: "Od čišćenja soba do praonice posuđa – besprijekorna higijena i učinkoviti procesi u svakom dijelu vašeg hotela."
    },
    building: {
      title: "Profesionalno čišćenje zgrada",
      description: "Uredi, komercijalni prostori, čišćenje nakon izgradnje i posebna čišćenja – činimo da vaše nekretnine zablistaju."
    },
    renovation: {
      title: "Renoviranje, popravak i održavanje",
      description: "Bojanje, gipsarski radovi, podopolagački radovi i mali popravci."
    }
  },
  card: {
    aria: "Saznajte više o {{service}}",
    button: "Detalji"
  }
};
const servicesList = {
  title: "Usluge",
  subtitle: "Otkrijte naše sveobuhvatne usluge za vaš dom i posao",
  meta_title: "Naše usluge - O&I CLEAN group GmbH",
  meta_description: "Profesionalno čišćenje, renoviranje i upravljanje objektima.",
  loading: "Učitavanje…",
  contact_cta: "Kontaktirajte nas",
  contact_cta_aria: "Kontaktirajte nas odmah"
};
const contact = {
  page_title: "Kontakt",
  intro_static_text: "Besplatno i neobvezujuće –",
  intro_dynamic_text: "javit ćemo vam se u najkraćem mogućem roku",
  title: "Kontaktirajte nas",
  description: "Profesionalne usluge čišćenja za vašu tvrtku. Rado ćemo vas informirati individualno.",
  submit_label: "Pošalji poruku",
  submit_failed: "Poruka se nije mogla poslati.",
  select_placeholder: "Molimo odaberite",
  success_title: "Uspjeh!",
  success_message: "Vaša poruka je uspješno poslana. Kontaktirat ćemo vas uskoro.",
  redirecting: "Stranica se ponovno učitava...",
  phone_label: "Telefon",
  submitting: "Slanje...",
  success_sub: "Javit ćemo vam se uskoro.",
  email_label: "E-pošta",
  hours_label: "Radno vrijeme",
  hours_value: "Pon. - Pet.: 08:00 - 17:00",
  phone_number: "",
  email_address: "",
  form: {
    name: "Ime",
    phone: "Telefon",
    email: "E-pošta",
    message: "Poruka",
    other: "Polje"
  },
  required: {
    name: "Ime je obavezno.",
    phone: "Telefon je obavezan.",
    email: "E-pošta je obavezna.",
    message: "Poruka je obavezna."
  },
  error_generic: "Dogodila se pogreška. Molimo pokušajte ponovno."
};
const footer = {
  description: "Stojimo uz vas s njemačkom temeljitošću i pouzdanošću u profesionalnom čišćenju, održavanju i upravljanju objektima.",
  address: "",
  phone: "",
  phone_raw: "",
  email: "",
  links_title: "Linkovi",
  contact_title: "Kontakt",
  link_about: "O nama",
  link_contact: "Kontakt",
  link_contact_href: "/contact",
  link_faq: "FAQ",
  link_privacy: "Privatnost",
  link_imprint: "Impresum",
  link_privacy_bottom: "Privatnost",
  copyright: "Sva prava pridržana.",
  back_to_top: "Povratak na vrh",
  region_label: "Podnožje i kontakt informacije",
  home_aria: "O&I CLEAN - Početna stranica",
  linkedin_aria: "LinkedIn - O&I CLEAN",
  instagram_aria: "Instagram - O&I CLEAN",
  facebook_aria: "Facebook - O&I CLEAN",
  quicklinks_aria: "Brzi linkovi",
  phone_aria: "Broj telefona",
  email_aria: "E-pošta"
};
const header = {
  topbar_tagline: "Čišćenje kojem možete vjerovati — 24/7 usluga",
  cta_label: "Dogovorite termin",
  cta_href: "/contact",
  hide_topbar: "Sakrij gornju traku",
  home_aria: "Početna stranica",
  main_nav: "Glavna navigacija",
  menu_open: "Otvori izbornik",
  menu_close: "Zatvori izbornik",
  language: "Jezik",
  impressum: "Impresum",
  contact_button: "Kontakt"
};
const nav = {
  home: "Početna"
};
const staticPage = {
  breadcrumbs_home: "Početna",
  breadcrumbs_page: "Stranica",
  empty_content: "Sadržaj će biti dodan uskoro."
};
const offerDock = {
  title: "Ponuda",
  subtitle: "Besplatno i neobvezujuće",
  button: "Zatraži",
  aria_open: "Otvori ploču s ponudama",
  aria_close: "Zatvori ploču s ponudama"
};
const quote_modal = {
  title: "Zatražite ponudu",
  scrim_aria: "Zatvori modal",
  close_aria: "Zatvori",
  field_name: "Ime",
  field_email: "E-pošta",
  field_phone: "Telefon",
  field_service: "Usluga",
  field_message: "Poruka",
  service_placeholder: "Molimo odaberite…",
  service_hotel_cleaning: "Čišćenje hotela",
  service_building_cleaning: "Čišćenje zgrada",
  service_window_cleaning: "Pranje prozora",
  service_maintenance_cleaning: "Redovito održavanje",
  service_basic_cleaning: "Generalno čišćenje",
  service_carpet_cleaning: "Čišćenje tepiha",
  service_other: "Ostalo",
  service_general: "Općenito",
  message_placeholder: "Što možemo učiniti za vas?",
  consent_prefix: "Slažem se s obradom mojih podataka u skladu s ",
  consent_link: "pravilima o privatnosti",
  consent_suffix: ".",
  cancel: "Otkaži",
  submit: "Zatraži",
  sending: "Slanje…",
  error_consent: "Molimo dajte suglasnost za obradu podataka.",
  error_generic: "Nešto je pošlo po zlu. Molimo pokušajte ponovno.",
  subject_prefix: "Zahtjev za ponudu",
  thank_you_title: "Hvala vam!",
  thank_you_text: "Vaš zahtjev je zaprimljen, kontaktirat ćemo vas uskoro.",
  close_button: "Zatvori"
};
const cookies = {
  title: "Postavke kolačića",
  message: "Koristimo kolačiće kako bismo vam osigurali najbolje iskustvo na našoj web stranici.",
  accept_all: "Prihvati sve",
  reject: "Odbij",
  save: "Spremi odabir",
  privacy_policy: "Pravila o privatnosti",
  imprint: "Impresum"
};
const required = {
  name: "Ime je obavezno.",
  phone: "Telefon je obavezan.",
  email: "E-pošta je obavezna.",
  message: "Poruka je obavezna."
};
const submit_error = "Pogreška! Molimo pokušajte ponovno.";
const hr = {
  hero,
  services,
  servicesList,
  contact,
  footer,
  header,
  nav,
  staticPage,
  offerDock,
  quote_modal,
  cookies,
  required,
  submit_error
};
const allSupportedLngs = ["de", "en", "tr", "fr", "es", "it", "pt", "ro", "ru", "pl", "cs", "sk", "bg", "hr"];
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
  const fromStorage = localStorage.getItem("locale") || localStorage.getItem("i18nextLng");
  if (fromStorage) {
    const langCode = fromStorage.toLowerCase().slice(0, 2);
    if (allSupportedLngs.includes(langCode)) {
      return langCode;
    }
  }
  return "de";
};
i18n.use(initReactI18next).init({
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
    hr: { translation: hr }
  },
  lng: detectInitialLng(),
  fallbackLng: "de",
  supportedLngs: allSupportedLngs,
  interpolation: {
    escapeValue: false
  },
  react: {
    useSuspense: false
  }
});
const resolveAppName = (page) => {
  var _a, _b, _c, _d, _e, _f, _g, _h, _i, _j, _k, _l;
  return ((_d = (_c = (_b = (_a = page == null ? void 0 : page.props) == null ? void 0 : _a.global) == null ? void 0 : _b.settings) == null ? void 0 : _c.seo) == null ? void 0 : _d.meta_title) || ((_h = (_g = (_f = (_e = page == null ? void 0 : page.props) == null ? void 0 : _e.global) == null ? void 0 : _f.settings) == null ? void 0 : _g.general) == null ? void 0 : _h.site_name) || ((_l = (_k = (_j = (_i = page == null ? void 0 : page.props) == null ? void 0 : _i.global) == null ? void 0 : _j.settings) == null ? void 0 : _k.branding) == null ? void 0 : _l.site_name) || "Website";
};
createServer(async (page) => {
  const locale = page.props.locale || "de";
  await i18n.changeLanguage(locale);
  return createInertiaApp({
    page,
    render: ReactDOMServer.renderToString,
    title: (title) => title || resolveAppName(page),
    resolve: (name) => resolvePageComponent(
      `./Pages/${name}.jsx`,
      /* @__PURE__ */ Object.assign({ "./Pages/Blog/Index.jsx": () => import("./assets/Index-BCAg9hNE.js"), "./Pages/Blog/Show.jsx": () => import("./assets/Show-Bvme4l1L.js"), "./Pages/Errors/NotFound.jsx": () => import("./assets/NotFound-D1_x1Ky3.js"), "./Pages/Errors/Show.jsx": () => import("./assets/Show-DsKqflBu.js"), "./Pages/Home.jsx": () => import("./assets/Home-DrNp2XSB.js"), "./Pages/Services/Index.jsx": () => import("./assets/Index-cT_3G4yh.js"), "./Pages/Services/Show.jsx": () => import("./assets/Show-SLPAD-kx.js"), "./Pages/StaticPage.jsx": () => import("./assets/StaticPage-Dd4whOiz.js"), "./Pages/kontakt/index.jsx": () => import("./assets/index-PdSxeLYo.js") })
    ),
    setup: ({ App, props }) => /* @__PURE__ */ jsx(I18nextProvider, { i18n, children: /* @__PURE__ */ jsx(ThemeProvider, { initial: "light", children: /* @__PURE__ */ jsx(App, { ...props }) }) })
  });
});
