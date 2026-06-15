# Portable Assets for Modern WordPress Development

A modern, portable solution for managing frontend and editor assets in WordPress themes. This guide outlines how to use a dedicated assets folder to bring reusable styles, scripts, and build tools into any WordPress project.

## Overview

The assets folder contains all the necessary components to manage styles and scripts effectively within a WordPress environment. This approach supports both frontend assets and block editor integrations, including tools for maintaining high code standards using Webpack, Babel, SCSS, and more.

### Key Features

- Frontend Styles and Scripts: Enqueue CSS and JavaScript files dynamically.
- Block Editor Integration: Enqueue styles and scripts for the block editor (Gutenberg) to ensure a consistent look across both frontend and backend.
- Webpack Configuration: Customizable configuration for building and versioning assets.
- Portable and Reusable: Easily integrate this folder into any WordPress project.

## `assets` directory

The assets directory name is not fixed and can be changed to anything that suits your project. However, if you choose to rename it, make sure to update all related references in the `functions.php`, `assets.php`, and other scripts where this directory is used.

## File Tree

```txt
assets
├── scripts
│   ├── components
│   │   ├── test.js
│   └── dashboard.js
│   └── editor.js
│   └── frontend.js
├── styles
│   ├── components
│   │   ├── test.js
│   └── dashboard.scss
│   └── editor.scss
│   └── frontend.scss
├── .gitignore
├── assets.php
├── package.json
└── webpack.config.js
```

## Installation

### Prerequisites

- Node.js: Version 16.20.2 or higher.
- npm: Version 8.19.4 or higher.
- @wordpress/scripts: Version 27.0.0.

Ensure you have the correct versions of Node.js, npm, and `@wordpress/scripts` installed to proceed with the setup.

### Steps

1. Add `assets.php` to `functions.php`

Add the `assets.php` file to your WordPress plugin e.g.:

```php
require_once plugin_dir_path( __FILE__ ) . 'assets/assets.php';
```

2. Install Dependencies

Navigate to the assets directory and install dependencies using npm:

```bash
npm install
```

3. Run Development Server

Start the development server for hot-reloading during development:

```bash
npm run start
```

4. Build for Production

When you are ready to build the final assets for production, run:

```bash
npm run build
```

## Asset Management (assets.php)

The `assets.php` file handles enqueueing styles and scripts for both the frontend and editor.

### Code Example

```php
<?php
/**
 * Asset Management for Frontend and Editor
 *
 * This file contains functions to enqueue frontend styles and scripts,
 * as well as styles and scripts for the WordPress block editor.
 *
 * @package starter
 */

/**
 * Enqueue frontend styles and scripts.
 *
 * @return void
 */
function site_frontend_scripts_support() {
    // Enqueue the main stylesheet.
    $css_file_path = glob( get_template_directory() . '/assets/build/frontend.min.*.css' );
    if ( ! empty( $css_file_path ) && file_exists( $css_file_path[0] ) ) {
        $css_file_uri = get_template_directory_uri() . '/assets/build/' . basename( $css_file_path[0] );
        wp_enqueue_style( 'site-frontend-style', $css_file_uri, array(), filemtime( $css_file_path[0] ), 'all' );
    }

    // Enqueue the main JavaScript file.
    $js_file_path = glob( get_template_directory() . '/assets/build/frontend.min.*.js' );
    if ( ! empty( $js_file_path ) && file_exists( $js_file_path[0] ) ) {
        $js_file_uri = get_template_directory_uri() . '/assets/build/' . basename( $js_file_path[0] );
        wp_enqueue_script( 'site-frontend-script', $js_file_uri, array(), filemtime( $js_file_path[0] ), true );
    }
}
add_action( 'wp_enqueue_scripts', 'site_frontend_scripts_support', 999 );

/**
 * Enqueue editor styles.
 *
 * @return void
 */
function site_editor_styles_support() {
    if ( ! current_theme_supports( 'editor-styles' ) ) {
        // Enable editor styles if not already set.
        add_theme_support( 'editor-styles' );
    }

    // Always add the editor stylesheet.
    add_editor_style( 'assets/build/editor.css' );
}
add_action( 'after_setup_theme', 'site_editor_styles_support' );

/**
 * Enqueue editor scripts.
 *
 * @return void
 */
function site_editor_scripts_support() {
    // Enqueue the editor scripts.
    wp_enqueue_script(
        'site-editor-script',
        get_template_directory_uri() . '/assets/build/editor.js',
        array( 'wp-blocks', 'wp-dom-ready', 'wp-edit-post' ),
        '1.0',
        true
    );
}
add_action( 'enqueue_block_editor_assets', 'site_editor_scripts_support' );
```

