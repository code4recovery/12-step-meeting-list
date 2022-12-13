# 12 Step Meeting List

This plugin is designed to help 12 step programs (AA, NA, Al-Anon, etc) list their meetings. It standardizes addresses, and displays in a list or map.

The best way to install this plugin is via [its home page](https://wordpress.org/plugins/12-step-meeting-list/) in the WordPress Plugin Directory.

## Support

Have a question? Check out our [Frequently Asked Questions](https://wordpress.org/plugins/12-step-meeting-list/).

Need help? Please [open a new discussion](https://github.com/code4recovery/12-step-meeting-list/discussions).

## Helping With Development

Do you want to help develop the plugin? We welcome new members! Please find out more at [code4recovery.org](https://code4recovery.org).

## Coding Suggestions

These help improve code readability and maintainability:

- Use extensions like [PHP Intelephense](https://marketplace.visualstudio.com/items?itemName=bmewburn.vscode-intelephense-client) and
  [Prettier](https://marketplace.visualstudio.com/items?itemName=esbenp.prettier-vscode) to format code on save
- Use the [Query Monitor WordPress plugin](https://wordpress.org/plugins/query-monitor/) locally to detect and fix any PHP warnings
- All constants, global functions, and global variables should have a name starting with `tsml_`
- Functions ought to be useful in multiple places (except functions that are available to end users such as `tsml_custom_types`)
- Use anonymous functions when possible (we are PHP 5.6+)
- Use bracket syntax for arrays (we are PHP 5.6+)

Also some best practices:

- Don't leave code commented out (if it's needed later we can find it in the git history)
- Don't put database updates or other expensive operations inside a repeat loop
- No unused variables
- Filter inputs

## Compiling Assets

If you're making changes to javascript or CSS, you will want to install SASS and webpack one time by running `npm i`. Then, while developing,
run `npx mix watch` to compile assets as you make changes. When you are ready to make a pull request, run `npx mix --production`.
