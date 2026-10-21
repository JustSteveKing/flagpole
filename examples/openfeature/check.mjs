// Runs OpenFeature's own OFREP providers against Flagpole's seeded demo, and fails if any answer is
// not what the demo's rules say it should be. The server provider evaluates one flag at a time;
// the web provider evaluates them all in bulk and polls with If-None-Match. The check then
// switches a flag off through the management API, waits for the web provider to notice, and
// switches it back on, so it can be run again.
import { OpenFeature as Server } from '@openfeature/server-sdk';
import { OFREPProvider } from '@openfeature/ofrep-provider';
import { OpenFeature as Web } from '@openfeature/web-sdk';
import { OFREPWebProvider } from '@openfeature/ofrep-web-provider';

const baseUrl = process.env.FLAGPOLE_URL ?? 'http://127.0.0.1:8000';
const key = process.env.SDK_KEY;

if (!key) {
  console.error('Set SDK_KEY to the development SDK key that php artisan db:seed printed.');
  process.exit(2);
}

const headers = [['Authorization', `Bearer ${key}`]];
const context = { targetingKey: 'user-1', email: 'jane@flagpole.test', country: 'GB', orders: 12, plan: 'trade' };

let failures = 0;

// Compares only the fields named in expected, so an SDK adding fields does not fail the check.
function expect(label, details, expected) {
  const actual = Object.fromEntries(Object.keys(expected).map((field) => [field, details[field]]));
  const ok = JSON.stringify(actual) === JSON.stringify(expected);

  if (!ok) {
    failures++;
  }

  console.log(`${ok ? 'ok  ' : 'FAIL'} ${label.padEnd(44)} ${JSON.stringify(actual)}${ok ? '' : `, expected ${JSON.stringify(expected)}`}`);
}

// Every bulk evaluation the web provider makes, to see its polls answered with 304.
const bulkStatuses = [];
const originalFetch = globalThis.fetch;
globalThis.fetch = async (input, init) => {
  const response = await originalFetch(input, init);
  const url = new URL(input instanceof Request ? input.url : String(input));

  if (url.pathname === '/ofrep/v1/evaluate/flags') {
    bulkStatuses.push(response.status);
  }

  return response;
};

console.log('Server provider, one flag at a time');

await Server.setProviderAndWait(new OFREPProvider({ baseUrl, headers }));
const server = Server.getClient();

expect('boolean, matched by email', await server.getBooleanDetails('new-checkout', false, context), { value: true, variant: 'on', reason: 'TARGETING_MATCH' });
expect('string, matched by country', await server.getStringDetails('theme', 'unset', context), { value: '#00aa55', variant: 'green', reason: 'TARGETING_MATCH' });
expect('integer, matched by plan', await server.getNumberDetails('basket-limit', 0, context), { value: 200, variant: 'trade', reason: 'TARGETING_MATCH' });
expect('float, matched by order count', await server.getNumberDetails('discount-rate', -1, context), { value: 0.1, variant: 'loyal', reason: 'TARGETING_MATCH' });
expect('object, switched off', await server.getObjectDetails('payment-providers', {}, context), { value: { providers: ['card'] }, variant: 'cards', reason: 'DISABLED' });
expect('rollout with no targetingKey', await server.getBooleanDetails('new-checkout', false, { email: 'sam@example.com' }), { value: false, errorCode: 'TARGETING_KEY_MISSING' });
expect('a flag that does not exist', await server.getBooleanDetails('missing', false, context), { value: false, errorCode: 'FLAG_NOT_FOUND' });
expect('a boolean asked for as a string', await server.getStringDetails('new-checkout', 'unset', context), { value: 'unset', errorCode: 'TYPE_MISMATCH' });

console.log('\nWeb provider, every flag at once, polling');

await Web.setContext(context);
await Web.setProviderAndWait(new OFREPWebProvider({ baseUrl, headers, pollInterval: 500 }));
const web = Web.getClient();

expect('boolean, matched by email', web.getBooleanDetails('new-checkout', false), { value: true, variant: 'on', reason: 'TARGETING_MATCH' });
expect('string, matched by country', web.getStringDetails('theme', 'unset'), { value: '#00aa55', variant: 'green', reason: 'TARGETING_MATCH' });

await new Promise((resolve) => setTimeout(resolve, 1800));
expect('polls while nothing changed get 304', { notModified: bulkStatuses.includes(304) }, { notModified: true });

// Switch new-checkout off in development as the owner would, with the settings' ETag in If-Match.
const login = await originalFetch(`${baseUrl}/v1/auth/login`, {
  method: 'POST',
  headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
  body: JSON.stringify({ email: 'demo@flagpole.test', password: 'password', device_name: 'openfeature-check' }),
}).then((response) => response.json());
const owner = { Authorization: `Bearer ${login.meta.token}`, Accept: 'application/json' };
const settingsUrl = `${baseUrl}/v1/projects/checkout/flags/new-checkout/settings/development`;

async function setEnabled(enabled) {
  const etag = (await originalFetch(settingsUrl, { headers: owner })).headers.get('ETag');
  const response = await originalFetch(settingsUrl, {
    method: 'PATCH',
    headers: { ...owner, 'Content-Type': 'application/merge-patch+json', 'If-Match': etag },
    body: JSON.stringify({ enabled }),
  });

  return response.status;
}

let changed = false;
web.addHandler('PROVIDER_CONFIGURATION_CHANGED', () => {
  changed = true;
});

expect('switched off through the API', { status: await setEnabled(false) }, { status: 200 });

for (let attempt = 0; attempt < 50 && !changed; attempt++) {
  await new Promise((resolve) => setTimeout(resolve, 200));
}

expect('the next poll notices', { changed }, { changed: true });
expect('boolean, now switched off', web.getBooleanDetails('new-checkout', false), { value: false, variant: 'off', reason: 'DISABLED' });
expect('switched back on through the API', { status: await setEnabled(true) }, { status: 200 });

await Web.close();
await Server.close();

console.log(failures === 0 ? '\nEverything matched.' : `\n${failures} check(s) failed.`);
process.exit(failures === 0 ? 0 : 1);
