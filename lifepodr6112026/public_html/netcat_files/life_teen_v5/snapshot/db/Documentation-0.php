<?php
return array (
  0 => 
  array (
    'Documentation_ID' => '93',
    'Type' => 'function',
    'Name' => 'browse_messages',
    'Signature' => 'browse_messages ( $cc_env, $range )',
    'ShortHelp' => '<p>
	Отображает блок навигации по страницам списка объектов компонента в формате «1 2 3 &gt;&gt;».</p>',
    'FullHelp' => '<p>Массив $cc_env является неизменным параметром данной функции и содержит переменные окружения текущего компонента раздела. Параметр $range определяет количество выводимых страниц. Вместо этой переменной обычно пишется число. Подразумевается, что из множества страниц одновременно будет показываться только список из $range страниц. Например, ваш листинг состоит из 20 страниц. Если $range=10, то, находясь на первой странице, вы будете видеть страницы с 1 по 10, находясь на 15-й странице, вы будете видеть страницы 10-20. Для настройки формата отображения используется массив $browse_msg[], значения которого указываются в настройках макета дизайна. Макропеременная %PAGE обозначает номер страницы, макропеременная %URL – ссылку на соответствующие страницы. Возможно также использование макропеременных %FROM и %TO, соответственно обозначающих номера начального и конечного объекта на странице.</p>
<pre><code class=\'language-php\'>$browse_msg[prefix] = ""; // Префикс перед блоком навигации
$browse_msg[suffix] = ""; // Суффикс после блока навигации
$browse_msg[active] = "%PAGE"; // Формат вывода текущей страницы
$browse_msg[unactive] = "<a href=%URL>%PAGE</a>"; // Формат вывода ссылок
$browse_msg[divider] = " "; // Разделитель между ссылками</code></pre>',
    'Module_ID' => NULL,
    'Parent' => '',
    'Areas' => 'ClassForm_FormPrefix ClassForm_RecordTemplate ClassForm_FormSuffix ClassForm_RecordTemplateFull ClassForm_Settings',
  ),
  1 => 
  array (
    'Documentation_ID' => '94',
    'Type' => 'function',
    'Name' => 'nc_browse_cc',
    'Signature' => 'nc_browse_cc ( $template )',
    'ShortHelp' => 'Выводит список ссылок по компонентам раздела в соответствии с шаблоном $template.',
    'FullHelp' => '',
    'Module_ID' => NULL,
    'Parent' => '',
    'Areas' => 'TemplateForm',
  ),
  2 => 
  array (
    'Documentation_ID' => '95',
    'Type' => 'function',
    'Name' => 'nc_browse_sub',
    'Signature' => 'nc_browse_sub( $browse_parent_sub, $browse_template, $ignore_check = 0, $where_cond = "", $level = 0 )',
    'ShortHelp' => 'Выводит список подразделов раздела $parent_sub в соответствии с шаблоном $template.',
    'FullHelp' => 'C помощью флага $ignore_check можно игнорировать вывод только включённых разделов ( если $ignore_check равен 1, то выведутся все разделы ). С помощью $where_cond можно дополнить запрос в секции WHERE.',
    'Module_ID' => NULL,
    'Parent' => '',
    'Areas' => 'TemplateForm',
  ),
  3 => 
  array (
    'Documentation_ID' => '96',
    'Type' => 'function',
    'Name' => 'nc_browse_level',
    'Signature' => 'nc_browse_level( $level, $browse_template )',
    'ShortHelp' => 'Выводит список разделов уровня $level в соответствии с шаблоном $template.',
    'FullHelp' => 'Обратите внимание, что нумерация уровней начинается с нуля, т.е. Для вывода списка разделов верхнего уровня первый параметр функции должна быть равен нулю.',
    'Module_ID' => NULL,
    'Parent' => '',
    'Areas' => 'TemplateForm',
  ),
  4 => 
  array (
    'Documentation_ID' => '97',
    'Type' => 'function',
    'Name' => 'nc_browse_path',
    'Signature' => 'nc_browse_path( $browse_template )',
    'ShortHelp' => 'Выводит навигацию типа "хлебные крошки" (путь до текущей страницы) в соответствии с шаблоном $template.',
    'FullHelp' => '',
    'Module_ID' => NULL,
    'Parent' => '',
    'Areas' => 'TemplateForm',
  ),
  5 => 
  array (
    'Documentation_ID' => '98',
    'Type' => 'function',
    'Name' => 'nc_browse_catalogue',
    'Signature' => 'nc_browse_catalogue( $browse_template )',
    'ShortHelp' => 'Функция выводит список сайтов (блок навигации) в соответствии с
шаблоном, описанным в хэш-массиве $template',
    'FullHelp' => '<div>
	Массив должен иметь&nbsp;элементы со следующими индексами:</div>
<div>
	<table>
		<thead>
			<tr>
				<th scope="col">
					Индексы</th>
				<th scope="col">
					Описание</th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td>
					prefix</td>
				<td>
					выводится перед списком</td>
			</tr>
			<tr>
				<td>
					suffix</td>
				<td>
					выводится после списка</td>
			</tr>
			<tr>
				<td>
					active</td>
				<td>
					шаблон вывода активного элемента списка (а данном случае это&nbsp;касается текущего сайта)</td>
			</tr>
			<tr>
				<td>
					active_link</td>
				<td>
					шаблон вывода активного элемента списка в том случае, если ссылка&nbsp;на этот элемент идентична адресу текущей страницы</td>
			</tr>
			<tr>
				<td>
					unactive</td>
				<td>
					<div>
						шаблон вывода неактивного элемента списка (в данном случае это&nbsp;касается всех сайтов, кроме текущего)</div>
				</td>
			</tr>
			<tr>
				<td>
					divider</td>
				<td>
					шаблон разделителя между элементами списка</td>
			</tr>
			<tr>
				<td>
					sortby</td>
				<td>
					признак сортировки элементов</td>
			</tr>
		</tbody>
	</table>
</div>
<p>
	&nbsp;</p>',
    'Module_ID' => NULL,
    'Parent' => '',
    'Areas' => 'TemplateForm',
  ),
  6 => 
  array (
    'Documentation_ID' => '99',
    'Type' => 'function',
    'Name' => 'nc_browse_path_range',
    'Signature' => 'nc_browse_path_range( $from, $to, $browse_template, $reverse = 0, $show = 0 )',
    'ShortHelp' => 'Функция аналогична s_browse_path(), но выводит только путь указанного
диапазона (минимальное значение $from – (-1), максимальное значение
$to - $sub_level_count), в соответствии с шаблоном, описанным в
массиве $template.',
    'FullHelp' => '',
    'Module_ID' => NULL,
    'Parent' => '',
    'Areas' => 'TemplateForm',
  ),
  7 => 
  array (
    'Documentation_ID' => '100',
    'Type' => 'function',
    'Name' => 'nc_put_field',
    'Signature' => 'nc_put_field (string $field_name, [string $style, [int $classID, [bool $caption]]] )',
    'ShortHelp' => 'Функция генерирует поле любого типа.',
    'FullHelp' => '<div>
	<table>
		<thead>
			<tr>
				<th scope="col">
					Параметры&nbsp;</th>
				<th scope="col">
					Описание</th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td>
					$field_name</td>
				<td>
					имя поля в текущем компоненте или компоненте $classID</td>
			</tr>
			<tr>
				<td>
					$style&nbsp;(опционально)</td>
				<td>
					параметры отображения поля, например: «size=\'50\' class=\'my_field\'»</td>
			</tr>
			<tr>
				<td>
					$classID&nbsp;(опционально)</td>
				<td>
					<div>
						идентификатор компонента, используется например, при выводе&nbsp;формы через функцию s_list_class() его следует указывать, по&nbsp;умолчанию этот параметр можно не задавать</div>
				</td>
			</tr>
			<tr>
				<td>
					$caption&nbsp;(опционально)</td>
				<td>
					<div>
						выводить или не выводить описание над генерируемым полем</div>
				</td>
			</tr>
		</tbody>
	</table>
</div>',
    'Module_ID' => NULL,
    'Parent' => '',
    'Areas' => 'ClassForm_AddTemplate ClassForm_AddCond ClassForm_AddActionTemplate ClassForm_EditTemplate ClassForm_EditCond ClassForm_EditActionTemplate ClassForm_CheckActionTemplate',
  ),
  8 => 
  array (
    'Documentation_ID' => '101',
    'Type' => 'function',
    'Name' => 'nc_list_field',
    'Signature' => 'nc_list_field ( string $field_name, [string $style, [int $classID, [bool $caption, [mixed $selected, [mixed $disabled, [bool $ignore_check]]]]]] )',
    'ShortHelp' => 'Функция генерирует поле типа «Список».',
    'FullHelp' => '<div>
	<table>
		<thead>
			<tr>
				<th scope="col">
					Параметры</th>
				<th scope="col">
					Описание</th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td>
					$field_name</td>
				<td>
					имя поля в текущем компоненте или компоненте $classID</td>
			</tr>
			<tr>
				<td>
					$style&nbsp;(опционально)</td>
				<td>
					<div>
						параметры отображения поля, например:&nbsp;«class=\'my_field\'»</div>
				</td>
			</tr>
			<tr>
				<td>
					$classID&nbsp;(опционально)</td>
				<td>
					<div>
						идентификатор компонента, используется например, при выводе&nbsp;формы через функцию s_list_class() его следует указывать, по&nbsp;умолчанию этот параметр можно не задавать</div>
				</td>
			</tr>
			<tr>
				<td>
					$caption&nbsp;(опционально)</td>
				<td>
					выводить или не выводить описание над генерируемым полем</td>
			</tr>
			<tr>
				<td>
					$selected&nbsp;(опционально)&nbsp;</td>
				<td>
					выбранный элемент списка</td>
			</tr>
			<tr>
				<td>
					$disabled&nbsp;(опционально)</td>
				<td>
					выключенный элемент списка</td>
			</tr>
			<tr>
				<td>
					$ignore_check&nbsp;(опционально)</td>
				<td>
					игнорировать выборку только включенных</td>
			</tr>
		</tbody>
	</table>
</div>
<p>
	&nbsp;</p>',
    'Module_ID' => NULL,
    'Parent' => '',
    'Areas' => 'ClassForm_AddTemplate ClassForm_AddCond ClassForm_AddActionTemplate ClassForm_EditTemplate ClassForm_EditCond ClassForm_EditActionTemplate ClassForm_CheckActionTemplate',
  ),
  9 => 
  array (
    'Documentation_ID' => '102',
    'Type' => 'function',
    'Name' => 'nc_file_field',
    'Signature' => 'nc_file_field ( string $field_name, [string $style, [int $classID, [bool $caption]]] )',
    'ShortHelp' => 'Функция генерирует поле типа «Файл».',
    'FullHelp' => '<div>
	В большинстве случаев она</div>
<div>
	применяется в альтернативных формах компонента, чтобы вывести информацию о поле типа Файл.</div>
<div>
	<table>
		<thead>
			<tr>
				<th scope="col">
					Параметры</th>
				<th scope="col">
					Описание</th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td>
					$field_name</td>
				<td>
					имя поля в текущем компоненте или компоненте $classID</td>
			</tr>
			<tr>
				<td>
					$style&nbsp;(опционально)</td>
				<td>
					параметры отображения поля, например:&nbsp;«size=\'50\'»</td>
			</tr>
			<tr>
				<td>
					$classID&nbsp;(опционально)</td>
				<td>
					идентификатор компонента, используется например, при выводе&nbsp;формы через функцию s_list_class() его следует указывать, по&nbsp;умолчанию этот параметр можно не задавать</td>
			</tr>
			<tr>
				<td>
					$caption&nbsp;(опционально)</td>
				<td>
					выводить или не выводить описание над генерируемым полем</td>
			</tr>
			<tr>
				<td>
					$selected&nbsp;(опционально)</td>
				<td>
					выбранный элемент списка</td>
			</tr>
			<tr>
				<td>
					$disabled&nbsp;(опционально)</td>
				<td>
					выключенный элемент списка</td>
			</tr>
			<tr>
				<td>
					$ignore_check&nbsp;(опционально)</td>
				<td>
					игнорировать выборку только включенных</td>
			</tr>
		</tbody>
	</table>
</div>',
    'Module_ID' => NULL,
    'Parent' => '',
    'Areas' => 'ClassForm_AddTemplate ClassForm_AddCond ClassForm_AddActionTemplate ClassForm_EditTemplate ClassForm_EditCond ClassForm_EditActionTemplate ClassForm_CheckActionTemplate',
  ),
  10 => 
  array (
    'Documentation_ID' => '103',
    'Type' => 'function',
    'Name' => 'nc_bool_field',
    'Signature' => 'nc_bool_field ( string $field_name, [string $style, [int $classID, [bool $caption]]] )',
    'ShortHelp' => 'Функция генерирует поле типа «Логическая переменная».',
    'FullHelp' => '<div>
	<table>
		<thead>
			<tr>
				<th scope="col">
					Параметры</th>
				<th scope="col">
					Описание</th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td>
					$field_name</td>
				<td>
					имя поля в текущем компоненте или компоненте $classID</td>
			</tr>
			<tr>
				<td>
					$style&nbsp;(опционально)</td>
				<td>
					параметры отображения поля, например:&nbsp;«size=\'50\'»</td>
			</tr>
			<tr>
				<td>
					$classID&nbsp;(опционально)</td>
				<td>
					идентификатор компонента, используется например, при выводе&nbsp;формы через функцию s_list_class() его следует указывать, по&nbsp;умолчанию этот параметр можно не задавать</td>
			</tr>
			<tr>
				<td>
					$caption&nbsp;(опционально)</td>
				<td>
					выводить или не выводить описание над генерируемым полем</td>
			</tr>
		</tbody>
	</table>
</div>',
    'Module_ID' => NULL,
    'Parent' => '',
    'Areas' => 'ClassForm_AddTemplate ClassForm_AddCond ClassForm_AddActionTemplate ClassForm_EditTemplate ClassForm_EditCond ClassForm_EditActionTemplate ClassForm_CheckActionTemplate',
  ),
  11 => 
  array (
    'Documentation_ID' => '104',
    'Type' => 'function',
    'Name' => 'nc_date_field',
    'Signature' => 'nc_date_field ( string $field_name, [string $style, [int $classID, [bool $caption, [string $dateDiv, [string $timeDiv]]]]] )',
    'ShortHelp' => 'Функция генерирует поле типа «Дата и время».',
    'FullHelp' => '<div>
	<table>
		<thead>
			<tr>
				<th scope="col">
					Параметры</th>
				<th scope="col">
					Описание</th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td>
					$field_name</td>
				<td>
					имя поля в текущем компоненте или компоненте $classID</td>
			</tr>
			<tr>
				<td>
					$style&nbsp;(опционально)</td>
				<td>
					параметры отображения поля, например:&nbsp;«size=\'50\'»</td>
			</tr>
			<tr>
				<td>
					$classID&nbsp;(опционально)</td>
				<td>
					идентификатор компонента, используется например, при выводе&nbsp;формы через функцию s_list_class() его следует указывать, по&nbsp;умолчанию этот параметр можно не задавать</td>
			</tr>
			<tr>
				<td>
					$caption&nbsp;(опционально)</td>
				<td>
					выводить или не выводить описание над генерируемым полем</td>
			</tr>
			<tr>
				<td>
					$dateDiv&nbsp;(опционально)</td>
				<td>
					разделитель полей для даты, по умолчанию "-"</td>
			</tr>
			<tr>
				<td>
					$timeDiv&nbsp;(опционально)</td>
				<td>
					разделитель полей для времени, по умолчанию ":"</td>
			</tr>
		</tbody>
	</table>
</div>',
    'Module_ID' => NULL,
    'Parent' => '',
    'Areas' => 'ClassForm_AddTemplate ClassForm_AddCond ClassForm_AddActionTemplate ClassForm_EditTemplate ClassForm_EditCond ClassForm_EditActionTemplate ClassForm_CheckActionTemplate',
  ),
  12 => 
  array (
    'Documentation_ID' => '105',
    'Type' => 'function',
    'Name' => 'nc_text_field',
    'Signature' => 'nc_text_field ( string $field_name, [string $style, [int $classID, [bool $caption, [bool $bbcode]]]] )',
    'ShortHelp' => 'Функция генерирует поле типа «Текстовый блок».',
    'FullHelp' => '<div>
	<table>
		<thead>
			<tr>
				<th scope="col">
					Параметры</th>
				<th scope="col">
					Описание</th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td>
					$field_name</td>
				<td>
					имя поля в текущем компоненте или компоненте $classID</td>
			</tr>
			<tr>
				<td>
					$style&nbsp;(опционально)</td>
				<td>
					параметры отображения поля, например:&nbsp;«size=\'50\'»</td>
			</tr>
			<tr>
				<td>
					$classID&nbsp;(опционально)</td>
				<td>
					идентификатор компонента, используется например, при выводе&nbsp;формы через функцию s_list_class() его следует указывать, по&nbsp;умолчанию этот параметр можно не задавать</td>
			</tr>
			<tr>
				<td>
					$caption&nbsp;(опционально)</td>
				<td>
					выводить или не выводить описание над генерируемым полем</td>
			</tr>
			<tr>
				<td>
					$bbcode&nbsp;(опционально)</td>
				<td>
					выводить панельку с BB-кодами (для панельки нужны стили CSS в&nbsp;макете дизайна!), по умолчанию отключено</td>
			</tr>
		</tbody>
	</table>
</div>',
    'Module_ID' => NULL,
    'Parent' => '',
    'Areas' => 'ClassForm_AddTemplate ClassForm_AddCond ClassForm_AddActionTemplate ClassForm_EditTemplate ClassForm_EditCond ClassForm_EditActionTemplate ClassForm_CheckActionTemplate',
  ),
  13 => 
  array (
    'Documentation_ID' => '106',
    'Type' => 'function',
    'Name' => 'nc_string_field',
    'Signature' => 'nc_string_field ( string $field_name, [string $style, [int $classID, [bool $caption]]] )',
    'ShortHelp' => 'Функция генерирует поле типа «Строка».',
    'FullHelp' => '<div>
	<table>
		<thead>
			<tr>
				<th scope="col">
					Параметры</th>
				<th scope="col">
					Описание</th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td>
					$field_name</td>
				<td>
					имя поля в текущем компоненте или компоненте $classID</td>
			</tr>
			<tr>
				<td>
					$style&nbsp;(опционально)</td>
				<td>
					параметры отображения поля, например:&nbsp;«size=\'50\'»</td>
			</tr>
			<tr>
				<td>
					$classID&nbsp;(опционально)</td>
				<td>
					идентификатор компонента, используется например, при выводе&nbsp;формы через функцию s_list_class() его следует указывать, по&nbsp;умолчанию этот параметр можно не задавать</td>
			</tr>
			<tr>
				<td>
					$caption&nbsp;(опционально)</td>
				<td>
					выводить или не выводить описание над генерируемым полем</td>
			</tr>
		</tbody>
	</table>
</div>',
    'Module_ID' => NULL,
    'Parent' => '',
    'Areas' => 'ClassForm_AddTemplate ClassForm_AddCond ClassForm_AddActionTemplate ClassForm_EditTemplate ClassForm_EditCond ClassForm_EditActionTemplate ClassForm_CheckActionTemplate',
  ),
  14 => 
  array (
    'Documentation_ID' => '107',
    'Type' => 'function',
    'Name' => 'nc_int_field',
    'Signature' => 'nc_int_field ( string $field_name, [string $style, [int $classID, [bool $caption]]] )',
    'ShortHelp' => 'Функция генерирует поле типа «Целое число».',
    'FullHelp' => '<div>
	<table>
		<thead>
			<tr>
				<th scope="col">
					Параметры</th>
				<th scope="col">
					Описание</th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td>
					$field_name</td>
				<td>
					имя поля в текущем компоненте или компоненте $classID</td>
			</tr>
			<tr>
				<td>
					$style&nbsp;(опционально)</td>
				<td>
					параметры отображения поля, например:&nbsp;«size=\'50\'»</td>
			</tr>
			<tr>
				<td>
					$classID&nbsp;(опционально)</td>
				<td>
					идентификатор компонента, используется например, при выводе&nbsp;формы через функцию s_list_class() его следует указывать, по&nbsp;умолчанию этот параметр можно не задавать</td>
			</tr>
			<tr>
				<td>
					$caption&nbsp;(опционально)</td>
				<td>
					выводить или не выводить описание над генерируемым полем</td>
			</tr>
		</tbody>
	</table>
</div>',
    'Module_ID' => NULL,
    'Parent' => '',
    'Areas' => 'ClassForm_AddTemplate ClassForm_AddCond ClassForm_AddActionTemplate ClassForm_EditTemplate ClassForm_EditCond ClassForm_EditActionTemplate ClassForm_CheckActionTemplate',
  ),
  15 => 
  array (
    'Documentation_ID' => '108',
    'Type' => 'function',
    'Name' => 'nc_float_field',
    'Signature' => 'nc_float_field ( string $field_name, [string $style, [int $classID, [bool $caption]]] )',
    'ShortHelp' => 'Функция генерирует поле типа «Число с плавающей запятой».',
    'FullHelp' => '<table>
	<thead>
		<tr>
			<th scope="col">
				Параметры</th>
			<th scope="col">
				Описание</th>
		</tr>
	</thead>
	<tbody>
		<tr>
			<td>
				$field_name</td>
			<td>
				имя поля в текущем компоненте или компоненте $classID</td>
		</tr>
		<tr>
			<td>
				$style&nbsp;(опционально)</td>
			<td>
				параметры отображения поля, например:&nbsp;«size=\'50\'»</td>
		</tr>
		<tr>
			<td>
				$classID</td>
			<td>
				идентификатор компонента, используется например, при выводе&nbsp;формы через функцию s_list_class() его следует указывать, по&nbsp;умолчанию этот параметр можно не задавать</td>
		</tr>
		<tr>
			<td>
				$caption</td>
			<td>
				выводить или не выводить описание над генерируемым полем</td>
		</tr>
	</tbody>
</table>',
    'Module_ID' => NULL,
    'Parent' => '',
    'Areas' => 'ClassForm_AddTemplate ClassForm_AddCond ClassForm_AddActionTemplate ClassForm_EditTemplate ClassForm_EditCond ClassForm_EditActionTemplate ClassForm_CheckActionTemplate',
  ),
  16 => 
  array (
    'Documentation_ID' => '109',
    'Type' => 'function',
    'Name' => 'nc_multilist_field',
    'Signature' => 'nc_multilist_field ( string $field_name, [string $style, [string $type, [int $classID, [bool $caption, [string $selected, [bool $disabled, [bool $getData]]]]]] )',
    'ShortHelp' => 'Функция генерирует поле типа «Множественный выбор»',
    'FullHelp' => '<table>
	<thead>
		<tr>
			<th scope="col">
				Параметры</th>
			<th scope="col">
				Описание</th>
		</tr>
	</thead>
	<tbody>
		<tr>
			<td>
				$field_name</td>
			<td>
				имя поля в текущем компоненте или компоненте $classID</td>
		</tr>
		<tr>
			<td>
				$style&nbsp;(опционально)</td>
			<td>
				параметры отображения поля, например:&nbsp;«size=\'50\'»</td>
		</tr>
		<tr>
			<td>
				$type (опционально)</td>
			<td>
				тип: "select", "select:N", "checkbox", по умолчанию - "select:3"</td>
		</tr>
		<tr>
			<td>
				$classID (опционально)</td>
			<td>
				идентификатор компонента, используется например, при выводе&nbsp;формы через функцию s_list_class() его следует указывать, по&nbsp;умолчанию этот параметр можно не задавать</td>
		</tr>
		<tr>
			<td>
				$caption (опционально)</td>
			<td>
				&nbsp;выводить или не выводить описание над генерируемым полем</td>
		</tr>
		<tr>
			<td>
				$selected (опционально)</td>
			<td>
				выбранные элементы. Передаются через строку, где id выбранных&nbsp;элементов разделены между собой символами \'[пробел]\', \'.\', \',\' , \';\'</td>
		</tr>
		<tr>
			<td>
				$disabled &nbsp;(опционально)</td>
			<td>
				недоступные&nbsp;элементы.&nbsp;Формат&nbsp;аргумента&nbsp;аналогичен</td>
		</tr>
		<tr>
			<td>
				<div>
					$getData&nbsp;(опционально)</div>
			</td>
			<td>
				принудительно вытаскивать из базы</td>
		</tr>
	</tbody>
</table>',
    'Module_ID' => NULL,
    'Parent' => '',
    'Areas' => 'ClassForm_AddTemplate ClassForm_AddCond ClassForm_AddActionTemplate ClassForm_EditTemplate ClassForm_EditCond ClassForm_EditActionTemplate ClassForm_CheckActionTemplate',
  ),
  17 => 
  array (
    'Documentation_ID' => '110',
    'Type' => 'function',
    'Name' => 'nc_list_select',
    'Signature' => 'nc_list_select (string $classificator_name, [string $field_name, [int $current_value, [int $sort_type, [int $sort_direction, [string $template_prefix, [string $template_object, [string $template_suffix, [string $template_any, [bool $ignore_check]]]]]]]]])',
    'ShortHelp' => 'Данная функция позволяет генерировать HTML списки из Списков
Netcat.',
    'FullHelp' => '<p>
	В большинстве случаев она применяется в альтернативных формах компонента, чтобы вывести информацию о поле типа Список.</p>
<table>
	<thead>
		<tr>
			<th scope="col">
				Параметры</th>
			<th scope="col">
				Описание</th>
		</tr>
	</thead>
	<tbody>
		<tr>
			<td>
				$classificator_name</td>
			<td>
				имя списка, например, Gallery, обязательный параметр</td>
		</tr>
		<tr>
			<td>
				$field_name имя&nbsp;(опционально)</td>
			<td>
				поля в компоненте (без префикса f_, например, Field)</td>
		</tr>
		<tr>
			<td>
				$current_value (опционально)</td>
			<td>
				выбранный элемент списка&nbsp;(например, $f_Field_id, если мы используем функцию в&nbsp;альтернативной форме добавления/изменения)</td>
		</tr>
		<tr>
			<td>
				$sort_type (опционально)</td>
			<td>
				поле сортировки: 0 – ID (по умолчанию), 1 – имя, 2 - приоритет</td>
		</tr>
		<tr>
			<td>
				$sort_direction (опционально)</td>
			<td>
				порядок сортировки: 0 – восходящий (по умолчанию), 1 –&nbsp;нисходящий)</td>
		</tr>
		<tr>
			<td>
				$template_prefix (опционально)</td>
			<td>
				темплейт префикса списка, по умолчанию: "&lt;select name=\'f_\\$field_name\'&gt;\\r\\n"</td>
		</tr>
		<tr>
			<td>
				$template_suffix (опционально)</td>
			<td>
				темплейт суффикса списка, по умолчанию: "&lt;/select&gt;\\r\\n"</td>
		</tr>
		<tr>
			<td>
				$template_any (опционально)</td>
			<td>
				темплейт для первого нулевого элемента списка, если поле&nbsp;может быть пустым, по умолчанию:&nbsp;&lt;option value=\'\'&gt;--выбрать--&lt;/option&gt;</td>
		</tr>
		<tr>
			<td>
				$ignore_check</td>
			<td>
				игнорировать выборку только включенных</td>
		</tr>
	</tbody>
</table>
<p>
	<strong>Внимание!</strong> Не забывайте экранировать кавычки!</p>
<div>
	<div>
		Поле $template_prefix имеет «константу». Переменная, а точнее запись</div>
	<div>
		вида «\\$field_name», автоматически заменится на указанный в вызове</div>
	<div>
		функции параметр $field_name.</div>
	<div>
		Поле $template_object имеет 3 «константы»: «\\$value_id», «\\</div>
	<div>
		$value_selected» и «\\$value_name», которые отвечают за подстановку ID,</div>
	<div>
		выбранной записи и название элемента.</div>
</div>',
    'Module_ID' => NULL,
    'Parent' => '',
    'Areas' => 'ClassForm_AddTemplate ClassForm_AddCond ClassForm_AddActionTemplate ClassForm_EditTemplate ClassForm_EditCond ClassForm_EditActionTemplate ClassForm_CheckActionTemplate',
  ),
  18 => 
  array (
    'Documentation_ID' => '111',
    'Type' => 'function',
    'Name' => 'nc_message_link',
    'Signature' => 'nc_message_link ( int $message_id, int $class_id, [$action=\'\'] )',
    'ShortHelp' => 'Функция позволяет получить относительный путь к объекту (без домена)
по номеру (ID) этого объекта и номеру (ID) компонента, которому он
принадлежит.',
    'FullHelp' => '<table>
	<thead>
		<tr>
			<th scope="col">
				Параметры</th>
			<th scope="col">
				Описание</th>
		</tr>
	</thead>
	<tbody>
		<tr>
			<td>
				$message_id</td>
			<td>
				номер объекта</td>
		</tr>
		<tr>
			<td>
				$class_id</td>
			<td>
				номер компонента</td>
		</tr>
		<tr>
			<td>
				$action&nbsp;(опционально)</td>
			<td>
				действие с объектом:<br />
				edit — редактирование<br />
				delete — удаление<br />
				drop — удаление без подтверждения<br />
				checked — смена статуса,<br />
				по умолчанию — просмотр&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; .</td>
		</tr>
	</tbody>
</table>
<p>
	Будьте внимательны, не путайте номера компонента и номер компонента раздела. Номер компонента Вы можете узнать, например, в разделе «Список компонентов» системы администрирования.</p>',
    'Module_ID' => NULL,
    'Parent' => '',
    'Areas' => 'ClassForm TemplateForm',
  ),
  19 => 
  array (
    'Documentation_ID' => '112',
    'Type' => 'function',
    'Name' => 'parentofmessage',
    'Signature' => 'parentofmessage ( int $message_id, int $class_id )',
    'ShortHelp' => 'Возвращает номер объекта – родителя «ветки» объектов (может
использоваться в иерархическом форуме), в которой, в частности,
содержится объект с номером $message_id.',
    'FullHelp' => '<table>
	<thead>
		<tr>
			<th scope="col">
				Параметры</th>
			<th scope="col">
				Описание</th>
		</tr>
	</thead>
	<tbody>
		<tr>
			<td>
				$message_id</td>
			<td>
				номер объекта</td>
		</tr>
		<tr>
			<td>
				$class_id</td>
			<td>
				номер компонента</td>
		</tr>
	</tbody>
