<?php
declare(strict_types=1);

include('addons.class.php');
require_once __DIR__.'/lib/bootstrap.php';
ph_require_login();
$db=ph_db(); ph_schema($db);
$page=preg_replace('/[^a-z_]/','',(string)($_GET['p']??'dashboard')) ?: 'dashboard';
$allowed=['dashboard','clients','products','inactive','catalog','sync','logs','settings'];
if(!in_array($page,$allowed,true))$page='dashboard';
$selectedLogin=trim((string)($_GET['login']??''));
if(strlen($selectedLogin)>100 || !preg_match('/^[A-Za-z0-9_.@-]*$/',$selectedLogin))$selectedLogin='';
$customerDetailExtension=is_file('/opt/mk-auth/admin/cliente_det.hhvm')?'.hhvm':'.php';
$customerDetailBase='../../cliente_det'.$customerDetailExtension;
$pageTitles=['dashboard'=>'Visão geral','clients'=>'Ativar Clientes','products'=>'Produtos e assinaturas','inactive'=>'Clientes desativados','catalog'=>'Catálogo e Preços','sync'=>'Sincronização','logs'=>'Histórico','settings'=>'Configurações da API'];
$pageDescriptions=['dashboard'=>'Acompanhe clientes, assinaturas e a integração PlayHub em um só lugar.','clients'=>'Localize um assinante e mantenha o cadastro sincronizado com a PlayHub.','products'=>'Consulte, associe ou retire os produtos autorizados para cada cliente.','inactive'=>'Consulte separadamente os clientes desativados no MK-AUTH.','catalog'=>'Acompanhe aplicativos, planos SKY, adicionais à la carte, custos e preços por categoria.','sync'=>'Confira bloqueios, cancelamentos e reativações automáticas.','logs'=>'Consulte a auditoria das ações realizadas pelo addon.','settings'=>'Configure as credenciais e os endereços da API PlayHub.'];
$cssAsset=(string)filemtime(__DIR__.'/assets/app.css');
$jsAsset=(string)filemtime(__DIR__.'/assets/app.js');
?>
<!doctype html><?php if(isset($_SESSION['MM_Usuario'])): ?><html lang="pt-BR"><?php else: ?><html lang="pt-BR" class="has-navbar-fixed-top"><?php endif; ?>
<head>
 <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
 <title>MK-AUTH :: PlayHub</title>
 <link rel="preconnect" href="https://fonts.googleapis.com">
 <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
 <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Manrope:wght@600;700;800&display=swap" rel="stylesheet">
 <link href="../../estilos/mk-auth.css" rel="stylesheet">
 <link href="../../estilos/font-awesome.css" rel="stylesheet">
 <link href="../../estilos/bi-icons.css" rel="stylesheet">
 <link href="assets/app.css?v=<?=$cssAsset?>" rel="stylesheet">
 <script src="../../scripts/jquery.js"></script>
 <script src="../../scripts/mk-auth.js"></script>
