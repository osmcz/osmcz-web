<?php
class Front_TalkczPresenter extends Front_BasePresenter
{

    public function actionDefault($month = null)
    {
        if (!$month OR !preg_match("~^(\d{4})(\d{2})$~", $month, $matches)) {
            $this->redirect('this', ['month' => 201601]);
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

        $dates = $dbResult->fetchPairs('msgid', 'date');
        $result = $dbResult->fetchAll();

        $this->template->result = $result;
        $this->template->count = count($result);
        $this->template->subject = $result[0]->subject;
        $this->template->min = min($dates);
        $this->template->max = new DateTime(max($dates));
    }

    public function actionAuthor($stub)
    {

    }

}