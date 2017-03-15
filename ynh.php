<?php
namespace Grav\Plugin;

use Grav\Common\File\CompiledYamlFile;
use Grav\Common\Grav;
use Grav\Common\Plugin;
use Grav\Common\User\User;

class YnhPlugin extends Plugin
{
    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            'onPluginsInitialized'  => ['checkUsers', 1],
        ];
    }

    /**
     * Check if at least one user exists
     * If not, create one based on ynh infos
     */
    public function checkUsers()
    {
        if (!$this->isAdminPath()) {
            return;
        }

        // check for existence of a user account
        $account_dir = $this->grav['locator']->findResource('account://');
        $user_check  = (array) glob($account_dir . '/*.yaml');

        // If no users found, create the first one
        if (!count($user_check) > 0) {
            if (!$username = $this->createUserFromYnh()) {
                $this->grav['log']->error('User creation failed, credentials missing');
                throw new \RuntimeException('User creation failed, credentials missing');
            }
            $this->authenticateAndRedirectToAdminPanel($username);
        }
    }

    /**
     * Create admin user for Yunohost install
     */
    protected function createUserFromYnh()
    {
        $auth     = HttpbasicauthPlugin::extractFromHeaders();
        $username = $auth['username'];

        if (empty($username)) {
            $this->grav['log']->info('HTTP basic auth seems empty');
            return false;
        }

        $user = new User([
            'password' => $auth['password'],
            'email'    => !empty($_SERVER['HTTP_EMAIL']) ? $_SERVER['HTTP_EMAIL'] : '',
            'fullname' => !empty($_SERVER['HTTP_NAME']) ? $_SERVER['HTTP_NAME'] : '',
            'title'    => 'Administrator',
            'state'    => 'enabled',
            'access'   => ['admin' => ['login' => true, 'super' => true], 'site' => ['login' => true]],
        ]);
        $file = CompiledYamlFile::instance($this->grav['locator']->findResource('user://accounts/' . $username . YAML_EXT, true, true));
        $user->file($file);
        $user->save();

        return $username;
    }

    /**
     * Connect and redirect user to admin panel
     *
     * @param  string $username
     */
    protected function authenticateAndRedirectToAdminPanel($username)
    {
        // Auth
        $user = User::load($username);
        $this->grav['session']->user = $user;
        unset($this->grav['user']);
        $this->grav['user'] = $user;
        // Redirect
        $route = $this->config->get('plugins.admin.route');
        $base  = '/' . trim($route, '/');
        $this->grav->redirect($base);
    }

    /**
     * Are we browsing admin panel ?
     *
     * @return boolean
     */
    protected function isAdminPath()
    {
        $base    = '/' . trim($this->config->get('plugins.admin.route'), '/');
        $current = $this->grav['uri']->route();
        return $current === $base || substr($current, 0, strlen($base) + 1) === $base . '/';
    }
}