</table>',
    'Module_ID' => NULL,
    'Parent' => '',
    'Areas' => 'ClassForm TemplateForm',
  ),
  20 => 
  array (
    'Documentation_ID' => '113',
    'Type' => 'function',
    'Name' => 's_list_class',
    'Signature' => 's_list_class ( int $sub, int $cc, [char $params, [bool $show_in_admin_mode]] )',
    'ShortHelp' => '<div>
	Функция выводит "верхние" объекты из раздела $sub компонента раздела $cc с параметрами $params.</div>',
    'FullHelp' => '<table>
	<thead>
		<tr>
			<th scope="col">
				Параметры</th>
			<th scope="col">
				Описание</th>
		</tr>
	</thead>
	<tbody>
		<tr>
			<td>
				$sub</td>
			<td>
				номер раздела</td>
		</tr>
		<tr>
			<td>
				$cc</td>
			<td>
				номер компонента в разделе</td>
		</tr>
		<tr>
			<td>
				$params&nbsp;(опционально)</td>
			<td>
				может содержать произвольный состав параметров. Все они&nbsp;могут быть обработаны в тексте шаблона вывода объекта.&nbsp;Параметры подаются в URL-формате: &amp;param1=12&amp;param2=46.</td>
		</tr>
		<tr>
			<td>
				$show_in_admin_mode (опционально)&nbsp;</td>
			<td>
				если он имеет значение TRUE (истина), этот блок будет выведен&nbsp;и в режиме администрирования, иначе только в обычном режиме&nbsp;работы сайта</td>
		</tr>
	</tbody>
</table>',
    'Module_ID' => NULL,
    'Parent' => '',
    'Areas' => 'ClassForm TemplateForm',
  ),
  21 => 
  array (
    'Documentation_ID' => '114',
    'Type' => 'function',
    'Name' => 'nc_usergroup_create',
    'Signature' => 'nc_usergroup_create ( string $name )',
    'ShortHelp' => 'Функция создает группу пользователей именем $name.',
    'FullHelp' => '',
    'Module_ID' => NULL,
    'Parent' => '',
    'Areas' => 'ClassForm TemplateForm',
  ),
  22 => 
  array (
    'Documentation_ID' => '115',
    'Type' => 'function',
    'Name' => 'nc_usergroup_rename',
    'Signature' => 'nc_usergroup_rename ( int $PermissionGroupID, string $name )',
    'ShortHelp' => 'Функция переименовывает группу пользователей.',
    'FullHelp' => '<table>
	<thead>
		<tr>
			<th scope="col">
				Параметры</th>
			<th scope="col">
				Описание</th>
		</tr>
	</thead>
	<tbody>
		<tr>
			<td>
				$PermissionGroupID</td>
			<td>
				идентификатор группы</td>
		</tr>
		<tr>
			<td>
				$name</td>
			<td>
				имя группы</td>
		</tr>
	</tbody>
</table>',
    'Module_ID' => NULL,
    'Parent' => '',
    'Areas' => 'ClassForm TemplateForm',
  ),
  23 => 
  array (
    'Documentation_ID' => '116',
    'Type' => 'function',
    'Name' => 'nc_usergroup_delete',
    'Signature' => 'nc_usergroup_delete ( mixed $PermissionGroupID )',
    'ShortHelp' => 'Функция удаляет группу(-ы) пользователей по идентификатору группы
или массиву с идентификаторами групп $PermissionGroupID.',
    'FullHelp' => 'Группу нельзя удалить, если есть хотя бы один пользователь, который
состоит только в этой группе. Функция возвращает массив с номерами
удаленных групп.',
    'Module_ID' => NULL,
    'Parent' => '',
    'Areas' => 'ClassForm TemplateForm',
  ),
  24 => 
  array (
    'Documentation_ID' => '117',
    'Type' => 'function',
    'Name' => 'nc_usergroup_add_to_group',
    'Signature' => 'nc_usergroup_add_to_group ( int $UserID, int $PermissionGroupID )',
    'ShortHelp' => 'Функция добавляет пользователя в группу.',
    'FullHelp' => '<table>
	<thead>
		<tr>
			<th scope="col">
				Параметры</th>
			<th scope="col">
				Описание</th>
		</tr>
	</thead>
	<tbody>
		<tr>
			<td>
				$UserID</td>
			<td>
				идентификатор пользователя</td>
		</tr>
		<tr>
			<td>
				$PermissionGroupID</td>
			<td>
				идентификатор группы&nbsp;</td>
		</tr>
	</tbody>
</table>',
    'Module_ID' => NULL,
    'Parent' => '',
    'Areas' => 'ClassForm TemplateForm',
  ),
  25 => 
  array (
    'Documentation_ID' => '118',
    'Type' => 'function',
    'Name' => 'nc_usergroup_remove_from_group',
    'Signature' => 'nc_usergroup_remove_from_group ( int $UserID, int $PermissionGroupID )',
    'ShortHelp' => 'Функция исключает пользователя из группы. (Пользователь должен
состоять хотя бы в одной группе.)',
    'FullHelp' => '<table>
	<thead>
		<tr>
			<th scope="col">
				Параметры</th>
			<th scope="col">
				Описание</th>
		</tr>
	</thead>
	<tbody>
		<tr>
			<td>
				$UserID</td>
			<td>
				идентификатор пользователя</td>
		</tr>
		<tr>
			<td>
				$PermissionGroupID</td>
			<td>
				идентификатор группы</td>
		</tr>
	</tbody>
