// This file is managed by dropfort/dropfort_build.
// Modifications to this file will be overwritten by default.

const config = {
  "*.{js,mjs,cjs}": ["eslint --fix", "prettier --write"],
  "*.{php,module,inc,install,test,profile,theme}": "npm run lint:php -- ",
};

module.exports = config;
