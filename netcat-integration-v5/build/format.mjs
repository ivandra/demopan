import fs from 'node:fs';
import path from 'node:path';
import {createRequire} from 'node:module';
const require=createRequire(import.meta.url);
const modules=process.env.NETCAT_FORMAT_MODULES || '/workspace/netcat-study/formatting/node_modules';
const prettier=await import(path.join(modules,'prettier/index.mjs'));
const phpPlugin=await import(path.join(modules,'@prettier/plugin-php/src/index.mjs'));
const beautify=require(path.join(modules,'js-beautify'));
const root=path.resolve(process.argv[2]);
if(process.argv[3]==='installer') {
    const file=path.join(root,'files/zhp-install.php');
    const formatted=await prettier.format(fs.readFileSync(file,'utf8'),{parser:'php',plugins:[phpPlugin],tabWidth:4,printWidth:100,phpVersion:'7.4'});
    fs.writeFileSync(file,formatted);
    fs.writeFileSync(path.join(root,'installer/zhp-install.php'),formatted);
    process.exit(0);
}

async function format(text) {
    if (text.startsWith('<?php') && !text.includes('?>')) {
        return prettier.format(text,{parser:'php',plugins:[phpPlugin],tabWidth:4,printWidth:100});
    }
    const php=[];
    const source=text;
    let position=0,shielded='';
    while(true){
        const offset=source.indexOf('<?',position);
        if(offset<0){shielded+=source.slice(position);break;}
        shielded+=source.slice(position,offset);
        let end=offset+2,quote=null;
        while(end<source.length){
            const char=source[end];
            if(quote){if(char==='\\'){end+=2;continue;}if(char===quote)quote=null;}
            else if(["'",'"','`'].includes(char))quote=char;
            else if(source.slice(end,end+2)==='?>'){end+=2;break;}
            end++;
        }
        const code=source.slice(offset,end);
        const index=php.length;php.push(code);
        const before=shielded;
        shielded+=before.lastIndexOf('<')>before.lastIndexOf('>') || before.lastIndexOf('<script')>before.lastIndexOf('</script')
            ? `PHPTOKEN${index}END` : `\n<php-block-${index} />\n`;
        position=end;
    }
    text=shielded;
    text=text.replace(/<!--[\s\S]*?-->/g,'');
    text=beautify.html(text,{indent_size:4,preserve_newlines:false,extra_liners:[],wrap_line_length:0,inline:[],inline_custom_elements:false,
        unformatted:['pre','textarea'],content_unformatted:['pre','textarea']});
    for(let index=0;index<php.length;index++) {
        let code=php[index];
        if(code.startsWith('<?php') && code.endsWith('?>')) {
            const options={parser:'php',plugins:[phpPlugin],tabWidth:4,printWidth:100};
            try {code=(await prettier.format(code,options)).trimEnd();} catch {
                for(let closings=1;closings<=4;closings++) {
                    try {
                        const completed=await prettier.format(code.slice(0,-2)+'\n/* NC_FORMAT_END */\n'+'}\n'.repeat(closings)+'?>',options);
                        code=completed.split('/* NC_FORMAT_END */')[0].trimEnd()+'\n?>';
                        break;
                    } catch {}
                }
            }
        }
        text=text.replace(`PHPTOKEN${index}END`,()=>code);
        text=text.replace(new RegExp(`^([ \\t]*)<php-block-${index}\\s*/>`, 'm'),(match,indent)=>
            code.split('\n').map(line=>indent+line).join('\n'));
    }
    return text.replace(/[ \t]+$/gm,'').trimEnd()+'\n';
}

for(const name of fs.readdirSync(path.join(root,'components'))) {
    const folder=path.join(root,'components',name);
    const names=['FormPrefix','RecordTemplate','FormSuffix'];
    const parts=names.map(name=>fs.existsSync(path.join(folder,name+'.html'))?fs.readFileSync(path.join(folder,name+'.html'),'utf8'):'');
    const combined=await format(parts.join('\n<nc-part-boundary />\n'));
    const formatted=combined.split(/^[ \t]*<nc-part-boundary\s*\/>[ \t]*$/m);
    if(formatted.length!==3)throw new Error('Template boundaries: '+folder);
    for(let index=0;index<3;index++)fs.writeFileSync(path.join(folder,names[index]+'.html'),parts[index].trim()?formatted[index].replace(/^\n+/,'').trimEnd()+'\n':'');
    for(const file of fs.readdirSync(folder)) {
        if(!file.endsWith('.html')||names.some(name=>file===name+'.html'))continue;
        fs.writeFileSync(path.join(folder,file),await format(fs.readFileSync(path.join(folder,file),'utf8')));
    }
}
function walk(folder) {
    return fs.readdirSync(folder,{withFileTypes:true}).flatMap(item=>item.isDirectory()?walk(path.join(folder,item.name)):[path.join(folder,item.name)]);
}
const design=path.join(root,'files/netcat_template/template/life_teen');
for(const file of walk(design)) {
    if(file.includes('/.install/'))continue;
    if(file.endsWith('.html')||file.endsWith('.php.txt')) {
        const text=fs.readFileSync(file,'utf8');
        fs.writeFileSync(file,text.trim()?await format(text):'');
    }
}
fs.cpSync(path.join(root,'components'),path.join(design,'.install/components'),{recursive:true});
