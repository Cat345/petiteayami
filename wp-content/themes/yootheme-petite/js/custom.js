(function($){
  const isContactPage = window.location.pathname.includes('contact');
  
  var showBadge = false; // <- поменяем из PHP/локализации в WP
    if (isContactPage) {
        showBadge = true
    }
  function applyVisibility() {
    var badge = document.querySelector('.grecaptcha-badge');
    if (badge) {
      if (showBadge) {
        badge.style.display = '';
        badge.style.visibility = '';
      } else {
        badge.style.display = 'none';
        badge.style.visibility = 'hidden';
      }
      return true;
    }
    return false;
  }

  function init() {
    // пробуем сразу
    if (applyVisibility()) return;

    // наблюдатель на добавление элементов в DOM
    var observer = new MutationObserver(function() {
      if (applyVisibility()) {
        observer.disconnect();
      }
    });
    observer.observe(document.body, { childList: true, subtree: true });

    // запасной интервал (максимум 10 попыток)
    var tries = 0;
    var iv = setInterval(function() {
      if (applyVisibility() || ++tries > 10) clearInterval(iv);
    }, 500);
  }

  // запускаем после DOM ready
  $(function(){ init(); });

  // expose setter (опционально)
  window.truemishaSetRecaptcha = function(show) { showBadge = !!show; };
  
})(jQuery);

/*
(function ($) {
    // Настройка: укажи дату окончания акции (ISO-строка)
    const DEADLINE_STR = "2025-11-30T23:59:59"; // <- поменяй
    const CHECK_SELECTOR = ".ditty-ticker__items";
    const PLACEHOLDER = "{timer}";
    const TIMER_CLASS = "my-timer"; // класс вставленного span

    // Парсинг даты с защитой
    function parseDeadline(s) {
        const d = new Date(s);
        if (isNaN(d.getTime())) {
            console.error("DEADLINE_STR:", s);
            return null;
        }
        return d;
    }
    const DEADLINE = parseDeadline(DEADLINE_STR);

    // Форматирование
    function formatRemaining(ms) {
        if (ms <= 0) return "";
        const days = Math.floor(ms / (1000 * 60 * 60 * 24));
        const hours = Math.floor((ms / (1000 * 60 * 60)) % 24);
        const minutes = Math.floor((ms / (1000 * 60)) % 60);
        const seconds = Math.floor((ms / 1000) % 60);

        const h = hours.toString().padStart(2, "0");
        const m = minutes.toString().padStart(2, "0");
        const s = seconds.toString().padStart(2, "0");
        return (days > 0 ? days + "d " : "") + h + ":" + m + ":" + s;
    }

    // Находит текстовые узлы, содержащие PLACEHOLDER
    function findTextNodesWithPlaceholder(root) {
        const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, null, false);
        const nodes = [];
        let node;
        while (node = walker.nextNode()) {
            if (node.nodeValue && node.nodeValue.indexOf(PLACEHOLDER) !== -1) {
                nodes.push(node);
            }
        }
        return nodes;
    }

    // Заменяет placeholder на span.timer. Возвращает массив созданных span'ов
    function replacePlaceholderWithSpan($el) {
        const created = [];
        const nodes = findTextNodesWithPlaceholder($el[0]);
        nodes.forEach(textNode => {
            try {
                const parent = textNode.parentNode;
                // разбиваем текст на части до/после плейсхолдера (поддерживает несколько в одном узле)
                const parts = textNode.nodeValue.split(PLACEHOLDER);
                const frag = document.createDocumentFragment();
                parts.forEach((p, idx) => {
                    if (p) frag.appendChild(document.createTextNode(p));
                    if (idx < parts.length - 1) {
                        const span = document.createElement("span");
                        span.className = TIMER_CLASS;
                        span.textContent = "loading..."; // временный текст
                        frag.appendChild(span);
                        created.push(span);
                    }
                });
                parent.replaceChild(frag, textNode);
            } catch (err) {
                console.error("error placeholder:", err);
            }
        });
        return created;
    }

    // Обновляет все span.my-timer
    let updateIntervalId = null;
    function startIntervalIfNeeded() {
        if (!DEADLINE) return;
        if (updateIntervalId !== null) return; // уже запущен

        function tick() {
            const spans = document.querySelectorAll("span." + TIMER_CLASS);
            const now = new Date();
            const diff = DEADLINE - now;
            spans.forEach(span => {
                try {
                    span.textContent = formatRemaining(diff);
                } catch (err) {
                    console.error("error:", err);
                }
            });
            if (diff <= 0) {
                // подчистим интервал — таймер завершён
                clearInterval(updateIntervalId);
                updateIntervalId = null;
            }
        }

        // первый вызов немедленно
        tick();
        updateIntervalId = setInterval(tick, 1000);
    }

    // Основная функция: находит элементы и делает замену
    function processAll() {
        try {
            const $blocks = $(CHECK_SELECTOR);
            if (!$blocks.length) {
                // не найдено пока — возможно динамически появится
                console.info("Elements None", CHECK_SELECTOR);
                return;
            }

            $blocks.each(function () {
                const $this = $(this);
                // если внутри уже есть наш span — пропускаем (чтобы не дублировать)
                if ($this.find("." + TIMER_CLASS).length) return;
                const created = replacePlaceholderWithSpan($this);
                if (created.length) {
                   // console.info("Вставлен(ы) таймер(ы) в", this, created);
                }
            });

            // запустить интервал обновления, если есть вставленные спаны
            if (document.querySelectorAll("span." + TIMER_CLASS).length) {
                startIntervalIfNeeded();
            }
        } catch (err) {
            console.error("processAll error:", err);
        }
    }

    // Наблюдатель за динамическими изменениями (Ditty часто обновляет DOM)
    function startObserver() {
        const host = document.body;
        const observer = new MutationObserver((mutations) => {
            let needProcess = false;
            for (const m of mutations) {
                // если добавлены узлы или изменён текст внутри интересующих селекторов
                if (m.addedNodes && m.addedNodes.length) needProcess = true;
                if (m.type === "characterData") needProcess = true;
                // более умная логика при желании...
            }
            if (needProcess) {
                // дать Ditty 50-150ms чтобы дописать, затем обработать
                setTimeout(processAll, 80);
            }
        });

        observer.observe(host, {
            childList: true,
            subtree: true,
            characterData: true
        });
    }

    // безопасный старт: проверяем, что jQuery готов и DOM готов
    $(function () {
        try {
            // Первичная попытка обработать (если контент уже на странице)
            processAll();

            // Наблюдатель включаем всегда (на случай динамики)
            startObserver();

            // Для страховки: ещё раз через 1 сек и 3 сек (когда ресурсы подгрузятся)
            setTimeout(processAll, 1000);
            setTimeout(processAll, 3000);

            console.info("Custom timer script запущен для", CHECK_SELECTOR);
        } catch (err) {
            console.error("Ошибка при инициализации таймера:", err);
        }
    });

})(jQuery);
*/
