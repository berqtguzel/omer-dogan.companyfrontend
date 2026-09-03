import Cookies from "js-cookie";

const QUEUE_KEY = "omr_analytics_queue_v1";
const LAST_FLUSH_KEY = "omr_analytics_last_flush_v1";
const LOCK_KEY = "omr_analytics_flush_lock_v1";
const VISITOR_KEY = "omr_analytics_visitor_v1";
const SESSION_KEY = "omr_analytics_session_v1";
const FLUSH_INTERVAL_MS = 60 * 60 * 1000;
const LOCK_TTL_MS = 2 * 60 * 1000;
const MAX_QUEUE_SIZE = 500;
const ALLOWED_ENDPOINTS = new Set([
    "track",
    "page-timing",
    "track-conversion",
]);

let initialized = false;
let flushTimer = null;
let analyticsConsentActive = false;
let timingTracked = false;

const storage = {
    get(key, fallback = null) {
        try {
            const value = localStorage.getItem(key);
            return value === null ? fallback : value;
        } catch {
            return fallback;
        }
    },
    set(key, value) {
        try {
            localStorage.setItem(key, value);
            return true;
        } catch {
            return false;
        }
    },
    remove(key) {
        try {
            localStorage.removeItem(key);
        } catch {}
    },
};

const makeId = () => {
    if (typeof crypto?.randomUUID === "function") {
        return crypto.randomUUID();
    }

    return `${Date.now()}-${Math.random().toString(36).slice(2)}`;
};

const hasAnalyticsConsent = () => {
    try {
        const consent = JSON.parse(Cookies.get("cookie_consent") || "{}");
        return consent?.analytics === true;
    } catch {
        return false;
    }
};

const readQueue = () => {
    try {
        const queue = JSON.parse(storage.get(QUEUE_KEY, "[]"));
        return Array.isArray(queue) ? queue : [];
    } catch {
        return [];
    }
};

const writeQueue = (queue) =>
    storage.set(QUEUE_KEY, JSON.stringify(queue.slice(-MAX_QUEUE_SIZE)));

const clearAnalyticsStorage = () => {
    storage.remove(QUEUE_KEY);
    storage.remove(LAST_FLUSH_KEY);
    storage.remove(LOCK_KEY);
    storage.remove(VISITOR_KEY);
    storage.remove(SESSION_KEY);
    if (flushTimer) clearTimeout(flushTimer);
    flushTimer = null;
};

const acquireLock = () => {
    const now = Date.now();
    const current = Number(storage.get(LOCK_KEY, "0"));

    if (Number.isFinite(current) && now - current < LOCK_TTL_MS) {
        return false;
    }

    storage.set(LOCK_KEY, String(now));
    return true;
};

const releaseLock = () => storage.remove(LOCK_KEY);

const scheduleFlush = () => {
    if (!hasAnalyticsConsent()) return;
    if (flushTimer) clearTimeout(flushTimer);

    const now = Date.now();
    let lastFlush = Number(storage.get(LAST_FLUSH_KEY, "0"));

    if (!Number.isFinite(lastFlush) || lastFlush <= 0) {
        lastFlush = now;
        storage.set(LAST_FLUSH_KEY, String(lastFlush));
    }

    const wait = Math.max(1000, FLUSH_INTERVAL_MS - (now - lastFlush));
    flushTimer = window.setTimeout(() => void flushAnalytics(), wait);
};

const enqueue = (endpoint, payload) => {
    if (!ALLOWED_ENDPOINTS.has(endpoint) || !hasAnalyticsConsent()) return;

    const queue = readQueue();
    queue.push({
        id: makeId(),
        endpoint,
        created_at: new Date().toISOString(),
        payload,
    });
    writeQueue(queue);
    scheduleFlush();
};

const sendGroup = async (endpoint, events) => {
    const response = await fetch(`/api/analytics/${endpoint}`, {
        method: "POST",
        headers: {
            Accept: "application/json",
            "Content-Type": "application/json",
        },
        body: JSON.stringify({
            batch_id: makeId(),
            visitor_id: storage.get(VISITOR_KEY),
            session_id: storage.get(SESSION_KEY),
            events: events.map(({ id, payload }) => ({ id, payload })),
        }),
        keepalive: true,
    });

    const result = await response.json().catch(() => ({}));

    if (result?.visitor_id) {
        storage.set(VISITOR_KEY, String(result.visitor_id));
    }
    if (result?.session_id) {
        storage.set(SESSION_KEY, String(result.session_id));
    }

    if (result?.drop === true) {
        return events.map((event) => event.id);
    }

    return response.ok && Array.isArray(result?.accepted_ids)
        ? result.accepted_ids.map(String)
        : [];
};

