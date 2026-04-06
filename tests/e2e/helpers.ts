import { Page } from '@playwright/test';

export const BASE = 'http://127.0.0.1:8000';

/**
 * Navigate safely through Filament/Livewire pages.
 * Clears current page state first to prevent Livewire SPA from hijacking nav,
 * then navigates with a graceful ERR_ABORTED fallback.
 */
export async function goto(page: Page, url: string) {
  // Clear any Livewire SPA navigation interceptors by going to blank first
  try {
    await page.goto('about:blank', { waitUntil: 'commit', timeout: 5_000 });
  } catch { /* non-fatal */ }

  try {
    await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 30_000 });
  } catch (e: unknown) {
    const msg = (e as Error).message ?? '';
    if (msg.includes('ERR_ABORTED') || msg.includes('net::') || msg.includes('Timeout')) {
      // Fallback — page may still be usable
      await page.waitForTimeout(800);
    } else {
      throw e;
    }
  }

  // Give Livewire / Alpine time to hydrate
  await page.waitForTimeout(1200);
}

/** Login as admin */
export async function login(page: Page) {
  await goto(page, `${BASE}/admin/login`);
  await page.getByLabel('Email address').fill('admin@kise.ac.ke');
  await page.getByLabel('Password').fill('password');
  await page.getByRole('button', { name: 'Sign in' }).click();
  await page.waitForURL(/\/admin/, { timeout: 20_000 });
  await page.waitForTimeout(1500);
}

/** Wait for Livewire to settle */
export async function lwWait(page: Page, ms = 1000) {
  await page.waitForTimeout(ms);
}

/** Take a labelled screenshot */
export async function snap(page: Page, name: string) {
  await page.screenshot({
    path: `playwright-report/screenshots/${name}.png`,
    fullPage: true,
  }).catch(() => { /* non-fatal */ });
}

/**
 * Fill a Filament TextInput by trying label, placeholder, and wire:model.
 */
export async function fillInput(
  page: Page,
  labelText: string,
  placeholder: string,
  value: string,
  wireModel?: string,
) {
  // Strategy 1: getByLabel
  const byLabel = page.getByLabel(labelText, { exact: false }).first();
  if (await byLabel.isVisible({ timeout: 3000 }).catch(() => false)) {
    await byLabel.fill(value);
    return;
  }

  // Strategy 2: getByPlaceholder
  const byPlaceholder = page.getByPlaceholder(placeholder, { exact: false }).first();
  if (await byPlaceholder.isVisible({ timeout: 2000 }).catch(() => false)) {
    await byPlaceholder.fill(value);
    return;
  }

  // Strategy 3: wire:model
  if (wireModel) {
    const byWire = page.locator(`[wire\\:model*="${wireModel}"], [wire\\:model\\.live*="${wireModel}"]`).first();
    if (await byWire.isVisible({ timeout: 2000 }).catch(() => false)) {
      await byWire.fill(value);
      return;
    }
  }

  console.log(`  ⚠️  Could not find input: "${labelText}"`);
}

/**
 * Select from a Filament Select component (native or custom combobox).
 */
export async function filamentSelect(
  page: Page,
  labelText: string,
  optionText: string,
) {
  // Try native select first
  const labelEl = page.getByLabel(labelText, { exact: false }).first();
  const isVisible = await labelEl.isVisible({ timeout: 3000 }).catch(() => false);

  if (!isVisible) {
    console.log(`  ⚠️  Select not found: "${labelText}"`);
    return;
  }

  const tag = await labelEl
    .evaluate((el: HTMLElement) => el.tagName.toLowerCase())
    .catch(() => 'div');

  if (tag === 'select') {
    await labelEl.selectOption({ label: optionText }).catch(() => {
      labelEl.selectOption(optionText.toLowerCase()).catch(() => {});
    });
    return;
  }

  // Custom combobox — click trigger, pick option
  await labelEl.click().catch(async () => {
    // fallback: click the wrapper div
    await page.locator(`div:has(label:text-is("${labelText}"))`).first().click();
  });
  await page.waitForTimeout(500);

  const option = page.locator('[role="option"]').filter({ hasText: new RegExp(optionText, 'i') }).first();
  if (await option.isVisible({ timeout: 5000 }).catch(() => false)) {
    await option.click();
  } else {
    console.log(`  ⚠️  Option "${optionText}" not found in select "${labelText}"`);
  }
  await page.waitForTimeout(300);
}

/**
 * Check if a page has a real authorization/server error.
 * Returns an object with error flags and any found issues.
 */
export function checkPageErrors(bodyText: string, currentUrl: string): {
  has500: boolean;
  has403: boolean;
  has404: boolean;
  isLogin: boolean;
  issues: string[];
} {
  const issues: string[] = [];

  // Real 403 forbidden: Filament renders a specific message
  const has403 = (
    /Forbidden/.test(bodyText) ||
    /You don.*t have permission/i.test(bodyText) ||
    /Access Denied/i.test(bodyText) ||
    /This action is unauthorized/i.test(bodyText)
  ) && !currentUrl.includes('/admin/login');

  const has500 = /Whoops|Something went wrong|Server Error|Stack trace/i.test(bodyText);

  const has404 = /Not Found|404 Error/i.test(bodyText) && !currentUrl.includes('/admin');

  const isLogin = currentUrl.includes('/login');

  if (has500) issues.push('500 Server Error');
  if (has403) issues.push('403 Forbidden');
  if (has404) issues.push('404 Not Found');
  if (isLogin) issues.push('Redirected to login');

  return { has500, has403, has404, isLogin, issues };
}
