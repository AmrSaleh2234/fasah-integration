# Fasah Pay Onboarding Guide (Sandbox to Production)

This guide covers the non-code steps required before the package works against Fasah, and how to go live.

## Overview

| Phase | You get from Fasah | You do |
|---|---|---|
| 1. Sandbox access | IP whitelisting, sandbox `username`, `password`, `client_id`, `client_secret` | Email Fasah (template below) |
| 2. Sandbox testing | n/a | Create test invoices, collect FP numbers, export PDFs |
| 3. Sandbox sign-off | Approval to go live | Email Fasah the FP numbers and invoice PDFs |
| 4. Production | Production `client_id`, `client_secret` | Use your real Fasah account `username` and `password` |

## Phase 1: Request sandbox access

Send the email below to your Fasah / Tabadul integration contact (use the address given in the Fasah Pay Integration Guidelines or your account manager).

You need to give them:
- Your server's **public static IP address(es)**, for both the app making API calls and, if different, the server receiving callbacks. Fasah only accepts requests from whitelisted IPs.
- Your company name, VAT number and contact person.
- Your callback (webhook) URLs, which must be public HTTPS.

### Email template (sandbox)

```text
Subject: Fasah Pay API Integration - IP Whitelisting and Sandbox Credentials Request

Dear Fasah Pay Team,

We are integrating with the Fasah Pay invoice APIs and would like to start testing in the sandbox environment.

Company name: <COMPANY NAME>
VAT number: <15-DIGIT VAT>
Contact person: <NAME / PHONE / EMAIL>

Please:
1. Whitelist the following IP address(es) for the sandbox environment:
   - <SERVER PUBLIC IP 1>
   - <SERVER PUBLIC IP 2, if any>

2. Provide our sandbox credentials:
   - Username
   - Password
   - Client ID
   - Client Secret

3. Confirm that the following callback URLs may be registered:
   - Invoice notifications: https://<YOUR-DOMAIN>/fasahpay/invoice-notification
   - Settlement notifications: https://<YOUR-DOMAIN>/fasahpay/settlement-notification

Thank you,
<NAME>
<COMPANY>
```

### Store the credentials

When Fasah replies, put the values in `.env` (never commit this file):

```dotenv
FASAHPAY_ENV=sandbox
FASAHPAY_BILLER_VAT_NUMBER=<15-digit VAT>

FASAHPAY_SANDBOX_CLIENT_ID=<client_id>
FASAHPAY_SANDBOX_CLIENT_SECRET=<client_secret>
FASAHPAY_SANDBOX_USERNAME=<username>
FASAHPAY_SANDBOX_PASSWORD=<password>
```

Verify:

```bash
php artisan fasah:test-auth
```

If it fails with `401`/`403`/timeout, the most common cause is that your IP is not yet whitelisted. Confirm the IP your server uses to reach the internet (`curl https://ifconfig.me` from the server).

## Phase 2: Test in the sandbox

1. Register callbacks:
   ```bash
   php artisan fasah:subscribe-callbacks --connection=sandbox
   ```
2. Create invoices for every invoice type you will use in production (`general`, `billoflading`, `declaration`, `custombroker`, `importer`, `shippingagent`). See the README for examples.
3. For each invoice, save the returned **FP number** (`fasahpayInvoiceNumber`).
4. Generate a **printable PDF of each invoice, showing its FP number**, using your application's invoice print/export feature. Keep one PDF per invoice type tested.
5. Confirm the callbacks arrive (`InvoiceNotificationReceived`, `SettlementNotificationReceived`) and your listeners handle them.

## Phase 3: Request production go-live

Fasah reviews the sandbox results before issuing production access. Reply to the same thread with the FP numbers and PDFs.

### Email template (go-live)

```text
Subject: Fasah Pay API Integration - Sandbox Testing Completed, Request for Production Credentials

Dear Fasah Pay Team,

We have completed sandbox testing for our integration.

Company name: <COMPANY NAME>
VAT number: <15-DIGIT VAT>

Test invoices (FP numbers):
- General:        <FP NUMBER>
- Bill of lading: <FP NUMBER>
- Declaration:    <FP NUMBER>
- Custom broker:  <FP NUMBER>
- Importer:       <FP NUMBER>
- Shipping agent: <FP NUMBER>

Attached are the printed PDF copies of these invoices showing the FP numbers.

Please:
1. Whitelist the following IP address(es) for the production environment:
   - <PRODUCTION SERVER PUBLIC IP>
2. Provide our production Client ID and Client Secret.

We will use our real Fasah account username and password for production authentication.

Thank you,
<NAME>
<COMPANY>
```

List only the invoice types you actually use. Attach the PDFs.

## Phase 4: Production

Fasah sends only the production **Client ID** and **Client Secret**. The **username** and **password** are those of your real account on the Fasah portal: <https://oga.fasah.sa/ar/login/1.0/#>, the same ones you use to log in there.

```dotenv
FASAHPAY_PRODUCTION_CLIENT_ID=<client_id from Fasah>
FASAHPAY_PRODUCTION_CLIENT_SECRET=<client_secret from Fasah>
FASAHPAY_PRODUCTION_USERNAME=<your Fasah portal username>
FASAHPAY_PRODUCTION_PASSWORD=<your Fasah portal password>
FASAHPAY_CALLBACK_SECRET=<long random string>
```

Then:

```bash
php artisan fasah:test-auth --connection=production
php artisan fasah:subscribe-callbacks --connection=production
```

Finally set `FASAHPAY_ENV=production`.

### Go-live checklist

- [ ] Production IP whitelisted by Fasah
- [ ] Production `client_id` / `client_secret` received
- [ ] Real portal username and password in `.env`
- [ ] `fasah:test-auth --connection=production` succeeds
- [ ] `FASAHPAY_CALLBACK_SECRET` set; callback URLs registered over public HTTPS
- [ ] `FASAHPAY_PAYMENT_METHOD=Sadad` (use `View` only for demos; it produces no payable bill)
- [ ] `FASAHPAY_ENV=production`
- [ ] First live invoice created and verified on the Fasah portal

## Troubleshooting

| Symptom | Likely cause |
|---|---|
| Timeout or connection refused | IP not whitelisted, or wrong environment URL |
| `401` on token request | Wrong username, password, client id or secret (check you are not mixing sandbox and production values) |
| `401` on invoice call | Handled by one automatic token refresh; if it persists, credentials or whitelist issue |
| `RequestException::isUncertain()` is true | 5xx or timeout: check the invoice status in Fasah before retrying to avoid duplicates |
| Callbacks not arriving | URL not public HTTPS, server IP not whitelisted, or wrong `X-Callback-Secret` |

Always quote the `correlationId` (`$e->correlationId()`) when contacting Fasah support.
