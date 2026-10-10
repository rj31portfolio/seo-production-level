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


test('client login shows a restricted portal and SEO bulk templates download', async ({ page }) => {
    const stamp = Date.now();
    const clientEmail = 'portal-' + stamp + '@example.com';
    await page.goto('/register');
    await page.getByLabel('Your name', { exact: true }).fill('Portal Test Owner');
    await page.getByLabel('Agency name', { exact: true }).fill('Portal Agency ' + stamp);
    await page.getByLabel('Email address', { exact: true }).fill('portal-owner-' + stamp + '@example.com');
    await page.getByLabel('Password', { exact: true }).fill('BrowserTestPassword123');
    await page.getByLabel('Confirm password', { exact: true }).fill('BrowserTestPassword123');
    await page.getByRole('button', { name: 'Create workspace' }).click();
    await page.goto('/clients/create');
    await page.getByLabel('Name', { exact: false }).first().fill('Portal Test Client');
    await page.getByRole('button', { name: 'Save client', exact: true }).click();
    await page.getByLabel('Login email', { exact: true }).fill(clientEmail);
    await page.getByLabel('Password', { exact: true }).fill('PortalPassword123');
    await page.getByLabel('Confirm password', { exact: true }).fill('PortalPassword123');
    await page.getByRole('button', { name: 'Save client login', exact: true }).click();
    await expect(page.getByText('Client login saved. The client can sign in to view only their reports and backlinks.')).toBeVisible();
    await page.goto('/seo/bulk-upload');
    await expect(page.getByRole('heading', { name: 'Task work progress' })).toBeVisible();
    for (const link of await page.getByRole('link', { name: 'Download Excel template' }).all()) {
        const response = await page.request.get(await link.getAttribute('href'));
        expect(response.status()).toBe(200);
        expect(response.headers()['content-disposition']).toContain('.xlsx');
    }
    await page.getByRole('button', { name: 'Sign out', exact: true }).click();
    await page.getByLabel('Email address', { exact: true }).fill(clientEmail);
    await page.getByLabel('Password', { exact: true }).fill('PortalPassword123');
    await page.getByRole('button', { name: 'Sign in', exact: true }).click();
    await expect(page).toHaveURL(/\/portal\/reports$/);
    await expect(page.getByRole('heading', { name: 'Your reports' })).toBeVisible();
    await expect(page.locator('aside').getByText('Team members')).toHaveCount(0);
    await expect(page.locator('aside').getByText('Bulk SEO uploads')).toHaveCount(0);
    await page.goto('/portal/backlinks');
    await expect(page.getByRole('heading', { name: 'Your backlinks' })).toBeVisible();
    const excel = await page.request.get(await page.getByRole('link', { name: 'Download Excel' }).getAttribute('href'));
    expect(excel.status()).toBe(200);
    expect(excel.headers()['content-disposition']).toContain('backlinks.xlsx');
    for (const url of ['/portal/reports', '/portal/backlinks']) {
        await page.setViewportSize({ width: 390, height: 844 });
        expect((await page.goto(url)).status()).toBe(200);
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
    }
    expect((await page.request.get('/seo/tools')).status()).toBe(403);
    await page.screenshot({ path: 'test-results/client-portal-mobile.png', fullPage: true });
});
