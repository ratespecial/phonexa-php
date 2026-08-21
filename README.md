# Phonexa

PHP API client for the [Phonexa](https://phonexa.com) ping-tree platform, built on
[Saloon](https://docs.saloon.dev).

Phonexa is made up of several sub-services. Each gets its own namespace, connector and
credentials under `src/`. The first one implemented is **LMS Sync** — posting leads to be sold.

## Requirements

- PHP 8.3+

## Installation

```bash
composer require ratespecial/phonexa
```

### Laravel

The service provider is auto-discovered. Publish the config if you want to edit it directly:

```bash
php artisan vendor:publish --provider="Ratespecial\Phonexa\ServiceProvider\PhonexaServiceProvider"
```

Then set your credentials:

```dotenv
PHONEXA_LMS_SYNC_BASE_URL=https://leads-inst1-client.phonexa.com
PHONEXA_LMS_SYNC_API_ID=your-api-id
PHONEXA_LMS_SYNC_API_PASSWORD=your-api-password

# Optional
PHONEXA_LMS_SYNC_PRODUCT_ID=218
PHONEXA_LMS_SYNC_TEST_MODE=false
```

Note the base URL is the `leads-` host and is specific to your Phonexa instance — it is not
the `cp-` host the API documentation itself is served from.

## Basic usage

### With Laravel

```php
use Ratespecial\Phonexa\LmsSync\LmsSyncService;
use Ratespecial\Phonexa\LmsSync\Models\Lead;

public function __construct(private readonly LmsSyncService $phonexa) {}

public function sell(): void
{
    $lead = new Lead([
        'productId'     => 218,
        'price'         => 0.01,
        'firstName'     => 'John',
        'lastName'      => 'Smith',
        'email'         => 'john.n@yahoo.com',
        'cellPhone'     => '5555555555',
        'address'       => 'my address',
        'zip'           => '90210',
        'dob'           => '1980-10-21',
        'unsecuredDebt' => 10000,
    ]);

    $response = $this->phonexa->postLead($lead);

    if ($response->isSold()) {
        // $response->leadId, $response->price, $response->redirectUrl
    }
}
```

### Without Laravel

```php
use Ratespecial\Phonexa\LmsSync\LmsSyncConnector;
use Ratespecial\Phonexa\LmsSync\LmsSyncService;

$connector = new LmsSyncConnector(
    baseUrl: 'https://leads-inst1-client.phonexa.com',
    apiId: 'your-api-id',
    apiPassword: 'your-api-password',
);

$service = new LmsSyncService($connector);
```

## Leads

Phonexa defines only four universal system fields — `apiId`, `apiPassword`, `productId` and
`tPar`. Every other field is ad-hoc and differs per product, so `Lead` does not whitelist
field names. Set whatever the product's API doc lists:

```php
$lead = new Lead();

$lead->productId = 218;
$lead->firstName = 'John';           // ad-hoc, stored in the field bag
$lead->unsecuredDebt = 10000;
$lead->set('consentEmailSms', 'YES');

$lead->setTPar('affiliateId', '123'); // pass-through, forwarded to buyers but not stored
```

**Phonexa field names are case sensitive.** `firstName` works, `firstname` does not.

Credentials are supplied by the connector, so leads normally omit them. Setting them on a
lead overrides the connector for that one post — useful when a single application posts to
more than one Phonexa account:

```php
$lead->apiId = 'other-account-id';
$lead->apiPassword = 'other-account-password';
```

### Using your own model

`Lead` is a convenience, not a requirement. Anything implementing `ProvidesLeadData` can be
posted — an Eloquent model, a DTO, a form object:

```php
use Ratespecial\Phonexa\LmsSync\Contracts\ProvidesLeadData;

class DebtApplication extends Model implements ProvidesLeadData
{
    public function getLeadData(): array
    {
        return [
            'productId'     => 218,
            'price'         => 0.01,
            'firstName'     => $this->first_name,
            'lastName'      => $this->last_name,
            'email'         => $this->email,
            'dob'           => $this->date_of_birth->format('Y-m-d'),
            'unsecuredDebt' => $this->debt_total,
        ];
    }
}

$response = $service->postLead($application);
```

## Outcomes

Phonexa answers with HTTP 200 whatever happens and reports the result in a `status` field:

| Status | Meaning | Result |
|---|---|---|
| 1 | Sold | `$response->isSold()`, with `leadId`, `price` and `redirectUrl` |
| 2 | Reject | `$response->isRejected()` |
| 3 | In progress | `$response->isInProgress()` — poll for the outcome |
| 4 | Validation or auth failure | throws `LeadValidationException`, or `DuplicateLeadException` for a re-post |
| 5 | Unknown lead | throws `LeadNotFoundException` |

A reject means no buyer wanted the lead. That is an ordinary business outcome, so it returns
normally rather than throwing:

```php
$response = $service->postLead($lead);

if ($response->isSold()) {
    return redirect()->away($response->redirectUrl);
}

// Rejected — nothing went wrong, there was just no buyer.
```

Validation failures do throw, and carry Phonexa's field-level errors:

```php
use Ratespecial\Phonexa\LmsSync\Exceptions\LeadValidationException;

try {
    $service->postLead($lead);
} catch (LeadValidationException $e) {
    $e->getMessage();  // 'Phonexa rejected the request: email: required'
    $e->getErrors();   // [['email' => 'required']]
}
```

Phonexa reports an already-submitted lead as an ordinary status 4, so a duplicate is a
`DuplicateLeadException` — a subclass of `LeadValidationException`. Catch it first to treat a
re-post differently from a malformed lead:

```php
use Ratespecial\Phonexa\LmsSync\Exceptions\DuplicateLeadException;
use Ratespecial\Phonexa\LmsSync\Exceptions\LeadValidationException;

try {
    $service->postLead($lead);
} catch (DuplicateLeadException $e) {
    // Already sent — {"status":4,"errors":[{"Duplicate Application":"Duplicate Application"}]}
} catch (LeadValidationException $e) {
    // Something is actually wrong with the lead.
}
```

## Async posting

Set `closeConnection` to hand the lead off in the background, then poll for the result:

```php
$lead->closeConnection = 1;

$posted = $service->postLead($lead);

$final = $service->waitForLeadStatus($posted->leadId, timeout: 120);

if ($final->isSold()) {
    // $final->redirectUrl
}
```

`waitForLeadStatus()` follows Phonexa's recommended intervals (1s for the first 10 seconds,
then 2s, 3s and 5s) and throws `LeadStatusTimeoutException` if the auction has not settled by
the deadline. It blocks, so in Laravel it belongs in a queued job rather than a web request.

To poll on your own schedule, call `checkLeadStatus()` directly.

## Test mode

`testMode` validates a lead end to end without selling it. Set it per lead, or flip the whole
environment via `PHONEXA_LMS_SYNC_TEST_MODE=true`:

```php
$lead->testMode = 1;
$lead->testSold = 1;  // force a sold response
```

## Retries

The connector retries network failures and 5xx responses three times with exponential backoff
— **except when posting a lead**. Phonexa offers no idempotency key, so a replayed
`POST /lead/` can be auctioned and sold a second time. A duplicate sale is worse than a failed
post, so `PostLead` fails fast and only the read-only status check retries.

## Using requests directly

The service is a thin wrapper; requests can be sent on the connector when you need the raw
response:

```php
use Ratespecial\Phonexa\LmsSync\Requests\PostLead;

$response = $connector->send(new PostLead($lead));

$response->status();   // HTTP status
$response->json();     // raw body
$response->dto();      // PostLeadResponse
```

Status 4 and 5 still throw, because that check lives in the request rather than the service.

## Running tests

```bash
composer test          # phpunit
composer check-style   # pint --test
composer phpstan
composer qa            # fix-style, test, phpstan
```

The suite is fully mocked and needs no credentials. `tests/LiveTest.php` is excluded from the
suite and hits a real Phonexa instance; copy `tests/.env.example` to `tests/.env`, fill it in,
and run it by hand:

```bash
./vendor/bin/phpunit tests/LiveTest.php
```

Every lead it posts sets `testMode=1`.

## Support

<https://github.com/ratespecial/phonexa-php/issues>
