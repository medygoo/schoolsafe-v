const path = require("path");
const { chromium } = require("playwright");
const base = (process.env.SCHOOLSAFE_URL || "http://127.0.0.1:8080").replace(/\/$/, "");

(async () => {
  const browser = await chromium.launch({
    args: ["--disable-background-timer-throttling", "--disable-renderer-backgrounding", "--disable-backgrounding-occluded-windows"],
  });
  try {
    for (const gesture of ["sendStanding", "sendKneeling"]) {
      const page = await browser.newPage({ viewport: { width: 340, height: 480 } });
      await page.goto(`${base}/modules/SchoolSafe_Jaspe/jaspe2d/geste-harness.html?review=${gesture}&shot=${Date.now()}`, { waitUntil: "load" });
      await page.waitForFunction(() => window.__harness && window.__harness.status === "review", null, { timeout: 60000, polling: 200 });
      await page.waitForFunction(() => {
        const live = document.getElementById("jaspe-native").jaspeV12.getLive();
        const state = live.getState();
        return Boolean(state && state.reaction && state.progress > 0.42 && state.progress < 0.68);
      }, null, { timeout: 60000, polling: 30 });
      const shot = path.join(__dirname, `jaspe-emoji-${gesture}.png`);
      await page.screenshot({ path: shot, clip: { x: 0, y: 0, width: 330, height: 470 } });
      const state = await page.evaluate(() => document.getElementById("jaspe-native").jaspeV12.getLive().getState());
      console.log(gesture, "progress", Math.round(state.progress * 100) / 100, "reaction", JSON.stringify(state.reaction), "->", path.basename(shot));
      await page.close();
    }
  } finally {
    await browser.close();
  }
})().catch(error => { console.error(String(error && error.stack || error)); process.exitCode = 1; });
