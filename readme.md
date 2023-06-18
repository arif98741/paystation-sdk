xenon/multisms is a universal sms sending library specially for Bangladesh. <br> You can integrate this library in your php application easily for sending sms to any Bangladeshi mobile number. <strong>This is for raw php as well.</strong>


### Installation

```
composer require xenon/multisms
```

### Sample Code

<pre>

use Xenon\Paystation\Exception\PaystationPaymentParameterException;
use Xenon\Paystation\Paystation;

require 'vendor/autoload.php';

$sender = Sender::getInstance();
try {
    $config = [
            'merchantId' => 'xxx',
            'password' => 'xxxx'
        ];
        $pay = new Paystation($config);
        $pay->setPaymentParams([
            'invoice_number' => uniqid('ddsf', true),
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
        $pay->payNow();
} catch (Exception $e) {
    var_dump($e->getMessage());
}

</pre>


#### Currently Supported SMS Gateways
* BDBulkSMS
* BulkSMSBD
* MDLSMS
* OnnoRokomSMS
* SSLSms
* MIMSMS

 We are continuously working in this open source library for adding more Bangladeshi sms gateway. If you fee something is missing then make a issue regarding that.
If you want to contribute in this library, then you are highly welcome to do that.

