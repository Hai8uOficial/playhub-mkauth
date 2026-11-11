(function(){'use strict';const cfg=window.PlayHubConfig,$=s=>document.querySelector(s),$$=s=>Array.from(document.querySelectorAll(s));
const toast=(m,e=false)=>{const t=$('#phToast');if(!t)return;t.textContent=m;t.className='ph-toast show'+(e?' error':'');setTimeout(()=>t.className='ph-toast',4200)};
async function request(action,data=null,params={}){const query=new URLSearchParams(Object.assign({action},params));const opt=data?{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(Object.assign({csrf:cfg.csrf},data))}:{};const r=await fetch(cfg.api+'?'+query.toString(),opt);const j=await r.json().catch(()=>({ok:false,message:'Resposta inválida do servidor.'}));if(!j.ok)throw new Error(j.message||'Não foi possível concluir.');return j;}
async function requestForm(action,data){data.set('csrf',cfg.csrf);const r=await fetch(cfg.api+'?'+new URLSearchParams({action}),{method:'POST',body:data});const j=await r.json().catch(()=>({ok:false,message:'Resposta inválida do servidor.'}));if(!j.ok)throw new Error(j.message||'Não foi possível concluir.');return j;}
function esc(v){const d=document.createElement('div');d.textContent=v==null?'':String(v);return d.innerHTML}
let phConfirmResolver=null;
function finishConfirmation(answer){const modal=$('#phConfirmModal');if(modal)modal.hidden=true;const resolver=phConfirmResolver;phConfirmResolver=null;const anotherModal=Array.from(document.querySelectorAll('.ph-modal:not(#phConfirmModal)')).some(item=>!item.hidden);if(!anotherModal)document.body.classList.remove('ph-modal-open');if(resolver)resolver(answer)}
function askConfirmation(message,title='Confirmar ação'){if(phConfirmResolver)finishConfirmation(false);const modal=$('#phConfirmModal');if(!modal)return Promise.resolve(window.confirm(message));$('#phConfirmTitle').textContent=title;$('#phConfirmMessage').textContent=message;modal.hidden=false;document.body.classList.add('ph-modal-open');return new Promise(resolve=>{phConfirmResolver=resolve;setTimeout(()=>$('#phConfirmYes')?.focus(),0)})}
$$('[data-confirm-no]').forEach(button=>button.addEventListener('click',()=>finishConfirmation(false)));
$('#phConfirmYes')?.addEventListener('click',()=>finishConfirmation(true));
document.addEventListener('keydown',event=>{if(event.key==='Escape'&&!$('#phConfirmModal')?.hidden){event.preventDefault();event.stopImmediatePropagation();finishConfirmation(false)}});
function applyHelpTitles(root=document){root.querySelectorAll('[aria-label]:not([title])').forEach(element=>element.title=element.getAttribute('aria-label')||'');root.querySelectorAll('button:not([title]),a.ph-btn:not([title])').forEach(element=>{const label=element.textContent.trim();if(label)element.title=label});root.querySelectorAll('input:not([title])').forEach(element=>{const label=element.getAttribute('aria-label')||element.getAttribute('placeholder');if(label)element.title=label})}
function customerUrl(uuid,login){const base=String(cfg.customerDetailBase||'../../cliente_det.hhvm');return uuid?base+'?uuid='+encodeURIComponent(uuid):base+'?login='+encodeURIComponent(login||'')}
const side=$('#phSide'),collapse=$('#phCollapse'),shell=$('.ph-shell');function applySide(collapsed){if(!shell)return;shell.classList.toggle('collapsed',collapsed);collapse?.setAttribute('aria-expanded',collapsed?'false':'true');collapse?.setAttribute('aria-label',collapsed?'Expandir menu':'Recolher menu');const icon=collapse?.querySelector('i');if(icon)icon.className=collapsed?'fa fa-chevron-right':'fa fa-chevron-left'}applySide(localStorage.phSide==='1');collapse?.addEventListener('click',()=>{const collapsed=!shell.classList.contains('collapsed');applySide(collapsed);localStorage.phSide=collapsed?'1':'0'});
async function summary(){try{const j=await request('summary'),d=j.data;$('#phIntegration').textContent=d.configured?'Integração configurada':'Aguardando credenciais';const c=$$('#summaryCards article strong');[d.linked,d.active,d.suspended,d.errors].forEach((v,i)=>{if(c[i])c[i].textContent=v})}catch(e){toast(e.message,true)}}summary();
$$('[data-sync-all]').forEach(b=>b.addEventListener('click',async()=>{b.disabled=true;try{const j=await request('reconcile',{});toast(j.message);summary()}catch(e){toast(e.message,true)}finally{b.disabled=false}}));
async function loadClients(){const tbody=$('#clientRows');if(!tbody)return;tbody.innerHTML='<tr><td colspan="6">Carregando...</td></tr>';try{const q=$('#clientSearch').value,j=await request('clients',null,q?{search:q}:{});tbody.innerHTML=j.data.length?j.data.map(r=>{const p=r.playhub||{},label={active:'Ativo',suspended:'Suspenso',cancelled:'Cancelado',error:'Erro',pending:'Pendente'}[p.status]||'Não vinculado';return `<tr><td><a class="ph-customer-link" href="${esc(customerUrl(r.uuid,r.login))}" target="_blank" rel="noopener" title="Abre o cadastro deste cliente no MK-AUTH."><b>${esc(r.name)}</b><small>${esc(r.cpf||r.cnpj)}</small></a></td><td>${esc(r.login)}</td><td><span class="ph-status ${esc(r.desired_status)}">${esc(r.desired_status)}</span></td><td><span class="ph-status ${esc(p.status||'pending')}">${label}</span></td><td>${esc(p.last_sync_at||'—')}</td><td><div class="ph-row-actions"><button class="ph-btn sync-one" data-login="${esc(r.login)}" data-name="${esc(r.name||r.login)}" title="Cadastra ou atualiza este cliente na PlayHub e abre a janela para associar produtos.">Ativar</button></div></td></tr>`}).join(''):'<tr><td colspan="6">Nenhum cliente encontrado.</td></tr>';$$('.sync-one').forEach(b=>b.onclick=async()=>{if(!await askConfirmation(`Deseja ativar o cliente ${b.dataset.name} na PlayHub? Depois da ativação será aberta a janela para associar os produtos.`,`Ativar ${b.dataset.name}`))return;b.disabled=true;try{const x=await request('sync_client',{login:b.dataset.login});toast(x.message);loadClients();if(x.data?.status==='active')openProductModal(b.dataset.login)}catch(e){toast(e.message,true)}finally{b.disabled=false}});applyHelpTitles(tbody)}catch(e){tbody.innerHTML='<tr><td colspan="6">'+esc(e.message)+'</td></tr>'}}$('#searchClients')?.addEventListener('click',loadClients);$('#clientSearch')?.addEventListener('keydown',e=>{if(e.key==='Enter')loadClients()});
async function loadRegisteredSubscriptions(){
 const tbody=$('#subscriptionRows');if(!tbody)return;
 tbody.innerHTML='<tr><td colspan="7">Carregando...</td></tr>';
 try{
  const search=$('#subscriptionSearch')?.value.trim()||'',params={mode:'active'};if(search)params.search=search;const j=await request('registered_subscriptions',null,params);
  const clients=[],$byLogin=new Map();
  j.data.forEach(row=>{const key=String(row.login||'');if(!$byLogin.has(key)){$byLogin.set(key,{client:row,subscriptions:[]});clients.push($byLogin.get(key))}$byLogin.get(key).subscriptions.push(row)});
  tbody.innerHTML=clients.length?clients.map(group=>{
   const rows=group.subscriptions,rowspan=rows.length,client=group.client;
   return rows.map((row,index)=>{
    const hasProduct=Boolean(row.product_code),active=row.subscription_status==='active',blocked=client.client_status==='suspended',disabled=client.client_status==='cancelled',clientError=client.client_status==='error';
    const product=hasProduct?`<b>${esc(row.product_code)}</b><small>${esc(row.product_name||row.product_code)}</small>`:'<span class="muted">—</span>';
    const status=blocked?'<span class="ph-status suspended">Bloqueado</span>':disabled?'<span class="ph-status cancelled">Desativado</span>':clientError?'<span class="ph-status error">Erro</span>':active?'<span class="ph-status active">Ativo</span>':'<span class="ph-status pending">Sem produto</span>';
    const customerCells=index===0?`<td class="ph-client-cell" rowspan="${rowspan}"><a class="ph-customer-link${blocked?' ph-customer-blocked':''}" href="${esc(customerUrl(client.uuid,client.login))}" target="_blank" rel="noopener" title="Abre o cadastro deste cliente no MK-AUTH.">${esc(client.name||client.login)}</a></td><td class="ph-client-cell" rowspan="${rowspan}">${esc(client.login)}</td><td class="ph-client-cell" rowspan="${rowspan}">${esc(client.email||'—')}</td><td class="ph-client-cell" rowspan="${rowspan}">${esc(client.mobile||'—')}</td>`:'';
    const canAssociate=client.client_status==='active',associateTitle=blocked?'Cliente bloqueado':disabled?'Cliente desativado':clientError?'Cliente com erro de sincronização':'Associar produto';
    const associate=index===0?`<button class="ph-icon-btn ph-icon-associate associate-product" data-login="${esc(client.login)}" title="${associateTitle}" aria-label="${associateTitle}"${canAssociate?'':' disabled'}><i class="fa fa-link" aria-hidden="true"></i></button>`:'';
    const deactivate=hasProduct&&active?`<button class="ph-icon-btn ph-icon-deactivate subscription-action" data-action="deactivate_subscription" data-login="${esc(client.login)}" data-name="${esc(client.name||client.login)}" data-code="${esc(row.product_code)}" title="Desassocia o produto ${esc(row.product_code)} do cliente ${esc(client.name||client.login)} na PlayHub." aria-label="Desassociar ${esc(row.product_code)} de ${esc(client.name||client.login)}"><i class="fa fa-unlink" aria-hidden="true"></i></button>`:'';
    return `<tr class="${index?'ph-subscription-continuation':''}">${customerCells}<td>${product}</td><td>${status}</td><td><div class="ph-row-actions">${associate}${deactivate}</div></td></tr>`;
   }).join('');
  }).join(''):'<tr><td colspan="7">Nenhum cliente ou assinatura encontrado.</td></tr>';
  $$('.associate-product').forEach(button=>button.onclick=()=>openProductModal(button.dataset.login));
  $$('.subscription-action').forEach(button=>button.onclick=()=>runSubscriptionAction(button));
  applyHelpTitles(tbody);
 }catch(e){tbody.innerHTML='<tr><td colspan="7">'+esc(e.message)+'</td></tr>'}
}
async function runSubscriptionAction(button){
 const action=button.dataset.action,login=button.dataset.login,code=button.dataset.code;
 const messages={deactivate_subscription:`Deseja desassociar o cliente ${button.dataset.name||login} do produto ${code}?`};
 if(!await askConfirmation(messages[action]||'Deseja executar esta ação?','Desassociar produto'))return;
 button.disabled=true;
 try{const result=await request(action,{login,code});toast(result.message);await loadRegisteredSubscriptions();if(selectedProductLogin===login)await loadProducts(login);summary()}
 catch(e){toast(e.message,true);button.disabled=false}
}
$('#searchSubscriptions')?.addEventListener('click',loadRegisteredSubscriptions);
$('#subscriptionSearch')?.addEventListener('keydown',event=>{if(event.key==='Enter'){event.preventDefault();loadRegisteredSubscriptions()}});
loadRegisteredSubscriptions();
async function loadInactiveClients(){
 const tbody=$('#inactiveRows');if(!tbody)return;tbody.innerHTML='<tr><td colspan="6">Carregando...</td></tr>';
 try{
  const search=$('#inactiveSearch')?.value.trim()||'',params={mode:'inactive'};if(search)params.search=search;const result=await request('registered_subscriptions',null,params),clients=[],seen=new Set();
  (result.data||[]).forEach(client=>{const login=String(client.login||'');if(login!==''&&!seen.has(login)){seen.add(login);clients.push(client)}});
  tbody.innerHTML=clients.length?clients.map(client=>`<tr><td><a class="ph-customer-link ph-customer-blocked" href="${esc(customerUrl(client.uuid,client.login))}" target="_blank" rel="noopener" title="Abre o cadastro desativado no MK-AUTH.">${esc(client.name||client.login)}</a></td><td>${esc(client.login)}</td><td>${esc(client.email||'—')}</td><td>${esc(client.mobile||'—')}</td><td>${esc(client.last_sync_at||'—')}</td><td><span class="ph-status cancelled">Desativado</span></td></tr>`).join(''):'<tr><td colspan="6">Nenhum cliente desativado encontrado.</td></tr>';applyHelpTitles(tbody);
 }catch(e){tbody.innerHTML='<tr><td colspan="6">'+esc(e.message)+'</td></tr>'}
}
$('#searchInactive')?.addEventListener('click',loadInactiveClients);
$('#inactiveSearch')?.addEventListener('keydown',event=>{if(event.key==='Enter'){event.preventDefault();loadInactiveClients()}});
loadInactiveClients();
let productData={},activeSubscriptions=[],selectedProductLogin='';
const hasSubscription=code=>activeSubscriptions.some(item=>item.product_code===code);
const availableApps={
 rqc:[
  {name:'Looke',mark:'Looke',brand:'looke',access:'3 acessos simultâneos'},
  {name:'Kiddle Pass',mark:'Kiddle',brand:'kiddle',access:'3 acessos simultâneos'},
  {name:'+Nutri',mark:'+Nutri',brand:'nutri'},
  {name:'ExitLag',mark:'EXITLAG',brand:'exitlag'},
  {name:'PlayKids Learning',mark:'PlayKids',brand:'playkids',access:'5 acessos simultâneos'},
  {name:'Hub Vantagens',mark:'HUB',brand:'hub'},
  {name:'Ubook Plus',mark:'ubook+',brand:'ubook'},
  {name:'Estuda+',mark:'ESTUDA+',brand:'estuda'},
  {name:'Pequenos Leitores',mark:'Pequenos Leitores',brand:'leitores'},
  {name:'Fluid',mark:'fluid',brand:'fluid'},
  {name:'Social Comics',mark:'Social Comics',brand:'social',access:'3 acessos simultâneos'},
  {name:'Revistaria',mark:'revistaria',brand:'revistaria'},
  {name:'Playlist',mark:'Playlist',brand:'playlist'},
  {name:'Kaspersky',mark:'K',brand:'kaspersky',access:'1 licença'}
 ],
 rqd:[
  {name:'Universal+',mark:'UNIVERSAL+',brand:'universal',access:'2 acessos simultâneos'},
  {name:'SKY+',mark:'SKY+',brand:'sky',access:'1 acesso Light ou 3 acessos Full'},
  {name:'Kiddle Pass',mark:'Kiddle',brand:'kiddle',access:'3 acessos simultâneos'},
  {name:'Deezer',mark:'DEEZER',brand:'deezer',access:'1 acesso'},
  {name:'CurtaOn',mark:'CurtaOn',brand:'curta',access:'2 acessos simultâneos'},
  {name:'HotGo',mark:'HOTGO',brand:'hotgo',access:'2 acessos simultâneos'},
  {name:'Docway',mark:'Docway',brand:'docway',access:'uso exclusivo do titular'},
  {name:'O Jornalista',mark:'O Jornalista',brand:'jornalista'},
  {name:'Kaspersky',mark:'K',brand:'kaspersky',access:'3 licenças'}
 ],
 rqn:[
  {name:'Apple TV+',mark:'tv+',brand:'apple',access:'6 acessos simultâneos'},
  {name:'Globoplay',mark:'globoplay',brand:'globoplay',access:'3 acessos simultâneos no TOP'},
  {name:'Disney+',mark:'Disney+',brand:'disney',access:'2 acessos simultâneos'},
  {name:'HBO Max',mark:'HBO max',brand:'hbo',access:'2 acessos simultâneos'},
  {name:'Cindie',mark:'CINDIE',brand:'cindie',access:'3 acessos simultâneos'},
  {name:'Leitura360',mark:'Leitura 360',brand:'leitura'}
 ],
 rqp:[
  {name:'Disney+',mark:'Disney+',brand:'disney',access:'2 acessos simultâneos'},
  {name:'Globoplay',mark:'globoplay',brand:'globoplay',access:'5 acessos simultâneos no Premium'},
  {name:'HBO Max',mark:'HBO max',brand:'hbo',access:'2 acessos simultâneos'},
  {name:'Universal+',mark:'UNIVERSAL+',brand:'universal',access:'2 acessos simultâneos'},
  {name:'SmartContent',mark:'SmartContent',brand:'smartcontent'},
  {name:'ZenWellness',mark:'zen',brand:'zen'},
  {name:'Queima Diária',mark:'QUEIMA DIÁRIA',brand:'queima'},
  {name:'Kaspersky',mark:'K',brand:'kaspersky',access:'5 licenças'}
 ],
 sky_base:[
  {name:'DTQ — SKY+ Full SVA',mark:'SKY+ Full',brand:'dtq',access:'Plano principal SKY+ Full'},
  {name:'DTS — SKY+ Light com Globo e Paramount+',mark:'SKY+ Light',brand:'dts',access:'Plano principal que libera adicionais à la carte'},
  {name:'DTT — SKY+ Full com Paramount+',mark:'SKY+ Full',brand:'dtt',access:'Plano principal que libera adicionais à la carte'}
 ],
 sky_alacarte:[
  {name:'DTN — SKY+ Nosso Futebol',mark:'Nosso Futebol',brand:'dtn',access:'Adicional; exige DTS ou DTT ativo'},
  {name:'DTP — SKY+ Premiere',mark:'Premiere',brand:'dtp',access:'Adicional; exige DTS ou DTT ativo'},
  {name:'DTR — SKY+ Telecine',mark:'Telecine',brand:'dtr',access:'Adicional; exige DTS ou DTT ativo'}
 ]
};
const catalogNames={rqc:'Standard',rqd:'Advanced',rqn:'TOP',rqp:'Premium sem anúncios',sky_base:'SKY — Planos',sky_alacarte:'SKY — À la carte'};
const catalogMeta={
 rqc:{icon:'fa-th-large',description:'Aplicativos da categoria Standard'},
 rqd:{icon:'fa-rocket',description:'Aplicativos da categoria Advanced'},
 rqn:{icon:'fa-star',description:'Aplicativos da categoria TOP'},
 rqp:{icon:'fa-diamond',description:'Aplicativos Premium sem anúncios'},
 sky_base:{icon:'fa-television',description:'Planos principais SKY+ Light e Full'},
 sky_alacarte:{icon:'fa-plus-circle',description:'Adicionais liberados por DTS ou DTT'}
};
const catalogOrder=['rqc','rqd','rqn','rqp','sky_base','sky_alacarte'];
const skyFullChannels=[
 {name:'Entretenimento, filmes e séries',channels:['Universal TV','TNT','TNT Séries','Warner Channel','Sony Channel','AXN','AMC','AMC Series','E!','Space','FX','USA','Lifetime','A&E','Investigation Discovery','Studio Universal','Megapix','Cinemax']},
 {name:'Variedades e esportes',channels:['TV Gazeta','ge tv','SporTV','SporTV 2','SporTV 3','ESPN','ESPN 3','ESPN 4','BandSports','D Sports','X Sports']},
 {name:'Documentários e factual',channels:['Discovery Channel','Animal Planet','Discovery Science','History','National Geographic','TLC','HGTV','Food Network','Travel Channel']},
 {name:'Infantil',channels:['Discovery Kids','Cartoon Network','Gloob','Gloobinho','Boy Kids TV']},
 {name:'Abertos',channels:['Globo','SBT','Record','RedeTV!','Band','Amazon Sat Boa Vista','Amazon Sat Porto Velho']},
 {name:'Notícias',channels:['Record News','SBT News','CNN Brasil','CNN Internacional','CNN Brasil Money','OnNews','Sky News','BandNews TV','BBC News','Bloomberg Television','Times Brasil CNBC','CNBC','JP News']},
 {name:'Religiosos',channels:['Canção Nova','TV Pai Eterno','Novo Tempo','TV Evangelizar']},
 {name:'Agronegócio e cultura',channels:['Canal do Boi','Futura','TV Cultura']}
];
const skyLightChannels=[
 {name:'Canais abertos',channels:['Globo','SBT','Record','RedeTV!','Band','TV Cultura','TV Gazeta']},
 {name:'Notícias e esportes',channels:['Record News','SBT News','ge tv']},
 {name:'Religiosos',channels:['Canção Nova','TV Pai Eterno','Novo Tempo','TV Evangelizar']},
 {name:'Agronegócio',channels:['Canal do Boi']}
];
const globoplayChannels=[
 {name:'Globo e informação',channels:['TV Globo','ge tv','Futura','OnNews','CBN Rio de Janeiro','CBN São Paulo']},
 {name:'Esportes',channels:['SporTV','Combate','SporTV 2','SporTV 3']},
 {name:'Entretenimento',channels:['Globoplay Novelas','Multishow','GNT','Canal OFF','Modo Viagem','BIS','Megapix']},
 {name:'Filmes e séries',channels:['Universal TV','Studio Universal','USA','Canal Brasil']},
 {name:'Família e infantil',channels:['Receitas Fast','Malhação Fast','D.P.A. Fast','Gloob','Gloobinho']}
];
const channelLogoSheets={
 light:{url:'assets/channel-sources/sky-light.png',width:1797,height:842},
 full:{url:'assets/channel-sources/sky-full.png',width:1808,height:935},
 globo:{url:'assets/channel-sources/globoplay.png',width:1783,height:922}
};
const channelLogoMap={
 'Globo':['light',575,304,95,95],'TV Globo':['globo',800,157,63,35],
 'Record News':['light',705,320,165,60],'SBT News':['light',907,320,130,55],'ge tv':['light',1112,305,110,95],
 'SBT':['light',578,430,88,88],'Record':['light',723,422,93,100],'RedeTV!':['light',856,420,151,115],'Band':['light',1099,426,122,105],
 'TV Cultura':['light',571,548,105,100],'TV Gazeta':['light',746,566,195,75],'Canal do Boi':['light',1000,560,120,90],
 'Canção Nova':['light',565,685,112,65],'TV Pai Eterno':['light',690,684,105,85],'Novo Tempo':['light',833,685,105,75],'TV Evangelizar':['light',977,684,120,77],
 'SporTV':['globo',882,159,66,30],'Combate':['globo',963,166,80,25],'Futura':['globo',1139,158,64,39],
 'Receitas Fast':['globo',1232,154,56,50],'Malhação Fast':['globo',1310,160,75,31],'D.P.A. Fast':['globo',1396,158,70,33],
 'CBN Rio de Janeiro':['globo',1482,154,92,48],'CBN São Paulo':['globo',1578,154,74,48],
 'OnNews':['globo',800,228,65,26],'Globoplay Novelas':['globo',883,221,80,45],'Multishow':['globo',971,218,68,45],
 'SporTV 2':['globo',1058,231,76,25],'SporTV 3':['globo',1140,231,72,25],'GNT':['globo',1235,224,46,32],
 'Canal OFF':['globo',1320,221,50,38],'Modo Viagem':['globo',1398,220,64,45],'BIS':['globo',1481,223,55,36],'Megapix':['globo',1569,232,70,23],
 'Universal TV':['globo',800,287,72,45],'Studio Universal':['globo',882,291,81,29],'USA':['globo',980,288,50,35],
 'Canal Brasil':['globo',1060,284,53,45],'Gloob':['globo',1138,291,63,32],'Gloobinho':['globo',1223,286,70,42],
 'TNT':['full',239,262,42,38],'TNT Séries':['full',292,262,77,38],'Warner Channel':['full',382,262,50,44],
 'Sony Channel':['full',440,263,49,44],'AXN':['full',501,271,93,27],'AMC':['full',598,265,66,33],'AMC Series':['full',674,265,97,32],
 'E!':['full',836,262,16,41],'Space':['full',170,313,93,35],'FX':['full',282,312,49,35],'Lifetime':['full',403,311,79,39],
 'A&E':['full',507,312,46,36],'Investigation Discovery':['full',566,313,51,35],'Cinemax':['full',827,315,83,34],
 'ESPN':['full',655,487,72,25],'ESPN 3':['full',165,540,85,26],'ESPN 4':['full',282,540,83,27],
 'BandSports':['full',405,535,73,33],'D Sports':['full',492,536,83,30],'X Sports':['full',638,538,117,32],
 'Discovery Channel':['full',180,686,91,25],'Animal Planet':['full',290,678,45,40],'Discovery Science':['full',339,679,75,38],
 'History':['full',420,675,39,48],'National Geographic':['full',466,677,106,45],'TLC':['full',583,680,57,40],
 'HGTV':['full',654,679,58,40],'Food Network':['full',735,675,43,45],'Travel Channel':['full',786,679,73,43],
 'Discovery Kids':['full',186,826,45,49],'Cartoon Network':['full',252,832,55,43],'Boy Kids TV':['full',514,821,63,53],
 'Amazon Sat Boa Vista':['full',1515,274,64,55],'Amazon Sat Porto Velho':['full',1619,273,63,57],
 'CNN Brasil':['full',1207,454,49,39],'CNN Internacional':['full',1272,456,42,36],'CNN Brasil Money':['full',1331,454,90,39],
 'Sky News':['full',1570,463,88,21],'BandNews TV':['full',1004,508,58,34],'BBC News':['full',1081,508,55,34],
 'Bloomberg Television':['full',1146,508,67,35],'Times Brasil CNBC':['full',1246,510,88,32],'CNBC':['full',1355,503,47,40],
 'JP News':['full',1440,508,55,34]
};
let catalogActiveCategory=localStorage.phCatalogCategory||'rqc';
if(!catalogOrder.includes(catalogActiveCategory))catalogActiveCategory='rqc';
const catalogMoney=value=>value==null||value===''?'':String(value).replace('.',',');
function catalogLogo(app){return app.logo?`<span class="ph-app-chip ph-brand-custom" role="img" aria-label="${esc(app.name)}"><img src="${esc(app.logo)}" alt=""></span>`:`<span class="ph-app-chip ph-brand-${esc(app.brand)}" role="img" aria-label="${esc(app.name)}"><b>${esc(app.mark)}</b></span>`}
function channelWithLogo(channel){const crop=channelLogoMap[channel];if(!crop)return `<span class="ph-channel-item ph-channel-without-logo"><b>${esc(channel)}</b></span>`;const [sheetKey,x,y,w,h]=crop,sheet=channelLogoSheets[sheetKey],scale=Math.min(62/w,34/h,1),width=Math.max(1,Math.round(w*scale)),height=Math.max(1,Math.round(h*scale)),positionX=Math.round(x*scale),positionY=Math.round(y*scale),sheetWidth=Math.round(sheet.width*scale),sheetHeight=Math.round(sheet.height*scale);return `<span class="ph-channel-item"><span class="ph-channel-logo ph-channel-logo-${sheetKey}" role="img" aria-label="Logo ${esc(channel)}"><i style="width:${width}px;height:${height}px;background-image:url('${sheet.url}');background-size:${sheetWidth}px ${sheetHeight}px;background-position:-${positionX}px -${positionY}px"></i></span><b>${esc(channel)}</b></span>`}
function skyChannelGuide(title,channels,open=false){const count=channels.reduce((total,group)=>total+group.channels.length,0);return `<details class="ph-sky-guide"${open?' open':''}><summary><div><span>Grade de referência</span><h3>${esc(title)}</h3><p>Lista organizada conforme o material fornecido pela PlayHub. A disponibilidade pode ser atualizada pela operadora.</p></div><div class="ph-sky-guide-summary"><span class="ph-sky-channel-count">${count} canais</span><i class="fa fa-chevron-down" aria-hidden="true"></i></div></summary><div class="ph-sky-channel-grid">${channels.map(group=>`<article><h4>${esc(group.name)}</h4><div>${group.channels.map(channelWithLogo).join('')}</div></article>`).join('')}</div></details>`}
function skyChannelGuides(){return `<div class="ph-sky-guides"><div class="ph-sky-guides-title"><i class="fa fa-list" aria-hidden="true"></i><div><b>Grades de canais SKY+</b><small>Abra cada plano para consultar os canais incluídos.</small></div></div>${skyChannelGuide('Canais do SKY+ Light',skyLightChannels,true)}${skyChannelGuide('Canais do SKY+ Full',skyFullChannels)}</div>`}
function globoplayChannelGuide(category){const premium=category==='rqp',plan=premium?'Plano Premium':'Plano Padrão com anúncios',count=globoplayChannels.reduce((total,group)=>total+group.channels.length,0);return `<div class="ph-globo-guides"><div class="ph-globo-guides-title"><i class="fa fa-television" aria-hidden="true"></i><div><b>Canais do Globoplay</b><small>Consulta rápida da grade incluída no ${plan}.</small></div></div><details class="ph-globo-guide" open><summary><div><span>Grade de referência</span><h3>Globoplay — ${plan}</h3><p>Os dois planos possuem a grade informada no material da PlayHub; a diferença principal é a exibição de anúncios.</p></div><div class="ph-globo-guide-summary"><span class="ph-globo-channel-count">${count} canais</span><i class="fa fa-chevron-down" aria-hidden="true"></i></div></summary><div class="ph-globo-channel-grid">${globoplayChannels.map(group=>`<article><h4>${esc(group.name)}</h4><div>${group.channels.map(channelWithLogo).join('')}</div></article>`).join('')}</div></details></div>`}
function activateCatalogCategory(category){if(!catalogOrder.includes(category))return;catalogActiveCategory=category;localStorage.phCatalogCategory=category;$$('.ph-catalog-tab').forEach(button=>{const active=button.dataset.category===category;button.classList.toggle('active',active);button.setAttribute('aria-selected',active?'true':'false')});$$('.ph-catalog-pane').forEach(pane=>pane.hidden=pane.dataset.category!==category)}
async function loadCatalog(){
 const root=$('#catalogGroups');if(!root)return;
 try{
  const response=await request('catalog_values'),saved=new Map((response.data||[]).map(item=>[item.category+':'+item.app_key,item])),hidden=new Set((response.hidden||[]).map(item=>item.category+':'+item.app_key)),logos=new Map((response.logos||[]).map(item=>[item.category+':'+item.app_key,item.logo_path])),customByCategory={};
  catalogOrder.forEach(category=>customByCategory[category]=[]);
  (response.apps||[]).forEach(app=>{if(customByCategory[app.category])customByCategory[app.category].push({name:app.name,mark:app.name,brand:app.app_key,key:app.app_key,logo:app.logo_path,custom:true,id:app.id})});
  const tabs=`<nav class="ph-catalog-tabs" aria-label="Categorias do catálogo">${catalogOrder.map(category=>`<button type="button" class="ph-catalog-tab${category===catalogActiveCategory?' active':''}" data-category="${category}" role="tab" aria-selected="${category===catalogActiveCategory?'true':'false'}"><i class="fa ${catalogMeta[category].icon}" aria-hidden="true"></i><span><b>${catalogNames[category]}</b><small>${catalogMeta[category].description}</small></span></button>`).join('')}</nav>`;
  const panes=catalogOrder.map(category=>{
   const original=(availableApps[category]||[]).filter(app=>!hidden.has(category+':'+app.brand)).map(app=>Object.assign({},app,{key:app.brand,custom:false,logo:logos.get(category+':'+app.brand)||''})),apps=original.concat(customByCategory[category]||[]);
   const rows=apps.map(app=>{const value=saved.get(category+':'+app.key)||{},access=value.access_info!=null?value.access_info:(app.access||''),action=`<div class="ph-row-actions ph-catalog-actions"><button type="button" class="ph-icon-btn replace-catalog-logo" data-category="${category}" data-key="${esc(app.key)}" data-name="${esc(app.name)}" title="Trocar logo de ${esc(app.name)}" aria-label="Trocar logo de ${esc(app.name)}"><i class="fa fa-image" aria-hidden="true"></i></button><button type="button" class="ph-icon-btn ph-icon-delete delete-catalog-app" data-category="${category}" data-key="${esc(app.key)}" data-name="${esc(app.name)}" title="Remover ${esc(app.name)}" aria-label="Remover ${esc(app.name)}"><i class="fa fa-trash" aria-hidden="true"></i></button></div>`;return `<tr class="ph-catalog-row" data-category="${category}" data-app-key="${esc(app.key)}"><td><div class="ph-catalog-app">${catalogLogo(app)}<div><b>${esc(app.name)}</b><small>${catalogNames[category]}${app.custom?' · adicionado manualmente':''}</small></div></div></td><td><input class="ph-catalog-cost" type="text" inputmode="decimal" autocomplete="off" placeholder="0,00" aria-label="Preço de custo de ${esc(app.name)}" value="${esc(catalogMoney(value.cost_price))}"></td><td><input class="ph-catalog-sale" type="text" inputmode="decimal" autocomplete="off" placeholder="0,00" aria-label="Preço de venda de ${esc(app.name)}" value="${esc(catalogMoney(value.sale_price))}"></td><td><input class="ph-catalog-access" type="text" autocomplete="off" maxlength="120" placeholder="Informe a quantidade" aria-label="Acessos ou licenças de ${esc(app.name)}" value="${esc(access)}"></td><td class="ph-catalog-row-action">${action}</td></tr>`}).join('');
   const empty=rows?'':`<tr><td colspan="5">Nenhum item nesta categoria. Use “Adicionar item”.</td></tr>`;
   const reference=category==='sky_base'?skyChannelGuides():(category==='rqn'||category==='rqp'?globoplayChannelGuide(category):'');
   return `<section class="ph-catalog-group ph-catalog-pane" data-category="${category}"${category===catalogActiveCategory?'':' hidden'}><div class="ph-catalog-group-title"><div><span class="ph-catalog-eyebrow">${catalogMeta[category].description}</span><h3>${catalogNames[category]}</h3></div><div class="ph-catalog-group-actions"><span>${apps.length} ${category.startsWith('sky_')?'produtos':'aplicativos'}</span><button type="button" class="ph-btn ph-btn-light add-catalog-app" data-category="${category}" title="Adiciona um novo item à categoria ${catalogNames[category]} somente no catálogo interno."><i class="fa fa-plus" aria-hidden="true"></i> Adicionar item</button></div></div><div class="ph-table-wrap"><table class="ph-catalog-table"><thead><tr><th>${category.startsWith('sky_')?'Produto':'Aplicativo'}</th><th>Preço de custo (R$)</th><th>Preço de venda (R$)</th><th>Acessos ou observação</th><th>Ação</th></tr></thead><tbody>${rows}${empty}</tbody></table></div>${reference}</section>`;
  }).join('');
  root.innerHTML=tabs+`<div class="ph-catalog-content">${panes}</div>`;
  $$('.ph-catalog-tab').forEach(button=>button.addEventListener('click',()=>activateCatalogCategory(button.dataset.category)));
  $$('.add-catalog-app').forEach(button=>button.addEventListener('click',()=>openCatalogModal(button.dataset.category)));
  $$('.delete-catalog-app').forEach(button=>button.addEventListener('click',()=>deleteCatalogApp(button)));
  $$('.replace-catalog-logo').forEach(button=>button.addEventListener('click',()=>chooseCatalogLogo(button)));
  applyHelpTitles(root);
 }catch(e){root.innerHTML='<div class="ph-notice">'+esc(e.message)+'</div>';toast(e.message,true)}
}
async function saveCatalog(){const button=$('#saveCatalog');if(!button)return;const items=$$('.ph-catalog-row').map(row=>({category:row.dataset.category,app_key:row.dataset.appKey,cost_price:row.querySelector('.ph-catalog-cost')?.value.trim()||'',sale_price:row.querySelector('.ph-catalog-sale')?.value.trim()||'',access_info:row.querySelector('.ph-catalog-access')?.value.trim()||''}));button.disabled=true;try{const result=await request('save_catalog',{items});toast(result.message);await loadCatalog()}catch(e){toast(e.message,true)}finally{button.disabled=false}}
let catalogPreviewUrl='';
function resetCatalogPreview(){if(catalogPreviewUrl){URL.revokeObjectURL(catalogPreviewUrl);catalogPreviewUrl=''}const preview=$('#catalogLogoPreview');if(preview)preview.innerHTML='<i class="fa fa-image" aria-hidden="true"></i>'}
function openCatalogModal(category){const modal=$('#catalogAppModal'),form=$('#catalogAppForm');if(!modal||!form||!catalogNames[category])return;form.reset();resetCatalogPreview();$('#catalogAppCategory').value=category;$('#catalogAppCategoryName').textContent=catalogNames[category];modal.hidden=false;document.body.classList.add('ph-modal-open');form.querySelector('[name="name"]')?.focus()}
function closeCatalogModal(){const modal=$('#catalogAppModal');if(!modal)return;modal.hidden=true;document.body.classList.remove('ph-modal-open');resetCatalogPreview()}
async function deleteCatalogApp(button){if(!await askConfirmation(`Deseja remover ${button.dataset.name} do catálogo interno? Esta ação também apaga a logo enviada.`,`Remover ${button.dataset.name}`))return;button.disabled=true;try{const result=await request('remove_catalog_app',{category:button.dataset.category,app_key:button.dataset.key});toast(result.message);await loadCatalog()}catch(e){toast(e.message,true);button.disabled=false}}
function chooseCatalogLogo(button){const input=$('#replaceCatalogLogo');if(!input)return;input.value='';input.dataset.category=button.dataset.category;input.dataset.key=button.dataset.key;input.dataset.name=button.dataset.name;input.click()}
$('#saveCatalog')?.addEventListener('click',saveCatalog);
$('#catalogAppLogo')?.addEventListener('change',event=>{resetCatalogPreview();const file=event.target.files?.[0],preview=$('#catalogLogoPreview');if(!file||!preview)return;catalogPreviewUrl=URL.createObjectURL(file);preview.innerHTML=`<img src="${catalogPreviewUrl}" alt="Prévia da logo">`});
$('#replaceCatalogLogo')?.addEventListener('change',async event=>{const input=event.currentTarget,file=input.files?.[0];if(!file)return;const data=new FormData();data.set('category',input.dataset.category||'');data.set('app_key',input.dataset.key||'');data.set('logo',file);try{const result=await requestForm('replace_catalog_logo',data);toast(result.message);await loadCatalog()}catch(e){toast(e.message,true)}finally{input.value=''}});
$('#catalogAppForm')?.addEventListener('submit',async event=>{event.preventDefault();const form=event.currentTarget,button=$('#addCatalogApp'),data=new FormData(form);button.disabled=true;try{const result=await requestForm('add_catalog_app',data);toast(result.message);closeCatalogModal();await loadCatalog()}catch(e){toast(e.message,true)}finally{button.disabled=false}});
$$('[data-close-catalog-modal]').forEach(button=>button.addEventListener('click',closeCatalogModal));
document.addEventListener('keydown',event=>{if(event.key==='Escape'&&!$('#catalogAppModal')?.hidden)closeCatalogModal()});
loadCatalog();
function openProductModal(login){selectedProductLogin=login||'';const modal=$('#productModal');if(!modal||!selectedProductLogin)return;modal.hidden=false;document.body.classList.add('ph-modal-open');$('#productModalTitle').textContent='Produtos de '+selectedProductLogin;$('#currentSubscription').textContent='Carregando cliente...';$('#productGroups').innerHTML='';$('#playhubEmailText').textContent='Carregando...';$('#playhubMobileText').textContent='—';$('#mkEmailText').textContent='Carregando...';$('#mkMobileText').textContent='—';const update=$('#updatePlayHubContact');if(update)update.disabled=true;loadProducts(selectedProductLogin)}
function closeProductModal(){const modal=$('#productModal');if(!modal)return;modal.hidden=true;document.body.classList.remove('ph-modal-open');selectedProductLogin=''}
async function loadProducts(requestedLogin=''){
 const login=requestedLogin||selectedProductLogin;
 if(!login)return toast('Informe o login do cliente.',true);
 try{
  const j=await request('products',null,{login});
  productData=j.data;
  const names={rqc:'Standard à escolha',rqd:'Advanced à escolha',rqn:'TOP à escolha',rqp:'Premium à escolha (sem anúncios)',sky_base:'SKY — Plano principal',sky_alacarte:'SKY — À la carte'};
  const types={rqc:'Standard',rqd:'Advanced',rqn:'TOP',rqp:'Premium',sky_base:'SKY',sky_alacarte:'À la carte'};
  const visibleCodes=new Set(Object.keys(names).reduce((codes,group)=>codes.concat((productData[group]||[]).map(product=>product.code)),[]));
  activeSubscriptions=(j.subscriptions||[]).filter(item=>visibleCodes.has(item.product_code));
  const client=j.client||{login,name:login,document:''},clientLogin=client.login||login;
  $('#productModalTitle').textContent='Produtos de '+(client.name||clientLogin);
  $('#playhubEmailText').textContent=client.playhub_email||'Não informado';$('#playhubMobileText').textContent=client.playhub_mobile||'Não informado';$('#mkEmailText').textContent=client.mk_email||'Não informado';$('#mkMobileText').textContent=client.mk_mobile||'Não informado';
  const contactStatus=$('#playhubContactStatus'),contactHelp=$('#playhubContactHelp'),updateContact=$('#updatePlayHubContact');
  if(client.contact_changed){
   contactStatus.textContent='Atualização pendente';contactStatus.className='ph-status suspended';
   if(client.contact_update_blocked){updateContact.disabled=true;updateContact.title=`Desassocie os ${client.active_product_count} produto(s) ativos antes de atualizar o e-mail da PlayHub.`;contactHelp.textContent=`O e-mail do MK-AUTH está diferente do cadastrado na PlayHub. Desassocie todos os ${client.active_product_count} produto(s) antes de atualizar os dados.`}
   else{updateContact.disabled=false;updateContact.title='Copia para a PlayHub o e-mail e o celular atuais do cadastro do MK-AUTH.';contactHelp.textContent=client.email_changed?'Não há produtos ativos. Agora é possível substituir o e-mail e o celular da PlayHub pelos dados atuais do MK-AUTH.':'O celular está diferente e pode ser atualizado com o dado atual do MK-AUTH.'}
  }else{contactStatus.textContent='Dados atualizados';contactStatus.className='ph-status active';updateContact.disabled=true;updateContact.title='A PlayHub já está com o mesmo e-mail e celular do MK-AUTH.';contactHelp.textContent='A PlayHub está com o mesmo e-mail e celular cadastrados no MK-AUTH.'}
  const productsText=activeSubscriptions.length?`Produtos ativos na PlayHub: <b>${activeSubscriptions.map(item=>esc(item.name||item.product_code)).join(', ')}</b>`:'Nenhum produto ativo para este cliente.';
  $('#currentSubscription').innerHTML=`<div class="ph-selected-customer"><span><small>Cliente</small><a class="ph-customer-link" href="${esc(customerUrl(client.uuid,clientLogin))}" target="_blank" rel="noopener" title="Abre o cadastro deste cliente no MK-AUTH.">${esc(client.name||clientLogin)}</a><em>${esc(clientLogin)}</em></span><span>${productsText}</span></div>`;
	  const hasSkyBase=activeSubscriptions.some(item=>['DTS','DTT'].includes(item.product_code)),hasSkyAddons=activeSubscriptions.some(item=>['DTN','DTP','DTR'].includes(item.product_code));
  $('#productGroups').innerHTML=Object.keys(names).map(group=>{
	   const multiple=group==='sky_alacarte',currentInGroup=multiple?null:activeSubscriptions.find(item=>item.product_class===group);
   const items=(productData[group]||[]).map(product=>{
    const active=hasSubscription(product.code);
	    const blockedAddon=group==='sky_alacarte'&&!hasSkyBase,blockedDtq=group==='sky_base'&&product.code==='DTQ'&&hasSkyAddons,blocked=blockedAddon||blockedDtq;
	    let help=currentInGroup?`Troca o pacote ${currentInGroup.product_code} pelo ${product.code}. O pacote anterior desta categoria será desassociado.`:`Associa o pacote ${product.code} a este cliente na PlayHub.`;
	    if(blockedAddon)help='Ative primeiro DTS ou DTT para liberar este adicional SKY à la carte.';else if(blockedDtq)help='DTQ não libera adicionais. Desassocie Nosso Futebol, Premiere e Telecine antes desta troca.';else if(product.code==='DTQ')help+=' Este plano não libera Nosso Futebol, Premiere ou Telecine.';
	    const button=active?'<span class="ph-status active" title="Este pacote está associado e ativo para o cliente.">Ativo</span>':`<button class="ph-btn choose-product" data-code="${esc(product.code)}" data-old="${esc(currentInGroup?.product_code||'')}" title="${esc(help)}"${blocked?' disabled':''}>${currentInGroup?'Trocar':'Associar'}</button>`;
    return `<article class="ph-product"><span class="ph-product-code">${esc(product.code)}</span><div class="ph-product-name"><b>${esc(product.name)}</b>${product.vasp?`<small>${esc(product.vasp)}</small>`:''}</div><span class="ph-product-type">${esc(types[group])}</span><div class="ph-product-action">${button}</div></article>`;
   }).join('');
	   const note=group==='sky_base'?'<p class="ph-product-group-note">DTS ou DTT são obrigatórios para contratar adicionais SKY. DTQ não libera os adicionais.</p>':group==='sky_alacarte'?'<p class="ph-product-group-note">Nosso Futebol, Premiere e Telecine podem ser combinados, desde que DTS ou DTT esteja ativo.</p>':'';
	   return `<section class="ph-product-group"><h3>${names[group]}</h3>${note}<div class="ph-product-head"><span>Código</span><span>Nome</span><span>Tipo</span><span>Ação</span></div><div class="ph-products">${items||'<p>Nenhum produto cadastrado nesta classe.</p>'}</div></section>`;
  }).join('');
  $$('.choose-product').forEach(button=>button.onclick=()=>changeProduct(login,button.dataset.code,button.dataset.old||''));
  applyHelpTitles($('#productModal'));
 }catch(e){toast(e.message,true)}
}
async function updatePlayHubContactFromMk(){const button=$('#updatePlayHubContact'),login=selectedProductLogin;if(!button||!login||button.disabled)return;if(!await askConfirmation('Deseja substituir o e-mail e o celular atuais da PlayHub pelos dados cadastrados no MK-AUTH?','Atualizar dados da PlayHub'))return;button.disabled=true;try{const result=await request('update_playhub_contact_from_mk',{login});toast(result.message);await loadProducts(login);loadRegisteredSubscriptions()}catch(e){toast(e.message,true);await loadProducts(login)}}
async function changeProduct(login,code,old=''){const question=old?`Deseja trocar o pacote ${old} pelo ${code}? O pacote anterior desta categoria será desassociado.`:`Deseja associar o pacote ${code} a este cliente?`;if(!await askConfirmation(question,old?'Trocar pacote':'Associar pacote'))return;try{const j=await request('change_product',{login,old,new:code});toast(j.message);loadProducts(login);loadRegisteredSubscriptions()}catch(e){toast(e.message,true)}}
$('#updatePlayHubContact')?.addEventListener('click',updatePlayHubContactFromMk);
$$('[data-close-product-modal]').forEach(button=>button.addEventListener('click',closeProductModal));
document.addEventListener('keydown',event=>{if(event.key==='Escape'&&!$('#productModal')?.hidden)closeProductModal()});
if(cfg.page==='products'&&cfg.selectedLogin)openProductModal(cfg.selectedLogin);
async function logs(){const tbody=$('#logRows');if(!tbody)return;try{const j=await request('logs');tbody.innerHTML=j.data.length?j.data.map(r=>`<tr><td>${esc(r.created_at)}</td><td>${esc(r.level)}</td><td>${esc(r.action)}</td><td>${esc(r.mk_login||'—')}</td><td>${esc(r.message)}</td><td>${esc(r.user_login||'—')}</td></tr>`).join(''):'<tr><td colspan="6">Sem registros.</td></tr>'}catch(e){tbody.innerHTML='<tr><td colspan="6">'+esc(e.message)+'</td></tr>'}}logs();
async function settings(){const form=$('#settingsForm');try{const j=await request('settings'),d=j.data;if(form)Object.keys(d).forEach(k=>{if(form.elements[k])form.elements[k].value=d[k]||''});const u=$('#workerUrl');if(u&&d.worker_token)u.textContent=location.origin+location.pathname.replace(/index\.php$/,'').replace(/\/$/,'')+'/worker.php?token='+d.worker_token}catch(e){if(form)toast(e.message,true)}}settings();
$('#settingsForm')?.addEventListener('submit',async e=>{e.preventDefault();const d=Object.fromEntries(new FormData(e.target));try{const j=await request('save_settings',d);toast(j.message);settings();summary()}catch(x){toast(x.message,true)}});$('#testApi')?.addEventListener('click',async()=>{const form=$('#settingsForm');const d=form?Object.fromEntries(new FormData(form)):{};try{const j=await request('test',d);toast(j.message)}catch(e){toast(e.message,true)}});
applyHelpTitles();
})();
