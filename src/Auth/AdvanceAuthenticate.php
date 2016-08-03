<?php
namespace Authenticate\Auth;

use Cake\Auth\BaseAuthenticate;
use Cake\Controller\ComponentRegistry;
use Cake\Controller\Component\CookieComponent;
use Cake\Event\Event;
use Cake\Filesystem\File;
use Cake\Filesystem\Folder;
use Cake\I18n\Time;
use Cake\Network\Request;
use Cake\Network\Response;
use Cake\Utility\Hash;

class AdvanceAuthenticate extends BaseAuthenticate
{
    /**
     * default config
     */
    protected $_defaultConfig = [
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
        'userModel' => 'Users',
        'scope' => [],
        'finder' => 'all',
        'contain' => null,
        'passwordHasher' => 'Default'
    ];

    /**
     * Constructor.
     *
     * @param \Cake\Controller\ComponentRegistry $registry The Component registry used on this request.
     * @param array $config Array of config to use.
     */
    public function __construct(ComponentRegistry $registry, array $config = [])
    {
        $this->_registry = $registry;
        $this->config($config);
        if ($this->_config['remember']['enable']) {
            if (empty($this->_registry->Cookie) || !$this->_registry->Cookie instanceof CookieComponent) {
                throw new \RuntimeException('You need to load the CookieComponent.');
            }
        }
        $this->_removeExpiredLock();
    }

    /**
     * Authenticate a user based on the cookies information.
     *
     * @param \Cake\Network\Request  $request  The request instance.
     * @param \Cake\Network\Response $response The response instance.
     *
     * @return mixed
     *
     * @throws \RuntimeException When the CookieComponent is not loaded.
     */
    public function authenticate(Request $request, Response $response)
    {
        if ($this->_preventBruteForceAttack($request)) {
            $message = __($this->_config['lockout']['message']['locked'], $this->_config['lockout']['expires']);
            $this->_pushError($message);
            return false;
        }

        $fields = $this->_config['fields'];
        //Get username, password from Cookie
        $data = $this->_readCookie();
        //If Cookie doen't exist, get from request
        if (!$data || empty($data)) {
            if (!$this->_checkFields($request, $fields)) {
                return false;
            }
            $data = $request->data;
        }
        $user = $this->_findUser($data[$fields['username']], $data[$fields['password']]);
        if ($user) {
            if (!empty($request->data[$this->_config['remember']['key']]) &&
                $request->data[$this->_config['remember']['key']]) {
                $this->_writeCookie($data[$fields['username']], $data[$fields['password']]);
            }
            $this->_resetBruteForceAttack($request);

            return $user;
        }
        $this->_clearCookie();
        // Login failed, starting count
        $loginFailed = $this->_noticeBruteForceAttack($request);
        if ($loginFailed > 0) {
            $message = __($this->_config['lockout']['message']['login_fail'], $loginFailed);
        } else {
            $message = __($this->_config['lockout']['message']['locked'], $this->_config['lockout']['expires']);
        }
        $this->_pushError($message);

        return false;
    }

    /**
     * Returns a list of all events that this authenticate class will listen to.
     *
     * @return array
     */
    public function implementedEvents()
    {
        return [
            'Auth.logout' => 'logout'
        ];
    }

    /**
     * Delete cookies when an user logout.
     *
     * @param \Cake\Event\Event  $event The logout Event.
     * @param array $user The user about to be logged out.
     *
     * @return void
     */
    public function logout(Event $event, array $user)
    {
        $this->_clearCookie();
    }

    /**
     * write username, password to the Cookie
     *
     * @param string $username user name
     * @param string $password user password
     * @return bool
     */
    protected function _writeCookie($username = null, $password = null)
    {
        if (!$this->_config['remember']['enable']) {
            return false;
        }
        $fields = $this->_config['fields'];
        $this->_registry->Cookie->configKey($this->_config['remember']['key'], [
            'expires' => $this->_config['remember']['expires'],
        ]);
        $this->_registry->Cookie->write($this->_config['remember']['key'], [
            $fields['username'] => $username,
            $fields['password'] => $password
        ]);

        return true;
    }

