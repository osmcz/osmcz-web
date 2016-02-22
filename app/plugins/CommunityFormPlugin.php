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

    public $tags;

    public function __construct()
    {
        parent::__construct();

        $this->tags = array();
        $tags = PagesModel::getPageById(27)->getMeta('tag_options');
        foreach (explode("\n", $tags) as $t) {
            $t = trim($t);
            $this->tags[$t] = $t;
        }

        $this->addText('username', 'OSM.org username')
            ->setOption('description', 'Obrázek je třeba změnit na osm.org nebo gravatar.com.')
            ->setDisabled();

        $this->addText('email', 'E-mail')
            ->setOption('description', 'Email použivaný pro talk-cz.')
            ->addRule(Form::FILLED, '%label není vyplněn.')
            ->addRule(Form::EMAIL, '%label není validní.');

        $this->addText('fullname', 'Celé jméno')
            ->setOption('description', 'Ať se poznáme!')
            ->addRule(Form::FILLED, '%label není vyplněn.')
            ->addRule(Form::MIN_LENGTH, '%label musí mít alespoň 5 znaků.', 5);

        $this->addText('contact', 'E-mail kontaktní')
            ->setOption('description', '(nepovinné) Pokud se liší.')
            ->addCondition(Form::FILLED)
            ->addRule(Form::EMAIL, '%label není validní.');

        $this->addText('twitter', 'Twitter')
            ->setOption('description', '(nepovinné) Uživatelské jméno bez zavináče');
        $this->addText('github', 'Github')
            ->setOption('description', '(nepovinné) Uživatelské jméno');


        $this->addText('about_me', 'O mně')
            ->addRule(Form::MAX_LENGTH, '%label nesmí mít více než %d znaků.', 120)
            ->setOption('description', '(nepovinné) O mně ve 120 znacích.')
            ->getControlPrototype()->maxlength(120);

        $this->addMultiSelect('tags', 'Oblasti zájmu', $this->tags)
            ->setOption('description', '(nepovinné)')
            ->getControlPrototype()->style = 'height:150px;width:200px';


        $this->addCheckbox('public', 'Zveřejnit profil na openstreetmap.cz/komunita');

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
            $row['tags'] = explode("\n", $row['tags']);
            if ($row) $this->setValues($row);
        }
    }

    public function submitted()
    {
        if ($this->presenter->user->id) {
            $values = $this->values;
            $values['tags'] = join("\n", $values['tags']);
            dibi::query("UPDATE users SET", $values, "WHERE username = %s", $this->presenter->user->id); //our id is username
            $this->presenter->flashMessage('Profil upraven pro ' . $this->presenter->user->id);
        }

        $this->presenter->redirect('this');
    }
}

