import React from "react";
import { Link, usePage } from "@inertiajs/react";
import AppLayout from "@/Layouts/AppLayout";
import SeoHead from "@/Components/SeoHead";
import "../../../css/error-page.css";

const STATUS_MESSAGES = {
    403: {
        title: "Zugriff verweigert",
        message: "Sie haben keine Berechtigung, diese Seite aufzurufen.",
    },
    404: {
        title: "Seite nicht gefunden",
        message: "Die angeforderte Seite konnte nicht gefunden werden.",
    },
    500: {
        title: "Serverfehler",
        message: "Es ist ein unerwarteter Fehler aufgetreten.",
    },
    503: {
        title: "Service nicht verfügbar",
        message: "Der Service ist momentan nicht verfügbar. Bitte versuchen Sie es später erneut.",
    },
};

const cleanText = (value) => {
    if (value === null || value === undefined) return "";
    if (typeof value === "object") return "";
    return String(value).replace(/<[^>]*>/g, "").trim();
};

export default function ErrorShow() {
    const { props } = usePage();
    const status = Number(props.status || props.error?.status || 500);
    const fallback = STATUS_MESSAGES[status] || STATUS_MESSAGES[500];

    const title = cleanText(props.title || props.error?.title) || fallback.title;
    const message =
        cleanText(props.message || props.error?.message || props.error) ||
        fallback.message;
    const locale = props.locale || "de";

    return (
        <AppLayout>
            <SeoHead title={`${status} - ${title}`} description={message} noindex />

            <section className="error-page">
                <div className="error-container">
                    <h1 className="error-status">{status}</h1>
                    <h2 className="error-title">{title}</h2>
                    <p className="error-message">{message}</p>

                    <div className="error-actions">
                        <Link href={`/${locale}`} className="error-btn primary">
                            Zur Startseite
                        </Link>
                        <Link
                            href={`/${locale}/kontakt`}
                            className="error-btn secondary"
                        >
                            Kontakt aufnehmen
                        </Link>
                    </div>
                </div>
            </section>
        </AppLayout>
    );
}