</head>
<body bgcolor="whitesmoke">
<?php include('../../topo.php'); ?>
<div class="ph-shell" data-page="<?=htmlspecialchars($page)?>">
 <header class="ph-hero">
  <div class="ph-hero-copy"><span class="ph-kicker"><span class="ph-status-dot" aria-hidden="true"></span> Integração PlayHub</span><h1><?=htmlspecialchars($pageTitles[$page],ENT_QUOTES,'UTF-8')?></h1><p><?=htmlspecialchars($pageDescriptions[$page],ENT_QUOTES,'UTF-8')?></p></div>
  <div class="ph-hero-actions"><span class="ph-pill" id="phIntegration" title="Mostra se as credenciais da API PlayHub estão configuradas.">Verificando integração</span><button class="ph-btn ph-btn-light" data-sync-all title="Confere os clientes vinculados e aplica bloqueios, desativações e reativações pendentes."><i class="fa fa-refresh" aria-hidden="true"></i> Sincronizar agora</button></div>
 </header>
 <div class="ph-workspace">
 <aside class="ph-side" id="phSide">
  <div class="ph-brand"><span class="ph-side-copy"><b>PlayHub</b><small>Streaming integrado</small></span><button id="phCollapse" aria-label="Recolher menu" aria-expanded="true" title="Recolher menu"><i class="fa fa-chevron-left" aria-hidden="true"></i></button></div>
  <nav>
   <a href="?p=dashboard" class="<?=$page==='dashboard'?'active':''?>" data-title="Visão geral"><i class="fa fa-dashboard"></i><span>Visão geral<small>Indicadores e alertas</small></span></a>
   <div class="ph-nav-title">OPERAÇÃO</div>
   <a href="?p=clients" class="<?=$page==='clients'?'active':''?>" data-title="Ativar Clientes"><i class="fa fa-users"></i><span>Ativar Clientes<small>Cadastro e situação</small></span></a>
   <a href="?p=products" class="<?=$page==='products'?'active':''?>" data-title="Produtos e assinaturas"><i class="fa fa-play-circle"></i><span>Produtos e assinaturas<small>Classes e trocas</small></span></a>
	   <a href="?p=inactive" class="<?=$page==='inactive'?'active':''?>" data-title="Clientes desativados"><i class="fa fa-user-times"></i><span>Clientes desativados<small>Cadastros encerrados</small></span></a>
	   <a href="?p=catalog" class="<?=$page==='catalog'?'active':''?>" data-title="Catálogo e Preços"><i class="fa fa-tags"></i><span>Catálogo e Preços<small>Custos, vendas e acessos</small></span></a>
   <a href="?p=sync" class="<?=$page==='sync'?'active':''?>" data-title="Sincronização"><i class="fa fa-exchange"></i><span>Sincronização<small>Bloqueio e cancelamento</small></span></a>
   <div class="ph-nav-title">ADMINISTRAÇÃO</div>
   <a href="?p=logs" class="<?=$page==='logs'?'active':''?>" data-title="Histórico"><i class="fa fa-history"></i><span>Histórico<small>Auditoria das ações</small></span></a>
   <a href="?p=settings" class="<?=$page==='settings'?'active':''?>" data-title="Configurações"><i class="fa fa-cog"></i><span>Configurações<small>Credenciais e API</small></span></a>
  </nav>
 </aside>
 <main class="ph-main">

 <?php if($page==='dashboard'): ?>
 <section class="ph-heading"><div><h2>Visão geral</h2><p>Acompanhe a integração e as assinaturas em um só lugar.</p></div></section>
 <div class="ph-cards" id="summaryCards"><article><small>Clientes vinculados</small><strong>—</strong></article><article><small>Assinaturas ativas</small><strong>—</strong></article><article><small>Suspensos</small><strong>—</strong></article><article class="danger"><small>Erros pendentes</small><strong>—</strong></article></div>
 <div class="ph-grid ph-grid-2"><section class="ph-panel"><h3>Automação de situação</h3><div class="ph-rule"><b>Internet liberada ou reduzida</b><span>Mantém os produtos PlayHub ativos.</span></div><div class="ph-rule"><b>Cliente bloqueado</b><span>Retira temporariamente os produtos até a liberação.</span></div><div class="ph-rule"><b>Cliente desativado</b><span>Cancela os produtos vinculados na PlayHub.</span></div></section><section class="ph-panel"><h3>Produtos PlayHub</h3><p>Associe ou retire qualquer produto autorizado no line-up da AIO CONNECT.</p><div class="ph-class-tags"><span>Standard à escolha</span><span>Premium à escolha</span><span>Advanced à escolha</span><span>TOP à escolha</span></div><a class="ph-link" href="?p=products">Gerenciar produtos →</a></section></div>
 <?php elseif($page==='clients'): ?>
 <section class="ph-heading"><div><h2>Ativar Clientes</h2><p>Localize um assinante e sincronize os dados com a PlayHub.</p></div></section>
 <section class="ph-panel"><div class="ph-toolbar"><input id="clientSearch" placeholder="Nome, login, CPF ou CNPJ" title="Digite o nome, login, CPF ou CNPJ de um cliente ativo no MK-AUTH."><button class="ph-btn" id="searchClients" title="Pesquisa somente clientes ativos que podem ser cadastrados na PlayHub.">Pesquisar</button></div><div class="ph-table-wrap"><table><thead><tr><th>Cliente</th><th>Login</th><th>Internet</th><th>PlayHub</th><th>Última sincronização</th><th>Ações</th></tr></thead><tbody id="clientRows"><tr><td colspan="6">Pesquise para listar os clientes.</td></tr></tbody></table></div></section>
 <?php elseif($page==='products'): ?>
 <section class="ph-heading"><div><h2>Produtos e assinaturas</h2><p>Consulte a PlayHub e associe ou retire produtos autorizados.</p></div></section>
 <section class="ph-panel"><h3>Clientes ativos e assinaturas</h3><div class="ph-toolbar"><input id="subscriptionSearch" placeholder="Pesquisar por nome, login, e-mail, telefone, produto ou status" title="Filtra clientes e assinaturas por qualquer uma destas informações."><button class="ph-btn" id="searchSubscriptions" title="Pesquisa na lista de clientes ativos ou bloqueados da PlayHub.">Pesquisar</button></div><div class="ph-table-wrap"><table class="ph-subscriptions-table"><thead><tr><th>Cliente</th><th>Login</th><th>E-mail</th><th>Telefone</th><th>Assinaturas</th><th>Status</th><th>Ações</th></tr></thead><tbody id="subscriptionRows"><tr><td colspan="7">Carregando clientes cadastrados...</td></tr></tbody></table></div></section>
	 <?php elseif($page==='inactive'): ?>
	 <section class="ph-heading"><div><h2>Clientes desativados</h2><p>Clientes desativados no MK-AUTH e sem produtos ativos na PlayHub.</p></div></section>
	 <section class="ph-panel"><h3>Cadastros desativados</h3><div class="ph-toolbar"><input id="inactiveSearch" placeholder="Pesquisar por nome, login, e-mail ou telefone" title="Filtra somente os clientes desativados no MK-AUTH."><button class="ph-btn" id="searchInactive" title="Pesquisa na lista separada de clientes desativados.">Pesquisar</button></div><div class="ph-table-wrap"><table><thead><tr><th>Cliente</th><th>Login</th><th>E-mail</th><th>Telefone</th><th>Última sincronização</th><th>Status</th></tr></thead><tbody id="inactiveRows"><tr><td colspan="6">Carregando clientes desativados...</td></tr></tbody></table></div></section>
	 <?php elseif($page==='catalog'): ?>
	 <section class="ph-heading"><div><h2>Catálogo e Preços</h2><p>Aplicativos, planos SKY, adicionais à la carte e grade de canais organizados por categoria.</p></div></section>
	 <section class="ph-panel ph-catalog-panel"><div class="ph-catalog-toolbar"><div><h3>Portfólio PlayHub</h3><p>Os valores e itens personalizados são somente para acompanhamento interno; não são enviados para a PlayHub.</p></div><button type="button" class="ph-btn" id="saveCatalog" title="Salva preços, acessos e observações somente no catálogo interno do addon."><i class="fa fa-save" aria-hidden="true"></i> Salvar valores</button></div><div id="catalogGroups"><div class="ph-notice">Carregando catálogo...</div></div><input type="file" id="replaceCatalogLogo" accept="image/png,image/jpeg,image/webp" title="Selecione uma nova logo PNG, JPG ou WebP. A logo anterior será apagada." hidden></section>
 <?php elseif($page==='sync'): ?>
 <section class="ph-heading"><div><h2>Sincronização</h2><p>Reconcile a situação do MK-AUTH com os aplicativos.</p></div></section>
 <div class="ph-grid"><section class="ph-panel"><h3>Regras aplicadas</h3><ul class="ph-list"><li><b>Bloqueado:</b> suspender os produtos PlayHub.</li><li><b>Reduzido:</b> manter os produtos ativos.</li><li><b>Liberado:</b> reativar produtos suspensos.</li><li><b>Desativado:</b> cancelar os produtos PlayHub.</li></ul><button class="ph-btn" data-sync-all title="Executa agora a conferência de situação dos clientes vinculados.">Executar conferência agora</button></section></div>
 <?php elseif($page==='logs'): ?>
 <section class="ph-heading"><div><h2>Histórico</h2><p>Auditoria de sincronizações, bloqueios, cancelamentos e trocas.</p></div></section><section class="ph-panel"><div class="ph-table-wrap"><table><thead><tr><th>Data</th><th>Nível</th><th>Ação</th><th>Cliente</th><th>Mensagem</th><th>Usuário</th></tr></thead><tbody id="logRows"><tr><td colspan="6">Carregando...</td></tr></tbody></table></div></section>
 <?php else: ?>
 <section class="ph-heading"><div><h2>Configurações da API</h2><p>As credenciais ficam protegidas no banco de dados e nunca são exibidas novamente.</p></div></section>
 <section class="ph-panel"><form id="settingsForm"><div class="ph-form-grid"><label>API Base URL<input name="base_url" placeholder="https://sandbox-api.playhub.com.br/api/v3" title="Endereço base da API sandbox ou de produção da PlayHub."></label><label>ISP Code<input name="isp_code" title="Código do provedor informado pela PlayHub."></label><label>API Key<input name="api_key" autocomplete="off" placeholder="Deixe em branco para manter a atual" title="Chave de acesso fornecida pela PlayHub. Deixe em branco para não alterar."></label><label>API Secret<input type="password" name="api_secret" autocomplete="new-password" placeholder="Deixe em branco para manter o atual" title="Segredo da API fornecido pela PlayHub. Deixe em branco para não alterar."></label></div><div class="ph-actions"><button type="button" class="ph-btn ph-btn-light" id="testApi" title="Testa as credenciais informadas sem salvar alterações.">Testar conexão</button><button class="ph-btn" title="Salva o endereço e as credenciais da API PlayHub.">Salvar configurações</button></div></form></section>
 <?php endif; ?>
 </main>
 </div>
