# Advance Authenticate plugin for CakePHP 3.x

## Feature

- Prevent brute force attack by IP
- Remember login information

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
bin/cake plugin load crabstudio/authenticate
```

Or add this line to the end of file **Your_project\config\bootstrap.php**
```
Plugin::load('Crabstudio/Authenticate');
```

## Configure

Config Auth component from **AppController.php**
```
$this->loadComponent('Auth', [
    'authenticate' => [
        'Advance' => [
            'fields' => [
                'username' => 'username',
                'password' => 'password',
            ],
            'scope' => [
            	'flag' => 'ACTIVE'
            ],
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

If you want to store login information to the Cookie, login Form required `checkbox` RememberMe.
