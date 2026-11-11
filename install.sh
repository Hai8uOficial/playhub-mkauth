#!/usr/bin/env bash
set -Eeuo pipefail

ADDON_NAME="PlayHub"
ADDON_VERSION="1.0.0"
GITHUB_REPOSITORY="Hai8uOficial/playhub-mkauth"
DEFAULT_PACKAGE_URL="https://raw.githubusercontent.com/${GITHUB_REPOSITORY}/main/dist/playhub-addon.tar.gz"
EXPECTED_SHA256="c351846a488c5c8c2d7214a2370ec173695a0a34d2f61d49d00f4e4503915268"

MKAUTH_ROOT="${PLAYHUB_MKAUTH_ROOT:-/opt/mk-auth}"
ADDONS_DIR="${MKAUTH_ROOT}/admin/addons"
TARGET_DIR="${ADDONS_DIR}/${ADDON_NAME}"
CRON_DIR="${PLAYHUB_CRON_DIR:-/etc/cron.d}"
CRON_FILE="${CRON_DIR}/playhub"
PACKAGE_URL="${PLAYHUB_PACKAGE_URL:-${DEFAULT_PACKAGE_URL}}"
PACKAGE_FILE="${PLAYHUB_PACKAGE_FILE:-}"
SKIP_CRON="${PLAYHUB_SKIP_CRON:-0}"
SKIP_MENU="${PLAYHUB_SKIP_MENU:-0}"

info() {
    printf '\033[1;36m[PlayHub]\033[0m %s\n' "$*"
}

fail() {
    printf '\033[1;31m[PlayHub] ERRO:\033[0m %s\n' "$*" >&2
    exit 1
}

cleanup() {
    if [[ -n "${WORK_DIR:-}" && "${WORK_DIR}" == /tmp/playhub-install.* && -d "${WORK_DIR}" ]]; then
        rm -rf -- "${WORK_DIR}"
    fi
}
trap cleanup EXIT

[[ "${EUID}" -eq 0 ]] || fail "execute o instalador como root."
[[ -d "${MKAUTH_ROOT}/admin" ]] || fail "MK-AUTH não encontrado em ${MKAUTH_ROOT}."

for command_name in tar sha256sum install find awk; do
    command -v "${command_name}" >/dev/null 2>&1 || fail "comando obrigatório não encontrado: ${command_name}."
done
if [[ -z "${PACKAGE_FILE}" ]]; then
    command -v wget >/dev/null 2>&1 || fail "wget não está instalado."
fi

PHP_BIN=""
for php_candidate in "${MKAUTH_ROOT}/php8/bin/php" /opt/php8/bin/php /usr/bin/php; do
    if [[ -x "${php_candidate}" ]]; then
        PHP_BIN="${php_candidate}"
        break
    fi
done
[[ -n "${PHP_BIN}" ]] || fail "PHP do MK-AUTH não foi encontrado."

WORK_DIR="$(mktemp -d /tmp/playhub-install.XXXXXX)"
ARCHIVE="${WORK_DIR}/playhub-addon.tar.gz"
STAGING="${WORK_DIR}/staging"
mkdir -p "${STAGING}"

if [[ -n "${PACKAGE_FILE}" ]]; then
    [[ -f "${PACKAGE_FILE}" ]] || fail "pacote local não encontrado: ${PACKAGE_FILE}."
    cp -- "${PACKAGE_FILE}" "${ARCHIVE}"
else
    info "baixando a versão ${ADDON_VERSION} do GitHub..."
    wget --https-only --secure-protocol=TLSv1_2 --timeout=30 --tries=3 -q -O "${ARCHIVE}" "${PACKAGE_URL}" \
        || fail "não foi possível baixar o pacote do GitHub."
fi

ACTUAL_SHA256="$(sha256sum "${ARCHIVE}" | awk '{print $1}')"
[[ "${ACTUAL_SHA256}" == "${EXPECTED_SHA256}" ]] \
    || fail "o pacote baixado não passou na verificação de integridade."

while IFS= read -r archive_entry; do
    case "${archive_entry}" in
        PlayHub|PlayHub/*) ;;
        *) fail "o pacote contém um caminho inválido: ${archive_entry}." ;;
    esac
    [[ "${archive_entry}" != *"../"* && "${archive_entry}" != */.. ]] \
        || fail "o pacote contém um caminho inseguro."
done < <(tar -tzf "${ARCHIVE}")

tar -xzf "${ARCHIVE}" -C "${STAGING}" --no-same-owner
SOURCE_DIR="${STAGING}/PlayHub"
for required_file in index.php api.php cron.php lib/bootstrap.php lib/schema.php assets/app.js assets/app.css menu.js VERSION; do
    [[ -f "${SOURCE_DIR}/${required_file}" ]] || fail "arquivo obrigatório ausente no pacote: ${required_file}."
