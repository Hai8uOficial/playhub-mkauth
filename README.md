# PlayHub para MK-AUTH

Addon para cadastrar clientes do MK-AUTH na PlayHub, associar produtos e automatizar
bloqueios, liberações e cancelamentos conforme a situação do assinante.

## Instalação

Entre no terminal do MK-AUTH como `root` e execute:

```bash
wget -qO- https://raw.githubusercontent.com/Hai8uOficial/playhub-mkauth/main/install.sh | bash
```

O mesmo comando instala atualizações sem apagar as configurações existentes.

### O que é instalado

- addon em `/opt/mk-auth/admin/addons/PlayHub`;
- atalho PlayHub no menu do MK-AUTH;
- sincronização automática em `/etc/cron.d/playhub`, a cada cinco minutos;
- tabelas próprias do addon no banco do MK-AUTH.

O repositório contém somente o código do addon. Credenciais da PlayHub, clientes,
assinaturas e demais dados de cada provedor não são incluídos no pacote.

Depois da instalação, acesse `/admin/addons/PlayHub/` e informe em **Configurações da API**:

- API Base URL;
- ISP Code;
- API Key;
- API Secret.

Consulte também [as informações detalhadas do instalador](INSTALACAO.md).
