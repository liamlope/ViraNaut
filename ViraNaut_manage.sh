#!/usr/bin/env bash
set -e

# ============================================================
#  ViraNaut — Backup / Manage (Telegram VPN Bot + Admin Panel)
#  Auto-detect: /var/www/html/viranaut + legacy paths
# ============================================================

INSTALL_BOT_DIR="/var/www/html/viranaut"
LEGACY_VIRANAUT_DIR="/var/www/html/viranautconfig"
LEGACY_MIRZA_DIR="/var/www/html/mirzaprobotconfig"
LEGACY_PROJECT_DIR="/var/www/mirza_pro"
ALT_HTML_BOT_DIR="/var/www/html/mirzabotconfig"
VIRANAUT_STATE_FILE="/root/.viranaut_manage_active_dir"
MIRZA_STATE_FILE="/root/.mirza_manage_active_dir"
VIRANAUT_MANAGE_VERSION="2.2.8-ViraNaut"
MIRZA_MANAGE_VERSION="$VIRANAUT_MANAGE_VERSION"
VIRANAUT_GITHUB_REPO="${VIRANAUT_GITHUB_REPO:-https://github.com/liamlope/ViraNaut.git}"
VIRANAUT_GITHUB_BRANCH="${VIRANAUT_GITHUB_BRANCH:-main}"
VIRANAUT_GITHUB_PAGE="${VIRANAUT_GITHUB_PAGE:-https://github.com/liamlope/ViraNaut}"
VIRANAUT_BACKUP_PREFIX="viranaut_backup"
VIRANAUT_PREUPDATE_PREFIX="viranaut_preupdate"
VIRANAUT_PREUPDATE_KEEP=3
VIRANAUT_VHOST_GENERIC="viranaut-pro.conf"
VIRANAUT_VHOST_LEGACY="mirza-pro.conf"
VIRANAUT_LOG_ERROR="viranaut_error.log"
VIRANAUT_LOG_ACCESS="viranaut_access.log"

KNOWN_BOT_DIRS=(
  "$INSTALL_BOT_DIR"
  "$LEGACY_VIRANAUT_DIR"
  "$LEGACY_MIRZA_DIR"
  "$ALT_HTML_BOT_DIR"
  "$LEGACY_PROJECT_DIR"
)

# Active install — set by resolve_project_paths()
PROJECT_DIR=""
CONFIG_FILE=""
MIRZA_ALL_INSTALLS=()
BACKUP_DIR="/root/viranaut_backups"
CRON_MARKER="# viranaut_cron"

# -------------------- Colors --------------------
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
BOLD='\033[1m'
NC='\033[0m'

# -------------------- Helpers --------------------
msg()  { echo -e "${GREEN}==> ${NC}${BOLD}$1${NC}"; }
warn() { echo -e "${YELLOW}[!] $1${NC}"; }
err()  { echo -e "${RED}[ERROR] $1${NC}"; }

# a2ensite prints "Site X already enabled" on stdout — must not pollute $(...) captures
mirza_a2ensite()  { a2ensite "$@" >/dev/null 2>&1 || true; }
mirza_a2dissite() { a2dissite "$@" >/dev/null 2>&1 || true; }

# Strip accidental stdout (a2ensite / status lines) from command substitutions
mirza_sanitize_bot_dir() {
  local raw="$1" line best=""
  while IFS= read -r line || [ -n "$line" ]; do
    line="${line//$'\r'/}"
    if [[ "$line" =~ ^/[a-zA-Z0-9._/-]+$ ]]; then
      best="${line%/}"
    fi
  done <<< "$raw"
  if [ -n "$best" ]; then
    printf '%s' "$best"
  elif [ -d "$INSTALL_BOT_DIR" ]; then
    printf '%s' "${INSTALL_BOT_DIR%/}"
  else
    printf '%s' "${raw%%$'\n'*}"
  fi
}
line() { echo -e "${CYAN}────────────────────────────────────────────${NC}"; }

check_root() {
  if [ "$(id -u)" -ne 0 ]; then
    err "Please run as root:  sudo bash $0"
    exit 1
  fi
}

# Valid ViraNaut / legacy Mirza bot directory: config.php + index.php
mirza_is_valid_bot_dir() {
  local d="${1%/}"
  [ -n "$d" ] && [ -f "$d/config.php" ] && [ -f "$d/index.php" ]
}

# List every detected installation (one path per line, priority order)
mirza_discover_installations() {
  local seen="|" d
  for d in "${KNOWN_BOT_DIRS[@]}"; do
    d="${d%/}"
    if mirza_is_valid_bot_dir "$d" && [[ "$seen" != *"|$d|"* ]]; then
      echo "$d"
      seen="${seen}${d}|"
    fi
  done
  for d in /var/www/html/*/; do
    [ -d "$d" ] || continue
    d="${d%/}"
    if mirza_is_valid_bot_dir "$d" && [[ "$seen" != *"|$d|"* ]]; then
      echo "$d"
      seen="${seen}${d}|"
    fi
  done
  for d in /var/www/*/; do
    [ -d "$d" ] || continue
    d="${d%/}"
    [[ "$d" == "/var/www/html" ]] && continue
    if mirza_is_valid_bot_dir "$d" && [[ "$seen" != *"|$d|"* ]]; then
      echo "$d"
      seen="${seen}${d}|"
    fi
  done
}

mirza_save_active_dir() {
  local d="${1%/}"
  echo "$d" >"$VIRANAUT_STATE_FILE"
  echo "$d" >"$MIRZA_STATE_FILE"
}

mirza_load_saved_active_dir() {
  local state_file="$VIRANAUT_STATE_FILE"
  [ -f "$state_file" ] || state_file="$MIRZA_STATE_FILE"
  if [ ! -f "$state_file" ]; then
    return 1
  fi
  local p
  p=$(tr -d '\r\n' <"$state_file")
  p="${p%/}"
  if mirza_is_valid_bot_dir "$p"; then
    echo "$p"
    return 0
  fi
  return 1
}

mirza_list_installations() {
  local i=1 d
  line
  echo -e "  ${CYAN}Detected ViraNaut / legacy Mirza installations:${NC}"
  echo ""
  if [ ${#MIRZA_ALL_INSTALLS[@]} -eq 0 ]; then
    echo "    (none — no config.php + index.php found)"
    echo ""
    echo -e "  ${CYAN}Common paths checked:${NC}"
    for d in "${KNOWN_BOT_DIRS[@]}"; do
      echo "    • $d"
    done
    echo "    • /var/www/html/*"
    echo ""
    return 1
  fi
  for d in "${MIRZA_ALL_INSTALLS[@]}"; do
    if [ "$d" = "$PROJECT_DIR" ]; then
      echo -e "    ${BOLD}${GREEN}$i)${NC} ${BOLD}$d${NC}  ${GREEN}(active)${NC}"
    else
      echo -e "    ${BOLD}$i)${NC} $d"
    fi
    i=$((i + 1))
  done
  echo ""
  return 0
}

# Pick active bot path from discovered installs (+ saved state file)
resolve_project_paths() {
  MIRZA_ALL_INSTALLS=()
  local d
  while IFS= read -r d; do
    [ -n "$d" ] && MIRZA_ALL_INSTALLS+=("$d")
  done < <(mirza_discover_installations)

  if d=$(mirza_load_saved_active_dir 2>/dev/null); then
    PROJECT_DIR="$d"
  elif [ ${#MIRZA_ALL_INSTALLS[@]} -ge 1 ]; then
    PROJECT_DIR="${MIRZA_ALL_INSTALLS[0]}"
  else
    PROJECT_DIR="$INSTALL_BOT_DIR"
  fi
  CONFIG_FILE="$PROJECT_DIR/config.php"
}

# True when a working bot install exists (canonical path or any discovered install)
viranaut_is_installed() {
  resolve_project_paths
  if mirza_is_valid_bot_dir "$INSTALL_BOT_DIR"; then
    return 0
  fi
  if [ -f "$CONFIG_FILE" ] && [ -f "$PROJECT_DIR/index.php" ]; then
    return 0
  fi
  return 1
}

# Shown when user runs install but bot is already on the server
viranaut_already_installed_prompt() {
  resolve_project_paths
  line
  warn "ViraNaut is already installed."
  if [ -f "$CONFIG_FILE" ]; then
    local _domain _bot
    _domain=$(read_php_var "domainhosts")
    _domain=$(mirza_normalize_domainhosts "$_domain")
    _bot=$(mirza_normalize_bot_username "$(read_php_var "usernamebot")")
    echo -e "  ${GREEN}●${NC} @${_bot}  —  ${_domain}"
    echo -e "  ${CYAN}Path:${NC} $PROJECT_DIR"
  else
    echo -e "  ${CYAN}Path:${NC} $INSTALL_BOT_DIR"
  fi
  echo ""
  echo -e "  ${BOLD}1)${NC} Update from GitHub"
  echo -e "  ${BOLD}2)${NC} Restart full (MySQL + Apache + webhook)"
  echo -e "  ${BOLD}3)${NC} Open main menu"
  echo -e "  ${BOLD}0)${NC} Exit"
  echo ""
  read -p "  Select [0-3]: " _ac
  case "$_ac" in
    1) do_update_bot; return 0 ;;
    2) do_restart_full; return 0 ;;
    3) return 2 ;;
    0) exit 0 ;;
    *)
      warn "Invalid option."
      return 2
      ;;
  esac
}

