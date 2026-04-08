const STATIC_EXT = /\.(css|js|png|jpg|jpeg|webp|svg|woff2|ico)(\?.*)?$/i;
const ALLOWED_METHODS = new Set(["GET", "HEAD", "POST"]);

export default {
  async fetch(request, env, ctx) {
    // 許可されていないHTTPメソッドを拒否
    if (!ALLOWED_METHODS.has(request.method)) {
      return new Response("Method Not Allowed", { status: 405 });
    }

    const url = new URL(request.url);
    const originUrl = new URL(url.pathname + url.search, env.ORIGIN);

    try {
      // 静的アセット → Cache API でキャッシュ
      if (request.method === "GET" && STATIC_EXT.test(url.pathname)) {
        return await handleStatic(request, originUrl, ctx);
      }

      // トップページ → オリジンダウン時にKVフォールバック
      if (request.method === "GET" && (url.pathname === "/" || url.pathname === "/index")) {
        return await handleTopPage(request, url, originUrl, env);
      }

      // 動的ページ → そのままプロキシ
      return await proxyToOrigin(request, url, originUrl);
    } catch (err) {
      // トップページならKVフォールバックを試行
      if (request.method === "GET" && (url.pathname === "/" || url.pathname === "/index")) {
        const cached = await env.TOP_PAGE_CACHE.get("index");
        if (cached) {
          return new Response(cached, {
            headers: { "Content-Type": "text/html;charset=UTF-8", "X-Cache-Fallback": "true" },
          });
        }
      }
      return new Response("Service Temporarily Unavailable", { status: 502 });
    }
  },

  // 5分ごとにオリジンをピング＋トップページをKVにキャッシュ
  async scheduled(event, env, ctx) {
    try {
      const res = await fetch(env.ORIGIN + "/", {
        headers: { Accept: "text/html" },
      });
      if (res.ok) {
        const html = await res.text();
        await env.TOP_PAGE_CACHE.put("index", html, { expirationTtl: 3600 });
      }
    } catch (e) {
      // オリジン到達不可 — キャッシュは上書きしない
    }
  },
};

async function handleTopPage(request, workerUrl, originUrl, env) {
  const response = await proxyToOrigin(request, workerUrl, originUrl);

  // オリジンが正常ならそのまま返す
  if (response.status < 500) {
    return response;
  }

  // オリジンエラー → KVフォールバック
  const cached = await env.TOP_PAGE_CACHE.get("index");
  if (cached) {
    return new Response(cached, {
      headers: { "Content-Type": "text/html;charset=UTF-8", "X-Cache-Fallback": "true" },
    });
  }

  return response;
}

async function handleStatic(request, originUrl, ctx) {
  const cache = caches.default;
  const cacheKey = new Request(request.url, request);

  // キャッシュヒットならそのまま返す
  const cached = await cache.match(cacheKey);
  if (cached) {
    return cached;
  }

  // オリジンから取得
  const response = await fetch(originUrl.toString(), {
    headers: request.headers,
  });

  if (!response.ok) {
    return response;
  }

  // エッジキャッシュ用に s-maxage を追加
  const newResponse = new Response(response.body, response);
  newResponse.headers.set(
    "Cache-Control",
    "public, max-age=31536000, s-maxage=31536000, immutable"
  );

  ctx.waitUntil(cache.put(cacheKey, newResponse.clone()));
  return newResponse;
}

async function proxyToOrigin(request, workerUrl, originUrl) {
  const headers = new Headers(request.headers);
  headers.set("Host", originUrl.host);
  headers.set("X-Forwarded-Host", workerUrl.host);
  headers.set("X-Forwarded-Proto", "https");

  const init = {
    method: request.method,
    headers,
    redirect: "manual",
  };

  if (request.method === "POST") {
    init.body = request.body;
  }

  const response = await fetch(originUrl.toString(), init);
  const newResponse = new Response(response.body, response);

  // リダイレクトのLocationヘッダーを書き換え（オリジンURLを隠す）
  const location = newResponse.headers.get("Location");
  if (location && location.includes(originUrl.origin)) {
    newResponse.headers.set(
      "Location",
      location.replace(originUrl.origin, workerUrl.origin)
    );
  }

  // 動的ページはキャッシュしない
  newResponse.headers.set("Cache-Control", "no-store");

  return newResponse;
}
