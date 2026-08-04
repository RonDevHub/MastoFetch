<?php
$configPath = __DIR__ . '/../config/accounts.json';
$widgets = [];
if (file_exists($configPath)) {
    $config = json_decode(file_get_contents($configPath), true);
    $widgets = $config['widgets'] ?? [];
}

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];
$currentDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
$baseUrl = $protocol . $host . $currentDir;
?>
<!DOCTYPE html>
<html lang="de" class="h-full bg-neutral-950">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MastoFetch Dashboard</title>
    <link rel="icon" type="image/png" href="/assets/logo/MastoFetch.png">
    <link rel="stylesheet" href="/assets/style.css">
    <script defer src="/assets/alpine.js"></script>
</head>

<body class="h-full text-neutral-200 antialiased font-sans">

    <div class="min-h-full">
        <!-- Navigation Header -->
        <nav class="border-b border-neutral-800 bg-neutral-900/50 backdrop-blur">
            <div class="mx-auto px-4 sm:px-6 lg:px-8 max-w-7xl">
                <div class="flex h-16 items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="flex items-center justify-center h-10 w-10 overflow-hidden rounded">
                            <img src="assets/logo/MastoFetch.png" alt="MastoFetch Logo" class="h-full w-full object-contain">
                        </div>
                        <span class="text-xl font-bold tracking-tight bg-gradient-to-r from-violet-400 to-indigo-400 bg-clip-text text-transparent">MastoFetch Control Panel</span>
                    </div>
                    <div class="text-sm text-neutral-400 font-mono">
                        Version 1.0.0
                    </div>
                </div>
            </div>
        </nav>

        <!-- Main Content -->
        <main class="py-10">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">

                <div class="md:flex md:items-center md:justify-between mb-8">
                    <div class="min-w-0 flex-1">
                        <h2 class="text-2xl font-bold leading-7 text-white sm:truncate sm:text-3xl tracking-tight">Deine Widgets</h2>
                        <p class="mt-1 text-sm leading-6 text-neutral-400">Verwalte deine Feeds und binde sie ganz einfach auf deinen Webseiten ein.</p>
                    </div>
                </div>

                <?php if (empty($widgets)): ?>
                    <div class="rounded-lg border border-dashed border-neutral-800 p-12 text-center">
                        <span class="mx-auto block text-4xl text-neutral-600">📭</span>
                        <h3 class="mt-2 text-sm font-semibold text-white">Keine Widgets konfiguriert</h3>
                        <p class="mt-1 text-sm text-neutral-400">Füge in deiner <code>config/accounts.json</code> neue Widgets hinzu.</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-10">
                        <?php foreach ($widgets as $id => $w):
                            $embedUrl = $baseUrl . "/widget/" . urlencode($id);
                            $embedCode = '<iframe src="' . $embedUrl . '" width="100%" height="600" style="border-radius: 16px; overflow: hidden; border: none;"></iframe>';
                        ?>
                            <div class="overflow-hidden rounded-xl border border-neutral-800 bg-neutral-900/30 backdrop-blur-sm" x-data="{ copied: false, embedCode: <?php echo htmlspecialchars(json_encode($embedCode), ENT_QUOTES, 'UTF-8'); ?> }">

                                <!-- Card Header -->
                                <div class="border-b border-neutral-800 bg-neutral-900/80 px-6 py-4 sm:flex sm:items-center sm:justify-between">
                                    <div class="sm:flex-auto">
                                        <h3 class="text-base font-semibold leading-6 text-white flex items-center gap-2">
                                            <span class="inline-block h-2.5 w-2.5 rounded-full bg-emerald-500"></span>
                                            <?php echo htmlspecialchars($w['title']); ?>
                                            <span class="text-xs text-neutral-500 font-mono font-normal">(ID: <?php echo htmlspecialchars($id); ?>)</span>
                                        </h3>
                                        <p class="mt-1 text-sm text-neutral-400">
                                            Verknüpfte Accounts:
                                            <span class="font-mono text-xs text-violet-400">
                                                <?php echo implode(', ', $w['accounts']); ?>
                                            </span>
                                        </p>
                                    </div>
                                    <div class="mt-4 sm:ml-16 sm:mt-0 sm:flex-none flex gap-3 text-xs">
                                        <span class="inline-flex items-center rounded-md bg-neutral-800 px-2.5 py-1 font-medium text-neutral-300 ring-1 ring-inset ring-neutral-700">
                                            Theme: <?php echo htmlspecialchars($w['theme'] ?? 'dark'); ?>
                                        </span>
                                        <span class="inline-flex items-center rounded-md bg-neutral-800 px-2.5 py-1 font-medium text-neutral-300 ring-1 ring-inset ring-neutral-700">
                                            Cache: <?php echo htmlspecialchars($w['cache_ttl'] ?? 900); ?>s
                                        </span>
                                        <span class="inline-flex items-center rounded-md bg-neutral-800 px-2.5 py-1 font-medium text-neutral-300 ring-1 ring-inset ring-neutral-700">
                                            Limit: <?php echo htmlspecialchars($w['limit'] ?? 10); ?>
                                        </span>
                                    </div>
                                </div>

                                <div class="p-6 space-y-6">
                                    <!-- Einbettungscode mit Alpine.js Copy-Action -->
                                    <div>
                                        <label class="block text-sm font-medium leading-6 text-neutral-300">HTML-Einbettungscode</label>
                                        <div class="mt-2 relative rounded-md shadow-sm">
                                            <input
                                                type="text"
                                                readonly
                                                :value="embedCode"
                                                class="block w-full rounded-lg border-0 bg-neutral-950 py-3 pl-4 pr-24 font-mono text-sm text-emerald-400 ring-1 ring-inset ring-neutral-800 focus:ring-1 focus:ring-inset focus:ring-violet-500 focus:outline-none">
                                            <div class="absolute inset-y-0 right-0 flex items-center pr-1.5">
                                                <button
                                                    type="button"
                                                    @click="
                                                        navigator.clipboard.writeText(embedCode); 
                                                        copied = true; 
                                                        setTimeout(() => copied = false, 2000)
                                                    "
                                                    class="inline-flex items-center gap-1.5 rounded-md bg-neutral-900 px-3 py-1.5 text-xs font-semibold text-white shadow-sm ring-1 ring-inset ring-neutral-700 hover:bg-neutral-800 transition-all duration-200">
                                                    <span x-show="!copied" class="flex items-center gap-1">
                                                        📋 Kopieren
                                                    </span>
                                                    <span x-show="copied" class="flex items-center gap-1 text-emerald-400" x-cloak>
                                                        ✔ Kopiert!
                                                    </span>
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Live Preview -->
                                    <div>
                                        <label class="block text-sm font-medium leading-6 text-neutral-300 mb-2">Live-Vorschau</label>
                                        <div class="rounded-xl overflow-hidden border border-neutral-800 bg-neutral-950 p-1">
                                            <iframe src="widget.php?id=<?php echo urlencode($id); ?>" class="w-full h-[550px] rounded-lg bg-neutral-950" style="border:none;"></iframe>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

            </div>
        </main>
    </div>

</body>

</html>