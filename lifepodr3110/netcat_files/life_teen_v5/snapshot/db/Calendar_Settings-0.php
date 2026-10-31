<?php
return array (
  0 => 
  array (
    'ID' => '1',
    'ThemeName' => 'Стандартная тема',
    'DefaultTheme' => '1',
    'CalendarPrefix' => '<table cellpadding=\'0\' cellspacing=\'0\' class=\'calend_table\'>',
    'CalendarHeader' => '<tr valign=\'top\'>
   <td class=\'navig_form1\' width=\'10%\' height=\'30\' align=\'right\'>
      &nbsp;
   </td>
   <td align=\'center\' class=\'navig_form1\'>
      %SELECT_MONTH %SELECT_YEAR
   </td>
   <td class=\'navig_form1\' width=\'10%\' height=\'30\' align=\'right\'>
      %IMG_STATUS
   </td>
</tr>
<tr valign=\'top\'>
   <td colspan=\'3\'>
      <table cellpadding=\'0\' cellspacing=\'0\' class=\'navig_form2\' width=\'100%\'>
      <tr>
         <td align=\'left\' class=\'navig_butt\'>
            %IMG_PREV_MONTH
         </td>
         <td align=\'center\' class=\'navig_date\'>
            <span class=\'month_link\'>%MONTH_LINK</span>
            <span class=\'year_link\'>%YEAR_LINK</span>
         </td>
         <td align=\'right\' class=\'navig_butt\'>
            %IMG_NEXT_MONTH
         </td>
      </tr>
      </table>
   </td>
</tr>',
    'CalendarSuffix' => '</table>',
    'dayTemplatePrefix' => '<tr><td colspan=\'3\'><table cellpadding=\'0\' cellspacing=\'0\' width=\'100%\' class=\'days_table\'>',
    'dayTemplateBegin' => '<tr>',
    'dayTemplateHeader' => '<td align=\'center\' class=\'days\'>%NAME_DAY</td>',
    'daySETTemplateHeader' => '<td align=\'center\' class=\'days_sun\'>%NAME_DAY</td>',
    'daySUNTemplateHeader' => '<td align=\'center\' class=\'days_sun_right\'>%NAME_DAY</td>',
    'dayTemplate' => '<td class=\'day\' align=\'center\' onmouseover=\'this.className="mouseover"\' onmouseout=\'this.className="day"\'>%DAY</td>',
    'dayCurTemplate' => '<td class=\'day_curr\' align=\'center\' onmouseover=\'this.className="mouseover_c"\' onmouseout=\'this.className="day_curr"\'>%DAY</td>',
    'daySETTemplate' => '<td class=\'day_sun\' align=\'center\' onmouseover=\'this.className="mouseover"\' onmouseout=\'this.className="day_sun"\'>%DAY</td>',
    'daySUNTemplate' => '<td class=\'day_sun_right\' align=\'center\' onmouseover=\'this.className="mouseover_s"\' onmouseout=\'this.className="day_sun_right"\'>%DAY</td>',
    'nodayTemplate' => '<td class=\'no_day\'>&nbsp;</td>',
    'nodaySETTemplate' => '<td class=\'no_day_sun\'>&nbsp;</td>',
    'nodaySUNTemplate' => '<td class=\'no_day_sun_right\'>&nbsp;</td>',
    'dayTemplateEnd' => '</tr>',
    'dayTemplateSuffix' => '</table></td></tr>',
    'DaysName' => 'ПН,ВТ,СР,ЧТ,ПТ,СБ,ВС',
    'CalendarCSS' => '.calend_table {
  width:245;
  height:229;
  border:2px solid #AEA69E;
  background:#ECE9E3;
  font-family:Verdana;
  font-size:10px;
  color:#636363;
}

.calend_table td {
  vertical-align:middle !important;
  color:#333333;
}

.days_table {
  font-family:Verdana;
  font-size:10px;
}
.days, .days_sun, .days_sun_right {
  background:#AEA69E;
  color:#FFFFFF !important;
  width:35px;
  height:25px;
}
.day, .no_day {
  background: #ECE9E3;
  margin-top:10px;
  padding:0;
}
.day_curr {
  background: #FFFFFF;
  margin-top:10px;
  padding:0;
  border-right:1px solid #FFFFFF
}
.day_sun, .no_day_sun, .day_sun_right, .no_day_sun_right{
  background:#E5E0DB;
}
.days, .days_sun, .day, .no_day, .day_curr, .day_sun, .no_day_sun{
  border-top: 1px solid #FFFFFF;
  border-right: 1px solid #FFFFFF;
  height:25px;
}
.day_sun_right, .no_day_sun_right, .days_sun_right {
  border-top:1px solid #FFFFFF;
  border-right:0;
}
.navig_form1 {
  padding:3px;
  background:#DAD6CD;
}
.navig_form2 td {
  background:#E5E0DB;
  color:#4E4A46;
  border-top:1px solid #FFFFFF;
  padding:3px;
  font-weight:bold;
  font-size:14px;
}
td.navig_butt {
  padding:0 7px;
  cursor:pointer;
}

.mouseover {
  background:#AEA69E;
  color:#FFFFFF;
  border-top: 1px solid #FFFFFF;
}

.mouseover_s {
  background:#AEA69E;
  color:#FFFFFF;
  border-top: 1px solid #FFFFFF;
  border-right:none
}

.mouseover_c {
  background:#AEA69E;
  color:#FFFFFF;
  border-top: 1px solid #FFFFFF;
  border-right: 1px solid #FFFFFF
}

.month_link a{
  text-decoration:underline;
  color:#333333;
  cursor:pointer;
}

.year_link a{
  text-decoration:underline;
  color:#333333;
  cursor:pointer;
}
.calend_table a, .calend_table a:hover {
  color:#333333;
  text-decoration:underline;
}',
    'StatusImage' => 'loading-std.gif',
    'PrevImage' => 'left-arrow-std.gif',
    'NextImage' => 'right-arrow-std.gif',
    'parallax_year_forward' => '0',
    'parallax_year_backward' => '0',
    'CloseImage' => 'close.gif',
  ),
  1 => 
  array (
    'ID' => '2',
    'ThemeName' => 'Морской бриз',
    'DefaultTheme' => '0',
    'CalendarPrefix' => '<table width=\'250px\' height=\'196\' cellspacing=\'0\' cellpadding=\'0\' class=\'calend_table\'>',
    'CalendarHeader' => '<tr valign=\'top\'>
   <td class=\'navig_form1\' width=\'20\' height=\'28\'>
   &nbsp;
   </td>
   <td class=\'navig_form1\'>
      %SELECT_MONTH &nbsp; %SELECT_YEAR
   </td>
   <td class=\'navig_form1\' width=\'20\' height=\'28\'>
      %IMG_STATUS
   </td>
</tr>
<tr valign=\'top\' class=\'navig_form2\'>
         <td class=\'navig_butt\'>
            %IMG_PREV_MONTH
         </td>
         <td class=\'navig_date\'>
            <span class=\'month_link\' title=\'вывести за месяц\'>%MONTH_LINK</span>
	    &nbsp;
            <span class=\'year_link\' title=\'вывести за год\'>%YEAR_LINK</span>
         </td>
         <td class=\'navig_butt\'>
            %IMG_NEXT_MONTH
         </td>
</tr>

',
    'CalendarSuffix' => '</table>',
    'dayTemplatePrefix' => '<tr><td colspan=\'3\'><table cellspacing=\'1\' cellpadding=\'0\' border=\'0\' class=\'days_table\'>',
    'dayTemplateBegin' => '<tr>',
    'dayTemplateHeader' => '<td align=\'center\' class=\'days\'>%NAME_DAY</td>',
    'daySETTemplateHeader' => '<td align=\'center\' class=\'days_sun\'>%NAME_DAY</td>',
    'daySUNTemplateHeader' => '<td align=\'center\' class=\'days_sun_right\'>%NAME_DAY</td>',
    'dayTemplate' => '<td class=\'day\' align=\'center\' onmouseover=\'this.className="mouseover"\' onmouseout=\'this.className="day"\'>%DAY</td>',
    'dayCurTemplate' => '<td class=\'day_curr\' align=\'center\' onmouseover=\'this.className="mouseover"\' onmouseout=\'this.className="day_curr"\'>%DAY</td>',
    'daySETTemplate' => '<td class=\'day_sun\' align=\'center\' onmouseover=\'this.className="mouseover"\' onmouseout=\'this.className="day_sun"\'>%DAY</td>',
    'daySUNTemplate' => '<td class=\'day_sun_right\' align=\'center\' onmouseover=\'this.className="mouseover"\' onmouseout=\'this.className="day_sun_right"\'>%DAY</td>',
    'nodayTemplate' => '<td class=\'no_day\'>&nbsp;</td>',
    'nodaySETTemplate' => '<td class=\'no_day_sun\'>&nbsp;</td>',
    'nodaySUNTemplate' => '<td class=\'no_day_sun_right\'>&nbsp;</td>',
    'dayTemplateEnd' => '</tr>',
    'dayTemplateSuffix' => '</table></td></tr>',
    'DaysName' => 'пон,втр,срд,чет,пят,суб,вск',
    'CalendarCSS' => '.calend_table {
  vertical-align: middle;
  border: 2px solid #A7BAC3;
  background: #E0EBF1;
  color: #636363;
}
.calend_table td {
  vertical-align:middle !important;
  font-size: 75%;
  font-family: Verdana;
}
.calend_table .days_table {
  width:100%;
  background:#FFF;
}
.calend_table .days, .calend_table .days_sun, .calend_table .days_sun_right {
  background: #A7BAC3;
  color: #FFFFFF;
  text-align:center;
  vertical-align:middle;
  width:14%;
  height: 22px;
}
.calend_table .day, .calend_table .no_day {
  background: #EDF4F7;
  color:#A7BAC3;
  text-align:center;
  vertical-align:middle;
  height: 24px;
  margin-top: 10px;
  padding: 0;
}

