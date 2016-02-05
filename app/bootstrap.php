<?php

FileTemplate::extensionMethod('timeago', function ($that, $s) {
    return Helpers::timeAgoInWords($s);
});

