const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage();

  // Start the php server if not started? We don't have instructions.
  // Wait, I should check how to start the app.

  await browser.close();
})();
