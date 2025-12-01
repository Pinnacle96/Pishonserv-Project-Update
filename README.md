# Pishonserv Real Estate Automation

## Overview
- Backend powering a conversational real estate assistant on Botpress Cloud.
- Provides endpoints for availability, property data, vendor messaging, payments, CRM logging.
- Emits Botpress events: `vendor_reply`, `vendor_timeout`, `payment_success`.

## Prerequisites
- PHP 8+, cURL enabled.
- Public HTTPS domain for webhooks.
- Google Drive service account JSON on disk.
- Paystack account and secret key.

## Environment Variables
- `BOTPRESS_INCOMING_URL` (default: Botpress Events API)
- `BOTPRESS_API_TOKEN` (Botpress API token for events)
- `WHATSAPP_VERIFY_TOKEN` (Meta webhook verify token)
- `WHATSAPP_APP_SECRET` (Meta app secret for signature)
- `DRIVE_ROOT_FOLDER_ID` (Google Drive folder ID)
- `GOOGLE_DRIVE_CREDENTIALS` (path to service account JSON)
- `PAYSTACK_SECRET_KEY` (Paystack secret)

## Botpress Configuration
- Bot ID: `c889b0dc-c8e5-4e3e-a191-158721ff6b56`
- Events URL: `https://api.botpress.cloud/v1/bots/c889b0dc-c8e5-4e3e-a191-158721ff6b56/events`
- Use `BOTPRESS_API_TOKEN` for authorization on event emission.
- When Botpress calls backend endpoints, include `Authorization: Bearer <SUPERADMIN_API_KEY>`.

## WhatsApp Cloud
- Set webhook to `POST https://<domain>/api/v1/whatsapp/incoming`.
- GET verifies with `WHATSAPP_VERIFY_TOKEN`.
- POST validates `X-Hub-Signature-256` using `WHATSAPP_APP_SECRET`.

## Paystack
- Initiate with `POST /api/v1/payment/initiate` returns `{ payment_link, reference }`.
- Configure Paystack webhook: `POST https://<domain>/api/v1/payment/webhook`.

## Google Drive Logging
- Drive root folder must be shared with the service account.
- Backend creates conversation folders, activity sheets, raw and sanitized vendor message files.

## Superadmin API Key
- Store a secure API key in `users.api_key` for a `superadmin` user.
- Botpress includes this API key as a Bearer token when calling backend.

## Key Endpoints
- `POST /api/v1/availability`
- `GET /api/v1/properties/{id}`
- `POST /api/v1/property/similar`
- `POST /api/v1/conversation/create`
- `POST /api/v1/vendor/request`
- `POST /api/v1/whatsapp/incoming` (webhook)
- `POST /api/v1/payment/initiate`
- `POST /api/v1/payment/webhook`
- `POST /api/v1/crm/log-interaction`
- `POST /api/v1/vendor/check-timeouts`

## Testing Checklist
- Verify availability, property details, similar properties.
- Create conversation and check Drive artifacts.
- Send vendor request, receive WhatsApp inbound, see `vendor_reply` event.
- Simulate timeout cleanup and emit `vendor_timeout` if configured.
- Initiate payment and confirm webhook → `payment_success` event.

## Dev Notes
- Event emission implemented in `api/bootstrap.php` via `botpress_emit`.
- Config defaults in `api/config.php`; constants in `includes/config.php`.
