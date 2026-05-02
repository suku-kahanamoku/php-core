<?php

declare(strict_types=1);

$base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
http_response_code(200);
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="cs">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>php-core</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-slate-100 min-h-screen font-sans text-slate-800 flex flex-col">

    <header class="bg-slate-900 text-white">
        <div class="flex items-center gap-3 px-8 py-4">
            <span class="font-extrabold text-lg tracking-tight">&#9889; php-core</span>
            <span class="text-slate-600 text-sm select-none">&mdash;</span>
            <span class="text-slate-300 text-sm">PHP 8.1+ &middot; MySQL &middot; Bearer token auth &middot; REST API</span>
        </div>
    </header>

    <div class="px-8 py-6 max-w-5xl mx-auto w-full flex-1">

        <h2 class="text-xs font-bold uppercase tracking-widest text-slate-400 mb-3">&#128196; Dokumentace &amp; vizualizace</h2>
        <div class="grid grid-cols-[repeat(auto-fill,minmax(210px,1fr))] gap-3 mb-8">

            <a href="<?php echo $base; ?>/pages/flow-login.html"
                class="bg-white border-[1.5px] border-slate-200 rounded-xl p-5 no-underline text-slate-800 flex flex-col gap-2
              hover:border-blue-500 hover:shadow-lg transition-all duration-150 group">
                <span class="text-2xl leading-none">&#128273;</span>
                <span class="text-sm font-bold text-slate-900">Login flow</span>
                <span class="text-xs text-slate-500 leading-relaxed">Sekven&#269;n&#237; diagram p&#345;ihl&#225;&#353;en&#237; a z&#237;sk&#225;n&#237; Bearer tokenu</span>
                <span class="text-xs font-bold px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 w-fit">flow diagram</span>
            </a>

            <a href="<?php echo $base; ?>/pages/flow-order-cancel.html"
                class="bg-white border-[1.5px] border-slate-200 rounded-xl p-5 no-underline text-slate-800 flex flex-col gap-2
              hover:border-blue-500 hover:shadow-lg transition-all duration-150 group">
                <span class="text-2xl leading-none">&#128230;</span>
                <span class="text-sm font-bold text-slate-900">Storno objedn&#225;vky</span>
                <span class="text-xs text-slate-500 leading-relaxed">Krok za krokem: auth &rarr; router &rarr; controller &rarr; SQL &rarr; response</span>
                <span class="text-xs font-bold px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 w-fit">flow diagram</span>
            </a>

            <a href="<?php echo $base; ?>/pages/flow-models.html"
                class="bg-white border-[1.5px] border-slate-200 rounded-xl p-5 no-underline text-slate-800 flex flex-col gap-2
              hover:border-blue-500 hover:shadow-lg transition-all duration-150 group">
                <span class="text-2xl leading-none">&#128218;</span>
                <span class="text-sm font-bold text-slate-900">Model flows</span>
                <span class="text-xs text-slate-500 leading-relaxed">Hlavn&#237; operace v&#353;ech model&#367;: User, Product, Order, Invoice&hellip;</span>
                <span class="text-xs font-bold px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 w-fit">flow diagram</span>
            </a>

            <a href="<?php echo $base; ?>/pages/db-schema.html"
                class="bg-white border-[1.5px] border-slate-200 rounded-xl p-5 no-underline text-slate-800 flex flex-col gap-2
              hover:border-blue-500 hover:shadow-lg transition-all duration-150 group">
                <span class="text-2xl leading-none">&#128194;</span>
                <span class="text-sm font-bold text-slate-900">ER diagram</span>
                <span class="text-xs text-slate-500 leading-relaxed">Mermaid entity-relationship diagram, export SVG</span>
                <span class="text-xs font-bold px-2 py-0.5 rounded-full bg-violet-100 text-violet-700 w-fit">datab&#225;ze</span>
            </a>

            <a href="<?php echo $base; ?>/pages/db-table.html"
                class="bg-white border-[1.5px] border-slate-200 rounded-xl p-5 no-underline text-slate-800 flex flex-col gap-2
              hover:border-blue-500 hover:shadow-lg transition-all duration-150 group">
                <span class="text-2xl leading-none">&#128203;</span>
                <span class="text-sm font-bold text-slate-900">DB tabulky</span>
                <span class="text-xs text-slate-500 leading-relaxed">V&#353;echny tabulky, sloupce, typy a FK vazby</span>
                <span class="text-xs font-bold px-2 py-0.5 rounded-full bg-violet-100 text-violet-700 w-fit">datab&#225;ze</span>
            </a>

            <a href="<?php echo $base; ?>/api"
                class="bg-white border-[1.5px] border-slate-200 rounded-xl p-5 no-underline text-slate-800 flex flex-col gap-2
              hover:border-blue-500 hover:shadow-lg transition-all duration-150 group">
                <span class="text-2xl leading-none">&#128279;</span>
                <span class="text-sm font-bold text-slate-900">REST API</span>
                <span class="text-xs text-slate-500 leading-relaxed">JSON API &ndash; v&#353;echna vol&#225;n&#237; pod <code class="font-mono bg-slate-100 px-1 rounded">/api/</code></span>
                <span class="text-xs font-bold px-2 py-0.5 rounded-full bg-amber-100 text-amber-700 w-fit">api</span>
            </a>

        </div>

        <h2 class="text-xs font-bold uppercase tracking-widest text-slate-400 mb-3">
            &#128268; API endpointy
            <span class="ml-2 text-slate-400 font-normal normal-case tracking-normal">
                Base URL: <code class="font-mono bg-slate-200 px-1.5 py-0.5 rounded text-slate-600"><?php echo $base; ?>/api</code>
            </span>
        </h2>

        <div class="flex flex-col gap-2">

            <div class="bg-white border border-slate-200 rounded-xl overflow-hidden shadow-sm">
                <div class="px-4 py-2 text-xs font-bold uppercase tracking-wider text-white bg-slate-700">Auth</div>
                <div class="flex items-center gap-3 px-4 py-1.5 border-b border-slate-50 text-xs"><span class="font-bold px-2 py-0.5 rounded bg-blue-100 text-blue-700 min-w-[52px] text-center font-mono">POST</span><span class="text-slate-600 font-mono">/auth/login</span><span class="ml-auto text-slate-400">&rarr; Bearer token</span></div>
                <div class="flex items-center gap-3 px-4 py-1.5 border-b border-slate-50 text-xs"><span class="font-bold px-2 py-0.5 rounded bg-blue-100 text-blue-700 min-w-[52px] text-center font-mono">POST</span><span class="text-slate-600 font-mono">/auth/logout</span></div>
                <div class="flex items-center gap-3 px-4 py-1.5 border-b border-slate-50 text-xs"><span class="font-bold px-2 py-0.5 rounded bg-emerald-100 text-emerald-700 min-w-[52px] text-center font-mono">GET</span><span class="text-slate-600 font-mono">/auth/me</span><span class="ml-auto text-slate-400">&#128274;</span></div>
                <div class="flex items-center gap-3 px-4 py-1.5 border-b border-slate-50 text-xs"><span class="font-bold px-2 py-0.5 rounded bg-blue-100 text-blue-700 min-w-[52px] text-center font-mono">POST</span><span class="text-slate-600 font-mono">/auth/register</span></div>
                <div class="flex items-center gap-3 px-4 py-1.5 text-xs"><span class="font-bold px-2 py-0.5 rounded bg-blue-100 text-blue-700 min-w-[52px] text-center font-mono">POST</span><span class="text-slate-600 font-mono">/auth/change-password</span><span class="ml-auto text-slate-400">&#128274;</span></div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">

                <div class="bg-white border border-slate-200 rounded-xl overflow-hidden shadow-sm">
                    <div class="px-4 py-2 text-xs font-bold uppercase tracking-wider text-white bg-slate-700">Users</div>
                    <div class="flex items-center gap-3 px-4 py-1.5 border-b border-slate-50 text-xs"><span class="font-bold px-2 py-0.5 rounded bg-emerald-100 text-emerald-700 min-w-[52px] text-center font-mono">GET</span><span class="text-slate-600 font-mono">/users</span><span class="ml-auto text-slate-400">&#128274; admin</span></div>
                    <div class="flex items-center gap-3 px-4 py-1.5 border-b border-slate-50 text-xs"><span class="font-bold px-2 py-0.5 rounded bg-blue-100 text-blue-700 min-w-[52px] text-center font-mono">POST</span><span class="text-slate-600 font-mono">/users</span><span class="ml-auto text-slate-400">&#128274; admin</span></div>
                    <div class="flex items-center gap-3 px-4 py-1.5 border-b border-slate-50 text-xs"><span class="font-bold px-2 py-0.5 rounded bg-emerald-100 text-emerald-700 min-w-[52px] text-center font-mono">GET</span><span class="text-slate-600 font-mono">/users/:id</span><span class="ml-auto text-slate-400">&#128274;</span></div>
                    <div class="flex items-center gap-3 px-4 py-1.5 border-b border-slate-50 text-xs"><span class="font-bold px-2 py-0.5 rounded bg-amber-100 text-amber-700 min-w-[52px] text-center font-mono">PUT</span><span class="text-slate-600 font-mono">/users/:id</span><span class="ml-auto text-slate-400">&#128274;</span></div>
                    <div class="flex items-center gap-3 px-4 py-1.5 text-xs"><span class="font-bold px-2 py-0.5 rounded bg-red-100 text-red-700 min-w-[52px] text-center font-mono">DEL</span><span class="text-slate-600 font-mono">/users/:id</span><span class="ml-auto text-slate-400">&#128274; admin</span></div>
                </div>

                <div class="bg-white border border-slate-200 rounded-xl overflow-hidden shadow-sm">
                    <div class="px-4 py-2 text-xs font-bold uppercase tracking-wider text-white bg-slate-700">Products</div>
                    <div class="flex items-center gap-3 px-4 py-1.5 border-b border-slate-50 text-xs"><span class="font-bold px-2 py-0.5 rounded bg-emerald-100 text-emerald-700 min-w-[52px] text-center font-mono">GET</span><span class="text-slate-600 font-mono">/products</span><span class="ml-auto text-slate-400">&#128274;</span></div>
                    <div class="flex items-center gap-3 px-4 py-1.5 border-b border-slate-50 text-xs"><span class="font-bold px-2 py-0.5 rounded bg-blue-100 text-blue-700 min-w-[52px] text-center font-mono">POST</span><span class="text-slate-600 font-mono">/products</span><span class="ml-auto text-slate-400">&#128274; admin</span></div>
                    <div class="flex items-center gap-3 px-4 py-1.5 border-b border-slate-50 text-xs"><span class="font-bold px-2 py-0.5 rounded bg-emerald-100 text-emerald-700 min-w-[52px] text-center font-mono">GET</span><span class="text-slate-600 font-mono">/products/:id</span><span class="ml-auto text-slate-400">&#128274;</span></div>
                    <div class="flex items-center gap-3 px-4 py-1.5 border-b border-slate-50 text-xs"><span class="font-bold px-2 py-0.5 rounded bg-amber-100 text-amber-700 min-w-[52px] text-center font-mono">PUT</span><span class="text-slate-600 font-mono">/products/:id</span><span class="ml-auto text-slate-400">&#128274; admin</span></div>
                    <div class="flex items-center gap-3 px-4 py-1.5 border-b border-slate-50 text-xs"><span class="font-bold px-2 py-0.5 rounded bg-red-100 text-red-700 min-w-[52px] text-center font-mono">DEL</span><span class="text-slate-600 font-mono">/products/:id</span><span class="ml-auto text-slate-400">&#128274; admin</span></div>
                    <div class="flex items-center gap-3 px-4 py-1.5 text-xs"><span class="font-bold px-2 py-0.5 rounded bg-violet-100 text-violet-700 min-w-[52px] text-center font-mono">PATCH</span><span class="text-slate-600 font-mono">/products/:id/stock</span><span class="ml-auto text-slate-400">&#128274; admin</span></div>
                </div>

                <div class="bg-white border border-slate-200 rounded-xl overflow-hidden shadow-sm">
                    <div class="px-4 py-2 text-xs font-bold uppercase tracking-wider text-white bg-slate-700">Categories</div>
                    <div class="flex items-center gap-3 px-4 py-1.5 border-b border-slate-50 text-xs"><span class="font-bold px-2 py-0.5 rounded bg-emerald-100 text-emerald-700 min-w-[52px] text-center font-mono">GET</span><span class="text-slate-600 font-mono">/categories</span><span class="ml-auto text-slate-400">&#128274;</span></div>
                    <div class="flex items-center gap-3 px-4 py-1.5 border-b border-slate-50 text-xs"><span class="font-bold px-2 py-0.5 rounded bg-blue-100 text-blue-700 min-w-[52px] text-center font-mono">POST</span><span class="text-slate-600 font-mono">/categories</span><span class="ml-auto text-slate-400">&#128274; admin</span></div>
                    <div class="flex items-center gap-3 px-4 py-1.5 border-b border-slate-50 text-xs"><span class="font-bold px-2 py-0.5 rounded bg-emerald-100 text-emerald-700 min-w-[52px] text-center font-mono">GET</span><span class="text-slate-600 font-mono">/categories/:id</span><span class="ml-auto text-slate-400">&#128274;</span></div>
                    <div class="flex items-center gap-3 px-4 py-1.5 border-b border-slate-50 text-xs"><span class="font-bold px-2 py-0.5 rounded bg-amber-100 text-amber-700 min-w-[52px] text-center font-mono">PUT</span><span class="text-slate-600 font-mono">/categories/:id</span><span class="ml-auto text-slate-400">&#128274; admin</span></div>
                    <div class="flex items-center gap-3 px-4 py-1.5 text-xs"><span class="font-bold px-2 py-0.5 rounded bg-red-100 text-red-700 min-w-[52px] text-center font-mono">DEL</span><span class="text-slate-600 font-mono">/categories/:id</span><span class="ml-auto text-slate-400">&#128274; admin</span></div>
                </div>

                <div class="bg-white border border-slate-200 rounded-xl overflow-hidden shadow-sm">
                    <div class="px-4 py-2 text-xs font-bold uppercase tracking-wider text-white bg-slate-700">Orders</div>
                    <div class="flex items-center gap-3 px-4 py-1.5 border-b border-slate-50 text-xs"><span class="font-bold px-2 py-0.5 rounded bg-emerald-100 text-emerald-700 min-w-[52px] text-center font-mono">GET</span><span class="text-slate-600 font-mono">/orders</span><span class="ml-auto text-slate-400">&#128274;</span></div>
                    <div class="flex items-center gap-3 px-4 py-1.5 border-b border-slate-50 text-xs"><span class="font-bold px-2 py-0.5 rounded bg-blue-100 text-blue-700 min-w-[52px] text-center font-mono">POST</span><span class="text-slate-600 font-mono">/orders</span><span class="ml-auto text-slate-400">&#128274;</span></div>
                    <div class="flex items-center gap-3 px-4 py-1.5 border-b border-slate-50 text-xs"><span class="font-bold px-2 py-0.5 rounded bg-emerald-100 text-emerald-700 min-w-[52px] text-center font-mono">GET</span><span class="text-slate-600 font-mono">/orders/:id</span><span class="ml-auto text-slate-400">&#128274;</span></div>
                    <div class="flex items-center gap-3 px-4 py-1.5 border-b border-slate-50 text-xs"><span class="font-bold px-2 py-0.5 rounded bg-violet-100 text-violet-700 min-w-[52px] text-center font-mono">PATCH</span><span class="text-slate-600 font-mono">/orders/:id/status</span><span class="ml-auto text-slate-400">&#128274; admin</span></div>
                    <div class="flex items-center gap-3 px-4 py-1.5 text-xs"><span class="font-bold px-2 py-0.5 rounded bg-red-100 text-red-700 min-w-[52px] text-center font-mono">DEL</span><span class="text-slate-600 font-mono">/orders/:id</span><span class="ml-auto text-slate-400">&#128274; admin</span></div>
                </div>

                <div class="bg-white border border-slate-200 rounded-xl overflow-hidden shadow-sm">
                    <div class="px-4 py-2 text-xs font-bold uppercase tracking-wider text-white bg-slate-700">Invoices</div>
                    <div class="flex items-center gap-3 px-4 py-1.5 border-b border-slate-50 text-xs"><span class="font-bold px-2 py-0.5 rounded bg-emerald-100 text-emerald-700 min-w-[52px] text-center font-mono">GET</span><span class="text-slate-600 font-mono">/invoices</span><span class="ml-auto text-slate-400">&#128274;</span></div>
                    <div class="flex items-center gap-3 px-4 py-1.5 border-b border-slate-50 text-xs"><span class="font-bold px-2 py-0.5 rounded bg-blue-100 text-blue-700 min-w-[52px] text-center font-mono">POST</span><span class="text-slate-600 font-mono">/invoices</span><span class="ml-auto text-slate-400">&#128274; admin</span></div>
                    <div class="flex items-center gap-3 px-4 py-1.5 border-b border-slate-50 text-xs"><span class="font-bold px-2 py-0.5 rounded bg-emerald-100 text-emerald-700 min-w-[52px] text-center font-mono">GET</span><span class="text-slate-600 font-mono">/invoices/:id</span><span class="ml-auto text-slate-400">&#128274;</span></div>
                    <div class="flex items-center gap-3 px-4 py-1.5 border-b border-slate-50 text-xs"><span class="font-bold px-2 py-0.5 rounded bg-violet-100 text-violet-700 min-w-[52px] text-center font-mono">PATCH</span><span class="text-slate-600 font-mono">/invoices/:id/status</span><span class="ml-auto text-slate-400">&#128274; admin</span></div>
                    <div class="flex items-center gap-3 px-4 py-1.5 text-xs"><span class="font-bold px-2 py-0.5 rounded bg-red-100 text-red-700 min-w-[52px] text-center font-mono">DEL</span><span class="text-slate-600 font-mono">/invoices/:id</span><span class="ml-auto text-slate-400">&#128274; admin</span></div>
                </div>

                <div class="bg-white border border-slate-200 rounded-xl overflow-hidden shadow-sm">
                    <div class="px-4 py-2 text-xs font-bold uppercase tracking-wider text-white bg-slate-700">Texts &amp; Enumerations</div>
                    <div class="flex items-center gap-3 px-4 py-1.5 border-b border-slate-50 text-xs"><span class="font-bold px-2 py-0.5 rounded bg-emerald-100 text-emerald-700 min-w-[52px] text-center font-mono">GET</span><span class="text-slate-600 font-mono">/texts</span><span class="ml-auto text-slate-400">&#128274;</span></div>
                    <div class="flex items-center gap-3 px-4 py-1.5 border-b border-slate-50 text-xs"><span class="font-bold px-2 py-0.5 rounded bg-emerald-100 text-emerald-700 min-w-[52px] text-center font-mono">GET</span><span class="text-slate-600 font-mono">/texts/by-key/:key</span><span class="ml-auto text-slate-400">&#128274;</span></div>
                    <div class="flex items-center gap-3 px-4 py-1.5 border-b border-slate-50 text-xs"><span class="font-bold px-2 py-0.5 rounded bg-emerald-100 text-emerald-700 min-w-[52px] text-center font-mono">GET</span><span class="text-slate-600 font-mono">/enumerations</span><span class="ml-auto text-slate-400">&#128274;</span></div>
                    <div class="flex items-center gap-3 px-4 py-1.5 border-b border-slate-50 text-xs"><span class="font-bold px-2 py-0.5 rounded bg-emerald-100 text-emerald-700 min-w-[52px] text-center font-mono">GET</span><span class="text-slate-600 font-mono">/enumerations/types</span><span class="ml-auto text-slate-400">&#128274;</span></div>
                    <div class="flex items-center gap-3 px-4 py-1.5 text-xs"><span class="font-bold px-2 py-0.5 rounded bg-emerald-100 text-emerald-700 min-w-[52px] text-center font-mono">GET</span><span class="text-slate-600 font-mono">/addresses/:id</span><span class="ml-auto text-slate-400">&#128274;</span></div>
                </div>

            </div>
        </div>

    </div>

    <footer class="border-t border-slate-200 bg-white px-8 py-3 flex flex-wrap gap-4 text-xs text-slate-400 mt-auto">
        <span>&#128274; = vy&#382;aduje <code class="font-mono bg-slate-100 px-1.5 py-0.5 rounded text-slate-600">Authorization: Bearer &lt;token&gt;</code></span>
        <span>&#128274; admin = vy&#382;aduje roli admin</span>
        <span>Token z&#237;sk&#225;&#353; p&#345;es <code class="font-mono bg-slate-100 px-1.5 py-0.5 rounded text-slate-600">POST /api/auth/login</code></span>
    </footer>

</body>

</html>