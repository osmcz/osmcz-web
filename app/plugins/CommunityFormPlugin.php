<?php

use Nette\Application\UI\Form;

/**
 * nPress - opensource cms
 *
 * @copyright  (c) 2012 Pavel Zbytovský (pavel@zby.cz)
 * @link       http://npress.zby.cz/
 * @package    nPress
 */
class CommunityFormPlugin extends Form
{
  static $events = array();

  public $tags;
  public $projects;

  public function __construct()
  {
    parent::__construct();

    $this->tags = array();
    $tags = PagesModel::getPageById(27)->getMeta('tag_options');
    foreach (explode("\n", $tags) as $t) {
      $t = trim($t);
      $this->tags[$t] = $t;
    }

    $this->projects = PagesModel::getPagesByMeta("project_tag")->getPairs('');

    $this->addText('username', 'OSM.org username')->setDisabled();

    $this->addText('fullname', 'Celé jméno')
      ->setOption('description', 'Ať se poznáme!')
      ->addRule(Form::FILLED, '%label není vyplněn.')
      ->addRule(Form::MIN_LENGTH, '%label musí mít alespoň 5 znaků.', 5);

    $this->addText('email', 'Talk-cz')
      ->setOption(
        'description',
        'Email použivaný pro spočítání příspěvků v talk-cz (neveřejný)'
      )
      ->addRule(Form::FILLED, '%label není vyplněn.')
      ->addRule(Form::EMAIL, '%label není validní.');

    $this->addText('contact', 'Veřejný e-mail')
      ->setOption('description', '(nepovinné)')
      ->addCondition(Form::FILLED)
      ->addRule(Form::EMAIL, '%label není validní.');

    $this->addText('twitter', 'Twitter')->setOption(
      'description',
      '(nepovinné) Uživatelské jméno bez zavináče'
    );
    $this->addText('github', 'Github')->setOption(
      'description',
      '(nepovinné) Uživatelské jméno'
    );

    $this->addText('places', 'Výskyt')->setOption(
      'description',
      '(nepovinné) Kde se vyskystuju - typicky jaká města.'
    );
    $this['places']->getControlPrototype()->placeholder = 'oddělené čárkou';
    $this['places']->getControlPrototype()->style = 'width: 40%';

    $this->addText('tags', 'Oblasti zájmu')->setOption(
      'description',
      '(nepovinné)'
    );
    $this['tags']->getControlPrototype()->placeholder = 'oddělené čárkou';
    $this['tags']->getControlPrototype()->style = 'width: 60%';
    $this['tags']->getControlPrototype()->{'data-options'} = Json::encode(
      array_values($this->tags)
    );

    $this->addMultiSelect('projects', 'Projekty', $this->projects)
      ->setOption(
        'description',
        '(nepovinné) Projektovou stránku možno přidat v administraci. Případně napiš na dev@openstreetmap.cz'
      )
      ->getControlPrototype()->style = 'height:150px;width:40%';

    $this->addCheckbox('public', 'Zveřejnit údaje na openstreetmap.cz');

    $this->addSubmit('submit', 'Uložit údaje');
    $this->onSuccess[] = callback($this, 'submitted');

    $renderer = $this->getRenderer();
    $renderer->wrappers['controls']['container'] =
      'table class="table form-inline"';
    $renderer->wrappers['error']['container'] = 'ul class="bg-danger"';
    $renderer->wrappers['control']['.text'] = 'form-control';
    $renderer->wrappers['control']['.email'] = 'form-control';
    $renderer->wrappers['control']['.submit'] = 'btn btn-primary';
  }

  protected function attached($presenter)
  {
    parent::attached($presenter);

    if (!$this->isSubmitted()) {
      $row = dibi::fetch(
        "SELECT * FROM users WHERE username = %s",
        $presenter->user->id
      );
      $row['tags'] = $row['tags'];
      $row['projects'] = array_map(function ($a) {
        return trim($a, '()');
      }, explode(",", $row['projects']));
      if ($row) {
        $this->setValues($row);
      }
    }
  }

  public function submitted()
  {
    if ($this->presenter->user->id) {
      $values = $this->values;
      $values['projects'] = join(
        ",",
        array_map(function ($a) {
          return "($a)";
        }, $values['projects'])
      );
      dibi::query(
        "UPDATE users SET",
        $values,
        "WHERE username = %s",
        $this->presenter->user->id
      ); //our id is username
      $this->presenter->flashMessage(
        'Profil upraven pro ' . $this->presenter->user->id
      );
    }

    $this->presenter->redirect('this');
  }
}
