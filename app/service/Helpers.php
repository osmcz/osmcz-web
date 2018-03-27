<?php

/**
 * Nette Framework Extras
 *
 * This source file is subject to the New BSD License.
 *
 * For more information please see http://addons.nette.org
 *
 * @copyright  Copyright (c) 2008, 2009 David Grudl
 * @license    New BSD License
 * @link       http://addons.nette.org
 * @package    Nette Extras
 */




class Helpers
{

    // taken from: https://github.com/zbycz/casopisy/blob/dbd091/app/model/Obsah.php#L153
    public static function excerpt($text, $query)
    {
        //words
        $words = join('|', explode(' ', preg_quote($query)));

        //lookahead/behind assertions ensures cut between words
        $s = '\s\x00-/:-@\[-`{-~'; //character set for start/end of words
        $matches = Strings::matchAll($text, '#(?<=['.$s.']).{1,30}(('.$words.').{1,30})+(?=['.$s.'])#uis');

        //delimiter between occurences
        $results = array();
        foreach($matches as $line) {
            $results[] = htmlspecialchars($line[0], 0, 'UTF-8');
        }
        $result = join(' <b>(...)</b> ', $results);

        //highlight
        $result = Strings::replace($result, '#'.$words.'#iu', "<span class=\"highlight\">\$0</span>");
        return $result;
    }



    public static function talkMailBody($s)
    {
        $startQuoteHtml = "<a href='#' onclick='$(this).next().toggle();return false;'>[&hellip;]</a>\n<div class='quoted'>";

        $s = htmlspecialchars($s);
        //$s = preg_replace('~==([^=]+)==[\r\n]+~is', '<h2>\\1</h2>', $s);
        //$s = preg_replace('~\*([^*]+)\*~iU', '<b>\\1</b>', $s);
        $opened = false;
        $out = array();
        foreach(explode("\n", $s) as $line) {
            $isQuote = preg_match("~^(\s*&gt;\s*){1,}~", $line);
            if (!$opened && $isQuote) {
                $opened = true;
                $out[] = $startQuoteHtml . $line;
            }
            else if ($opened && !$isQuote) {
                $out[] = "</div>" . $line;
                $opened = false;
            }
            else
                $out[] = $line;
        }
        if ($opened) $out[] = "</div>";

        //---------- Původní zpráva ----------
        //Od: ..
        //Komu: ..
        //Datum: ..
        $opened = false;
        for ($i = 0; $i < count($out); $i++) {
            $line = $out[$i];
            if (!$opened AND preg_match("/^\s*[-~_]{5,}.*[-~_]{5,}\s*$/", $line)) {
                $opened = $i;
                continue;
            }
            if (abs($opened-$i) < 3 AND !preg_match("/^\s*[a-zA-Z]+: /", $line)) {
                $opened = false;
                continue;
            }
        }
        if ($opened) {
            $out[$opened] = "<div style='border-left:1px silver solid;padding-left:5px'>" . $out[$opened];
            $out[count($out)-1] .= "</div>";
        }

        $s = implode("\n", $out);

        // cant stop regexp on whole "&gt;"  .. so we hack it
        $s = str_replace("&gt;", ")&gt;", $s);
        $s = preg_replace('~(https?://)([^ \n\r\t()[\]]+)~is', '<a href="\\1\\2" target="_blank" rel="nofollow noopener">\\1\\2</a>', $s);
        $s = str_replace(")&gt;", "&gt;", $s);

        return $s;
    }


	/**
	 * Czech helper time ago in words.
	 * @param  int
	 * @return string
	 */
	public static function timeAgoInWords($time, $format, $formatAfterDays)
	{
		if (!$time) {
			return FALSE;
		} elseif (is_numeric($time)) {
			$time = (int) $time;
		} elseif ($time instanceof DateTime) {
			$time = $time->format('U');
		} else {
			$time = strtotime($time);
		}

		$delta = time() - $time;

        if ($format AND $delta > $formatAfterDays*60*60*24) {
            return date($format, $time);
        }

		if ($delta < 0) {
			$delta = round(abs($delta) / 60);
			if ($delta == 0) return 'za okamžik';
			if ($delta == 1) return 'za minutu';
			if ($delta < 45) return 'za ' . $delta . ' ' . self::plural($delta, 'minuta', 'minuty', 'minut');
			if ($delta < 90) return 'za hodinu';
			if ($delta < 1440) return 'za ' . round($delta / 60) . ' ' . self::plural(round($delta / 60), 'hodina', 'hodiny', 'hodin');
			if ($delta < 2880) return 'zítra';
			if ($delta < 43200) return 'za ' . round($delta / 1440) . ' ' . self::plural(round($delta / 1440), 'den', 'dny', 'dní');
			if ($delta < 86400) return 'za měsíc';
			if ($delta < 525960) return 'za ' . round($delta / 43200) . ' ' . self::plural(round($delta / 43200), 'měsíc', 'měsíce', 'měsíců');
			if ($delta < 1051920) return 'za rok';
			return 'za ' . round($delta / 525960) . ' ' . self::plural(round($delta / 525960), 'rok', 'roky', 'let');
		}

		$delta = round($delta / 60);
		if ($delta == 0) return 'před okamžikem';
		if ($delta == 1) return 'před minutou';
		if ($delta < 45) return "před $delta minutami";
		if ($delta < 90) return 'před hodinou';
		if ($delta < 1440) return 'před ' . round($delta / 60) . ' hodinami';
		if ($delta < 2880) return 'včera';
		if ($delta < 43200) return 'před ' . round($delta / 1440) . ' dny';
		if ($delta < 86400) return 'před měsícem';
		if ($delta < 525960) return 'před ' . round($delta / 43200) . ' měsíci';
		if ($delta < 1051920) return 'před rokem';
		return 'před ' . round($delta / 525960) . ' lety';
	}



	/**
	 * Plural: three forms, special cases for 1 and 2, 3, 4.
	 * (Slavic family: Slovak, Czech)
	 * @param  int
	 * @return mixed
	 */
	private static function plural($n)
	{
		$args = func_get_args();
		return $args[($n == 1) ? 1 : (($n >= 2 && $n <= 4) ? 2 : 3)];
	}

}
