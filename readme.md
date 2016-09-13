12 Step Meeting List
====================

This plugin is designed to help 12 step programs list their meetings and locations. It standardizes addresses, and displays in a list or map.

The best way to install this plugin is via [its home page](https://wordpress.org/plugins/12-step-meeting-list/) in the WordPress Plugin Directory.

## Helping With Development

Are you looking to get involved with helping develop the plugin? We welcome pull requests! To get started:

* Start by forking this repository to your GitHub space
* Create a development copy of your WordPress site; please, do NOT develop against your production site or data!
* Delete the plugin from your development WordPress site.
* Clone the repository into your `wp-content/plugins` folder with the following commands (substituting your local path):

```
cd /var/www/wordpress-dev/wp-content/plugins
git clone https://github.com/YourGitHubUsername/12-step-meeting-list
cd 12-step-meeting-list
```

* Add an upstream feed, so you can pull in changes from the root repository:

```
git remote add upstream https://github.com/meeting-guide/12-step-meeting-list.git
````

* You should update your copy from the upstream repository frequently, especially before starting work on a new feature or issuing a pull request. Here is how:

```
git fetch --all
git checkout master
git merge upstream/master
```

* To create a new feature, first update your copy from the upstream repository, then from the master branch:

```
git checkout -b my-new-feature
```

* Make all of your changes, get it working, test, test, test...

```
git commit -am"These is my wonderful new feature!"
git push origin my-new-feature
```

* Last, issue a pull request (PR) to the root repository to be merged.
    * In your browser, go to: https://github.com/meeting-guide/12-step-meeting-list
    * Click the "New pull request" button
    * Click "Compare across forks"
    * Under the drop down, make the "Head fork" your copy of the repository, and under the "compare" drop down, select "my-new-feature"
    * Check the diff provided and make sure everything looks good, then open the Pull Request.

If you're new to git, I would recommend going through the git book tutorial:

https://git-scm.com/book/en/v2
