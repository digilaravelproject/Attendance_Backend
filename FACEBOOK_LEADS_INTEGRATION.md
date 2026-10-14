# Facebook Leads Integration

This integration authorizes a Facebook account, selects the page named by
`FACEBOOK_PAGE_NAME`, imports every lead form on that page, and synchronizes its
leads into the existing `leads` table every two minutes.

## Configuration

Add these values to `.env`:

```dotenv
FACEBOOK_AUTHORIZATION_URL=https://www.facebook.com/v26.0/dialog/oauth
FACEBOOK_GRAPH_URL=https://graph.facebook.com/v26.0
FACEBOOK_PAGE_NAME="Digi Emperor-Digital Marketing Agency"
FACEBOOK_TIMEOUT=30
FACEBOOK_MAX_PAGES=100
```

Run the migration:

```bash
php artisan migrate
```

In the Meta developer console, add the exact callback URL used by the mobile
application to **Valid OAuth Redirect URIs**. It must be HTTPS and must match the
`redirect_uri` sent to the backend exactly, including path and trailing slash.

## Mobile authorization flow

### 1. Generate the authorization URL

```bash
curl --request POST "https://your-api.example.com/api/facebook/authorization-url" \
  --header "Accept: application/json" \
  --header "Content-Type: application/json" \
  --data '{
    "client_id": "YOUR_META_APP_ID",
    "client_secret": "YOUR_META_APP_SECRET",
    "redirect_uri": "https://your-api.example.com/api/facebook/callback",
    "state": "optional-mobile-reference"
  }'
```

The response contains `authorization_url`, an opaque server-generated `state`,
and `integration_id`. Open `authorization_url` in the mobile WebView. The app
secret is encrypted in the database and is never returned in the response.

For production, the app secret should be held by the backend and not embedded in
the mobile application. The current request field is retained for compatibility
with the supplied API flow.

### 2. Complete the callback

Recommended: use the backend endpoint itself as `redirect_uri`. Facebook calls:

```text
GET https://your-api.example.com/api/facebook/callback?code=...&state=...
```

That one callback automatically:

1. Exchanges the code for a user access token.
2. Fetches `/me/accounts` and selects only the configured page.
3. Saves encrypted user and page access tokens.
4. Fetches all lead forms and all pages of leads.
5. Imports the leads into the CRM.

If the mobile app owns the registered callback URL, intercept `code` and
`state`, then forward both values:

```bash
curl --request POST "https://your-api.example.com/api/facebook/callback" \
  --header "Accept: application/json" \
  --header "Content-Type: application/json" \
  --data '{"code":"CODE_FROM_FACEBOOK","state":"STATE_FROM_AUTHORIZATION_RESPONSE"}'
```

The `redirect_uri` used during code exchange is always the original stored
value, as required by Facebook.

## Lead mapping

| Facebook field | CRM field |
|---|---|
| Lead `id` | `external_id` |
| `full_name` | `full_name` |
| `email` | `email` |
| `phone_number` | `mobile_number` |
| `city` | `location` |
| Other form answers | `requirement` |
| Complete response | `source_payload` |
| `created_time` | `source_created_at` |

The lead source is `facebook_ads`. The unique database constraint on
`(source, external_id)` prevents duplicate inserts. A repeated sync refreshes
the existing record without adding another lead or another captured-history
entry.

## Two-minute synchronization

The application schedule contains `leads:sync-facebook` with
`everyTwoMinutes()` and `withoutOverlapping()`.

For a local verification session, keep this running:

```bash
php artisan schedule:work
```

For production, configure the server scheduler to run Laravel's scheduler once
per minute:

```text
* * * * * cd /path/to/Attendance_Backend && php artisan schedule:run
```

The sync can also be tested immediately:

```bash
php artisan leads:sync-facebook
php artisan leads:sync-facebook --integration=1
```

## Verification checklist

1. Run `php artisan migrate`.
2. Confirm the two-minute entry with `php artisan schedule:list`.
3. Generate an authorization URL and complete Facebook login in the WebView.
4. Confirm the callback response names the expected page and reports sync counts.
5. Query `GET /api/admin/leads?source=facebook_ads` with an admin Bearer token.
6. Run `php artisan leads:sync-facebook` twice. The second run may report the
   lead as refreshed, but the database lead count must not increase.
7. Inspect `storage/logs/laravel.log` and the integration's `last_error` if a
   Graph request fails.

Automated coverage is available with:

```bash
php artisan test --filter=FacebookLeadIntegrationTest
```
