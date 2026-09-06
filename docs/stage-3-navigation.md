# Stage 3 — application shell and navigation

The application layout now uses `Components/Navigation` and the corporate design
tokens. Header, footer and language picker legacy implementations/styles were
removed after checking their imports. Page-specific content still uses its
existing styles until its scheduled migration.

`NavigationData` and `SiteShellData` prepare the panel menu hierarchy, links,
branding and contact values in Laravel. Executable URL schemes are rejected;
external links retain their destinations and new-tab behavior. Root German URLs
stay unprefixed, while explicitly localized requests keep their locale prefix.
Footer columns follow panel menu groups; no service list is synthesized.

`GlobalSiteDataService` is request scoped and memoizes settings and menus by
tenant/locale. Provider, middleware and home controller reuse these values.
The existing API/cache clients and media mirror still own retrieval. Menu
sharing no longer replaces cached menus with empty arrays during cooldown.
Other global data consolidation remains for stage 7.

The mobile drawer uses a native modal dialog for focus containment and Escape.
It restores trigger focus and body scrolling on close, and closes at the desktop
breakpoint. Desktop disclosures support keyboard activation, Escape and outside
clicks. Both levels of navigation retain parent destination links.

Language choices still come from the panel; UI labels are centralized. Language
switching keeps the existing URL helper. Translated slug lookup is stage 8 work.
No new corporate routes or invented menu destinations are introduced here.

## Validation

- Production build: passed.
- Existing JavaScript tests: 5 passed.
- Targeted PHP regression run: 18 passed, 71 assertions (including 4 new tests).
- Full PHP suite with the existing environment: 105 passed, 30 failed. Baseline
  was 101 passed, 30 failed. Existing failures remain, including missing SQLite
  support in the default invocation and legacy/favicon test failures. Running
  Pest directly with PDO SQLite enabled also exposes absent auth/profile routes;
  adding a local admin/auth system is outside this migration's requirements.
- Route listing: 60 routes. Local HTTP checks: `/`, `/de/`, `/kontakt` return 200;
  an unknown localized URL returns 404 with the shared shell props.
- Headless Chrome: 320, 390, 768, 1024, 1440 px; no horizontal overflow. Drawer
  focus, scroll lock, Escape/focus restoration, desktop disclosure, language/CTA
  navigation, external target, tracking attributes and reduced motion passed.
- Actual local Laravel response: 390, 768, 1440 px and console checks passed.
- A stale `public/hot` marker referenced an unavailable Vite server on port 5173.
  The obsolete generated marker was removed so Laravel uses production assets.

Browser checks use `tests/browser/navigation.mjs`, Node's built-in WebSocket and
a dedicated headless Chrome debugging endpoint. They do not submit live forms.
Run after building:

```text
node tests/browser/navigation.mjs http://127.0.0.1:9333
node tests/browser/navigation.mjs http://127.0.0.1:9333 http://127.0.0.1:8124/
```

Local screenshots are written to `storage/app/stage3-checks` (not public assets).
