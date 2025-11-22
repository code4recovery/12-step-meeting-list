# Testing the 12 Step Meeting List Plugin on a Live Site

## ⚠️ Important: Test on Staging First!

**Before testing on a live/production site, always test on a staging site first.** This prevents breaking your live site if something goes wrong.

## Step 1: Build the Plugin Assets

Before uploading the plugin, you need to build the JavaScript and CSS assets:

```bash
# Install dependencies (if you haven't already)
npm install

# Build the production assets
npm run build
```

This will compile all the JavaScript, CSS, and other assets needed for the plugin to work properly.

## Step 2: Prepare the Plugin for Upload

You have two main options for getting the plugin onto your WordPress site:

### Option A: Upload via WordPress Admin (Recommended for beginners)

1. **Create a ZIP file** of the entire plugin directory:
   ```bash
   # From the parent directory of 12-step-meeting-list
   zip -r 12-step-meeting-list.zip 12-step-meeting-list/
   ```

2. **Go to your WordPress admin panel**:
   - Navigate to **Plugins → Add New**
   - Click **Upload Plugin**
   - Choose the ZIP file you just created
   - Click **Install Now**
   - Click **Activate Plugin**

### Option B: Upload via FTP/SFTP (For direct file access)

1. **Upload the entire plugin folder** to:
   ```
   /wp-content/plugins/12-step-meeting-list/
   ```

2. **Go to WordPress admin**:
   - Navigate to **Plugins**
   - Find "12 Step Meeting List" in the list
   - Click **Activate**

## Step 3: Verify Installation

After activation, check:

1. **Look for the plugin menu**: You should see a "Meetings" menu item in your WordPress admin sidebar
2. **Check for errors**: Look at the top of your admin panel for any error messages
3. **Visit the plugin settings**: Go to **Meetings → Settings** to verify it loaded correctly

## Step 4: Test Core Functionality

### Basic Tests:

1. **Create a test meeting**:
   - Go to **Meetings → Add New**
   - Fill in required fields (name, day, time, location, address)
   - Save and verify it appears

2. **Test the public-facing display**:
   - Visit your site's meetings page (usually `/meetings/` or check **Meetings → Import & Export** for the exact URL)
   - Verify meetings display correctly
   - Test the search/filter functionality

3. **Test data import** (if applicable):
   - Go to **Meetings → Import & Export**
   - Try importing a test CSV file
   - Verify meetings are imported correctly

4. **Test admin features**:
   - Edit a meeting
   - Delete a meeting
   - Create a location
   - Create a region

## Step 5: Check for Conflicts

1. **Test with your theme**: Make sure the plugin displays correctly with your active theme
2. **Test with other plugins**: If you have other plugins active, check for conflicts
3. **Check browser console**: Open browser developer tools (F12) and look for JavaScript errors

## Step 6: Monitor Performance

- Check if the site loads at normal speed
- Monitor server resources if you have access
- Test with multiple meetings to ensure it scales

## Troubleshooting

### If the plugin doesn't activate:
- Check PHP version (requires PHP 5.6+, but 7.2+ recommended)
- Check WordPress version (requires 3.2+)
- Look for error messages in WordPress admin
- Check server error logs

### If assets don't load:
- Make sure you ran `npm run build` before uploading
- Clear your browser cache
- Clear WordPress cache if you use a caching plugin
- Check file permissions on the `assets/` directory

### If you see PHP errors:
- Enable WordPress debug mode (add to `wp-config.php`):
  ```php
  define('WP_DEBUG', true);
  define('WP_DEBUG_LOG', true);
  ```
- Check the error log at `/wp-content/debug.log`

## Best Practices

1. **Always backup first**: Before testing on a live site, create a full backup
2. **Use staging**: Test on a staging site that mirrors your production site
3. **Test incrementally**: Don't activate and test everything at once
4. **Document issues**: Keep notes of any problems you encounter
5. **Test with real data**: Use realistic test data that matches your actual use case

## Quick Development Workflow

If you're actively developing and need to test changes frequently:

1. **Use version control**: Keep your code in Git
2. **Use a deployment tool**: Tools like WP-CLI, Git, or deployment scripts can help
3. **Set up local development**: Use Local by Flywheel, XAMPP, or Docker for faster iteration
4. **Use a staging site**: Push changes to staging first, then to production

