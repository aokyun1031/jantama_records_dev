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

      // 動的ページ → そのままプロキシ
      return await proxyToOrigin(request, url, originUrl);
    } catch (err) {
      return new Response("Service Temporarily Unavailable", { status: 502 });
    }
  },
};

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
