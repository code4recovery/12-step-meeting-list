# Copilot Instructions for 12 Step Meeting List

## Project Overview
- **Purpose:** WordPress plugin for listing and managing 12-step recovery meetings (AA, NA, Al-Anon, etc.), supporting both legacy and modern UI modes.
- **Architecture:**
  - Core logic in `includes/` (functions, REST API, admin, UI switching)
  - Templates in `templates/` for meeting/location archive and detail pages
  - Assets (JS, CSS, images, fonts) in `assets/`
  - Block editor integration via `includes/blocks.php` and `assets/build/blocks/`
  - Entry point: `12-step-meeting-list.php` (defines constants, loads modules)

## Key Patterns & Conventions
- **Global Namespace:** All global functions, constants, and variables start with `tsml_` (e.g., `tsml_get_meetings`, `TSML_VERSION`).
- **UI Mode:**
  - Controlled by `$tsml_user_interface` global (`tsml_ui` for modern, else legacy)
  - Template selection logic in `includes/init.php` (see `archive_template` and `single_template` filters)
- **REST API:**
  - Custom endpoint: `/wp-json/tsml/meetings` (see `includes/rest.php`)
  - Sharing controlled by `$tsml_sharing` and `$tsml_sharing_keys`
- **Blocks:**
  - Registered via `includes/blocks.php` and `includes/blocks/class-tsml-blocks.php`
  - Block config in `assets/build/blocks/block.json`
- **Asset Compilation:**
  - Use `npm i` to install dependencies
  - Use `npx mix watch` for development, `npx mix --production` for production builds
  - Webpack config: `webpack.mix.js`, source files in `assets/src/`
- **Internationalization:**
  - Text wrapped with `__()` and `esc_html_e()`
  - Update POT file: `wp i18n make-pot . ./languages/12-step-meeting-list.pot --exclude=assets/`
- **Custom Types:**
  - Extend meeting types via `tsml_custom_types()` in theme's `functions.php`

## Developer Workflows
- **Build:**
  - `npm i` (once)
  - `npx mix watch` (dev) or `npx mix --production` (release)
- **Block Development:**
  - Use `@wordpress/scripts` for block JS/CSS (`npm run build:wp`)
- **Debugging:**
  - Use Query Monitor plugin for PHP warnings
  - All plugin logic is PSR-12 compliant, uses bracket array syntax
- **Testing:**
  - No formal test suite; manual testing via WordPress admin and REST endpoints

## Integration Points
- **External:**
  - Geocoding via `TSML_GEOCODING_URL`
  - Meeting Guide app notification via `TSML_MEETING_GUIDE_APP_NOTIFY`
- **Theme Overrides:**
  - Custom templates supported (e.g., `archive-meetings.php`, `single-meetings.php` in theme directory)

## Examples
- **Template Selection:**
  ```php
  // in includes/init.php
  if (is_post_type_archive('tsml_meeting')) {
      if ($tsml_user_interface == 'tsml_ui') {
          return .../archive-tsml-ui.php;
      } else {
          // legacy
          ...
      }
  }
  ```
- **REST API Usage:**
  ```php
  // GET /wp-json/tsml/meetings
  ```
- **Asset Build:**
  ```bash
  npm i
  npx mix watch
  npx mix --production
  ```

---

If any section is unclear or missing, please provide feedback so this guide can be improved for future AI agents.