export const flushAnalytics = async () => {
    if (!hasAnalyticsConsent()) {
        clearAnalyticsStorage();
        return;
    }

    const queue = readQueue();
    if (queue.length === 0 || !acquireLock()) {
        scheduleFlush();
        return;
    }

    storage.set(LAST_FLUSH_KEY, String(Date.now()));

    try {
        const groups = queue.reduce((carry, event) => {
            if (!ALLOWED_ENDPOINTS.has(event?.endpoint)) return carry;
            carry[event.endpoint] ||= [];
            carry[event.endpoint].push(event);
            return carry;
        }, {});

        const accepted = new Set();

        for (const [endpoint, events] of Object.entries(groups)) {
            try {
                const ids = await sendGroup(endpoint, events);
                ids.forEach((id) => accepted.add(String(id)));
            } catch {
                // Network/API failures stay in localStorage for the next hourly flush.
            }
        }

        const latestQueue = readQueue();
        writeQueue(
            latestQueue.filter((event) => !accepted.has(String(event.id))),
        );
    } finally {
        releaseLock();
        scheduleFlush();
    }
};

export const trackPageView = () => {
    if (typeof window === "undefined") return;

    window.setTimeout(() => {
        enqueue("track", {
            event_type: "page_view",
            event_name: "page_view",
            page_url: window.location.href,
            url: window.location.href,
            page_title: document.title,
            referrer: document.referrer || null,
            language: document.documentElement.lang || navigator.language,
            occurred_at: new Date().toISOString(),
        });
    }, 0);
};

export const trackConversion = (conversionType, metadata = {}) => {
    if (!conversionType) return;

    enqueue("track-conversion", {
        conversion_type: String(conversionType),
        event_type: "conversion",
        event_name: String(conversionType),
        page_url: window.location.href,
        metadata,
        occurred_at: new Date().toISOString(),
    });
};

const trackPageTiming = () => {
    if (timingTracked) return;

    const navigation = performance.getEntriesByType?.("navigation")?.[0];
    if (!navigation) return;
    timingTracked = true;

    const metric = (value) => Math.max(0, Math.round(Number(value) || 0));

    enqueue("page-timing", {
        page_url: window.location.href,
        load_time: metric(navigation.loadEventEnd - navigation.startTime),
        dom_content_loaded: metric(
            navigation.domContentLoadedEventEnd - navigation.startTime,
        ),
        first_byte_time: metric(
            navigation.responseStart - navigation.requestStart,
        ),
        dns_time: metric(navigation.domainLookupEnd - navigation.domainLookupStart),
        connect_time: metric(navigation.connectEnd - navigation.connectStart),
        response_time: metric(navigation.responseEnd - navigation.responseStart),
        occurred_at: new Date().toISOString(),
    });
};

export const initializeAnalytics = () => {
    if (initialized || typeof window === "undefined") return;
    initialized = true;

    const onConsentSaved = () => {
        const granted = hasAnalyticsConsent();

        if (!granted) {
            analyticsConsentActive = false;
            clearAnalyticsStorage();
            return;
        }

        if (!analyticsConsentActive) {
            analyticsConsentActive = true;
            trackPageView();
            trackPageTiming();
        }

        scheduleFlush();
    };

    const onConversion = (event) => {
        trackConversion(event?.detail?.type, event?.detail?.metadata || {});
    };

    window.addEventListener("cookie-saved", onConsentSaved);
    window.addEventListener("omr:conversion", onConversion);

    analyticsConsentActive = hasAnalyticsConsent();

    if (!analyticsConsentActive) {
        clearAnalyticsStorage();
        return;
    }

    trackPageView();
    scheduleFlush();

    if (document.readyState === "complete") {
        window.setTimeout(trackPageTiming, 0);
    } else {
        window.addEventListener("load", trackPageTiming, { once: true });
    }
};
