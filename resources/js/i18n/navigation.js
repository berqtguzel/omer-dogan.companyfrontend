const keys = ['contact', 'menu', 'close', 'navigation', 'language', 'skip', 'company', 'home', 'newTab'];
const labels = {
    de: ['Kontakt aufnehmen', 'Menü öffnen', 'Menü schließen', 'Hauptnavigation', 'Sprache auswählen', 'Zum Inhalt springen', 'Unternehmensgruppe', 'Startseite', 'Öffnet in einem neuen Tab'],
    en: ['Get in touch', 'Open menu', 'Close menu', 'Main navigation', 'Select language', 'Skip to content', 'Company group', 'Home', 'Opens in a new tab'],
    tr: ['İletişime geçin', 'Menüyü aç', 'Menüyü kapat', 'Ana menü', 'Dil seçin', 'İçeriğe geç', 'Şirketler grubu', 'Ana sayfa', 'Yeni sekmede açılır'],
    fr: ['Contactez-nous', 'Ouvrir le menu', 'Fermer le menu', 'Navigation principale', 'Choisir la langue', 'Aller au contenu', 'Groupe d’entreprises', 'Accueil', 'S’ouvre dans un nouvel onglet'],
    es: ['Contactar', 'Abrir menú', 'Cerrar menú', 'Navegación principal', 'Seleccionar idioma', 'Saltar al contenido', 'Grupo empresarial', 'Inicio', 'Se abre en una nueva pestaña'],
    it: ['Contattaci', 'Apri menu', 'Chiudi menu', 'Navigazione principale', 'Seleziona lingua', 'Vai al contenuto', 'Gruppo aziendale', 'Home', 'Si apre in una nuova scheda'],
    pt: ['Entre em contacto', 'Abrir menu', 'Fechar menu', 'Navegação principal', 'Selecionar idioma', 'Ir para o conteúdo', 'Grupo empresarial', 'Início', 'Abre num novo separador'],
    ro: ['Contactați-ne', 'Deschide meniul', 'Închide meniul', 'Navigare principală', 'Selectează limba', 'Salt la conținut', 'Grup de companii', 'Acasă', 'Se deschide într-o filă nouă'],
    ru: ['Связаться с нами', 'Открыть меню', 'Закрыть меню', 'Основная навигация', 'Выбрать язык', 'Перейти к содержимому', 'Группа компаний', 'Главная', 'Открывается в новой вкладке'],
    pl: ['Skontaktuj się', 'Otwórz menu', 'Zamknij menu', 'Nawigacja główna', 'Wybierz język', 'Przejdź do treści', 'Grupa przedsiębiorstw', 'Strona główna', 'Otwiera się w nowej karcie'],
    cs: ['Kontaktujte nás', 'Otevřít nabídku', 'Zavřít nabídku', 'Hlavní navigace', 'Vybrat jazyk', 'Přejít na obsah', 'Skupina společností', 'Úvod', 'Otevře se na nové kartě'],
    sk: ['Kontaktujte nás', 'Otvoriť ponuku', 'Zavrieť ponuku', 'Hlavná navigácia', 'Vybrať jazyk', 'Prejsť na obsah', 'Skupina spoločností', 'Úvod', 'Otvorí sa na novej karte'],
    bg: ['Свържете се с нас', 'Отвори менюто', 'Затвори менюто', 'Основна навигация', 'Избери език', 'Към съдържанието', 'Група компании', 'Начало', 'Отваря се в нов раздел'],
    hr: ['Kontaktirajte nas', 'Otvori izbornik', 'Zatvori izbornik', 'Glavna navigacija', 'Odaberi jezik', 'Prijeđi na sadržaj', 'Grupa poduzeća', 'Početna', 'Otvara se u novoj kartici'],
};

export function navigationLabels(locale) {
    const values = labels[locale] || labels.de;
    return Object.fromEntries(keys.map((key, index) => [key, values[index]]));
}
