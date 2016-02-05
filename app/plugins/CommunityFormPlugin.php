<?php

/**
 * nPress - opensource cms
 *
 * @copyright  (c) 2012 Pavel Zbytovský (pavel@zby.cz)
 * @link       http://npress.zby.cz/
 * @package    nPress
 */
class CommunityFormPlugin extends AppForm
{
    static $events = array();

    public function __construct()
    {
        parent::__construct();

        $this->addText('email', 'E-mail')
            ->setOption('description', 'Email je potřeba k spočítání příspěvků v talk-cz.')
            ->addRule(Form::FILLED, '%label není vyplněn.')
            ->addRule(Form::EMAIL, '%label není validní.');

        $this->addText('fullname', 'Celé jméno')
            ->setOption('description', 'Ať se poznáme!')
            ->addRule(Form::FILLED, '%label není vyplněn.')
            ->addRule(Form::MIN_LENGTH, '%label musí mít alespoň 5 znaků.', 5);

        $this->addText('twitter', 'Twitter')
            ->setOption('description', '(nepovinné) Uživatelské jméno bez zavináče');
        $this->addText('github', 'Github')
            ->setOption('description', '(nepovinné) Uživatelské jméno');


        $this->addText('about_me', 'O mně')
            ->addRule(Form::MAX_LENGTH, '%label nesmí mít více než %d znaků.', 160)
            ->setOption('description', '(nepovinné) O mně ve 160 znacích.')
            ->getControlPrototype()->maxlength(160);

        $this->addSubmit('submit', 'Uložit údaje');
        $this->onSuccess[] = callback($this, 'submitted');

        $renderer = $this->getRenderer();
        $renderer->wrappers['controls']['container'] = 'table class="table form-inline"';
        $renderer->wrappers['error']['container'] = 'ul class="bg-danger"';
        $renderer->wrappers['control']['.text'] = 'form-control';
        $renderer->wrappers['control']['.email'] = 'form-control';
        $renderer->wrappers['control']['.submit'] = 'btn btn-primary';
    }

    protected function attached($presenter)
    {
        parent::attached($presenter);

        if (!$this->isSubmitted()) {
            $row = dibi::fetch("SELECT * FROM users WHERE username = %s", $presenter->user->id);
            if ($row) $this->setValues($row);
        }
    }

    public function submitted()
    {
        if ($this->presenter->user->id) {
            dibi::query("UPDATE users SET", $this->values, "WHERE username = %s", $this->presenter->user->id); //our id is username
            $this->presenter->flashMessage('Profil upraven pro ' . $this->presenter->user->id);
        }

        $this->presenter->redirect('this');
    }
}

