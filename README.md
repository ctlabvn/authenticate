[![Build Status](https://travis-ci.org/crabstudio/authenticate.svg?branch=master)](https://travis-ci.org/crabstudio/authenticate) [![Latest Stable Version](https://poser.pugx.org/crabstudio/authenticate/v/stable)](https://packagist.org/packages/crabstudio/authenticate) [![Total Downloads](https://poser.pugx.org/crabstudio/authenticate/downloads)](https://packagist.org/packages/crabstudio/authenticate) [![License](https://poser.pugx.org/crabstudio/authenticate/license)](https://packagist.org/packages/crabstudio/authenticate) [![Latest Unstable Version](https://poser.pugx.org/crabstudio/authenticate/v/unstable)](https://packagist.org/packages/crabstudio/authenticate)
# Advance Authenticate plugin for CakePHP 3.2+

## Support me [![paypal](https://img.shields.io/badge/Donate-PayPal-green.svg)](https://www.paypal.com/cgi-bin/webscr?cmd=_donations&business=anhtuank7c%40hotmail%2ecom&lc=US&item_name=Crabstudio%20CakePHP%203%20%2d%20FlatAdmin%20Skeleton&item_number=crabstudio%2dcakephp%2dskeleton&no_note=0&currency_code=USD&bn=PP%2dDonationsBF%3abtn_donateCC_LG%2egif%3aNonHostedGuest)

## Feature

- Prevent brute force attack by IP
- Remember/Auto login

## Installation

You can install this plugin into your CakePHP application using [composer](http://getcomposer.org).

The recommended way to install composer packages is:

```
composer self-update
composer require crabstudio/authenticate
```

Or add the following lines to your application's **composer.json**:

```
"require": {
    "crabstudio/authenticate": "^1.0"
}
```
followed by the command:

```
composer update
```

## Load plugin

From command line:
```
bin/cake plugin load Authenticate
```

Or add this line to the end of file **Your_project\config\bootstrap.php**
```
Plugin::load('Authenticate');
```

## Configure

Config Auth component from **AppController.php**
```
// All config key as usual FormAuthenticate/BaseAuthenticate
// I list the different config keys only.

$this->loadComponent('Auth', [
    'authenticate' => [
        'Authenticate.Advance' => [
	        'lockout' => [
	            'retries' => 3,
	            'expires' => '5 minutes',
	            'file_path' => 'prevent_brute_force',
	            'message' => [
	                'locked' => 'You have exceeded the number of allowed login attempts. Please try again in {0}',
	                'login_fail' => 'Incorrect username or password. {0} retries remain. Please try again',
	            ]
	        ],
	        'remember' => [
	            'enable' => true,
	            'key' => 'RememberMe',
	            'expires' => '1 months',
	        ],
        ],
    ]);
```

If you want to store login information to the Cookie, login Form required `checkbox` RememberMe as bellow

Paste this function to the **AppController.php**

```
// remember to import Event to the AppController.php
use Cake\Event\Event;

public function initialize()
{
    parent::initialize();
    $this->loadComponent('Cookie');
}

public function beforeFilter(Event $event)
{
    //Automaticaly Login.
    if (!$this->Auth->user() && $this->Cookie->read('RememberMe')) {
        $user = $this->Auth->identify();
        if ($user) {
            $this->Auth->setUser($user);
        } else {
            $this->Cookie->delete('RememberMe');
        }
    }
}
```

```
// login.ctp template

<?=$this->Form->create()?>
<?=$this->Flash->render()?>
<?=$this->Form->input('username')?>
<?=$this->Form->input('password')?>
<?=$this->Form->input('RememberMe', ['checked' => false, 'type' => 'checkbox'])?>
<?=$this->Form->input(__('Login'), ['type' => 'submit'])?>
<?=$this->Form->end()?>
```
