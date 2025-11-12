# WP Product Update Client

This repository contains a WordPress plugin that integrates with the companion [WP Product Update Server](https://example.com) to deliver updates for premium plugins purchased by authenticated customers.

## Features

- Connects a WordPress site to a remote update server using secure API requests.
- Adds an admin settings page where site administrators configure the server URL and authenticate with their customer credentials.
- Requires a successful login before manual or automatic updates can be performed for purchased plugins.
- Injects update metadata into the WordPress update system and attaches authorization headers to package downloads.
- Displays helpful notices in the WordPress admin area when action is needed.

## Installation

1. Copy the `wp-product-update-client` directory into your WordPress site's `wp-content/plugins/` folder.
2. Activate the **WP Product Update Client** plugin from the WordPress Plugins screen.
3. Navigate to **Settings → Product Updates** and configure the base URL of your WP Product Update Server installation. You can
   supply either the site URL (e.g. `https://example.com`) or the REST namespace URL (e.g. `https://example.com/wp-json/wp-product-update-server/v1`).
4. Log in with the credentials provided when you purchased your plugins. After a successful login, the plugin will automatically manage update checks and downloads.

## Development

The plugin is organized into several classes under the `WP_Product_Update_Client` namespace:

- `API_Client` – handles authenticated HTTP communication with the update server.
- `Authentication_Manager` – stores and validates the authentication token returned by the server.
- `Update_Manager` – integrates with WordPress' update hooks to fetch metadata and guard update downloads.
- `Admin\Settings_Page` – renders the settings screen and login controls.
- `Plugin` – bootstraps the plugin, registers assets, and displays admin notices.

To extend the plugin, add additional classes within the `includes/` directory. The lightweight autoloader automatically resolves classes within the `WP_Product_Update_Client` namespace.

## License

This project is provided as-is for integration with the WP Product Update Server. Adapt it to suit the requirements of your marketplace or distribution workflow.