</div>
<?php if(in_array($page,['products','clients'],true)): ?>
<div class="ph-modal" id="productModal" hidden>
 <div class="ph-modal-backdrop" data-close-product-modal></div>
 <section class="ph-modal-card" role="dialog" aria-modal="true" aria-labelledby="productModalTitle">
  <header class="ph-modal-header"><div><span>Associar produto</span><h2 id="productModalTitle">Selecionar produtos</h2></div><button class="ph-icon-btn" type="button" data-close-product-modal aria-label="Fechar" title="Fechar"><i class="fa fa-times" aria-hidden="true"></i></button></header>
	  <div class="ph-modal-body"><div id="currentSubscription" class="ph-notice">Carregando cliente...</div><div class="ph-contact-review" id="playhubContactReview"><div class="ph-contact-compare"><article><small>Dados atuais na PlayHub</small><b id="playhubEmailText">Carregando...</b><span id="playhubMobileText">—</span></article><article><small>Dados atuais no MK-AUTH</small><b id="mkEmailText">Carregando...</b><span id="mkMobileText">—</span></article></div><div class="ph-contact-update"><span class="ph-status pending" id="playhubContactStatus">Verificando dados</span><button type="button" class="ph-btn" id="updatePlayHubContact" disabled title="Copia para a PlayHub o e-mail e o celular atuais do cadastro do MK-AUTH. A troca de e-mail só é permitida quando não há produtos associados."><i class="fa fa-refresh" aria-hidden="true"></i> Atualizar dados na PlayHub</button></div><small id="playhubContactHelp">Os dados são somente de leitura e vêm do cadastro do MK-AUTH. Nenhuma sincronização automática troca o e-mail ou o celular já gravados na PlayHub.</small></div><div id="productGroups"></div></div>
 </section>
