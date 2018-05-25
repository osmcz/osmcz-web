<?php

/** Oauth 1.0 authentication
 *
 * 1. user goes to /oauth/login
 * 2. if we have session - just log him
 * 3. else get login_token
 * 4. send user to AUTHORIZE_URL with login_token
 * 5. user returns with ?oauth_token to /oauth/callback
 * 6. we get new access_token from server
 * 7. we fetch API_URL user/details to know who is the user
 *
 * @author Pavel Zbytovský, zby.cz
 */
class Front_OauthPresenter extends Front_BasePresenter
{
    private $config;

    /** @var OAuth
     */
    private $oauth;

    const REQUEST_TOKEN_URL = 'https://www.openstreetmap.org/oauth/request_token';
    const ACCESS_TOKEN_URL = 'https://www.openstreetmap.org/oauth/access_token';
    const AUTHORIZE_URL = 'https://www.openstreetmap.org/oauth/authorize';
    const API_URL = 'https://api.openstreetmap.org/api/0.6/';


    public function startup()
    {
        parent::startup();

        $this->config = $this->context->parameters['osm_oauth'];

        $this->oauth = new OAuth($this->config['consumer_key'], $this->config['consumer_secret'], OAUTH_SIG_METHOD_HMACSHA1, OAUTH_AUTH_TYPE_URI);
        $this->oauth->disableSSLChecks();
    }

    // OAuth 1.0
    public function actionLogin($backUrl = '/komunita')
    {
        try {
            $this->getSession('oauth')->back_url = $backUrl;
            $this->getUserDetailsAndLoginUser();
            return;

        } catch (OAuthException $E) {
            // not authentized -> continue below in asking for new token
        }

        // request token
        $request_token_info = $this->oauth->getRequestToken(self::REQUEST_TOKEN_URL);

        //save secret
        $this->getSession('oauth')->login_secret = $request_token_info['oauth_token_secret'];

        //redirect
        $this->redirectUrl(self::AUTHORIZE_URL . "?oauth_token=" . $request_token_info['oauth_token']);
    }


    public function actionCallback($oauth_token)
    {
        try {

            $login_secret = $this->getSession('oauth')->login_secret;

            if (!$oauth_token) {
                echo "Error! There is no OAuth token!";
                exit;
            }

            if (!$login_secret) {
                echo "Error! There is no OAuth secret!";
                exit;
            }

            $this->oauth->enableDebug();
            $this->oauth->setToken($oauth_token, $login_secret);

            $access_token_info = $this->oauth->getAccessToken(self::ACCESS_TOKEN_URL);

            $this->getSession('oauth')->login_secret = false;
            $this->getSession('oauth')->token = $access_token_info['oauth_token'];
            $this->getSession('oauth')->secret = $access_token_info['oauth_token_secret'];

            $this->getUserDetailsAndLoginUser();

        } catch (OAuthException $E) {
            Debugger::log($E); //zalogujeme for sichr
            echo "OAuth login failed. Please, contact administrator.";
            $this->terminate();
        }

    }

    protected function getUserDetailsAndLoginUser()
    {
        $this->oauth->setToken($this->getSession('oauth')->token, $this->getSession('oauth')->secret);

        //fetch user datail XML
        $this->oauth->fetch(self::API_URL . "user/details");
        $user_details = $this->oauth->getLastResponse();

        $xml = simplexml_load_string($user_details);
        $user = array(
            'id' => $xml->user['id'],
            'username' => $xml->user['display_name'],
            'account_created' => $xml->user['account_created'],
            'img' => $xml->user->img['href'],
            'changesets' => $xml->user->changesets['count'],
            'traces' => $xml->user->traces['count'],
            'description' => $xml->user->description,
            'home_lat' => $xml->user->home['lat'],
            'home_lon' => $xml->user->home['lon'],
            'last_login' => date("Y-m-d H:i:s"),
        );

        // convert xml-nodes to strings
        foreach ($user as &$val)
            $val = strval($val);

        $user['account_created'] = new DateTime($user['account_created']);

        // update db
        $row = dibi::fetch('SELECT * FROM users WHERE id = %i', $user['id']);
        if ($row) {
            //better dont change usernames, we use it as primary key
            unset($user['username']);
            dibi::query('UPDATE users SET ', $user, ' WHERE id = %i', $user['id']);

        } else {
            $user['first_login'] = new DateTime();
            dibi::query('INSERT INTO users ', $user);
        }

        // load complete row from db
        $dbuser = dibi::fetch('SELECT * FROM users WHERE id = %i', $user['id']);

        if ($dbuser['webpages'] != 'admin' AND $dbuser['webpages'] != 'all')
            $dbuser['webpages'] = '14' . ($dbuser['webpages'] ? ',' : '') . $dbuser['webpages'];

        $this->user->login(new Identity($dbuser['username'], array($dbuser['webpages'] == 'admin' ? 'admin' : 'user'), $dbuser));

        // remove all tokens - TODO if tokens to be used, save them in DB

        $this->redirectUrl('//' . $_SERVER['HTTP_HOST'] . $this->getSession('oauth')->back_url);
    }
}


/*
<osm version="0.6" generator="OpenStreetMap server">
  <user id="162287" display_name="zby-cz" account_created="2009-08-24T13:40:24Z">
    <description>[**Pavel Zbytovský** - my personal homepage http://zby.cz/](http://zby.cz)
            pavel@zby.cz ~ [wiki](http://wiki.openstreetmap.org/wiki/User:Zby-cz) ~ Prague, Czech Rep.

            www.openstreetmap.cz ~ http://github.com/osmcz

            I prefer mail communication to message system.&lt;br&gt;Preferuji tykání a mailem - ne přes vnitřní zprávy.</description>
    <contributor-terms agreed="true" pd="true">
    <img href="http://api.openstreetmap.org/attachments/users/images/000/162/287/original/ff90b90420b4795f053c2e33d49688a1.jpg">
    <roles></roles>
    <changesets count="313">
    <traces count="28">
    <blocks>
      <received count="0" active="0">
    </blocks>
    <home lat="50.092247972069" lon="14.321143531209" zoom="3">
    <languages>
      <lang>en</lang>
    </languages>
    <messages>
      <received count="16" unread="0">
      <sent count="24">
    </messages>
</osm>
 */
