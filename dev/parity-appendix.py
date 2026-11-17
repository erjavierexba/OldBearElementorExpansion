#!/usr/bin/env python3
"""Genera los anexos de docs/PARIDAD-PRO.md a partir de spec-missing.json. Uso: parity-appendix.py spec-missing.json"""
import json,sys,re,collections
spec=json.load(open(sys.argv[1]))
grp=re.compile(r'^(.*?)_(background|typography|box_shadow|text_shadow|border|css_filters|image_size|text_stroke)(_.*)?$')
resp=re.compile(r'_(tablet|mobile|laptop|tablet_extra|mobile_extra|widescreen)$')
ignore=re.compile(r'^(motion_fx_transform_origin_[xy]|e_display_conditions|motion_fx_scale_range|handle_motion_fx_asset_loading|custom_css)$')
def collapse(d):
    out=collections.OrderedDict()
    for k,v in d.items():
        if ignore.match(k) or k.startswith('__'): continue
        if isinstance(v,dict) and ('__missing_options' in v or '__missing_fields' in v): continue
        if v.get('type') in ('tabs','tab','heading','raw_html','divider','notice','alert'): continue
        m=grp.match(k)
        if m: out.setdefault(f'{m.group(1)}_{m.group(2)}*', 'grupo')
        else: out.setdefault(resp.sub('',k), v.get('type',''))
    return out
def extras(d):
    r=[]
    for k,v in d.items():
        if isinstance(v,dict) and '__missing_options' in v:
            o=[x for x in v['__missing_options'] if x!='']
            if o and not k.startswith('motion_fx'): r.append(f"`{k}` sin las opciones {', '.join('`'+x+'`' for x in o)}")
        if isinstance(v,dict) and '__missing_fields' in v:
            f=[x for x in v['__missing_fields'] if not x.endswith('_note')]
            if f: r.append(f"`{k}` (repetidor) sin los campos {', '.join('`'+x+'`' for x in f)}")
    return r
print('## Anexo A · Ajustes ausentes por widget\n')
print('Generado desde el inventario (Pro 4.2.3 y 4.1.2 con WooCommerce frente a OBX 0.7.3). Los grupos de controles se muestran como `prefijo_grupo*` (fondo, tipografía, borde, sombra…); las variantes por dispositivo se cuentan una vez. Se omiten los ajustes comunes a todos los widgets (ver §3).\n')
rows=[]
for w,d in spec['widgets'].items():
    if d.get('__missing_widget'): continue
    c=collapse(d); e=extras(d)
    if c or e: rows.append((w,c,e))
for w,c,e in sorted(rows,key=lambda x:-len(x[1])):
    print(f'### `{w}` — {len(c)} ajustes\n')
    if c: print(', '.join(f'`{k}`' for k in c)+'\n')
    for x in e: print(f'- {x}')
    if e: print()
print('## Anexo B · Widgets ausentes y sus ajustes\n')
for w,d in sorted(spec['widgets'].items()):
    if not d.get('__missing_widget'): continue
    c=collapse({k:v for k,v in d['controls'].items() if not k.startswith('_') and not k.startswith('motion_fx') and k not in ('sticky','sticky_on','sticky_offset','sticky_effects_offset','sticky_parent','sticky_anchor_link_offset')})
    print(f"### `{w}` · {d['title']} · {', '.join(d['categories'])}\n")
    print(', '.join(f'`{k}`' for k in list(c)[:200]) + (' …' if len(c)>200 else '') + '\n')
print('## Anexo C · Documentos\n')
for doc,d in spec['documents'].items():
    if d.get('__missing_doc'): print(f'- `{doc}`: tipo de documento ausente ({d["src"]}).'); continue
    c=collapse({k:v for k,v in d.items() if not k.startswith(('hfe','ekit'))})
    if c: print(f'- **`{doc}`** ({len(c)}): ' + ', '.join(f'`{k}`' for k in c))
print()
print('## Anexo D · Elementos (contenedor, sección, columna, común)\n')
for el,d in spec['elements'].items():
    c=collapse({k:v for k,v in d.items()})
    if el.startswith('__') : c=collapse(d) 
    allk=[k for k in d if not k.startswith('__')]
    if allk: print(f'- **`{el}`**: ' + ', '.join(f'`{k}`' for k in collapse({k:d[k] for k in allk}) or allk))
