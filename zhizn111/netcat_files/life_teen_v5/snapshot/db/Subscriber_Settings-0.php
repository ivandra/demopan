<?php
return array (
  0 => 
  array (
    'ID' => '1',
    'Key' => 'ConfirmBody',
    'Value' => 'Уважаемый!

Чтобы подтвердить подписку, перейдите по <a href=\'%LINK%\'>ссылке.</a>

<a href=\'%SUB_LINK%\'>Страница управления подписками</a>',
  ),
  1 => 
  array (
    'ID' => '2',
    'Key' => 'ConfirmSubject',
    'Value' => 'Подтверждение подписки',
  ),
  2 => 
  array (
    'ID' => '3',
    'Key' => 'ConfirmType',
    'Value' => '0',
  ),
  3 => 
  array (
    'ID' => '4',
    'Key' => 'EmailField',
    'Value' => 'Email',
  ),
  4 => 
  array (
    'ID' => '5',
    'Key' => 'Charset',
    'Value' => 'utf-8',
  ),
  5 => 
  array (
    'ID' => '6',
    'Key' => 'ReplyEmail',
    'Value' => 'reply@example.com',
  ),
  6 => 
  array (
    'ID' => '7',
    'Key' => 'FromEmail',
    'Value' => 'info@example.com',
  ),
  7 => 
  array (
    'ID' => '8',
    'Key' => 'FromName',
    'Value' => 'MailBot',
  ),
  8 => 
  array (
    'ID' => '9',
    'Key' => 'MergeMail',
    'Value' => '0',
  ),
  9 => 
  array (
    'ID' => '10',
    'Key' => 'TextConfirm',
    'Value' => 'Подписка подтверждена.',
  ),
  10 => 
  array (
    'ID' => '11',
    'Key' => 'TextUnscribe',
    'Value' => 'Вы успешно отписались.',
  ),
  11 => 
  array (
    'ID' => '12',
    'Key' => 'TextError',
    'Value' => 'Код подтверждения недействителен, либо истек срок его действия.',
  ),
  12 => 
  array (
    'ID' => '13',
    'Key' => 'MaxMailCount',
    'Value' => '50',
  ),
  13 => 
  array (
    'ID' => '14',
    'Key' => 'MaxTime',
    'Value' => '100',
  ),
  14 => 
  array (
    'ID' => '15',
    'Key' => 'FormSubscribe',
    'Value' => '<form method=\'post\' action=\'$SUB_FOLDER/netcat/subscribe.php\'>
  <input type=\'hidden\' name=\'catalogue\' value=\'".$catalogue."\' />
  <input type=\'hidden\' name=\'sub\' value=\'".$sub."\' />
  <input type=\'hidden\' name=\'cc\' value=\'".$cc."\' />
  <input type=\'hidden\' name=\'posting\' value=\'1\' />

 Укажите ваш электронный адрес: <br/>

 <input type=\'text\' name=\'fields[Email]\' value=\'".$fields[Email]."\' />

<input type=\'submit\' value=\'Подписаться\' />

</form>',
  ),
  15 => 
  array (
    'ID' => '16',
    'Key' => 'ConfirmHTML',
    'Value' => '1',
  ),
  16 => 
  array (
    'ID' => '17',
    'Key' => 'TestEmail',
    'Value' => 'test@example.com',
  ),
  17 => 
  array (
    'ID' => '18',
    'Key' => 'Block',
    'Value' => '0',
  ),
);
