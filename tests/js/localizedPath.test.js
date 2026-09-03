import test from "node:test";
import assert from "node:assert/strict";
import { buildLocalizedPath } from "../../resources/js/utils/localizedPath.js";

test("replaces every leading locale segment with the selected locale", () => {
    assert.equal(buildLocalizedPath("/pl", "ru"), "/ru/");
    assert.equal(buildLocalizedPath("/es/cs/kontakt", "sk"), "/sk/kontakt");
    assert.equal(buildLocalizedPath("/de/standorte", "tr"), "/tr/standorte");
});

test("uses the public cs locale for the Czech alias", () => {
    assert.equal(buildLocalizedPath("/de", "cz"), "/cs/");
});
