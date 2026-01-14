## Purpose

This short guide helps AI coding assistants get productive quickly in this repository (a small PHP website). It highlights the architecture, common patterns, data flows, and concrete examples to reference while editing.

## Big picture

- This repo hosts a PHP-based website for Sandside Information (sandside.info), a community site for Sandside, Cumbria, UK.

- Authentication is session-based. Pages use `insertPageHeader()` function to display headers. `Site/index.php` redirects to the main block menu page (`/Pages/blockMenuPage.php?pageID=1`).

## Key files & directories (examples)

- `Site/phpCode/` — core PHP utilities organized by function:
    - `includeFunctions.php` — central utility functions (error logging, session management, DB connection).
    - `insertFunctions.php` — page rendering functions like `insertPageSectionOneColumnByRefID` (reads `section_tb`), `insertPageHeader`, `insertPageFooter`, `insertMenuChoiceCard`.
    - `sectionDisplayFunctions.php` — specialized section display styles.
    - `config.php` — database credentials and connection factory (auto-detects local vs. production environment).
    - `validationFunctions.php` — input validation and sanitization.
- `Site/Pages/` — public-facing pages:
    - `sectionsPage.php` — displays multiple sections for a given page ID.
    - `blockMenuPage.php` — main menu page with card-based navigation.
    - `phoneList2025Page.php` — phone directory using `phone_numbers_tb` and `phone_groups_tb`.
- `Site/LoginOrOut/` — authentication handlers (login, logout, registration, password reset).
- `Site/CoursesAndTasks/` — **Main site functionality**: create and manage learning courses (each course has multiple tasks). Users complete courses to build competency.
- `Site/PagesAndSections/` — admin pages to create/edit pages and sections (the building blocks of site content).
- `Site/ImageLibraryPages/` — repository for images used across the site; images are linked in sections via user-friendly HTML-like tags.
- `Site/ResourceLibraryPages/` — manage documents/resources used when creating tasks.
- `Site/UserEditPages/` — admin pages to manage user details (name, password, role, class assignment).
- `Site/TestsAndQuestions/` — create and manage tests to prove competency after course completion.

## Data flows & integration points

- Database: code uses mysqli to query tables such as `section_tb`, `image_library_tb`, `phone_numbers_tb`, `phone_groups_tb`, `course_tb`, `task_tb`, etc. Connection credentials are in `Site/phpCode/config.php` (auto-detects local vs. production). Be cautious with credentials.
- Session storage: **heavily used to reduce database calls**. On page load, session arrays like `$_SESSION['pagesOnSite']`, `$_SESSION['sectionDB']`, `$_SESSION['currentUserID']` are populated once and reused. Check `pageStarterPHP.php` to see where session data is loaded.
- Image handling: images are stored in `uploadedImages/` and `images/`, referenced via `image_library_tb` rows. Images are embedded in section content using custom HTML-like tags (parsed by functions in `insertFunctions.php`).

## Project-specific conventions

- Page includes: most pages now use rendering functions like `insertPageHeader()`, `insertPageFooter()`, `insertPageSectionOneColumn()` from `phpCode/insertFunctions.php`. Some older pages may still use includes from `HTMLpages/` (possibly deprecated). Keep markup consistent with CSS in `styleSheets/`.
- Link types: `insertMenuChoiceCard` uses `linkPageType` values `sectionsPage`, `blockMenu`, `builtInPage` to decide target URL format — search for `insertMenuChoiceCard` to see usage.
- Avoid moving or renaming files between `OldSite/` and `Site/` without updating both; tests and admins may still reference either tree.

## Quick dev notes (how to run & debug)

- No build system. To run locally use PHP's built-in server and a MySQL instance matching credentials (or update credentials in `Site/phpCode/config.php`). Example (PowerShell):

    php -S localhost:8000 -t "d:\Development\SandsideInfo\Site"

- To show PHP errors while running the built-in server:

    php -d display_errors=1 -S localhost:8000 -t "d:\Development\SandsideInfo\Site"

- Database: the code assumes a live MySQL server. If you can't use the hosted DB, set up a local MySQL and update the connection strings in `Site/phpCode/config.php`.

## Editing guidance & examples

- When updating page HTML, prefer editing the rendering functions in `Site/phpCode/insertFunctions.php` to keep markup consistent across pages.
- To add a new Section type, create a new function in `sectionDisplayFunctions.php` and call it from `insertFunctions.php` based on a new `SectionType` value.
- For content stored in DB: use `insertPageSectionOneColumn($contentString, $title, $sectionID)` as the canonical renderer — it expects a `SectionDB` row with `SectionContent`, `SectionTitle`, `SectionColour`, `PageImageIDRef`.
- When changing image logic, review `insertImagefromDBIntoPageSection` and `insertImageStringByRedID` to keep session and DB behaviors consistent.

## Safety & caution

- Credentials are present in source. Do not commit alternative credentials to the repository. Prefer environment-based overrides if adding deployment changes.
- Several DB queries are not parameterized. If you edit query code, prefer prepared statements to avoid SQL injection.

If anything here is unclear or you'd like examples for a specific task (e.g., add a new Section type, change image sourcing, or run locally with a sample DB), tell me which area and I will expand with concrete edits and tests.