# Menu: choose which installation manage.sh should use
do_select_bot_path() {
  line
  msg "Select active bot installation path"
  resolve_project_paths
  if ! mirza_list_installations; then
    return 1
  fi
  if [ ${#MIRZA_ALL_INSTALLS[@]} -eq 1 ]; then
    mirza_save_active_dir "${MIRZA_ALL_INSTALLS[0]}"
    PROJECT_DIR="${MIRZA_ALL_INSTALLS[0]}"
    CONFIG_FILE="$PROJECT_DIR/config.php"
    msg "Only one install found — set active: $PROJECT_DIR"
    return 0
  fi
  echo -e "  ${CYAN}Enter number to manage (backup/update/configure use this path):${NC}"
  read -p "  Choice: " _pick
  if ! [[ "$_pick" =~ ^[0-9]+$ ]] || [ "$_pick" -lt 1 ] || [ "$_pick" -gt "${#MIRZA_ALL_INSTALLS[@]}" ]; then
    err "Invalid choice."
    return 1
  fi
  PROJECT_DIR="${MIRZA_ALL_INSTALLS[$((_pick - 1))]}"
  CONFIG_FILE="$PROJECT_DIR/config.php"
  mirza_save_active_dir "$PROJECT_DIR"
  echo -e "  ${GREEN}✓${NC} Active path: ${BOLD}$PROJECT_DIR${NC}"
  echo ""
  return 0
}

mirza_vhost_use_domain_conf() {
  [[ "${PROJECT_DIR%/}" == /var/www/html/* ]]
}

# Fix Apache vhosts still pointing at legacy paths (mirza_pro, …)
viranaut_ensure_apache_documentroot() {
  local bot_dir="${1%/}"
  local domain="${2:-}"
  local cfg="$bot_dir/config.php"

  [ -f "$cfg" ] || return 0
  [ -z "$domain" ] && domain=$(mirza_normalize_domainhosts "$(read_php_var "domainhosts" "$cfg")")

  local legacy_paths=(
    "/var/www/mirza_pro"
    "/var/www/html/mirzaprobotconfig"
    "/var/www/html/mirzabotconfig"
    "/var/www/html/viranautconfig"
  )
  local f old fixed=0

  for f in /etc/apache2/sites-available/*.conf; do
    [ -f "$f" ] || continue
    for old in "${legacy_paths[@]}"; do
      [ "$old" = "$bot_dir" ] && continue
      if grep -qF "$old" "$f" 2>/dev/null; then
        sed -i "s|${old}|${bot_dir}|g" "$f"
        mirza_sanitize_vhost_conf "$f"
        fixed=1
        echo -e "  ${GREEN}✓${NC} Patched vhost $(basename "$f"): ${old} → ${bot_dir}" >&2
      fi
    done
    if [ -n "$domain" ] && grep -qiE "ServerName[[:space:]]+${domain}([[:space:]]|$)" "$f" 2>/dev/null; then
      if ! grep -qF "DocumentRoot $bot_dir" "$f" 2>/dev/null; then
        sed -i "s|^[[:space:]]*DocumentRoot.*|    DocumentRoot ${bot_dir}|" "$f"
        mirza_sanitize_vhost_conf "$f"
        fixed=1
        echo -e "  ${GREEN}✓${NC} DocumentRoot → $bot_dir in $(basename "$f")" >&2
      fi
    fi
  done

  if [ -n "$domain" ]; then
    mirza_write_http_vhost "$domain" "$bot_dir" >/dev/null
    mirza_a2ensite "${domain}.conf"
    mirza_rewrite_vhost_documentroot "$domain" "$bot_dir"
    mirza_ensure_ssl_vhost "$domain" "$bot_dir"
    fixed=1
  fi

  viranaut_disable_stale_vhosts "$bot_dir" "$domain"
  if [ "$fixed" -eq 1 ]; then
    apache2ctl configtest >/dev/null 2>&1 && systemctl reload apache2 2>/dev/null || mirza_restart_apache || true
  fi
}

# Disable Apache sites still pointing at deleted legacy folders (mirza_pro, …)
viranaut_disable_stale_vhosts() {
  local bot_dir="${1%/}"
  local domain="${2:-}"
  local f base docroot
  local legacy_names=(
    mirza-pro.conf
    mirza-pro-le-ssl.conf
  )
  local stale_roots=(
    "/var/www/mirza_pro"
    "/var/www/html/mirzaprobotconfig"
    "/var/www/html/mirzabotconfig"
    "/var/www/html/viranautconfig"
  )

  for base in "${legacy_names[@]}"; do
    if [ -e "/etc/apache2/sites-enabled/$base" ]; then
      mirza_a2dissite "$base" 2>/dev/null || true
      rm -f "/etc/apache2/sites-enabled/$base"
      echo -e "  ${YELLOW}●${NC} Disabled legacy vhost: $base" >&2
    fi
  done

  if [ -n "$domain" ]; then
    for f in /etc/apache2/sites-enabled/*"${domain}"*.conf; do
      [ -f "$f" ] || continue
      base=$(basename "$f")
      case "$base" in
        "${domain}.conf"|"${domain}-ssl.conf"|"${domain}-le-ssl.conf")
          continue
          ;;
      esac
      if grep -qiE "ServerName[[:space:]]+${domain}" "$f" 2>/dev/null; then
        mirza_a2dissite "$base" 2>/dev/null || true
        rm -f "/etc/apache2/sites-enabled/$base"
        echo -e "  ${YELLOW}●${NC} Disabled duplicate vhost: $base" >&2
      fi
    done
  fi

  for f in /etc/apache2/sites-enabled/*.conf; do
    [ -f "$f" ] || continue
    docroot=$(grep -i DocumentRoot "$f" 2>/dev/null | head -1 | awk '{print $2}')
    [ -n "$docroot" ] || continue
    docroot="${docroot%/}"
    [ "$docroot" = "$bot_dir" ] && continue
    local stale
    for stale in "${stale_roots[@]}"; do
      if [ "$docroot" = "$stale" ] || grep -qF "$stale" "$f" 2>/dev/null; then
        base=$(basename "$f")
        mirza_a2dissite "$base" 2>/dev/null || true
        rm -f "/etc/apache2/sites-enabled/$base"
        echo -e "  ${YELLOW}●${NC} Disabled stale vhost: $base (was $docroot)" >&2
        break
      fi
    done
  done
}

viranaut_apache_log_file() {
  local kind="$1"
  local domain="${2:-}"
  local candidates=()

  if [ "$kind" = error ]; then
    [ -n "$domain" ] && candidates+=("/var/log/apache2/${domain}-error.log")
    candidates+=("/var/log/apache2/$VIRANAUT_LOG_ERROR" "/var/log/apache2/mirza_error.log" "/var/log/apache2/error.log")
  else
    [ -n "$domain" ] && candidates+=("/var/log/apache2/${domain}-access.log")
    candidates+=("/var/log/apache2/$VIRANAUT_LOG_ACCESS" "/var/log/apache2/mirza_access.log" "/var/log/apache2/access.log")
  fi

  local p
  for p in "${candidates[@]}"; do
    [ -f "$p" ] && printf '%s' "$p" && return 0
  done
  return 1
}

# Move bot to /var/www/html/viranaut when installed elsewhere (mirza_pro, viranautconfig, …)
viranaut_relocate_to_canonical_path() {
  local src="${1%/}"
  local dest="${INSTALL_BOT_DIR%/}"
  local domain cfg="$src/config.php"

  if [ "$src" = "$dest" ]; then
    cfg="$dest/config.php"
    domain=$(mirza_normalize_domainhosts "$(read_php_var "domainhosts" "$cfg")")
    viranaut_ensure_apache_documentroot "$dest" "$domain" >&2
    printf '%s' "$dest"
    return 0
  fi

  if ! mirza_is_valid_bot_dir "$src"; then
    warn "Skip relocate: invalid bot dir $src" >&2
    printf '%s' "$src"
    return 1
  fi

  msg "Relocating bot → $dest (from $src) ..." >&2
  mkdir -p "$dest"
  if command -v rsync >/dev/null 2>&1; then
    rsync -a "$src"/ "$dest"/
  else
    find "$dest" -mindepth 1 -maxdepth 1 ! -name 'config.php' -exec rm -rf {} + 2>/dev/null || true
    cp -a "$src"/. "$dest"/
  fi

  chown -R www-data:www-data "$dest"
  find "$dest" -type d -exec chmod 755 {} \;
  find "$dest" -type f -exec chmod 644 {} \;
  [ -f "$dest/config.php" ] && chmod 640 "$dest/config.php"

  cfg="$dest/config.php"
  domain=$(mirza_normalize_domainhosts "$(read_php_var "domainhosts" "$cfg")")
  viranaut_ensure_apache_documentroot "$dest" "$domain" >&2

  local f
  for f in "/etc/apache2/sites-available/$VIRANAUT_VHOST_GENERIC" \
           "/etc/apache2/sites-available/$VIRANAUT_VHOST_LEGACY"; do
    [ -f "$f" ] || continue
    if grep -qF "$src" "$f" 2>/dev/null; then
      sed -i "s|${src}|${dest}|g" "$f"
      mirza_sanitize_vhost_conf "$f"
    fi
  done

  local TMP_CRON
  TMP_CRON=$(mktemp)
  crontab -l 2>/dev/null | sed "s|${src}|${dest}|g" >"$TMP_CRON" || true
  crontab "$TMP_CRON" 2>/dev/null || true
  rm -f "$TMP_CRON"

  mirza_save_active_dir "$dest"

  if [ -d "$src" ] && [ "$src" != "$dest" ]; then
    rm -rf "$src"
    echo -e "  ${GREEN}✓${NC} Removed old install path: $src" >&2
  fi

  echo -e "  ${GREEN}✓${NC} Canonical install path: $dest" >&2
  printf '%s' "$dest"
  return 0
}

# --- Local package (mirzabot-0.1.5.zip / .tar.gz next to this script) ---
mirza_manage_script_dir() {
  cd "$(dirname "${BASH_SOURCE[0]:-$0}")" && pwd
}

# Telegram bot username: trim spaces, strip leading @ (user may type @bot or bot)
mirza_normalize_bot_username() {
  local u="${1//[[:space:]]/}"
  u="${u#@}"
  printf '%s' "$u"
}

# install.sh / mirza_pro expect $domainhosts = hostname only (no scheme). PHP builds "https://$domainhosts/..."
# Strips leading/trailing whitespace, CR (Windows paste), and all internal whitespace (domains should not contain spaces)
mirza_normalize_domainhosts() {
  local h="$1"
  h="${h//$'\r'/}"
  h="${h//[[:space:]]/}"
  h="${h#https://}"
  h="${h#http://}"
  h="${h%/}"
  printf '%s' "$h"
}

# Same file can match mirzabot*.zip and *.zip — dedupe by canonical path
mirza_find_local_packages() {
  local dir="$1"
  local f base canon
  local -A _mirza_pkg_seen=()
  shopt -s nullglob
  for f in \
    "$dir"/ViraNaut*.zip "$dir"/ViraNaut*.tar.gz "$dir"/viranaut*.zip "$dir"/viranaut*.tar.gz \
    "$dir"/mirzabot*.zip "$dir"/mirzabot*.tar.gz "$dir"/mirzabot*.tgz \
    "$dir"/mirza_pro*.zip "$dir"/mirza_pro*.tar.gz \
    "$dir"/mirzaprobot*.zip "$dir"/mirzaprobot*.tar.gz \
    "$dir"/*.zip "$dir"/*.tar.gz "$dir"/*.tgz; do
    [ -f "$f" ] || continue
    base=$(basename "$f" | tr '[:upper:]' '[:lower:]')
    if [[ "$base" == viranaut_manage* ]] || [[ "$base" == mirza_manage* ]] || [[ "$base" == *backup* ]]; then
      continue
    fi
    if [[ "$base" =~ viranaut|mirzabot|mirza.pro|mirza_pro|mirzapanel|mirzapro ]]; then
      canon=$(readlink -f "$f" 2>/dev/null || printf '%s' "$f")
      if [[ -n "${_mirza_pkg_seen[$canon]:-}" ]]; then
        continue
      fi
      _mirza_pkg_seen[$canon]=1
      echo "$f"
    fi
  done
  shopt -u nullglob
}

mirza_guess_install_dir_from_package() {
  local pkg="$1"
  local base
  base=$(basename "$pkg" | tr '[:upper:]' '[:lower:]')
  if [[ "$base" =~ viranaut ]]; then
    echo "$INSTALL_BOT_DIR"
  elif [[ "$base" =~ mirzaprobotconfig|mirzapro ]]; then
    echo "$LEGACY_MIRZA_DIR"
  elif [[ "$base" =~ mirzabotconfig ]]; then
    echo "$ALT_HTML_BOT_DIR"
  elif [[ "$base" =~ mirza.pro|mirza_pro ]]; then
    echo "$LEGACY_PROJECT_DIR"
  else
    echo "$ALT_HTML_BOT_DIR"
  fi
}

mirza_extract_package() {
  local archive="$1" dest="$2"
  mkdir -p "$dest"
  case "$archive" in
    *.zip)
      unzip -q -o "$archive" -d "$dest" || return 1
      ;;
    *.tar.gz|*.tgz)
      tar -xzf "$archive" -C "$dest" || return 1
      ;;
    *)
      err "Unsupported archive: $archive"
      return 1
      ;;
  esac
  local inner
  inner=$(find "$dest" -mindepth 1 -maxdepth 1 -type d | head -n 1)
  if [ -z "$inner" ]; then
    err "Archive is empty or invalid."
    return 1
  fi
  # Single top folder (e.g. mirzabot-0.1.5/) → use its contents
  if [ "$(find "$dest" -mindepth 1 -maxdepth 1 | wc -l)" -eq 1 ] && [ -f "$inner/index.php" ]; then
    echo "$inner"
  elif [ -f "$dest/index.php" ]; then
    echo "$dest"
  else
    echo "$inner"
  fi
}

# --- GitHub (liamlope/ViraNaut) — clone / pull for install & update ---
viranaut_ensure_git() {
  command -v git >/dev/null 2>&1 && return 0
  msg "Installing git ..."
  apt-get install -y git >/dev/null 2>&1 || return 1
}

# Git refuses pull as root when repo is owned by www-data
viranaut_git_trust_dir() {
  local dir="${1%/}"
  git config --global --add safe.directory '*' 2>/dev/null || true
  [ -n "$dir" ] && git config --global --add safe.directory "$dir" 2>/dev/null || true
}

# Run git in bot dir as repo owner (www-data) — avoids dubious ownership
viranaut_git_in_bot_dir() {
  local bot_dir="${1%/}"
  shift
  local owner="www-data"

  viranaut_git_trust_dir "$bot_dir"
  [ -d "$bot_dir/.git" ] || return 1

  if [ -f "$bot_dir/.git/config" ]; then
    owner=$(stat -c '%U' "$bot_dir/.git" 2>/dev/null) || owner="www-data"
  fi

  local git_home="/var/www"
  [ -d "$git_home" ] || git_home="/tmp"

  if [ -n "$owner" ] && [ "$owner" != "root" ] && id "$owner" >/dev/null 2>&1; then
    sudo -u "$owner" env HOME="$git_home" GIT_TERMINAL_PROMPT=0 \
      git -C "$bot_dir" -c "safe.directory=${bot_dir}" -c "safe.directory=*" "$@"
  else
    git -C "$bot_dir" -c "safe.directory=${bot_dir}" -c "safe.directory=*" "$@"
  fi
}

viranaut_git() {
  local dir="${1%/}"
  shift
  viranaut_git_in_bot_dir "$dir" "$@"
}

viranaut_is_bot_source() {
  local d="${1%/}"
  [ -f "$d/index.php" ] && { [ -f "$d/version" ] || [ -f "$d/config.sample.php" ] || [ -f "$d/config.php" ]; }
}

viranaut_link_cli() {
  local src self
  self="${BASH_SOURCE[0]:-$0}"
  if command -v readlink >/dev/null 2>&1; then
    self=$(readlink -f "$self" 2>/dev/null) || self="$self"
  fi

  # /root/ViraNaut_manage.sh is canonical — never overwrite it from a stale /usr/local/bin copy
  if [ -f "/root/ViraNaut_manage.sh" ]; then
    src="/root/ViraNaut_manage.sh"
  elif [ -f "$self" ]; then
    cp -f "$self" /root/ViraNaut_manage.sh 2>/dev/null || true
    src="/root/ViraNaut_manage.sh"
  else
    return 0
  fi

  chmod +x "$src" 2>/dev/null || true
  ln -sf "$src" /root/ViraNaut_manage.sh 2>/dev/null || true
  ln -sf "$src" /usr/local/bin/viranaut 2>/dev/null || true
  ln -sf "$src" /usr/local/bin/mirza 2>/dev/null || true
  ln -sf "$src" /usr/local/bin/ViraNaut_manage.sh 2>/dev/null || true
}

# Pull latest manage script from GitHub (fixes servers stuck on old /usr/local/bin copy)
viranaut_self_update_manage_script() {
  local dest="/root/ViraNaut_manage.sh"
  local url tmp old_ver new_ver branch
  branch="${VIRANAUT_GITHUB_BRANCH:-main}"
  url="https://raw.githubusercontent.com/liamlope/ViraNaut/${branch}/ViraNaut_manage.sh"

  command -v curl >/dev/null 2>&1 || return 1
  tmp=$(mktemp)
  if ! curl -fsSL "$url" -o "$tmp" 2>/dev/null; then
    rm -f "$tmp"
    return 1
  fi
  [ -s "$tmp" ] || { rm -f "$tmp"; return 1; }
  head -1 "$tmp" | grep -q '#!/usr/bin/env bash' || { rm -f "$tmp"; return 1; }
  grep -q 'VIRANAUT_MANAGE_VERSION' "$tmp" || { rm -f "$tmp"; return 1; }

  if [ -f "$dest" ] && cmp -s "$tmp" "$dest" 2>/dev/null; then
    rm -f "$tmp"
    viranaut_link_cli
    return 0
  fi

  old_ver=$(grep -m1 'VIRANAUT_MANAGE_VERSION=' "$dest" 2>/dev/null | sed -n 's/.*"\([^"]*\)".*/\1/p') || old_ver="?"
  new_ver=$(grep -m1 'VIRANAUT_MANAGE_VERSION=' "$tmp" | sed -n 's/.*"\([^"]*\)".*/\1/p')
  chmod +x "$tmp"
  mv -f "$tmp" "$dest"
  viranaut_link_cli
  echo -e "  ${GREEN}✓${NC} Manage script updated: ${old_ver} → ${new_ver}" >&2
  return 0
}

viranaut_sync_manage_script_from_bot() {
  local bot_dir="${1%/}"
  if [ -f "$bot_dir/ViraNaut_manage.sh" ]; then
    cp -f "$bot_dir/ViraNaut_manage.sh" /root/ViraNaut_manage.sh 2>/dev/null || true
    chmod +x /root/ViraNaut_manage.sh 2>/dev/null || true
  fi
  viranaut_link_cli
}

viranaut_update_from_github() {
  local bot_dir="${1%/}"
  local branch="${VIRANAUT_GITHUB_BRANCH:-main}"

  viranaut_ensure_git || return 1
  [ -d "$bot_dir/.git" ] || return 1

  msg "Git pull — ${VIRANAUT_GITHUB_PAGE} (${branch}) ..."
  viranaut_git_in_bot_dir "$bot_dir" fetch origin "$branch" \
    || viranaut_git_in_bot_dir "$bot_dir" fetch origin || return 1
  viranaut_git_in_bot_dir "$bot_dir" checkout "$branch" 2>/dev/null || true
  viranaut_git_in_bot_dir "$bot_dir" pull --ff-only origin "$branch" \
    || viranaut_git_in_bot_dir "$bot_dir" pull --ff-only || return 1
  echo -e "  ${GREEN}✓${NC} Git pull completed"
  viranaut_git_reconcile_panel "$bot_dir"
  return 0
}

# Clone repo to temp; sets tmp dir in $1 and source path in $2 (no stdout — safe for callers)
viranaut_github_clone_staging() {
  local _tmp_ref="${1:-}"
  local _src_ref="${2:-}"
  local branch="${VIRANAUT_GITHUB_BRANCH:-main}"
  local tmp inner repo_dir

  viranaut_ensure_git || return 1
  tmp=$(mktemp -d)
  repo_dir="$tmp/repo"

  msg "Fetching ${VIRANAUT_GITHUB_PAGE} (${branch}) ..." >&2
  if ! git clone --depth 1 -b "$branch" "$VIRANAUT_GITHUB_REPO" "$repo_dir" 2>/dev/null; then
    rm -rf "$tmp"
    tmp=$(mktemp -d)
    repo_dir="$tmp/repo"
    git clone --depth 1 "$VIRANAUT_GITHUB_REPO" "$repo_dir" || {
      rm -rf "$tmp"
      return 1
    }
  fi

  if viranaut_is_bot_source "$repo_dir"; then
    inner="$repo_dir"
  else
    inner=$(find "$repo_dir" -mindepth 1 -maxdepth 1 -type d 2>/dev/null | head -1)
    if [ -z "$inner" ] || ! viranaut_is_bot_source "$inner"; then
      rm -rf "$tmp"
      return 1
    fi
  fi

  [ -n "$_tmp_ref" ] && printf -v "$_tmp_ref" '%s' "$tmp"
  [ -n "$_src_ref" ] && printf -v "$_src_ref" '%s' "$inner"
  return 0
}

viranaut_github_staging_cleanup() {
  local tmp="${1:-}"
  [ -n "$tmp" ] && [ -d "$tmp" ] && rm -rf "$tmp"
}

# Replace bot tree from SRC; keeps config.php (+ vendor fallback)
viranaut_swap_bot_files_preserve_config() {
  local BOT_DIR="${1%/}" SRC_DIR="${2%/}"
  local CONFIG_PATH="$BOT_DIR/config.php"
  local TEMP_CONFIG="/root/mirza_local_update_config_backup.php"
  local TEMP_VENDOR="/root/mirza_local_update_vendor_backup"
  local TEMP_PANEL_INC="/root/viranaut_panel_inc_backup"

  [ -f "$CONFIG_PATH" ] || { err "config.php missing at $CONFIG_PATH"; return 1; }
  SRC_DIR="${SRC_DIR//$'\r'/}"
  SRC_DIR="${SRC_DIR//$'\n'/}"
  [ -d "$SRC_DIR" ] || { err "Invalid update source: $SRC_DIR"; return 1; }

  cp "$CONFIG_PATH" "$TEMP_CONFIG" || return 1

  if [ -d "$BOT_DIR/panel/inc" ]; then
    rm -rf "$TEMP_PANEL_INC"
    cp -a "$BOT_DIR/panel/inc" "$TEMP_PANEL_INC" 2>/dev/null || true
  fi

  if [ -f "$BOT_DIR/vendor/autoload.php" ]; then
    rm -rf "$TEMP_VENDOR"
    cp -a "$BOT_DIR/vendor" "$TEMP_VENDOR" 2>/dev/null || warn "vendor backup failed"
  fi

  msg "Replacing files under $BOT_DIR ..."
  find "$BOT_DIR" -mindepth 1 -maxdepth 1 ! -name 'config.php' -exec rm -rf {} +
  cp -a "$SRC_DIR"/. "$BOT_DIR"/

  mv "$TEMP_CONFIG" "$CONFIG_PATH" || {
    err "Failed to restore config.php"
    return 1
  }

  if [ ! -f "$BOT_DIR/vendor/autoload.php" ] && [ -f "$TEMP_VENDOR/autoload.php" ]; then
    msg "Restoring vendor/ (update source had no vendor folder) ..."
    rm -rf "$BOT_DIR/vendor"
    cp -a "$TEMP_VENDOR" "$BOT_DIR/vendor"
  fi
  if [ -d "$TEMP_PANEL_INC" ]; then
    local _pf
    for _pf in config.php brand.php vira_compat.php layout_head.php layout_foot.php nav_registry.php icons.php; do
      if [ ! -f "$BOT_DIR/panel/inc/$_pf" ] && [ -f "$TEMP_PANEL_INC/$_pf" ]; then
        mkdir -p "$BOT_DIR/panel/inc"
        cp -a "$TEMP_PANEL_INC/$_pf" "$BOT_DIR/panel/inc/$_pf" 2>/dev/null || true
      fi
    done
    if [ ! -f "$BOT_DIR/panel/inc/config.php" ]; then
      mkdir -p "$BOT_DIR/panel/inc"
      cp -a "$TEMP_PANEL_INC/." "$BOT_DIR/panel/inc/" 2>/dev/null || true
    fi
  fi
  rm -rf "$TEMP_VENDOR" "$TEMP_PANEL_INC"
  viranaut_ensure_panel_integrity "$BOT_DIR" 2>/dev/null || true
  return 0
}

# After git pull: restore tracked panel/ files + fetch any still missing from GitHub
viranaut_git_reconcile_panel() {
  local bot_dir="${1%/}"
  [ -d "$bot_dir/.git" ] || return 0
  viranaut_git_in_bot_dir "$bot_dir" checkout HEAD -- panel/ 2>/dev/null || true
  viranaut_ensure_panel_integrity "$bot_dir"
}

# Critical panel files — auto-restore from GitHub if an update removed them
viranaut_ensure_panel_integrity() {
  local BOT_DIR="${1%/}"
  local branch="${VIRANAUT_GITHUB_BRANCH:-main}"
  local base="https://raw.githubusercontent.com/liamlope/ViraNaut/${branch}"
  local restored=0 failed=0 rel path url

  local -a critical=(
    panel/inc/config.php
    panel/inc/brand.php
    panel/inc/vira_compat.php
    panel/inc/layout_head.php
    panel/inc/layout_foot.php
    panel/inc/nav_registry.php
    panel/inc/icons.php
    panel/inc/wallet_defs.php
    panel/inc/pay_settings_defs.php
    panel/login.php
    panel/check.php
    panel/ping.php
    panel/index.php
  )

  for rel in "${critical[@]}"; do
    path="$BOT_DIR/$rel"
    if [ -f "$path" ] && [ -s "$path" ]; then
      continue
    fi
    warn "Missing $rel — restoring from GitHub ..."
    mkdir -p "$(dirname "$path")"
    url="$base/$rel"
    if command -v curl >/dev/null 2>&1 \
        && curl -fsSL "$url" -o "$path" 2>/dev/null \
        && [ -s "$path" ]; then
      chown www-data:www-data "$path" 2>/dev/null || true
      chmod 644 "$path" 2>/dev/null || true
      echo -e "  ${GREEN}✓${NC} Restored $rel"
      restored=$((restored + 1))
    else
      err "Could not restore $rel"
      failed=$((failed + 1))
    fi
  done

  if [ "$failed" -gt 0 ]; then
    return 1
  fi
  [ "$restored" -gt 0 ] && echo -e "  ${GREEN}✓${NC} Panel integrity check (${restored} file(s) restored)"
  return 0
}

# Backward-compatible alias
viranaut_ensure_panel_inc_config() {
  viranaut_ensure_panel_integrity "${1%/}"
}

# Quick panel health check (files + PHP load + optional HTTPS)
viranaut_panel_smoke_test() {
  local BOT_DIR="${1%/}"
  local domain="${2:-}"
  local pinc="$BOT_DIR/panel/inc/config.php"
  local ok=1

  if [ ! -f "$BOT_DIR/panel/login.php" ]; then
    err "panel/login.php missing"
    return 1
  fi
  if [ ! -f "$pinc" ]; then
    err "panel/inc/config.php missing — panel will not load"
    return 1
  fi
  echo -e "  ${GREEN}✓${NC} panel/inc/config.php present"

  local _cli_mysqli=0
  if command -v php >/dev/null 2>&1 && php -m 2>/dev/null | grep -qi '^mysqli$'; then
    _cli_mysqli=1
  fi

  if [ "$_cli_mysqli" -eq 1 ]; then
    if sudo -u www-data php -r "require '${pinc}';" >/dev/null 2>&1; then
      echo -e "  ${GREEN}✓${NC} panel/inc/config.php loads (PHP CLI)"
    else
      local _php_err
      _php_err=$(sudo -u www-data php -r "require '${pinc}';" 2>&1 | head -3)
      warn "panel PHP CLI load failed (Apache may still work):"
      [ -n "$_php_err" ] && echo "$_php_err" | sed 's/^/    /'
    fi
  else
    echo -e "  ${CYAN}Note:${NC} PHP CLI has no mysqli — skipping CLI load test (Apache mod_php is authoritative)"
  fi

  if [ -z "$domain" ] && [ -f "$BOT_DIR/config.php" ]; then
    domain=$(mirza_normalize_domainhosts "$(read_php_var "domainhosts" "$BOT_DIR/config.php")")
  fi
  if [ -n "$domain" ]; then
    local login_code check_body
    login_code=$(curl -sk -o /dev/null -w "%{http_code}" --connect-timeout 10 "https://${domain}/panel/login.php" 2>/dev/null)
    login_code=${login_code:-000}
    echo -e "  ${CYAN}Panel URL:${NC} https://${domain}/panel/login.php → HTTP ${login_code}"
    case "$login_code" in
      200) echo -e "  ${GREEN}✓${NC} Panel reachable"; ok=1 ;;
      404) err "Panel 404 — Apache DocumentRoot wrong; run menu 8 (Auto-fix)"; ok=0 ;;
      500|502|503)
        err "Panel HTTP $login_code — PHP error"
        check_body=$(curl -sk --connect-timeout 10 "https://${domain}/panel/check.php" 2>/dev/null | head -6)
        [ -n "$check_body" ] && echo "$check_body" | sed 's/^/    /'
        echo -e "    ${CYAN}Log:${NC} tail -30 $BOT_DIR/error_log"
        ok=0
        ;;
      000) warn "Cannot reach panel URL (DNS/SSL)" ;;
      *) warn "Unexpected HTTP $login_code for panel/login.php" ;;
    esac
  fi
  [ "$ok" -eq 1 ]
}

# Restore panel config + permissions + reload Apache
viranaut_panel_fix() {
  local BOT_DIR="${1%/}"
  local domain=""

  line
  msg "Panel fix — $BOT_DIR"
  line

  [ -f "$BOT_DIR/config.php" ] || { err "No config.php"; return 1; }
  domain=$(mirza_normalize_domainhosts "$(read_php_var "domainhosts" "$BOT_DIR/config.php")")

  viranaut_ensure_panel_integrity "$BOT_DIR" || true
  mirza_ensure_bot_permissions "$BOT_DIR"
  systemctl reload apache2 2>/dev/null || true

  viranaut_panel_smoke_test "$BOT_DIR" "$domain"
}

viranaut_finish_bot_update() {
  local BOT_DIR="${1%/}"
  local CONFIG_PATH="$BOT_DIR/config.php"

  mirza_apply_php_core_fixes "$BOT_DIR"
  mirza_sync_config_domainhosts_file "$CONFIG_PATH"
  chown -R www-data:www-data "$BOT_DIR"
  find "$BOT_DIR" -type d -exec chmod 755 {} \;
  find "$BOT_DIR" -type f -exec chmod 644 {} \;
  chmod 640 "$CONFIG_PATH"

  if [ -f "$BOT_DIR/table.php" ]; then
    msg "Running table.php (database migrations) ..."
    (cd "$BOT_DIR" && MIRZA_SKIP_WEBHOOK=1 php table.php >/dev/null 2>&1) || warn "table.php had warnings (often OK)."
  fi

  viranaut_db_migrate "$BOT_DIR"
  viranaut_ensure_panel_integrity "$BOT_DIR" 2>/dev/null || true
  mirza_ensure_bot_permissions "$BOT_DIR"
  viranaut_sync_manage_script_from_bot "$BOT_DIR"
  systemctl reload apache2 2>/dev/null || true

  msg "Panel check ..."
  local _panel_domain=""
  _panel_domain=$(mirza_normalize_domainhosts "$(read_php_var "domainhosts" "$CONFIG_PATH")" 2>/dev/null || true)
  viranaut_panel_smoke_test "$BOT_DIR" "$_panel_domain" || warn "Panel issue — run: /root/ViraNaut_manage.sh panel-fix"
}

mirza_ssl_cert_exists() {
  local domain="$1"
  [ -n "$domain" ] && [ -f "/etc/letsencrypt/live/${domain}/fullchain.pem" ]
}

mirza_ssl_days_remaining() {
  local domain="$1"
  if ! mirza_ssl_cert_exists "$domain"; then
    echo "-1"
    return
  fi
  local end exp now
  end=$(openssl x509 -enddate -noout -in "/etc/letsencrypt/live/${domain}/cert.pem" 2>/dev/null | cut -d= -f2)
  exp=$(date -d "$end" +%s 2>/dev/null || echo 0)
  now=$(date +%s)
  echo $(( (exp - now) / 86400 ))
}

mirza_setup_ssl() {
  local domain="$1"
  local force_renew="${2:-0}"

  apt-get install -y certbot python3-certbot-apache >/dev/null 2>&1 || true

  if mirza_ssl_cert_exists "$domain"; then
    local days
    days=$(mirza_ssl_days_remaining "$domain")
    echo ""
    echo -e "  ${YELLOW}SSL certificate already exists for ${BOLD}$domain${NC} (~${days} days left)."
    if [ "$force_renew" = "1" ]; then
      msg "Renewing certificate ..."
      certbot renew --cert-name "$domain" --apache --non-interactive 2>/dev/null \
        || certbot --apache -d "$domain" --non-interactive --agree-tos --register-unsafely-without-email --redirect --force-renewal
    else
      read -p "  Renew certificate now? (y/n) [n]: " _ren
      _ren=${_ren,,}
      if [ "$_ren" = "y" ]; then
        msg "Renewing certificate ..."
        certbot renew --cert-name "$domain" --apache --non-interactive 2>/dev/null \
          || certbot --apache -d "$domain" --non-interactive --agree-tos --register-unsafely-without-email --redirect --force-renewal
      else
        echo -e "  ${CYAN}Keeping existing certificate.${NC}"
        return 0
      fi
    fi
  else
    echo ""
    read -p "  Install Let's Encrypt SSL for $domain? (y/n) [y]: " _ssl
    _ssl=${_ssl:-y}
    _ssl=${_ssl,,}
    if [ "$_ssl" != "y" ]; then
      warn "Skipping SSL — webhook will use http:// unless you add SSL later."
      return 1
    fi
    msg "Requesting certificate (Apache plugin) ..."
    certbot --apache -d "$domain" --non-interactive --agree-tos --register-unsafely-without-email --redirect
  fi
  return 0
}

mirza_apply_php_core_fixes() {
  local BOT_DIR="${1%/}"
  local cfg="$BOT_DIR/config.php"
  [ -f "$cfg" ] || return 0
  if ! grep -q "function_exists('select')" "$cfg" 2>/dev/null; then
    sed -i 's/^\?>//g' "$cfg" 2>/dev/null || true
    cat >>"$cfg" <<'PHP'

if (!function_exists('select')) {
    if (is_file(__DIR__ . '/function.php')) {
        require_once __DIR__ . '/function.php';
    } elseif (is_file(__DIR__ . '/functions.php')) {
        require_once __DIR__ . '/functions.php';
    }
}
PHP
  fi
  if [ -f "$BOT_DIR/function.php" ] && [ ! -f "$BOT_DIR/functions.php" ]; then
    printf '%s\n' '<?php require_once __DIR__ . "/function.php";' >"$BOT_DIR/functions.php"
  fi
}

# Rewrite $domainhosts in config.php if it has leading/trailing spaces, CR, or https:// prefix (matches install.sh)
mirza_sync_config_domainhosts_file() {
  local cfg="$1"
  local cur norm tmp
  [ -f "$cfg" ] || return 0
  cur=$(grep -E '^[[:space:]]*\$domainhosts[[:space:]]*=' "$cfg" 2>/dev/null | head -1 | sed -E "s/^[[:space:]]*\\\$domainhosts[[:space:]]*=[[:space:]]*['\"]([^'\"]*)['\"].*/\1/")
  [ -z "$cur" ] && return 0
  norm=$(mirza_normalize_domainhosts "$cur")
  [ -z "$norm" ] && return 0
  [ "$cur" = "$norm" ] && return 0
  msg "Fixing \$domainhosts in config.php (trim / normalize)"
  tmp=$(mktemp)
  awk -v n="$norm" '
    $0 ~ /^[[:space:]]*\$domainhosts[[:space:]]*=/ {
      print "$domainhosts = \047" n "\047;"
      next
    }
    { print }
  ' "$cfg" >"$tmp" && mv "$tmp" "$cfg"
}

mirza_write_fresh_config() {
  local cfg="$1" dbname="$2" dbuser="$3" dbpass="$4" token="$5" admin="$6" userbot="$7" _proto="$8" domain_raw="$9"
  local dbpass_php="${dbpass//\'/\\\'}"
  local domain
  domain=$(mirza_normalize_domainhosts "$domain_raw")
  cat >"$cfg" <<PHP
<?php
\$request_exec_timeout = 30000;
\$dbname     = '$dbname';
\$usernamedb = '$dbuser';
\$passworddb = '${dbpass_php}';
\$dbhost     = 'localhost';

\$connect = mysqli_connect(\$dbhost, \$usernamedb, \$passworddb, \$dbname);
if (\$connect->connect_error) { die("error" . \$connect->connect_error); }
mysqli_set_charset(\$connect, "utf8mb4");

\$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];
\$dsn = "mysql:host=\$dbhost;dbname=\$dbname;charset=utf8mb4";
try { \$pdo = new PDO(\$dsn, \$usernamedb, \$passworddb, \$options); } catch (\PDOException \$e) {
    error_log("Database connection failed: " . \$e->getMessage());
}

