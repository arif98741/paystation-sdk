xenon/paystation is a php library for Bangladeshi  payment gateway provider. You can integrate this in your php application and get customer payment using mfs, credit card and so on 


### Installation

```
composer require xenon/paystation
```

### Environment (Sandbox / Live)

Paystation runs two separate environments. Pick one with the optional
`environment` key; omit it and the library talks to the live gateway, as before.

| Environment | Value | Base URL |
|---|---|---|
| Sandbox (Test) | `sandbox` | `https://sandbox.paystation.com.bd` |
| Production (Live) | `live` | `https://api.paystation.com.bd` |

`test`, `testing`, `dev` and `development` are accepted as aliases of `sandbox`;
`production` and `prod` as aliases of `live`. Matching is case-insensitive and
ignores surrounding spaces. Anything else throws a `PaystationException`.

<pre>
$pay = new Paystation([
    'merchantId' => 'xxx',        // your sandbox credentials
    'password' => 'xxxx',
    'environment' => 'sandbox',
]);

// or switch at runtime
$pay->setEnvironment('live');

$pay->getEnvironment(); // 'live'
$pay->getBaseUrl();     // 'https://api.paystation.com.bd'
$pay->isSandbox();      // false
</pre>

Sandbox and live credentials are different — Paystation issues each set to you,
so pass whichever pair matches the environment you selected. `merchantId` and
`password` are both required; leaving either out throws a `PaystationException`
when a request is made. Construction itself never throws on missing credentials,
so binding this class in a container before your config is loaded stays safe.

#### Upgrading

This is a backward compatible addition. Existing code needs no changes — with no
`environment` key the library talks to the live gateway exactly as it did before.

# Sample Code
## Step:1  Create Payment and Redirect to Payment Url
<pre>

use Xenon\Paystation\Exception\PaystationPaymentParameterException;
use Xenon\Paystation\Paystation;

require 'vendor/autoload.php';

try {
    $config = [
        'merchantId' => 'xxx',
        'password' => 'xxxx',
        'environment' => 'sandbox', // omit for live
    ];
    $pay = new Paystation($config);
    $pay->setPaymentParams([
        'invoice_number' => 'XXXXXXXXXXXX',
        'currency' => "BDT",
        'payment_amount' => 1,
        'reference' => "102030",
        'cust_name' => "Jhon Max",
        'cust_phone' => "01700000001",
        'cust_email' => "max@gmail.com",
        'cust_address' => "Dhaka, Bangladesh",
        'callback_url' => "http://www.yourdomain.com/success.php",
        // 'checkout_items' => "orderItems"
    ]);
    $pay->payNow(); //will automatically redirect to gateway payment page
} catch (Exception $e) {
    var_dump($e->getMessage());
}
</pre>

## Step:2 Verify Payment 
<pre>
 $config = [
    'merchantId' => 'xxx',
    'password' => 'xxxx'
 ];
$pay = new Paystation($config);
$status  = $pay->verifyPayment("invoice_number","trx_id"); //this will retrieve response as json
</pre>

### sample json response for transaction verification(Success)
<pre>
    {
        "status_code": "200",
        "status": "success",
        "message": "Transaction found",
        "data": {
            "invoice_number": "ddsf648feebc415138XXXXX",
            "trx_status": "Success",
            "trx_id": "AFJ7IXXX",
            "payment_amount": 1,
            "order_date_time": "2023-06-19 11:57:04",
            "payer_mobile_no": "01750XXXX",
            "payment_method": "bKash",
            "reference": "102030",
            "checkout_items": null,
            "cust_phone": "01700000001"
        }
    }
</pre>

### sample json response for transaction verification(Failed)
<pre>
{
    "status_code": "1006",
    "status": "failed",
    "message": "Transaction not found in system"
}
</pre>


## Step:3 Merchant IPN (Instant Payment Notification)

After every **successful** transaction Paystation posts a server-to-server notification to your IPN url.
The url is configured per merchant, so you share it with Paystation once - there is no `ipn_url` parameter
on create-payment. Failed, cancelled and pending transactions are never reported here.

The notification carries **no signature and no authentication** (the gateway documents it as `Auth: None`),
so anyone who learns your url can post to it. Treat the body as untrusted input.

### Reading the notification

<pre>

use Xenon\Paystation\Ipn\IpnHandler;
use Xenon\Paystation\Ipn\IpnResponse;
use Xenon\Paystation\Exception\PaystationIpnException;

require 'vendor/autoload.php';

$handler = new IpnHandler();

