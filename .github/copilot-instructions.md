Purpose
-------
This short guide helps AI coding assistants get productive quickly in this repository (a small PHP website). It highlights the architecture, common patterns, data flows, and concrete examples to reference while editing.

Big picture
-----------
- Two parallel site trees exist: `OldSite/` and `Site/`. Work is normally done in `Site/` (mirrors `OldSite` but is the active copy). Look for the same filenames in both when unsure which is current.
- The app is procedural PHP that renders pages by including fragments in `HTMLpages/` and page controllers in `Pages/`.
- Authentication is session-based. `Site/index.php` checks `$_SESSION['Email']` and includes `HTMLpages/headerHTML.php`, `HTMLpages/indexPageHTML.php`, `HTMLpages/footerHTML.php`.

Key files & directories (examples)
---------------------------------
- `Site/phpCode/includeFunctions.php` — central utility functions used across pages (section rendering, image helpers, DB calls). Example functions: `insertPageSectionOneColumnByRefID` (reads `SectionDB`), `insertImagefromDBIntoPageSection` (queries `ImageLibrary`).
- `Site/Pages/` — page controllers that use the helpers and include HTML fragments.
- `Site/HTMLpages/` — reusable HTML fragments for header/footer/index content.
- `Site/LoginOrOut/` — login, authenticate and logout handlers. `Site/index.php` redirects to `LoginOrOut/loginPage.php` when unauthenticated.
- `PhoneList/` — many phone-list XMLs and HTML views; used by phone/contacts features.

Data flows & integration points
------------------------------
- Database: code uses mysqli to query tables such as `SectionDB` and `ImageLibrary` (DB connection strings currently hard-coded in `includeFunctions.php`). Be cautious with credentials.
- Session storage: some functions rely on session data (e.g. `$_SESSION['pagesOnSite']`) rather than always hitting the DB — search for `pagesOnSite` to see where it's populated.
- Image handling: images are stored in `images/` and referenced via `ImageLibrary` rows. Note a likely bug/quirk: `insertImagefromDBIntoPageSection` currently queries `WHERE ImageID = 1` (hard-coded) — check call sites.

Project-specific conventions
---------------------------
- Page includes: pages are composed by including fragments in `HTMLpages/` rather than templating engines. Keep markup consistent with `section`/`section1` and CSS in `css/`.
- Link types: `insertMenuChoiceBlock` uses `linkPageType` values `sectionsPage`, `blockMenu`, `builtInPage` to decide target URL format — search for `insertMenuChoiceBlock` to see usage.
- Avoid moving or renaming files between `OldSite/` and `Site/` without updating both; tests and admins may still reference either tree.

Quick dev notes (how to run & debug)
-----------------------------------
- No build system. To run locally use PHP's built-in server and a MySQL instance matching credentials (or update credentials in `Site/phpCode/includeFunctions.php`). Example (PowerShell):

    php -S localhost:8000 -t "C:\Users\ade\OneDrive\Documents\SandsideInfo\Site"

- To show PHP errors while running the built-in server:

    php -d display_errors=1 -S localhost:8000 -t "C:\Users\ade\OneDrive\Documents\SandsideInfo\Site"

- Database: the code assumes a live MySQL server. If you can't use the hosted DB, set up a local MySQL and update the connection strings in `Site/phpCode/includeFunctions.php`.

Editing guidance & examples
---------------------------
- When updating page HTML, prefer editing `HTMLpages/headerHTML.php` / `footerHTML.php` rather than multiple controllers.
- For content stored in DB: use `insertPageSectionOneColumnByRefID($refID)` as the canonical renderer — it expects a `SectionDB` row with `SectionContent`, `SectionTitle`, `SectionColour`, `PageImageIDRef`.
- When changing image logic, review `insertImagefromDBIntoPageSection` and `insertImageStringByRedID` to keep session and DB behaviors consistent.

Safety & caution
----------------
- Credentials are present in source. Do not commit alternative credentials to the repository. Prefer environment-based overrides if adding deployment changes.
- Several DB queries are not parameterized. If you edit query code, prefer prepared statements to avoid SQL injection.

If anything here is unclear or you'd like examples for a specific task (e.g., add a new Section type, change image sourcing, or run locally with a sample DB), tell me which area and I will expand with concrete edits and tests.
