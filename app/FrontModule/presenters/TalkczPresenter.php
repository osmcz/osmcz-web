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

        if ($this->isAjax()) {
            $this->invalidateControl('content');
            $this->invalidateControl('title');
            $this->payload->uri = $this->context->httpRequest->getUrl()->getRelativeUrl();
        }
    }


    public function actionDefault($month = null)
    {
        if (!$month OR !preg_match("~^(\d{4})(\d{2})$~", $month, $matches)) {
            $defaultMonth = dibi::fetchSingle("SELECT DATE_FORMAT(max(`date`), '%Y%m') FROM mailarchive");
            $this->redirect('this', ['month' => $defaultMonth]);
        }

        $this->template->result = dibi::query("
                    SELECT m.conversationid, m.date last_date, m.subject, 
                          (SELECT count(1) FROM mailarchive mc WHERE mc.conversationid = m.conversationid) count,
                          (SELECT name FROM mailarchive oldest WHERE oldest.conversationid = m.conversationid ORDER BY date ASC LIMIT 1) opener,
                          (SELECT `from` FROM mailarchive oldest2 WHERE oldest2.conversationid = m.conversationid ORDER BY date ASC LIMIT 1) opener_mail
                    FROM mailarchive m
                    LEFT JOIN mailarchive newest ON (m.conversationid = newest.conversationid AND m.date < newest.date)
                    WHERE newest.conversationid IS NULL  -- this works like a GROUP BY
                        AND m.conversationid >0
                        AND YEAR(m.date) = %i", $matches[1]," AND MONTH(m.date) = %i", $matches[2],"
                    ORDER BY m.date DESC
                ");

        /*  10 times worse performance (0.4s versus 0.07s):
                   SELECT conversationid,
                      max(`date`) AS last_date,
                      `date` AS opened, -- wrong!!
                      `name` AS opener, `from` AS opener_mail, `subject`,
                      count(1) AS count
                    FROM `mailarchive`
                    WHERE conversationid > 0
                    GROUP BY conversationid
                    HAVING YEAR(last_date) = 2017 AND MONTH(last_date) = 12
                    ORDER BY last_date DESC
        */
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

    public function actionSearch($query)
    {
        $result = dibi::query("
            SELECT m.*, u.*, m1.talk_cz_mails
                FROM `mailarchive` m
                LEFT JOIN users u ON m.`from` = u.email 
                LEFT JOIN (SELECT count(msgid) talk_cz_mails, `from` FROM mailarchive GROUP BY `from`) m1 ON m.from = m1.from
		    WHERE MATCH(text) AGAINST (%s",$query," IN BOOLEAN MODE)
		    ORDER BY date DESC, MATCH(text) AGAINST (%s",$query,") DESC
	    ");

        $this->template->results = $result->fetchAssoc('conversationid,#');
        $this->template->query = $query;
    }

}
