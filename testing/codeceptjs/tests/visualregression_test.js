Feature("visualregression");

const $urlPaths = ["/user/login", "/", "/node"];
const $referenceUrl = process.env.DF_REF_HOST || "https://drupal.org";

$urlPaths.forEach(($urlPath) => {
  Scenario(`Visual Reference Page ${$urlPath}`, ({ I }) => {
    const $url = $referenceUrl + $urlPath;
    const $filename = `${$urlPath
      .replace(/[^a-z0-9]/gi, "_")
      .toLowerCase()}.png`;
    I.amOnPage($url);
    I.saveScreenshot($filename, true);
    I.seeVisualDiff($filename, {
      tolerance: 0,
      prepareBaseImage: true,
    });
  })
    .tag("@visual")
    .tag("@reference")
    .tag("@acceptance")
    .tag("@css");

  Scenario(`Visual Test Pages ${$urlPath}`, ({ I }) => {
    const $filename = `${$urlPath
      .replace(/[^a-z0-9]/gi, "_")
      .toLowerCase()}.png`;
    I.amOnPage($urlPath);
    I.saveScreenshot($filename, true);
    I.seeVisualDiff($filename, {
      tolerance: 2,
      prepareBaseImage: false,
      scaleToSameSize: true,
    });
  })
    .tag("@css")
    .tag("@visual")
    .tag("@acceptance")
    .tag("@test");
});