</div>
<?php endif; ?>
<?php if($page==='catalog'): ?>
<div class="ph-modal" id="catalogAppModal" hidden>
 <div class="ph-modal-backdrop" data-close-catalog-modal></div>
 <section class="ph-modal-card ph-catalog-modal-card" role="dialog" aria-modal="true" aria-labelledby="catalogAppModalTitle">
  <header class="ph-modal-header"><div><span>Novo item</span><h2 id="catalogAppModalTitle">Adicionar ao catálogo</h2></div><button class="ph-icon-btn" type="button" data-close-catalog-modal aria-label="Fechar" title="Fechar"><i class="fa fa-times" aria-hidden="true"></i></button></header>
  <form class="ph-modal-body" id="catalogAppForm" enctype="multipart/form-data">
   <input type="hidden" name="category" id="catalogAppCategory">
   <div class="ph-catalog-category-note">Categoria: <b id="catalogAppCategoryName">—</b></div>
   <div class="ph-catalog-add-grid">
    <label class="ph-catalog-logo-field"><span>Logo do item</span><span class="ph-catalog-logo-preview" id="catalogLogoPreview"><i class="fa fa-image" aria-hidden="true"></i></span><input type="file" name="logo" id="catalogAppLogo" accept="image/png,image/jpeg,image/webp" required title="Seleciona a logo do novo item em PNG, JPG ou WebP, com até 2 MB."><small>PNG, JPG ou WebP, até 2 MB.</small></label>
    <div class="ph-catalog-add-fields">
     <label><span>Nome do item</span><input type="text" name="name" maxlength="120" autocomplete="off" required placeholder="Ex.: Netflix ou novo adicional" title="Nome que será mostrado na lista do catálogo interno."></label>
     <div class="ph-catalog-price-fields"><label><span>Preço de custo</span><input type="text" name="cost_price" inputmode="decimal" autocomplete="off" placeholder="0,00" title="Valor de custo do aplicativo para acompanhamento interno."></label><label><span>Preço de venda</span><input type="text" name="sale_price" inputmode="decimal" autocomplete="off" placeholder="0,00" title="Valor de venda do aplicativo para acompanhamento interno."></label></div>
     <label><span>Acessos ou licenças</span><input type="text" name="access_info" maxlength="120" autocomplete="off" placeholder="Ex.: 2 acessos simultâneos" title="Informe quantos acessos simultâneos ou licenças o aplicativo oferece."></label>
    </div>
   </div>
   <div class="ph-actions"><button type="button" class="ph-btn ph-btn-light" data-close-catalog-modal title="Fecha a janela sem cadastrar o item.">Cancelar</button><button type="submit" class="ph-btn" id="addCatalogApp" title="Salva o novo item, sua logo, preços e informações de acesso no catálogo interno."><i class="fa fa-plus" aria-hidden="true"></i> Adicionar item</button></div>
  </form>
 </section>