.calend_table .day_curr {
  background: #FFFFFF;
  color:#446B7E;
  text-align:center;
  vertical-align:middle;
  height: 22px;
  padding: 0;
  border: 1px solid #C2D0D7;
}

.calend_table .day_sun, .calend_table .no_day_sun, .calend_table .day_sun_right, .calend_table .no_day_sun_right{
  background: #E0EBF1;
  color:#A7BAC3;
  text-align:center;
  vertical-align:middle;
}

.calend_table .navig_form1 {
  padding: 3px;
  background: #E0EBF1;
  text-align:center;
  vertical-align:middle;
}
.calend_table .navig_form2 td {
  background: #EDF4F7;
  color:#446B7E;
  font-size:90%;
  font-weight: bold;
  text-align:center;
  vertical-align:middle;
  border-top: 1px solid #A7BAC3;
  padding: 3px;
}
.calend_table .navig_butt {
  padding: 0;
  height:26px;
}
.calend_table .navig_butt img {
  cursor: pointer;
}

.calend_table .mouseover {
  background: #A7BAC3;
  color: #FFF;
  text-align:center;
  vertical-align:middle;
}

.calend_table .month_link {
  text-decoration:underline;
  color: #446B7E;
  cursor: pointer;
}

.calend_table .year_link {
  text-decoration:underline;
  color: #446B7E;
  cursor: pointer;
}

