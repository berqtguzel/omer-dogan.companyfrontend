import assert from "node:assert/strict";
import test from "node:test";

const stored = new Map();
const requests = [];
const delays = [];
let clickListener = null;

Object.defineProperty(globalThis, "window", {
    configurable: true,
    value: {
        location: { origin: "https://oi-clean.de", pathname: "/kontakt" },
        setTimeout: (callback, delay) => {
            delays.push(delay);
            queueMicrotask(callback);
        },
        localStorage: {
            getItem: (key) => stored.get(key) ?? null,
            setItem: (key, value) => stored.set(key, String(value)),
        },
    },
});
Object.defineProperty(globalThis, "document", {
    configurable: true,
    value: {
        referrer: "https://example.com/services?campaign=private",
        documentElement: { lang: "de" },
        addEventListener: (type, listener) => {
            if (type === "click") clickListener = listener;
        },
    },
});
Object.defineProperty(globalThis, "navigator", {
    configurable: true,
    value: { language: "de-DE", userAgent: "Test Browser" },
});
Object.defineProperty(globalThis, "fetch", {
    configurable: true,
    value: async (url, options) => {
        requests.push({ url, options });
        return {
            ok: true,
            status: 200,
            json: async () => ({ success: true }),
        };
    },
});

const { initializeButtonTracking, trackButtonClick } = await import(
    "../../resources/js/utils/buttonTracking.js"
);

const details = (buttonKey) => ({
    tenantId: "oi_cleande_690e161c3a1dd",
    buttonKey,
    buttonName: "Kontakt – Nachricht senden",
    action: "submit_contact_form",
    location: "contact_page",
    form: "contact_form",
});

test("sends the backend contract without personal form data", async () => {
    await trackButtonClick(details("contact_form_submit_test"));

    const request = requests.at(-1);
    const payload = JSON.parse(request.options.body);

    assert.equal(request.url, "/api/button-tracking/track");
    assert.ok(delays.at(-1) >= 5000 && delays.at(-1) <= 10000);
    assert.equal(payload.button_key, "contact_form_submit_test");
    assert.equal(payload.metadata.page, "/kontakt");
    assert.equal(payload.metadata.url, "https://oi-clean.de/kontakt");
    assert.equal(payload.metadata.locale, "de");
    assert.equal(payload.metadata.referrer, "https://example.com/services");
    assert.equal(payload.metadata.user_agent, "Test Browser");
    assert.equal(
        payload.metadata.tenant_id,
        "oi_cleande_690e161c3a1dd",
    );
    assert.equal(payload.metadata.button_name, "Kontakt – Nachricht senden");
    assert.match(
        payload.session_id,
        /^s_oi_cleande_690e161c3a1dd_[a-z0-9]+_[a-z0-9]+$/,
    );
    assert.equal(payload.email, undefined);
    assert.equal(payload.phone, undefined);
    assert.equal(payload.message, undefined);
});

test("deduplicates the same visitor and button inside the short click window", async () => {
    const before = requests.length;

    await trackButtonClick(details("dedup_test"));
    const duplicate = await trackButtonClick(details("dedup_test"));

    assert.equal(requests.length - before, 1);
    assert.deepEqual(duplicate, { skipped: true, duplicate: true });
});

test("tracks data attributes through one delegated click listener", async () => {
    initializeButtonTracking({ tenantId: "tenant_delegated" });

    const element = {
        id: "quote-button",
        className: "btn tracked",
        disabled: false,
        tagName: "BUTTON",
        textContent: "Anfordern",
        dataset: {
            trackKey: "home_fixed_anfordern",
            trackName: "Anfordern – Formular öffnen",
            trackLocation: "left_fixed_button",
            trackAction: "open_contact_form",
            trackForm: "anfordern_form",
        },
        closest: (selector) =>
            selector === "[data-track-key]" ? element : null,
    };

    const before = requests.length;
    clickListener({ target: element, clientX: 12, clientY: 24 });
    await new Promise((resolve) => setImmediate(resolve));

    const payload = JSON.parse(requests.at(-1).options.body);
    assert.equal(requests.length - before, 1);
    assert.equal(payload.button_key, "home_fixed_anfordern");
    assert.equal(payload.metadata.button_name, "Anfordern – Formular öffnen");
    assert.equal(payload.metadata.location, "left_fixed_button");
    assert.equal(payload.metadata.action, "open_contact_form");
    assert.equal(payload.metadata.form, "anfordern_form");
    assert.equal(payload.metadata.element_id, "quote-button");
    assert.equal(payload.metadata.element_type, "button");
    assert.equal(payload.metadata.click_x, 12);
    assert.equal(payload.metadata.click_y, 24);
    assert.match(payload.session_id, /^s_tenant_delegated_/);
});
