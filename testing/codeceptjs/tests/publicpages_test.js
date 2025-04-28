Feature("publicpages");

Scenario("Test Public Homepage", ({ I }) => {
  I.amOnPage("/");
  I.see("Home");
  // @todo add validation tests.
}).tag("@js");