## Webpack Configuration (webpack.config.js)

The Webpack configuration is customized to generate versioned CSS and JavaScript files for both the frontend and the editor. This ensures cache-busting by creating unique hashes for each build.

### Code Example

```js
// Imports @wordpress/scripts abstraction of Webpack
const defaultConfig = require("@wordpress/scripts/config/webpack.config");
const path = require("path");
const { CleanWebpackPlugin } = require("clean-webpack-plugin");
const buildDir = path.resolve(__dirname, "build");

// Find the MiniCssExtractPlugin in the default plugins array
const miniCssExtractPlugin = defaultConfig.plugins.find(
  (plugin) => plugin.constructor.name === "MiniCssExtractPlugin"
);

// Update the MiniCssExtractPlugin configuration
if (miniCssExtractPlugin) {
  miniCssExtractPlugin.options.filename = (pathData) => {
    return pathData.chunk.name === "editor"
      ? "editor.css"
      : "[name].min.[fullhash].css";
  };
}

// Create a new configuration object
const assetsConfig = {
  ...defaultConfig,
  output: {
    ...defaultConfig.output,
    filename: (chunkData) => {
      return chunkData.chunk.name === "editor"
        ? "editor.js"
        : "[name].min.[fullhash].js";
    },
    path: buildDir,
  },
  module: {
    ...defaultConfig.module,
    rules: [
      ...defaultConfig.module.rules,
      // Loader for images and icons (only required if CSS references image files)
      {
        test: /\.(png|jpg|gif|svg)$/,
        type: "asset/resource",
        generator: {
          filename: "images/[name][ext]",
        },
      },
    ],
  },
  plugins: [
    ...defaultConfig.plugins,
    new CleanWebpackPlugin({
      cleanOnceBeforeBuildPatterns: [buildDir],
      protectWebpackAssets: false,
    }),
  ],
};

module.exports = assetsConfig;
```

## Package Configuration (package.json)

The `package.json` includes dependencies and scripts to manage the build process using `@wordpress/scripts`, making development consistent with modern WordPress standards.

### Code Example

```json
{
  "name": "assets",
  "version": "1.2.0",
  "description": "A portable assets folder for modern WordPress development.",
  "main": "index.js",
  "author": "Hector Jarquin, Tomas Llobet-Arany, Gregius",
  "license": "GPL-2.0 AND MIT",
  "homepage": "https://gregius.com",
  "scripts": {
    "build": "wp-scripts build --config webpack.config.js src/frontend.js src/editor.js",
    "format": "wp-scripts format",
    "lint:css": "wp-scripts lint-style",
    "lint:js": "wp-scripts lint-js",
    "packages-update": "wp-scripts packages-update",
    "plugin-zip": "wp-scripts plugin-zip",
    "start": "wp-scripts start --config webpack.config.js src/frontend.js src/editor.js"
  },
  "devDependencies": {
    "@wordpress/scripts": "^27.0.0",
    "clean-webpack-plugin": "^4.0.0",
    "css-loader": "^6.7.3",
    "mini-css-extract-plugin": "^2.7.5",
    "npm-run-all": "^4.1.5",
    "sass": "^1.62.1",
    "sass-loader": "^13.2.2"
  }
}
```

## Usage

Follow these steps for working with the assets in your WordPress project:

1. Add the Assets File: Include `assets.php` in your theme's `functions.php`.
2. Install Dependencies: Run `npm install` to install all required packages.
3. Start Development: Use `npm run start` to start the development server.
4. Build Production Files: When ready to deploy, run `npm run build`.

This guide ensures that your assets are efficiently managed and portable across multiple projects, making WordPress theme development more streamlined and modern.
