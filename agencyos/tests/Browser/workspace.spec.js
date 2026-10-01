import { test, expect } from '@playwright/test';

test('real agency onboarding and responsive workspace', async ({ page }) => {
    const stamp = Date.now();
    const email = `browser-${stamp}@example.com`;
    await page.goto('/register');
    await page.getByLabel('Your name', { exact: true }).fill('Browser Test Owner');
    await page.getByLabel('Agency name', { exact: true }).fill(`Browser Test Agency ${stamp}`);
    await page.getByLabel('Email address', { exact: true }).fill(email);
    await page.getByLabel('Password', { exact: true }).fill('BrowserTestPassword123');
    await page.getByLabel('Confirm password', { exact: true }).fill('BrowserTestPassword123');
    await page.getByRole('button', { name: 'Create workspace' }).click();
    await expect(page.getByRole('heading', { name: 'Agency overview' })).toBeVisible();
    await page.goto('/clients/create');
    await page.getByLabel('Name', { exact: false }).first().fill('Browser Test Client');
    await page.getByRole('button', { name: 'Save client' }).click();
    await expect(page.getByRole('heading', { name: 'Browser Test Client' })).toBeVisible();
    await page.goto('/client-plans');
    await page.getByLabel('Plan name').fill('Browser Test SEO');
    await page.getByLabel('Price in minor currency units').fill('50000');
    await page.getByLabel('Duration in days').fill('30');
    await page.getByRole('button', { name: 'Create plan', exact: true }).click();
    await expect(page.getByText('Client SEO plan created.')).toBeVisible();
    await page.goto('/client-subscriptions/create');
    await page.getByLabel('Service starts').fill('2026-10-01T12:00');
    await page.getByLabel('Service expires').fill('2026-10-31T12:00');
    await page.getByRole('button', { name: 'Create subscription and invoice' }).click();
    await expect(page.getByRole('heading', { name: 'Browser Test Client · SEO subscription' })).toBeVisible();
    await page.screenshot({ path: 'test-results/subscription-desktop.png', fullPage: true });
    for (const path of ['/dashboard', '/clients', '/projects', '/websites', '/employees', '/client-subscriptions', '/invoices', '/notifications', '/agency/settings']) {
        await page.setViewportSize({ width: 390, height: 844 });
        await page.goto(path);
        await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
    }
    await page.goto('/dashboard');
    const csrfResponse = await page.request.post('/agency/settings', { form: { _method: 'PATCH', name: 'Unprotected change', timezone: 'UTC', currency: 'USD' } });
    expect(csrfResponse.status()).toBe(419);
    await page.getByRole('button', { name: 'Open navigation' }).click();
    await expect(page.getByRole('link', { name: 'Overview', exact: false }).first()).toBeVisible();
    await page.getByRole('button', { name: 'Close navigation' }).click({ position: { x: 350, y: 20 } });
    await expect.poll(async () => (await page.locator('aside').boundingBox()).x).toBeLessThanOrEqual(-250);
    await page.screenshot({ path: 'test-results/dashboard-mobile.png', fullPage: true });
    await page.getByRole('button', { name: 'Sign out' }).click();
    await expect(page.getByRole('heading', { name: 'Welcome back' })).toBeVisible();
});
