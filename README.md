# tsoauth
Tronsalt OAuth library

## Usage:

### Create TSOauth object
```php
use ova777\TSOAuth;

$tsoauth = TSOAuth\Core::create(array(
    'auto_refresh_token' => true,
    'id' => 'OAUTH_CLIENT_ID',
    'secret' => 'OAUTH_CLIENT_SECRET',
    'host' => 'https://tronsalt.ru',
    'access_token' => '', //If exists
    'refresh_token' => '', //If exists
    'scope' => array('user.data'), //Required permissions
));
```

### Reirect to User authentication
```php
$tsoauth->goAuthorizationCode();
```

### If User click "Cancel" (denied access)
There is redirect to your REDIRECT_URI with GET parameters "error" and "error_description"
```php
if(isset($_GET['error'])) { ?>
	<b><?= $_GET['error'] ?></b><br/>
	<?= $_GET['error_description'] ?>
<?php }
```

### If User is granted access
There is redirect to your REDIRECT_URI with GET parameter "code"  
You need get "access_token" and "refresh_token" by the "code"
```php
try {
    $tsoauth->getAccessTokenByCode($_GET['code']);
    //Save $tsoauth->access_token and $tsoauth->refresh_token for this User
} catch (TSOAuth\Except $error) {
    echo $error->asString();
}
```
If you have "access_token" and "refresh_token" - you can make transactions
```php
try {
    $transaction = $tsoauth->makeTransaction('500'); //Sum
    if(isset($transaction['actions'])) {
        $commit = $tsoauth->endTransaction($transaction['actions']['commit']);
        //print_r($commit);
    }
} catch (TSOAuth\Except $error) {
    echo $error->asString();
}
```