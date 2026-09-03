import React from "react";
import { FaWhatsapp } from "react-icons/fa";
import { usePage } from "@inertiajs/react";

const FALLBACK_WHATSAPP_LOGO_URL = "/images/logo/WhatsApp-80.webp";

export default function WhatsAppWidget({ data = [] }) {
    const { props } = usePage();
    const widgets = props.global?.widgets ?? {};

    const whatsappItems =
        Array.isArray(data) && data.length > 0
            ? data
            : Array.isArray(widgets.whatsapp)
              ? widgets.whatsapp
              : [];
    const config = whatsappItems[0];

    if (!config || !config.is_active) return null;

    const phone = String(config.phone_number ?? "").replace(/\D/g, "");
    if (phone.length < 6) return null;

    const message = config.default_message || config.welcome_text || "Hello!";
    const position =
        config.button_position === "bottom-left" ? "left" : "right";
    const buttonColor = config.button_color || "#25D366";
    const textColor = config.button_text_color || "#FFFFFF";
    const logoUrl = config.logo_url || FALLBACK_WHATSAPP_LOGO_URL;

    const whatsappUrl = `https://wa.me/${phone}?text=${encodeURIComponent(message)}`;

    return (
        <a
            href={whatsappUrl}
            target="_blank"
            rel="noopener noreferrer"
            aria-label="WhatsApp Chat"
            data-track-key="whatsapp_contact_click"
            data-track-name="WhatsApp – Kontakt starten"
            data-track-location="floating_whatsapp_widget"
            data-track-action="open_whatsapp"
            className={`
                fixed bottom-4 z-50 w-14 h-14
                flex items-center justify-center
                rounded-full shadow-lg cursor-pointer
                hover:scale-110 hover:shadow-2xl
                transition-transform
                ${position === "left" ? "left-4" : "right-4"}
            `}
            style={{ backgroundColor: buttonColor, color: textColor }}
            title={config.welcome_text || "WhatsApp"}
        >
            {logoUrl ? (
                <img
                    src={logoUrl}
                    className="w-10 h-10 object-contain rounded-full"
                    alt="WhatsApp contact"
                    width={40}
                    height={40}
                    loading="lazy"
                    decoding="async"
                    onError={(e) => {
                        if (
                            e.currentTarget.src.includes(
                                FALLBACK_WHATSAPP_LOGO_URL,
                            )
                        ) {
                            e.currentTarget.style.display = "none";
                            return;
                        }

                        e.currentTarget.src = FALLBACK_WHATSAPP_LOGO_URL;
                    }}
                />
            ) : (
                <FaWhatsapp size={32} />
            )}
        </a>
    );
}
