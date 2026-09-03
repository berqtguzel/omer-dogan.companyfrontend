import React from "react";
import { useTranslation } from "react-i18next";
import AppLayout from "@/Layouts/AppLayout";
import ContactSection from "@/Components/Home/Contact/ContactSection";
import SeoHead from "@/Components/SeoHead";

export default function ContactIndex({ currentRoute = "kontakt" }) {
    const { t } = useTranslation();

    return (
        <AppLayout currentRoute={currentRoute}>
            <SeoHead title={t("contact.title", "Kontakt")} />

            <main className="contactx-page-wrapper">
                <section className="contactx-intro contactx-page-intro">
                    <div className="contactx-intro__inner">
                        <span className="contactx-eyebrow">
                            {t("contact.eyebrow", "Direkter Kontakt")}
                        </span>
                        <h1 className="contactx-title">
                            {t("contact.title", "Kontakt")}
                        </h1>
                        <p>
                            {t(
                                "contact.page_intro",
                                "Erzählen Sie uns kurz, wobei wir Sie unterstützen dürfen. Wir melden uns persönlich bei Ihnen.",
                            )}
                        </p>
                    </div>
                </section>

                <ContactSection trackContactButton />
            </main>
        </AppLayout>
    );
}