</div>
<?php endif; ?>
<div class="ph-modal" id="phConfirmModal" hidden>
 <div class="ph-modal-backdrop" data-confirm-no></div>
 <section class="ph-modal-card ph-confirm-card" role="dialog" aria-modal="true" aria-labelledby="phConfirmTitle">
  <header class="ph-modal-header"><div><span>Confirmação</span><h2 id="phConfirmTitle">Confirmar ação</h2></div><button class="ph-icon-btn" type="button" data-confirm-no aria-label="Fechar" title="Fecha esta confirmação sem executar a ação."><i class="fa fa-times" aria-hidden="true"></i></button></header>
  <div class="ph-modal-body"><p class="ph-confirm-message" id="phConfirmMessage"></p><div class="ph-actions"><button type="button" class="ph-btn ph-btn-light" data-confirm-no title="Não executa a ação e volta para a tela anterior.">Não</button><button type="button" class="ph-btn" id="phConfirmYes" title="Confirma e executa a ação informada.">Sim</button></div></div>
 </section>
</div>
<div class="ph-toast" id="phToast"></div>
<script>window.PlayHubConfig={csrf:<?=json_encode(ph_csrf())?>,api:'api.php',page:<?=json_encode($page)?>,selectedLogin:<?=json_encode($selectedLogin)?>,customerDetailBase:<?=json_encode($customerDetailBase)?>};</script>
<script src="assets/app.js?v=<?=$jsAsset?>"></script>
<?php include('../../baixo.php'); ?>
<script src="../../menu.js.hhvm"></script>
<script src="menu.js?v=<?=rawurlencode((string)@filemtime(__DIR__.'/menu.js'))?>"></script>
</body></html>
