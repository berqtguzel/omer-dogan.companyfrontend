export function renderText(value, fallback = "") {
    if (typeof value === "string" || typeof value === "number") {
        return value;
    }

    return fallback;
}

export function renderUrl(value, fallback = "") {
    const raw = value?.url || value?.path || value;

    if (typeof raw === "number") {
        return String(raw);
    }

    if (typeof raw !== "string") {
        return fallback;
    }

    return raw.trim();
}

const canonicalSocialHosts = {
    "facebook.com": "www.facebook.com",
    "m.facebook.com": "www.facebook.com",
    "instagram.com": "www.instagram.com",
    "linkedin.com": "www.linkedin.com",
    "tiktok.com": "www.tiktok.com",
    "twitter.com": "x.com",
    "www.twitter.com": "x.com",
    "www.x.com": "x.com",
    "youtube.com": "www.youtube.com",
};

export function normalizeExternalUrl(value, fallback = "") {
    const raw = renderUrl(value, fallback);
    if (!raw) return fallback;

    const isProtocolRelative = raw.startsWith("//");
    const isHttpUrl = /^https?:\/\//i.test(raw);
    const isBareWebUrl =
        /^(?:www\.)?(?:facebook|instagram|linkedin|tiktok|twitter|x|youtube)\.com(?:[/?#]|$)/i.test(
            raw,
        );

    if (!isProtocolRelative && !isHttpUrl && !isBareWebUrl) {
        return raw;
    }

    try {
        const url = new URL(
            isProtocolRelative
                ? `https:${raw}`
                : isHttpUrl
                  ? raw
                  : `https://${raw}`,
        );
        const hostname = url.hostname.toLowerCase();

        url.protocol = "https:";
        url.hostname = canonicalSocialHosts[hostname] || hostname;

        return url.toString();
    } catch {
        return raw;
    }
}
