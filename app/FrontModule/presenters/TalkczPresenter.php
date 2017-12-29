<?php
class Front_TalkczPresenter extends Front_BasePresenter
{
    public function startup()
    {
        parent::startup();

        $this->template->monthList = dibi::query("
                SELECT last_date `date`, DATE_FORMAT(last_date,'%Y%m') ym, count(conversationid) AS count
                FROM (
                    SELECT conversationid, max(`date`) AS last_date
                    FROM `mailarchive`
                    WHERE conversationid > 0
                    GROUP BY conversationid
                    ORDER BY last_date DESC
                ) conversationsView
                GROUP BY YEAR(last_date), MONTH(last_date)
                ORDER BY last_date DESC");
    }


    public function actionDefault($month = null)
    {
        if (!$month OR !preg_match("~^(\d{4})(\d{2})$~", $month, $matches)) {
            $defaultMonth = dibi::fetchSingle("SELECT DATE_FORMAT(max(`date`), '%Y%m') FROM mailarchive");
            $this->redirect('this', ['month' => $defaultMonth]);
        }

        $this->template->result = dibi::query("
                    SELECT conversationid, 
                      max(`date`) AS last_date, 
                      `date` AS opened, 
                      `name` AS opener, `from` AS opener_mail, `subject`, 
                      count(1) AS count
                    FROM `mailarchive`
                    WHERE conversationid > 0
                    GROUP BY conversationid
                    HAVING YEAR(last_date) = %i", $matches[1]," AND MONTH(last_date) = %i", $matches[2]," 
                    ORDER BY last_date DESC
                ");

        $month = new DateTime53();
        $month->setDate($matches[1], $matches[2], 1);
        $this->template->month = $month;
        $this->template->prev = $month->modifyClone('-1 month');
        $this->template->next = $month->modifyClone('+1 month');
    }


    public function actionConversation($id)
    {
        $dbResult = dibi::query("
                SELECT m.*, u.*, m1.talk_cz_mails
                FROM `mailarchive` m
                LEFT JOIN users u ON m.`from` = u.email 
                LEFT JOIN (SELECT count(msgid) talk_cz_mails, `from` FROM mailarchive GROUP BY `from`) m1 ON m.from = m1.from
                WHERE m.conversationid = %i",$id,"
                ORDER BY m.date
            ");
        if (!count($dbResult))
            throw new BadRequestException("Talkcz conversation ($id) not found");

        $dates = $dbResult->fetchPairs('msgid', 'date');
        $result = $dbResult->fetchAll();

        $this->template->result = $result;
        $this->template->count = count($result);
        $this->template->subject = $result[0]->subject;

        $min = new DateTime(min($dates));
        $max = new DateTime(max($dates));
        $this->template->minYear = ($min->format('Y') == $max->format('Y')) ? "" : $min->format('Y');
        $this->template->min = $min;
        $this->template->max = $max;

    }

    public function actionAuthor($stub)
    {
        list($mailuser, $mailhash) = explode("-", $stub);
        $author = dibi::fetch("
                SELECT DISTINCT m.from, m.name
                FROM mailarchive m
                WHERE m.from LIKE %s", $mailuser . "%", " 
                AND mid(md5(`from`),-5) = %s", $mailhash);
        if (!$author) {
            throw new BadRequestException("Talkcz author ($stub) not found");
        }

        $authorInfo = dibi::fetch("
                SELECT u.*, (SELECT count(1) FROM mailarchive m1 WHERE m1.from = u.email) talk_cz_mails
                FROM users u
                WHERE u.email = %s", $author->from);
        if ($authorInfo) {
            $author = (object) array_merge($author->toArray(), $authorInfo->toArray());
        }

        $mailList = dibi::query("SELECT * FROM mailarchive WHERE `from` = %s", $author->from," ORDER BY `date` DESC");

        $this->template->author = $author;
        $this->template->mailList = $mailList;
        $this->template->themeDir = $this->context->params["themeDir"];
    }

    public function actionMakeConversation()
    {
        /*
        // run once to fill all "opening posts"
        dibi::query("
            SELECT @pv:=0;
            UPDATE mailarchive m
                SET conversationid = (@pv:=@pv+1)
                WHERE replyid = ''
                ORDER BY date ASC;
           ");
        */

        // add index

        // deduplicate msgid


        // assign to conversation based on "msgid == replyid"
        echo dibi::fetchSingle("SELECT count(1) FROM mailarchive WHERE conversationid = 0");
        echo "<hr>";

        $i = 0;
        while (true) {
            $updateOneDepth = dibi::query("
                SELECT mreply.msgid, morig.conversationid
                FROM mailarchive mreply 
                LEFT JOIN mailarchive morig ON mreply.replyid = morig.msgid
                WHERE mreply.conversationid = 0
                AND morig.conversationid > 0
            ");

            if (count($updateOneDepth) == 0)
                break;

            $i = 0;
            foreach ($updateOneDepth as $r) {
                dibi::query("UPDATE mailarchive SET conversationid = %i", $r->conversationid, " WHERE msgid = %s", $r->msgid);

                if ($i % 10 == 0) {
                    echo ".";
                    flush();
                }
            }

            $i++;
        }

        echo "<hr>";
        echo "depth: $i, rest: " . dibi::fetchSingle("SELECT count(1) FROM mailarchive WHERE conversationid = 0");
        echo "<hr>";

        // assign to conversation unmatched "replyid"  --- try to match by subject and date, or  "create new conversation from it"


        $this->terminate();
    }
}




