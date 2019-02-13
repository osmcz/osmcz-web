<?php

/**
 * nPress - opensource cms
 *
 * @copyright  (c) 2012 Pavel Zbytovský (pavel@zby.cz)
 * @link       http://npress.info/
 * @package    nPress
 */
class OsmAuthPlugin extends Control
{
  static $events = array(
    'login_motd',
    'admin_motd',
    'allow_admin_request',
    'allow_page_edit'
  );

  public static function allow_admin_request($presenter)
  {
    if (!$presenter->user->isInRole('user')) {
      //probably admin -> allow
      return true;
    }

    //allowed presenter views
    $presenters = array(
      'Admin:Admin' => 'default,logout',
      'Admin:Pages' => 'edit,error,add,trash',
      'Admin:Redirect' => 'default'
    );
    if (!isset($presenters[$presenter->name])) {
      //not allowed presenter
      return false;
    }

    if (
      !in_array($presenter->view, explode(',', $presenters[$presenter->name]))
    ) {
      //not allowed view
      return false;
    }

    //allowed signals
    $signals = array(
      'Admin:Pages' => array(
        //checked by allow_page_edit($id)
        'pageEditForm-submit',
        'npFilesControl-.*',
        '-subpagessort',
        '-deletePage'
      )
    );

    $signal = implode('-', (array) $presenter->signal);
    if ($signal) {
      $allowed = false;
      foreach ($signals[$presenter->name] as $s) {
        if (preg_match('~^' . $s . '$~', $signal)) {
          //allowed signal
          $allowed = true;
        }
      }
      if (!$allowed) {
        return false;
      }
    }

    return true;
  }

  public static function allow_page_edit($presenter, $id)
  {
    if (!$presenter->user->isInRole('user')) {
      //probably admin -> allow
      return true;
    }

    //log all changes by users
    if (count($presenter->request->post)) {
      $v = PagesModel::getPageById($id);
    }
    //old
    else {
      $v = $presenter->request;
    }

    $key = Strings::webalize($presenter->user->id);
    $data =
      date("Y-m-d H:i:s ") .
      $_SERVER["REQUEST_URI"] .
      " " .
      base64_encode(print_r($v, 1));
    file_put_contents(
      WWW_DIR . "/data/activitylog/$key.log",
      $data,
      FILE_APPEND
    );

    if ($presenter->user->identity->webpages == 'all') {
      return true;
    }

    $allowed = explode(',', $presenter->user->identity->webpages);
    foreach (PagesModel::getPageById($id)->getParents() as $page) {
      if (in_array($page->id, $allowed)) {
        //allowed to edit page?
        return true;
      }
    }

    return false;
  }

  public function login_motd()
  {
    echo "<p><a href='/oauth/login'>Přihlásit přes osm.org</a><h2>Lokální účty:</h2>";
  }

  public function admin_motd()
  {
    if ($this->presenter->user->isInRole('user')) {
      $pairs = PagesModel::getPagesFlat()->getPairs();
      $row = $this->presenter->user->identity;
      echo "<p>Jsi přihlášen jako <b><code>$row->id</code></b> - uživatel osm.org";
      echo "<p>Máš oprávnění editovat tyto stránky a jejich podstránky:<ul>";
      $nic = true;
      foreach (explode(',', $row->webpages) as $id) {
        if (isset($pairs[$id])) {
          echo "<li style='line-height:2'><a href='" .
            $this->presenter->link(':Admin:Pages:edit', $id) .
            "' class='btn btn-warning'>$pairs[$id]</a>";
          $nic = false;
        }
      }
      if ($row->webpages == 'all') {
        echo '<li>Všechny!</li>';
      } elseif ($nic) {
        echo "<li>Žádné :-)";
      }
      echo "</ul>";
      echo "<p>Pokud chceš editovat ještě něco, napiš prosím na dev@openstreetmap.cz.";
      echo "<p>Pro znovunačtení práv, prosím, klikni sem: <a href='/oauth/login?backUrl=/admin' class='btn btn-default btn-xs'>Znovu načíst</a>.";
    } else {
      echo '<p style="font-size:150%;text-align:center;">Jsi přihlášen jako <b><code>SuperAdmin</code></a>.';
    }
  }
}
