<?php
return array (
  0 => 
  array (
    'Mailer_ID' => '1',
    'Name' => 'Форум: Шаблон рассылки',
    'Sub_Class_ID' => '0',
    'Access' => '2',
    'Type' => '1',
    'Active' => '0',
    'InStat' => '0',
    'Header' => 'Здравствуйте, <b>%USER_LOGIN% </b>. <br/>
На <a href=\'http://".$current_catalogue[\'Domain\'].$static_vars[\'forum_obj\']->get_forum_url_by_cc_id($cc)."\'> форуме</a> появилось новое сообщение. 
<br><br>
<table border=\'0\'>',
    'Footer' => '</table>
<br><br>
Надоело получать рассылку? - <a href=\'%LINK%\'>Отпишитесь от всех топиков этого форума.</a>.',
    'Record' => '";

// имя пользователя, добавивший комментарий
if ( $f_UserID ) {
  $f_Author = $db->get_var("SELECT `Login` FROM `User` WHERE `User_ID` = \'".$f_UserID."\' ");
}
else {
  $f_Author = \'Гость\';
}

$result .="
<tr>
  <td> В <a href=\'http://".$current_catalogue[\'Domain\'].$static_vars[\'topic_obj\']->get_url($f_Topic_ID)."\'>топике</a> 
       пользователь <b>$f_Author</b> написал:</a>
  </td>
</tr>

<tr>
  <td>".nc_bbcode($f_Message)."</td>
</tr>


<tr>
  <td><a href=\'http://".$current_catalogue[\'Domain\'].nc_message_link($f_Topic_ID, $MODULE_VARS[\'forum2\'][\'TOPIC_CLASS_ID\'], \'subscribe\')."\'>Отписаться от этого топика.</a></td>
</tr>
',
    'SubscribeCond' => '$nc_forum2_topic = nc_forum2_topic::get_object();
$topic_url = $nc_forum2_topic->get_url($message);



$nc_subscriber = nc_subscriber::get_object();

if ( $nc_subscriber->is_subscribe_to_message ( $cc, $message ) ) {
  $db->query("DELETE FROM `Subscriber_UserMessage` WHERE `Sub_Class_ID` = \'".$cc."\' AND `Message_ID` = \'".$message."\'");
  $posting = 0;
  $warnText = "Вы успешно отписались.</a><br/><a href=\'".$topic_url."\'>Вернуться в топик</a>";
}',
    'SendCond' => '// объект для работы с топиком и фоурмом
$static_vars[\'topic_obj\'] = nc_forum2_topic::get_object();
$static_vars[\'forum_obj\'] = nc_forum2_forum::get_object();

',
    'SubscribeAction' => '";
  // get topic object
  $nc_forum2_topic = nc_forum2_topic::get_object();
  // determine topic url
  $topic_url = $nc_forum2_topic->get_url($message);

echo "

".( $nc_status == \'on\' ? "Вы успешно подписались." : "На ваш email отправлено письмо с ссылкой для подтверждения подписки")."
<br/>
<a href=\'".$topic_url."\'>Вернуться в топик</a>',
    'Period' => '1',
    'Subject' => 'Новый ответ на форуме ".$current_sub[\'Subdivision_Name\']."',
    'HTML' => '1',
    'ActionType' => '0',
    'MailCount' => '0',
    'LastSend' => NULL,
    'PeriodMask' => NULL,
    'Class_ID' => '0',
  ),
);
