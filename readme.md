xenon/paystation is a php library for Bangladeshi  payment gateway provider. You can integrate this in your php application and get customer payment using mfs, credit card and so on 


### Installation

```
composer require xenon/paystation
```

### Sample Code

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
    $pay->payNow();
} catch (Exception $e) {
    var_dump($e->getMessage());
}

</pre>


#### Important Methods
* setPaymentParams()
* payNow()

This library is still in beta version and if you are interested to contribute this , we highly encourage you. Make a fork of this repository
and give send a pull request. If you face any issues or error during development or after deployment, you should crate an issue

