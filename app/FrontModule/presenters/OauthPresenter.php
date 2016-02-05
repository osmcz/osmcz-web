<?php

class Front_OauthPresenter extends Front_BasePresenter
{
    private $config;
    private $oauth;

    const REQUEST_TOKEN_URL = 'https://www.openstreetmap.org/oauth/request_token';
    const ACCESS_TOKEN_URL = 'https://www.openstreetmap.org/oauth/access_token';
    const AUTHORIZE_URL = 'https://www.openstreetmap.org/oauth/authorize';
    const API_URL = 'http://api.openstreetmap.org/api/0.6/';


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
        $this->getSession('oauth')->back_url = $backUrl;

        try {
            $this->getUserDetailsAndLoginUser();
            return;
        } catch (OAuthException $E) {
            // OK - not authentized -> use
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

            $this->getSession('oauth')->token = $access_token_info['oauth_token'];
            $this->getSession('oauth')->secret = $access_token_info['oauth_token_secret'];

            $this->getUserDetailsAndLoginUser();

        } catch (OAuthException $E) {
            echo("Exception:\n");
            print_r($E);
            exit;
        }

    }

    protected function getUserDetailsAndLoginUser()
    {
        $this->oauth->setToken($this->getSession('oauth')->token, $this->getSession('oauth')->secret);

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

        foreach ($user as &$val)
            $val = strval($val);

        // update db
        $row = dibi::fetch('SELECT * FROM users WHERE id = %i', $user['id']);
        if ($row) {
            unset($user['username']); //better dont change usernames, we use it as primary key

            dibi::query('UPDATE users SET ', $user, ' WHERE id = %i', $user['id']);
        } else {
            $user['first_login'] = new DateTime();
            dibi::query('INSERT INTO users ', $user);
        }

        // load row from db
        $user = dibi::fetch('SELECT * FROM users WHERE id = %i', $user['id']);
        $this->user->login(new Identity($user['username'], array($user['webpages'] == 'admin' ? 'admin' : 'user'), $user));

        // remove all tokens - TODO if tokens to be used, save them in DB
        $this->getSession('oauth')->remove();

        $this->redirectUrl('//' . $_SERVER['HTTP_HOST'] . $this->getSession('oauth')->back_url);
        //$this->redirect(':Admin:Admin:');
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