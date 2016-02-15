<?php

FileTemplate::extensionMethod('timeago', function ($that, $s) {
    return Helpers::timeAgoInWords($s);
});

FileTemplate::extensionMethod('modified', function ($that, $s) {
    return $s . "?". dechex(filemtime(WWW_DIR.$s));
});