\$APIKEY      = '$token';
\$adminnumber = '$admin';
\$domainhosts = '$domain';
\$usernamebot = '$userbot';

PHP
  mirza_apply_php_core_fixes "$(dirname "$cfg")"
  chown www-data:www-data "$cfg"
  chmod 640 "$cfg"
}

mirza_restart_apache() {
  msg "Restarting Apache ..."
  if systemctl restart apache2 2>/dev/null; then
    echo -e "  ${GREEN}✓${NC} Apache restarted"
    systemctl status apache2 --no-pager -l 2>/dev/null | head -5 || true
    return 0
  fi
  warn "Could not restart apache2"
  return 1
}

# Restart Apache and refresh Telegram webhook after install/update
mirza_reload_services() {
  local BOT_DIR="${1%/}"
  mirza_restart_apache || true

  local cfg="$BOT_DIR/config.php"
  [ -f "$cfg" ] || return 0
  local token domain proto webhook
  token=$(grep -E "^\s*\\\$APIKEY\s*=" "$cfg" 2>/dev/null | head -1 | sed -E "s/.*=\s*['\"]([^'\"]+)['\"].*/\1/")
  domain=$(grep -E "^\s*\\\$domainhosts\s*=" "$cfg" 2>/dev/null | head -1 | sed -E "s/.*=\s*['\"]([^'\"]+)['\"].*/\1/")
  if [ -z "$token" ] || [ -z "$domain" ]; then
    return 0
  fi
  if [[ "$domain" == https://* ]]; then
    proto="https"
    domain="${domain#https://}"
  elif [[ "$domain" == http://* ]]; then
    proto="http"
    domain="${domain#http://}"
  else
    proto="https"
  fi
  domain="${domain%/}"
  webhook="${proto}://${domain}/index.php"
  if [ -n "${MIRZA_SKIP_WEBHOOK_REFRESH:-}" ]; then
    return 0
  fi
  msg "Refreshing Telegram webhook ..."
  if [ -n "${MIRZA_DROP_PENDING_WEBHOOK:-}" ]; then
    curl -s "https://api.telegram.org/bot${token}/deleteWebhook?drop_pending_updates=true" >/dev/null 2>&1 || true
    sleep 2
  fi
  local wh_info wh_current
  wh_info=$(curl -s "https://api.telegram.org/bot${token}/getWebhookInfo")
  wh_current=$(echo "$wh_info" | sed -n 's/.*"url"[[:space:]]*:[[:space:]]*"\([^"]*\)".*/\1/p' | head -1)
  if [ "$wh_current" = "$webhook" ] && [ -z "${MIRZA_DROP_PENDING_WEBHOOK:-}" ]; then
    echo -e "  ${GREEN}✓${NC} Webhook already set: $webhook"
    return 0
  fi
  sleep 3
  local res attempt=1 max_attempts=8
  while [ "$attempt" -le "$max_attempts" ]; do
    res=$(curl -s "https://api.telegram.org/bot${token}/setWebhook?url=${webhook}")
    if echo "$res" | grep -q '"ok":true'; then
      echo -e "  ${GREEN}✓${NC} Webhook: $webhook"
      return 0
    fi
    if echo "$res" | grep -q 'retry_after'; then
      local wait_sec
      wait_sec=$(echo "$res" | sed -n 's/.*"retry_after"[[:space:]]*:[[:space:]]*\([0-9][0-9]*\).*/\1/p' | head -1)
      wait_sec=${wait_sec:-2}
      sleep "$((wait_sec + 1))"
      attempt=$((attempt + 1))
      continue
    fi
    break
  done
  if echo "$res" | grep -qE 'Too Many Requests|error_code.:429'; then
    warn "Webhook rate-limited by Telegram (429). Bot files/Apache are OK — wait ~15s then run:"
    echo "    curl -s \"https://api.telegram.org/bot${token}/setWebhook?url=${webhook}\""
    if [ "$wh_current" = "$webhook" ] || echo "$wh_info" | grep -qF "$webhook"; then
      echo -e "  ${YELLOW}Note:${NC} Webhook may already be correct; 429 is often harmless after update."
    fi
  else
    warn "Webhook refresh failed: $res"
  fi
}

# Safe update: pre-backup → GitHub only (pull or clone deploy)
do_github_update() {
  local BOT_DIR="${1%/}"
  local AUTO_YES="${2:-0}"
  local CONFIG_PATH="$BOT_DIR/config.php"
  local UPDATE_SRC=""

  if [ ! -f "$CONFIG_PATH" ]; then
    err "No config.php at $CONFIG_PATH — use menu 1 (Install) first."
    return 1
  fi

  # Always trust bot path (fixes dubious ownership when running as root)
  git config --global --add safe.directory '*' 2>/dev/null || true
  git config --global --add safe.directory "${BOT_DIR}" 2>/dev/null || true

  # Refresh manage script from GitHub if server still runs an old /usr/local/bin copy
  if [ "${VIRANAUT_SKIP_SCRIPT_BOOTSTRAP:-0}" != "1" ] && command -v curl >/dev/null 2>&1; then
    local _ms_tmp _ms_url
    _ms_url="https://raw.githubusercontent.com/liamlope/ViraNaut/${VIRANAUT_GITHUB_BRANCH:-main}/ViraNaut_manage.sh"
    _ms_tmp=$(mktemp)
    if curl -fsSL "$_ms_url" -o "$_ms_tmp" 2>/dev/null \
        && grep -q 'viranaut_git_in_bot_dir' "$_ms_tmp" 2>/dev/null \
        && { [ ! -f /root/ViraNaut_manage.sh ] || ! cmp -s "$_ms_tmp" /root/ViraNaut_manage.sh 2>/dev/null; }; then
      chmod +x "$_ms_tmp"
      mv -f "$_ms_tmp" /root/ViraNaut_manage.sh
      ln -sf /root/ViraNaut_manage.sh /usr/local/bin/viranaut 2>/dev/null || true
      ln -sf /root/ViraNaut_manage.sh /usr/local/bin/mirza 2>/dev/null || true
      ln -sf /root/ViraNaut_manage.sh /usr/local/bin/ViraNaut_manage.sh 2>/dev/null || true
      msg "Manage script updated from GitHub — continuing ..."
      export VIRANAUT_SKIP_SCRIPT_BOOTSTRAP=1
      if [ "$AUTO_YES" = "1" ]; then
        exec /root/ViraNaut_manage.sh update -y
      else
        exec /root/ViraNaut_manage.sh update
      fi
    fi
    rm -f "$_ms_tmp"
  fi

  echo ""
  echo -e "  ${CYAN}Target:${NC} $BOT_DIR"
  echo -e "  ${CYAN}Source:${NC} ${VIRANAUT_GITHUB_PAGE} (${VIRANAUT_GITHUB_BRANCH})"
  echo -e "  ${CYAN}Backup:${NC} auto before update (keeps last ${VIRANAUT_PREUPDATE_KEEP} × ${VIRANAUT_PREUPDATE_PREFIX}_*.zip)"
  if [ "$AUTO_YES" != "1" ]; then
    read -p "  Update from GitHub? config.php + DB kept (y/n): " _go
    _go=${_go,,}
    [ "$_go" = "y" ] || { msg "Cancelled."; return 0; }
  fi

  if ! viranaut_preupdate_backup "$BOT_DIR"; then
    err "Pre-update backup failed — update aborted (nothing changed)."
    return 1
  fi

  viranaut_self_update_manage_script 2>/dev/null || true

  if [ -d "$BOT_DIR/.git" ] && viranaut_update_from_github "$BOT_DIR"; then
    UPDATE_SRC="GitHub git pull"
  else
    local gh_tmp="" gh_src=""
    if ! viranaut_github_clone_staging gh_tmp gh_src || [ -z "$gh_src" ]; then
      err "GitHub update failed — check network, git, and ${VIRANAUT_GITHUB_REPO}"
      viranaut_github_staging_cleanup "$gh_tmp"
      echo -e "  ${CYAN}Restore:${NC} ${BACKUP_DIR}/${VIRANAUT_PREUPDATE_PREFIX}_*.zip"
      return 1
    fi
    if ! viranaut_swap_bot_files_preserve_config "$BOT_DIR" "$gh_src"; then
      err "GitHub file deploy failed."
      viranaut_github_staging_cleanup "$gh_tmp"
      return 1
    fi
    viranaut_github_staging_cleanup "$gh_tmp"
    UPDATE_SRC="GitHub clone deploy"
  fi

  if [ ! -f "$BOT_DIR/vendor/autoload.php" ]; then
    err "vendor/autoload.php MISSING — restore latest pre-update ZIP from ${BACKUP_DIR}"
  fi

  viranaut_finish_bot_update "$BOT_DIR"

  line
  echo -e "  ${GREEN}${BOLD}✓ ViraNaut update complete.${NC}  Source: ${UPDATE_SRC}"
  echo -e "  ${CYAN}Bot:${NC} $BOT_DIR"
  local _upd_domain _upd_login
  _upd_domain=$(mirza_normalize_domainhosts "$(read_php_var "domainhosts" "$CONFIG_PATH")" 2>/dev/null || true)
  if [ -n "$_upd_domain" ]; then
    _upd_login=$(curl -sk -o /dev/null -w "%{http_code}" --connect-timeout 10 "https://${_upd_domain}/panel/login.php" 2>/dev/null)
    _upd_login=${_upd_login:-000}
    echo -e "  ${CYAN}Panel:${NC} https://${_upd_domain}/panel/login.php → HTTP ${_upd_login}"
    [ "$_upd_login" = "200" ] && echo -e "  ${GREEN}✓${NC} Web admin panel OK" \
      || warn "Panel not HTTP 200 — run: /root/ViraNaut_manage.sh panel-fix"
  fi
  echo -e "  ${CYAN}Restore if needed:${NC} ${BACKUP_DIR}/${VIRANAUT_PREUPDATE_PREFIX}_*.zip"
  viranaut_list_preupdate_backups 2>/dev/null || true
  echo ""
}

do_update_bot() {
  line
  resolve_project_paths
  if [ ! -f "$CONFIG_FILE" ]; then
    local legacy
    legacy=$(mirza_find_legacy_mirza_dir 2>/dev/null) || legacy=""
    if [ -n "$legacy" ]; then
      warn "Mirza found at $legacy — run Install (menu 1) to migrate to ViraNaut."
    else
      err "No installation found — use menu 1 (Install) first."
    fi
    return 1
  fi
  msg "GitHub update — ${BOLD}$PROJECT_DIR${NC}"
  do_github_update "$PROJECT_DIR" "${VIRANAUT_AUTO_YES:-0}"
}

# Legacy Mirza path (not ViraNaut)
mirza_find_legacy_mirza_dir() {
  local d
  for d in "$ALT_HTML_BOT_DIR" "$LEGACY_MIRZA_DIR" "$LEGACY_PROJECT_DIR" "$LEGACY_VIRANAUT_DIR"; do
    d="${d%/}"
    [ "${d}" = "${INSTALL_BOT_DIR%/}" ] && continue
    if mirza_is_valid_bot_dir "$d"; then
      echo "$d"
      return 0
    fi
  done
  return 1
}

viranaut_clone_github_into() {
  local BOT_DIR="${1%/}"
  local branch="${VIRANAUT_GITHUB_BRANCH:-main}"

  mkdir -p "$(dirname "$BOT_DIR")"
  rm -rf "$BOT_DIR"
  viranaut_ensure_git || return 1
  msg "Cloning ${VIRANAUT_GITHUB_PAGE} → $BOT_DIR ..."
  if ! git clone --depth 1 -b "$branch" "$VIRANAUT_GITHUB_REPO" "$BOT_DIR" 2>/dev/null; then
    rm -rf "$BOT_DIR"
    git clone --depth 1 "$VIRANAUT_GITHUB_REPO" "$BOT_DIR" || return 1
  fi
  viranaut_is_bot_source "$BOT_DIR" || return 1
  chown -R www-data:www-data "$BOT_DIR"
  find "$BOT_DIR" -type d -exec chmod 755 {} \;
  find "$BOT_DIR" -type f -exec chmod 644 {} \;
  return 0
}

# Mirza → ViraNaut: keep DB + bot credentials, deploy from GitHub
do_migrate_mirza_to_viranaut() {
  local OLD_DIR="${1%/}"
  local BOT_DIR="${INSTALL_BOT_DIR%/}"
  local OLD_CFG="$OLD_DIR/config.php"

  msg "Mirza detected: ${BOLD}$OLD_DIR${NC} → migrating to ViraNaut"
  viranaut_preupdate_backup "$OLD_DIR" 2>/dev/null || warn "Pre-migration backup skipped."

  local DB_NAME DB_USER DB_PASS BOT_TOKEN ADMIN_ID BOT_USERNAME DOMAIN
  DB_NAME=$(read_php_var "dbname" "$OLD_CFG")
  DB_USER=$(read_php_var "usernamedb" "$OLD_CFG")
  DB_PASS=$(read_php_var "passworddb" "$OLD_CFG")
  BOT_TOKEN=$(read_php_var "APIKEY" "$OLD_CFG")
  ADMIN_ID=$(read_php_var "adminnumber" "$OLD_CFG")
  BOT_USERNAME=$(mirza_normalize_bot_username "$(read_php_var "usernamebot" "$OLD_CFG")")
  DOMAIN=$(mirza_normalize_domainhosts "$(read_php_var "domainhosts" "$OLD_CFG")")

  [ -n "$BOT_TOKEN" ] && [ -n "$DOMAIN" ] && [ -n "$DB_NAME" ] || {
    err "Could not read Mirza config.php"
    return 1
  }

  viranaut_clone_github_into "$BOT_DIR" || return 1
  mirza_apply_php_core_fixes "$BOT_DIR"

  PROTOCOL="https"
  mirza_write_fresh_config "$BOT_DIR/config.php" "$DB_NAME" "$DB_USER" "$DB_PASS" \
    "$BOT_TOKEN" "$ADMIN_ID" "$BOT_USERNAME" "$PROTOCOL" "$DOMAIN"

  PROJECT_DIR="$BOT_DIR"
  CONFIG_FILE="$BOT_DIR/config.php"

  msg "Apache VirtualHost → $BOT_DIR ..."
  mirza_rewrite_vhost_documentroot "$DOMAIN" "$BOT_DIR" 2>/dev/null || true
  if [ -f "$BOT_DIR/table.php" ]; then
    (cd "$BOT_DIR" && MIRZA_SKIP_WEBHOOK=1 php table.php >/dev/null 2>&1) || true
  fi
  viranaut_db_migrate "$BOT_DIR"
  setup_cron_jobs
  mirza_save_active_dir "$BOT_DIR"
  MIRZA_DROP_PENDING_WEBHOOK=1 mirza_fix_webhook_complete "$BOT_TOKEN" "$DOMAIN" || mirza_reload_services "$BOT_DIR"
  viranaut_sync_manage_script_from_bot "$BOT_DIR"

  warn "Old Mirza files remain at: $OLD_DIR (remove manually after testing /start)"
  line
  echo -e "  ${GREEN}${BOLD}✓ Mirza → ViraNaut migration complete${NC}"
  echo -e "  ${CYAN}Path:${NC}   $BOT_DIR"
  echo -e "  ${CYAN}URL:${NC}    https://${DOMAIN}"
  echo -e "  ${CYAN}Panel:${NC}  https://${DOMAIN}/panel/"
  echo ""
}

# Install: detect Mirza → migrate | else fresh GitHub install
do_install() {
  line
  msg "ViraNaut Install — source: ${VIRANAUT_GITHUB_PAGE}"
  echo ""

  install_dependencies

  if viranaut_is_installed; then
    viranaut_already_installed_prompt
    return $?
  fi

  local legacy=""
  legacy=$(mirza_find_legacy_mirza_dir 2>/dev/null) || legacy=""
  if [ -n "$legacy" ]; then
    echo -e "  ${YELLOW}●${NC} Legacy Mirza bot found: ${BOLD}$legacy${NC}"
    if [ "${VIRANAUT_AUTO_YES:-0}" = "1" ]; then
      do_migrate_mirza_to_viranaut "$legacy"
      return $?
    fi
    read -p "  Migrate Mirza → ViraNaut automatically? (y/n) [y]: " _mig
    _mig=${_mig:-y}
    _mig=${_mig,,}
    if [ "$_mig" = "y" ]; then
      do_migrate_mirza_to_viranaut "$legacy"
      return $?
    fi
    warn "Migration declined — continuing with fresh install."
  fi

  local BOT_DIR="${INSTALL_BOT_DIR%/}"
  local DOMAIN="${VIRANAUT_DOMAIN:-}"
  local BOT_TOKEN="${VIRANAUT_TOKEN:-}"
  local ADMIN_ID="${VIRANAUT_ADMIN:-}"
  local BOT_USERNAME="${VIRANAUT_BOT_USER:-}"

  if [ -z "$DOMAIN" ] && [ "${VIRANAUT_AUTO_YES:-0}" != "1" ]; then
    read -p "  Domain (e.g. bot.example.com): " DOMAIN
  fi
  DOMAIN=$(mirza_normalize_domainhosts "$DOMAIN")
  [[ "$DOMAIN" =~ ^[a-zA-Z0-9]([a-zA-Z0-9.-]*[a-zA-Z0-9])?$ ]] || { err "Invalid domain."; return 1; }

  if [ -z "$BOT_TOKEN" ] && [ "${VIRANAUT_AUTO_YES:-0}" != "1" ]; then
    read -p "  Bot token: " BOT_TOKEN
  fi
  [[ "$BOT_TOKEN" =~ ^[0-9]+:[A-Za-z0-9_-]+$ ]] || { err "Invalid bot token."; return 1; }

  if [ -z "$ADMIN_ID" ] && [ "${VIRANAUT_AUTO_YES:-0}" != "1" ]; then
    read -p "  Admin Telegram ID: " ADMIN_ID
  fi
  [[ "$ADMIN_ID" =~ ^-?[0-9]+$ ]] || { err "Invalid admin ID."; return 1; }

  if [ -z "$BOT_USERNAME" ] && [ "${VIRANAUT_AUTO_YES:-0}" != "1" ]; then
    read -p "  Bot username (with or without @): " BOT_USERNAME
  fi
  BOT_USERNAME=$(mirza_normalize_bot_username "$BOT_USERNAME")

  local DB_NAME="viranaut_$(openssl rand -hex 3)"
  local DB_USER="viranaut_$(openssl rand -hex 3)"
  local DB_PASS
  DB_PASS=$(openssl rand -base64 18 | tr -dc 'a-zA-Z0-9' | head -c 20)

  if [ "${VIRANAUT_AUTO_YES:-0}" != "1" ]; then
    echo ""
    echo -e "  ${CYAN}Summary:${NC} GitHub → $BOT_DIR | @$BOT_USERNAME | $DOMAIN"
    read -p "  Start install? (y/n): " _go
    _go=${_go,,}
    [ "$_go" = "y" ] || { msg "Cancelled."; return 0; }
  fi

  viranaut_clone_github_into "$BOT_DIR" || return 1
  mirza_apply_php_core_fixes "$BOT_DIR"

  msg "Creating database ..."
  local DB_PASS_SQL="${DB_PASS//\'/\'\'}"
  mysql -u root <<SQL
CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '${DB_PASS_SQL}';
ALTER USER '$DB_USER'@'localhost' IDENTIFIED BY '${DB_PASS_SQL}';
GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'localhost';
FLUSH PRIVILEGES;
SQL

  local PROTOCOL="http"
  mirza_write_fresh_config "$BOT_DIR/config.php" "$DB_NAME" "$DB_USER" "$DB_PASS" \
    "$BOT_TOKEN" "$ADMIN_ID" "$BOT_USERNAME" "$PROTOCOL" "$DOMAIN"

  PROJECT_DIR="$BOT_DIR"
  CONFIG_FILE="$BOT_DIR/config.php"

  msg "Apache VirtualHost ..."
  if mirza_vhost_use_domain_conf; then
    VHOST_FILE="/etc/apache2/sites-available/${DOMAIN}.conf"
    mirza_a2dissite "$VIRANAUT_VHOST_GENERIC" "$VIRANAUT_VHOST_LEGACY" 2>/dev/null || true
  else
    VHOST_FILE="/etc/apache2/sites-available/$VIRANAUT_VHOST_GENERIC"
  fi
  cat >"$VHOST_FILE" <<VHOST
<VirtualHost *:80>
    ServerName $DOMAIN
    DocumentRoot $BOT_DIR
    <Directory $BOT_DIR>
        AllowOverride All
        Require all granted
    </Directory>
    ErrorLog \${APACHE_LOG_DIR}/${VIRANAUT_LOG_ERROR}
    CustomLog \${APACHE_LOG_DIR}/${VIRANAUT_LOG_ACCESS} combined
</VirtualHost>
VHOST
  mirza_a2ensite "$(basename "$VHOST_FILE")" 2>/dev/null || true
  mirza_a2dissite 000-default.conf 2>/dev/null || true
  a2enmod rewrite 2>/dev/null || true
  systemctl enable apache2 2>/dev/null || true
  systemctl restart apache2

  if mirza_setup_ssl "$DOMAIN"; then
    PROTOCOL="https"
    mirza_write_fresh_config "$BOT_DIR/config.php" "$DB_NAME" "$DB_USER" "$DB_PASS" \
      "$BOT_TOKEN" "$ADMIN_ID" "$BOT_USERNAME" "$PROTOCOL" "$DOMAIN"
  fi

  if [ -f "$BOT_DIR/table.php" ]; then
    (cd "$BOT_DIR" && MIRZA_SKIP_WEBHOOK=1 php table.php >/dev/null 2>&1) || true
  fi
  viranaut_db_migrate "$BOT_DIR"

  BOT_DIR=$(viranaut_relocate_to_canonical_path "$BOT_DIR")
  BOT_DIR=$(mirza_sanitize_bot_dir "$BOT_DIR")
  PROJECT_DIR="$BOT_DIR"
  CONFIG_FILE="$BOT_DIR/config.php"

  setup_cron_jobs
  mirza_save_active_dir "$BOT_DIR"
  resolve_project_paths
  mirza_reload_services "$BOT_DIR"
  ufw allow 80/tcp 2>/dev/null || true
  ufw allow 443/tcp 2>/dev/null || true
  viranaut_sync_manage_script_from_bot "$BOT_DIR"

  line
  echo -e "  ${GREEN}${BOLD}✓ ViraNaut install complete${NC}"
  echo -e "  ${CYAN}Path:${NC}     $BOT_DIR"
  echo -e "  ${CYAN}URL:${NC}      ${PROTOCOL}://${DOMAIN}"
  echo -e "  ${CYAN}Panel:${NC}    ${PROTOCOL}://${DOMAIN}/panel/"
  echo -e "  ${CYAN}Database:${NC} $DB_NAME / $DB_USER / $DB_PASS"
  echo -e "  ${YELLOW}Save the DB password — shown once.${NC}"
  echo -e "  ${CYAN}CLI:${NC}       ${BOLD}viranaut${NC}"
  echo ""
}

# One-line / CLI entry (see README)
viranaut_cli_entry() {
  local cmd="${1:-}"
  shift || true
  while [ $# -gt 0 ]; do
    case "$1" in
      -y|--yes) VIRANAUT_AUTO_YES=1; shift ;;
      --domain) VIRANAUT_DOMAIN="$2"; shift 2 ;;
      --token) VIRANAUT_TOKEN="$2"; shift 2 ;;
      --admin) VIRANAUT_ADMIN="$2"; shift 2 ;;
      --bot) VIRANAUT_BOT_USER="$2"; shift 2 ;;
      *) shift ;;
    esac
  done
  case "$cmd" in
    install)
      check_root
      viranaut_link_cli
      if viranaut_is_installed; then
        viranaut_already_installed_prompt
        local _rc=$?
        [ "$_rc" -eq 2 ] && return 1
        exit 0
      fi
      do_install
      exit $?
      ;;
    update)
      check_root
      viranaut_link_cli
      VIRANAUT_AUTO_YES="${VIRANAUT_AUTO_YES:-1}"
      do_update_bot
      exit $?
      ;;
    restart|reload)
      check_root
      viranaut_link_cli
      do_restart_full
      exit $?
      ;;
    stop)
      check_root
      do_stop_apache
      exit $?
      ;;
    start)
      check_root
      do_start_apache
      exit $?
      ;;
    diagnose)
      check_root
      viranaut_link_cli
      do_diagnose_bot
      exit $?
      ;;
    fix|autofix)
      check_root
      viranaut_link_cli
      do_fix_all_bot
      exit $?
      ;;
    panel-fix|panelfix)
      check_root
      viranaut_link_cli
      resolve_project_paths
      viranaut_panel_fix "$PROJECT_DIR"
      exit $?
      ;;
    remove|uninstall)
      check_root
      viranaut_link_cli
      do_full_remove_bot
      exit $?
      ;;
    logs)
      check_root
      viranaut_link_cli
      do_logs
      exit $?
      ;;
    menu|"")
      return 1
      ;;
  esac
  return 1
}