try {
    //reads php://input, or pass the body yourself: $handler->capture($body)
    $ipn = $handler->capture();

    $order = findOrderByInvoice($ipn->invoiceNumber());

    if (!$order) {
        //permanently unusable, so acknowledge rather than collect retries
        IpnResponse::rejected('unknown invoice')->send();
        return;
    }

    //trx_status === 'Success' and the amount matches what you initiated
    $handler->validate($ipn, $order['amount']);

    //idempotency is yours: this is the one documented check the library
    //cannot do, because it needs your order state
    if ($order['status'] === 'paid') {
        IpnResponse::acknowledged()->send();
        return;
    }

    markOrderPaid($order, $ipn->trxId(), $ipn->paymentMethod(), $ipn->orderDateTimeString());

    IpnResponse::acknowledged()->send();
} catch (PaystationIpnException $e) {
    //the exception knows which status keeps the gateway from retrying
    IpnResponse::fromException($e)->send();
}
</pre>

### Confirming against the gateway

Because the notification is unsigned, the only check that does not rely on trusting the request is asking
the gateway itself. `confirm()` calls `retrive-transaction` with your own credentials and refuses unless the
transaction exists, succeeded, and carries the same `trx_id` and amount:

<pre>
$paystation = new Paystation([
    'merchantId' => 'xxx',
    'password' => 'xxxx',
    'environment' => 'live',
]);

$handler = new IpnHandler($paystation);

$ipn = $handler->capture();
$handler->validate($ipn, $order['amount']);
$handler->confirm($ipn);   //throws PaystationIpnException when not confirmed
</pre>

It costs one round trip, and the documentation asks for a fast acknowledgement - so either use it where a
wrong answer is expensive, or acknowledge first and confirm in a background job.

Optionally restrict by source address. Paystation does not publish its sending ips, so ask them for the
current list first - an out of date allow-list rejects real payments:

<pre>
$handler->trustIps(['203.0.113.7', '198.51.100.0/24']);
$handler->validate($ipn, $order['amount'], $_SERVER['REMOTE_ADDR']);
</pre>

### Notification fields

| Method | Field | Notes |
|---|---|---|
| `invoiceNumber()` | `invoice_number` | your order reference, use it to match the order |
| `trxStatus()` | `trx_status` | always `Success` for this notification |
| `trxId()` | `trx_id` | gateway transaction id, keep it for reconciliation |
| `amount()` | `trx_amount` | returned as `float`, in BDT |
| `orderDateTimeString()` | `order_date_time` | raw, format `Y-m-d H:i:s` |
| `orderDateTime()` | `order_date_time` | `DateTimeImmutable`, or `null` when unparsable |
| `paymentMethod()` | `payment_method` | Nagad, bKash, Rocket, Visa, ... |
| `reference()` | `reference` | optional, `null` when absent |
| `get()` / `toArray()` | any | including fields this library does not know |
| `isSuccess()` | - | `trx_status` is `Success`, trimmed and case insensitive |
| `matchesAmount($expected)` | - | numeric comparison with tolerance, not string equality |
| `loggable()` | - | the fields worth putting in a log line |

The gateway masks `trx_id` as `****` in the documentation examples; a real notification carries the actual id.

### Answering the gateway

The status code is the whole protocol: Paystation stops on `2xx` and retries on `4xx`, `5xx` and timeouts.

| Helper | Status | Meaning |
|---|---|---|
| `IpnResponse::acknowledged()` | 200 | processed, or already processed - no further attempt |
| `IpnResponse::retry($reason)` | 503 | your side failed, please deliver again |
| `IpnResponse::rejected($reason)` | 200 | permanently unusable, stop retrying |
| `IpnResponse::fromException($e)` | from the exception | the status that fits the failure |

`send()` sets the status, the json content type and echoes the body. It deliberately does **not** call `exit`,
so work you deferred until after acknowledging still runs. In a framework, use `statusCode()` and `body()`
and build your own response instead.

`rejected()` answers `200` on purpose - a body that can never be processed should not be redelivered for the
rest of the retry window. Pass a 4xx yourself if you would rather the gateway kept trying.

#### Important Methods
* setPaymentParams()
* payNow()
* verifyPayment()
* setEnvironment()
* getEnvironment()
* getBaseUrl()
* isSandbox()
* IpnHandler::capture(), validate(), confirm(), trustIps()

This library is still in beta version and if you are interested to contribute this , we highly encourage you. Make a fork of this repository
and give send a pull request. If you face any issues or error during development or after deployment, you should crate an issue

