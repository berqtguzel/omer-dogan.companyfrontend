import React, { useEffect, useRef } from "react";
import { usePage } from "@inertiajs/react";
import AppLayout from "@/Layouts/AppLayout";
import HeroSection from "@/Components/Home/HeroSection";
import ServiceCategories from "@/Components/Home/Services/ServiceCategories";
import ServicesGrid from "@/Components/Home/Services/ServicesGrid";
import HomeFaqSection from "@/Components/Home/Faq/HomeFaqSection";
import ReviewsSection from "@/Components/Home/Reviews/ReviewsSection";
import ContactSection from "@/Components/Home/Contact/ContactSection";
import { renderText, renderUrl } from "@/utils/renderValue";

import SeoHead from "@/Components/SeoHead";
export default function Home({
    content,
    currentRoute,
    settings,
}) {
    const { props } = usePage();
    const homeRef = useRef(null);
    const locale = props.locale || "de";

    const seo = props.settings?.seo || {};

    const siteName = renderText(
        props.settings?.general?.site_name,
        renderText(props.settings?.branding?.site_name, "Reinigungsunternehmen"),
    );
    const title = renderText(seo.meta_title, siteName);
    const description = renderText(seo.meta_description);
    const keywords = renderText(seo.meta_keywords);
    const ogTitle = renderText(seo.og_title, title);
    const ogDesc = renderText(seo.og_description, description);
    const ogImage = renderUrl(seo.og_image);

    const canonicalUrl = props.tenantSeo?.canonicalUrl;
    const canonicalOrigin = props.tenantSeo?.canonicalBaseUrl;

    useEffect(() => {
        const root = homeRef.current;
        if (!root) return undefined;

        const targets = root.querySelectorAll(
            ".svc-title, .services-title, .svc-card, .service-card, .reviews-section, .home-faq-section, .contact-content",
        );

        targets.forEach((target, index) => {
            target.classList.add("home-reveal");
            target.style.setProperty("--reveal-order", String(index % 6));
        });
        root.classList.add("home-motion-ready");

        const observer = new IntersectionObserver(
            (entries) => {
                entries.forEach((entry) => {
                    if (!entry.isIntersecting) return;
                    entry.target.classList.add("is-revealed");
                    observer.unobserve(entry.target);
                });
            },
            { threshold: 0.12, rootMargin: "0px 0px -6%" },
        );

        targets.forEach((target) => observer.observe(target));
        return () => observer.disconnect();
    }, []);

    return (
        <AppLayout content={content} currentRoute={currentRoute}>
            <SeoHead
                title={title}
                description={description}
                keywords={keywords}
                canonical={canonicalUrl}
                image={ogImage}
                ogTitle={ogTitle}
                ogDescription={ogDesc}
                locale={locale}
                origin={canonicalOrigin}
            />

            <div ref={homeRef} className="home-page">
                <HeroSection sliders={props.sliders} />
                <ServiceCategories />

                <ServicesGrid />

                <ReviewsSection content={content} />

                <HomeFaqSection content={content} />

                <ContactSection settings={settings} />
            </div>
        </AppLayout>
    );
}
