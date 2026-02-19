const fs = require('fs');
const vm = require('vm');

function createElement(id) {
  return {
    id,
    innerHTML: '',
    textContent: '',
    className: '',
    value: '',
    dataset: {},
    disabled: false,
    addEventListener() {},
    remove() {},
    insertAdjacentHTML(pos, html) {
      this.innerHTML += html;
    },
    dispatchEvent() {},
    getAttribute() {
      return null;
    },
    setAttribute() {},
  };
}

function run() {
  const listeners = new Map();
  const fetchCalls = [];

  const elements = new Map();
  const ids = [
    'total-items',
    'optimized-items',
    'pending-items',
    'avg-score',
    'top-performers-list',
    'top-performers-period',
    'autopilot-status',
    'recent-activity',
    'results-section',
    'results-content',
    'seoKillerTabs',
  ];

  for (const id of ids) {
    elements.set(id, createElement(id));
  }

  elements.get('top-performers-period').value = '30d';

  const documentMock = {
    addEventListener(type, fn) {
      const list = listeners.get(type) || [];
      list.push(fn);
      listeners.set(type, list);
    },
    querySelectorAll() {
      return [];
    },
    querySelector() {
      return null;
    },
    getElementById(id) {
      return elements.get(id) || null;
    },
    createElement() {
      return createElement('');
    },
    body: {
      appendChild() {},
    },
    head: {
      appendChild() {},
    },
  };

  const bootstrapMock = {
    Toast: class {
      constructor() {}
      show() {}
    },
    Modal: class {
      constructor() {}
      show() {}
    },
    Tab: class {
      constructor() {}
      show() {}
    },
    Tooltip: class {
      constructor() {}
    },
  };

  async function fetchMock(url, options = {}) {
    fetchCalls.push({ url: String(url), method: (options.method || 'GET').toUpperCase() });
    const ok = true;
    const status = 200;

    if (String(url).includes('/api/seo-killer/diagnose')) {
      return {
        ok,
        status,
        statusText: 'OK',
        json: async () => ({ success: true, stats: { total: 10, optimized: 4, pending: 6, avgScore: 72.5 } }),
      };
    }

    if (String(url).includes('/api/seo-killer/top-performers')) {
      return {
        ok,
        status,
        statusText: 'OK',
        json: async () => ({ success: true, items: [] }),
      };
    }

    if (String(url).includes('/api/seo-killer/autopilot/status')) {
      return {
        ok,
        status,
        statusText: 'OK',
        json: async () => ({ enabled: false }),
      };
    }

    if (String(url).includes('/api/seo-killer/autopilot/history')) {
      return {
        ok,
        status,
        statusText: 'OK',
        json: async () => ({ success: true, history: [] }),
      };
    }

    return {
      ok,
      status,
      statusText: 'OK',
      json: async () => ({}),
    };
  }

  const context = {
    console,
    setTimeout,
    clearTimeout,
    Intl,
    URL,
    URLSearchParams,
    window: {
      location: { href: 'http://localhost/dashboard/seo-killer', search: '' },
      history: { replaceState() {} },
      document: documentMock,
    },
    document: documentMock,
    bootstrap: bootstrapMock,
    fetch: fetchMock,
  };

  const code = fs.readFileSync('public/assets/js/seo-killer.js', 'utf8');
  vm.createContext(context);
  vm.runInContext(code, context, { filename: 'seo-killer.js' });

  const domLoaded = listeners.get('DOMContentLoaded') || [];
  if (domLoaded.length !== 1) {
    throw new Error(`Esperado 1 listener DOMContentLoaded; obtido ${domLoaded.length}`);
  }

  return Promise.resolve()
    .then(async () => {
      await domLoaded[0]();
      await new Promise((r) => setTimeout(r, 0));

      const counts = fetchCalls.reduce((acc, c) => {
        acc[c.url] = (acc[c.url] || 0) + 1;
        return acc;
      }, {});

      const mustBeOnce = [
        '/api/seo-killer/diagnose',
        '/api/seo-killer/top-performers',
        '/api/seo-killer/autopilot/status',
        '/api/seo-killer/autopilot/history?limit=5',
      ];

      for (const u of mustBeOnce) {
        const k = Object.keys(counts).find((x) => x.includes(u));
        if (!k) {
          throw new Error(`Chamada esperada não ocorreu: ${u}`);
        }
        if (counts[k] !== 1) {
          throw new Error(`Chamada duplicada detectada: ${k} (x${counts[k]})`);
        }
      }

      console.log('OK: sem chamadas duplicadas no init');
      console.log('Calls:', fetchCalls.map((c) => c.url).join('\n'));
    });
}

run().catch((e) => {
  console.error('FAIL:', e.message);
  process.exitCode = 1;
});

