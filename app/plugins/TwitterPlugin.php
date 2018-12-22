<?php

/**
 * nPress - opensource cms
 *
 * @copyright  (c) 2012 Pavel Zbytovský (pavel@zby.cz)
 * @link       http://npress.info/
 * @package    nPress
 */
class TwitterPlugin extends Control
{
  public static $events = array();

  function getConfig()
  {
    return $this->parent->context->params['twitter'];
  }

  public function fetchTweets()
  {
    $TweetPHP = new TweetPHP(
      $this->config + array(
        'twitter_screen_name' => 'osmcz',
        'tweets_to_display' => 20,
        'tweets_to_retrieve' => 20,
        'ignore_retweets' => false,
        'date_lang' => 'cs_CZ',
        'cachetime' => 120,
        'cache_dir' => $this->parent->context->params['tempDir'] . '/'
      )
    );

    $tweet_array = $TweetPHP->get_tweet_array();
    if (count($tweet_array) === 1) {
      //error message
      Debugger::log(print_r($tweet_array, 1), Debugger::INFO);
      return;
    }

    foreach ($tweet_array as $t) {
      $exists = dibi::fetch(
        "SELECT 1 FROM twitter_archive WHERE id = %s",
        $t['id_str']
      );
      if ($exists) {
        continue;
      }

      Debugger::log("Adding tweet id=$t[id_str]: $t[full_text]");

      try {
        $text = isset($t['retweeted_status']) // text for fulltext search in future
          ? 'RT @' .
            $t['retweeted_status']['user']['screen_name'] .
            ': ' .
            $t['retweeted_status']['full_text']
          : $t['full_text'];
        $text = self::linkifiedText($t, $text, true);

        dibi::query("INSERT INTO twitter_archive ", array(
          'id' => $t['id_str'],
          'date' => new DateTime($t['created_at']),
          'text' => $text,
          'serialized' => JSON::encode($t),
          'weekly_link' => self::getWeeklyLink($t, 'expanded_url', true)
        ));
      } catch (Exception $e) {
        Debugger::log($e, Debugger::ERROR);
      }
    }
  }

  private $weeklys = array();
  private $tweets = array();

  public function preprocessTweets()
  {
    foreach (
      dibi::query("SELECT * FROM twitter_archive ORDER BY date DESC")
      as $row
    ) {
      $t = json_decode($row['serialized'], true);

      $t['retweet'] = false;
      if (isset($t['retweeted_status'])) {
        $orig = $t;
        $t = $t['retweeted_status'];
        $t['retweet'] = true;
        $t['orig'] = $orig;
      }
      if (isset($t['full_text'])) {
        // older tweets dont have full_text
        $t['text'] = $t['full_text'];
      }

      if (
        preg_match('~^(WeeklyOSM \d+[^:]*):~', $t['text'], $matches) &&
        self::getWeeklyLink($t)
      ) {
        $text = $t['text'];
        $text = str_replace(self::getWeeklyLink($t), '', $text); // remove weekly_link
        $text = str_replace($matches[0], '', $text); // remove "Weekly...:"
        $text = str_replace('OSM.cz ', 'OpenStreetMap.cz ', $text);
        $text = self::linkifiedText($t, $text); //images or other links(?)
        $t['weekly_text'] = $text;
        $t['weekly_title'] = $matches[1];
        $t['weekly_link'] = self::getWeeklyLink($t, 'expanded_url');
        $this->weeklys[] = $t;
      } else {
        $t['text'] = self::linkifiedText($t, $t['text']);
        $this->tweets[] = $t;
      }
    }
  }

  public function write($type = 'twitter', $limit = 4)
  {
    if (!$this->tweets and !$this->weeklys) {
      $this->fetchTweets();
      $this->preprocessTweets();
    }

    $this->template->limit = isset($_GET['tweets']) ? $_GET['tweets'] : $limit;
    $this->template->tweets = $this->tweets;

    $this->template->w_limit = isset($_GET['weeklys'])
      ? $_GET['weeklys']
      : $limit;
    $this->template->weeklys = $this->weeklys;

    if ($type == 'weekly') {
      $this->template->setFile(
        dirname(__FILE__) . '/TwitterPlugin-weekly.latte'
      );
    } elseif ($type == 'weekly-archive') {
      $this->template->setFile(
        dirname(__FILE__) . '/TwitterPlugin-weekly-archive.latte'
      );
    } else {
      $this->template->setFile(dirname(__FILE__) . '/TwitterPlugin.latte');
    }

    //render
    echo $this->template->render();
  }

  public static function getWeeklyLink($t, $key = 'url')
  {
    foreach ($t['entities']['urls'] as $url) {
      if (strpos($url['expanded_url'], 'weeklyosm.eu/cz/archives') !== false) {
        return $url[$key];
      }
    }
    return '';
  }

  public static function linkifiedText($t, $text, $plaintext = false)
  {
    $text = htmlspecialchars($text);
    $text = @str_replace($t['entities']['media'][0]['url'], '', $text); //image shown below

    // search for short t.co urls and replace them for the full link
    if (isset($t['entities']['urls'])) {
      foreach ($t['entities']['urls'] as $r) {
        $replace = $plaintext
          ? $r['expanded_url']
          : "<a href='$r[expanded_url]' target='_blank'>$r[display_url]</a>";
        $text = str_replace($r['url'], $replace, $text);
      }
    }
    return $text;
  }
}
