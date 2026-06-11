<?php

/**
 * Facebook Oauth Class
 *
 * @package Krypto
 * @author Ovrley <hello@ovrley.com>
 */
class FacebookOauth extends MySQL
{

    /**
     * User object
     * @var User
     */
    private $user = null;

    /**
     * App object
     * @var App
     */
    private $App = null;

    /**
     * Provider oauth
     * @var Object
     */
    private $provider = null;

    /**
     * OAuth token
     * @var String
     */
    private $token = null;

    /**
     * Ressource owner
     * @var Object
     */
    private $ressourceOwner = null;

    /**
     * FacebookOauth constructor
     * @param User $user User object
     * @param App $App   App object
     */
    public function __construct($user = null, $App = null)
    {

        if (is_null($App)) {
            $this->App = new App(true);
        } else {
            $this->App = $App;
        }

        if (is_null($user)) {
            throw new Exception("Error : Facebook Oauth require User instance", 1);
        }

        $this->user = $user;
    }

    /**
     * Get Oauth name
     * @return String Oauth name
     */
    public function _getOauthName()
    {
        return 'facebook';
    }

    /**
     * Get user object
     * @return User User object
     */
    public function _getUser()
    {
        if (is_null($this->user)) {
            throw new Exception("Error : Facebook Oauth, user is null", 1);
        }
        return $this->user;
    }

    /**
     * Get app object
     * @return App App object
     */
    public function _getApp()
    {
        if (is_null($this->App)) {
            $this->App = new App(true);
        }
        return $this->App;
    }

    /**
     * Get oauth provider
     * @return Object Facebook oauth provider
     */
    public function _getProvider()
    {
        // Check if provider is not already saved
        if (!is_null($this->provider)) {
            return $this->provider;
        }

        $this->provider = new League\OAuth2\Client\Provider\Facebook([
          'clientId' => $this->_getApp()->_getFacebookAppID(),
          'clientSecret' => $this->_getApp()->_getFacebookAppSecret(),
          'redirectUri' => APP_URL.'/app/modules/kr-facebookoauth/src/actions/callback.php',
          'graphApiVersion' => 'v2.12',
          'fields' => [
            'id',
            'name',
            'email',
            'picture.height(400){url,is_silhouette}'
          ]
        ]);
        return $this->_getProvider();
    }

    /**
     * Generate new authorization url
     * @return String Authorization url
     */
    public function _getAuthorizationUrl()
    {
        // Generate new authorization url
        $permissions = ['email', 'public_profile'];
        $oauthUrl = $this->_getProvider()->getAuthorizationUrl([
          'scope' => $permissions
        ]);

        $_SESSION['krypto_oauth_facebook_state'] = $this->_getProvider()->getState();

        return $oauthUrl;
    }

    /**
     * Parse oauth callback
     * @param  Array $callback  Oauth callback
     */
    public function _parseCallback($callback = null)
    {
        if (is_null($callback)) {
            $callback = $_GET;
        }
        if (empty($callback)) {
            throw new Exception("Error : Facebook Oauth can't be parsed", 1);
        }
        if (!empty($callback['error'])) {
            throw new Exception(htmlspecialchars($callback['error'], ENT_QUOTES, 'UTF-8'), 1);
        }
        if (empty($callback['state']) || !isset($_SESSION['krypto_oauth_facebook_state']) || ($callback['state'] !== $_SESSION['krypto_oauth_facebook_state'])) {
            throw new Exception("Error : Facebook Oauth, state can't be checked", 1);
        }
        if (empty($callback['code'])) {
            throw new Exception("Error : Facebook Oauth code is missing", 1);
        }

        unset($_SESSION['krypto_oauth_facebook_state']);
        $this->_loadToken($callback['code']);
        return $this->_getUser()->_oauthCallbackID($this);
    }

    /**
     * Load token
     * @param  String $code Token code
     */
    private function _loadToken($code)
    {
      $this->token = $this->_getProvider()->getAccessToken('authorization_code', [
        'code' => $code
      ]);
    }

    /**
     * Get token
     * @return Object Token
     */
    private function _getToken()
    {
        return $this->token;
    }

    /**
     * Get ressource owner
     * @return Object Ressource owner
     */
    private function _getRessourceOwner()
    {
        if (!is_null($this->ressourceOwner)) {
            return $this->ressourceOwner;
        }
        $this->ressourceOwner = $this->_getProvider()->getResourceOwner($this->_getToken());
        return $this->ressourceOwner;
    }

    /**
     * Get name
     * @return String User name
     */
    public function _getName()
    {
        return $this->_getRessourceOwner()->getName();
    }

    /**
     * Get user email
     * @return String User email
     */
    public function _getEmail()
    {
        return $this->_getRessourceOwner()->getEmail();
    }

    /**
     * Get user avatar
     * @return String User avatar path
     */
    public function _getAvatar()
    {
        return $this->_getRessourceOwner()->getPictureUrl();
    }

    /**
     * Get user id
     * @return Int
     */
    public function _getId(){
      return $this->_getRessourceOwner()->getId();
    }
}
