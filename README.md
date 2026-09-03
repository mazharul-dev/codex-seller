# CodeX Seller

CodeX Seller is a WordPress plugin for syncing purchased CodeX Seller products and updating them directly from the WordPress admin dashboard.

It connects to the fixed CodeX Seller API at `https://codexsell.com/`, fetches purchased plugins and themes, checks installed versions, installs available updates, creates backups before updates, and supports rollback from the latest backup.

## Features

- Purchased product sync from CodeX Seller
- Plugin and theme update/install support
- Manual `Run Now` update action
- Scheduled automatic updates with hourly, twice-daily, or daily frequency
- Backup before update
- Latest backup rollback
- Health checks before update runs
- Email update reports
- Product filters for all products, plugins, and themes
- Responsive WordPress admin interface

## Requirements

- WordPress installation with admin access
- PHP with WordPress-compatible version support
- PHP `ZipArchive` extension for backup and rollback features
- Writable `wp-content/uploads` directory
- Valid CodeX Seller account credentials

## Installation

1. Download or clone this repository.
2. Copy the plugin folder into your WordPress plugins directory:

   ```bash
   wp-content/plugins/codex-seller
   ```

3. Log in to your WordPress admin panel.
4. Go to `Plugins`.
5. Activate `CodeX Seller`.
6. Open the `CodeX Seller` menu from the WordPress admin sidebar.

## Configuration

1. Go to `CodeX Seller > Settings`.
2. Enter your CodeX Seller email and password.
3. Review update settings:
   - Auto updates
   - Backup before update
   - Health check
   - Email reports
   - Plugin updates
   - Theme updates
   - Update frequency
4. Save settings.

The API base URL is fixed to:

```text
https://codexsell.com/
```

## Usage

### Fetch Purchased Products

Go to `CodeX Seller > Updates` and click `Fetch Products` to load products from your CodeX Seller account.

### Install or Update Products

After products are fetched, each product row shows:

- Remote version
- Installed version
- Current status
- Install or update action

Click `Install` or `Update` to apply a product package.

### Run Manual Update Cycle

Click `Run Now` from the Updates page to run the full update cycle manually.

### Backups and Rollback

When backup is enabled, CodeX Seller creates a backup before installing an update. Backups are stored in:

```text
wp-content/uploads/codex-seller/backups
```

To restore the latest backup, go to `CodeX Seller > Backups` and click `Rollback Now`.

### Email Reports

Enable email reports from `CodeX Seller > Settings` and set the report email address. Update summaries will be sent after update cycles.

## Project Structure

```text
codex-seller/
|-- assets/
|   |-- admin.css
|   `-- admin.js
|-- CHANGELOG.md
|-- codex-seller.php
`-- README.md
```

## Development Notes

- Main plugin file: `codex-seller.php`
- Admin styles: `assets/admin.css`
- Admin scripts: `assets/admin.js`
- WordPress text domain: `codex-seller`
- Current plugin version: `1.1.0`

## GitHub Upload

Run these commands from the project folder:

```bash
git init
git add .
git commit -m "Initial CodeX Seller plugin"
git branch -M main
git remote add origin https://github.com/mazharul-dev/codex-seller.git
git push -u origin main
```

Replace `your-username` with your GitHub username.

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for release history.

## License

No license file is included yet. Add a license before publishing this repository publicly if you want to define usage, modification, and distribution rights.
