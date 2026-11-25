import assert from 'node:assert/strict';
import vm from 'node:vm';
import { readFileSync } from 'node:fs';
const code = readFileSync(new URL('../assets/release-template/tracking.js', import.meta.url), 'utf8');
function runtime({ dryRun=false, failure=false, gtm=false, existing=false }={}) {
  const scripts=[], ids=new Set(existing?['finprov-gtm']:[]), store=new Map();
  const w={ FINPROV_SITE_CONFIG:{tagMode:gtm?'gtm':'direct',googleTagManagerId:'GTM-TEST123',googleAnalyticsId:'G-TEST123',googleAdsId:'AW-123456789',googleAdsConversionLabel:'test_label',apiEndpoint:'./api/lead.php'} };
  const c={window:w,document:{referrer:'',getElementById:id=>ids.has(id),createElement:()=>({}),head:{appendChild:s=>{scripts.push(s);if(s.id)ids.add(s.id);}},addEventListener:()=>{}},location:{search:'',href:'https://example.invalid/campaign/'},sessionStorage:{getItem:k=>store.get(k)||null,setItem:(k,v)=>store.set(k,v)},URLSearchParams,Map,Set,AbortController,setTimeout,clearTimeout,fetch:async()=>({ok:!failure,json:async()=>failure?{ok:false}:{ok:true,dryRun}})};
  vm.runInNewContext(code,c);return {w,scripts};
}
const payload={name:'Local mock',phone:'9000000000',course:'course-test',formLocation:'hero'};
const leads=w=>w.dataLayer.filter(x=>x.event==='generate_lead');
const ads=w=>w.dataLayer.filter(x=>x[0]==='event'&&x[1]==='conversion');
{
 const {w}=runtime();await w.FinprovIntegrations.captureLead(payload);await w.FinprovIntegrations.captureLead(payload);
 assert.equal(leads(w).length,1);assert.equal(ads(w).length,1);
}
{
 const {w}=runtime({dryRun:true});await w.FinprovIntegrations.captureLead(payload);
 assert.equal(leads(w).length,0);assert.equal(ads(w).length,0);assert.equal(w.dataLayer.filter(x=>x.event==='lead_capture_dry_run').length,1);
}
{
 const {w}=runtime({failure:true});await assert.rejects(w.FinprovIntegrations.captureLead(payload));assert.equal(leads(w).length,0);assert.equal(ads(w).length,0);
}
for(const existing of [false,true]) {
 const {w,scripts}=runtime({gtm:true,existing});assert.equal(scripts.filter(s=>s.src.includes('/gtm.js')).length,existing?0:1);assert.equal(w.dataLayer.filter(x=>x.event==='gtm.js').length,existing?0:1);
}
console.log('PASS: mock-only live success, duplicate suppression, dry-run conversion suppression, failure suppression, and GTM single bootstrap');