</table>',
    'Module_ID' => NULL,
    'Parent' => '',
    'Areas' => 'ClassForm TemplateForm',
  ),
  26 => 
  array (
    'Documentation_ID' => '119',
    'Type' => 'function',
    'Name' => 'nc_usergroup_get_users_from_group',
    'Signature' => 'nc_usergroup_get_users_from_group ( int $PermissionGroupID , [int $output_type])',
    'ShortHelp' => 'Функция возвращает всех пользователей, находящихся в группе
PermissionGroupID.',
    'FullHelp' => '<table>
	<thead>
		<tr>
			<th scope="col">
				Параметры</th>
			<th scope="col">
				Описание</th>
		</tr>
	</thead>
	<tbody>
		<tr>
			<td>
				$PermissionGroupID</td>
			<td>
				идентификатор группы</td>
		</tr>
		<tr>
			<td>
				$output_type&nbsp;(опционально)</td>
			<td>
				тип выходного массива:<br />
				&nbsp;0: просто номера пользователей [0] =&gt; user1 id, [1] =&gt; user2&nbsp;id<br />
				1: [group_id][user_id] =&gt; Login</td>
		</tr>
	</tbody>
</table>',
    'Module_ID' => NULL,
    'Parent' => '',
    'Areas' => 'ClassForm TemplateForm',
  ),
  27 => 
  array (
    'Documentation_ID' => '120',
    'Type' => 'function',
    'Name' => 'nc_usergroup_get_group_by_user',
    'Signature' => 'nc_usergroup_get_group_by_user ( int $UserID , int $output_type = 0 )',
    'ShortHelp' => 'Функция возвращает все группы, в которых состоит пользователь.',
    'FullHelp' => '<table>
	<thead>
		<tr>
			<th scope="col">
				Параметры</th>
			<th scope="col">
				Описание</th>
		</tr>
	</thead>
	<tbody>
		<tr>
			<td>
				$UserID</td>
			<td>
				идентификатор пользователя</td>
		</tr>
		<tr>
			<td>
				$output_type&nbsp;(опционально)</td>
			<td>
				тип выходного массива:<br />
				0:&nbsp;на выходе одномерный числовой массив, значения&nbsp;элементов которых соответствует идентификатором групп<br />
				1: на выходе одномерный массив, значения элементов&nbsp;которых соответствует названиям групп, а&nbsp;индекс -&nbsp;номерам групп</td>
		</tr>
	</tbody>
</table>',
    'Module_ID' => NULL,
    'Parent' => '',
    'Areas' => 'ClassForm TemplateForm',
  ),
  28 => 
  array (
    'Documentation_ID' => '121',
    'Type' => 'function',
    'Name' => 'is_even',
    'Signature' => 'is_even ( int $param )',
    'ShortHelp' => '<p>Проверяет параметр на четность.</p>',
    'FullHelp' => '',
    'Module_ID' => NULL,
    'Parent' => '',
    'Areas' => 'ClassForm TemplateForm',
  ),
  29 => 
  array (
    'Documentation_ID' => '122',
    'Type' => 'function',
    'Name' => 'listQuery',
    'Signature' => 'listQuery (char $sql_query, [char $output_template, [char $divider]] )',
    'ShortHelp' => '<p>Функция производит запрос $sql_query к базе данных, форматирует в
соответствии с шаблоном $output_template и выводит
результаты
запроса.</p>',
    'FullHelp' => '<table>
	<thead>
		<tr>
			<th scope="col">
				Параметры</th>
			<th scope="col">
				Описание</th>
		</tr>
	</thead>
	<tbody>
		<tr>
			<td>
				$sql_query</td>
			<td>
				запрос к БД</td>
		</tr>
		<tr>
			<td>
				$output_template&nbsp;(опционально)&nbsp;</td>
			<td>
				шаблон вывода результатов запроса</td>
		</tr>
		<tr>
			<td>
				$divider (опционально)&nbsp;</td>
			<td>
				разделитель между результатами</td>
		</tr>
	</tbody>
</table>
<p>
	В шаблоне $output_template могут использоваться обращения к массиву $data[] с маскировочным слешем перед знаком $: \\$data[Name].</p>
<p>
	В качестве индексов массива используются названия столбцов таблиц, из которых происходит выборка.</p>',
    'Module_ID' => NULL,
    'Parent' => '',
    'Areas' => 'ClassForm TemplateForm',
  ),
  30 => 
  array (
    'Documentation_ID' => '123',
    'Type' => 'function',
    'Name' => 'nc_bbcode',
    'Signature' => 'nc_bbcode ( string $text, [string $cut_link, [bool $cut_full, [array $codes]]] )',
    'ShortHelp' => 'Обрабатывает текст с BB-кодами.',
    'FullHelp' => '',
    'Module_ID' => NULL,
    'Parent' => '',
    'Areas' => 'ClassForm TemplateForm',
  ),
  31 => 
  array (
    'Documentation_ID' => '124',
    'Type' => 'function',
    'Name' => 'nc_bbcode_bar',
    'Signature' => 'nc_bbcode_bar ( string $winID, string $formID, string $textareaID, [bool $help, [array $codes="", [string $prefix="", [string $suffix=""]]]] )',
    'ShortHelp' => 'Отображает панельку вставки BB-кодов.',
    'FullHelp' => '',
    'Module_ID' => NULL,
    'Parent' => '',
    'Areas' => 'ClassForm TemplateForm',
  ),
  32 => 
  array (
    'Documentation_ID' => '125',
    'Type' => 'function',
    'Name' => 'nc_bbcode_clear',
    'Signature' => 'nc_bbcode_clear ( string $text )',
    'ShortHelp' => 'Функция очистки текста от BB-кодов (кроме URL).',
    'FullHelp' => '',
    'Module_ID' => NULL,
    'Parent' => '',
    'Areas' => 'ClassForm TemplateForm',
  ),
  33 => 
  array (
    'Documentation_ID' => '126',
    'Type' => 'function',
    'Name' => 'nc_array_to_string',
    'Signature' => 'nc_array_to_string ( array $arr, array $template )',
    'ShortHelp' => 'Переводит массив в строку по шаблону.',
    'FullHelp' => '<table>
	<thead>
		<tr>
			<th scope="col">
				Параметры</th>
			<th scope="col">
				Описание</th>
		</tr>
	</thead>
	<tbody>
		<tr>
			<td>
				$arr</td>
			<td>
				непосредственно сам массив</td>
		</tr>
		<tr>
			<td>
				$template</td>
			<td>
				массив с шаблонами, должен иметь следующие ключи:<br />
				prefix<br />
				element<br />
				divider<br />
				suffix</td>
		</tr>
	</tbody>
</table>
<div>
	В элементе с ключом element можно использовать макропеременные:</div>
<div>
	1. %ELEMENT - текущий элемент массива $arr</div>
<div>
	2. %KEY - его ключ</div>
<div>
	3. %I - номер по порядку (отсчет начинается с 1 )</div>',
    'Module_ID' => NULL,
    'Parent' => '',
    'Areas' => 'ClassForm TemplateForm',
  ),
  34 => 
  array (
    'Documentation_ID' => '127',
    'Type' => 'function',
    'Name' => 'nc_mail2queue',
    'Signature' => 'nc_mail2queue ( string $recipient, string $from, string $subject, string $message )',
    'ShortHelp' => 'Функция является частью функционала по рассылке писем. Указанное
письмо кладется в таблицу БД mail_queue, откуда в дальнейшем будет
разослано скриптом /netcat/admin/mailer.php.',
    'FullHelp' => '<table>
	<thead>
		<tr>
			<th scope="col">
				Параметры</th>
			<th scope="col">
				Описание</th>
		</tr>
	</thead>
	<tbody>
		<tr>
			<td>
				$recipient</td>
			<td>
				содержит адрес получателя</td>
		</tr>
		<tr>
			<td>
				$from</td>
			<td>
				содержит координаты отправителя</td>
		</tr>
		<tr>
			<td>
				$subject</td>
			<td>
				тема письма</td>
		</tr>
		<tr>
			<td>
				$message</td>
			<td>
				сообщение&nbsp;</td>
		</tr>
	</tbody>
</table>
<p>
	При желании Вы можете отсылать даже письма с файлами – для этого при помощи каких-либо средств необходимо сгенерировать правильный $message. Однако учтите, что письмо кладется в БД ровно столько раз, сколько подписчиков его должны получить, и при большом вложенном файле размер БД может значительно вырасти.</p>
<p>
	Файл /netcat/admin/mailer.php, отвечает за порционную рассылку писем. Количество писем задается параметром number, если не указано – высылается 20 писем из очереди. Этот скрипт должен быть добавлен в CRON Вашего сервера (каждую минуту, /netcat/admin/mailer.php?number=20). При наличии писем в очереди они будут отсылаться порциями. При отсутствии писем скрипт ничего делать не будет.</p>',
    'Module_ID' => NULL,
    'Parent' => '',
    'Areas' => 'ClassForm TemplateForm',
  ),
  35 => 
  array (
    'Documentation_ID' => '128',
    'Type' => 'function',
    'Name' => 'nc_file_path',
    'Signature' => 'nc_file_path ( int $class_id, int $message_id, mixed $field_name, [string $file_name_prefix] )',
    'ShortHelp' => 'Функция позволяет получить путь к файлу, указанному в определенном
поле, по номеру (ID) этого объекта и номеру (ID) компонента, которому
он принадлежит.',
    'FullHelp' => '<table>
	<thead>
		<tr>
			<th scope="col">
				Параметры</th>
			<th scope="col">
				Описание</th>
		</tr>
	</thead>
	<tbody>
		<tr>
			<td>
				$class_id</td>
			<td>
				номер компонента (для системных таблиц – название&nbsp;таблицы, допустимые значения – “Catalogue”, “Subdivision”,&nbsp;“User”, “Template”)</td>
		</tr>
		<tr>
			<td>
				$message_id</td>
			<td>
				номер объекта</td>
		</tr>
		<tr>
			<td>
				$field_name</td>
			<td>
				название поля или его идентификатор в компоненте</td>
		</tr>
		<tr>
			<td>
				$file_name_prefix (опционально)&nbsp;</td>
			<td>
				укажите “h_”, если хотите получить ссылку для скачивания&nbsp;файла под оригинальным именем (подробнее см. в&nbsp;разделе «Файловая система» настоящего руководства)</td>
		</tr>
	</tbody>
</table>
<p>
	Будьте внимательны, не путайте номера компонента и номер компонента в разделе. Номер компонента вы можете узнать, например, в разделе «Список компонентов» системы администрирования.</p>
<p>
	Если в указанном поле файл не был закачан, функция возвращает NULL. При неправильно указанном имени или идентификаторе поля функция сообщит об ошибке и вернет NULL.</p>',
    'Module_ID' => NULL,
    'Parent' => '',
    'Areas' => 'ClassForm TemplateForm',
  ),
  36 => 
  array (
    'Documentation_ID' => '129',
    'Type' => 'function',
    'Name' => 'nc_get_visual_settings',
    'Signature' => 'nc_get_visual_settings ( int $cc )',
    'ShortHelp' => 'Функция возвращает текущие визуальные настройки компонента в
разделе по его идентификатору $cc.',
    'FullHelp' => 'Возвращаемый результат — массив данных.',
    'Module_ID' => NULL,
    'Parent' => '',
    'Areas' => 'ClassForm TemplateForm',
  ),
  37 => 
  array (
    'Documentation_ID' => '130',
    'Type' => 'function',
    'Name' => 'opt',
    'Signature' => 'opt ( $flag, $string )',
    'ShortHelp' => 'Функция выводит строку $string в случае если $flag не ложь, не имеет
пустое значение или не ноль.',
    'FullHelp' => '',
    'Module_ID' => NULL,
    'Parent' => '',
    'Areas' => 'ClassForm TemplateForm',
  ),
  38 => 
  array (
    'Documentation_ID' => '131',
    'Type' => 'function',
    'Name' => 'opt_case',
    'Signature' => 'opt_case ( $flag, $string1, $string2 )',
    'ShortHelp' => 'Выводит строку $string1 в случае если $flag не ложь, не имеет пустое
значение или не ноль; в противном случае выводит строку $string2.',
    'FullHelp' => '',
    'Module_ID' => NULL,
    'Parent' => '',
    'Areas' => 'ClassForm TemplateForm',
  ),
  39 => 
  array (
    'Documentation_ID' => '132',
    'Type' => 'function',
    'Name' => 'encode_host',
    'Signature' => 'encode_host ( string $host )',
    'ShortHelp' => '<p>Функция возвращает закодированное в punycode значение параметра
$host, содержащее доменное имя.</p>',
    'FullHelp' => '',
    'Module_ID' => NULL,
    'Parent' => '',
    'Areas' => 'ClassForm TemplateForm',
  ),
  40 => 
  array (
    'Documentation_ID' => '133',
    'Type' => 'function',
    'Name' => 'decode_host',
    'Signature' => 'decode_host ( string $host )',
    'ShortHelp' => '<p>Функция возвращает декодированное значение параметра
$host содержащее доменное имя в формате punycode.</p>',
    'FullHelp' => '',
    'Module_ID' => NULL,
    'Parent' => '',
    'Areas' => 'ClassForm TemplateForm',
  ),
  41 => 
  array (
    'Documentation_ID' => '134',
    'Type' => 'variable',
    'Name' => 'cc',
    'Signature' => 'cc',
    'ShortHelp' => 'Номер (ID) текущего инфоблока.',
    'FullHelp' => 'Переменной присваивается значение поля Sub_Class_ID таблицы Sub_Class, соответствующее текущему инфоблоку.',
    'Module_ID' => NULL,
    'Parent' => '',
    'Areas' => 'ClassForm TemplateForm',
  ),
  42 => 
  array (
    'Documentation_ID' => '135',
    'Type' => 'variable',
    'Name' => 'cc_array[]',
    'Signature' => 'cc_array',
    'ShortHelp' => 'Массив номеров инфоблоков в текущем разделе.',
    'FullHelp' => 'Массив индексов (номеров) компонентов текущего раздела, отсортированных по приоритету. Нулевой элемент ($cc_array[0]) – компонент с наименьшим приоритетом.',
    'Module_ID' => NULL,
    'Parent' => '',
    'Areas' => 'ClassForm TemplateForm',
  ),
  43 => 
  array (
    'Documentation_ID' => '136',
    'Type' => 'variable',
    'Name' => 'admin_mode',
    'Signature' => 'admin_mode',
    'ShortHelp' => 'Определение текущего режима страницы.',
    'FullHelp' => 'Логическая переменная. Если страница находится в режиме просмотра, то равна FALSE, если в режиме администирования - TRUE.',
    'Module_ID' => NULL,
    'Parent' => '',
    'Areas' => 'ClassForm TemplateForm',
  ),
  44 => 
  array (
    'Documentation_ID' => '137',
    'Type' => 'variable',
    'Name' => 'catalogue',
    'Signature' => 'catalogue',
    'ShortHelp' => 'Номер (ID) текущего сайта.',
    'FullHelp' => 'Переменной присваивается значение поля Catalogue_ID таблицы Catalogue, соответствующее текущему сайту.',
    'Module_ID' => NULL,
    'Parent' => '',
    'Areas' => 'ClassForm TemplateForm',
  ),
  45 => 
  array (
    'Documentation_ID' => '138',
    'Type' => 'variable',
    'Name' => 'AUTH_USER_ID',
    'Signature' => 'AUTH_USER_ID',
    'ShortHelp' => 'Номер авторизованного пользователя.',
    'FullHelp' => 'Номер (ID) в таблице User пользователя, авторизованного на странице. Если посетитель не авторизован, содержит 0.',
    'Module_ID' => NULL,
    'Parent' => '',
    'Areas' => 'ClassForm TemplateForm',
  ),
  46 => 
  array (
    'Documentation_ID' => '139',
    'Type' => 'variable',
    'Name' => 'current_catalogue[]',
    'Signature' => 'current_catalogue',
    'ShortHelp' => 'Массив свойств текущего сайта.',
    'FullHelp' => 'Хэш-массив всех свойств текущего сайта (атрибутов таблицы Catalogue). Пример: $current_catalogue[\'Catalogue_ID\'].',
    'Module_ID' => NULL,
    'Parent' => '',
    'Areas' => 'ClassForm TemplateForm',
  ),
  47 => 
  array (
    'Documentation_ID' => '140',
    'Type' => 'variable',
    'Name' => 'current_cc[]',
    'Signature' => 'current_cc',
    'ShortHelp' => 'Массив свойств текущего раздела.',
    'FullHelp' => 'Хэш-массив всех свойств текущего сайта (атрибутов таблицы Sub_Class). Пример: $current_cc[\'Sub_Class_Name\'].',
    'Module_ID' => NULL,
    'Parent' => '',
    'Areas' => 'ClassForm TemplateForm',
  ),
  48 => 
  array (
    'Documentation_ID' => '141',
    'Type' => 'variable',
    'Name' => 'current_sub[]',
    'Signature' => 'current_sub',
    'ShortHelp' => 'Массив свойств текущего раздела.',
    'FullHelp' => 'Хэш-массив всех свойств текущего сайта (атрибутов таблицы Subdivision). Пример: $current_sub[\'Subdivision_Name\'].',
    'Module_ID' => NULL,
    'Parent' => '',
    'Areas' => 'ClassForm TemplateForm',
  ),
  49 => 
  array (
    'Documentation_ID' => '142',
    'Type' => 'variable',
    'Name' => 'current_user[]',
    'Signature' => 'current_user',
    'ShortHelp' => 'Массив свойств текущего раздела.',
    'FullHelp' => 'Хэш-массив всех свойств текущего сайта (атрибутов таблицы User). Пример: $current_user[\'Login\']. Массив определен только в случае, когда посетитель авторизован на сайте (то есть при наличии модуля "Личный кабинет"). Если модуля нет, номер авторизованного пользователя хранится в переменной $AUTH_USER_ID.',
    'Module_ID' => NULL,
    'Parent' => '',
    'Areas' => 'ClassForm TemplateForm',
  ),
  50 => 
  array (
    'Documentation_ID' => '143',
    'Type' => 'variable',
    'Name' => 'nc_parent_template_folder_path',
    'Signature' => 'nc_parent_template_folder_path',
    'ShortHelp' => 'URI папки макета дизайна.',
    'FullHelp' => 'Переменная содержит относительную ссылку (URI) на папку текущего макета дизайна (или корневого макета, если текущий макет является дочерним, а не корневым). В этой папке и/или ее подпапках рекомендуется хранить картинки, CSS-файлы и пр., необходимые для работы макета.',
    'Module_ID' => NULL,
    'Parent' => '',
    'Areas' => 'TemplateForm',
  ),
  51 => 
  array (
    'Documentation_ID' => '144',
    'Type' => 'variable',
    'Name' => 'template_settings[]',
    'Signature' => 'template_settings',
    'ShortHelp' => 'Массив, содержащий пользовательские настройки макета.',
    'FullHelp' => 'Если у макета заданы пользовательские настройки, этот хэш-массив содержит их значение (эти значения проставляются в настройках сайта или раздела, к которому подключен макет).',
    'Module_ID' => NULL,
    'Parent' => '',
    'Areas' => 'TemplateForm',
  ),
  52 => 
  array (
    'Documentation_ID' => '145',
    'Type' => 'macros',
    'Name' => 'NAME',
    'Signature' => 'NAME',
    'ShortHelp' => 'Название раздела.',
    'FullHelp' => '',
    'Module_ID' => NULL,
    'Parent' => '',
    'Areas' => 'TemplateForm_Settings',
  ),
  53 => 
  array (
    'Documentation_ID' => '146',
    'Type' => 'macros',
    'Name' => 'Footer',
    'Signature' => 'Footer',
    'ShortHelp' => 'Вывод футера родительского макета.',
    'FullHelp' => 'Если макет имеет родителя, эта макропеременная будет содержать текст футера (нижней части) родительского макета.',
    'Module_ID' => NULL,
    'Parent' => '',
    'Areas' => 'TemplateForm_Header TemplateForm_Footer',
  ),
  54 => 
  array (
    'Documentation_ID' => '147',
    'Type' => 'macros',
    'Name' => 'Header',
    'Signature' => 'Header',
    'ShortHelp' => 'Вывод хедера родительского макета.',
    'FullHelp' => 'Если макет имеет родителя, эта макропеременная будет содержать текст хедера (верхней части) родительского макета.',
    'Module_ID' => NULL,
    'Parent' => '',
    'Areas' => 'TemplateForm_Header TemplateForm_Footer',
  ),
  55 => 
  array (
    'Documentation_ID' => '148',
    'Type' => 'variable',
    'Name' => 'begRow',
    'Signature' => 'begRow',
    'ShortHelp' => 'Порядковый номер объекта, с которого начинается вывод списка объектов на текущей странице.',
    'FullHelp' => 'Нумерация начинается с первого объекта на первой странице. Таким образом, если объекты какого-либо компонента выводятся по 15 штук на странице, на второй странице листинга значение $begRow будет равно 16.',
    'Module_ID' => NULL,
    'Parent' => '',
    'Areas' => 'ClassForm_FormPrefix ClassForm_RecordTemplate ClassForm_FormSuffix ClassForm_RecordTemplateFull',
  ),
  56 => 
  array (
    'Documentation_ID' => '149',
    'Type' => 'macros',
    'Name' => 'URL',
    'Signature' => 'URL',
    'ShortHelp' => 'Ссылка на сайт/раздел/компонент.',
    'FullHelp' => '',
    'Module_ID' => NULL,
    'Parent' => '',
    'Areas' => 'TemplateForm_Settings',
  ),
  57 => 
  array (
    'Documentation_ID' => '150',
    'Type' => 'macros',
    'Name' => 'PARENT_SUB',
    'Signature' => 'PARENT_SUB',
    'ShortHelp' => 'Номер родительского раздела (только для разделов).',
    'FullHelp' => '',
    'Module_ID' => NULL,
    'Parent' => '',
    'Areas' => 'TemplateForm_Settings',
  ),
  58 => 
  array (
    'Documentation_ID' => '151',
    'Type' => 'macros',
    'Name' => 'KEYWORD',
    'Signature' => 'KEYWORD',
    'ShortHelp' => 'Ключевое слово раздела (только для разделов).',
    'FullHelp' => '',
    'Module_ID' => NULL,
    'Parent' => '',
    'Areas' => 'TemplateForm_Settings',
  ),
  59 => 
  array (
    'Documentation_ID' => '152',
    'Type' => 'macros',
    'Name' => 'SUB',
    'Signature' => 'SUB',
    'ShortHelp' => 'Номер раздела (только для разделов).',
    'FullHelp' => '',
    'Module_ID' => NULL,
    'Parent' => '',
    'Areas' => 'TemplateForm_Settings',
  ),
  60 => 
  array (
    'Documentation_ID' => '153',
    'Type' => 'macros',
    'Name' => 'COUNTER',
    'Signature' => 'COUNTER',
    'ShortHelp' => 'Номер выводимого элемента в списке (начиная с нуля).',
    'FullHelp' => '',
    'Module_ID' => NULL,
    'Parent' => '',
    'Areas' => 'TemplateForm_Settings',
  ),
  61 => 
  array (
    'Documentation_ID' => '154',
    'Type' => 'variable',
    'Name' => 'ccLink',
    'Signature' => 'ccLink',
    'ShortHelp' => 'Содержит путь (URI) к текущему инфоблоку.',
    'FullHelp' => '',
    'Module_ID' => NULL,
    'Parent' => '',
    'Areas' => 'ClassForm_RecordTemplate',
  ),
  62 => 
  array (
    'Documentation_ID' => '155',
    'Type' => 'variable',
    'Name' => 'f_AdminCommon',
    'Signature' => 'f_AdminCommon',
    'ShortHelp' => 'Панелька управления инфоблоком.',
    'FullHelp' => 'В режиме администрирования выводит панельку операций с инфоблоком: добавление объекта, настройка инфоблока, редактирование текста шаблона компонента, удаления объектов. Если в шаблоне вывода объекта в списке эта переменная указана не будет, вы не сможете изменить или удалить объекты этого компонента стандартными средствами системы.',
    'Module_ID' => NULL,
    'Parent' => '',
    'Areas' => 'ClassForm_FormPrefix ClassForm_RecordTemplate ClassForm_FormSuffix ClassForm_RecordTemplateFull',
  ),
  63 => 
  array (
    'Documentation_ID' => '156',
    'Type' => 'variable',
    'Name' => 'endRow',
    'Signature' => 'endRow',
    'ShortHelp' => 'Порядковый номер объекта, которым кончается вывод списка объектов на текущей странице.',
    'FullHelp' => 'Нумерация начинается с первого объекта на первой странице. Таким образом, если объекты какого-либо компонента выводятся по 15 штук на странице, на второй странице листинга значение $endRow будет равно 30.',
    'Module_ID' => NULL,
    'Parent' => '',
    'Areas' => 'ClassForm_FormPrefix ClassForm_RecordTemplate ClassForm_FormSuffix ClassForm_RecordTemplateFull',
  ),
  64 => 
  array (
    'Documentation_ID' => '157',
    'Type' => 'variable',
    'Name' => 'f_AdminButtons',
    'Signature' => 'f_AdminButtons',
    'ShortHelp' => 'Панелька управления объектом.',
    'FullHelp' => 'В режиме администрирования выводит панельку операций с объектами: изменения, удаления, включения-выключения, копирования. Если в шаблоне вывода объекта в списке или объекта на одной странице эта переменная указана не будет, вы не сможете изменить или удалить объекты этого компонента стандартными средствами системы.',
    'Module_ID' => NULL,
    'Parent' => '',
    'Areas' => 'ClassForm_RecordTemplate ClassForm_RecordTemplateFull',
  ),
  65 => 
  array (
    'Documentation_ID' => '158',
    'Type' => 'variable',
    'Name' => 'f_Checked',
    'Signature' => 'f_Checked',
    'ShortHelp' => 'Истина, если объект включен, и наоборот.',
    'FullHelp' => 'Используется в шаблоне вывода объекта в списке и на отдельной странице.',
    'Module_ID' => NULL,
    'Parent' => '',
    'Areas' => 'ClassForm_RecordTemplate ClassForm_RecordTemplateFull',
  ),
  66 => 
  array (
    'Documentation_ID' => '159',
    'Type' => 'variable',
    'Name' => 'f_Created',
    'Signature' => 'f_Created',
    'ShortHelp' => 'Дата и время создания объекта.',
    'FullHelp' => 'Дата и время создания (добавления) объекта в формате "гггг-мм-дд чч:мм:сс". Помимо полного формата вывода даты и времени доступны переменные $f_Created_year, $f_Created_month, $f_Created_day, $f_Created_hours, $f_Created_minutes, $f_Created_seconds, содержащие соответственно год, месяц, день, час, минуту и секунду добавления объекта. Используется в шаблоне вывода объекта в списке и на отдельной странице.',
    'Module_ID' => NULL,
    'Parent' => '',
    'Areas' => 'ClassForm_RecordTemplate ClassForm_RecordTemplateFull',
  ),
  67 => 
  array (
    'Documentation_ID' => '160',
    'Type' => 'variable',
    'Name' => 'f_IP',
    'Signature' => 'f_IP',
    'ShortHelp' => 'IP-адрес посетителя, добавившего объект.',
    'FullHelp' => 'Используется в шаблоне вывода объекта в списке и на отдельной странице.',
    'Module_ID' => NULL,
    'Parent' => '',
    'Areas' => 'ClassForm_RecordTemplate ClassForm_RecordTemplateFull',
  ),
  68 => 
  array (
    'Documentation_ID' => '161',
    'Type' => 'variable',
    'Name' => 'f_LastIP',
    'Signature' => 'f_LastIP',
    'ShortHelp' => 'IP-адрес посетителя, последним изменившего объект.',
    'FullHelp' => '',
    'Module_ID' => NULL,
    'Parent' => '',
    'Areas' => 'ClassForm_RecordTemplate ClassForm_RecordTemplateFull',
  ),
  69 => 
  array (
    'Documentation_ID' => '162',
    'Type' => 'variable',
    'Name' => 'f_LastUpdated',
    'Signature' => 'f_LastUpdated',
    'ShortHelp' => 'Дата и время последнего изменения этого объекта.',
    'FullHelp' => 'Используется в шаблоне вывода объекта в списке и на отдельной странице.',
    'Module_ID' => NULL,
    'Parent' => '',
    'Areas' => 'ClassForm_RecordTemplate ClassForm_RecordTemplateFull',
  ),
  70 => 
  array (
    'Documentation_ID' => '163',
    'Type' => 'variable',
    'Name' => 'f_LastUserAgent',
    'Signature' => 'f_LastUserAgent',
    'ShortHelp' => 'UserAgent пользователя, последним изменившего объект.',
    'FullHelp' => 'Используется в шаблоне вывода объекта в списке и на отдельной странице.',
    'Module_ID' => NULL,
    'Parent' => '',
    'Areas' => 'ClassForm_RecordTemplate ClassForm_RecordTemplateFull',
  ),
  71 => 
  array (
    'Documentation_ID' => '164',
    'Type' => 'variable',
    'Name' => 'f_LastUserID',
    'Signature' => 'f_LastUserID',
    'ShortHelp' => 'Номер пользователя, последним изменившего объект.',
    'FullHelp' => '',
    'Module_ID' => NULL,
    'Parent' => '',
    'Areas' => 'ClassForm_RecordTemplate ClassForm_RecordTemplateFull',
  ),
  72 => 
  array (
    'Documentation_ID' => '165',
    'Type' => 'variable',
    'Name' => 'f_RowID',
    'Signature' => 'f_RowID',
    'ShortHelp' => 'Номер (ID) объекта в таблице БД (MessageXX).',
    'FullHelp' => 'Используется в шаблоне вывода объекта в списке и на отдельной странице.',
    'Module_ID' => NULL,
    'Parent' => '',
    'Areas' => 'ClassForm_RecordTemplate ClassForm_RecordTemplateFull',
  ),
  73 => 
  array (
    'Documentation_ID' => '166',
    'Type' => 'variable',
    'Name' => 'f_RowNum',
    'Signature' => 'f_RowNum',
    'ShortHelp' => 'Порядковый номер объекта на странице.',
    'FullHelp' => 'Нумерация начинается с первого объекта, отображенного на странице. Используется в шаблоне вывода объекта в списке и на отдельной странице.',
    'Module_ID' => NULL,
    'Parent' => '',
    'Areas' => 'ClassForm_RecordTemplate',
  ),
  74 => 
  array (
    'Documentation_ID' => '167',
    'Type' => 'variable',
    'Name' => 'f_title',
    'Signature' => 'f_title',
    'ShortHelp' => 'Заголовок текущей страницы.',
    'FullHelp' => 'Выводит название текущего раздела за исключением случая, когда текущая страница представляет собой вывод одного объекта на странице и поле «Заголовок страницы» данного компонента определено.',
    'Module_ID' => NULL,
    'Parent' => '',
    'Areas' => 'TemplateForm',
  ),
  75 => 
  array (
    'Documentation_ID' => '168',
    'Type' => 'variable',
    'Name' => 'nc_next_object',
    'Signature' => 'nc_next_object',
    'ShortHelp' => 'Ссылка на следующий объект в списке.',
    'FullHelp' => 'Данная переменная содержит ссылку на "следующий" объект относительно текущего. Для определения порядка объектов используется переменная $query_order из системных настройках, а если она не задана — то значение поля "Сортировать объекты по" в настройках инфоблока.',
    'Module_ID' => NULL,
    'Parent' => '',
    'Areas' => 'ClassForm_RecordTemplate',
  ),
  76 => 
  array (
    'Documentation_ID' => '169',
    'Type' => 'variable',
    'Name' => 'nc_prev_object',
    'Signature' => 'nc_prev_object',
    'ShortHelp' => 'Ссылка на предыдущий объект в списке.',
    'FullHelp' => 'Данная переменная содержит ссылку на "предыдущий" объект относительно текущего. Для определения порядка объектов используется переменная $query_order из системных настройках, а если она не задана — то значение поля "Сортировать объекты по" в настройках инфоблока.',
    'Module_ID' => NULL,
    'Parent' => '',
    'Areas' => 'ClassForm_RecordTemplate',
  ),
  77 => 
  array (
    'Documentation_ID' => '170',
    'Type' => 'variable',
    'Name' => 'fullDateLink ',
    'Signature' => 'fullDateLink',
    'ShortHelp' => 'Ссылка на страницу с полным выводом объекта в виде
«.../2002/02/02/message_2.html».',
    'FullHelp' => 'Устанавливается в случае если в компоненте имеется поле типа «Дата и время» с форматом «event»,иначе значение переменной идентично значению $fullLink.',
    'Module_ID' => NULL,
    'Parent' => '',
    'Areas' => 'ClassForm_RecordTemplate ClassForm_RecordTemplateFull',
  ),
  78 => 
  array (
    'Documentation_ID' => '171',
    'Type' => 'variable',
    'Name' => 'fullLink',
    'Signature' => 'fullLink',
    'ShortHelp' => 'Ссылка на страницу с полным выводом объекта.',
    'FullHelp' => 'Ссылка формируется в «человеко-понятном» формате: /URLРаздела/КлючСловоКомпонентаРаздела_КлючСловоОбъекта.html или /URLРаздела/КлючСловоОбъекта.html, например, /about/team/ivanov.html',
    'Module_ID' => NULL,
    'Parent' => '',
    'Areas' => 'ClassForm_RecordTemplate ClassForm_RecordTemplateFull',
  ),
  79 => 
  array (
    'Documentation_ID' => '172',
    'Type' => 'variable',
    'Name' => 'editLink',
    'Signature' => 'editLink',
    'ShortHelp' => 'Ссылка на страницу редактирования объекта.',
    'FullHelp' => '',
    'Module_ID' => NULL,
    'Parent' => '',
    'Areas' => 'ClassForm_RecordTemplate ClassForm_RecordTemplateFull',
  ),
  80 => 
  array (
    'Documentation_ID' => '173',
    'Type' => 'variable',
    'Name' => 'deleteLink',
    'Signature' => 'deleteLink',
    'ShortHelp' => 'Ссылка на страницу удаления объекта.',
    'FullHelp' => '',
    'Module_ID' => NULL,
    'Parent' => '',
    'Areas' => 'ClassForm_RecordTemplate ClassForm_RecordTemplateFull',
  ),
  81 => 
  array (
    'Documentation_ID' => '174',
    'Type' => 'variable',
    'Name' => 'dropLink',
    'Signature' => 'dropLink',
    'ShortHelp' => 'Ссылка на страницу удаления объекта без подтверждения.',
    'FullHelp' => '',
    'Module_ID' => NULL,
    'Parent' => '',
    'Areas' => 'ClassForm_RecordTemplate ClassForm_RecordTemplateFull',
  ),
  82 => 
  array (
    'Documentation_ID' => '175',
    'Type' => 'variable',
    'Name' => 'checkedLink',
    'Signature' => 'checkedLink',
    'ShortHelp' => 'Ссылка на страницу включения-выключения объекта.',
    'FullHelp' => '',
    'Module_ID' => NULL,
    'Parent' => '',
    'Areas' => 'ClassForm_RecordTemplate ClassForm_RecordTemplateFull',
  ),
  83 => 
  array (
    'Documentation_ID' => '176',
    'Type' => 'variable',
    'Name' => 'prevLink',
    'Signature' => 'prevLink',
    'ShortHelp' => 'Переменная содержит ссылку на следующую страницу в листинге компонента.',
    'FullHelp' => 'Если текущее положение в списке – его конец, то переменная пустая.',
    'Module_ID' => NULL,
    'Parent' => '',
    'Areas' => 'ClassForm_RecordTemplate',
  ),
  84 => 
  array (
    'Documentation_ID' => '177',
    'Type' => 'variable',
    'Name' => 'nextLink',
    'Signature' => 'nextLink',
    'ShortHelp' => 'Переменная содержит ссылку на следующую страницу в листинге компонента.',
    'FullHelp' => 'Если текущее положение в списке – его конец, то
переменная пустая.',
    'Module_ID' => NULL,
    'Parent' => '',
    'Areas' => 'ClassForm_RecordTemplate',
  ),
  85 => 
  array (
    'Documentation_ID' => '178',
    'Type' => 'variable',
    'Name' => 'parent_sub_tree[]',
    'Signature' => 'parent_sub_tree',
    'ShortHelp' => 'Массивы свойств раздела различных уровней вложенности в реверсивном порядке.',
    'FullHelp' => 'От текущего раздела $parent_sub_tree[0] до свойств головного сайта $parent_sub_tree[$sub_level_count-1].',
    'Module_ID' => NULL,
    'Parent' => '',
    'Areas' => 'ClassForm TemplateForm',
  ),
  86 => 
  array (
    'Documentation_ID' => '179',
    'Type' => 'variable',
    'Name' => 'recNum',
    'Signature' => 'recNum',
    'ShortHelp' => 'Максимальное количество объектов для вывода.',
    'FullHelp' => 'Переменную можно подать в адресной строке (/news/?recNum=3), в функции s_list_class(), в системных настройках компонента.',
    'Module_ID' => NULL,
    'Parent' => '',
    'Areas' => 'ClassForm_RecordTemplate',
  ),
  87 => 
  array (
    'Documentation_ID' => '180',
    'Type' => 'variable',
    'Name' => 'sub',
    'Signature' => 'sub',
    'ShortHelp' => 'Номер(ID) текущего раздела.',
    'FullHelp' => '',
    'Module_ID' => NULL,
    'Parent' => '',
    'Areas' => 'ClassForm TemplateForm',
  ),
  88 => 
  array (
    'Documentation_ID' => '181',
    'Type' => 'variable',
    'Name' => 'sub_level_count',
    'Signature' => 'sub_level_count',
    'ShortHelp' => 'Переменная содержит текущий уровень вложенности навигации.',
    'FullHelp' => '',
    'Module_ID' => NULL,
    'Parent' => '',
    'Areas' => 'TemplateForm',
  ),
  89 => 
  array (
    'Documentation_ID' => '182',
    'Type' => 'variable',
    'Name' => 'subHost',
    'Signature' => 'subHost',
    'ShortHelp' => 'Переменная содержит текущий хост (домен) вида «www.company.ru».',
    'FullHelp' => '',
    'Module_ID' => NULL,
    'Parent' => '',
    'Areas' => 'ClassForm TemplateForm',
  ),
  90 => 
  array (
    'Documentation_ID' => '183',
    'Type' => 'variable',
    'Name' => 'subLink',
    'Signature' => 'subLink',
    'ShortHelp' => 'Переменная содержит URI текущего раздела вида «/about/news/».',
    'FullHelp' => '',
    'Module_ID' => NULL,
    'Parent' => '',
    'Areas' => 'ClassForm TemplateForm',
  ),
  91 => 
  array (
    'Documentation_ID' => '184',
    'Type' => 'variable',
    'Name' => 'totRows',
    'Signature' => 'totRows',
    'ShortHelp' => 'Содержит общее количество объектов данного компонента раздела.',
    'FullHelp' => '',
    'Module_ID' => NULL,
    'Parent' => '',
    'Areas' => 'ClassForm_FormPrefix ClassForm_RecordTemplate ClassForm_FormSuffix ClassForm_RecordTemplateFull',
  ),
);
