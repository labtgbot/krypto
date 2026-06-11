const { test, expect } = require('@playwright/test');
const fs = require('fs');
const path = require('path');

const root = path.resolve(__dirname, '../..');
const fixtureRoot = path.join(root, 'tests/fixtures/changenow');
const pagePath = '/tests/e2e/fixtures/public-swap-page.php';
const publicSwapActionPath = '/app/modules/kr-changenow/src/actions/publicSwap.php';

function fixture(name) {
  return JSON.parse(fs.readFileSync(path.join(fixtureRoot, name), 'utf8'));
}

function parsePayload(request) {
  const payload = {};
  const body = request.postData() || '';
  const url = new URL(request.url());
  const params = new URLSearchParams(body || url.search.slice(1));

  for (const [key, value] of params.entries()) {
    payload[key] = value;
  }

  return payload;
}

function normalizeCreatedSwap(origin) {
  const created = fixture('exchange_create_success.json');
  return {
    ...created,
    providerId: created.id,
    status: 'waiting',
    availableActions: {},
    supportEmail: 'support@example.test'
  };
}

function normalizeFinishedStatus() {
  const status = fixture('exchange_status_finished.json');
  return {
    ...status,
    providerId: status.id,
    fromAmount: status.amountFrom,
    toAmount: status.amountTo,
    availableActions: {}
  };
}

async function mockPublicSwapProvider(page) {
  const actions = [];
  const blockedExternalRequests = [];

  await page.route('**/*', async route => {
    const request = route.request();
    const requestUrl = new URL(request.url());
    const isLocal = requestUrl.hostname === '127.0.0.1' || requestUrl.hostname === 'localhost';

    if (isLocal && requestUrl.pathname === publicSwapActionPath) {
      const payload = parsePayload(request);
      const action = payload.action || '';
      actions.push(action);

      if (action === 'validate') {
        const invalid = fixture('validation_error.json');
        const isValidAddress = payload.destinationAddress === '0xexamplepayoutaddress';
        return route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify({
            error: 0,
            validation: {
              result: isValidAddress,
              message: isValidAddress ? '' : invalid.message,
              isActivated: null
            }
          })
        });
      }

      if (action === 'quote') {
        const quote = {
          ...fixture('estimated_amount_standard_success.json'),
          quoteId: 'e2e-server-quote-1'
        };
        return route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify({
            error: 0,
            quote
          })
        });
      }

      if (action === 'create') {
        if (payload.quoteId !== 'e2e-server-quote-1') {
          return route.fulfill({
            status: 400,
            contentType: 'application/json',
            body: JSON.stringify({
              error: 2,
              type: 'validation',
              msg: 'Missing server quote id.'
            })
          });
        }

        return route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify({
            error: 0,
            swap: {
              lookupToken: 'lookup-token-1',
              statusUrl: `${requestUrl.origin}${pagePath}?swap_token=lookup-token-1`,
              supportEmail: 'support@example.test',
              transaction: normalizeCreatedSwap(requestUrl.origin)
            }
          })
        });
      }

      if (action === 'status') {
        return route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify({
            error: 0,
            status: {
              supportEmail: 'support@example.test',
              transaction: normalizeFinishedStatus()
            }
          })
        });
      }

      if (action === 'destinations') {
        return route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify({
            error: 0,
            assets: [
              {
                ticker: 'eth',
                network: 'eth',
                name: 'Ethereum',
                sell: true,
                buy: true
              }
            ]
          })
        });
      }

      return route.fulfill({
        status: 400,
        contentType: 'application/json',
        body: JSON.stringify({
          error: 2,
          type: 'validation',
          msg: `Unexpected e2e action: ${action}`
        })
      });
    }

    if (isLocal) {
      return route.continue();
    }

    blockedExternalRequests.push(request.url());
    return route.abort('blockedbyclient');
  });

  return {
    actions,
    blockedExternalRequests
  };
}

test('public ChangeNOW swap completes validation, quote, create, and status with mocked provider', async ({ page }, testInfo) => {
  const provider = await mockPublicSwapProvider(page);

  await page.goto(pagePath);
  await expect(page.getByRole('heading', { name: 'Swap crypto' })).toBeVisible();

  if (testInfo.project.name === 'desktop') {
    await page.getByLabel('Destination address', { exact: true }).fill('bad-address');
    await page.getByRole('button', { name: 'Validate address' }).click();
    await expect(page.getByRole('alert')).toContainText('address is not valid');
  }

  await page.getByLabel('Destination address', { exact: true }).fill('0xexamplepayoutaddress');
  await page.getByLabel('Refund address', { exact: true }).fill('bc1qexamplerefundaddress');

  await page.getByRole('button', { name: 'Validate address' }).click();
  await expect(page.getByRole('alert')).toContainText('Destination address is valid.');

  await page.getByRole('button', { name: 'Get quote' }).click();
  await expect(page.locator('.kr-public-quote-panel')).toContainText('0.052286 ETH / ETH');
  await expect(page.locator('input[name="quoteId"]')).toHaveValue('e2e-server-quote-1');
  await expect(page.getByRole('button', { name: 'Create swap' })).toBeEnabled();

  await page.getByRole('button', { name: 'Create swap' }).click();
  await expect(page).toHaveURL(/swap_token=lookup-token-1/);
  await expect(page.locator('.kr-public-result-panel')).toContainText('bc1qexamplepayinaddress');

  await page.goto(`${pagePath}?swap_token=lookup-token-1`);
  await expect(page.locator('.kr-public-result-panel')).toContainText('finished');
  await expect(page.locator('.kr-public-result-panel')).toContainText('tx-created');

  await expect(page).toHaveScreenshot(`changenow-public-swap-${testInfo.project.name}.png`, {
    fullPage: true
  });

  expect(provider.actions).toContain('validate');
  expect(provider.actions).toContain('quote');
  expect(provider.actions).toContain('create');
  expect(provider.actions).toContain('status');
  expect(provider.blockedExternalRequests).toEqual([]);
});
