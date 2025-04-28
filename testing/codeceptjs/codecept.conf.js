const { setHeadlessWhen, setCommonPlugins } = require("@codeceptjs/configure");

// turn on headless mode when running with HEADLESS=true environment variable
// export HEADLESS=true && npx codeceptjs run
setHeadlessWhen(process.env.HEADLESS);

// enable all common plugins https://github.com/codeceptjs/configure#setcommonplugins
setCommonPlugins();

exports.config = {
  tests: "./tests/*_test.js",
  output: "./output",
  helpers: {
    WebDriver: {
      url: process.env.DF_TEST_URL || "http://drupal",
      browser: "chrome",
      host: process.env.DF_SELENIUM_HOST || "selenium",
      port: Number(process.env.DF_SELENIUM_PORT) || 4444,
      basicAuth: {
        username: process.env.DF_SHIELD_USER || "",
        password: process.env.DF_SHIELD_PW || "",
      },
      restart: true,
      windowSize: process.env.DF_SELENIUM_WINDOW_SIZE || "1280x1024",
      desiredCapabilities: {
        chromeOptions: {
          args: [
            "--headless",
            "--disable-gpu",
            "--no-sandbox",
            "--ignore-certificate-errors",
          ],
        },
      },
    },
    ResembleHelper: {
      require: "codeceptjs-resemblehelper",
      screenshotFolder: "./output/",
      baseFolder: "./screenshots/base/",
      diffFolder: "./screenshots/diff/",
      prepareBaseImage: true,
    },
  },
  include: {
    I: "./steps_file.js",
  },
  bootstrap: null,
  mocha: {
    reporterOptions: {
      "codeceptjs-cli-reporter": {
        stdout: "-",
        options: {
          verbose: true,
          steps: true,
        },
      },
      mochawesome: {
        stdout: "./output/console.log",
        options: {
          reportDir: "./output",
          reportFilename: "report",
        },
      },
      "mocha-junit-reporter": {
        stdout: "./output/console.log",
        options: {
          mochaFile: "./output/result.xml",
          attachments: true, // add screenshot for a failed test
        },
      },
    },
  },
  name: "html",
};