    /**
     * read username, password from Cookie
     *
     * @return string|null|value|array
     */
    protected function _readCookie()
    {
        if (!$this->_config['remember']['enable']) {
            return null;
        }
        return $this->_registry->Cookie->read($this->_config['remember']['key']);
    }

    /**
     * clear Cookie
     *
     * @return void
     */
    protected function _clearCookie()
    {
        if (!$this->_config['remember']['enable']) {
            return;
        }
        $this->_registry->Cookie->delete($this->_config['remember']['key']);
    }

    /**
     * Checks the fields to ensure they are supplied.
     *
     * @param \Cake\Network\Request $request The request that contains login information.
     * @param array $fields The fields to be checked.
     * @return bool False if the fields have not been supplied. True if they exist.
     */
    protected function _checkFields(Request $request, array $fields)
    {
        foreach ([$fields['username'], $fields['password']] as $field) {
            $value = $request->data($field);
            if (empty($value) || !is_string($value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * prevent brute force attack
     *
     * @param \Cake\Network\Request $request The request that contains login information.
     * @return bool
     */
    protected function _preventBruteForceAttack(Request $request)
    {
        $clientIp = $request->clientIp();
        $filePath = CONFIG . $this->_config['lockout']['file_path'] . DS . $clientIp;
        if (!file_exists($filePath)) {
            return false;
        }
        $lastModified = filemtime($filePath);
        $lastModified = Time::createFromTimestamp($lastModified);
        if (!$lastModified->wasWithinLast($this->_config['lockout']['expires'])) {
            unlink($filePath);
            return false;
        }
        $count = intval(file_get_contents($filePath));
        if (!$count) {
            return false;
        }

        return $count >= $this->_config['lockout']['retries'];
    }

    /**
     * notice brute force attack from ip
     *
     * @param \Cake\Network\Request $request The request that contains login information.
     * @return int
     */
    protected function _noticeBruteForceAttack(Request $request)
    {
        $filePath = CONFIG . $this->_config['lockout']['file_path'] . DS . $request->clientIp();
        if (file_exists($filePath)) {
            $count = intval(file_get_contents($filePath)) + 1;
        } else {
            $count = 1;
        }
        file_put_contents($filePath, $count);

        return $this->_config['lockout']['retries'] - $count;
    }

    /**
     * _resetBruteForceAttack
     *
     * @param \Cake\Network\Request $request The request that contains login information.
     * @return void
     */
    protected function _resetBruteForceAttack(Request $request)
    {
        $filePath = CONFIG . $this->_config['lockout']['file_path'] . DS . $request->clientIp();
        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }

    /**
     * _removeExpiredLock
     *
     * @return void
     */
    protected function _removeExpiredLock()
    {
        // $filePath = CONFIG . $this->_config['lockout']['file_path'];
        // if (!file_exists($filePath)) {
        //     mkdir($filePath, 0777);
        // }
        // $files = scandir($filePath);
        // foreach ($files as $k => $file) {
        //     $lastModified = filemtime($filePath . DS . $file);
        //     $lastModified = Time::createFromTimestamp($lastModified);
        //     if ($lastModified->wasWithinLast($this->_config['lockout']['expires'])) {
        //         continue;
        //     }
        //     chown($filePath . DS . $file, 0777);
        //     unlink($filePath . DS . $file);
        // }
        $dir = new Folder(CONFIG . $this->_config['lockout']['file_path'], true);
        $files = $dir->find();
        foreach ($files as $fileName) {
            $file = new File($dir->pwd() . DS . $fileName);
            $lastChange = Time::createFromTimestamp($file->lastChange());
            if ($lastChange->wasWithinLast($this->_config['lockout']['expires'])) {
                continue;
            }
            $file->delete();
        }
    }

    /**
     * push error message to Flash component
     *
     * @param string $message message
     * @return bool
     */
    protected function _pushError($message = null)
    {
        if (!in_array('Flash', $this->_registry->loaded())) {
            return false;
        }
        $this->_registry->getController()->Flash->error($message);

        return true;
    }
}
