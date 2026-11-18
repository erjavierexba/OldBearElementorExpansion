#!/usr/bin/env python3
"""Compara inventarios (dev/inventory.php) de OBX y de dos sitios con Elementor Pro y escribe los ajustes ausentes."""
import json,sys,re
import gzip
def load(p): return json.load(gzip.open(p,'rt') if p.endswith('.gz') else open(p))
if len(sys.argv) < 4: sys.exit('Uso: parity-spec.py obx.json pro-a.json[.gz] pro-b.json[.gz] > spec-missing.json')
O=load(sys.argv[1]); W=load(sys.argv[2]); Pf=load(sys.argv[3])
noise=re.compile(r'^(premium_|pa_|ekit_|hfe|glass_|lq_|effects_tabs$|hide_tooltip)')
# Elementor 4 no longer expands responsive controls in PHP (the editor adds the device keys), so a
# device variant of a key OBX has is not a gap when the Pro inventory came from an older Elementor.
device=re.compile(r'^(.+)_(tablet|mobile|mobile_extra|tablet_extra|laptop|widescreen)$')
def asdict(x):
    if isinstance(x,list): return {str(i):v for i,v in enumerate(x)}
    return x or {}
spec={'widgets':{},'elements':{},'documents':{}}
def diff(pc,oc):
    r={}
    for c,d in pc.items():
        if noise.match(c) or d.get('type')=='section': continue
        m=device.match(c)
        if c not in oc and m and m.group(1) in oc: continue
        if c not in oc: r[c]=d
        elif d.get('options') and isinstance(oc[c].get('options'),list):
            mo=[o for o in d['options'] if o not in oc[c]['options']]
            if mo: r[c]={'__missing_options':mo}
        elif d.get('fields'):
            of=asdict(oc[c].get('fields'))
            mf={f:v for f,v in asdict(d['fields']).items() if f not in of}
            if mf: r[c]={'__missing_fields':mf}
    return r
for src in (W,Pf):
    for k,v in src['widgets'].items():
        if v['src']!='pro' or k in spec['widgets']: continue
        if k in O['widgets'] and 'controls' in O['widgets'][k]: spec['widgets'][k]=diff(asdict(v['controls']),asdict(O['widgets'][k]['controls']))
        else: spec['widgets'][k]={'__missing_widget':True,'title':v['title'],'categories':v['categories'],'controls':{c:d for c,d in asdict(v.get('controls')).items() if not noise.match(c)}}
    for k,v in src['elements'].items():
        if k in spec['elements'] or k not in O['elements']: continue
        spec['elements'][k]=diff(v['controls'],O['elements'][k]['controls'])
    for k,v in src['documents'].items():
        if k in spec['documents']: continue
        if k in O['documents'] and v.get('controls'): spec['documents'][k]=diff(v['controls'],O['documents'][k].get('controls',{}))
        elif k not in O['documents']: spec['documents'][k]={'__missing_doc':True,'src':v['src']}
json.dump(spec,sys.stdout,indent=1,ensure_ascii=False)
