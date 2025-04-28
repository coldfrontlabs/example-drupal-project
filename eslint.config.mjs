// This file is managed by dropfort/dropfort_build.
// Modifications to this file will be overwritten by default.

import drupalContrib from "eslint-plugin-drupal-contrib";

const recommended = drupalContrib.configs["flat/recommended"];

export const files = ["*.js", "*.mjs", "*.cjs"];
export const ignores = [
  ".devcontainer/*",
  ".gitlab/*",
  ".github/*",
  ".husky/*",
  ".vscode/*",
  "bin/*",
  "ci/*",
  "config/*",
  "container/*",
  "drush/*",
  "node_modules/*",
  "vendor/*",
  "web/*",
  "docroot/*",
  "!.*.js",
  "!.*.mjs",
  "!.*.cjs",
];

export const config = {
  plugins: {
    recommended,
  },
  languageOptions: {
    globals: {
      describe: false,
      it: false,
      after: false,
      Feature: false,
      Scenario: false,
      actor: false,
    },
  },
};

export default [
  config,
  {
    files,
  },
  {
    ignores,
  },
];
