// This file is managed by dropfort/dropfort_build.
// Modifications to this file will be overwritten by default.

// Allow .env files to be read.
require("dotenv").config();

// Get proxy URL for syncing.
const webroot = process.env.DF_DRUPAL_WEBROOT || "web";

/**
 * Initializes browser sync and reloads on JS/CSS file changes.
 */
module.exports = {
  files: [
    `/var/www/html/${webroot}/*/custom/*/css/*.css`,
    `/var/www/html/${webroot}/*/custom/*/dist/css/*.css`,
    `/var/www/html/${webroot}/*/custom/*/js/*.js`,
    `/var/www/html/${webroot}/*/custom/*/dist/js/*.js`,
    `/var/www/html/${webroot}/*/custom/*/templates/**/*.html.twig`,
  ],
  injectChanges: true,
  logLevel: "info",
  minify: false,
  open: false,
  host: "drupal.dflocal.net",
  proxy: "drupal",
  reloadDebounce: 500,
  reloadDelay: 1000,
  reloadOnRestart: true,
  reloadThrottle: 200,
  scrollThrottle: 100,
  watchEvents: ["add", "change"],
};
