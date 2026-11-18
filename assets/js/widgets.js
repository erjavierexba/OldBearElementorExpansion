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
            const actions = (el.dataset.expire || '').split(',');
            if (actions.includes('hide') || actions.includes('message')) { el.hidden = true; }
            if (actions.includes('message')) { const msg = el.nextElementSibling; if (msg && msg.classList.contains('obx-countdown__message')) { msg.hidden = false; } }
            if (actions.includes('redirect') && el.dataset.redirect) { window.location.href = el.dataset.redirect; }
        };
        const timer = setInterval(tick, 1000);
        tick();
    };

    const headline = (el) => {
        if (!once(el, 'obxReady')) { return; }
        const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        const loop = el.dataset.loop !== '0';
        if (el.classList.contains('obx-headline--highlight')) {
            const duration = Number(el.dataset.duration) || 1200;
            const delay = Number(el.dataset.delay) || 8000;
            el.style.setProperty('--obx-highlight-duration', duration + 'ms');
            const draw = () => { el.classList.remove('is-visible'); void el.offsetWidth; el.classList.add('is-visible'); };
            const start = () => {
                draw();
                if (loop && !reduced) { setInterval(draw, duration + delay); }
            };
            if (reduced || !('IntersectionObserver' in window)) { el.classList.add('is-visible'); return; }
            const io = new IntersectionObserver((entries) => { if (entries.some((e) => e.isIntersecting)) { io.disconnect(); start(); } });
            io.observe(el);
            return;
        }
        let words = [];
        try { words = JSON.parse(el.dataset.words || '[]'); } catch (e) { words = []; }
        const target = el.querySelector('.obx-headline__dynamic');
        if (!target || words.length < 2 || reduced) { return; }
        const duration = Number(el.dataset.duration) || 2500;
        let index = 0;
        const typing = el.classList.contains('obx-headline--typing');
        const next = () => {
            if (!loop && index === words.length - 1) { return; }
            index = (index + 1) % words.length;
            if (typing) {
                target.classList.add('is-selected');
                setTimeout(() => {
                    target.classList.remove('is-selected');
                    const word = words[index];
                    let i = 0;
                    target.textContent = '';
                    const type = setInterval(() => { target.textContent = word.slice(0, ++i); if (i >= word.length) { clearInterval(type); setTimeout(next, duration); } }, 90);
                }, 500);
                return;
            }
            target.classList.remove('is-in');
            target.classList.add('is-out');
            setTimeout(() => {
                target.textContent = words[index];
                target.classList.remove('is-out');
                void target.offsetWidth;
                target.classList.add('is-in');
                setTimeout(next, duration);
            }, 450);
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
        const setOpen = (open) => {
            if (!toggle) { return; }
            toggle.setAttribute('aria-expanded', String(open));
            body.hidden = !open;
            el.classList.toggle('is-collapsed', !open);
        };
        if (toggle) {
            toggle.addEventListener('click', () => setOpen(toggle.getAttribute('aria-expanded') !== 'true'));
            const below = { desktop: Infinity, tablet: 1024, mobile: 767 }[el.dataset.minimizedOn];
            if (below && window.innerWidth <= below) { setOpen(false); }
        }
        const bullet = el.querySelector('template.obx-toc__bullet');
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
            if (bullet) { li.classList.add('has-icon'); const icon = document.createElement('span'); icon.className = 'obx-toc__icon'; icon.appendChild(bullet.content.cloneNode(true)); li.appendChild(icon); }
            li.appendChild(a);
            parent.sub.appendChild(li);
            stack.push({ level, item: li, sub: null });
            links.push([a, h]);
        });
        Array.from(body.children).forEach((child) => { if (!child.matches('template')) { child.remove(); } });
        body.appendChild(top);
        if (el.dataset.collapseSubitems === '1') { el.classList.add('obx-toc--collapse-subitems'); }
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
                    links.forEach(([a, h]) => {
                        a.classList.toggle('is-active', h === entry.target);
                        a.parentElement.classList.toggle('is-active', h === entry.target);
                    });
                    el.querySelectorAll('li.is-open').forEach((li) => li.classList.remove('is-open'));
                    const active = el.querySelector('a.is-active');
                    for (let li = active && active.parentElement; li && li !== el; li = li.parentElement) { if (li.tagName === 'LI') { li.classList.add('is-open'); } }
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
            if (circular) { fill.style.strokeDashoffset = String(el.classList.contains('obx-progress--rtl') ? pct - 100 : 100 - pct); } else { fill.style.width = pct + '%'; }
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

    /* «Load more» and infinite scroll: fetch the next page and append this widget's posts. */
    const loadMore = (el) => {
        if (!once(el, 'obxReady')) { return; }
        const widget = el.closest('[data-id]');
        const grid = widget && widget.querySelector('.obx-posts, .obx-loop');
        if (!grid) { return; }
        const button = el.querySelector('.obx-load-more__button');
        const message = el.querySelector('.obx-load-more__message');
        let next = el.dataset.next;
        let page = Number(el.dataset.page) || 1;
        const max = Number(el.dataset.max) || 1;
        let busy = false;
        let observer = null;
        const finish = () => {
            if (button) { button.hidden = true; }
            if (observer) { observer.disconnect(); }
            el.classList.remove('is-loading');
            if (message && el.dataset.done) { message.textContent = el.dataset.done; message.hidden = false; }
        };
        const load = () => {
            if (busy || !next) { return; }
            busy = true;
            el.classList.add('is-loading');
            if (button) { button.disabled = true; }
            fetch(next, { credentials: 'same-origin' })
                .then((response) => (response.ok ? response.text() : Promise.reject(response.status)))
                .then((html) => {
                    const doc = new DOMParser().parseFromString(html, 'text/html');
                    const id = CSS.escape(widget.dataset.id);
                    const remote = doc.querySelector(`[data-id="${id}"] .obx-posts, [data-id="${id}"] .obx-loop`);
                    const items = remote ? Array.from(remote.children) : [];
                    items.forEach((item) => { const node = document.importNode(item, true); grid.appendChild(node); ready(node); });
                    importStyles(doc);
                    const more = remote && remote.parentElement.querySelector('.obx-load-more');
                    page += 1;
                    next = more && page < max ? more.dataset.next : '';
                    busy = false;
                    el.classList.remove('is-loading');
                    if (button) { button.disabled = false; }
                    if (!items.length || !next) { finish(); }
                })
                .catch(() => { busy = false; el.classList.remove('is-loading'); if (button) { button.disabled = false; } });
        };
        if (button) { button.addEventListener('click', load); }
        if (el.dataset.mode === 'scroll' && 'IntersectionObserver' in window && !document.body.classList.contains('elementor-editor-active')) {
            observer = new IntersectionObserver((entries) => { if (entries.some((e) => e.isIntersecting)) { load(); } }, { rootMargin: '200px' });
            observer.observe(el);
        }
    };

    /* Elementor handlers (and ours) for markup added after the page loaded. */
    const ready = (node) => {
        init(node);
        const fe = window.elementorFrontend;
        if (!fe || !fe.elementsHandler || !window.jQuery) { return; }
        const nodes = node.matches && node.matches('.elementor-element') ? [node] : [];
        node.querySelectorAll('.elementor-element').forEach((el) => nodes.push(el));
        nodes.forEach((el) => { try { fe.elementsHandler.runReadyTrigger(window.jQuery(el)); } catch (e) { /* keep going */ } });
    };
    /* Loop item CSS printed inline in the fetched page (templates not used on this one yet). */
    const importStyles = (doc) => {
        doc.querySelectorAll('link[id^="elementor-post-"], style[id^="elementor-post-"]').forEach((style) => {
            if (style.id && !document.getElementById(style.id)) { document.head.appendChild(document.importNode(style, true)); }
        });
    };

    /* Numbered pagination without reloading (Loop Grid «pagination_load_type: ajax»). */
    const ajaxPages = (nav) => {
        if (!once(nav, 'obxReady')) { return; }
        const widget = nav.closest('[data-id]');
        if (!widget) { return; }
        nav.addEventListener('click', (event) => {
            const link = event.target.closest('a.page-numbers');
            if (!link || document.body.classList.contains('elementor-editor-active')) { return; }
            event.preventDefault();
            const box = nav.closest('.obx-loop-container, .obx-posts-container') || nav.parentElement;
            box.classList.add('is-loading');
            box.setAttribute('aria-busy', 'true');
            fetch(link.href, { credentials: 'same-origin' })
                .then((response) => (response.ok ? response.text() : Promise.reject(response.status)))
                .then((html) => {
                    const doc = new DOMParser().parseFromString(html, 'text/html');
                    const id = CSS.escape(widget.dataset.id);
                    const remote = doc.querySelector(`[data-id="${id}"] .obx-loop-container`) || doc.querySelector(`[data-id="${id}"] .obx-posts-container`);
                    if (!remote) { window.location.href = link.href; return; }
                    importStyles(doc);
                    const fresh = document.importNode(remote, true);
                    box.replaceWith(fresh);
                    ready(fresh);
                    try { window.history.replaceState(window.history.state, '', link.href); } catch (e) { /* not allowed */ }
                    if (nav.dataset.scroll) {
                        const top = widget.getBoundingClientRect().top + window.scrollY - (Number(nav.dataset.offset) || 0);
                        window.scrollTo({ top, behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
                    }
                    const focus = fresh.querySelector('.e-loop-item a, .e-loop-item [tabindex]');
                    if (focus) { focus.focus({ preventScroll: true }); }
                })
                .catch(() => { window.location.href = link.href; });
        });
    };

    /* Search widget: live results printed with the widget's loop item template (REST route obx/v1/search). */
    const liveSearch = (form) => {
        if (!once(form, 'obxReady')) { return; }
        let cfg;
        try { cfg = JSON.parse(form.dataset.live); } catch (e) { return; }
        const field = form.querySelector('.obx-search__field');
        const box = form.querySelector('.obx-search__results');
        const content = box && box.querySelector('.obx-search__content');
        const doc = form.closest('[data-elementor-id]');
        const widget = form.closest('[data-id]');
        if (!field || !content || !doc || !widget) { return; }
        const editor = document.body.classList.contains('elementor-editor-active');
        const min = Number(cfg.min) || 3;
        let timer = 0;
        let seq = 0;
        let controller = null;
        let term = '';
        const toggle = (open) => { box.hidden = !open; field.setAttribute('aria-expanded', String(open)); };
        const load = (page, focusFirst) => {
            const id = ++seq;
            if (controller) { controller.abort(); }
            controller = 'AbortController' in window ? new AbortController() : null;
            const url = new URL(cfg.url, window.location.href);
            url.searchParams.set('post', doc.dataset.elementorId);
            url.searchParams.set('widget', cfg.widget || widget.dataset.id);
            url.searchParams.set('q', term);
            url.searchParams.set('page', String(page));
            form.classList.add('is-loading');
            box.setAttribute('aria-busy', 'true');
            if (box.querySelector('.obx-search__loader')) { toggle(true); }
            fetch(url.toString(), { credentials: 'same-origin', signal: controller ? controller.signal : undefined, headers: cfg.nonce ? { 'X-WP-Nonce': cfg.nonce } : {} })
                .then((response) => (response.ok ? response.json() : Promise.reject(response.status)))
                .then((data) => {
                    if (id !== seq) { return; }
                    content.innerHTML = (data && data.html) || '';
                    ready(content);
                    toggle(content.innerHTML.trim() !== '');
                    if (focusFirst) { const first = box.querySelector('.e-loop-item a[href], .e-loop-item [tabindex]'); if (first) { first.focus({ preventScroll: true }); } }
                })
                .catch((error) => { if (id === seq && !(error && error.name === 'AbortError')) { toggle(false); } })
                .finally(() => { if (id === seq) { form.classList.remove('is-loading'); box.removeAttribute('aria-busy'); } });
        };
        field.addEventListener('input', () => {
            window.clearTimeout(timer);
            const value = field.value.trim();
            if (value.length < min) { seq += 1; term = ''; content.innerHTML = ''; form.classList.remove('is-loading'); toggle(false); return; }
            timer = window.setTimeout(() => { if (value !== term) { term = value; load(1, false); } else { toggle(content.innerHTML.trim() !== ''); } }, 300);
        });
        field.addEventListener('focus', () => { if (term && field.value.trim() === term && content.innerHTML.trim() !== '') { toggle(true); } });
        form.addEventListener('reset', () => { window.clearTimeout(timer); seq += 1; term = ''; content.innerHTML = ''; toggle(false); window.setTimeout(() => field.focus(), 0); });
        box.addEventListener('click', (event) => {
            const button = event.target.closest('[data-page]');
            if (!button || button.classList.contains('is-current')) { return; }
            event.preventDefault();
            load(Number(button.dataset.page) || 1, true);
        });
        const focusables = () => Array.from(box.querySelectorAll('a[href], button:not([disabled]), [tabindex]:not([tabindex="-1"])')).filter((el) => el.offsetParent !== null);
        form.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && !box.hidden) { event.preventDefault(); toggle(false); field.focus(); return; }
            if ((event.key !== 'ArrowDown' && event.key !== 'ArrowUp') || box.hidden) { return; }
            if (event.target === field && event.key === 'ArrowUp') { return; }
            const list = focusables();
            if (!list.length) { return; }
            event.preventDefault();
            const at = list.indexOf(document.activeElement);
            const next = at + (event.key === 'ArrowDown' ? 1 : -1);
            if (next < 0) { field.focus(); return; }
            list[Math.min(next, list.length - 1)].focus();
        });
        if (!editor) {
            document.addEventListener('click', (event) => { if (!form.contains(event.target)) { toggle(false); } });
            form.addEventListener('focusout', (event) => { if (event.relatedTarget && !form.contains(event.relatedTarget)) { toggle(false); } });
        }
    };

    /* Full screen search: open/close the overlay. */
    const searchToggle = (el) => {
        if (!once(el, 'obxReady')) { return; }
        const open = el.querySelector('.obx-search-toggle__open');
        const overlay = el.querySelector('.obx-search-overlay');
        if (!open || !overlay) { return; }
        const set = (state) => {
            overlay.hidden = !state;
            open.setAttribute('aria-expanded', String(state));
            if (state) { const field = overlay.querySelector('input'); if (field) { field.focus(); } } else { open.focus(); }
        };
        open.addEventListener('click', () => set(true));
        overlay.querySelector('.obx-search-overlay__close').addEventListener('click', () => set(false));
        overlay.addEventListener('click', (event) => { if (event.target === overlay) { set(false); } });
        overlay.addEventListener('keydown', (event) => { if (event.key === 'Escape') { set(false); } });
    };


    /* Form: steps, validation and submission to our REST endpoint. */
    const form = (el) => {
        if (!once(el, 'obxReady')) { return; }
        let messages = {};
        try { messages = JSON.parse(el.dataset.messages || '{}'); } catch (e) { messages = {}; }
        const box = el.querySelector('.obx-form__message');
        const custom = el.hasAttribute('novalidate');
        const steps = Array.from(el.querySelectorAll('.obx-form__step'));
        let current = 0;
        const say = (text, ok) => {
            if (!box) { return; }
            box.textContent = text || '';
            box.hidden = !text;
            box.classList.toggle('is-success', !!ok);
            box.classList.toggle('is-error', !ok);
        };
        const fieldError = (wrap, text) => {
            const slot = wrap && wrap.querySelector('.obx-form__error');
            if (!slot) { return; }
            slot.textContent = text || '';
            slot.hidden = !text;
            wrap.classList.toggle('has-error', !!text);
        };
        const clear = (scope) => scope.querySelectorAll('.obx-form__field.has-error').forEach((w) => fieldError(w, ''));
        const check = (scope) => {
            let valid = true;
            let first = null;
            clear(scope);
            scope.querySelectorAll('.obx-form__field').forEach((wrap) => {
                const group = wrap.querySelector('.obx-form__group[data-required]');
                if (group && !group.querySelector('input:checked')) { fieldError(wrap, messages.required_field_message); valid = false; first = first || group.querySelector('input'); return; }
                wrap.querySelectorAll('input, select, textarea').forEach((input) => {
                    if (input.type === 'file' && input.files) {
                        const max = Number(input.dataset.maxSize) * 1048576;
                        const files = Array.from(input.files);
                        if (input.dataset.maxFiles && files.length > Number(input.dataset.maxFiles)) { input.setCustomValidity(messages.invalid_message || 'invalid'); }
                        else if (max && files.some((f) => f.size > max)) { input.setCustomValidity(messages.invalid_message || 'invalid'); }
                        else { input.setCustomValidity(''); }
                    }
                    if (!input.checkValidity()) {
                        valid = false;
                        first = first || input;
                        fieldError(wrap, input.validity.valueMissing ? messages.required_field_message : (custom ? messages.invalid_message : input.validationMessage));
                    }
                });
            });
            if (first) { first.focus(); }
            return valid;
        };
        const indicators = Array.from(el.querySelectorAll('.obx-form__indicator'));
        const bar = el.querySelector('.obx-form__progress');
        const show = (index) => {
            current = index;
            steps.forEach((step, i) => { step.hidden = i !== index; });
            indicators.forEach((li, i) => { li.classList.toggle('is-active', i === index); li.classList.toggle('is-completed', i < index); });
            if (bar) {
                const pct = Math.round(((index + 1) / steps.length) * 100);
                bar.setAttribute('aria-valuenow', String(pct));
                const fill = bar.querySelector('.obx-form__progress-fill');
                if (fill) { fill.style.width = pct + '%'; fill.textContent = pct + '%'; }
            }
            const focus = steps[index] && steps[index].querySelector('input:not([type=hidden]), select, textarea');
            if (focus) { focus.focus(); }
        };
        // Native validation stops the submit event: show the same inline messages.
        el.addEventListener('invalid', (event) => {
            const input = event.target;
            fieldError(input.closest('.obx-form__field'), input.validity.valueMissing ? messages.required_field_message : (custom ? messages.invalid_message : input.validationMessage));
        }, true);
        el.addEventListener('input', (event) => { const wrap = event.target.closest('.obx-form__field.has-error'); if (wrap && event.target.checkValidity()) { fieldError(wrap, ''); } });
        el.addEventListener('click', (event) => {
            if (event.target.closest('.obx-form__next')) { event.preventDefault(); if (check(steps[current])) { show(current + 1); } }
            if (event.target.closest('.obx-form__previous')) { event.preventDefault(); show(current - 1); }
        });
        el.addEventListener('submit', (event) => {
            event.preventDefault();
            say('');
            if (!check(el)) { return; }
            const button = el.querySelector('.obx-form__submit');
            el.classList.add('is-sending');
            if (button) { button.disabled = true; }
            fetch(el.action, { method: 'POST', body: new FormData(el), credentials: 'same-origin', headers: { Accept: 'application/json' } })
                .then((response) => response.json().catch(() => ({ success: false, data: {} })))
                .then((json) => {
                    const data = json.data || {};
                    if (json.success) {
                        el.reset();
                        if (steps.length) { show(0); }
                        say(data.message || messages.success_message, true);
                        el.dispatchEvent(new CustomEvent('obx:form:success', { bubbles: true, detail: data }));
                        // Popup action: open another popup or close the one holding the form.
                        const popups = window.elementorProFrontend && window.elementorProFrontend.modules && window.elementorProFrontend.modules.popup;
                        if (data.popup && popups) {
                            const owner = el.closest('[data-obx-popup]');
                            if (data.popup.action === 'open') { popups.showPopup({ id: data.popup.id }); }
                            if (data.popup.action === 'close' && owner) { window.setTimeout(() => popups.closePopup({ id: owner.dataset.obxPopup, doNotShowAgain: !!data.popup.do_not_show_again }), 1000); }
                        }
                        if (data.redirect_url) { window.location.href = data.redirect_url; }
                        return;
                    }
                    let firstStep = -1;
                    Object.entries(data.errors || {}).forEach(([id, text]) => {
                        const wrap = el.querySelector('.obx-form__field--' + CSS.escape(id));
                        fieldError(wrap, text);
                        const step = wrap && wrap.closest('.obx-form__step');
                        if (step && firstStep < 0) { firstStep = steps.indexOf(step); }
                    });
                    if (firstStep >= 0) { show(firstStep); }
                    say((data.message || messages.error_message) + (data.admin_error ? ' (' + data.admin_error + ')' : ''), false);
                })
                .catch(() => say(messages.server_message || messages.error_message, false))
                .finally(() => { el.classList.remove('is-sending'); if (button) { button.disabled = false; } });
        });
    };


    /* Carousel on CSS scroll-snap: slides per view and per step come from CSS variables. */
    const carousel = (el) => {
        if (!once(el, 'obxReady')) { return; }
        const track = el.querySelector(':scope > .obx-carousel__track');
        if (!track) { return; }
        let options = {};
        try { options = JSON.parse(el.dataset.carousel || '{}'); } catch (e) { options = {}; }
        const slides = () => Array.from(track.children).filter((c) => c.classList.contains('obx-carousel__slide') || c.classList.contains('e-con'));
        const css = (name, fallback) => { const v = parseInt(getComputedStyle(el).getPropertyValue(name), 10); return v > 0 ? v : fallback; };
        const perView = () => Math.min(css('--obx-slides', 1), Math.max(1, slides().length));
        const perStep = () => Math.max(1, Math.min(css('--obx-scroll', 1), perView()));
        const pages = () => Math.max(1, Math.ceil((slides().length - perView()) / perStep()) + 1);
        const rtl = getComputedStyle(el).direction === 'rtl';
        const pagination = el.querySelector(':scope > .obx-carousel__pagination');
        if (options.fade) { el.classList.add('obx-carousel--fade'); el.style.setProperty('--obx-slides', '1'); }
        let page = 0;
        const go = (target, smooth = true) => {
            const total = pages();
            if (options.loop) { target = (target + total) % total; } else { target = Math.max(0, Math.min(total - 1, target)); }
            const list = slides();
            const slide = list[Math.min(list.length - 1, target * perStep())];
            if (!slide) { return; }
            page = target;
            if (options.fade) { paint(); return; }
            const first = list[0];
            const left = rtl ? (slide.offsetLeft + slide.offsetWidth) - (first.offsetLeft + first.offsetWidth) : slide.offsetLeft - first.offsetLeft;
            track.scrollTo({ left, behavior: smooth && options.speed !== 0 ? 'smooth' : 'auto' });
            paint();
        };
        // Arrows and dots are role="button" elements (not <button>, so theme button styles stay away): Enter and Space press them.
        const pressable = (node) => node.addEventListener('keydown', (event) => { if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); node.click(); } });
        const paint = () => {
            // Visible slides are marked .is-active (fade transition, content animation, Ken Burns).
            const from = Math.min(page * perStep(), Math.max(0, slides().length - perView()));
            slides().forEach((slide, i) => slide.classList.toggle('is-active', i >= from && i < from + perView()));
            if (!pagination) { return; }
            const total = pages();
            if (pagination.classList.contains('obx-carousel__pagination--bullets')) {
                if (pagination.children.length !== total) {
                    pagination.textContent = '';
                    for (let i = 0; i < total; i++) {
                        const dot = document.createElement('span');
                        dot.className = 'obx-carousel__dot';
                        dot.setAttribute('role', 'button');
                        dot.tabIndex = 0;
                        pressable(dot);
                        dot.setAttribute('aria-label', 'Ir a la diapositiva ' + (i + 1));
                        dot.addEventListener('click', () => { interact(); go(i); });
                        pagination.appendChild(dot);
                    }
                }
                Array.from(pagination.children).forEach((dot, i) => { dot.classList.toggle('is-active', i === page); dot.setAttribute('aria-current', i === page ? 'true' : 'false'); });
            } else if (pagination.classList.contains('obx-carousel__pagination--fraction')) {
                pagination.textContent = (page + 1) + ' / ' + total;
            } else {
                pagination.style.setProperty('--obx-progress', String((page + 1) / total));
            }
        };
        let timer = null;
        let stopped = false;
        const stop = () => { clearInterval(timer); timer = null; };
        const start = () => { stop(); if (options.autoplay && !stopped && pages() > 1) { timer = setInterval(() => go(page + 1), options.autoplaySpeed || 5000); } };
        const interact = () => { if (options.pauseOnInteraction) { stopped = true; stop(); } };
        el.querySelectorAll(':scope > .obx-carousel__arrow').forEach((arrow) => {
            pressable(arrow);
            arrow.addEventListener('click', () => { interact(); go(page + (arrow.classList.contains('obx-carousel__arrow--next') ? 1 : -1)); });
        });
        let settle = null;
        track.addEventListener('scroll', () => {
            clearTimeout(settle);
            settle = setTimeout(() => {
                const list = slides();
                if (list.length < 2) { return; }
                const step = Math.abs(list[1].offsetLeft - list[0].offsetLeft) || 1;
                const index = Math.round(Math.abs(track.scrollLeft) / step);
                const atEnd = Math.abs(track.scrollLeft) + track.clientWidth >= track.scrollWidth - 2;
                page = atEnd ? pages() - 1 : Math.min(pages() - 1, Math.round(index / perStep()));
                paint();
            }, 80);
        }, { passive: true });
        track.addEventListener('pointerdown', interact, { passive: true });
        el.addEventListener('keydown', (event) => {
            if (event.key === 'ArrowRight' || event.key === 'ArrowLeft') {
                const forward = (event.key === 'ArrowRight') !== rtl;
                interact(); go(page + (forward ? 1 : -1)); event.preventDefault();
            }
        });
        if (options.pauseOnHover) { el.addEventListener('mouseenter', stop); el.addEventListener('mouseleave', start); }
        el.addEventListener('focusin', stop);
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) { options.autoplay = false; }
        window.addEventListener('resize', () => { go(Math.min(page, pages() - 1), false); });
        slides().forEach((slide, i, all) => { if (!slide.hasAttribute('role')) { slide.setAttribute('role', 'group'); slide.setAttribute('aria-roledescription', 'diapositiva'); slide.setAttribute('aria-label', (i + 1) + ' / ' + all.length); } });
        el.classList.add('is-ready');
        paint();
        start();
    };

    /* Gallery filter bar (several galleries in one widget). */
    const galleryFilters = (bar) => {
        if (!once(bar, 'obxReady')) { return; }
        const gallery = bar.nextElementSibling;
        if (!gallery || !gallery.classList.contains('obx-gallery')) { return; }
        const show = (key) => {
            bar.querySelectorAll('.obx-gallery__filter').forEach((b) => { const on = b.dataset.gallery === key; b.classList.toggle('is-active', on); b.setAttribute('aria-selected', String(on)); });
            gallery.querySelectorAll('.obx-gallery__item').forEach((item) => { item.hidden = key !== 'all' && !item.dataset.galleries.split(',').includes(key); });
            gallery.querySelectorAll('[data-elementor-lightbox-slideshow]').forEach((a) => { a.dataset.elementorLightboxSlideshow = a.dataset.elementorLightboxSlideshow.replace(/^[^-]+-/, key + '-'); });
        };
        bar.addEventListener('click', (event) => { const b = event.target.closest('.obx-gallery__filter'); if (b) { show(b.dataset.gallery); } });
        if (gallery.dataset.initial !== undefined) { show(gallery.dataset.initial); }
    };

    /* Menu cart: opens the side panel or dropdown; WooCommerce refreshes its contents through cart fragments. */
    const menuCarts = new Set();
    const menuCart = (el) => {
        if (!once(el, 'obxReady')) { return; }
        let config = {};
        try { config = JSON.parse(el.dataset.obxMenuCart || '{}'); } catch (e) { config = {}; }
        const button = el.querySelector('.obx-menu-cart__toggle-button');
        const container = el.querySelector('.obx-menu-cart__container');
        if (!button || !container) { return; }
        const editor = document.body.classList.contains('elementor-editor-active');
        let timer = 0;
        const set = (open) => {
            el.classList.toggle('is-open', open);
            button.setAttribute('aria-expanded', String(open));
            container.setAttribute('aria-hidden', String(!open));
            if (open && el.classList.contains('obx-menu-cart--side-cart')) { const close = container.querySelector('.obx-menu-cart__close'); if (close) { close.focus({ preventScroll: true }); } }
        };
        el.obxMenuCart = { open: () => set(true), close: () => set(false), config };
        menuCarts.add(el);
        button.addEventListener('click', (event) => { event.preventDefault(); set(!el.classList.contains('is-open')); });
        if (config.open === 'mouseover') {
            el.addEventListener('mouseenter', () => { clearTimeout(timer); set(true); });
            el.addEventListener('mouseleave', () => { if (!editor) { timer = setTimeout(() => set(false), 250); } });
        }
        container.addEventListener('click', (event) => {
            if (event.target === container || event.target.closest('.obx-menu-cart__close')) { event.preventDefault(); set(false); button.focus({ preventScroll: true }); return; }
            // Without automatic updates, removing a product reloads the page through WooCommerce's remove URL.
            const remove = event.target.closest('.remove_from_cart_button');
            if (remove && !config.live) { event.stopImmediatePropagation(); }
        }, true);
        el.addEventListener('keydown', (event) => { if (event.key === 'Escape' && el.classList.contains('is-open')) { set(false); button.focus(); } });
        document.addEventListener('click', (event) => {
            if (!editor && el.classList.contains('obx-menu-cart--mini-cart') && el.classList.contains('is-open') && !el.contains(event.target)) { set(false); }
        });
    };
    if (window.jQuery) {
        window.jQuery(document.body).on('added_to_cart', () => {
            menuCarts.forEach((el) => { if (el.isConnected && el.obxMenuCart.config.autoOpen) { el.obxMenuCart.open(); } });
        });
    }

    /* WooCommerce pages: hover animations on WooCommerce's buttons, again after its AJAX refreshes. */
    const wooAnimated = new Set();
    const animateAll = () => wooAnimated.forEach((el) => {
        if (!el.isConnected) { wooAnimated.delete(el); return; }
        let map = {};
        try { map = JSON.parse(el.dataset.obxAnimations || '{}'); } catch (e) { map = {}; }
        Object.entries(map).forEach(([selector, cls]) => el.querySelectorAll(selector).forEach((node) => node.classList.add(cls)));
    });
    const wooAnimations = (el) => { if (!once(el, 'obxAnimated')) { return; } wooAnimated.add(el); animateAll(); };
    /* Cart: changing a quantity updates the cart without the «Update cart» button. */
    const wooCartAuto = (el) => {
        if (!once(el, 'obxAutoCart')) { return; }
        let timer = 0;
        const update = () => {
            clearTimeout(timer);
            timer = setTimeout(() => {
                const button = el.querySelector('button[name="update_cart"]');
                if (button) { button.disabled = false; button.removeAttribute('aria-disabled'); button.click(); }
            }, 600);
        };
        el.addEventListener('change', (event) => { if (event.target.matches('input.qty')) { update(); } });
        el.addEventListener('input', (event) => { if (event.target.matches('input.qty')) { update(); } });
    };
    if (window.jQuery) {
        window.jQuery(document.body).on('updated_wc_div updated_cart_totals updated_checkout updated_shipping_method', () => setTimeout(animateAll, 0));
    }

    /**
     * Masonry for post grids: the grid keeps its columns and order, and each post moves up to the bottom of the
     * post above it (negative top margin), so there are no gaps. Recomputed when sizes or the posts change.
     */
    const masonry = (grid) => {
        if (grid.dataset.obxMasonry) { return; }
        grid.dataset.obxMasonry = '1';
        let frame = 0;
        const layout = () => {
            frame = 0;
            const items = Array.from(grid.children).filter((item) => item.matches('.obx-post'));
            items.forEach((item) => { item.style.marginTop = ''; });
            const columns = getComputedStyle(grid).gridTemplateColumns.split(' ').filter(Boolean).length || 1;
            if (columns < 2) { return; }
            const gap = parseFloat(getComputedStyle(grid).rowGap) || 0;
            const bottoms = [];
            items.forEach((item, index) => {
                const column = index % columns;
                const rect = item.getBoundingClientRect();
                if (index >= columns) { item.style.marginTop = (bottoms[column] + gap - rect.top) + 'px'; }
                bottoms[column] = item.getBoundingClientRect().bottom;
            });
        };
        const schedule = () => { if (!frame) { frame = requestAnimationFrame(layout); } };
        schedule();
        if (window.ResizeObserver) { new ResizeObserver(schedule).observe(grid); }
        new MutationObserver(schedule).observe(grid, { childList: true });
        grid.addEventListener('load', schedule, true);
        window.addEventListener('resize', schedule);
    };
    const handlers = {
        '.obx-countdown': countdown, '.obx-headline': headline, '.obx-toc': toc, '.obx-progress': progress, '.obx-code': code,
        '.obx-load-more': loadMore, '.obx-pagination[data-ajax]': ajaxPages, '.obx-search-toggle': searchToggle, '.obx-search[data-live]': liveSearch, 'form[data-obx-form]': form, '.obx-carousel': carousel, '.obx-gallery__filters': galleryFilters, '[data-obx-menu-cart]': menuCart, '[data-obx-animations]': wooAnimations, '.obx-woo-cart--auto-update': wooCartAuto,
        '.obx-posts--masonry': masonry,
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
        // «global» runs for every element, whatever its skin («posts.classic», «countdown.default»…).
        window.elementorFrontend.hooks.addAction('frontend/element_ready/global', ($scope) => init($scope[0] || $scope));
    });
})();
