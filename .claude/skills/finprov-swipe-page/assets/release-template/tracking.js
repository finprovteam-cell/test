(function () {
  'use strict';

  const config = window.FINPROV_SITE_CONFIG || {};
  const dataLayer = window.dataLayer = window.dataLayer || [];
  const matches = (value, pattern) => typeof value === 'string' && pattern.test(value);
  const tagMode = config.tagMode === 'gtm' ? 'gtm' : 'direct';
  const pendingLeads = new Map();
  const completedLeads = new Set();

  function loadScript(src, id) {
    if (id && document.getElementById(id)) return;
    const script = document.createElement('script');
    script.async = true;
    script.src = src;
    if (id) script.id = id;
    document.head.appendChild(script);
  }

  function initializeTags() {
    const gtmId = matches(config.googleTagManagerId, /^GTM-[A-Z0-9]+$/i) ? config.googleTagManagerId : '';
    if (tagMode === 'gtm') {
      if (gtmId && !document.getElementById('finprov-gtm')) {
        dataLayer.push({ 'gtm.start': Date.now(), event: 'gtm.js' });
        loadScript('https://www.googletagmanager.com/gtm.js?id=' + encodeURIComponent(gtmId), 'finprov-gtm');
      }
      return;
    }

    const gaId = matches(config.googleAnalyticsId, /^G-[A-Z0-9]+$/i) ? config.googleAnalyticsId : '';
    const adsId = matches(config.googleAdsId, /^AW-[0-9]+$/i) ? config.googleAdsId : '';
    const gtagId = gaId || adsId;
    if (gtagId) {
      loadScript('https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(gtagId), 'finprov-gtag');
      window.gtag = window.gtag || function () { dataLayer.push(arguments); };
      window.gtag('js', new Date());
      if (gaId) window.gtag('config', gaId);
      if (adsId) window.gtag('config', adsId);
    }

    if (matches(config.clarityProjectId, /^[a-z0-9]+$/i)) {
      window.clarity = window.clarity || function () {
        (window.clarity.q = window.clarity.q || []).push(arguments);
      };
      loadScript('https://www.clarity.ms/tag/' + encodeURIComponent(config.clarityProjectId), 'finprov-clarity');
    }
  }

  const attributionKey = 'finprov:first-touch';

  function readAttribution() {
    try {
      return JSON.parse(sessionStorage.getItem(attributionKey) || '{}');
    } catch (_) {
      return {};
    }
  }

  function captureAttribution() {
    const existing = readAttribution();
    if (Object.keys(existing).length) return existing;

    const params = new URLSearchParams(location.search);
    const value = {
      source: params.get('utm_source') || '',
      medium: params.get('utm_medium') || '',
      campaign: params.get('utm_campaign') || '',
      content: params.get('utm_content') || '',
      term: params.get('utm_term') || '',
      gclid: params.get('gclid') || '',
      gbraid: params.get('gbraid') || '',
      wbraid: params.get('wbraid') || '',
      landingPage: location.href,
      referrer: document.referrer || ''
    };
    try {
      sessionStorage.setItem(attributionKey, JSON.stringify(value));
    } catch (_) {}
    return value;
  }

  function track(event, parameters) {
    dataLayer.push(Object.assign({ event: event }, parameters || {}));
    if (tagMode === 'direct' && typeof window.gtag === 'function') {
      window.gtag('event', event, parameters || {});
    }
  }

  function leadKey(payload) {
    const phone = String(payload.phone || '').replace(/\D/g, '');
    return [phone, payload.course || '', payload.formLocation || ''].join('|');
  }

  async function submitLead(payload, key) {
    track('lead_form_submit', {
      course: payload.course,
      form_location: payload.formLocation
    });

    const controller = typeof AbortController === 'function' ? new AbortController() : null;
    const timeout = controller ? setTimeout(function () { controller.abort(); }, 12000) : null;

    try {
      const response = await fetch(config.apiEndpoint || './api/lead.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify(Object.assign({}, payload, { attribution: captureAttribution() })),
        signal: controller ? controller.signal : undefined,
        credentials: 'same-origin'
      });
      const result = await response.json().catch(function () { return {}; });
      if (!response.ok || result.ok !== true) {
        throw new Error(result.error || 'Lead capture failed');
      }

      completedLeads.add(key);
      track(result.dryRun === true ? 'lead_capture_dry_run' : 'generate_lead', {
        course: payload.course,
        form_location: payload.formLocation,
        dry_run: result.dryRun === true
      });

      if (
        result.dryRun !== true &&
        tagMode === 'direct' &&
        matches(config.googleAdsId, /^AW-[0-9]+$/i) &&
        matches(config.googleAdsConversionLabel, /^[A-Za-z0-9_-]+$/) &&
        typeof window.gtag === 'function'
      ) {
        window.gtag('event', 'conversion', {
          send_to: config.googleAdsId + '/' + config.googleAdsConversionLabel
        });
      }
      return result;
    } catch (error) {
      const message = error && error.name === 'AbortError'
        ? 'Lead request timed out'
        : String(error && error.message || error);
      track('lead_capture_error', {
        course: payload.course,
        form_location: payload.formLocation,
        message: message
      });
      throw new Error(message);
    } finally {
      if (timeout) clearTimeout(timeout);
    }
  }

  function captureLead(payload) {
    const key = leadKey(payload || {});
    if (completedLeads.has(key)) {
      return Promise.resolve({ ok: true, duplicateSuppressed: true });
    }
    if (pendingLeads.has(key)) return pendingLeads.get(key);

    const request = submitLead(payload || {}, key).finally(function () {
      pendingLeads.delete(key);
    });
    pendingLeads.set(key, request);
    return request;
  }

  document.addEventListener('click', function (event) {
    const link = event.target && event.target.closest ? event.target.closest('a[href^="tel:"]') : null;
    if (!link) return;
    track('phone_click', { location: link.dataset.location || 'page' });
  });

  captureAttribution();
  initializeTags();
  window.FinprovIntegrations = {
    captureLead: captureLead,
    getAttribution: readAttribution,
    track: track,
    tagMode: tagMode
  };
}());
