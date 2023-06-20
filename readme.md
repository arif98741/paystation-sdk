xenon/paystation is a php library for Bangladeshi  payment gateway provider. You can integrate this in your php application and get customer payment using mfs, credit card and so on 


### Installation

```
composer require xenon/paystation
```
# Sample Code
## Step:1  Create Payment and Redirect to Payment Url
<pre>

use Xenon\Paystation\Exception\PaystationPaymentParameterException;
use Xenon\Paystation\Paystation;

require 'vendor/autoload.php';

try {
    $config = [
        'merchantId' => 'xxx',
        'password' => 'xxxx'
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


#### Important Methods
* setPaymentParams()
* payNow()
* verifyPayment()

This library is still in beta version and if you are interested to contribute this , we highly encourage you. Make a fork of this repository
and give send a pull request. If you face any issues or error during development or after deployment, you should crate an issue

