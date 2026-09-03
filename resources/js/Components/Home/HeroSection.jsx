import React, { useEffect, useState, useRef, memo } from "react";
import { Head, usePage } from "@inertiajs/react";
import SafeHtml from "@/Components/Common/SafeHtml";
import { buildResponsiveImage } from "@/utils/imageOptimizer";
import "../../../css/HeroSection.css";

const API_BASE = (import.meta.env.VITE_API_BASE_URL || "").replace(/\/+$/, "");
const normalizeUrl = (value) => {
    const url = String(value || "").trim();

    if (!url || /^\d+$/.test(url)) return null;
    if (url.startsWith("http")) return url;
    if (url.startsWith("/storage/") && API_BASE) return `${API_BASE}${url}`;
    if (url.startsWith("/")) return url;
    if (!API_BASE) return `/${url.replace(/^\/+/, "")}`;

    return `${API_BASE}/${url.replace(/^\/+/, "")}`;
};

const HeroSection = memo(({ sliders }) => {
    const { props } = usePage();
    const sliderList = sliders?.sliders ?? [];
    const slide = sliderList[0] ?? {};
    const locale = String(props.locale || props.defaultLang || "de")
        .toLowerCase()
        .slice(0, 2);
    const defaultLocale = String(props.defaultLang || "de")
        .toLowerCase()
        .slice(0, 2);
    const servicesHref =
        locale === defaultLocale ? "/#services" : `/${locale}#services`;

    const heroRef = useRef(null);
    const videoRef = useRef(null);

    const [isMobile, setIsMobile] = useState(false);
    const [shouldLoadVideo, setShouldLoadVideo] = useState(false);
    const [showVideo, setShowVideo] = useState(false);
    const [videoLoaded, setVideoLoaded] = useState(false);
    const [isHeroVisible, setIsHeroVisible] = useState(true);

    const videoUrl = normalizeUrl(slide.video_url);
    const posterUrl =
        normalizeUrl(slide.video_poster) || normalizeUrl(slide.image);
    const poster = buildResponsiveImage(posterUrl, {
        widths: [640, 960, 1280, 1600],
        width: 1600,
        quality: 72,
    });
    const imageAlt =
        String(slide.image_alt || slide.title || "Service hero image")
            .replace(/<[^>]+>/g, "")
            .trim() || "Service hero image";

    useEffect(() => {
        if (typeof window === "undefined") return;
        const mobile = window.matchMedia("(max-width: 768px)").matches;
        setIsMobile(mobile);
    }, []);

    useEffect(() => {
        if (!heroRef.current || isMobile || !videoUrl) return;

        const observer = new IntersectionObserver(
            ([entry]) => {
                setIsHeroVisible(entry.isIntersecting);

                if (entry.isIntersecting) {
                    setShouldLoadVideo(true);
                }
            },
            { threshold: 0.05 },
        );

        observer.observe(heroRef.current);

        return () => observer.disconnect();
    }, [isMobile, videoUrl]);

    useEffect(() => {
        if (!shouldLoadVideo) return;

        const t = setTimeout(() => setShowVideo(true), 1500);
        return () => clearTimeout(t);
    }, [shouldLoadVideo]);

    useEffect(() => {
        const video = videoRef.current;

        if (!video || !showVideo) return;

        if (!isHeroVisible || document.hidden) {
            video.pause();
            return;
        }

        video.play().catch(() => {});
    }, [isHeroVisible, showVideo, videoLoaded]);

    useEffect(() => {
        if (!showVideo) return;

        const handleVisibilityChange = () => {
            const video = videoRef.current;
            if (!video) return;

            if (document.hidden || !isHeroVisible) {
                video.pause();
            } else {
                video.play().catch(() => {});
            }
        };

        document.addEventListener("visibilitychange", handleVisibilityChange);
        return () =>
            document.removeEventListener(
                "visibilitychange",
                handleVisibilityChange,
            );
    }, [isHeroVisible, showVideo]);

    const scrollToServices = (event) => {
        const services = document.getElementById("services");

        if (!services) return;

        event.preventDefault();
        services.scrollIntoView({ behavior: "smooth", block: "start" });
        window.history.pushState(null, "", servicesHref);
    };

    return (
        <section ref={heroRef} id="top" className="hero-section">
            {poster.src && (
                <Head>
                    <link
                        rel="preload"
                        as="image"
                        href={poster.src}
                        imageSrcSet={poster.srcSet || undefined}
                        imageSizes="100vw"
                        fetchpriority="high"
                    />
                </Head>
            )}

            {posterUrl && (
                <img
                    src={poster.src}
                    srcSet={poster.srcSet || undefined}
                    sizes="100vw"
                    alt={imageAlt}
                    className="hero-bg hero-bg--image"
                    width="1920"
                    height="1080"
                    loading="eager"
                    fetchpriority="high"
                    decoding="async"
                    style={{ objectFit: "cover" }}
                />
            )}

            {!isMobile && showVideo && videoUrl && (
                <video
                    ref={videoRef}
                    className="hero-bg hero-bg--video"
                    playsInline
                    muted
                    autoPlay
                    loop
                    preload="none"
                    onLoadedData={() => setVideoLoaded(true)}
                    style={{
                        opacity: videoLoaded ? 1 : 0,
                        transition: "opacity .6s ease-in-out",
                    }}
                >
                    <source src={videoUrl} type="video/mp4" />
                </video>
            )}

            <div className="hero-content">
                <h1 className="hero-title">
                    <SafeHtml html={slide.title || ""} inline />
                </h1>

                <p className="hero-subtitle">
                    <SafeHtml html={slide.description || ""} />
                </p>

                {slide.buttonLabel && (
                    <div className="hero-cta-group">
                        <a
                            href={servicesHref}
                            className="hero-cta hero-cta--primary"
                            onClick={scrollToServices}
                        >
                            {slide.buttonLabel}
                        </a>
                    </div>
                )}
            </div>
        </section>
    );
});

export default HeroSection;
