import { defineConfig } from '@playwright/test';
export default defineConfig({
    testDir: './tests/Browser',
    workers: 1,
    use: { baseURL: 'http://127.0.0.1:8099', channel: 'chrome', screenshot: 'only-on-failure' },
    webServer: {
        command: 'node tests/Browser/server.mjs',
        url: 'http://127.0.0.1:8099/login',
        reuseExistingServer: false,
    },
});