do_local_update_bot() { do_update_bot; }

# -------------------- Auto-install dependencies --------------------
# Installs Apache, PHP 8.2, MySQL, etc. if not present
install_dependencies() {
  resolve_project_paths
  local NEED_INSTALL=0

  if ! command -v mysql >/dev/null 2>&1; then
    NEED_INSTALL=1
  fi
  if ! command -v php >/dev/null 2>&1; then
    NEED_INSTALL=1
  fi
  if ! command -v apache2ctl >/dev/null 2>&1; then
    NEED_INSTALL=1
  fi

  if [ "$NEED_INSTALL" -eq 0 ]; then
    return 0
  fi

  echo ""
  warn "Required packages (Apache / PHP / MySQL) are not fully installed."
  msg "Installing dependencies (same as install_mirza.sh) ..."
  line

  export DEBIAN_FRONTEND=noninteractive

  # Step 1: Update & upgrade system
  msg "Updating system ..."
  apt-get update -y
  apt-get upgrade -y

  # Step 2: Apache, MySQL, tools
  msg "Installing Apache, MySQL, git, tools ..."
  apt-get install -y apache2 mysql-server git unzip zip software-properties-common curl

  # Step 3: PHP 8.2 from ondrej PPA
  msg "Installing PHP 8.2 ..."
  if ! dpkg -l | grep -q php8.2; then
    add-apt-repository ppa:ondrej/php -y 2>/dev/null || true
    apt-get update -y
  fi
  apt-get install -y \
    php8.2 libapache2-mod-php8.2 \
    php8.2-cli php8.2-common php8.2-mbstring php8.2-curl \
    php8.2-xml php8.2-zip php8.2-mysql php8.2-gd php8.2-bcmath

  # Step 4: Certbot for SSL
  msg "Installing certbot ..."
  apt-get install -y certbot python3-certbot-apache

  # Step 5: Enable Apache modules
  a2dismod php7.4 php8.0 php8.1 2>/dev/null || true
  a2enmod php8.2 rewrite 2>/dev/null || true
  systemctl restart apache2

  # Step 6: Start & enable MySQL
  systemctl start mysql 2>/dev/null || true
  systemctl enable mysql 2>/dev/null || true

  # Step 7: Show PHP version
  echo ""
  msg "PHP version:"
  php -v || true

  # Step 8: Create project directory
  mkdir -p "$PROJECT_DIR"
  chown -R www-data:www-data "$PROJECT_DIR"

  line
  echo -e "  ${GREEN}✓${NC} All dependencies installed"
  echo ""
}

# Read a PHP variable from config.php (GNU sed -E; avoids BRE \( \) errors)
# Usage: read_php_var "varname" [path/to/config.php]
read_php_var() {
  local var="$1"
  local file="${2:-$CONFIG_FILE}"
  local line val
  [ -f "$file" ] || return 0

  line=$(grep -E "^[[:space:]]*\\\$${var}[[:space:]]*=" "$file" 2>/dev/null | head -1)
  if [ -n "$line" ]; then
    val=$(printf '%s' "$line" | sed -E "s/^[[:space:]]*\\\$${var}[[:space:]]*=[[:space:]]*['\"]([^'\"]*)['\"].*$/\1/")
    if [ -n "$val" ]; then
      printf '%s' "$val"
      return 0
    fi
  fi

  val=$(sed -En "s/^[[:space:]]*\\\$${var}[[:space:]]*=[[:space:]]*['\"]([^'\"]*)['\"].*/\1/p" "$file" | head -n 1)
  [ -n "$val" ] && printf '%s' "$val"
}

mirza_config_mysql_ok() {
  local dbname="$1" dbuser="$2" dbpass="$3"
  [ -n "$dbname" ] && [ -n "$dbuser" ] || return 1
  mysql -u "$dbuser" ${dbpass:+-p"$dbpass"} -e "USE \`$dbname\`; SELECT 1;" 2>/dev/null
}

# Merge legacy Mirza DB branding → ViraNaut + run migration SQL
viranaut_db_migrate() {
  local BOT_DIR="${1%/}"
  local CONFIG_PATH="$BOT_DIR/config.php"
  [ -f "$CONFIG_PATH" ] || return 0

  local dbname dbuser dbpass
  dbname=$(read_php_var "dbname" "$CONFIG_PATH")
  dbuser=$(read_php_var "usernamedb" "$CONFIG_PATH")
  dbpass=$(read_php_var "passworddb" "$CONFIG_PATH")
  if [ -z "$dbname" ] || [ -z "$dbuser" ]; then
    warn "Skip DB merge: could not read database credentials."
    return 0
  fi

  if ! command -v mysql >/dev/null 2>&1; then
    warn "mysql client not found — skip DB merge."
    return 0
  fi

  msg "ViraNaut database merge (migration + rebrand) ..."

  local migrate_sql="$BOT_DIR/migrations/viranaut_migrate.sql"
  if [ -f "$migrate_sql" ]; then
    mysql -u "$dbuser" -p"$dbpass" "$dbname" <"$migrate_sql" 2>/dev/null \
      || warn "Some migration SQL lines skipped (often OK on re-run)."
  fi

  mysql -u "$dbuser" -p"$dbpass" "$dbname" >/dev/null 2>&1 <<EOSQL || true
SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='setting' AND COLUMN_NAME='status_usertest');
SET @sql := IF(@col=0, 'ALTER TABLE setting ADD COLUMN status_usertest VARCHAR(16) DEFAULT ''ontest''', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
EOSQL

  local has_textbot
  has_textbot=$(mysql -u "$dbuser" -p"$dbpass" -N -e "SHOW TABLES LIKE 'textbot';" "$dbname" 2>/dev/null || true)
  if [ -n "$has_textbot" ]; then
    mysql -u "$dbuser" -p"$dbpass" "$dbname" 2>/dev/null <<'EOSQL' || true
UPDATE textbot SET text = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(text,
  'تیم میرزا', 'تیم ویرانات'),
  'گروه میرزا', 'گروه ویرانات'),
  'میرزا', 'ویرانات'),
  'mirzapanelgroup', ''),
  'Mirza Group', 'ViraNaut Support'),
  'Mirza', 'ViraNaut')
WHERE text LIKE '%میرزا%' OR text LIKE '%Mirza%' OR text LIKE '%mirzapanel%';
EOSQL
  fi

  mysql -u "$dbuser" -p"$dbpass" "$dbname" 2>/dev/null <<'EOSQL' || true
UPDATE PaySetting SET ValuePay = REPLACE(REPLACE(REPLACE(ValuePay,
  'تیم میرزا', 'تیم ویرانات'), 'میرزا', 'ویرانات'), 'Mirza', 'ViraNaut')
WHERE ValuePay LIKE '%میرزا%' OR ValuePay LIKE '%Mirza%';
UPDATE shopSetting SET value = REPLACE(REPLACE(REPLACE(value,
  'تیم میرزا', 'تیم ویرانات'), 'میرزا', 'ویرانات'), 'Mirza', 'ViraNaut')
WHERE value LIKE '%میرزا%' OR value LIKE '%Mirza%';
INSERT INTO shopSetting (Namevalue, value) VALUES ('viranaut_version', '1.9-ViraNaut')
ON DUPLICATE KEY UPDATE value='1.9-ViraNaut';
EOSQL

  echo -e "  ${GREEN}✓${NC} ViraNaut database merge completed"
}

load_config() {
  resolve_project_paths
  if [ ! -f "$CONFIG_FILE" ]; then
    err "config.php not found at $CONFIG_FILE"
    err "Is ViraNaut installed? Use menu 1 (Install)."
    mirza_list_installations || true
    exit 1
  fi
  DB_NAME=$(read_php_var "dbname")
  DB_USER=$(read_php_var "usernamedb")
  DB_PASS=$(read_php_var "passworddb")
  BOT_TOKEN=$(read_php_var "APIKEY")
  ADMIN_ID=$(read_php_var "adminnumber")
  BOT_USERNAME=$(mirza_normalize_bot_username "$(read_php_var "usernamebot")")
  DOMAIN_RAW=$(read_php_var "domainhosts")
  DOMAIN=$(mirza_normalize_domainhosts "$DOMAIN_RAW")

  if [ -z "$DB_NAME" ] || [ -z "$DB_USER" ] || [ -z "$DB_PASS" ]; then
    err "Could not read database credentials from config.php"
    exit 1
  fi
}

# --- Auto-repair helpers ---
mirza_latest_backup_zip() {
  local f latest=""
  [ -d "$BACKUP_DIR" ] || return 1
  for f in "$BACKUP_DIR"/*.zip; do
    [ -f "$f" ] || continue
    if [ -z "$latest" ] || [ "$f" -nt "$latest" ]; then
      latest="$f"
    fi
  done
  [ -n "$latest" ] && printf '%s' "$latest"
}

mirza_sanitize_vhost_conf() {
  local f="$1"
  [ -f "$f" ] || return 0
  if [ ! -f /etc/apache2/conf-available/phpmyadmin.conf ]; then
    sed -i 's|^[[:space:]]*Include[[:space:]]*/etc/apache2/conf-available/phpmyadmin.conf|#Include phpmyadmin (not installed)|' "$f" 2>/dev/null || true
  fi
}

# After restore, vhost in ZIP may point at old path (e.g. mirzaprobotconfig) — force current bot dir
mirza_rewrite_vhost_documentroot() {
  local domain="$1"
  local bot_dir
  bot_dir=$(mirza_sanitize_bot_dir "${2%/}")
  [ -d "$bot_dir" ] || {
    warn "Invalid bot path for vhost rewrite: ${2:-empty}"
    return 1
  }
  local f
  for f in /etc/apache2/sites-available/${domain}*.conf \
    /etc/apache2/sites-available/*${domain}*-ssl.conf \
    /etc/apache2/sites-available/*${domain}*-le-ssl.conf; do
    [ -f "$f" ] || continue
    sed -i "s|^[[:space:]]*DocumentRoot.*|    DocumentRoot $bot_dir|" "$f"
    sed -i "s|/var/www/html/viranautconfig|$bot_dir|g" "$f"
    sed -i "s|/var/www/html/viranaut|$bot_dir|g" "$f"
    sed -i "s|/var/www/html/mirzaprobotconfig|$bot_dir|g" "$f"
    sed -i "s|/var/www/html/mirzabotconfig|$bot_dir|g" "$f"
    sed -i "s|/var/www/mirza_pro|$bot_dir|g" "$f"
    mirza_sanitize_vhost_conf "$f"
  done
  echo -e "  ${GREEN}✓${NC} Apache DocumentRoot → $bot_dir (all vhosts for $domain)" >&2
}

mirza_write_http_vhost() {
  local domain="$1"
  local bot_dir="${2%/}"
  local vhost="/etc/apache2/sites-available/${domain}.conf"
  cat >"$vhost" <<VHOST
<VirtualHost *:80>
    ServerName $domain
    DocumentRoot $bot_dir

    <Directory $bot_dir>
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog \${APACHE_LOG_DIR}/${domain}-error.log
    CustomLog \${APACHE_LOG_DIR}/${domain}-access.log combined
</VirtualHost>
VHOST
  mirza_sanitize_vhost_conf "$vhost"
}

mirza_ensure_ssl_vhost() {
  local domain="$1"
  local bot_dir
  bot_dir=$(mirza_sanitize_bot_dir "${2%/}")
  local ssl_conf="" f

  if ! mirza_ssl_cert_exists "$domain"; then
    return 0
  fi

  for f in \
    "/etc/apache2/sites-available/${domain}-le-ssl.conf" \
    "/etc/apache2/sites-available/${domain}-ssl.conf" \
    "/etc/apache2/sites-available/${domain}.conf"; do
    [ -f "$f" ] && grep -q ':443' "$f" 2>/dev/null && ssl_conf="$f" && break
  done

  if [ -z "$ssl_conf" ]; then
    ssl_conf="/etc/apache2/sites-available/${domain}-ssl.conf"
    cat >"$ssl_conf" <<VHOST
<VirtualHost *:443>
    ServerName $domain
    DocumentRoot $bot_dir
    SSLEngine on
    SSLCertificateFile /etc/letsencrypt/live/$domain/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/$domain/privkey.pem
    <Directory $bot_dir>
        AllowOverride All
        Require all granted
    </Directory>
    ErrorLog \${APACHE_LOG_DIR}/${domain}-error.log
    CustomLog \${APACHE_LOG_DIR}/${domain}-access.log combined
</VirtualHost>
VHOST
  fi

  # Fix DocumentRoot on any SSL vhost for this domain (certbot/le-ssl may point elsewhere)
  for f in /etc/apache2/sites-available/${domain}*.conf /etc/apache2/sites-available/*${domain}*; do
    [ -f "$f" ] || continue
    grep -q ':443' "$f" 2>/dev/null || continue
    if grep -q DocumentRoot "$f" 2>/dev/null; then
      sed -i "s|^[[:space:]]*DocumentRoot.*|    DocumentRoot $bot_dir|" "$f"
    fi
    mirza_sanitize_vhost_conf "$f"
    mirza_a2ensite "$(basename "$f")" 2>/dev/null || true
  done
  mirza_sanitize_vhost_conf "$ssl_conf"
  mirza_a2ensite "$(basename "$ssl_conf")" 2>/dev/null || true
}

mirza_ufw_allow_telegram() {
  if ! command -v ufw >/dev/null 2>&1; then
    return 0
  fi
  msg "Allowing Telegram webhook IPs in UFW (80/443) ..."
  ufw allow 80/tcp 2>/dev/null || true
  ufw allow 443/tcp 2>/dev/null || true
  ufw allow from 149.154.160.0/20 to any port 443 proto tcp 2>/dev/null || true
  ufw allow from 91.108.4.0/22 to any port 443 proto tcp 2>/dev/null || true
  ufw allow from 149.154.160.0/20 to any port 80 proto tcp 2>/dev/null || true
  ufw allow from 91.108.4.0/22 to any port 80 proto tcp 2>/dev/null || true
  echo -e "  ${GREEN}✓${NC} UFW rules for Telegram subnets (also check VPS cloud firewall panel)"
}

mirza_show_webhook_access_recent() {
  local domain="$1"
  local acc lines
  acc=$(viranaut_apache_log_file access "$domain") || acc=""
  [ -n "$acc" ] || acc="/var/log/apache2/access.log"
  [ -f "$acc" ] || return 0
  echo -e "    ${CYAN}Recent POST /index.php in access log:${NC}"
  lines=$(grep -F "POST /index.php" "$acc" 2>/dev/null | tail -5)
  if [ -n "$lines" ]; then
    echo "$lines" | sed 's/^/      /'
  else
    echo "      (none) — Telegram requests are NOT reaching Apache"
  fi
}

mirza_test_https_post_speed() {
  local domain="$1"
  local url="https://${domain}/index.php"
  local code time_total
  code=$(curl -sk -o /dev/null -w "%{http_code}|%{time_total}" --connect-timeout 15 -m 25 \
    -X POST -H "Content-Type: application/json" -d '{"update_id":1}' "$url" 2>/dev/null || echo "000|0")
  time_total="${code#*|}"
  code="${code%%|*}"
  echo "    POST test from server: HTTP $code in ${time_total}s (403/200 fast = OK; 000 = blocked)"
  if [ "$code" = "000" ]; then
    warn "HTTPS POST failed — Telegram will show Connection timed out"
    return 1
  fi
  return 0
}

mirza_enable_apache_for_bot() {
  local domain="$1"
  local bot_dir="${2%/}"
  a2enmod rewrite ssl 2>/dev/null || true
  mirza_write_http_vhost "$domain" "$bot_dir" >/dev/null
  mirza_a2ensite "${domain}.conf"
  if mirza_ssl_cert_exists "$domain"; then
    mirza_ensure_ssl_vhost "$domain" "$bot_dir"
  else
    mirza_a2dissite "${domain}-ssl.conf" "${domain}-le-ssl.conf" 2>/dev/null || true
    warn "No SSL cert yet — HTTP only until certbot succeeds"
  fi
  mirza_a2dissite 000-default.conf "$VIRANAUT_VHOST_GENERIC" "$VIRANAUT_VHOST_LEGACY" 2>/dev/null || true
  apache2ctl configtest >/dev/null 2>&1 || {
    err "Apache configtest failed"
    apache2ctl configtest
    return 1
  }
  systemctl reload apache2 2>/dev/null || systemctl restart apache2
}

mirza_ensure_mysql_from_config() {
  local dbname="$1" dbuser="$2" dbpass="$3"
  local dbpass_sql="${dbpass//\'/\'\'}"
  mysql -u root <<SQL
CREATE DATABASE IF NOT EXISTS \`$dbname\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$dbuser'@'localhost' IDENTIFIED BY '${dbpass_sql}';
ALTER USER '$dbuser'@'localhost' IDENTIFIED BY '${dbpass_sql}';
GRANT ALL PRIVILEGES ON \`$dbname\`.* TO '$dbuser'@'localhost';
FLUSH PRIVILEGES;
SQL
}

mirza_db_user_count() {
  local dbname="$1" dbuser="$2" dbpass="$3"
  mysql -u "$dbuser" -p"$dbpass" -N -e "USE \`$dbname\`; SELECT COUNT(*) FROM user;" 2>/dev/null || echo "-1"
}

mirza_import_database_from_zip() {
  local zip="$1" dbname="$2" dbuser="$3" dbpass="$4"
  local tmp sql
  [ -f "$zip" ] || return 1
  tmp=$(mktemp -d)
  unzip -q -o "$zip" database.sql -d "$tmp" 2>/dev/null || unzip -q -o "$zip" "*/database.sql" -d "$tmp" 2>/dev/null || {
    rm -rf "$tmp"
    return 1
  }
  sql=$(find "$tmp" -name 'database.sql' -type f | head -1)
  [ -f "$sql" ] || { rm -rf "$tmp"; return 1; }
  msg "Importing database from $(basename "$zip") ..."
  mysql -u "$dbuser" -p"$dbpass" "$dbname" <"$sql"
  rm -rf "$tmp"
}

# panel ZIP (manifest mirza-full-backup) or manage ZIP
mirza_backup_zip_is_panel() {
  local tmp="$1"
  [ -f "$tmp/manifest.json" ] && grep -q 'mirza-full-backup' "$tmp/manifest.json" 2>/dev/null
}

mirza_write_meta_cron_jobs() {
  local out="$1" domain="$2"
  [ -n "$domain" ] || domain="YOUR_DOMAIN"
  domain=$(mirza_normalize_domainhosts "$domain")
  mkdir -p "$(dirname "$out")"
  cat >"$out" <<CRON
*/15 * * * * curl -fsS https://${domain}/cronbot/statusday.php
*/1 * * * * curl -fsS https://${domain}/cronbot/card_receipt_prompt.php
*/1 * * * * curl -fsS https://${domain}/cronbot/NoticationsService.php
*/5 * * * * curl -fsS https://${domain}/cronbot/payment_expire.php
*/1 * * * * curl -fsS https://${domain}/cronbot/sendmessage.php
*/3 * * * * curl -fsS https://${domain}/cronbot/plisio.php
*/1 * * * * curl -fsS https://${domain}/cronbot/activeconfig.php
*/1 * * * * curl -fsS https://${domain}/cronbot/disableconfig.php
*/1 * * * * curl -fsS https://${domain}/cronbot/iranpay1.php
0 */5 * * * curl -fsS https://${domain}/cronbot/backupbot.php
*/2 * * * * curl -fsS https://${domain}/cronbot/gift.php
*/30 * * * * curl -fsS https://${domain}/cronbot/expireagent.php
*/15 * * * * curl -fsS https://${domain}/cronbot/on_hold.php
*/2 * * * * curl -fsS https://${domain}/cronbot/configtest.php
*/15 * * * * curl -fsS https://${domain}/cronbot/uptime_node.php
*/15 * * * * curl -fsS https://${domain}/cronbot/uptime_panel.php
*/1 * * * * curl -fsS https://${domain}/cronbot/lottery.php
CRON
}

