const DEDUP_WINDOW_MS = 1000;
const MIN_SEND_DELAY_MS = 5000;
const MAX_SEND_DELAY_MS = 10000;

const recentClicks = new Map();
const pendingClicks = new Map();
let initialized = false;
let activeTenantId = "site";

const storage = {
    get(key) {
        try {
            return window.localStorage.getItem(key);
        } catch {
            return null;
        }
    },
    set(key, value) {
        try {
            window.localStorage.setItem(key, value);
        } catch {}
    },
};

const sessionId = (tenantId) => {
    const key = `bp_session_id_${tenantId}`;
    const existing = storage.get(key);
    if (existing) return existing;

    const created = `s_${tenantId}_${Math.random().toString(36).slice(2)}_${Date.now().toString(36)}`;
    storage.set(key, created);

    return created;
};

const safeReferrer = () => {
    if (!document.referrer) return null;

    try {
        const url = new URL(document.referrer);
        return `${url.origin}${url.pathname}`;
    } catch {
        return null;
    }
};

const locale = () =>
    document.documentElement.lang || navigator.language || null;

export const buildButtonTrackingPayload = ({
    tenantId,
    buttonKey,
    buttonName,
    action,
    location,
    form = null,
    metadata = {},
}) => ({
    button_key: buttonKey,
    session_id: sessionId(tenantId),
    metadata: {
        page: window.location.pathname,
        url: `${window.location.origin}${window.location.pathname}`,
        locale: locale(),
        referrer: safeReferrer(),
        user_agent: navigator.userAgent,
        timestamp: new Date().toISOString(),
        tenant_id: tenantId,
        button_name: buttonName,
        action,
        location,
        form,
        ...metadata,
    },
});

export const trackButtonClick = async (details) => {
    if (typeof window === "undefined") return { skipped: true };

    const payload = buildButtonTrackingPayload(details);
    const dedupKey = `${payload.button_key}:${payload.session_id}`;
    const now = Date.now();
    const lastClick = recentClicks.get(dedupKey) || 0;

    if (
        pendingClicks.has(dedupKey) ||
        now - lastClick < DEDUP_WINDOW_MS
    ) {
        return { skipped: true, duplicate: true };
    }

    recentClicks.set(dedupKey, now);
    const delay =
        MIN_SEND_DELAY_MS +
        Math.floor(Math.random() * (MAX_SEND_DELAY_MS - MIN_SEND_DELAY_MS + 1));

    const pending = new Promise((resolve, reject) => {
        window.setTimeout(async () => {
            try {
                const response = await fetch("/api/button-tracking/track", {
                    method: "POST",
                    headers: {
                        Accept: "application/json",
                        "Content-Type": "application/json",
                    },
                    body: JSON.stringify(payload),
                    keepalive: true,
                });

                const result = await response.json().catch(() => ({}));

                if (!response.ok) {
                    const error = new Error(
                        result?.message ||
                            `Button tracking failed (${response.status})`,
                    );
                    error.status = response.status;
                    throw error;
                }

                resolve(result);
            } catch (error) {
                reject(error);
            } finally {
                pendingClicks.delete(dedupKey);
            }
        }, delay);
    });

    pendingClicks.set(dedupKey, pending);

    return pending;
};

export const trackButtonClickSafely = (details) => {
    void trackButtonClick(details).catch((error) => {
        if (import.meta.env?.DEV) {
            console.warn("Button tracking failed:", error);
        }
    });
};

export const setButtonTrackingTenant = (tenantId) => {
    const normalized = String(tenantId || "").trim();
    activeTenantId = normalized || "site";
};

const trackedElementDetails = (element, event) => ({
    tenantId: activeTenantId,
    buttonKey: element.dataset.trackKey,
    buttonName:
        element.dataset.trackName ||
        element.textContent?.trim() ||
        element.dataset.trackKey,
    action: element.dataset.trackAction || "click",
    location: element.dataset.trackLocation || "unknown",
    form:
        element.dataset.trackForm ||
        element.closest("form")?.getAttribute("name") ||
        element.closest("form")?.id ||
        null,
    metadata: {
        element_id: element.id || null,
        element_class:
            typeof element.className === "string" ? element.className : null,
        element_text: element.textContent?.trim() || null,
        element_type: element.tagName?.toLowerCase() || null,
        click_x: Number.isFinite(event.clientX) ? event.clientX : null,
        click_y: Number.isFinite(event.clientY) ? event.clientY : null,
    },
});

const handleTrackedClick = (event) => {
    if (event.isTrusted === false || !event.target?.closest) return;

    const element = event.target.closest("[data-track-key]");
    if (!element?.dataset.trackKey || element.disabled) return;

    trackButtonClickSafely(trackedElementDetails(element, event));
};

export const initializeButtonTracking = ({ tenantId } = {}) => {
    if (typeof document === "undefined") return;

    setButtonTrackingTenant(tenantId);
    if (initialized) return;

    initialized = true;
    document.addEventListener("click", handleTrackedClick);
};
