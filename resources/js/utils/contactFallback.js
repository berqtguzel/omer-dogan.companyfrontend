export const DEFAULT_CONTACT_INFO = {
    id: "default-office",
    title: "",
    phone: "",
    email: "",
    address: "",
    postal_code: "",
    city: "",
    country: "",
    formatted_address: "",
};

export function contactInfosFromSettings(settings = {}) {
    const candidates = [
        settings?.contact?.contact_infos,
        settings?.contact_infos,
    ];
    const infos = candidates.find(
        (items) => Array.isArray(items) && items.length > 0,
    );

    return infos || [];
}