# cronbot queue files, text.json — same idea as panel backup
mirza_restore_bot_data_files() {
  local bot_dir="${1%/}" tmp="$2"
  local n=0 f base

  if [ -d "$tmp/cronbot" ]; then
    mkdir -p "$bot_dir/cronbot"
    for f in "$tmp/cronbot"/*; do
      [ -f "$f" ] || continue
      base=$(basename "$f")
      [[ "$base" == *.php ]] && continue
      cp "$f" "$bot_dir/cronbot/$base"
      n=$((n + 1))
    done
    [ "$n" -gt 0 ] && echo -e "  ${GREEN}✓${NC} cronbot data restored ($n file(s))"
  fi

  if [ -f "$tmp/text.json" ]; then
    cp "$tmp/text.json" "$bot_dir/text.json"
    chown www-data:www-data "$bot_dir/text.json" 2>/dev/null || true
    echo -e "  ${GREEN}✓${NC} text.json restored"
  fi

  if [ -f "$tmp/version" ]; then
    cp "$tmp/version" "$bot_dir/version"
    chown www-data:www-data "$bot_dir/version" 2>/dev/null || true
  fi
}

mirza_import_database_from_dir() {
  local tmp="$1" dbname="$2" dbuser="$3" dbpass="$4"
  local sql
  sql=$(find "$tmp" -maxdepth 2 -name 'database.sql' -type f | head -1)
  [ -f "$sql" ] || return 1
  msg "Importing database ..."
  mysql -u "$dbuser" -p"$dbpass" "$dbname" <"$sql"
}

mirza_ensure_bot_permissions() {
  local bot_dir="${1%/}"
  chown -R www-data:www-data "$bot_dir"
  find "$bot_dir" -type d -exec chmod 755 {} \;
  find "$bot_dir" -type f -exec chmod 644 {} \;
  [ -f "$bot_dir/config.php" ] && chmod 640 "$bot_dir/config.php"
  mkdir -p "$bot_dir/storage/cache"
  chown -R www-data:www-data "$bot_dir/storage" 2>/dev/null || true
  chmod -R 775 "$bot_dir/storage" 2>/dev/null || true
}

mirza_server_public_ip() {
  local ip
  ip=$(hostname -I 2>/dev/null | awk '{print $1}')
  if [ -z "$ip" ]; then
    ip=$(curl -4 -s --connect-timeout 5 ifconfig.me 2>/dev/null || true)
  fi
  printf '%s' "$ip"
}

mirza_dns_a_record() {
  local domain="$1"
  dig +short "$domain" A 2>/dev/null | grep -E '^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$' | head -1
}

# 0 = DNS A matches this server; 1 = mismatch or unknown
mirza_check_dns_matches_server() {
  local domain="$1"
  local srv_ip dns_a
  srv_ip=$(mirza_server_public_ip)
  dns_a=$(mirza_dns_a_record "$domain")
  echo -e "    Server IP:   ${srv_ip:-unknown}"
  echo -e "    DNS A:       ${dns_a:-not set}"
  if [ -z "$srv_ip" ] || [ -z "$dns_a" ]; then
    warn "Cannot verify DNS — fix A record for $domain manually"
    return 1
  fi
  if [ "$srv_ip" = "$dns_a" ]; then
    echo -e "  ${GREEN}✓${NC} DNS points to this server"
    return 0
  fi
  err "DNS points to $dns_a but this server is $srv_ip"
  echo "    Update DNS A record → $srv_ip then run menu 8 again"
  return 1
}

mirza_wait_https_ready() {
  local domain="$1"
  local url="https://${domain}/index.php"
  local code attempt=1
  while [ "$attempt" -le 24 ]; do
    code=$(curl -sk -o /dev/null -w "%{http_code}" --connect-timeout 10 "$url" 2>/dev/null || echo "000")
    case "$code" in
      200|405|400|403) echo "$code"; return 0 ;;
    esac
    [ "$attempt" -eq 1 ] && msg "Waiting for HTTPS ($url) ..."
    sleep 5
    attempt=$((attempt + 1))
  done
  echo "$code"
  return 1
}

# drop pending updates, set webhook, verify getWebhookInfo (for menu 8)
mirza_fix_webhook_complete() {
  local token="$1"
  local domain="$2"
  local webhook="https://${domain}/index.php"
  local res wh_info pending last_err attempt=1

  if ! mirza_check_dns_matches_server "$domain"; then
    warn "Skipping webhook fix until DNS is correct"
    return 1
  fi

  local https_code
  https_code=$(mirza_wait_https_ready "$domain") || true
  if [ "$https_code" = "200" ] || [ "$https_code" = "405" ] || [ "$https_code" = "403" ]; then
    echo -e "  ${GREEN}✓${NC} HTTPS reachable (HTTP $https_code)"
  else
    warn "HTTPS returned $https_code — webhook may still timeout"
  fi

  msg "Clearing webhook + pending updates (drop_pending_updates) ..."
  curl -s "https://api.telegram.org/bot${token}/deleteWebhook?drop_pending_updates=true" >/dev/null 2>&1 || true
  sleep 3

  msg "Setting webhook ..."
  while [ "$attempt" -le 8 ]; do
    res=$(curl -s "https://api.telegram.org/bot${token}/setWebhook?url=${webhook}")
    if echo "$res" | grep -q '"ok":true'; then
      echo -e "  ${GREEN}✓${NC} setWebhook OK"
      break
    fi
    if echo "$res" | grep -q 'retry_after'; then
      local wait_sec
      wait_sec=$(echo "$res" | sed -n 's/.*"retry_after"[[:space:]]*:[[:space:]]*\([0-9][0-9]*\).*/\1/p' | head -1)
      sleep "$((wait_sec + 2))"
      attempt=$((attempt + 1))
      continue
    fi
    warn "setWebhook: $res"
    break
  done

  sleep 2
  msg "Verifying webhook (getWebhookInfo) ..."
  wh_info=$(curl -s "https://api.telegram.org/bot${token}/getWebhookInfo")
  pending=$(echo "$wh_info" | sed -n 's/.*"pending_update_count"[[:space:]]*:[[:space:]]*\([0-9][0-9]*\).*/\1/p' | head -1)
  pending=${pending:-?}
  last_err=$(echo "$wh_info" | sed -n 's/.*"last_error_message"[[:space:]]*:[[:space:]]*"\([^"]*\)".*/\1/p' | head -1)
  echo "    URL:      $(echo "$wh_info" | sed -n 's/.*"url"[[:space:]]*:[[:space:]]*"\([^"]*\)".*/\1/p' | head -1)"
  echo "    Pending:  $pending"
  if [ -n "$last_err" ]; then
    warn "last_error_message: $last_err"
    msg "Retrying setWebhook once more ..."
    sleep 5
    curl -s "https://api.telegram.org/bot${token}/setWebhook?url=${webhook}" >/dev/null
    sleep 2
    wh_info=$(curl -s "https://api.telegram.org/bot${token}/getWebhookInfo")
    last_err=$(echo "$wh_info" | sed -n 's/.*"last_error_message"[[:space:]]*:[[:space:]]*"\([^"]*\)".*/\1/p' | head -1)
    pending=$(echo "$wh_info" | sed -n 's/.*"pending_update_count"[[:space:]]*:[[:space:]]*\([0-9][0-9]*\).*/\1/p' | head -1)
  fi
  mirza_ufw_allow_telegram

  if [ -z "$last_err" ] && [ "${pending:-0}" = "0" ]; then
    echo -e "  ${GREEN}✓${NC} Webhook healthy — send /start in Telegram"
    mirza_show_webhook_access_recent "$domain"
    return 0
  fi
  if echo "$last_err" | grep -qi 'timed out\|timeout'; then
    warn "Connection timed out — usually VPS/cloud firewall blocks Telegram IPs (not UFW on server)"
    echo "    Open inbound TCP 443 (and 80) in your hosting panel for ALL IPs or Telegram ranges"
    mirza_test_https_post_speed "$domain" || true
  fi
  if echo "$last_err" | grep -qi 'SSL error\|record layer\|packet length'; then
    warn "SSL/TLS broken on :443 — run menu 8 again; keep ${domain}-le-ssl.conf enabled"
    echo "    Quick check: openssl s_client -connect ${domain}:443 -servername ${domain} </dev/null | head -5"
    mirza_test_ssl_handshake "$domain" || warn "TLS handshake still failing after fix attempt"
  fi
  [ -n "$last_err" ] && warn "Telegram last_error: $last_err"
  [ "${pending:-0}" != "0" ] && warn "pending_update_count=$pending"
  mirza_show_webhook_access_recent "$domain"
  return 0
}

mirza_test_ssl_handshake() {
  local domain="$1"
  local out
  out=$(echo | timeout 12 openssl s_client -connect "${domain}:443" -servername "$domain" 2>/dev/null | head -20)
  echo "$out" | grep -q 'BEGIN CERTIFICATE' && return 0
  echo "$out" | grep -qi 'SSL handshake\|verify return:1' && return 0
  return 1
}

mirza_restore_apache_vhosts_from_zip() {
  local zip="$1" domain="$2" bot_dir="${3%/}"
  local tmp restored=0 f base
  [ -f "$zip" ] || return 1
  [ -n "$domain" ] || return 1
  tmp=$(mktemp -d)
  unzip -q -o "$zip" \
    "${domain}.conf" "${domain}-ssl.conf" "${domain}-le-ssl.conf" \
    "$VIRANAUT_VHOST_GENERIC" "$VIRANAUT_VHOST_LEGACY" \
    -d "$tmp" 2>/dev/null || true
  for f in "$tmp"/*.conf; do
    [ -f "$f" ] || continue
    base=$(basename "$f")
    cp "$f" "/etc/apache2/sites-available/$base"
    mirza_sanitize_vhost_conf "/etc/apache2/sites-available/$base"
    mirza_a2ensite "$base" 2>/dev/null || true
    restored=1
    echo -e "  ${GREEN}✓${NC} Restored vhost from backup: $base"
  done
  rm -rf "$tmp"
  if [ "$restored" -eq 1 ]; then
    mirza_rewrite_vhost_documentroot "$domain" "$bot_dir"
  fi
  return 0
}

mirza_restore_vendor_from_zip_if_missing() {
  local zip="$1" bot_dir="${2%/}"
  [ -f "$bot_dir/vendor/autoload.php" ] && return 0
  [ -f "$zip" ] || return 1
  local tmp
  tmp=$(mktemp -d)
  if unzip -q -o "$zip" "vendor/autoload.php" -d "$tmp" 2>/dev/null \
    || unzip -q -o "$zip" "*/vendor/autoload.php" -d "$tmp" 2>/dev/null; then
    local vend
    vend=$(find "$tmp" -path '*/vendor/autoload.php' -type f | head -1)
    if [ -n "$vend" ]; then
      rm -rf "$bot_dir/vendor"
      cp -a "$(dirname "$vend")" "$bot_dir/vendor"
      echo -e "  ${GREEN}✓${NC} Restored vendor/ from backup ZIP"
    fi
  fi
  rm -rf "$tmp"
}

mirza_fix_ssl_and_firewall() {
  local domain="$1"
  local bot_dir="${2:-}"
  ufw allow 80/tcp 2>/dev/null || true
  ufw allow 443/tcp 2>/dev/null || true
  apt-get install -y certbot python3-certbot-apache openssl >/dev/null 2>&1 || true
  if ! mirza_check_dns_matches_server "$domain"; then
    warn "Skipping certbot until DNS A record matches this server"
    return 1
  fi
  a2enmod ssl 2>/dev/null || true
  if [ -n "$bot_dir" ]; then
    mirza_ensure_ssl_vhost "$domain" "$bot_dir"
  fi
  if mirza_ssl_cert_exists "$domain"; then
    if mirza_test_ssl_handshake "$domain"; then
      echo -e "  ${GREEN}✓${NC} SSL certificate + TLS handshake OK for $domain"
      return 0
    fi
    warn "SSL cert on disk but HTTPS handshake failed — repairing with certbot ..."
    certbot --apache -d "$domain" --non-interactive --agree-tos --register-unsafely-without-email --redirect 2>/dev/null \
      || certbot install --cert-name "$domain" 2>/dev/null \
      || certbot renew --cert-name "$domain" --force-renewal 2>/dev/null || true
    [ -n "$bot_dir" ] && mirza_ensure_ssl_vhost "$domain" "$bot_dir"
    if mirza_test_ssl_handshake "$domain"; then
      echo -e "  ${GREEN}✓${NC} SSL repaired"
      return 0
    fi
    warn "TLS still failing — check Apache :443 vhost (le-ssl must stay enabled)"
    return 1
  fi
  msg "Requesting SSL certificate (DNS OK, port 80 open) ..."
  if certbot --apache -d "$domain" --non-interactive --agree-tos --register-unsafely-without-email --redirect; then
    [ -n "$bot_dir" ] && mirza_ensure_ssl_vhost "$domain" "$bot_dir"
    echo -e "  ${GREEN}✓${NC} SSL installed"
    return 0
  fi
  warn "certbot failed — check firewall 80/443 on VPS panel"
  return 1
}

do_fix_all_bot() {
  line
  msg "Auto-fix ViraNaut bot (DB + Apache + SSL + webhook)"
  line
  resolve_project_paths

  if [ ! -f "$CONFIG_FILE" ]; then
    err "No config.php — use menu 1 (Install) first."
    return 1
  fi

  local domain token dbname dbuser dbpass user_count backup_zip bot_user
  local cfg_path="$CONFIG_FILE"

  # اول config را بخوان — قبل از relocate (باگ v1.9: relocate خروجی stdout را داخل PROJECT_DIR می‌ریخت)
  domain=$(mirza_normalize_domainhosts "$(read_php_var "domainhosts" "$cfg_path")")
  token=$(read_php_var "APIKEY" "$cfg_path")
  dbname=$(read_php_var "dbname" "$cfg_path")
  dbuser=$(read_php_var "usernamedb" "$cfg_path")
  dbpass=$(read_php_var "passworddb" "$cfg_path")
  bot_user=$(mirza_normalize_bot_username "$(read_php_var "usernamebot" "$cfg_path")")

  if [ -z "$domain" ] || [ -z "$token" ] || [ -z "$dbname" ] || [ -z "$dbuser" ]; then
    err "config.php incomplete — missing:"
    echo -e "    ${CYAN}File:${NC} $cfg_path"
    [ -z "$domain" ] && echo "    • domainhosts"
    [ -z "$token" ] && echo "    • APIKEY"
    [ -z "$dbname" ] && echo "    • dbname"
    [ -z "$dbuser" ] && echo "    • usernamedb"
    echo ""
    echo -e "    ${CYAN}Test:${NC} grep -E '^\\\$domainhosts|^\\\$APIKEY|^\\\$dbname|^\\\$usernamedb' $cfg_path"
    return 1
  fi

  PROJECT_DIR=$(viranaut_relocate_to_canonical_path "$PROJECT_DIR")
  PROJECT_DIR=$(mirza_sanitize_bot_dir "$PROJECT_DIR")
  CONFIG_FILE="$PROJECT_DIR/config.php"
  viranaut_ensure_apache_documentroot "$PROJECT_DIR" "$domain"

  if ! mirza_config_mysql_ok "$dbname" "$dbuser" "$dbpass"; then
    err "MySQL connection failed with credentials from config.php"
    return 1
  fi

  echo ""
  echo -e "  ${CYAN}Target:${NC} $PROJECT_DIR"
  echo -e "  ${CYAN}Domain:${NC} $domain"
  echo -e "  ${CYAN}Database:${NC} $dbname / $dbuser"
  echo ""
  read -p "  Run full auto-fix? (y/n) [y]: " _go
  _go=${_go,,}
  _go=${_go:-y}
  [ "$_go" = "y" ] || { msg "Cancelled."; return 0; }

  msg "Step 1/8 — MySQL ..."
  mirza_ensure_mysql_from_config "$dbname" "$dbuser" "$dbpass" || {
    err "MySQL failed (need root access)"
    return 1
  }
  echo -e "  ${GREEN}✓${NC} MySQL user/password from config.php"

  user_count=$(mirza_db_user_count "$dbname" "$dbuser" "$dbpass")
  if [ "$user_count" = "-1" ] || [ "$user_count" = "0" ]; then
    backup_zip=$(mirza_latest_backup_zip)
    if [ -n "$backup_zip" ]; then
      warn "DB empty — importing $(basename "$backup_zip")"
      mirza_import_database_from_zip "$backup_zip" "$dbname" "$dbuser" "$dbpass" || warn "Import failed"
      user_count=$(mirza_db_user_count "$dbname" "$dbuser" "$dbpass")
    else
      warn "No ZIP in $BACKUP_DIR — import database.sql manually"
    fi
  fi
  [ "$user_count" != "-1" ] && echo -e "  ${GREEN}✓${NC} Users in DB: $user_count"

  msg "Step 2/8 — DNS (domain must point to THIS server) ..."
  mirza_check_dns_matches_server "$domain" || warn "Fix DNS first — certbot/webhook will fail until A record is correct"

  backup_zip=$(mirza_latest_backup_zip)
  msg "Step 3/8 — Apache vhost (backup restore + SSL sites) ..."
  if [ -n "$backup_zip" ]; then
    echo -e "  ${CYAN}Backup:${NC} $(basename "$backup_zip")"
    mirza_restore_apache_vhosts_from_zip "$backup_zip" "$domain" "$PROJECT_DIR" || true
    mirza_restore_vendor_from_zip_if_missing "$backup_zip" "$PROJECT_DIR" || true
  fi
  viranaut_disable_stale_vhosts "$PROJECT_DIR" "$domain"
  mirza_enable_apache_for_bot "$domain" "$PROJECT_DIR" || return 1
  viranaut_ensure_apache_documentroot "$PROJECT_DIR" "$domain"
  mirza_rewrite_vhost_documentroot "$domain" "$PROJECT_DIR"
  local http_test
  http_test=$(curl -s -o /dev/null -w "%{http_code}" -H "Host: ${domain}" "http://127.0.0.1/index.php" 2>/dev/null || echo "000")
  [ "$http_test" != "404" ] && echo -e "  ${GREEN}✓${NC} HTTP test: $http_test" || err "Still 404 — check Apache"

  msg "Step 4/8 — SSL & firewall ..."
  mirza_fix_ssl_and_firewall "$domain" "$PROJECT_DIR" || true
  mirza_ensure_ssl_vhost "$domain" "$PROJECT_DIR"
  mirza_ufw_allow_telegram
  systemctl reload apache2 2>/dev/null || true

  msg "Step 5/8 — table.php + ViraNaut DB merge ..."
  if [ -f "$PROJECT_DIR/table.php" ]; then
    (cd "$PROJECT_DIR" && MIRZA_SKIP_WEBHOOK=1 php table.php 2>/dev/null) && echo -e "  ${GREEN}✓${NC} table.php"
    viranaut_db_migrate "$PROJECT_DIR"
  fi
  viranaut_ensure_panel_integrity "$PROJECT_DIR" 2>/dev/null || true

  msg "Step 6/8 — Cron ..."
  setup_cron_jobs && echo -e "  ${GREEN}✓${NC} Cron"

  msg "Step 7/8 — Permissions ..."
  mirza_ensure_bot_permissions "$PROJECT_DIR" && echo -e "  ${GREEN}✓${NC} Permissions"

  msg "Step 8/8 — Webhook (drop pending + verify) ..."
  mirza_restart_apache || true
  mirza_fix_webhook_complete "$token" "$domain" || warn "Webhook fix incomplete — check DNS/HTTPS then retry menu 8"

  line
  echo ""
  echo -e "  ${GREEN}${BOLD}✓ Auto-fix done${NC} — try /start @${bot_user}"
  echo -e "  Still broken? Menu ${BOLD}12${NC} diagnose, or: tail -f /var/log/apache2/${domain}-access.log"
  echo ""
}

# --- Backup ZIP helpers ---
mirza_has_backup_zips() {
  [ -d "$BACKUP_DIR" ] && ls "$BACKUP_DIR"/*.zip >/dev/null 2>&1
}

# Sets ZIP_PATH; returns 0 on success
mirza_select_backup_zip() {
  ZIP_PATH=""
  if mirza_has_backup_zips; then
    echo -e "  ${CYAN}Available backups in $BACKUP_DIR:${NC}"
    echo ""
    local i=1
    local files=()
    for f in "$BACKUP_DIR"/*.zip; do
      files+=("$f")
      echo -e "    ${BOLD}$i)${NC} $(basename "$f")  ($(du -h "$f" | cut -f1))"
      i=$((i + 1))
    done
    echo ""
    read -p "  Select backup number, or enter full path: " CHOICE
    if [[ "$CHOICE" =~ ^[0-9]+$ ]] && [ "$CHOICE" -ge 1 ] && [ "$CHOICE" -le "${#files[@]}" ]; then
      ZIP_PATH="${files[$((CHOICE - 1))]}"
    else
      ZIP_PATH="$CHOICE"
    fi
  else
    read -p "  Enter full path to backup ZIP file: " ZIP_PATH
  fi
  if [ ! -f "$ZIP_PATH" ]; then
    err "File not found: $ZIP_PATH"
    return 1
  fi
  return 0
}

mirza_install_files_from_package() {
  local PACKAGE="$1"
  local BOT_DIR="${2%/}"
  local TMP_EXTRACT SRC_DIR
  TMP_EXTRACT=$(mktemp -d)
  msg "Extracting $(basename "$PACKAGE") ..."
  SRC_DIR=$(mirza_extract_package "$PACKAGE" "$TMP_EXTRACT") || {
    rm -rf "$TMP_EXTRACT"
    return 1
  }
  msg "Installing bot files to $BOT_DIR ..."
  mkdir -p "$(dirname "$BOT_DIR")"
  rm -rf "$BOT_DIR"
  mkdir -p "$BOT_DIR"
  if [ -d "$SRC_DIR" ] && [ "$SRC_DIR" != "$BOT_DIR" ]; then
    cp -a "$SRC_DIR"/. "$BOT_DIR/"
  else
    cp -a "$TMP_EXTRACT"/. "$BOT_DIR/"
  fi
  rm -rf "$TMP_EXTRACT"
  chown -R www-data:www-data "$BOT_DIR"
  find "$BOT_DIR" -type d -exec chmod 755 {} \;
  find "$BOT_DIR" -type f -exec chmod 644 {} \;
  return 0
}

# ============================================================
#  14) RESTORE into existing install (no reinstall)
# ============================================================
do_restore_existing_bot() {
  line
  msg "Restore from backup ZIP — keep bot files, replace DB + data"
  resolve_project_paths
  if [ ! -f "$CONFIG_FILE" ]; then
    err "No bot at $PROJECT_DIR — use menu 1 (Install) first."
    return 1
  fi
  load_config
  echo -e "  ${CYAN}Target:${NC} $PROJECT_DIR"
  echo -e "  ${CYAN}DB:${NC} $DB_NAME / $DB_USER"
  echo ""
  mirza_select_backup_zip || return 1
  echo ""
  echo -e "  ${YELLOW}This will:${NC}"
  echo "    • Import database.sql from ZIP (overwrites DB data)"
  echo "    • Restore cronbot/, text.json if present in ZIP"
  echo "    • Optionally restore config.php / crontab / vhost from ZIP"
  echo "    • Set Telegram webhook again"
  echo ""
  read -p "  Continue restore? (y/n): " _r
  _r=${_r,,}
  [ "$_r" = "y" ] || { msg "Cancelled."; return 0; }

  TMP_DIR=$(mktemp -d)
  unzip -q "$ZIP_PATH" -d "$TMP_DIR" || { err "Bad ZIP"; rm -rf "$TMP_DIR"; return 1; }

  if mirza_import_database_from_dir "$TMP_DIR" "$DB_NAME" "$DB_USER" "$DB_PASS"; then
    echo -e "  ${GREEN}✓${NC} Database imported"
  else
    err "database.sql not found in ZIP"
    rm -rf "$TMP_DIR"
    return 1
  fi

  mirza_restore_bot_data_files "$PROJECT_DIR" "$TMP_DIR"

  if [ -f "$TMP_DIR/config.php" ]; then
    read -p "  Overwrite config.php from backup? (y/n) [n]: " _oc
    _oc=${_oc:-n}
    _oc=${_oc,,}
    if [ "$_oc" = "y" ]; then
      cp "$CONFIG_FILE" "${CONFIG_FILE}.bak.$(date +%s)"
      cp "$TMP_DIR/config.php" "$CONFIG_FILE"
      chown www-data:www-data "$CONFIG_FILE"
      echo -e "  ${GREEN}✓${NC} config.php restored"
      load_config
    fi
  fi

  if [ -f "$TMP_DIR/crontab.txt" ] && [ -s "$TMP_DIR/crontab.txt" ]; then
    read -p "  Replace entire crontab from backup? (y/n) [n]: " _cr
    _cr=${_cr:-n}
    _cr=${_cr,,}
    if [ "$_cr" = "y" ]; then
      crontab "$TMP_DIR/crontab.txt"
      echo -e "  ${GREEN}✓${NC} crontab restored"
    fi
  fi

  if [ -f "$PROJECT_DIR/table.php" ]; then
    msg "Running table.php ..."
    cd "$PROJECT_DIR"
    MIRZA_SKIP_WEBHOOK=1 php table.php 2>/dev/null || true
    viranaut_db_migrate "$PROJECT_DIR"
  fi

  PROJECT_DIR=$(viranaut_relocate_to_canonical_path "$PROJECT_DIR")
  PROJECT_DIR=$(mirza_sanitize_bot_dir "$PROJECT_DIR")
  CONFIG_FILE="$PROJECT_DIR/config.php"

  rm -rf "$TMP_DIR"
  MIRZA_DROP_PENDING_WEBHOOK=1 mirza_fix_webhook_complete "$BOT_TOKEN" "$DOMAIN" || mirza_reload_services "$PROJECT_DIR"
  line
  echo -e "  ${GREEN}${BOLD}✓ Restore on existing install complete${NC}"
  echo -e "  ${CYAN}Tip:${NC} Panel ZIP from backup.php works here (uses mysql on server)."
  echo ""
}

# ============================================================
#  Backup ZIP helpers (menu 1 + pre-update menu 8)
# ============================================================
# stdout: path to .zip; verbose=1 prints progress
viranaut_create_backup_zip() {
  local BOT_DIR="${1%/}"
  local NAME_PREFIX="${2:-$VIRANAUT_BACKUP_PREFIX}"
  local VERBOSE="${3:-0}"
  local CONFIG_PATH="$BOT_DIR/config.php"

  [ -f "$CONFIG_PATH" ] || {
    err "config.php not found at $CONFIG_PATH"
    return 1
  }

  local dbname dbuser dbpass domain admin_id bot_user
  dbname=$(read_php_var "dbname" "$CONFIG_PATH")
  dbuser=$(read_php_var "usernamedb" "$CONFIG_PATH")
  dbpass=$(read_php_var "passworddb" "$CONFIG_PATH")
  domain=$(mirza_normalize_domainhosts "$(read_php_var "domainhosts" "$CONFIG_PATH")")
  admin_id=$(read_php_var "adminnumber" "$CONFIG_PATH")
  bot_user=$(mirza_normalize_bot_username "$(read_php_var "usernamebot" "$CONFIG_PATH")")

  if [ -z "$dbname" ] || [ -z "$dbuser" ] || [ -z "$dbpass" ]; then
    err "Could not read database credentials from config.php"
    return 1
  fi

  mkdir -p "$BACKUP_DIR"
  local TIMESTAMP TMP_DIR BACKUP_NAME ZIP_PATH _vhost_saved=0 _cf
  TIMESTAMP=$(date +%Y%m%d_%H%M%S)
  TMP_DIR=$(mktemp -d)
  BACKUP_NAME="${NAME_PREFIX}_${TIMESTAMP}"

  if [ "$VERBOSE" = "1" ]; then
    msg "Dumping database: $dbname"
  fi
  if ! mysqldump -u "$dbuser" -p"$dbpass" --single-transaction --quick --no-tablespaces "$dbname" > "$TMP_DIR/database.sql" 2>/dev/null; then
    err "mysqldump failed. Check credentials."
    rm -rf "$TMP_DIR"
    return 1
  fi
  [ "$VERBOSE" = "1" ] && echo -e "  ${GREEN}✓${NC} Database dump OK  ($(du -h "$TMP_DIR/database.sql" | cut -f1))"

  if [ "$VERBOSE" = "1" ]; then
    msg "Saving cron jobs"
  fi
  crontab -l 2>/dev/null > "$TMP_DIR/crontab.txt" || echo "" > "$TMP_DIR/crontab.txt"
  [ "$VERBOSE" = "1" ] && echo -e "  ${GREEN}✓${NC} Cron saved"

  if [ "$VERBOSE" = "1" ]; then
    msg "Saving config.php"
  fi
  cp "$CONFIG_PATH" "$TMP_DIR/config.php"
  [ "$VERBOSE" = "1" ] && echo -e "  ${GREEN}✓${NC} config.php saved"

  if [ -f "/etc/apache2/sites-available/$VIRANAUT_VHOST_GENERIC" ]; then
    cp "/etc/apache2/sites-available/$VIRANAUT_VHOST_GENERIC" "$TMP_DIR/$VIRANAUT_VHOST_GENERIC"
    [ "$VERBOSE" = "1" ] && echo -e "  ${GREEN}✓${NC} Apache VirtualHost saved ($VIRANAUT_VHOST_GENERIC)"
    _vhost_saved=1
  fi
  if [ -f "/etc/apache2/sites-available/$VIRANAUT_VHOST_LEGACY" ]; then
    cp "/etc/apache2/sites-available/$VIRANAUT_VHOST_LEGACY" "$TMP_DIR/$VIRANAUT_VHOST_LEGACY"
    [ "$VERBOSE" = "1" ] && echo -e "  ${GREEN}✓${NC} Apache VirtualHost saved ($VIRANAUT_VHOST_LEGACY, legacy)"
    _vhost_saved=1
  fi
  if [ -n "$domain" ] && [ -f "/etc/apache2/sites-available/${domain}.conf" ]; then
    cp "/etc/apache2/sites-available/${domain}.conf" "$TMP_DIR/${domain}.conf"
    [ "$VERBOSE" = "1" ] && echo -e "  ${GREEN}✓${NC} Apache VirtualHost saved (${domain}.conf)"
    _vhost_saved=1
  fi
  if [ -n "$domain" ] && [ -f "/etc/apache2/sites-available/${domain}-ssl.conf" ]; then
    cp "/etc/apache2/sites-available/${domain}-ssl.conf" "$TMP_DIR/${domain}-ssl.conf"
    [ "$VERBOSE" = "1" ] && echo -e "  ${GREEN}✓${NC} Apache SSL vhost saved (${domain}-ssl.conf)"
    _vhost_saved=1
  fi
  if [ -n "$domain" ] && [ -f "/etc/apache2/sites-available/${domain}-le-ssl.conf" ]; then
    cp "/etc/apache2/sites-available/${domain}-le-ssl.conf" "$TMP_DIR/${domain}-le-ssl.conf"
    [ "$VERBOSE" = "1" ] && echo -e "  ${GREEN}✓${NC} Apache SSL vhost saved (${domain}-le-ssl.conf)"
    _vhost_saved=1
  fi
  if [ "$VERBOSE" = "1" ] && [ "$_vhost_saved" -eq 0 ]; then
    warn "No Apache site config found under sites-available (optional)."
  fi

  if [ -d "$BOT_DIR/cronbot" ]; then
    mkdir -p "$TMP_DIR/cronbot"
    for _cf in "$BOT_DIR/cronbot"/*; do
      [ -f "$_cf" ] || continue
      [[ "$(basename "$_cf")" == *.php ]] && continue
      cp "$_cf" "$TMP_DIR/cronbot/"
    done
    [ "$VERBOSE" = "1" ] && echo -e "  ${GREEN}✓${NC} cronbot data files saved"
  fi
  [ -f "$BOT_DIR/text.json" ] && cp "$BOT_DIR/text.json" "$TMP_DIR/text.json"
  [ "$VERBOSE" = "1" ] && [ -f "$BOT_DIR/text.json" ] && echo -e "  ${GREEN}✓${NC} text.json saved"
  [ -f "$BOT_DIR/version" ] && cp "$BOT_DIR/version" "$TMP_DIR/version"
  mirza_write_meta_cron_jobs "$TMP_DIR/meta/cron_jobs.txt" "$domain"

  cat > "$TMP_DIR/backup_info.txt" <<INFO
ViraNaut Backup
Created: $(date -u '+%Y-%m-%d %H:%M:%S UTC')
Domain:  $domain
DB Name: $dbname
DB User: $dbuser
Admin:   $admin_id
Bot:     @$bot_user
INFO

  cat > "$TMP_DIR/manifest.json" <<MANIFEST
{"format":"viranaut-manage-backup","version":1,"created_at":"$(date -u +%Y-%m-%dT%H:%M:%SZ)","domain":"$domain","path":"$BOT_DIR","kind":"$NAME_PREFIX"}
MANIFEST

  apt-get install -y zip >/dev/null 2>&1 || true
  ZIP_PATH="$BACKUP_DIR/${BACKUP_NAME}.zip"
  if [ "$VERBOSE" = "1" ]; then
    msg "Creating ZIP archive ..."
  fi
  if ! (cd "$TMP_DIR" && zip -r "$ZIP_PATH" . >/dev/null 2>&1); then
    err "Failed to create ZIP at $ZIP_PATH"
    rm -rf "$TMP_DIR"
    return 1
  fi
  [ "$VERBOSE" = "1" ] && echo -e "  ${GREEN}✓${NC} ZIP: database.sql, config.php, crontab.txt, cronbot/, text.json, meta/cron_jobs.txt, Apache conf"
  rm -rf "$TMP_DIR"
  printf '%s' "$ZIP_PATH"
  return 0
}

viranaut_prune_backup_zips() {
  local prefix="$1"
  local keep="${2:-3}"
  local -a sorted=()
  local f removed=0

  [ -d "$BACKUP_DIR" ] || return 0
  while IFS= read -r f; do
    [ -n "$f" ] && sorted+=("$f")
  done < <(ls -1t "$BACKUP_DIR/${prefix}"_*.zip 2>/dev/null || true)

  local i
  for (( i=keep; i<${#sorted[@]}; i++ )); do
    rm -f "${sorted[$i]}"
    echo -e "  ${YELLOW}●${NC} Removed old backup: $(basename "${sorted[$i]}")" >&2
    removed=$((removed + 1))
  done
  return 0
}

viranaut_list_preupdate_backups() {
  local -a files=()
  local f i=1

  [ -d "$BACKUP_DIR" ] || return 0
  while IFS= read -r f; do
    [ -n "$f" ] && files+=("$f")
  done < <(ls -1t "$BACKUP_DIR/${VIRANAUT_PREUPDATE_PREFIX}"_*.zip 2>/dev/null || true)
  [ ${#files[@]} -gt 0 ] || return 0

  echo -e "  ${CYAN}Pre-update backups kept (max ${VIRANAUT_PREUPDATE_KEEP}):${NC}"
  for f in "${files[@]}"; do
    echo -e "    ${BOLD}$i)${NC} $(basename "$f")  ($(du -h "$f" | cut -f1))"
    i=$((i + 1))
  done
}

viranaut_preupdate_backup() {
  local BOT_DIR="${1%/}"
  local zip

  msg "Pre-update backup (database + config + cron + vhost + data) ..."
  zip=$(viranaut_create_backup_zip "$BOT_DIR" "$VIRANAUT_PREUPDATE_PREFIX" 0) || return 1
  echo -e "  ${GREEN}✓${NC} Backup: $zip  ($(du -h "$zip" | cut -f1))"
  viranaut_prune_backup_zips "$VIRANAUT_PREUPDATE_PREFIX" "$VIRANAUT_PREUPDATE_KEEP"
  return 0
}

# ============================================================
#  1) BACKUP
# ============================================================
do_backup() {
  msg "Starting full backup ..."
  load_config
  line

  local ZIP_PATH
  ZIP_PATH=$(viranaut_create_backup_zip "$PROJECT_DIR" "$VIRANAUT_BACKUP_PREFIX" 1) || return 1

  line
  echo ""
  echo -e "  ${GREEN}${BOLD}✓ Backup created successfully!${NC}"
  echo -e "  ${CYAN}ZIP file:${NC} $ZIP_PATH"
  echo -e "  ${CYAN}Bot on server:${NC} $PROJECT_DIR/config.php"
  echo -e "  ${CYAN}Size:${NC} $(du -h "$ZIP_PATH" | cut -f1)"
  echo ""
  echo -e "  Copy to another server with:"
  echo -e "  ${BOLD}scp $ZIP_PATH root@NEW_SERVER_IP:/root/${NC}"
  echo ""
}

# ============================================================
#  RESTORE (called from Local install — not a separate menu item)
# ============================================================
do_restore_from_backup() {
  local PRESET_ZIP="${1:-}"
  local PRESET_BOT_DIR="${2:-}"

  if [ -n "$PRESET_BOT_DIR" ]; then
    PROJECT_DIR="${PRESET_BOT_DIR%/}"
    CONFIG_FILE="$PROJECT_DIR/config.php"
  else
    resolve_project_paths
  fi

  install_dependencies

  if [ -n "$PRESET_ZIP" ]; then
    ZIP_PATH="$PRESET_ZIP"
  else
    echo ""
    mirza_select_backup_zip || return 1
  fi

  echo ""
  msg "Extracting: $(basename "$ZIP_PATH")"
  TMP_DIR=$(mktemp -d)
  unzip -q "$ZIP_PATH" -d "$TMP_DIR"

  # --- Show backup info ---
  if [ -f "$TMP_DIR/backup_info.txt" ]; then
    echo ""
    echo -e "  ${CYAN}Backup info:${NC}"
    sed 's/^/    /' "$TMP_DIR/backup_info.txt"
    echo ""
  fi
  BK_INFO_DOMAIN=""
  if [ -f "$TMP_DIR/backup_info.txt" ]; then
    BK_INFO_DOMAIN=$(grep -E '^Domain:[[:space:]]*' "$TMP_DIR/backup_info.txt" 2>/dev/null | head -1 | sed 's/^Domain:[[:space:]]*//')
    BK_INFO_DOMAIN=$(mirza_normalize_domainhosts "$BK_INFO_DOMAIN")
  fi

  local PANEL_ZIP=0
  if mirza_backup_zip_is_panel "$TMP_DIR"; then
    PANEL_ZIP=1
    echo -e "  ${CYAN}●${NC} Panel backup (mirza-full-backup) detected"
  fi

  # --- Read config from backup ---
  if [ -f "$TMP_DIR/config.php" ]; then
    BACKUP_CONFIG="$TMP_DIR/config.php"
    BK_DB_NAME=$(read_php_var "dbname" "$BACKUP_CONFIG")
    BK_DB_USER=$(read_php_var "usernamedb" "$BACKUP_CONFIG")
    BK_DB_PASS=$(read_php_var "passworddb" "$BACKUP_CONFIG")
    BK_BOT_TOKEN=$(read_php_var "APIKEY" "$BACKUP_CONFIG")
    BK_ADMIN_ID=$(read_php_var "adminnumber" "$BACKUP_CONFIG")
    BK_BOT_USERNAME=$(read_php_var "usernamebot" "$BACKUP_CONFIG")
    BK_BOT_USERNAME=$(mirza_normalize_bot_username "$BK_BOT_USERNAME")
    BK_DOMAIN_RAW=$(read_php_var "domainhosts" "$BACKUP_CONFIG")
    BK_DOMAIN=$(mirza_normalize_domainhosts "$BK_DOMAIN_RAW")
    if [ -z "$BK_DOMAIN" ] && [ -n "$BK_INFO_DOMAIN" ]; then
      BK_DOMAIN="$BK_INFO_DOMAIN"
    fi
  elif [ "$PANEL_ZIP" = "1" ] && [ -f "$CONFIG_FILE" ]; then
    msg "Using current install config.php for database (panel backup has no config) ..."
    load_config
    BK_DB_NAME="$DB_NAME"
    BK_DB_USER="$DB_USER"
    BK_DB_PASS="$DB_PASS"
    BK_BOT_TOKEN="$BOT_TOKEN"
    BK_ADMIN_ID="$ADMIN_ID"
    BK_BOT_USERNAME="$BOT_USERNAME"
    BK_DOMAIN="$DOMAIN"
  else
    warn "No config.php found in backup. Enter values manually:"
    read -p "  Database name: " BK_DB_NAME
    read -p "  Database user: " BK_DB_USER
    read -sp "  Database password: " BK_DB_PASS
    echo ""
    read -p "  Domain (e.g. bot.example.com): " BK_DOMAIN
    BK_DOMAIN=$(mirza_normalize_domainhosts "$BK_DOMAIN")
    read -p "  Bot token: " BK_BOT_TOKEN
    read -p "  Admin ID: " BK_ADMIN_ID
    read -p "  Bot username (with or without @): " BK_BOT_USERNAME
    BK_BOT_USERNAME=$(mirza_normalize_bot_username "$BK_BOT_USERNAME")
  fi

  if [ -z "$BK_DB_NAME" ] || [ -z "$BK_DB_USER" ] || [ -z "$BK_DB_PASS" ]; then
    err "Cannot determine database credentials."
    rm -rf "$TMP_DIR"
    return 1
  fi

  echo ""
  echo -e "  ${CYAN}Restore summary:${NC}"
  echo -e "    Domain:   ${BOLD}${BK_DOMAIN:-unknown}${NC}"
  echo -e "    Database: ${BOLD}$BK_DB_NAME${NC} / $BK_DB_USER"
  echo -e "    Bot:      ${BOLD}@${BK_BOT_USERNAME:-unknown}${NC}"
  echo -e "    Admin:    $BK_ADMIN_ID"
  echo ""
  if [ -n "$PRESET_BOT_DIR" ] && [ -n "$PRESET_ZIP" ]; then
    msg "Restoring from backup into $PROJECT_DIR ..."
  else
    echo -e "  ${YELLOW}This will:${NC}"
    echo -e "    • Install dependencies (Apache/PHP/MySQL) if missing"
    echo -e "    • Prepare bot files if missing"
    echo -e "    • Create database & user, import data"
    echo -e "    • Restore config.php, cron jobs, Apache config"
    echo -e "    • Set Telegram webhook"
    echo ""
    read -p "  Continue? (y/n): " CONFIRM
    CONFIRM=${CONFIRM,,}
    if [ "$CONFIRM" != "y" ]; then
      echo "  Cancelled."
      rm -rf "$TMP_DIR"
      return 0
    fi
  fi

  # --- Step 1: Project files ---
  mkdir -p "$(dirname "$PROJECT_DIR")"
  if [ -f "$PROJECT_DIR/index.php" ]; then
    echo -e "  ${GREEN}✓${NC} Bot files ready at $PROJECT_DIR"
  elif [ -n "$PRESET_BOT_DIR" ]; then
    err "Bot files missing at $PROJECT_DIR — run Local install with a package archive first."
    rm -rf "$TMP_DIR"
    return 1
  else
    msg "Project files not found. Cloning from GitHub ..."
    mkdir -p /var/www
    if command -v git >/dev/null 2>&1; then
      git clone https://github.com/mahdiMGF2/mirza_pro.git "$PROJECT_DIR" 2>/dev/null || true
    fi
    if [ ! -d "$PROJECT_DIR" ]; then
      mkdir -p "$PROJECT_DIR"
    fi
    chown -R www-data:www-data "$PROJECT_DIR"
    echo -e "  ${GREEN}✓${NC} Project files ready"
  fi
  mirza_apply_php_core_fixes "$PROJECT_DIR"

  # --- Step 2: Create DB & user ---
  msg "Creating database and user ..."
  BK_DB_PASS_SQL="${BK_DB_PASS//\'/\'\'}"
  mysql -u root <<SQL
CREATE DATABASE IF NOT EXISTS \`$BK_DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$BK_DB_USER'@'localhost' IDENTIFIED BY '${BK_DB_PASS_SQL}';
GRANT ALL PRIVILEGES ON \`$BK_DB_NAME\`.* TO '$BK_DB_USER'@'localhost';
FLUSH PRIVILEGES;
SQL
  echo -e "  ${GREEN}✓${NC} Database & user ready"

  # --- Step 3: Import database ---
  if mirza_import_database_from_dir "$TMP_DIR" "$BK_DB_NAME" "$BK_DB_USER" "$BK_DB_PASS"; then
    echo -e "  ${GREEN}✓${NC} Database imported"
  else
    warn "No database.sql in backup. Skipping DB import."
  fi

  # --- Panel / manage extra files (cronbot queue, text.json) ---
  mirza_restore_bot_data_files "$PROJECT_DIR" "$TMP_DIR"

  # --- Step 4: Restore config.php ---
  if [ -f "$TMP_DIR/config.php" ]; then
    msg "Restoring config.php ..."
    if [ -f "$CONFIG_FILE" ]; then
      cp "$CONFIG_FILE" "${CONFIG_FILE}.bak.$(date +%s)"
    fi
    mkdir -p "$PROJECT_DIR"
    cp "$TMP_DIR/config.php" "$CONFIG_FILE"
    chown www-data:www-data "$CONFIG_FILE"
    echo -e "  ${GREEN}✓${NC} config.php restored"
  fi

  # --- Step 5: Run table.php (create/update tables) ---
  if [ -f "$PROJECT_DIR/table.php" ]; then
    msg "Running table.php (create/update database tables) ..."
    cd "$PROJECT_DIR"
    MIRZA_SKIP_WEBHOOK=1 php table.php 2>/dev/null || warn "table.php had some warnings (usually OK)."
    echo -e "  ${GREEN}✓${NC} Database tables ready"
    viranaut_db_migrate "$PROJECT_DIR"
  fi

  # --- Step 6: Apache VirtualHost ---
  if [ -n "$BK_DOMAIN" ] && [ -f "$TMP_DIR/${BK_DOMAIN}.conf" ]; then
    msg "Restoring Apache VirtualHost (${BK_DOMAIN}.conf, same as official install.sh) ..."
    cp "$TMP_DIR/${BK_DOMAIN}.conf" "/etc/apache2/sites-available/${BK_DOMAIN}.conf"
    mirza_sanitize_vhost_conf "/etc/apache2/sites-available/${BK_DOMAIN}.conf"
    if [ -f "$TMP_DIR/${BK_DOMAIN}-ssl.conf" ]; then
      cp "$TMP_DIR/${BK_DOMAIN}-ssl.conf" "/etc/apache2/sites-available/${BK_DOMAIN}-ssl.conf"
      mirza_sanitize_vhost_conf "/etc/apache2/sites-available/${BK_DOMAIN}-ssl.conf"
      if mirza_ssl_cert_exists "$BK_DOMAIN"; then
        mirza_a2ensite "${BK_DOMAIN}-ssl.conf" 2>/dev/null || true
      else
        mirza_a2dissite "${BK_DOMAIN}-ssl.conf" 2>/dev/null || true
      fi
    fi
    mirza_a2ensite "${BK_DOMAIN}.conf" 2>/dev/null || true
  elif [ -f "$TMP_DIR/$VIRANAUT_VHOST_GENERIC" ]; then
    msg "Restoring Apache VirtualHost ($VIRANAUT_VHOST_GENERIC) ..."
    cp "$TMP_DIR/$VIRANAUT_VHOST_GENERIC" "/etc/apache2/sites-available/$VIRANAUT_VHOST_GENERIC"
    mirza_a2ensite "$VIRANAUT_VHOST_GENERIC" 2>/dev/null || true
  elif [ -f "$TMP_DIR/$VIRANAUT_VHOST_LEGACY" ]; then
    msg "Restoring Apache VirtualHost ($VIRANAUT_VHOST_LEGACY, legacy) ..."
    cp "$TMP_DIR/$VIRANAUT_VHOST_LEGACY" "/etc/apache2/sites-available/$VIRANAUT_VHOST_LEGACY"
    mirza_a2ensite "$VIRANAUT_VHOST_LEGACY" 2>/dev/null || true
  elif [ -n "$BK_DOMAIN" ]; then
    if mirza_vhost_use_domain_conf; then
      msg "Creating Apache VirtualHost for $BK_DOMAIN ($BK_DOMAIN.conf) ..."
      VHOST_BASENAME="${BK_DOMAIN}.conf"
    else
      msg "Creating Apache VirtualHost for $BK_DOMAIN ($VIRANAUT_VHOST_GENERIC) ..."
      VHOST_BASENAME="$VIRANAUT_VHOST_GENERIC"
    fi
    cat > "/etc/apache2/sites-available/${VHOST_BASENAME}" <<VHOST
<VirtualHost *:80>
    ServerName $BK_DOMAIN
    DocumentRoot $PROJECT_DIR

    <Directory $PROJECT_DIR>
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog \${APACHE_LOG_DIR}/${VIRANAUT_LOG_ERROR}
    CustomLog \${APACHE_LOG_DIR}/${VIRANAUT_LOG_ACCESS} combined
</VirtualHost>
VHOST
    mirza_a2ensite "$VHOST_BASENAME" 2>/dev/null || true
  fi
  mirza_a2dissite 000-default.conf 2>/dev/null || true
  a2enmod rewrite 2>/dev/null || true
  if [ -n "$BK_DOMAIN" ]; then
    mirza_rewrite_vhost_documentroot "$BK_DOMAIN" "$PROJECT_DIR"
    mirza_ensure_ssl_vhost "$BK_DOMAIN" "$PROJECT_DIR"
  fi
  systemctl restart apache2
  echo -e "  ${GREEN}✓${NC} Apache configured"

  # --- Step 7: Restore cron jobs ---
  if [ -f "$TMP_DIR/crontab.txt" ] && [ -s "$TMP_DIR/crontab.txt" ]; then
    msg "Restoring cron jobs ..."
    crontab "$TMP_DIR/crontab.txt"
    echo -e "  ${GREEN}✓${NC} Cron jobs restored"
  elif [ -f "$TMP_DIR/meta/cron_jobs.txt" ] && [ -s "$TMP_DIR/meta/cron_jobs.txt" ]; then
    warn "Backup has meta/cron_jobs.txt but no crontab.txt"
    echo -e "  ${CYAN}Install cron lines manually:${NC} crontab -e"
    echo -e "  ${CYAN}Reference:${NC} $TMP_DIR/meta/cron_jobs.txt"
    setup_cron_jobs
    echo -e "  ${GREEN}✓${NC} Default ViraNaut cron jobs installed (from script)"
  else
    msg "Setting up default cron jobs ..."
    setup_cron_jobs
    echo -e "  ${GREEN}✓${NC} Default cron jobs installed"
  fi

  # --- Step 8: SSL ---
  if [ -n "$BK_DOMAIN" ]; then
    echo ""
    read -p "  Install SSL for $BK_DOMAIN? (y/n) [n]: " DO_SSL
    DO_SSL=${DO_SSL:-n}
    DO_SSL=${DO_SSL,,}
    if [ "$DO_SSL" == "y" ]; then
      msg "Installing SSL ..."
      apt-get install -y certbot python3-certbot-apache >/dev/null 2>&1 || true
      if certbot --apache -d "$BK_DOMAIN" --non-interactive --agree-tos --register-unsafely-without-email --redirect; then
        # Update config to https
        sed -i "s|'http://$BK_DOMAIN'|'https://$BK_DOMAIN'|g" "$CONFIG_FILE" 2>/dev/null || true
        echo -e "  ${GREEN}✓${NC} SSL installed"
      else
        warn "SSL failed. Check DNS and port 80."
        warn "Retry later: certbot --apache -d $BK_DOMAIN"
      fi
      mirza_rewrite_vhost_documentroot "$BK_DOMAIN" "$PROJECT_DIR"
      mirza_ensure_ssl_vhost "$BK_DOMAIN" "$PROJECT_DIR"
      systemctl reload apache2 2>/dev/null || true
    fi
  fi

  rm -rf "$TMP_DIR"
  mirza_save_active_dir "$PROJECT_DIR"

  if [ -z "$PRESET_BOT_DIR" ]; then
    mirza_reload_services "$PROJECT_DIR"
    line
    echo ""
    echo -e "  ${GREEN}${BOLD}✓ Restore completed!${NC}"
    echo ""
    echo -e "  ${CYAN}What was done:${NC}"
    echo "    ✓ Dependencies installed (if needed)"
    echo "    ✓ Project files ready"
    echo "    ✓ Database created & imported"
    echo "    ✓ config.php restored"
    echo "    ✓ Apache configured"
    echo "    ✓ Cron jobs restored"
    echo "    ✓ Webhook set"
    echo ""
    echo -e "  ${YELLOW}If you need to change domain/token/DB:${NC}"
    echo "    → Use menu option 5 (New Configure Bot)"
    echo ""
    echo -e "  Test: send /start to @${BK_BOT_USERNAME:-your_bot} in Telegram"
    echo ""
  fi
}

# ============================================================
#  3) STOP APACHE
# ============================================================
do_stop_apache() {
  msg "Stopping Apache ..."
  systemctl stop apache2
  echo -e "  ${GREEN}✓${NC} Apache stopped"
  echo ""
  systemctl status apache2 --no-pager -l 2>/dev/null | head -5 || true
  echo ""
}

# ============================================================
#  4) START APACHE
# ============================================================
do_start_apache() {
  msg "Starting Apache ..."
  systemctl start apache2
  echo -e "  ${GREEN}✓${NC} Apache started"
  echo ""
  systemctl status apache2 --no-pager -l 2>/dev/null | head -5 || true
  echo ""
}

# ============================================================
#  RESTART APACHE
# ============================================================
do_restart_apache() {
  mirza_restart_apache
  echo ""
}

do_restart_full() {
  line
  msg "Full restart — MySQL + Apache + bot webhook refresh"
  systemctl restart mysql 2>/dev/null || service mysql restart 2>/dev/null || warn "MySQL restart skipped"
  mirza_restart_apache
  resolve_project_paths
  if [ -f "$CONFIG_FILE" ]; then
    local _dom _tok
    _dom=$(mirza_normalize_domainhosts "$(read_php_var "domainhosts")")
    _tok=$(read_php_var "APIKEY")
    [ -n "$_tok" ] && [ -n "$_dom" ] && mirza_fix_webhook_complete "$_tok" "$_dom" || true
  fi
  echo -e "  ${GREEN}✓${NC} Full restart complete"
  echo ""
}

mirza_remove_cron_for_project() {
  local BOT_DIR="${1%/}"
  local TMP_CRON
  TMP_CRON=$(mktemp)
  crontab -l 2>/dev/null | grep -Fv "$BOT_DIR" >"$TMP_CRON" || true
  crontab "$TMP_CRON" 2>/dev/null || true
  rm -f "$TMP_CRON"
}

mirza_remove_apache_vhosts_for_bot() {
  local BOT_DIR="${1%/}"
  local domain="$2"
  local _vf
  for _vf in "$VIRANAUT_VHOST_GENERIC" "$VIRANAUT_VHOST_LEGACY"; do
    if [ -f "/etc/apache2/sites-available/$_vf" ]; then
      if grep -qF "$BOT_DIR" "/etc/apache2/sites-available/$_vf" 2>/dev/null; then
        mirza_a2dissite "$_vf" 2>/dev/null || true
        rm -f "/etc/apache2/sites-enabled/$_vf"
        rm -f "/etc/apache2/sites-available/$_vf"
        echo -e "  ${GREEN}✓${NC} Removed $_vf"
      fi
    fi
  done
  if [ -n "$domain" ]; then
    mirza_a2dissite "${domain}.conf" 2>/dev/null || true
    mirza_a2dissite "${domain}-ssl.conf" 2>/dev/null || true
    rm -f "/etc/apache2/sites-enabled/${domain}.conf"
    rm -f "/etc/apache2/sites-enabled/${domain}-ssl.conf"
    rm -f "/etc/apache2/sites-available/${domain}.conf"
    rm -f "/etc/apache2/sites-available/${domain}-ssl.conf"
    echo -e "  ${GREEN}✓${NC} Removed Apache vhost for $domain"
  fi
}

mirza_drop_bot_database() {
  local db="$1" dbuser="$2"
  [ -n "$db" ] || return 0
  msg "Dropping database: $db"
  mysql -u root -e "DROP DATABASE IF EXISTS \`$db\`;" 2>/dev/null \
    && echo -e "  ${GREEN}✓${NC} Database dropped" \
    || warn "Could not drop database $db"
  if [ -n "$dbuser" ]; then
    mysql -u root -e "DROP USER IF EXISTS '$dbuser'@'localhost'; FLUSH PRIVILEGES;" 2>/dev/null \
      && echo -e "  ${GREEN}✓${NC} DB user removed: $dbuser" \
      || warn "Could not drop user $dbuser"
  fi
}

# ============================================================
#  FULL REMOVE BOT
# ============================================================
do_full_remove_bot() {
  line
  msg "Full remove — ViraNaut bot (irreversible)"
  echo ""

  resolve_project_paths
  local REMOVE_DIR="$PROJECT_DIR"

  if [ ${#MIRZA_ALL_INSTALLS[@]} -gt 1 ]; then
    mirza_list_installations
    echo -e "  ${CYAN}Which installation to remove?${NC}"
    read -p "  Number [active=1]: " _pick
    _pick=${_pick:-1}
    if ! [[ "$_pick" =~ ^[0-9]+$ ]] || [ "$_pick" -lt 1 ] || [ "$_pick" -gt "${#MIRZA_ALL_INSTALLS[@]}" ]; then
      err "Invalid choice."
      return 1
    fi
    REMOVE_DIR="${MIRZA_ALL_INSTALLS[$((_pick - 1))]}"
  elif [ ${#MIRZA_ALL_INSTALLS[@]} -eq 1 ]; then
    REMOVE_DIR="${MIRZA_ALL_INSTALLS[0]}"
  fi

  REMOVE_DIR="${REMOVE_DIR%/}"
  if [ ! -d "$REMOVE_DIR" ]; then
    err "Directory not found: $REMOVE_DIR"
    return 1
  fi

  PROJECT_DIR="$REMOVE_DIR"
  CONFIG_FILE="$REMOVE_DIR/config.php"

  local DB_NAME="" DB_USER="" DB_PASS="" BOT_TOKEN="" DOMAIN=""
  if [ -f "$CONFIG_FILE" ]; then
    DB_NAME=$(read_php_var "dbname")
    DB_USER=$(read_php_var "usernamedb")
    DB_PASS=$(read_php_var "passworddb")
    BOT_TOKEN=$(read_php_var "APIKEY")
    DOMAIN=$(read_php_var "domainhosts")
    DOMAIN=$(mirza_normalize_domainhosts "$DOMAIN")
  fi

  echo -e "  ${CYAN}Target:${NC}  ${BOLD}$REMOVE_DIR${NC}"
  [ -n "$DOMAIN" ] && echo -e "  ${CYAN}Domain:${NC}  $DOMAIN"
  [ -n "$DB_NAME" ] && echo -e "  ${CYAN}Database:${NC} $DB_NAME / ${DB_USER:-?}"
  echo ""

  read -p "  Create backup before removal? (y/n) [y]: " _want_bk
  _want_bk=${_want_bk:-y}
  _want_bk=${_want_bk,,}
  if [ "$_want_bk" = "y" ]; then
    mirza_save_active_dir "$REMOVE_DIR"
    PROJECT_DIR="$REMOVE_DIR"
    CONFIG_FILE="$REMOVE_DIR/config.php"
    if do_backup; then
      echo -e "  ${GREEN}✓${NC} Backup saved under $BACKUP_DIR"
    else
      warn "Backup failed."
      read -p "  Continue removal without backup? (y/n) [n]: " _cont
      _cont=${_cont,,}
      [ "$_cont" = "y" ] || { msg "Cancelled."; return 0; }
    fi
    echo ""
  fi

  echo -e "  ${RED}${BOLD}This will permanently delete:${NC}"
  echo "    • Bot files: $REMOVE_DIR"
  [ -n "$DB_NAME" ] && echo "    • MySQL database: $DB_NAME"
  [ -n "$DB_USER" ] && echo "    • MySQL user: $DB_USER"
  echo "    • ViraNaut cron jobs for this path"
  echo "    • Apache VirtualHost (if tied to this bot)"
  [ -n "$BOT_TOKEN" ] && echo "    • Telegram webhook (deleteWebhook)"
  echo ""
  read -p "  Proceed with full removal? (y/n) [n]: " _confirm
  _confirm=${_confirm,,}
  if [ "$_confirm" != "y" ]; then
    msg "Cancelled — nothing was removed."
    return 0
  fi

  line
  if [ -n "$BOT_TOKEN" ]; then
    msg "Removing Telegram webhook ..."
    curl -s "https://api.telegram.org/bot${BOT_TOKEN}/deleteWebhook" >/dev/null 2>&1 || true
    echo -e "  ${GREEN}✓${NC} Webhook cleared"
  fi

  msg "Removing cron jobs ..."
  mirza_remove_cron_for_project "$REMOVE_DIR"

  msg "Removing Apache site config ..."
  mirza_remove_apache_vhosts_for_bot "$REMOVE_DIR" "$DOMAIN"

  if [ -n "$DB_NAME" ]; then
    mirza_drop_bot_database "$DB_NAME" "$DB_USER"
  else
    warn "No database name in config — skipping DB drop."
  fi

  msg "Deleting bot files ..."
  rm -rf "$REMOVE_DIR"
  echo -e "  ${GREEN}✓${NC} Removed $REMOVE_DIR"

  if [ -f "$MIRZA_STATE_FILE" ]; then
    local _saved
    _saved=$(tr -d '\r\n' <"$MIRZA_STATE_FILE")
    _saved="${_saved%/}"
    if [ "$_saved" = "$REMOVE_DIR" ]; then
      rm -f "$MIRZA_STATE_FILE"
    fi
  fi

  mirza_restart_apache || true

  line
  echo ""
  echo -e "  ${GREEN}${BOLD}✓ Full removal complete.${NC}"
  echo -e "  ${CYAN}Removed:${NC} $REMOVE_DIR"
  if [ "$_want_bk" = "y" ]; then
    echo -e "  ${CYAN}Backup:${NC} check $BACKUP_DIR"
  fi
  echo ""
}

# ============================================================
#  5) NEW CONFIGURE BOT
# ============================================================
do_configure() {
  msg "Reconfigure ViraNaut Bot"
  line

  # Make sure dependencies exist
  install_dependencies
  resolve_project_paths
  mkdir -p "$PROJECT_DIR"

  # Load current values
  if [ -f "$CONFIG_FILE" ]; then
    CUR_DB_NAME=$(read_php_var "dbname")
    CUR_DB_USER=$(read_php_var "usernamedb")
    CUR_DB_PASS=$(read_php_var "passworddb")
    CUR_BOT_TOKEN=$(read_php_var "APIKEY")
    CUR_ADMIN_ID=$(read_php_var "adminnumber")
    CUR_BOT_USERNAME=$(mirza_normalize_bot_username "$(read_php_var "usernamebot")")
    CUR_DOMAIN_RAW=$(read_php_var "domainhosts")
    CUR_DOMAIN=$(mirza_normalize_domainhosts "$CUR_DOMAIN_RAW")
  fi

  echo ""
  echo -e "  ${CYAN}Current config (press Enter to keep):${NC}"
  echo ""

  read -p "  Domain [$CUR_DOMAIN]: " NEW_DOMAIN
  NEW_DOMAIN=${NEW_DOMAIN:-$CUR_DOMAIN}
  NEW_DOMAIN=$(mirza_normalize_domainhosts "$NEW_DOMAIN")

  read -p "  Database name [$CUR_DB_NAME]: " NEW_DB_NAME
  NEW_DB_NAME=${NEW_DB_NAME:-$CUR_DB_NAME}

  read -p "  Database user [$CUR_DB_USER]: " NEW_DB_USER
  NEW_DB_USER=${NEW_DB_USER:-$CUR_DB_USER}

  read -sp "  Database password [hidden, Enter=keep]: " NEW_DB_PASS
  echo ""
  NEW_DB_PASS=${NEW_DB_PASS:-$CUR_DB_PASS}

  read -p "  Bot token [${CUR_BOT_TOKEN:0:15}...]: " NEW_BOT_TOKEN
  NEW_BOT_TOKEN=${NEW_BOT_TOKEN:-$CUR_BOT_TOKEN}

  read -p "  Admin ID [$CUR_ADMIN_ID]: " NEW_ADMIN_ID
  NEW_ADMIN_ID=${NEW_ADMIN_ID:-$CUR_ADMIN_ID}

  read -p "  Bot username (with or without @) [$CUR_BOT_USERNAME]: " NEW_BOT_USERNAME
  NEW_BOT_USERNAME=${NEW_BOT_USERNAME:-$CUR_BOT_USERNAME}
  NEW_BOT_USERNAME=$(mirza_normalize_bot_username "$NEW_BOT_USERNAME")

  read -p "  Install SSL? (y/n) [n]: " DO_SSL
  DO_SSL=${DO_SSL:-n}
  DO_SSL=${DO_SSL,,}

  # Determine protocol
  if [ "$DO_SSL" == "y" ]; then
    PROTOCOL="https"
  else
    PROTOCOL="http"
  fi

  echo ""
  echo -e "  ${CYAN}Review:${NC}"
  echo "    Domain:       $NEW_DOMAIN"
  echo "    Database:     $NEW_DB_NAME / $NEW_DB_USER"
  echo "    Bot:          @$NEW_BOT_USERNAME"
  echo "    Admin ID:     $NEW_ADMIN_ID"
  echo "    SSL:          $DO_SSL"
  echo ""
  read -p "  Apply these changes? (y/n): " CONFIRM
  CONFIRM=${CONFIRM,,}
  if [ "$CONFIRM" != "y" ]; then
    echo "  Cancelled."
    return 0
  fi

  # --- Backup old config ---
  if [ -f "$CONFIG_FILE" ]; then
    cp "$CONFIG_FILE" "${CONFIG_FILE}.bak.$(date +%s)"
  fi

  # --- Write new config.php ---
  msg "Writing config.php ..."
  NEW_DB_PASS_PHP="${NEW_DB_PASS//\'/\\\'}"
  cat >"$CONFIG_FILE" <<PHP
<?php
// ================= DATABASE =================
\$dbname     = '$NEW_DB_NAME';
\$usernamedb = '$NEW_DB_USER';
\$passworddb = '${NEW_DB_PASS_PHP}';

\$connect = mysqli_connect("localhost", \$usernamedb, \$passworddb, \$dbname);
if (\$connect->connect_error) { die("error" . \$connect->connect_error); }
mysqli_set_charset(\$connect, "utf8mb4");

\$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];
\$dsn = "mysql:host=localhost;dbname=\$dbname;charset=utf8mb4";
try { \$pdo = new PDO(\$dsn, \$usernamedb, \$passworddb, \$options); } catch (\PDOException \$e) { error_log("Database connection failed: " . \$e->getMessage()); }

// ================= TELEGRAM BOT =================
\$APIKEY      = '$NEW_BOT_TOKEN';
\$adminnumber = '$NEW_ADMIN_ID';
\$domainhosts = '$NEW_DOMAIN';
\$usernamebot = '$NEW_BOT_USERNAME';

?>
PHP
  chown www-data:www-data "$CONFIG_FILE"
  echo -e "  ${GREEN}✓${NC} config.php updated"

  # --- Create / update DB user ---
  msg "Updating database user ..."
  NEW_DB_PASS_SQL="${NEW_DB_PASS//\'/\'\'}"
  mysql -u root <<SQL
CREATE DATABASE IF NOT EXISTS \`$NEW_DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$NEW_DB_USER'@'localhost' IDENTIFIED BY '${NEW_DB_PASS_SQL}';
ALTER USER '$NEW_DB_USER'@'localhost' IDENTIFIED BY '${NEW_DB_PASS_SQL}';
GRANT ALL PRIVILEGES ON \`$NEW_DB_NAME\`.* TO '$NEW_DB_USER'@'localhost';
FLUSH PRIVILEGES;
SQL
  echo -e "  ${GREEN}✓${NC} Database user ready"

  # --- Apache VirtualHost ---
  msg "Updating Apache VirtualHost ..."
  if mirza_vhost_use_domain_conf; then
    VHOST_FILE="/etc/apache2/sites-available/${NEW_DOMAIN}.conf"
    mirza_a2dissite "$VIRANAUT_VHOST_GENERIC" "$VIRANAUT_VHOST_LEGACY" 2>/dev/null || true
  else
    VHOST_FILE="/etc/apache2/sites-available/$VIRANAUT_VHOST_GENERIC"
  fi
  cat > "$VHOST_FILE" <<VHOST
<VirtualHost *:80>
    ServerName $NEW_DOMAIN
    DocumentRoot $PROJECT_DIR

    <Directory $PROJECT_DIR>
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog \${APACHE_LOG_DIR}/${VIRANAUT_LOG_ERROR}
    CustomLog \${APACHE_LOG_DIR}/${VIRANAUT_LOG_ACCESS} combined
</VirtualHost>
VHOST
  mirza_a2ensite "$(basename "$VHOST_FILE")" 2>/dev/null || true
  mirza_a2dissite 000-default.conf 2>/dev/null || true
  a2enmod rewrite 2>/dev/null || true
  systemctl reload apache2
  echo -e "  ${GREEN}✓${NC} Apache configured for $NEW_DOMAIN"

  # --- SSL ---
  if [ "$DO_SSL" == "y" ]; then
    msg "Getting SSL certificate ..."
    apt-get install -y certbot python3-certbot-apache >/dev/null 2>&1 || true
    if certbot --apache -d "$NEW_DOMAIN" --non-interactive --agree-tos --register-unsafely-without-email --redirect; then
      echo -e "  ${GREEN}✓${NC} SSL installed"
    else
      warn "SSL failed. Check that DNS points to this server and port 80 is open."
      warn "You can retry later: certbot --apache -d $NEW_DOMAIN"
    fi
  fi

  # --- Webhook ---
  msg "Setting Telegram webhook ..."
  WEBHOOK_URL="${PROTOCOL}://$NEW_DOMAIN/index.php"
  curl -s "https://api.telegram.org/bot$NEW_BOT_TOKEN/deleteWebhook" >/dev/null 2>&1 || true
  WEBHOOK_RESULT=$(curl -s "https://api.telegram.org/bot$NEW_BOT_TOKEN/setWebhook?url=$WEBHOOK_URL")
  if echo "$WEBHOOK_RESULT" | grep -q '"ok":true'; then
    echo -e "  ${GREEN}✓${NC} Webhook set: $WEBHOOK_URL"
  else
    warn "Webhook failed: $WEBHOOK_RESULT"
  fi

  # --- Cron ---
  msg "Ensuring cron jobs ..."
  setup_cron_jobs
  echo -e "  ${GREEN}✓${NC} Cron jobs ready"

  line
  echo ""
  echo -e "  ${GREEN}${BOLD}✓ Configuration complete!${NC}"
  echo -e "  Send /start to @$NEW_BOT_USERNAME in Telegram."
  echo ""
}

# ============================================================
#  Diagnose: why Telegram bot does not respond
# ============================================================
do_diagnose_bot() {
  resolve_project_paths
  line
  msg "Bot diagnostics — $PROJECT_DIR"
  line
  echo ""

  local domain token dbname dbuser docroot vhost_file wh_url http_code

  if [ ! -f "$CONFIG_FILE" ]; then
    err "config.php missing at $CONFIG_FILE"
    return 1
  fi
  echo -e "  ${CYAN}1) Paths${NC}"
  echo -e "    Bot dir:     $PROJECT_DIR"
  echo -e "    index.php:   $([ -f "$PROJECT_DIR/index.php" ] && echo OK || echo MISSING)"
  echo -e "    panel:       $([ -f "$PROJECT_DIR/panel/login.php" ] && echo OK || echo MISSING)"
  echo -e "    vendor:      $([ -f "$PROJECT_DIR/vendor/autoload.php" ] && echo OK || echo MISSING)"
  echo ""

  domain=$(read_php_var "domainhosts")
  domain=$(mirza_normalize_domainhosts "$domain")
  token=$(read_php_var "APIKEY")
  dbname=$(read_php_var "dbname")
  dbuser=$(read_php_var "usernamedb")
  dbpass=$(read_php_var "passworddb")

  echo -e "  ${CYAN}2) config.php${NC}"
  echo -e "    Domain:      ${domain:-MISSING}"
  echo -e "    DB:          ${dbname:-?} / ${dbuser:-?}"
  echo -e "    Token:       $([ -n "$token" ] && echo "set (${#token} chars)" || echo MISSING)"
  echo ""

  if [ -n "$domain" ]; then
    local srv_ip dns_a
    srv_ip=$(hostname -I 2>/dev/null | awk '{print $1}')
    [ -z "$srv_ip" ] && srv_ip=$(curl -4 -s --connect-timeout 5 ifconfig.me 2>/dev/null || true)
    dns_a=$(dig +short "$domain" A 2>/dev/null | head -1)
    echo -e "  ${CYAN}3) DNS vs this server${NC}"
    echo -e "    Server IP:   ${srv_ip:-unknown}"
    echo -e "    DNS A ($domain): ${dns_a:-not set}"
    if [ -n "$srv_ip" ] && [ -n "$dns_a" ] && [ "$srv_ip" != "$dns_a" ]; then
      err "DNS points to WRONG server — Let's Encrypt/Telegram go to $dns_a, not $srv_ip"
      echo "    Fix: set A record for $domain → $srv_ip at your DNS panel, wait 5–30 min, then menu 8 again"
    elif [ -n "$srv_ip" ] && [ "$srv_ip" = "$dns_a" ]; then
      echo -e "  ${GREEN}✓${NC} DNS matches this server"
    fi
    echo ""
  fi

  echo -e "  ${CYAN}4) MySQL${NC}"
  if [ -z "$dbname" ] || [ -z "$dbuser" ]; then
    warn "Cannot read DB credentials from config.php"
  elif mysql -u "$dbuser" -p"$dbpass" -e "USE \`$dbname\`; SELECT COUNT(*) AS users FROM user;" 2>/dev/null; then
    echo -e "  ${GREEN}✓${NC} Connected to database \`$dbname\`"
  else
    err "MySQL failed — wrong dbname/user/pass or DB missing"
    echo "    Backup used: mirzaprobot / yalda — fix config.php if you see yalda/yalda as dbname"
  fi
  echo ""

  vhost_file="/etc/apache2/sites-available/${domain}.conf"
  docroot=""
  if [ -n "$domain" ] && [ -f "$vhost_file" ]; then
    docroot=$(grep -i DocumentRoot "$vhost_file" 2>/dev/null | head -1 | awk '{print $2}')
  fi
  echo -e "  ${CYAN}5) Apache vhost${NC}"
  echo -e "    Vhost file:  $([ -f "$vhost_file" ] && echo "$vhost_file" || echo not found)"
  if [ ! -f "$vhost_file" ] && [ -n "$domain" ]; then
    warn "HTTP vhost ${domain}.conf missing — only SSL may work; run menu 8"
  fi
  echo -e "    DocumentRoot: ${docroot:-unknown}"
  echo -e "    ${CYAN}apache2ctl -S (443):${NC}"
  apache2ctl -S 2>/dev/null | grep -E ":443|${domain}" | sed 's/^/      /' | head -8
  for f in mirza-pro.conf mirza-pro-le-ssl.conf; do
    if [ -e "/etc/apache2/sites-enabled/$f" ]; then
      err "Legacy vhost still ENABLED: $f — run menu 8 to disable"
    fi
  done
  local _bad_vhost
  for _bad_vhost in /etc/apache2/sites-available/*.conf; do
    [ -f "$_bad_vhost" ] || continue
    if grep -qF "/var/www/mirza_pro" "$_bad_vhost" 2>/dev/null; then
      err "STALE path in $(basename "$_bad_vhost") — still references /var/www/mirza_pro → run menu 8"
    fi
  done
  local ssl_doc f
  for f in /etc/apache2/sites-available/${domain}*-ssl.conf /etc/apache2/sites-available/${domain}*-le-ssl.conf; do
    [ -f "$f" ] || continue
    ssl_doc=$(grep -i DocumentRoot "$f" 2>/dev/null | head -1 | awk '{print $2}')
    echo -e "    SSL $(basename "$f"): DocumentRoot ${ssl_doc:-?}"
    if [ -n "$ssl_doc" ] && [ "${ssl_doc%/}" != "${PROJECT_DIR%/}" ]; then
      warn "SSL vhost DocumentRoot wrong — run menu 8"
    fi
  done
  if [ -n "$docroot" ] && [ "${docroot%/}" != "${PROJECT_DIR%/}" ]; then
    warn "DocumentRoot != bot path — Telegram may hit wrong folder!"
    echo "      Apache serves: $docroot"
    echo "      Bot files at:  $PROJECT_DIR"
  elif [ -n "$docroot" ]; then
    echo -e "  ${GREEN}✓${NC} DocumentRoot matches bot path"
  fi
  systemctl is-active apache2 >/dev/null 2>&1 && echo -e "  ${GREEN}✓${NC} apache2 active" || err "apache2 not running"
  echo ""

  echo -e "  ${CYAN}6) Ports${NC}"
  ss -tlnp 2>/dev/null | grep -E ':80 |:443 ' || warn "Nothing listening on 80/443?"
  echo ""

  if [ -n "$token" ]; then
    echo -e "  ${CYAN}7) Telegram webhook${NC}"
    wh_info=$(curl -s "https://api.telegram.org/bot${token}/getWebhookInfo")
    wh_url=$(echo "$wh_info" | sed -n 's/.*"url"[[:space:]]*:[[:space:]]*"\([^"]*\)".*/\1/p' | head -1)
    echo "    URL:         ${wh_url:-(empty)}"
    echo "    Expected:    https://${domain}/index.php"
    if [ -n "$domain" ] && [ "$wh_url" = "https://${domain}/index.php" ]; then
      echo -e "  ${GREEN}✓${NC} Webhook URL matches domain"
    else
      warn "Webhook URL wrong or empty — run menu 5 or setWebhook manually"
    fi
    echo "$wh_info" | grep -q '"last_error_message":""' && true
    local _recent_tg_post=0 _acc
    _acc=$(viranaut_apache_log_file access "$domain") || _acc=""
    if [ -n "$_acc" ] && [ -f "$_acc" ] && grep -E '91\.108\.|149\.154\.' "$_acc" 2>/dev/null | grep -q 'POST /index.php.* 200'; then
      _recent_tg_post=1
    fi
    if echo "$wh_info" | grep -q '"last_error_date":[1-9]'; then
      if [ "$_recent_tg_post" -eq 1 ] && echo "$wh_info" | grep -q '404 Not Found'; then
        echo -e "  ${YELLOW}Note:${NC} Old 404 error in Telegram cache — recent POSTs from Telegram returned 200 (webhook OK now)"
        echo -e "  ${CYAN}Tip:${NC} Run menu 8 once to refresh webhook, then send /start again"
      else
        warn "Telegram reports delivery errors (see last_error_message below):"
        echo "$wh_info" | tr ',' '\n' | grep -E 'last_error|pending_update' | sed 's/^/    /'
        if echo "$wh_info" | grep -qi 'timed out\|timeout'; then
          echo -e "    ${YELLOW}Hint:${NC} HTTPS works from server but Telegram times out → cloud firewall must allow 443 inbound"
        fi
      fi
    else
      echo -e "  ${GREEN}✓${NC} No recent webhook delivery error from Telegram"
    fi
    mirza_show_webhook_access_recent "$domain"
    echo ""
  fi

  if [ -n "$domain" ]; then
    echo -e "  ${CYAN}8) HTTPS reachability${NC}"
    http_code=$(curl -sk -o /dev/null -w "%{http_code}" --connect-timeout 10 "https://${domain}/index.php" 2>/dev/null)
    http_code=${http_code:-000}
    echo "    https://${domain}/index.php → HTTP $http_code"
    case "$http_code" in
      000|''|502|503)
        err "HTTPS not reachable — no SSL on :443 or firewall; Telegram webhook will timeout"
        ;;
      403)
        echo -e "  ${YELLOW}Note:${NC} 403 may be IP check (normal for browser; Telegram uses its own IPs)"
        ;;
      200|405|400|403)
        echo -e "  ${GREEN}✓${NC} Server responds (code $http_code)"
        ;;
      404)
        err "HTTPS GET returns 404 — wrong DocumentRoot or stale vhost; run menu 8"
        ;;
      *)
        warn "Unexpected HTTP $http_code — check vhost/SSL"
        ;;
    esac
    mirza_test_https_post_speed "$domain" || warn "HTTPS POST problem — Telegram may get 404/timeout"
    echo ""

    echo -e "  ${CYAN}8b) Web admin panel (/panel/)${NC}"
    echo -e "    panel/inc/config.php: $([ -f "$PROJECT_DIR/panel/inc/config.php" ] && echo OK || echo MISSING)"
    if [ ! -f "$PROJECT_DIR/panel/inc/config.php" ]; then
      warn "Run: /root/ViraNaut_manage.sh panel-fix"
    fi
    if [ ! -f "$PROJECT_DIR/panel/login.php" ]; then
      err "panel/login.php MISSING — run menu 8 (local zip) or copy panel/ folder"
      echo "    URL would be: https://${domain}/panel/login.php"
    else
      local ping_code login_code ping_body
      ping_code=$(curl -sk -o /dev/null -w "%{http_code}" --connect-timeout 10 "https://${domain}/panel/ping.php" 2>/dev/null)
      ping_code=${ping_code:-000}
      login_code=$(curl -sk -o /dev/null -w "%{http_code}" --connect-timeout 10 "https://${domain}/panel/login.php" 2>/dev/null)
      login_code=${login_code:-000}
      echo "    https://${domain}/panel/ping.php → HTTP $ping_code"
      echo "    https://${domain}/panel/login.php → HTTP $login_code"
      local check_code check_body
      check_code=$(curl -sk -o /dev/null -w "%{http_code}" --connect-timeout 10 "https://${domain}/panel/check.php" 2>/dev/null)
      check_code=${check_code:-000}
      if [ "$check_code" = "200" ] || [ "$check_code" = "500" ]; then
        echo "    https://${domain}/panel/check.php → HTTP $check_code"
        check_body=$(curl -sk --connect-timeout 10 "https://${domain}/panel/check.php" 2>/dev/null | head -n 8)
        [ -n "$check_body" ] && echo "$check_body" | sed 's/^/      /'
      fi
      if [ "$ping_code" = "200" ]; then
        ping_body=$(curl -sk --connect-timeout 10 "https://${domain}/panel/ping.php" 2>/dev/null | head -c 120)
        echo "    ping: ${ping_body:-(empty)}"
      fi
      case "$login_code" in
        200)
          echo -e "  ${GREEN}✓${NC} Login page reachable from this server"
          echo "    Default DB user is often: admin (password in admin table or set via table.php on first install)"
          ;;
        404)
          err "404 — Apache does not see panel/ (wrong DocumentRoot or folder missing on disk)"
          ;;
        500|502|503)
          err "HTTP $login_code — PHP error; run: tail -20 $PROJECT_DIR/error_log"
          curl -sk --connect-timeout 10 "https://${domain}/panel/login.php" 2>/dev/null | head -c 200 | sed 's/^/    /'
          ;;
        000)
          err "Cannot reach panel URL (DNS/SSL/firewall)"
          ;;
        *)
          warn "Unexpected HTTP $login_code for login.php"
          ;;
      esac
      if [ "$ping_code" != "200" ] && [ "$login_code" = "200" ]; then
        warn "Deploy panel/ping.php for faster checks (optional)"
      fi
    fi
    echo ""
  fi

  echo -e "  ${CYAN}9) PHP local test (HTTPS POST fake /start)${NC}"
  if [ -f "$PROJECT_DIR/index.php" ] && [ -n "$domain" ]; then
    local test_body='{"update_id":999999001,"message":{"message_id":1,"date":1,"chat":{"id":123456789,"type":"private"},"from":{"id":123456789,"is_bot":false,"first_name":"Diag"},"text":"/start"}}'
    local local_out post_code
    post_code=$(curl -sk -o /tmp/viranaut_post_test.out -w "%{http_code}" -X POST \
      "https://${domain}/index.php" \
      -H "Content-Type: application/json" \
      -d "$test_body" 2>/dev/null)
    local_out=$(head -c 200 /tmp/viranaut_post_test.out 2>/dev/null)
    rm -f /tmp/viranaut_post_test.out
    echo "    HTTPS POST → HTTP $post_code"
    echo "    Response: ${local_out:-(empty)}"
    case "$post_code" in
      200|405)
        echo -e "  ${GREEN}✓${NC} index.php reachable via HTTPS POST"
        ;;
      403)
        echo -e "  ${GREEN}✓${NC} index.php runs (403 = non-Telegram IP blocked — expected from server)"
        ;;
      301|302)
        err "HTTPS POST redirects ($post_code) — Telegram webhook will fail; fix Apache SSL vhost (menu 8)"
        ;;
      404)
        err "HTTPS POST returns 404 — run menu 8 to fix vhost DocumentRoot"
        ;;
      *)
        warn "Unexpected POST code $post_code"
        ;;
    esac
  fi
  echo ""

  echo -e "  ${CYAN}10) App error_log${NC}"
  if [ -f "$PROJECT_DIR/error_log" ]; then
    echo -e "  ${GREEN}✓${NC} Exists — last 3 lines:"
    tail -n 3 "$PROJECT_DIR/error_log" | sed 's/^/    /'
  else
    echo "    (no file yet — normal if no PHP errors and no webhook hits)"
  fi
  echo ""

  local acc_log="/var/log/apache2/${domain}-access.log"
  [ -f "$acc_log" ] || acc_log="/var/log/apache2/access.log"
  echo -e "  ${CYAN}11) Live test — do this now${NC}"
  echo "    1) Open another SSH window:"
  echo "       tail -f $acc_log"
  local _diag_bot
  _diag_bot=$(mirza_normalize_bot_username "$(read_php_var "usernamebot")")
  echo "    2) In Telegram send /start to @${_diag_bot:-YOUR_BOT}"
  echo "    3) If NO line POST /index.php appears → Telegram never reaches server (DNS/SSL/firewall/webhook)"
  echo "    4) If POST appears but bot silent → DB/config or panel code; check error_log after POST"
  line
  echo ""
}

# ============================================================
#  6) LOGS
# ============================================================
do_logs() {
  resolve_project_paths
  local DOMAIN=""
  local LOG_DOMAIN LOG_ERR LOG_ACC
  [ -f "$CONFIG_FILE" ] && DOMAIN=$(mirza_normalize_domainhosts "$(read_php_var "domainhosts")")
  LOG_ERR=$(viranaut_apache_log_file error "$DOMAIN") || LOG_ERR=""
  LOG_ACC=$(viranaut_apache_log_file access "$DOMAIN") || LOG_ACC=""
  echo ""
  echo -e "  ${BOLD}Select log to view:${NC}"
  [ -n "$DOMAIN" ] && echo -e "  ${CYAN}Domain:${NC} $DOMAIN  ${CYAN}Bot:${NC} $PROJECT_DIR"
  [ -n "$LOG_ERR" ] && echo -e "  ${CYAN}Error log:${NC} $LOG_ERR"
  echo ""
  echo ""
  echo -e "    ${BOLD}1)${NC} Apache error log"
  echo -e "    ${BOLD}2)${NC} Apache access log (last 50 lines)"
  echo -e "    ${BOLD}3)${NC} PHP error log"
  echo -e "    ${BOLD}4)${NC} Bot app errors (if exists)"
  echo -e "    ${BOLD}5)${NC} Follow Apache error log (live, Ctrl+C to stop)"
  echo -e "    ${BOLD}6)${NC} Back to menu"
  echo ""
  read -p "  Select [1-6]: " LOG_CHOICE

  case "$LOG_CHOICE" in
    1)
      echo ""
      msg "Apache Error Log (last 100 lines):"
      line
      if [ -n "$LOG_ERR" ]; then
        [[ "$LOG_ERR" == *mirza_error* ]] && warn "Showing legacy mirza_error.log — run menu 8 to fix Apache paths"
        tail -n 100 "$LOG_ERR"
      else
        warn "Log file not found."
      fi
      ;;
    2)
      echo ""
      msg "Apache Access Log (last 50 lines):"
      line
      if [ -n "$LOG_ACC" ]; then
        [[ "$LOG_ACC" == *mirza_access* ]] && warn "Showing legacy mirza_access.log — run menu 8 to fix Apache paths"
        tail -n 50 "$LOG_ACC"
      else
        warn "Log file not found."
      fi
      ;;
    3)
      echo ""
      msg "PHP Error Log:"
      line
      PHP_LOG=$(php -r "echo ini_get('error_log');" 2>/dev/null)
      if [ -n "$PHP_LOG" ] && [ -f "$PHP_LOG" ]; then
        tail -n 100 "$PHP_LOG"
      else
        tail -n 100 /var/log/php*.log 2>/dev/null || warn "PHP error log not found."
      fi
      ;;
    4)
      echo ""
      msg "Bot App Errors:"
      line
      if [ -f "$PROJECT_DIR/error_log" ]; then
        tail -n 100 "$PROJECT_DIR/error_log"
      elif ls "$PROJECT_DIR"/error_log* >/dev/null 2>&1; then
        tail -n 100 "$PROJECT_DIR"/error_log*
      else
        warn "No app error log found in $PROJECT_DIR"
      fi
      ;;
    5)
      echo ""
      msg "Following Apache error log (Ctrl+C to stop):"
      line
      if [ -n "$LOG_ERR" ]; then
        tail -f "$LOG_ERR"
      else
        warn "Log file not found."
      fi
      ;;
    6|*)
      return 0
      ;;
  esac
  echo ""
}

# ============================================================
#  Helper: Setup cron jobs (non-destructive)
# ============================================================
setup_cron_jobs() {
  MIRZA_CRON_LINES=(
    "* * * * * php $PROJECT_DIR/cronbot/NoticationsService.php >/dev/null 2>&1"
    "*/1 * * * * php $PROJECT_DIR/cronbot/card_receipt_prompt.php >/dev/null 2>&1"
    "*/5 * * * * php $PROJECT_DIR/cronbot/uptime_panel.php >/dev/null 2>&1"
    "*/5 * * * * php $PROJECT_DIR/cronbot/uptime_node.php >/dev/null 2>&1"
    "*/10 * * * * php $PROJECT_DIR/cronbot/expireagent.php >/dev/null 2>&1"
    "*/10 * * * * php $PROJECT_DIR/cronbot/payment_expire.php >/dev/null 2>&1"
    "0 * * * * php $PROJECT_DIR/cronbot/statusday.php >/dev/null 2>&1"
    "0 3 * * * php $PROJECT_DIR/cronbot/backupbot.php >/dev/null 2>&1"
    "*/15 * * * * php $PROJECT_DIR/cronbot/iranpay1.php >/dev/null 2>&1"
    "*/15 * * * * php $PROJECT_DIR/cronbot/plisio.php >/dev/null 2>&1"
  )
  TMP_CRON=$(mktemp)
  crontab -l 2>/dev/null | grep -Fv 'cronbot/croncard.php' | grep -Fv 'cronbot/card_receipt_prompt.php' > "$TMP_CRON" || true
  for cron_line in "${MIRZA_CRON_LINES[@]}"; do
    if ! grep -Fqx "$cron_line" "$TMP_CRON"; then
      echo "$cron_line" >> "$TMP_CRON"
    fi
  done
  crontab "$TMP_CRON"
  rm -f "$TMP_CRON"
}

# ============================================================
#  MENU
# ============================================================
show_menu() {
  clear
  resolve_project_paths
  echo ""
  echo -e "${BOLD}${CYAN}╔════════════════════════════════════════╗${NC}"
  echo -e "${BOLD}${CYAN}║      ViraNaut — Bot & Panel Manager              ║${NC}"
  echo -e "${BOLD}${CYAN}╚════════════════════════════════════════╝${NC}"
  echo -e "  ${CYAN}Version:${NC} v${VIRANAUT_MANAGE_VERSION}"
  echo -e "  ${CYAN}Product:${NC} Telegram VPN Bot + Web Admin Panel"
  echo -e "  ${CYAN}Script:${NC}  $(mirza_manage_script_dir)/ViraNaut_manage.sh"
  echo ""

  # Show status
  if [ -f "$CONFIG_FILE" ]; then
    _domain=$(read_php_var "domainhosts")
    _domain=$(mirza_normalize_domainhosts "$_domain")
    _bot=$(mirza_normalize_bot_username "$(read_php_var "usernamebot")")
    echo -e "  ${GREEN}●${NC} Bot  —  ${BOLD}@${_bot}${NC}  —  ${_domain}"
    echo -e "  ${CYAN}Path:${NC} $PROJECT_DIR"
  else
    local _legacy=""
    _legacy=$(mirza_find_legacy_mirza_dir 2>/dev/null) || _legacy=""
    if [ -n "$_legacy" ]; then
      echo -e "  ${YELLOW}●${NC} Mirza detected: ${BOLD}$_legacy${NC} — menu ${BOLD}1${NC} Install to migrate"
    else
      echo -e "  ${RED}●${NC} Not installed — menu ${BOLD}1${NC} Install (GitHub)"
    fi
  fi

  if systemctl is-active --quiet apache2 2>/dev/null; then
    echo -e "  ${GREEN}●${NC} Apache: running"
  else
    echo -e "  ${RED}●${NC} Apache: stopped"
  fi
  echo -e "  ${CYAN}GitHub:${NC} ${VIRANAUT_GITHUB_PAGE}"
  echo ""
  line
  echo ""
  echo -e "  ${GREEN}${BOLD}1)${NC} Install        ${GREEN}(GitHub — auto-detect Mirza → ViraNaut)${NC}"
  echo -e "  ${GREEN}${BOLD}2)${NC} Update         ${GREEN}(GitHub — auto backup before update)${NC}"
  echo -e "  ${BOLD}3)${NC} Stop Apache"
  echo -e "  ${BOLD}4)${NC} Start Apache"
  echo -e "  ${BOLD}5)${NC} Restart full   (MySQL + Apache + webhook)"
  echo -e "  ${BOLD}6)${NC} Logs"
  echo -e "  ${BOLD}7)${NC} Diagnose bot"
  echo -e "  ${GREEN}${BOLD}8)${NC} Auto-fix all   ${GREEN}(DB + vhost + SSL + webhook)${NC}"
  echo -e "  ${RED}${BOLD}9)${NC} Full remove bot"
  echo -e "  ${BOLD}0)${NC} Exit"
  echo ""
  read -p "  Select [0-9]: " MENU_CHOICE
}

# ============================================================
#  MAIN
# ============================================================
if viranaut_cli_entry "$@"; then
  exit 0
fi

check_root
viranaut_self_update_manage_script 2>/dev/null || true
viranaut_link_cli

while true; do
  show_menu
  case "$MENU_CHOICE" in
    1)
      do_install
      _rc=$?
      [ "$_rc" -eq 2 ] && continue
      read -p "  Press Enter to continue..."
      ;;
    2) do_update_bot;          read -p "  Press Enter to continue..." ;;
    3) do_stop_apache;         read -p "  Press Enter to continue..." ;;
    4) do_start_apache;        read -p "  Press Enter to continue..." ;;
    5) do_restart_full;        read -p "  Press Enter to continue..." ;;
    6) do_logs;                read -p "  Press Enter to continue..." ;;
    7) do_diagnose_bot;        read -p "  Press Enter to continue..." ;;
    8) do_fix_all_bot;         read -p "  Press Enter to continue..." ;;
    9) do_full_remove_bot;     read -p "  Press Enter to continue..." ;;
    0)
      echo ""
      echo -e "  ${GREEN}Goodbye!${NC}"
      echo ""
      exit 0
      ;;
    *)
      warn "Invalid option."
      sleep 1
      ;;
  esac
done
