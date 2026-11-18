/*
 * Editor: display conditions per element (Advanced tab). Edits the hidden `e_display_conditions`
 * setting in Elementor Pro's format: [ JSON ] with groups (any may pass) of conditions (all must pass).
 */
(function ($) {
    'use strict';
    const cfg = window.obxDisplayConditions;
    if (!cfg) { return; }
    let container = null;

    const el = (tag, attrs = {}, children = []) => {
        const node = document.createElement(tag);
        Object.entries(attrs).forEach(([k, v]) => {
            if (v === undefined || v === null || v === false) { return; }
            if (k === 'class') { node.className = v; } else if (k === 'text') { node.textContent = v; } else if (k.startsWith('on')) { node.addEventListener(k.slice(2), v); } else { node.setAttribute(k, v === true ? '' : v); }
        });
        [].concat(children).forEach((child) => { if (child) { node.appendChild(typeof child === 'string' ? document.createTextNode(child) : child); } });
        return node;
    };

    /** Saved value → groups; an older flat list counts as one group. */
    const parse = (value) => {
        const json = Array.isArray(value) ? value[0] : value;
        let groups = [];
        try { groups = json ? JSON.parse(json) : []; } catch (e) { groups = []; }
        if (!Array.isArray(groups)) { return []; }
        if (groups.some((g) => !Array.isArray(g))) { return [groups.filter((c) => c && c.condition)]; }
        return groups.filter((g) => g.length);
    };
    const count = (groups) => groups.reduce((n, g) => n + g.length, 0);

    const fresh = (name) => {
        const def = cfg.conditions[name];
        const condition = { condition: name };
        Object.entries(def.fields).forEach(([key, spec]) => {
            if (spec.default !== undefined) { condition[key] = Array.isArray(spec.default) ? [] : spec.default; } else if (spec.type === 'query') { condition[key] = []; }
        });
        return condition;
    };

    /* Dates: m-d-Y (stored) ↔ Y-m-d (input). Times: m-d-Y H:i ↔ H:i. */
    const toInputDate = (v) => { const m = /^(\d{2})-(\d{2})-(\d{4})/.exec(v || ''); return m ? `${m[3]}-${m[1]}-${m[2]}` : ''; };
    const fromInputDate = (v) => { const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(v || ''); return m ? `${m[2]}-${m[3]}-${m[1]}` : ''; };
    const toInputTime = (v) => { const m = /(\d{2}):(\d{2})$/.exec(v || ''); return m ? `${m[1]}:${m[2]}` : ''; };
    const fromInputTime = (v) => {
        if (!/^\d{2}:\d{2}$/.test(v || '')) { return ''; }
        const d = new Date(); const p = (n) => String(n).padStart(2, '0');
        return `${p(d.getMonth() + 1)}-${p(d.getDate())}-${d.getFullYear()} ${v}`;
    };

    const select = (options, value, onchange, label) => {
        const node = el('select', { class: 'obx-dc__select', 'aria-label': label, onchange: (e) => onchange(e.target.value) });
        const add = (parent, key, text) => parent.appendChild(el('option', { value: key, text, selected: String(value) === key }));
        Object.entries(options).forEach(([key, text]) => {
            if (text && typeof text === 'object') {
                const group = el('optgroup', { label: key });
                Object.entries(text).forEach(([k, t]) => add(group, k, t));
                node.appendChild(group);
            } else { add(node, key, text); }
        });
        return node;
    };

    const chips = (items, onremove) => el('span', { class: 'obx-dc__chips' }, items.map((item, i) => el('span', { class: 'obx-dc__chip' }, [
        item.text,
        el('button', { type: 'button', class: 'obx-dc__chip-remove', 'aria-label': `Quitar ${item.text}`, onclick: () => onremove(i) }, '×'),
    ])));

    /** Multiple fixed options (roles, days): chips plus a select to add. */
    const multi = (spec, values, set) => {
        const wrap = el('span', { class: 'obx-dc__multi' });
        const draw = () => {
            wrap.textContent = '';
            wrap.appendChild(chips(values.map((v) => ({ id: v, text: spec.options[v] || v })), (i) => { values.splice(i, 1); set(values.slice()); draw(); }));
            const left = Object.fromEntries(Object.entries(spec.options).filter(([k]) => !values.includes(k)));
            if (Object.keys(left).length) {
                wrap.appendChild(select(Object.assign({ '': 'Añadir…' }, left), '', (v) => { if (v) { values.push(v); set(values.slice()); draw(); } }, 'Añadir'));
            }
        };
        draw();
        return wrap;
    };

    /** Searchable values (posts, terms, authors) stored as {id, text}. */
    const query = (spec, values, set) => {
        const wrap = el('span', { class: 'obx-dc__query' });
        const list = el('span', { class: 'obx-dc__results', role: 'listbox', hidden: true });
        let timer = 0;
        const input = el('input', { type: 'search', class: 'obx-dc__input', placeholder: 'Buscar…', 'aria-label': 'Buscar', autocomplete: 'off' });
        const search = () => {
            const params = new URLSearchParams(Object.assign({ object: spec.object, q: input.value }, spec.query || {}));
            fetch(`${cfg.rest}?${params}`, { headers: { 'X-WP-Nonce': cfg.nonce }, credentials: 'same-origin' })
                .then((r) => (r.ok ? r.json() : [])).then((items) => {
                    list.textContent = '';
                    items.filter((item) => !values.some((v) => String(v.id) === String(item.id))).forEach((item) => {
                        list.appendChild(el('button', { type: 'button', class: 'obx-dc__result', role: 'option', text: item.text, onmousedown: (e) => { e.preventDefault(); values.push({ id: item.id, text: item.text }); set(values.slice()); draw(); } }));
                    });
                    list.hidden = !list.childNodes.length;
                }).catch(() => { list.hidden = true; });
        };
        input.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(search, 250); });
        input.addEventListener('focus', search);
        input.addEventListener('blur', () => setTimeout(() => { list.hidden = true; }, 150));
        const draw = () => {
            wrap.textContent = '';
            wrap.appendChild(chips(values, (i) => { values.splice(i, 1); set(values.slice()); draw(); }));
            wrap.appendChild(el('span', { class: 'obx-dc__search' }, [input, list]));
            input.value = '';
            list.hidden = true;
        };
        draw();
        return wrap;
    };

    const field = (key, spec, condition, redraw) => {
        const set = (v) => { condition[key] = v; };
        const value = condition[key];
        switch (spec.type) {
            case 'comparator': {
                const options = Object.fromEntries(spec.options.map((k) => [k, cfg.comparators[k] || k]));
                const node = select(options, value || spec.default, (v) => { set(v); redraw(); }, 'Comparación');
                node.classList.add('obx-dc__select--comparator');
                return node;
            }
            case 'select': return select(spec.options, value || spec.default, set, spec.label || key);
            case 'multi': return multi(spec, Array.isArray(value) ? value.slice() : [], set);
            case 'query': return query(spec, Array.isArray(value) ? value.slice() : [], set);
            case 'date': return el('input', { type: 'date', class: 'obx-dc__input', value: toInputDate(value), 'aria-label': spec.label || 'Fecha', onchange: (e) => set(fromInputDate(e.target.value)) });
            case 'time': return el('input', { type: 'time', class: 'obx-dc__input', value: toInputTime(value), 'aria-label': spec.label || 'Hora', onchange: (e) => set(fromInputTime(e.target.value)) });
            case 'number': return el('input', { type: 'number', class: 'obx-dc__input obx-dc__input--number', min: spec.min, step: 1, value: value ?? spec.default, 'aria-label': 'Número', oninput: (e) => set(e.target.value) });
            default:
                if ((spec.hide_for || []).includes(condition.comparator)) { return null; }
                return el('input', { type: 'text', class: 'obx-dc__input', value: value || '', placeholder: spec.placeholder || 'Valor', 'aria-label': 'Valor', oninput: (e) => set(e.target.value) });
        }
    };

    const conditionOptions = () => {
        const options = {};
        Object.entries(cfg.groups).forEach(([group, label]) => {
            const items = Object.entries(cfg.conditions).filter(([, def]) => def.group === group);
            if (items.length) { options[label] = Object.fromEntries(items.map(([name, def]) => [name, def.label])); }
        });
        return options;
    };

    const open = () => {
        if (!container) { return; }
        const groups = parse(container.settings.get(cfg.key)).map((g) => g.map((c) => Object.assign({}, c)));
        const previous = document.activeElement;
        const body = el('div', { class: 'obx-dc__groups' });
        const addGroup = el('button', { type: 'button', class: 'obx-dc__add obx-dc__add--group', onclick: () => { groups.push([fresh('login_status')]); draw(); } });
        const close = () => { overlay.remove(); document.removeEventListener('keydown', keys, true); if (previous && previous.focus) { previous.focus(); } };
        const keys = (e) => { if (e.key === 'Escape') { e.stopPropagation(); close(); } };
        const draw = () => {
            body.textContent = '';
            addGroup.textContent = groups.length ? '+ O bien…' : '+ Añadir condición';
            if (!groups.length) { body.appendChild(el('p', { class: 'obx-dc__empty', text: 'Sin condiciones: el elemento se muestra siempre.' })); }
            groups.forEach((group, gi) => {
                if (gi) { body.appendChild(el('div', { class: 'obx-dc__or', text: 'o' })); }
                const box = el('div', { class: 'obx-dc__group', role: 'group', 'aria-label': `Grupo ${gi + 1}` });
                group.forEach((condition, ci) => {
                    if (ci) { box.appendChild(el('div', { class: 'obx-dc__and', text: 'y' })); }
                    const def = cfg.conditions[condition.condition];
                    const row = el('div', { class: 'obx-dc__row' });
                    const picker = select(conditionOptions(), condition.condition, (name) => { group[ci] = fresh(name); draw(); }, 'Condición');
                    picker.classList.add('obx-dc__select--condition');
                    row.appendChild(picker);
                    if (def) {
                        Object.entries(def.fields).forEach(([key, spec]) => { const node = field(key, spec, condition, draw); if (node) { row.appendChild(node); } });
                    } else {
                        row.appendChild(el('span', { class: 'obx-dc__unknown', text: `«${condition.condition}» no está disponible aquí; se conserva tal cual.` }));
                    }
                    row.appendChild(el('button', { type: 'button', class: 'obx-dc__remove', 'aria-label': 'Quitar condición', title: 'Quitar condición', onclick: () => { group.splice(ci, 1); if (!group.length) { groups.splice(gi, 1); } draw(); } }, el('i', { class: 'eicon-close', 'aria-hidden': 'true' })));
                    box.appendChild(row);
                });
                box.appendChild(el('button', { type: 'button', class: 'obx-dc__add', onclick: () => { group.push(fresh('login_status')); draw(); } }, '+ Y además…'));
                body.appendChild(box);
            });
        };
        const save = () => {
            const clean = groups.map((g) => g.filter((c) => c && c.condition)).filter((g) => g.length);
            $e.run('document/elements/settings', { container, settings: { [cfg.key]: clean.length ? [JSON.stringify(clean)] : '' } });
            close();
            badge();
        };
        const overlay = el('div', { class: 'obx-dc', role: 'dialog', 'aria-modal': 'true', 'aria-labelledby': 'obx-dc-title', onclick: (e) => { if (e.target === overlay) { close(); } } }, el('div', { class: 'obx-dc__box' }, [
            el('header', { class: 'obx-dc__header' }, [
                el('i', { class: 'eicon-flow', 'aria-hidden': 'true' }),
                el('h2', { id: 'obx-dc-title', class: 'obx-dc__title', text: 'Condiciones de visualización' }),
                el('button', { type: 'button', class: 'obx-dc__close', 'aria-label': 'Cerrar', onclick: () => close() }, el('i', { class: 'eicon-close', 'aria-hidden': 'true' })),
            ]),
            el('p', { class: 'obx-dc__intro', text: 'El elemento solo se muestra si se cumple algún grupo. Dentro de un grupo deben cumplirse todas las condiciones. En el editor siempre se ve.' }),
            body,
            addGroup,
            el('footer', { class: 'obx-dc__footer' }, [
                el('button', { type: 'button', class: 'elementor-button e-btn-txt', onclick: () => close() }, 'Cancelar'),
                el('button', { type: 'button', class: 'elementor-button e-primary obx-dc__save', onclick: save }, 'Guardar'),
            ]),
        ]));
        draw();
        document.body.appendChild(overlay);
        document.addEventListener('keydown', keys, true);
        const first = overlay.querySelector('select, button.obx-dc__add');
        if (first) { first.focus(); }
    };

    /** Number of conditions next to the trigger in the panel. */
    const badge = () => {
        if (!container) { return; }
        const n = count(parse(container.settings.get(cfg.key)));
        document.querySelectorAll('.obx-dc-control__button').forEach((button) => {
            button.classList.toggle('is-active', n > 0);
            const counter = button.querySelector('.obx-dc-control__count');
            const text = n ? String(n) : '';
            if (counter && counter.textContent !== text) { counter.textContent = text; }
        });
    };

    $(window).on('elementor:init', () => {
        ['widget', 'section', 'column', 'container'].forEach((type) => {
            elementor.hooks.addAction(`panel/open_editor/${type}`, (panel, model, view) => {
                container = view && view.getContainer ? view.getContainer() : null;
                setTimeout(badge, 0);
            });
        });
        $(document).on('click', '[data-obx-display-conditions]', (e) => { e.preventDefault(); open(); });
        // The trigger is re-rendered whenever the Advanced tab or a section opens.
        new MutationObserver(() => { if (document.querySelector('.obx-dc-control__button')) { badge(); } }).observe(document.getElementById('elementor-panel') || document.body, { childList: true, subtree: true });
    });
})(jQuery);
