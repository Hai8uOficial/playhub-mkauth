# Instalador PlayHub para MK-AUTH

O repositório público contém somente o código do addon. Banco de dados, clientes,
credenciais da PlayHub e configurações particulares do MK-AUTH não fazem parte do pacote.

Execute como `root` no servidor MK-AUTH:

```bash
wget -qO- https://raw.githubusercontent.com/Hai8uOficial/playhub-mkauth/main/install.sh | bash
```

O mesmo comando poderá ser executado novamente para atualizar o addon.

O instalador:

- instala o addon em `/opt/mk-auth/admin/addons/PlayHub`;
- preserva as configurações e os vínculos já existentes no banco de cada servidor;
- preserva as logos personalizadas do catálogo;
- adiciona o PlayHub ao menu sem duplicar o atalho;
- configura a sincronização em `/etc/cron.d/playhub`, a cada cinco minutos;
- valida a integridade do pacote, os arquivos PHP e a conexão com o banco.

Depois da instalação, acesse `/admin/addons/PlayHub/` e preencha as credenciais em
**Configurações da API**.
