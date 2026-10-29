from bs4 import BeautifulSoup

popup=BeautifulSoup((B/'reference-layout/popup.html').read_text(),'html.parser').select_one('#popup')
popup.name='form';popup['method']='post';popup['action']='/netcat/add.php';popup['data-zhp-form']='consult'
fields=popup.select('input[type=text]')
for node,name in zip(fields,['name','phone']):
 node['name']='f_'+name;node['required']=''
 if name=='phone':node['type']='tel'
checkbox=popup.select_one('input[type=checkbox]');checkbox['name']='f_consent';checkbox['value']='1';checkbox['required']='';checkbox['id']='consult-policy'
popup.select_one('label')['for']='consult-policy';popup.select_one('label a')['href']='/policy/'
for image in popup.select('img'):image['src']=ASSET+image['src'];image['alt']=''
hidden='''
<input type="hidden" name="catalogue" value="<?= (int)$catalogue ?>">
<input type="hidden" name="sub" value="<?= (int)$current_catalogue['zakaz_sub'] ?>">
<input type="hidden" name="cc" value="<?= (int)$current_catalogue['zakaz_cc'] ?>">
<input type="hidden" name="posting" value="1">
<input type="hidden" name="isNaked" value="1">
<input type="hidden" name="f_form_type" value="consult">
<input type="hidden" name="f_page_url" value="<?= htmlspecialchars($_SERVER['REQUEST_URI'],ENT_QUOTES,'UTF-8') ?>">
<input type="hidden" name="f_request_key" value="<?= bin2hex(random_bytes(16)) ?>">
<input type="hidden" name="life_nonce" value="<?= htmlspecialchars($_SESSION['life_form_nonce'],ENT_QUOTES,'UTF-8') ?>">
<input type="hidden" name="life_website" value="">
<?= $nc_core->token->get_input() ?>
<output role="status" data-form-status></output>
'''
markup=str(popup).replace('</form>',hidden+'</form>')
folder=C/'life_popup';folder.mkdir(exist_ok=True)
for name,value in [('FormPrefix',markup),('RecordTemplate',''),('FormSuffix','')]: (folder/(name+'.html')).write_text(value)
m['classes']['life_popup']={'name':'Форма консультации','fields':[],'title_field':'name','parent':None,'settings':{},'static':True}
if not any(block['class']=='life_popup' for block in pages['/']['blocks']):
 pages['/']['blocks'].append({'class':'life_popup','name':'Форма консультации','records':[],'settings':{}})
footer=(D/'Footer.html').read_text()
footer=footer.replace('<script',"<?php if ((int)$sub !== (int)$current_catalogue['Title_Sub_ID']) { echo project_shared_block('life_popup'); } ?>\n<script",1)
(D/'Footer.html').write_text(footer)
condition=C/'life_leads/AddCond.html'
condition.write_text(condition.read_text().replace('["contact", "subscribe"]','["contact", "subscribe", "consult"]'))
