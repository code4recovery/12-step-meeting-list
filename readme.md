# 12 Step Meeting List

This plugin is designed to help recovery programs (AA, NA, Al-Anon, etc) list their meetings. It standardizes addresses, and displays in a list or map.

The best way to install this plugin is via [its home page](https://wordpress.org/plugins/12-step-meeting-list/) in the WordPress Plugin Directory.

## Support

Have a question? Check out our [Frequently Asked Questions](https://wordpress.org/plugins/12-step-meeting-list/#faq-header).

Need help? Please [open a new discussion](https://github.com/code4recovery/12-step-meeting-list/discussions).

## How can I report security bugs?

To report a security issue, please use the [Security Tab](https://github.com/code4recovery/12-step-meeting-list/security), located under the repository name. If you cannot see the "Security" tab, select the ... dropdown menu, and then click Security. Please include as much information as possible, including steps to help our team recreate the issue.

## Helping with Development

Do you want to help develop the plugin? We welcome new members! Please find out more at [code4recovery.org](https://code4recovery.org).

## Coding Suggestions

These help improve code readability and maintainability:

- Use extensions like [DevSense](https://www.devsense.com) and [Prettier](https://prettier.io/) to format code on save
- Use the [Query Monitor WordPress plugin](https://wordpress.org/plugins/query-monitor/) locally to detect and fix any PHP warnings
- All constants, global functions, and global variables should have a name starting with `tsml_`
- Functions ought to be useful in multiple places (except functions that are available to end users such as `tsml_custom_types`)
- Use anonymous functions when possible (we are PHP 5.6+)
- Use bracket syntax for arrays (we are PHP 5.6+)
- We are [PSR-12 compliant](https://www.php-fig.org/psr/psr-12/)

Also some best practices:

- Don't leave code commented out (if it's needed later we can find it in the git history)
- Don't put database updates or other expensive operations inside a repeat loop
- No unused variables
- Filter inputs

## Compiling Assets

If you're making changes to JavaScript or CSS, you will want to install SASS and webpack one time by running `npm i`. Then, while developing,
run `npx mix watch` to compile assets as you make changes. When you are ready to make a pull request, run `npx mix --production`.

## Rebuilding the POT file

To support other languages, the plugin wraps output language with:

```php
echo __('English message', '12-step-meeting-list')
```

To update the `./languages/12-step-meeting-list.pot` file, install [WP Cli](https://make.wordpress.org/cli/handbook/guides/installing/) and run:

```bash
wp i18n make-pot . ./languages/12-step-meeting-list.pot --exclude=assets/
```

## Local Development (Dockerized with [`wp-env`](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/))

**Prereqs:** [Docker Desktop](https://www.docker.com/products/docker-desktop/), [Node.js 18+](https://nodejs.org/en), [Git](https://git-scm.com/)

> Note: the repository already defines `start` for asset watching (`npx mix watch`). To avoid conflicts, use the `docker:*` commands below for the Dockerized WordPress environment.

### First-time (environment)
```bash
npm install -g @wordpress/env
npm install
npm run docker:start
```
On first run this will:
- create a **Meetings** page with `[tsml_ui]` and set it as the front page
- import demo data from [**template.csv**](https://github.com/code4recovery/12-step-meeting-list/blob/main/template.csv) only if no meetings exist

### Daily development
```bash
npm run docker:start   # bring up Dockerized WordPress (idempotent)
npx mix watch          # existing dev script for assets
```

### Optional
```bash
npm run docker:reseed  # force a re-import (dev only)
npm run docker:stop    # stop containers (keep data)
npm run docker:down    # destroy containers + volumes
```

### Notes
- **Query Monitor** is auto-installed inside the dev container