<?php


/**
 * For workshop attendants
 */
class Front_LivePresenter extends Front_BasePresenter
{
    private $liveuser;

    public function startup()
	{
		parent::startup();
        $this->liveuser = $this->getSession('liveuser');


        if($this->liveuser->data){
            barDump($this->liveuser);
			dibi::query('UPDATE live_users SET online=NOW() WHERE user_id=%i',$this->liveuser->id);
		}
	}

	public function actionDefault(){
		if(!$this->liveuser->id){
			$this->redirect("login");
		}

		$this['userForm']->setDefaults(dibi::fetch('SELECT * FROM live_users WHERE user_id=%i',$this->liveuser->id));
	}

	public function handleRefresh()
	{
		$this->invalidateControl('userlist');
		$this->invalidateControl('posts');
	}

	public function createComponentPostForm()
	{
		$form = new AppForm();
		$form->elementPrototype->class('ajax');
		$text = $form->addTextarea('text', 'Zpráva:')->controlPrototype;
		$form->addSubmit('send', 'Odeslat');
		$form->onSuccess[] = callback($this, 'postFormSubmitted');
		$form->renderer->wrappers["error"]["container"] = NULL;
		$form->renderer->wrappers["error"]["item"] = "div class='alert alert-error'";

        $text->title = "Odkaz končí mezerou";
        $text->style['width'] = "690px";
        $text->style['height'] = "30px";
		return $form;
	}

	public function postFormSubmitted(Form $form)
	{
		dibi::query("INSERT INTO live_posts", array('name'=>$this->liveuser->data->name, 'text'=>$form['text']->value));
		$this->invalidateControl('posts');
		$this->invalidateControl('postform');
		$this['postForm-text']->value = "";
		if(!$this->isAjax()){
			$this->redirect('default');
		}
	}

	public function createComponentUserForm()
	{
		$form = new AppForm();
        $form->addText('osm', 'OSM účet:');
		$form->addText('mail', 'E-mail:')	//->setRequired()
				//->addRule(Form::EMAIL)
				->setOption('description', '(neveřejné)');
		$form->addText('gc', 'GC přezdívka:');
        $form->addText('fullname', 'Vzkaz ostatním:');
		$form->addText('poznamka', 'Zpětná vazba:')
				->setOption('description', '(neveřejné)');

		$form->addSubmit('login', 'Uložit');
		$form->onSuccess[] = callback($this, 'userFormSubmitted');

		$form->renderer->wrappers["error"]["container"] = NULL;
		$form->renderer->wrappers["error"]["item"] = "div class='alert alert-error'";
		return $form;
	}

	public function userFormSubmitted(Form $form)
	{
		dibi::update('live_users', $form->values)->where('user_id=%i',$this->liveuser->id)->execute();
		$this->flashMessage("Uloženo");
		$this->redirect("default");
	}

	public function actionLogout(){
		$this->liveuser->data = false;
		$this->flashMessage("Odhlášení proběhlo úspěšně");
		$this->redirect("login");
	}

	public function createComponentLoginForm(){
		$form = new AppForm();
		$form->addText('nick', 'Přezdívka: ')
			->addRule(Form::FILLED, 'Vyplňte prosím uživatelské jméno.');
		$form->addSubmit('login', 'Přihlásit se')->getControlPrototype()->class = 'btn btn-primary margin-top-10 space-2em';
		$form->onSuccess[] = callback($this, 'loginFormSubmitted');

		$form->renderer->wrappers["error"]["container"] = NULL;
		$form->renderer->wrappers["error"]["item"] = "div class='alert alert-error'";
		return $form;
	}
	public function loginFormSubmitted($form){
		$nick = $form['nick']->value;
		$user = dibi::fetch('SELECT * FROM live_users_online WHERE name = %s', $nick);

		if(!$user){
			dibi::query('INSERT INTO live_users', array('name'=>$nick, 'registred%sql'=>'CURDATE()'));
			$user = dibi::fetch('SELECT * FROM live_users WHERE name = %s', $nick);
		}
		elseif($user->isonline) {
			$form->addError('Uživatel s tímto jménem již je online, zvolte si jiné. Pokud jste omylem zavřeli okno, počkejte 30 vteřin.');
			return;
		}

        // $this->user->login(new \Nette\Security\Identity($user->user_id, 'user', $user));
        $this->liveuser->id = $user['user_id'];
        $this->liveuser->data = $user;
		$this->redirect('default');
	}


    /**
     * Reload stats for current workshop day or all
     *
     * @param bool $all
     */
    public function actionOsmedit($all = FALSE)
    {
        @set_time_limit(3000);

        $time = microtime(true);
        $query = dibi::select('*')->from('live_users')->where('osm != ""');
        if(!$all) $query->where('registred = CURDATE() OR DATE(online) = CURDATE()');
        foreach($query as $r){
            echo "$r->osm, ";
            $o = rawurlencode($r->osm);
            if (!$o)
                continue;

            $ww = @file_get_contents("http://www.openstreetmap.org/user/$o");

            if(!$ww){
                dibi::query("UPDATE live_users SET osmedit = -404 WHERE user_id=%i",$r->user_id);
            }
            elseif(preg_match_all('~/history.*<span class=\'count-number\'>([,0-9]+)</span>~isU', $ww, $m)){
                dibi::query("UPDATE live_users SET osmedit = %s",str_replace(",","", $m[1][0])," WHERE user_id = %i",$r->user_id);
            }
        }
        echo microtime(true) - $time . " sec";

        echo "<table border=1 style=border-collapse:collapse>";
        foreach(dibi::query('SELECT * FROM live_users_online ORDER BY registred DESC, name') as $r){
            echo "<tr><td>";
            echo implode('<td>', (array)$r);
        }
        echo "</table>";


        echo "<script>setTimeout(function(){window.location.reload()}, 60*1000)</script>";
        $this->terminate();
    }
}
