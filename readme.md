# 12 Step Meeting List

This plugin is designed to help 12 step programs (AA, NA, Al-Anon, etc) list their meetings. It standardizes addresses, and displays in a list or map.

The best way to install this plugin is via [its home page](https://wordpress.org/plugins/12-step-meeting-list/) in the WordPress Plugin Directory.

## Support

We are switching the venue used for obtaining support for TSML from GitHub Issues to GitHub Discussions. By using Discussions, you are able to be a part of the community and contribute to the development of the plugin! You can ask questions and get answers, share ideas, or open general discussions. You can also showcase your website modifications/improvments.

To create a new support ticket, please navigate to [here](https://github.com/code4recovery/12-step-meeting-list/discussions) and click on `New discussion`. Then select the type of discussion, and complete as much as possible of the information requested. This will help us respond quicker.

Eventually, we will close down public write access to Issues.

## Helping With Development

Do you want to help develop the plugin? We welcome pull requests! To get started:

- Start by forking this repository to your GitHub space
- Create a development copy of your WordPress site
- Delete the plugin from your development WordPress site
- Clone the repository into your `wp-content/plugins` folder with the following commands (substituting your local path). Please note: We are now asking contributors to create branches and pull requests off of our `develop` branch.

```bash
cd /var/www/wordpress-dev/wp-content/plugins
git clone https://github.com/YourGitHubUsername/12-step-meeting-list
cd 12-step-meeting-list
```

- Add an upstream feed, so you can pull in changes from the root repository:

```bash
git remote add upstream https://github.com/code4recovery/12-step-meeting-list.git
```

- You should update your copy from the upstream repository frequently, especially before starting work on a new feature or issuing a pull request. Here is how:

```bash
git fetch --all
git checkout develop
git merge upstream/develop
```

- To create a new feature, first update your copy from the upstream repository, then from the develop branch:

```bash
git checkout -b my-new-feature
```

- Make all of your changes, get it working, test, test, test...

```bash
git commit -am"These is my wonderful new feature!"
git push origin my-new-feature
```

- Last, issue a pull request (PR) to the root repository `develop` branch to be merged.
  - In your browser, go to [your repo](https://github.com/YourGitHubUsername/12-step-meeting-list)
  - Click the "New pull request" button
  - Check the diff provided and make sure everything looks good
  - Click the "Create pull request" button

If you're new to git, you might benefit from [this git tutorial](https://git-scm.com/book/en/v2).

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