Midtrans Snap Payment Architecture Specification

Objective

Implement Midtrans Snap payment integration in Laravel 13 using Service Layer architecture.

Implementation MUST:

Follow Midtrans Snap official workflow.

Support payment method replacement.

Be safe against duplicate webhooks.

Be safe against multiple retries.

Be idempotent.

Prevent duplicate order payments.

Keep payment logic entirely inside backend services.

Avoid placing business logic inside Livewire components.

Use standard Laravel web routes for webhook endpoint.

Support invoice page resume payment experience.

Business Rules

Rule 1 - Single Order

An Order represents a single invoice.

Example:

INV-20260605-001 

Order can have multiple payment attempts.

Order can only become PAID once.

Rule 2 - Multiple Payment Attempts

Each order may create multiple payment attempts.

Examples:

Attempt #1

BNI VA

Attempt #2

BRI VA

Attempt #3

QRIS

Only one attempt can be ACTIVE at any given time.

Rule 3 - Resume Existing Payment

When user clicks:

Pay Now 

System MUST:

Search active pending attempt.

If found: 

Return existing snap token.

Return existing payment information.

Must NOT create a new Snap transaction.

Example:

User opens invoice. Attempt #1 exists. Status = pending. User clicks Pay Now. Result: Reuse Attempt #1. 

Rule 4 - Change Payment Method

Invoice page may expose:

Change Payment Method 

When clicked:

Current active attempt becomes inactive.

New payment attempt created.

New Midtrans order_id generated.

New Snap token generated.

New attempt becomes active.

Example:

Attempt #1 BNI VA active=true User changes to BRI VA Attempt #1 active=false Attempt #2 BRI VA active=true 

Rule 5 - Source of Truth

Payment attempt table is the source of truth.

Never trust frontend state.

Never trust browser session.

Never trust Livewire state.

All payment decisions must come from database.

Database Design

orders

id invoice_number customer_id grand_total payment_state created_at updated_at 

payment_state values:

unpaid waiting_payment paid expired cancelled 

payment_attempts

id order_id attempt_no midtrans_order_id snap_token redirect_url payment_type bank va_number bill_key biller_code transaction_status fraud_status gross_amount is_active superseded_at paid_at expired_at created_at updated_at 

webhook_logs

id provider external_id event_name payload_json signature processed_at created_at 

Purpose:

audit

debugging

replay

duplicate detection

Midtrans Order ID Strategy

Never reuse Midtrans order_id.

Format:

INV-20260605-001-A1 INV-20260605-001-A2 INV-20260605-001-A3 

Where:

A1 = Attempt 1 A2 = Attempt 2 

This guarantees uniqueness.

Service Layer Structure

app/ └── Services/ └── Payments/ ├── MidtransService.php ├── PaymentAttemptService.php ├── InvoicePaymentService.php └── MidtransWebhookService.php 

No payment business logic inside:

Livewire Controllers Blade Javascript 

Those layers only call services.

Invoice Page UX

Initial State

Status: Unpaid Button: Pay Now 

Active Attempt Exists

Status: Waiting Payment Method: BRI Virtual Account VA Number: 1234567890 Expired: 2026-06-06 23:59 Buttons: Continue Payment Change Payment Method 

Paid State

Status: Paid Paid At: 2026-06-05 12:00 Buttons: Download Invoice 

No payment button visible.

Payment Creation Flow

Invoice Page → Click Pay Now → Search active attempt Found? YES → Return existing attempt NO → Create new attempt → Request Snap Token → Save Database → Return attempt 

Payment Method Change Flow

Invoice → Change Payment Method → Lock transaction → Deactivate old attempt → Create new attempt → Request Snap → Save attempt → Return new attempt 

Must execute inside DB transaction.

Webhook Endpoint

Use web routes.

Example:

Route::post( '/webhooks/midtrans', MidtransWebhookController::class ); 

Do NOT use api.php.

Reason:

simpler middleware management

simpler deployment

no API versioning requirements

Webhook URL

Example:

https://domain.com/webhooks/midtrans 

Must be publicly accessible.

Must not require authentication.

Must not require CSRF.

Example middleware:

->withoutMiddleware([ VerifyCsrfToken::class ]); 

Webhook Verification

Must verify:

order_id status_code gross_amount signature_key 

Using Midtrans official signature algorithm.

Reject invalid signatures.

Return HTTP 403.

Idempotency Requirements

Webhook may arrive:

twice

three times

ten times

System MUST produce same final state.

Implementation requirement:

DB transaction Row locking Unique constraints Webhook log 

Duplicate webhook processing must not:

create duplicate payments

change paid order twice

generate duplicate events

Settlement Handling

When transaction status:

settlement 

or

capture 

Then:

Order -> PAID 

Only if order is not already paid.

Superseded Attempt Handling

Example:

Attempt #1 BNI

Attempt #2 BRI

Attempt #2 active

Later user pays Attempt #1.

System behavior:

IF order unpaid:

Accept payment.

Order becomes paid.

Deactivate all attempts.

IF order already paid:

Ignore.

Log event.

Do not modify order.

This avoids losing customer payment.

Polling Support

Invoice page may poll backend.

Recommended interval:

30 seconds 

Backend should call:

Midtrans Get Transaction Status API

Only for pending payments.

Never poll paid invoices.

Environment Variables

MIDTRANS_IS_PRODUCTION=false MIDTRANS_SERVER_KEY= MIDTRANS_CLIENT_KEY= MIDTRANS_MERCHANT_ID= MIDTRANS_SANITIZED=true MIDTRANS_3DS=true MIDTRANS_NOTIFICATION_URL=https://domain.com/webhooks/midtrans 

Acceptance Criteria

Implementation is considered complete only if:

User can create payment.

Existing payment can be resumed.

Existing VA appears on invoice page.

User can change payment method.

Old attempt becomes inactive.

New attempt becomes active.

Duplicate webhook causes no side effects.

Retry webhook causes no side effects.

Order cannot become paid twice.

Invoice correctly shows payment state.

Service layer contains all payment logic.

No business logic exists inside Livewire components.

Midtrans sandbox and production both supported.

Complete automated tests exist for: 

create payment

resume payment

change payment method

webhook settlement

duplicate webhook

superseded attempt payment