## Quick Patching for Already-Installed Plugin

If the plugin is already installed on your staging site, here are the fastest ways to update it with your changes:

### Method 1: Direct File Upload via SFTP/FTP (Fastest for small changes)

**Best for**: Quick PHP file edits, testing individual file changes

1. **Build assets** (if you changed JS/CSS):
   ```bash
   npm run build
   ```

2. **Upload only changed files**:
   - Connect via SFTP/FTP to your staging server
   - Navigate to `/wp-content/plugins/12-step-meeting-list/`
   - Upload only the files you modified
   - **Important**: If you changed assets, upload the entire `assets/` folder

3. **Clear caches**:
   - Clear browser cache (Ctrl+Shift+R or Cmd+Shift+R)
   - Clear WordPress cache if you use a caching plugin

### Method 0: Automated Deployment Script (Recommended!)

**Best for**: Quick, repeatable deployments with minimal typing

A `deploy.sh` script is included in the plugin root for easy deployment:

1. **First time setup** - Create your config file:
   ```bash
   cp .deploy-config.example .deploy-config
   # Edit .deploy-config with your server details
   ```

2. **Deploy individual files**:
   ```bash
   ./deploy.sh --file includes/admin_import.php
   ./deploy.sh --file includes/admin_import.php --file includes/functions_import.php
   ```

3. **Build and deploy assets**:
   ```bash
   ./deploy.sh --build --assets
   ```

4. **Deploy everything**:
   ```bash
   ./deploy.sh --build --all
   ```

The script handles:
- Building assets automatically
- Deploying specific files or entire plugin
- Proper path mapping
- Color-coded output
- Error handling

See `./deploy.sh --help` for all options.

### Method 1a: SSH - Copy Individual Files (Fastest with SSH)

**Best for**: Quick testing of individual file changes when you have SSH access

#### Using `scp` (Secure Copy):

```bash
# Copy a single PHP file
scp includes/admin_import.php user@staging-server:/path/to/wp-content/plugins/12-step-meeting-list/includes/

# Copy multiple files at once
scp includes/admin_import.php includes/functions_import.php \
  user@staging-server:/path/to/wp-content/plugins/12-step-meeting-list/includes/

# Copy with preserving directory structure
scp -r assets/build/ user@staging-server:/path/to/wp-content/plugins/12-step-meeting-list/assets/
```

#### Using `rsync` for individual files:

```bash
# Sync a single file
rsync -avz includes/admin_import.php \
  user@staging-server:/path/to/wp-content/plugins/12-step-meeting-list/includes/

# Sync specific files with pattern
rsync -avz includes/admin_*.php \
  user@staging-server:/path/to/wp-content/plugins/12-step-meeting-list/includes/
```

#### Direct SSH editing (advanced):

If you want to edit directly on the server (not recommended for complex changes):

```bash
# SSH into the server
ssh user@staging-server

# Navigate to plugin directory
cd /path/to/wp-content/plugins/12-step-meeting-list/

# Edit file with nano or vim
nano includes/admin_import.php
# or
vim includes/admin_import.php
```

#### Quick one-liner to patch a file:

```bash
# From your local machine, copy file directly
scp includes/admin_import.php user@staging-server:/path/to/wp-content/plugins/12-step-meeting-list/includes/admin_import.php
```

**Example workflow**:
```bash
# 1. Make your changes locally
# 2. Build if needed
npm run build

# 3. Copy just the changed file(s)
scp includes/admin_import.php user@staging:/var/www/wp-content/plugins/12-step-meeting-list/includes/

# 4. Test immediately - no plugin reactivation needed!
```

### Method 2: Sync Entire Plugin Folder (Best for multiple changes)

**Best for**: Multiple file changes, when you want to ensure everything is in sync

1. **Build assets**:
   ```bash
   npm run build
   ```

