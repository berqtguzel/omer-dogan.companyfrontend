import React from "react";
import { usePage } from "@inertiajs/react";
import AppLayout from "@/Layouts/AppLayout";
import ServicesGrid from "@/Components/Home/Services/ServicesGrid";
import SeoHead from "@/Components/SeoHead";
import { buildPageSeo } from "@/utils/pageSeo";

export default function ServicesIndex() {
    const { page, locale = "de", tenantSeo } = usePage().props;
    const seo = buildPageSeo(page, locale, {
        title: "Reinigungsleistungen",
        canonical: tenantSeo?.canonicalUrl,
    });

    return (
        <AppLayout>
            <SeoHead
                title={seo.title}
                description={seo.description}
                keywords={seo.keywords}
                ogTitle={seo.ogTitle}
                ogDescription={seo.ogDescription}
                image={seo.image}
                canonical={tenantSeo?.canonicalUrl}
                locale={seo.locale}
                origin={tenantSeo?.canonicalBaseUrl}
            />

            <div className="services-index-page">
                <ServicesGrid />
            </div>
        </AppLayout>
    );
}
