#!/usr/bin/env node

/**
 * Dev script to bootstrap local docker wordpress
 * This replaces the default WP hello world page with a new page
 * that includes the 12 step plugin. It also seeds the database
 * with meetings from the repo's meeting template on first run.
 */
const { execSync } = require("child_process");

const sh = (cmd, opts = {}) =>
  execSync(cmd, { encoding: "utf8", stdio: "pipe", ...opts }).trim();
const wp = (args) => sh(`wp-env run cli -- wp ${args}`);

function ensureFrontPage() {
  // Full-width Gutenberg wrapper around the shortcode.
  const CONTENT =
    '<!-- wp:group {"align":"full","layout":{"inherit":false}} -->' +
      '<div class="wp-block-group alignfull">' +
        '<!-- wp:shortcode -->[tsml_ui]<!-- /wp:shortcode -->' +
      '</div>' +
    '<!-- /wp:group -->';

  // Create or reuse a 'Meetings' page that renders the UI full-width.
  let pageId = "";
  try {
    pageId = wp(`post list --post_type=page --name=meetings --field=ID`);
  } catch {}

  if (!pageId) {
    pageId = wp(
      `post create --post_type=page --post_status=publish ` +
      `--post_title='Meetings' --post_name='meetings' ` +
      `--post_content='${CONTENT}' --porcelain`
    );
    console.log(`Created Meetings page: ${pageId}`);
  } else {
    wp(`post update ${pageId} --post_content='${CONTENT}' --post_status=publish`);
    console.log(`Ensured full-width [tsml_ui] on page ID ${pageId}`);
  }

  // Set as static front page
  wp(`option update show_on_front page`);
  wp(`option update page_on_front ${pageId}`);
  console.log(`Front page set to page ID ${pageId}`);
}

function seedIfEmpty() {
  // Idempotent: the CLI defaults to --if-empty.
  try {
    sh(`wp-env run cli -- wp tsml-dev import`, { stdio: "inherit" });
  } catch (e) {
    console.log(
      "Import attempt failed. Manual fallback:\n" +
      "  Admin → Meetings → Import & Settings → Import → choose template.csv"
    );
  }
}

try {
  ensureFrontPage();
  seedIfEmpty();
  console.log("✅ Ready: http://localhost:8888/  (admin / password)");
} catch (e) {
  console.error(`Bootstrap error: ${e.message}`);
  process.exit(0); // don't block wp-env
}
