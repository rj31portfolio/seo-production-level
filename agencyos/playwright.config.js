import { defineConfig } from '@playwright/test';
export default defineConfig({
    testDir: './tests/Browser',
    workers: 1,
    timeout: 90000,
    use: { baseURL: 'http://127.0.0.1:8101', channel: 'chrome', screenshot: 'only-on-failure' },
    webServer: {
        command: 'node tests/Browser/server.mjs',
        url: 'http://127.0.0.1:8101/login',
        env: { BROWSER_PORT: '8101', APP_URL: 'http://127.0.0.1:8101', DB_CONNECTION: 'sqlite', DB_DATABASE: 'D:/Working/seo/agencyos/storage/framework/testing/seo-browser.sqlite' },
        reuseExistingServer: false,
    },
});
