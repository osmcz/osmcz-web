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

  public function write($type = 'twitter', $limit = 4)
  {
    $TweetPHP = new TweetPHP(
      $this->config + array(
        'twitter_screen_name' => 'osmcz',
        'tweets_to_display' => 40,
        'tweets_to_retrieve' => 40,
        'ignore_retweets' => false,
        'date_lang' => 'cs_CZ',
        'cachetime' => 120,
        'cache_dir' => $this->parent->context->params['tempDir'] . '/'
      )
    );

    $tweet_array = $TweetPHP->get_tweet_array();
    if (count($tweet_array) === 1) {
      //error message
      print_r($tweet_array);
      return;
    }

    $weeklys = array();
    $tweets = array();
    $all = array();

    foreach ($tweet_array as $t) {
      $t['retweet'] = false;
      if (isset($t['retweeted_status'])) {
        $orig = $t;
        $t = $t['retweeted_status'];
        $t['retweet'] = true;
        $t['orig'] = $orig;
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
        $weeklys[] = $t;
      } else {
        $t['text'] = self::linkifiedText($t, $t['text']);
        $tweets[] = $t;
      }
    }

    $this->template->limit = isset($_GET['tweets']) ? $_GET['tweets'] : $limit;
    $this->template->tweets = $tweets;

    $this->template->w_limit = isset($_GET['weeklys']) ? $_GET['weeklys'] : 1;
    $this->template->weeklys = $weeklys;

    if ($type == 'weekly') {
      $this->template->setFile(
        dirname(__FILE__) . '/TwitterPlugin-weekly.latte'
      );
    } else {
      $this->template->setFile(dirname(__FILE__) . '/TwitterPlugin.latte');
    }

    echo $this->template->render();
  }

  public static function getWeeklyLink($t, $key = 'url')
  {
    foreach ($t['entities']['urls'] as $url) {
      if (strpos($url['expanded_url'], 'weeklyosm.eu/cz/archives') !== false) {
        return $url[$key];
      }
    }
    return false;
  }

  public static function linkifiedText($t, $s)
  {
    $s = htmlspecialchars($s);
    $s = @str_replace($t['entities']['media'][0]['url'], '', $s); //image shown below
    if (isset($t['entities']['urls'])) {
      foreach ($t['entities']['urls'] as $r) {
        $s = str_replace(
          $r['url'],
          "<a href='$r[expanded_url]' target='_blank'>$r[display_url]</a>",
          $s
        );
      }
    }
    return $s;
  }
}
