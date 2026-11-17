/* OldBear Expansion — behaviour for the countdown, animated headline, table of contents, progress tracker and code widgets. */
(() => {
    const once = (el, key) => { if (el.dataset[key]) { return false; } el.dataset[key] = '1'; return true; };
    const pad = (n) => String(n).padStart(2, '0');

    const countdown = (el) => {
        if (!once(el, 'obxReady')) { return; }
        let due = Number(el.dataset.due) * 1000;
        if (el.dataset.evergreen) {
            const key = 'obx-countdown-' + el.dataset.id;
            let stored = 0;
            try { stored = Number(localStorage.getItem(key)) || 0; } catch (e) { /* storage unavailable */ }
            if (!stored) { stored = Date.now() + Number(el.dataset.evergreen) * 1000; try { localStorage.setItem(key, String(stored)); } catch (e) { /* ignore */ } }
            due = stored;
        }
        const digits = {};
        el.querySelectorAll('[data-unit]').forEach((d) => { digits[d.dataset.unit] = d; });
        const tick = () => {
            let left = Math.max(0, Math.floor((due - Date.now()) / 1000));
            const parts = { days: Math.floor(left / 86400), hours: Math.floor(left / 3600) % 24, minutes: Math.floor(left / 60) % 60, seconds: left % 60 };
            if (!digits.days) { parts.hours += parts.days * 24; }
            if (!digits.hours) { parts.minutes += parts.hours * 60; }
            Object.keys(digits).forEach((unit) => { digits[unit].textContent = pad(parts[unit]); });
            if (left > 0) { return; }
            clearInterval(timer);
            if (document.body.classList.contains('elementor-editor-active')) { return; }
            const action = el.dataset.expire;
            if (action === 'hide') { el.hidden = true; }
            if (action === 'message') { el.hidden = true; const msg = el.nextElementSibling; if (msg) { msg.hidden = false; } }
            if (action === 'redirect' && el.dataset.redirect) { window.location.href = el.dataset.redirect; }
        };
        const timer = setInterval(tick, 1000);
        tick();
    };

    const headline = (el) => {
        if (!once(el, 'obxReady') || !el.classList.contains('obx-headline--rotate')) {
            if (el.classList.contains('obx-headline--highlight')) { requestAnimationFrame(() => el.classList.add('is-visible')); }
            return;
        }
        let words = [];
        try { words = JSON.parse(el.dataset.words || '[]'); } catch (e) { words = []; }
        const target = el.querySelector('.obx-headline__dynamic');
        if (!target || words.length < 2 || window.matchMedia('(prefers-reduced-motion: reduce)').matches) { return; }
        const duration = Number(el.dataset.duration) || 2500;
        const loop = el.dataset.loop !== '0';
        let index = 0;
        const typing = el.classList.contains('obx-headline--typing');
        const next = () => {
            if (!loop && index === words.length - 1) { return; }
            index = (index + 1) % words.length;
            if (typing) {
                const word = words[index];
                let i = 0;
                target.textContent = '';
                const type = setInterval(() => { target.textContent = word.slice(0, ++i); if (i >= word.length) { clearInterval(type); setTimeout(next, duration); } }, 70);
                return;
            }
            target.classList.add('is-out');
            setTimeout(() => { target.textContent = words[index]; target.classList.remove('is-out'); setTimeout(next, duration); }, 300);
        };
        setTimeout(next, duration);
    };

    const slug = (text, used) => {
        let base = text.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '') || 'seccion';
        let id = base, n = 2;
        while (used.has(id) || (document.getElementById(id) && !document.getElementById(id).dataset.obxToc)) { id = base + '-' + n++; }
        used.add(id);
        return id;
    };

    const toc = (el) => {
        if (!once(el, 'obxReady')) { return; }
        const body = el.querySelector('.obx-toc__body');
        const toggle = el.querySelector('.obx-toc__toggle');
        if (toggle) {
            toggle.addEventListener('click', () => {
                const open = toggle.getAttribute('aria-expanded') !== 'true';
                toggle.setAttribute('aria-expanded', String(open));
                body.hidden = !open;
                el.classList.toggle('is-collapsed', !open);
            });
        }
        let root = document;
        if (el.dataset.container) { try { root = document.querySelector(el.dataset.container) || document; } catch (e) { root = document; } }
        const tags = (el.dataset.headings || 'h2,h3').split(',');
        let headings = [];
        try { headings = Array.from(root.querySelectorAll(tags.join(','))); } catch (e) { headings = []; }
        headings = headings.filter((h) => {
            if (el.contains(h) || h.closest('.obx-toc, #wpadminbar, .obx-site-header, .obx-site-footer')) { return false; }
            if (el.dataset.exclude) { try { if (h.closest(el.dataset.exclude)) { return false; } } catch (e) { /* bad selector */ } }
            return h.textContent.trim() !== '';
        });
        if (!headings.length) { return; }
        const used = new Set();
        const ordered = el.classList.contains('obx-toc--numbers');
        const make = () => { const list = document.createElement(ordered ? 'ol' : 'ul'); list.className = 'obx-toc__list'; return list; };
        const top = make();
        // Each stack entry is a list item that may receive a nested list; the root holds the top list.
        const stack = [{ level: 0, sub: top }];
        const hierarchical = el.dataset.hierarchical === '1';
        const links = [];
        headings.forEach((h) => {
            if (!h.id) { h.id = slug(h.textContent.trim(), used); h.dataset.obxToc = '1'; }
            const level = hierarchical ? Number(h.tagName.slice(1)) : 1;
            while (stack.length > 1 && stack[stack.length - 1].level >= level) { stack.pop(); }
            const parent = stack[stack.length - 1];
            if (!parent.sub) { parent.sub = make(); parent.item.appendChild(parent.sub); }
            const li = document.createElement('li');
            const a = document.createElement('a');
            a.href = '#' + h.id;
            a.textContent = h.textContent.trim();
            li.appendChild(a);
            parent.sub.appendChild(li);
            stack.push({ level, item: li, sub: null });
            links.push([a, h]);
        });
        body.innerHTML = '';
        body.appendChild(top);
        const offset = Number(el.dataset.offset) || 0;
        el.addEventListener('click', (event) => {
            const a = event.target.closest('a[href^="#"]');
            if (!a) { return; }
            const target = document.getElementById(decodeURIComponent(a.hash.slice(1)));
            if (!target) { return; }
            event.preventDefault();
            window.scrollTo({ top: target.getBoundingClientRect().top + window.scrollY - offset, behavior: 'smooth' });
            history.replaceState(null, '', a.hash);
        });
        if ('IntersectionObserver' in window) {
            const io = new IntersectionObserver((entries) => {
                entries.forEach((entry) => {
                    if (!entry.isIntersecting) { return; }
                    links.forEach(([a, h]) => a.classList.toggle('is-active', h === entry.target));
                });
            }, { rootMargin: `-${offset}px 0px -70% 0px` });
            links.forEach(([, h]) => io.observe(h));
        }
    };

    const progress = (el) => {
        if (!once(el, 'obxReady')) { return; }
        const fill = el.querySelector('.obx-progress__fill');
        const value = el.querySelector('.obx-progress__value');
        const circular = el.classList.contains('obx-progress--circular');
        let target = null;
        if (el.dataset.target) { try { target = document.querySelector(el.dataset.target); } catch (e) { target = null; } }
        const update = () => {
            let ratio;
            if (target) {
                const rect = target.getBoundingClientRect();
                const total = rect.height - window.innerHeight;
                ratio = total > 0 ? -rect.top / total : (rect.top < 0 ? 1 : 0);
            } else {
                const total = document.documentElement.scrollHeight - window.innerHeight;
                ratio = total > 0 ? window.scrollY / total : 0;
            }
            const pct = Math.round(Math.min(1, Math.max(0, ratio)) * 100);
            if (circular) { fill.style.strokeDashoffset = String(100 - pct); } else { fill.style.width = pct + '%'; }
            if (value) { value.textContent = pct + '%'; }
            el.setAttribute('aria-valuenow', String(pct));
        };
        window.addEventListener('scroll', update, { passive: true });
        window.addEventListener('resize', update);
        update();
    };

    const KEYWORDS = 'abstract and as async await break case catch class const continue def default del do echo elif else enum except export extends false final finally fn for foreach from func function go if impl implements import in instanceof interface is let match mod namespace new nil none not null or package pass private protected pub public raise readonly return select self static struct super switch this throw true try type typeof use var void while with yield'.split(' ');
    const KEYWORD_SET = new Set(KEYWORDS.concat(KEYWORDS.map((k) => k.toUpperCase())));
    /** Small generic tokenizer: comments, strings, numbers, keywords and markup tags. */
    const tokens = (code, language) => {
        const rules = language === 'markup'
            ? [['comment', /<!--[\s\S]*?-->/y], ['tag', /<\/?[a-zA-Z][^\s>/]*/y], ['string', /"[^"]*"|'[^']*'/y], ['tag', /\/?>/y]]
            : [['comment', /\/\*[\s\S]*?\*\/|\/\/[^\n]*|#(?!\{)[^\n]*|--[^\n]*/y], ['string', /"(?:\\.|[^"\\])*"|'(?:\\.|[^'\\])*'|`(?:\\.|[^`\\])*`/y], ['number', /\b\d+(?:\.\d+)?\b/y], ['word', /[A-Za-z_$][\w$]*/y]];
        const out = [];
        let i = 0, plain = '';
        while (i < code.length) {
            let matched = false;
            for (const [type, re] of rules) {
                if (type === 'comment' && language !== 'markup' && code[i] === '#' && !['python', 'bash', 'yaml', 'ruby', 'php'].includes(language)) { continue; }
                if (type === 'comment' && code.startsWith('--', i) && language !== 'sql') { continue; }
                re.lastIndex = i;
                const m = re.exec(code);
                if (!m || !m[0]) { continue; }
                if (plain) { out.push(['', plain]); plain = ''; }
                out.push([type === 'word' ? (KEYWORD_SET.has(m[0]) ? 'keyword' : '') : type, m[0]]);
                i += m[0].length;
                matched = true;
                break;
            }
            if (!matched) { plain += code[i++]; }
        }
        if (plain) { out.push(['', plain]); }
        return out;
    };

    const code = (el) => {
        if (!once(el, 'obxReady')) { return; }
        const node = el.querySelector('code');
        const source = node.textContent;
        const highlight = new Set((el.dataset.highlight || '').split(',').filter(Boolean).map(Number));
        const lines = [[]];
        tokens(source, el.dataset.language).forEach(([type, text]) => {
            text.split('\n').forEach((piece, n) => {
                if (n > 0) { lines.push([]); }
                if (piece) { lines[lines.length - 1].push([type, piece]); }
            });
        });
        node.textContent = '';
        lines.forEach((parts, n) => {
            const line = document.createElement('span');
            line.className = 'obx-code__line' + (highlight.has(n + 1) ? ' is-highlighted' : '');
            parts.forEach(([type, text]) => {
                if (!type) { line.appendChild(document.createTextNode(text)); return; }
                const span = document.createElement('span');
                span.className = 'obx-tok-' + type;
                span.textContent = text;
                line.appendChild(span);
            });
            node.appendChild(line);
            if (n < lines.length - 1) { node.appendChild(document.createTextNode('\n')); }
        });
        const copy = el.querySelector('.obx-code__copy');
        if (copy) {
            const label = copy.textContent;
            copy.addEventListener('click', () => {
                const done = () => { copy.textContent = copy.dataset.done; setTimeout(() => { copy.textContent = label; }, 1500); };
                if (navigator.clipboard) { navigator.clipboard.writeText(source).then(done).catch(() => {}); }
            });
        }
    };

    const handlers = {
        '.obx-countdown': countdown, '.obx-headline': headline, '.obx-toc': toc, '.obx-progress': progress, '.obx-code': code,
    };
    const init = (root) => {
        Object.entries(handlers).forEach(([selector, fn]) => {
            if (root.matches && root.matches(selector)) { fn(root); }
            root.querySelectorAll(selector).forEach(fn);
        });
    };
    document.addEventListener('click', (event) => {
        if (event.target.closest('[data-obx-print]')) { event.preventDefault(); window.print(); }
    });
    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', () => init(document)); } else { init(document); }
    window.addEventListener('elementor/frontend/init', () => {
        if (!window.elementorFrontend || !window.elementorFrontend.hooks) { return; }
        ['countdown', 'animated-headline', 'table-of-contents', 'progress-tracker', 'code-highlight'].forEach((name) => {
            window.elementorFrontend.hooks.addAction(`frontend/element_ready/${name}.default`, ($scope) => init($scope[0] || $scope));
        });
    });
})();