done

while IFS= read -r -d '' php_file; do
    "${PHP_BIN}" -l "${php_file}" >/dev/null || fail "falha de sintaxe em ${php_file#${SOURCE_DIR}/}."
done < <(find "${SOURCE_DIR}" -type f -name '*.php' -print0)

mkdir -p "${ADDONS_DIR}"
BACKUP_DIR=""
if [[ -d "${TARGET_DIR}" && -f "${TARGET_DIR}/index.php" ]]; then
    BACKUP_ROOT="${MKAUTH_ROOT}/backups/playhub"
    mkdir -p "${BACKUP_ROOT}"
    BACKUP_DIR="${BACKUP_ROOT}/$(date +%Y%m%d-%H%M%S)"
    cp -a -- "${TARGET_DIR}" "${BACKUP_DIR}"
    info "backup da instalação atual criado em ${BACKUP_DIR}."
fi

mkdir -p "${TARGET_DIR}"
cp -a -- "${SOURCE_DIR}/." "${TARGET_DIR}/"

if [[ -e "${TARGET_DIR}/addons.class.php" && ! -L "${TARGET_DIR}/addons.class.php" ]]; then
    mv -- "${TARGET_DIR}/addons.class.php" "${TARGET_DIR}/addons.class.php.anterior"
fi
ln -sfn "${MKAUTH_ROOT}/include/addons.inc.hhvm" "${TARGET_DIR}/addons.class.php"

find "${TARGET_DIR}" -type d -exec chmod 0755 {} +
find "${TARGET_DIR}" -type f -exec chmod 0644 {} +
chmod 0755 "${TARGET_DIR}/install.sh" 2>/dev/null || true

WEB_USER="nobody"
if ! id "${WEB_USER}" >/dev/null 2>&1; then
    WEB_USER="www-data"
fi
if id "${WEB_USER}" >/dev/null 2>&1; then
    WEB_GROUP="$(id -gn "${WEB_USER}")"
    mkdir -p "${TARGET_DIR}/assets/catalog-logos"
    if chown -R "${WEB_USER}:${WEB_GROUP}" "${TARGET_DIR}/assets/catalog-logos" 2>/dev/null; then
        chmod 0775 "${TARGET_DIR}/assets/catalog-logos"
    else
        chmod 0777 "${TARGET_DIR}/assets/catalog-logos"
        info "o diretório de logos recebeu permissão de gravação compatível com este servidor."
    fi
fi

if [[ "${SKIP_MENU}" != "1" ]]; then
    ADDON_JS="${ADDONS_DIR}/addon.js"
    MENU_MARKER="PLAYHUB MENU LOADER - INSTALADOR AIO"
    touch "${ADDON_JS}"
    if ! grep -Fq "${MENU_MARKER}" "${ADDON_JS}"; then
        cp -a -- "${ADDON_JS}" "${ADDON_JS}.playhub-backup-$(date +%Y%m%d-%H%M%S)"
        {
            printf '\n// %s - INICIO\n' "${MENU_MARKER}"
            printf '%s\n' "(function(){if(document.getElementById('playhub-menu-loader'))return;var s=document.createElement('script');s.id='playhub-menu-loader';s.src='/admin/addons/PlayHub/menu.js?v=${ADDON_VERSION}';s.async=true;document.body.appendChild(s);}());"
            printf '// %s - FIM\n' "${MENU_MARKER}"
        } >> "${ADDON_JS}"
        info "atalho adicionado ao menu do MK-AUTH."
    else
        info "atalho do menu já estava instalado."
    fi
fi

if [[ "${SKIP_CRON}" != "1" ]]; then
    mkdir -p "${CRON_DIR}"
    {
        printf '%s\n' '# Sincronização automática PlayHub com os clientes vinculados do MK-AUTH.'
        printf '%s\n' '# Instalado automaticamente pelo addon PlayHub da AIO Connect.'
        printf '*/5 * * * * root %s %s/cron.php >/dev/null 2>&1\n' "${PHP_BIN}" "${TARGET_DIR}"
    } > "${CRON_FILE}"
    chmod 0644 "${CRON_FILE}"
    info "execução automática configurada a cada 5 minutos."
fi

if [[ -f "${MKAUTH_ROOT}/include/conexao.php" ]]; then
    if "${PHP_BIN}" "${TARGET_DIR}/cron.php" --check >/dev/null; then
        info "banco de dados e arquivos validados."
    else
        fail "os arquivos foram copiados, mas a validação do banco falhou.${BACKUP_DIR:+ Backup: ${BACKUP_DIR}}"
    fi
fi

info "instalação concluída com sucesso."
printf '\nAcesse: /admin/addons/PlayHub/\n'
printf 'Depois, informe a URL da API, ISP Code, API Key e API Secret em Configurações da API.\n'