.calend_table a {
  color: #446B7E;
  text-decoration: underline;
}
.calend_table .mouseover a {
  color: #FFF;
  cursor: pointer;
  text-decoration: underline;
}
',
    'StatusImage' => 'loading.gif',
    'PrevImage' => 'left-arrow.gif',
    'NextImage' => 'right-arrow.gif',
    'parallax_year_forward' => '0',
    'parallax_year_backward' => '0',
    'CloseImage' => 'close.gif',
  ),
  2 => 
  array (
    'ID' => '3',
    'ThemeName' => 'Минимализм',
    'DefaultTheme' => '0',
    'CalendarPrefix' => '<table width=\'245\' cellpadding=\'0\' cellspacing=\'0\' class=\'calend_table\'>',
    'CalendarHeader' => '<tr valign=\'top\' class=\'navig_form\'>
<td align=\'left\' class=\'navig_butt\'>
%IMG_PREV_MONTH
</td>
<td align=\'center\' class=\'navig_date\'>
<span class=\'month_link\'>%MONTH_LINK</span>
<span class=\'year_link\'>%YEAR_LINK</span>
</td>
<td align=\'right\' class=\'navig_butt\'>
%IMG_NEXT_MONTH
</td>
</tr>',
    'CalendarSuffix' => '</table>',
    'dayTemplatePrefix' => '<tr><td colspan=\'3\'><table cellpadding=\'0\' cellspacing=\'0\' width=\'100%\' class=\'days_table\'>',
    'dayTemplateBegin' => '<tr>',
    'dayTemplateHeader' => '<td align=\'center\' class=\'days\'>%NAME_DAY</td>',
    'daySETTemplateHeader' => '<td align=\'center\' class=\'days_sun\'>%NAME_DAY</td>',
    'daySUNTemplateHeader' => '<td align=\'center\' class=\'days_sun_right\'>%NAME_DAY</td>',
    'dayTemplate' => '<td class=\'day\' align=\'center\' onmouseover=\'this.className="mouseover"\' onmouseout=\'this.className="day"\'>%DAY</td>',
    'dayCurTemplate' => '<td class=\'day_curr\' align=\'center\'>%DAY</td>',
    'daySETTemplate' => '<td class=\'day_sun\' align=\'center\' onmouseover=\'this.className="mouseover_weekend"\' onmouseout=\'this.className="day_sun"\'>%DAY</td>',
    'daySUNTemplate' => '<td class=\'day_sun_right\' align=\'center\' onmouseover=\'this.className="mouseover_weekend_s"\' onmouseout=\'this.className="day_sun_right"\'>%DAY</td>',
    'nodayTemplate' => '<td class=\'no_day\'>&nbsp;</td>',
    'nodaySETTemplate' => '<td class=\'no_day_sun\'>&nbsp;</td>',
    'nodaySUNTemplate' => '<td class=\'no_day_sun_right\'>&nbsp;</td>',
    'dayTemplateEnd' => '</tr>',
    'dayTemplateSuffix' => '</table></td></tr>',
    'DaysName' => 'пон,втр,срд,чет,пят,суб,вск',
    'CalendarCSS' => '.calend_table {
  border:1px solid #999999;
  background:#FFFFFF;
  font-family:Verdana;
  font-size:10px;
  color:#333333;
}

