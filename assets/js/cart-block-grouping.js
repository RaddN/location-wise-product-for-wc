(function () {
    'use strict';

    const TABLE_SEL = '.wc-block-cart-items';
    const ROW_SEL = 'tbody > tr.wc-block-cart-items__row';
    const LOC_VALUE_SEL = '.wc-block-components-product-details__location .wc-block-components-product-details__value';
    const HEADER_ROW_CLASS = 'mulopimfwc-group-header-row';

    const makeHeaderHTML = (name) => `
    <div class="mulopimfwc-group-header" role="heading" aria-level="3">
      <span class="mulopimfwc-location-icon" aria-hidden="true"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
       <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z" fill="currentColor"></path>
        </svg></span>
      <span class="mulopimfwc-group-name">${name}</span>
    </div>`;

    const fromTitleFallback = (row) => {
        const a = row.querySelector('.wc-block-components-product-name');
        if (!a) return null;
        const m = a.textContent.match(/\[([^\]]+)\]/);
        return m ? m[1].trim() : null;
    };

    const getRowLocationName = (row) => {
        const el = row.querySelector(LOC_VALUE_SEL);
        const name = el?.textContent?.trim();
        if (name) return name;
        return fromTitleFallback(row) || 'Global';
    };

    const slugify = (s) =>
        String(s || '').toLowerCase().replace(/\s+/g, '-').replace(/[^a-z0-9_-]/g, '');

    const groupRows = (rows) => {
        const order = [];
        const bySlug = new Map();
        rows.forEach((row, idx) => {
            const name = getRowLocationName(row);
            const slug = slugify(name);
            if (!bySlug.has(slug)) {
                bySlug.set(slug, { name, slug, rows: [] });
                order.push(slug);
            }
            bySlug.get(slug).rows.push({ row, idx });
        });
        return order.map((slug) => bySlug.get(slug));
    };

    const applyGrouping = (table) => {
        const tbody = table.querySelector('tbody');
        if (!tbody) return;

        // Clean previous headers on re-run
        tbody.querySelectorAll('tr.' + HEADER_ROW_CLASS).forEach((n) => n.remove());

        const rows = Array.from(tbody.querySelectorAll(ROW_SEL));
        if (!rows.length) {
            // Nothing to do (empty cart or not rendered yet)
            return;
        }

        const groups = groupRows(rows);
        const colCount = table.querySelectorAll('thead th').length || 3;

        // Insert headers and re-append rows in grouped order
        let moved = 0;
        groups.forEach((g) => {
            // header row
            const headerTr = document.createElement('tr');
            headerTr.className = HEADER_ROW_CLASS + ' mulopimfwc-loc-' + slugify(g.slug);
            headerTr.innerHTML = `<td colspan="${colCount}">${makeHeaderHTML(g.name)}</td>`;
            tbody.appendChild(headerTr);

            // rows for this group
            g.rows.forEach(({ row }) => {
                row.classList.add('mulopimfwc-loc-' + slugify(g.slug));
                tbody.appendChild(row);
                moved++;
            });
        });

    };

    const run = () => {
        const tables = Array.from(document.querySelectorAll(TABLE_SEL));
        if (!tables.length) return;
        tables.forEach(applyGrouping);
    };

    // ---------- React re-render watcher (MutationObserver) ----------
    const startMutationObserver = () => {
        const tables = Array.from(document.querySelectorAll(TABLE_SEL));
        if (!tables.length) return;

        const mo = new MutationObserver((muts) => {
            if (muts.some((m) => m.type === 'childList')) {
                clearTimeout(startMutationObserver._t);
                startMutationObserver._t = setTimeout(run, 30);
            }
        });
        // Observe the table element — changes to the tbody rows trigger regroup
        tables.forEach((t) => mo.observe(t, { childList: true, subtree: false }));

        run(); // initial
    };

    // ---------- AJAX/fetch listeners to catch cart updates ----------
    // 1) jQuery AJAX (if present; some themes/plugins still use this)
    const armJqueryAjaxComplete = () => {
        if (!window.jQuery) return;
        try {
            jQuery(document).ajaxComplete((evt, xhr, opts) => {
                try {
                    const url = (opts?.url || '').toString();
                    // Any request hitting Woo endpoints usually triggers a re-render
                    if (/wc-ajax|wc\/store|woo|woocommerce|cart|checkout/i.test(url)) {
                        setTimeout(run, 30);
                    }
                } catch (_) { }
            });
        } catch (e) {
            // ignore
        }
    };

    // 2) fetch() monkey patch (Blocks mostly use fetch to /wc/store/v1/cart*)
    const armFetchHook = () => {
        if (!window.fetch || window.fetch._mulopimfwcPatched) return;
        const orig = window.fetch;
        window.fetch = function (...args) {
            const p = orig.apply(this, args);
            try {
                const url = (args && args[0] && args[0].toString) ? args[0].toString() : '';
                // Re-run grouping after any Store API cart-affecting call returns
                if (/\/wc\/store\/v\d+\/cart/i.test(url)) {
                    p.then(() => setTimeout(run, 30)).catch(() => { });
                }
            } catch (_) { }
            return p;
        };
        window.fetch._mulopimfwcPatched = true;
    };

    // 3) Woo Blocks data-store (if present) — subscribe to cart changes
    const armWpDataSubscribe = () => {
        const data = window.wp && wp.data;
        if (!data || !data.subscribe || !data.select) return;

        let lastHash = '';
        try {
            const selectCart = () => {
                // Try both known stores
                const store1 = data.select('wc/store');
                const store2 = data.select('wc/store/cart');
                // Prefer a cart object with items/totals
                return (store1 && store1.getCartData && store1.getCartData()) ||
                    (store2 && store2.getCartData && store2.getCartData()) ||
                    null;
            };

            data.subscribe(() => {
                const cart = selectCart();
                if (!cart) return;
                // crude hash based on item count + totals
                const h = `${(cart.items || []).length}|${JSON.stringify(cart.totals || {})}`;
                if (h !== lastHash) {
                    lastHash = h;
                    setTimeout(run, 30);
                }
            });
        } catch (e) {
            // ignore
        }
    };

    // ---------- boot ----------
    const boot = () => {
        startMutationObserver();
        armJqueryAjaxComplete();
        armFetchHook();
        armWpDataSubscribe();
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
