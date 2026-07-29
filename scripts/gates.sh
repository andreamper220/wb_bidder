#!/usr/bin/env bash
#
# Гейт G0 — локальные проверки перед PR.
# См. docs/02-AI-WORKFLOW.md, раздел 5.
#
# Известные нарушения перечислены в scripts/gates-baseline.txt и не блокируют прогон:
# каждое из них описано в docs/KNOWN-ISSUES.md и требует отдельной задачи.
# Новые нарушения блокируют.
#
# Использование:
#   bash scripts/gates.sh              полный прогон
#   bash scripts/gates.sh --no-tests   без phpunit (быстрая проверка)

set -uo pipefail
cd "$(dirname "$0")/.." || exit 1

BASELINE_FILE="scripts/gates-baseline.txt"
RUN_TESTS=1
[ "${1:-}" = "--no-tests" ] && RUN_TESTS=0

FAILED=0
KNOWN_TOTAL=0

red()   { printf '\033[31m%s\033[0m\n' "$1"; }
green() { printf '\033[32m%s\033[0m\n' "$1"; }
gray()  { printf '\033[90m%s\033[0m\n' "$1"; }

is_baselined() {
    local file="$1" check="$2"
    [ -f "$BASELINE_FILE" ] || return 1
    grep -qxF "${file}:${check}" "$BASELINE_FILE"
}

# check_pattern <id> <описание> <extended-regex> <путь...>
check_pattern() {
    local id="$1" desc="$2" pattern="$3"
    shift 3
    local existing=() p
    for p in "$@"; do [ -e "$p" ] && existing+=("$p"); done
    if [ ${#existing[@]} -eq 0 ]; then
        gray "  SKIP  ${id}: путей для проверки нет"
        return
    fi

    local hits new_hits=0 known=0 file
    hits=$(grep -rnE --include='*.php' --include='*.yaml' --include='*.yml' \
        -- "$pattern" "${existing[@]}" 2>/dev/null | sed 's|\\|/|g')

    while IFS= read -r line; do
        [ -z "$line" ] && continue
        file="${line%%:*}"
        file="${file#./}"
        if is_baselined "$file" "$id"; then
            known=$((known + 1))
            continue
        fi
        [ "$new_hits" -eq 0 ] && red "  FAIL  ${id}: ${desc}"
        new_hits=$((new_hits + 1))
        printf '          %s\n' "$line"
    done <<< "$hits"

    KNOWN_TOTAL=$((KNOWN_TOTAL + known))
    if [ "$new_hits" -gt 0 ]; then
        FAILED=1
    else
        if [ "$known" -gt 0 ]; then
            green "  OK    ${id}: ${desc} (известных нарушений: ${known})"
        else
            green "  OK    ${id}: ${desc}"
        fi
    fi
}

HAS_PHP=0
command -v php >/dev/null 2>&1 && HAS_PHP=1
if [ "$HAS_PHP" -eq 0 ]; then
    gray "php не найден в PATH — проверки, требующие PHP, будут пропущены."
    gray "Полный прогон: docker compose run --rm app bash scripts/gates.sh"
    echo
fi

echo "=== G0.1 Синтаксис PHP ==="
if [ "$HAS_PHP" -eq 1 ]; then
    lint_errors=0
    while IFS= read -r f; do
        php -l "$f" >/dev/null 2>&1 || { red "  FAIL  синтаксис: $f"; lint_errors=1; }
    done < <(find src tests -name '*.php' 2>/dev/null)
    [ "$lint_errors" -eq 1 ] && FAILED=1 || green "  OK    php -l"
else
    gray "  SKIP  php недоступен в PATH"
fi

echo
echo "=== G0.2 Запрещённые паттерны ==="

check_pattern money-float \
    "float в денежном пути (правило R-1)" \
    '\(float\)|floatval\(|\bround\(' \
    src/Bidding src/Metrics src/WbApi src/Sync

check_pattern http-outside-wbapi \
    "HttpClient вне src/WbApi (правило R-2)" \
    'HttpClientInterface' \
    src/Bidding src/Metrics src/Sync src/Service src/Command src/Controller src/Admin src/Demo

check_pattern em-in-domain \
    "EntityManager в стратегиях, гардах и метриках (правило R-2)" \
    'EntityManagerInterface' \
    src/Bidding/Strategy src/Bidding/Guard src/Bidding/Merge src/Metrics

check_pattern now-in-domain \
    "получение текущего времени внутри домена (правило R-2)" \
    'new \\DateTimeImmutable\(' \
    src/Bidding/Strategy src/Bidding/Guard src/Bidding/Merge

check_pattern fixture-magic \
    "значения из демо-фикстур в прод-коде (правило R-9)" \
    '987654321|100001' \
    src

check_pattern debug-leftovers \
    "отладочные вызовы" \
    '\b(dd|dump|var_dump|print_r)\(' \
    src

# Проверяются только конфигурации, применяемые по умолчанию. Файлы *.example.* и
# документация исключены намеренно: там выключенный mock — это инструкция, а не настройка.
check_pattern mock-disabled \
    "WB_API_MOCK выключен в конфигурации по умолчанию (правило R-7)" \
    'WB_API_MOCK[[:space:]]*[:=][[:space:]]*"?0' \
    config docker-compose.yml docker-compose.prod.yml

echo
echo "=== G0.3 Целостность зависимостей ==="
if [ "$HAS_PHP" -eq 1 ] && command -v composer >/dev/null 2>&1; then
    composer_out=$(composer validate --no-check-publish --no-check-lock 2>&1)
    composer_rc=$?
    # Предупреждения PHP о недоступных расширениях в stderr не являются ошибкой валидации.
    if [ "$composer_rc" -eq 0 ] || printf '%s' "$composer_out" | grep -q 'composer.json is valid'; then
        green "  OK    composer validate"
    else
        red "  FAIL  composer validate"
        printf '%s\n' "$composer_out" | sed 's/^/          /'
        FAILED=1
    fi
else
    gray "  SKIP  composer или php недоступны"
fi

echo
echo "=== G0.4 Статический анализ (гейт G1) ==="
if [ -x vendor/bin/phpstan ]; then
    vendor/bin/phpstan analyse --no-progress || FAILED=1
else
    gray "  SKIP  phpstan не установлен — гейт G1 запланирован, см. docs/02-AI-WORKFLOW.md"
fi

echo
echo "=== G0.5 Тесты (гейт G2) ==="
if [ "$RUN_TESTS" -eq 0 ]; then
    gray "  SKIP  явно отключено флагом --no-tests"
elif [ "$HAS_PHP" -eq 1 ] && [ -f bin/phpunit ]; then
    php bin/phpunit || FAILED=1
else
    gray "  SKIP  php или bin/phpunit недоступны"
fi

echo
if [ "$FAILED" -eq 0 ]; then
    green "Гейт G0 пройден. Известных нарушений в baseline: ${KNOWN_TOTAL}."
    echo "Каждое из них описано в docs/KNOWN-ISSUES.md и требует отдельной задачи."
    exit 0
fi

red "Гейт G0 не пройден."
echo "Если нарушение осознанное и описано в docs/KNOWN-ISSUES.md — добавьте строку"
echo "'<путь к файлу>:<id проверки>' в ${BASELINE_FILE} в том же PR."
exit 1