.calend_table td {
  vertical-align:middle !important;
  color:#333333;
}

.days_table {
  font-family:Verdana;
  font-size:10px;
}
.days, .days_sun, .days_sun_right {
  background:#999999;
  color:#FFFFFF !important;
  width:35px;
  height:25px;
}
.day, .no_day {
  background: #F9F9F9;
  margin-top:10px;
  padding:0;
}
.day_curr {
  background: #999999;
  color:#FFFFFF !important;
  font-weight:bold;
  margin-top:10px;
  padding:0;
}
.day_sun, .no_day_sun, .day_sun_right, .no_day_sun_right{
  background:#EEEEEE;
}

.no_day, .no_day_sun, .no_day_sun_right {
  color:#FFFFFF !important;
  text-align:center;
}

.days, .days_sun, .day, .no_day, .day_curr, .day_sun, .no_day_sun{
  border-top: 1px solid #FFFFFF;
  border-right: 1px solid #FFFFFF;
  height:25px;
}
.day_sun_right, .no_day_sun_right, .days_sun_right {
  border-top:1px solid #FFFFFF;
  border-right:0;
}

.navig_form td {
  background:#F9F9F9;
  color:#333333;
  padding:3px;
  font-weight:bold;
  font-size:12px;
  height:25px;
}
td.navig_butt {
  padding:0 7px;
  cursor:pointer;
}

.mouseover {
  background:#EEEEEE;
  color:#333333;
  border-top: 1px solid #FFFFFF;
  border-right: 1px solid #FFFFFF;
}

.mouseover_weekend, .mouseover_weekend_s {
  background:#F9F9F9;
  color:#333333;
  border-top: 1px solid #FFFFFF;
  border-right: 1px solid #FFFFFF;
}

.mouseover_weekend_s {
  border-right:none;
}

.month_link {
  text-decoration:underline;
  color:#333333;
  cursor:pointer;
  padding:0 5px;
}

.year_link {
  text-decoration:underline;
  color:#333333;
  cursor:pointer;
  padding:0 5px;
}
.calend_table a, .calend_table a:hover { color:#333333; text-decoration:underline; }',
    'StatusImage' => 'loading-std.gif',
    'PrevImage' => 'left-arrow-std.gif',
    'NextImage' => 'right-arrow-std.gif',
    'parallax_year_forward' => '0',
    'parallax_year_backward' => '0',
    'CloseImage' => 'close.gif',
  ),
);