2. **Sync the entire plugin folder** using rsync (recommended) or SFTP:
   ```bash
   # Using rsync (excludes node_modules and other dev files)
   rsync -avz --exclude 'node_modules' --exclude '.git' \
     --exclude '*.map' \
     ./12-step-meeting-list/ user@staging-server:/path/to/wp-content/plugins/12-step-meeting-list/
   ```

   Or using SFTP client:
   - Delete the old plugin folder on staging (or rename it as backup)
   - Upload the entire plugin folder

3. **Verify permissions** (if needed):
   ```bash
   # On staging server
   chmod -R 755 /path/to/wp-content/plugins/12-step-meeting-list/
   ```

### Method 3: Using WP-CLI (If you have SSH access)

**Best for**: Automated deployments, if you have WP-CLI installed

```bash
# On your local machine
npm run build

# On staging server via SSH
cd /path/to/wp-content/plugins/
# Remove old version (backup first!)
mv 12-step-meeting-list 12-step-meeting-list.backup

# Upload new version (use your preferred method)
# Then activate
wp plugin activate 12-step-meeting-list
```

### Method 4: Git-based Deployment (Most professional)

**Best for**: Teams, version control, automated testing

1. **Commit your changes**:
   ```bash
   git add .
   git commit -m "Fix: description of changes"
   git push origin your-branch
   ```

2. **On staging server**, pull changes:
   ```bash
   cd /path/to/wp-content/plugins/12-step-meeting-list/
   git pull origin your-branch
   npm install
   npm run build
   ```

### Quick Reference: What to Upload When

| What You Changed | What to Upload | SSH Command Example |
|-----------------|----------------|---------------------|
| PHP files in `includes/` | Just the changed PHP files | `scp includes/admin_import.php user@server:/path/to/wp-content/plugins/12-step-meeting-list/includes/` |
| `12-step-meeting-list.php` | Just that file | `scp 12-step-meeting-list.php user@server:/path/to/wp-content/plugins/12-step-meeting-list/` |
| Files in `assets/src/` | Run `npm run build`, then upload entire `assets/build/` folder | `rsync -avz assets/build/ user@server:/path/to/wp-content/plugins/12-step-meeting-list/assets/` |
| Template files | Just the changed template files | `scp templates/archive-meetings.php user@server:/path/to/wp-content/plugins/12-step-meeting-list/templates/` |
| Multiple files | Entire plugin folder (Method 2) | `rsync -avz --exclude 'node_modules' --exclude '.git' ./ user@server:/path/to/wp-content/plugins/12-step-meeting-list/` |

### Example: Patching Your Current Changes

Based on your modified files (`includes/admin_import.php` and `includes/functions_import.php`):

```bash
# Quick patch both files at once
scp includes/admin_import.php includes/functions_import.php \
  user@staging-server:/path/to/wp-content/plugins/12-step-meeting-list/includes/

# Or one at a time
scp includes/admin_import.php user@staging-server:/path/to/wp-content/plugins/12-step-meeting-list/includes/
scp includes/functions_import.php user@staging-server:/path/to/wp-content/plugins/12-step-meeting-list/includes/
```

**Note**: No plugin reactivation needed! WordPress will automatically use the updated files on the next page load.

### Pro Tips for Faster Testing

1. **Use a file watcher** to auto-upload on save:
   - Tools like FileZilla, Cyberduck, or VS Code extensions can watch and sync files

2. **Create a deployment script**:
   ```bash
   #!/bin/bash
   # deploy.sh
   npm run build
   rsync -avz --exclude 'node_modules' --exclude '.git' \
     ./ user@staging:/path/to/wp-content/plugins/12-step-meeting-list/
   ```

3. **Use symlinks** (advanced, for local development):
   - Symlink your local plugin folder to WordPress plugins directory
   - Changes are instant, no upload needed

4. **Test specific files first**:
   - Upload only the file you're debugging
   - Faster than syncing everything

### After Patching

1. **Refresh the page** (hard refresh: Ctrl+Shift+R / Cmd+Shift+R)
2. **Check for PHP errors** in WordPress debug log
3. **Test the specific feature** you changed
4. **Clear caches** (browser, WordPress, server)

## Need Help?

- Check the plugin's [GitHub repository](https://github.com/code4recovery/12-step-meeting-list)
- Review the `readme.txt` file for detailed documentation
- Check WordPress error logs for specific error messages

