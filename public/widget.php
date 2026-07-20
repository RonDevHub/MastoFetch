<?php
require_once __DIR__ . '/../src/MastoCache.php';

$configPath = __DIR__ . '/../config/accounts.json';

if (!file_exists($configPath)) {
    exit('Konfiguration fehlt.');
}

$config = json_decode(file_get_contents($configPath), true);

// --- Saubere Pfad-Ermittlung (Routing) ---
$widgetId = '';

// Fall 1: Aufruf über die schöne URL (z.B. /widget/multi_widget)
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (preg_match('#^/widget/([^/]+)$#', $requestUri, $matches)) {
    $widgetId = $matches[1];
}

// Fall 2: Fallback für die direkte URL (z.B. widget.php?id=multi_widget)
if (empty($widgetId) && isset($_GET['id'])) {
    $widgetId = $_GET['id'];
}

// Überprüfung der Widget-ID
if (empty($widgetId) || !isset($config['widgets'][$widgetId])) {
    exit('Widget nicht gefunden.');
}

$widget = $config['widgets'][$widgetId];
$theme = $widget['theme'] ?? 'dark';

// Ermittle alle erlaubten/konfigurierten Usernames für dieses Widget
$allowedUsernames = [];
if (isset($widget['accounts'])) {
    foreach ($widget['accounts'] as $accId) {
        if (isset($config['accounts'][$accId]['username'])) {
            $allowedUsernames[] = strtolower($config['accounts'][$accId]['username']);
        }
    }
}

$mastoCache = new MastoCache();
$isExpired = $mastoCache->isCacheExpiredForWidget($widget, $config['accounts']);
$feedData = [];

// Laden der Daten initial, sofern der Cache noch gültig ist
if (!$isExpired) {
    $feedData = $mastoCache->getWidgetData($widget, $config['accounts'], false);
}
?>
<!DOCTYPE html>
<html lang="de" class="h-full overflow-hidden" data-theme="<?php echo $theme; ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($widget['title']); ?></title>
    <link rel="icon" type="image/png" href="/assets/logo/MastoFetch.png">
    <link rel="stylesheet" href="/assets/widget-themes.css">
    <link rel="stylesheet" href="/assets/widget-tailwind.css">
</head>

<body class="h-screen flex flex-col overflow-hidden font-sans antialiased p-4">

    <!-- Fester Header (Nicht scrollbar) -->
    <div class="flex-none flex items-center justify-between pb-4 mb-4 border-b border-[var(--border-color)]">
        <a href="#" class="text-lg font-bold tracking-tight text-[var(--accent)] flex items-center gap-1.5">
            <span><img src="/assets/logo/MastoFetch.png" alt="PixelFetch Logo" class="gallery-logo"></span> <?php echo htmlspecialchars($widget['title']); ?>
        </a>
        <div class="flex -space-x-2 overflow-hidden" id="header-avatars">
            <?php if (!$isExpired):
                $shown = [];
                foreach ($feedData as $item):
                    $origUsername = strtolower($item['account']['username']);
                    // Zeige den Avatar nur, wenn er zu unseren eigenen Instanz-Accounts gehört und noch nicht ausgegeben wurde
                    if (in_array($origUsername, $allowedUsernames) && !in_array($origUsername, $shown)):
                        $shown[] = $origUsername; ?>
                        <a href="<?php echo htmlspecialchars($item['account']['url']); ?>" target="_blank" onclick="event.stopPropagation();" title="@<?php echo htmlspecialchars($item['account']['username']); ?>">
                            <img class="inline-block h-8 w-8 rounded-full ring-2 ring-[var(--card-bg)] object-cover" src="/proxy.php?file=<?php echo urlencode($item['account']['avatar']); ?>" alt="">
                        </a>
            <?php endif;
                endforeach;
            endif; ?>
        </div>
    </div>

    <!-- Scrollbarer Feed-Bereich -->
    <div class="flex-1 overflow-y-auto pr-1" id="scroll-container">
        <div class="uniform-grid align-stretch" id="content-grid">
            <?php if ($isExpired): ?>
                <!-- Platzhalter während des allerersten Ladens -->
                <div class="col-span-full py-20 text-center" id="widget-loader">
                    <div class="shimmer h-32 rounded-xl w-full"></div>
                </div>
            <?php else: ?>
                <?php foreach ($feedData as $item): ?>
                    <div onclick="window.open('<?php echo htmlspecialchars($item['url']); ?>', '_blank')" class="clickable-card flex flex-col justify-between bg-[var(--card-bg)] border border-[var(--border-color)] rounded-xl p-4 shadow-sm hover:shadow-md transition-shadow duration-200 h-full">
                        <div class="space-y-3">
                            <!-- Reblog / Boost Header -->
                            <?php if (!empty($item['reblogged_by'])): ?>
                                <div class="flex items-center gap-1.5 text-xs text-[var(--text-muted)] border-b border-[var(--border-color)] pb-2 mb-1" onclick="event.stopPropagation();">
                                    <svg class="fill-current w-3.5 h-3.5 text-[var(--accent)]" viewBox="0 0 512 512" aria-hidden="true">
                                        <path d="M57 288l103.5 0c35.3 0 64 28.7 64 64l0 103.5c0 24.9 27.1 40.2 48.5 27.4L361.2 430c14.5-8.7 23.3-24.3 23.3-41.2l0-95C513.4 217.8 519.9 104.3 508.8 28.4 506.9 15.6 496.9 5.6 484.1 3.7 408.2-7.4 294.7-.9 218.6 128l-95 0c-16.9 0-32.5 8.8-41.2 23.3L29.6 239.5C16.8 260.9 32.1 288 57 288zM384.5 80a48 48 0 1 1 0 96 48 48 0 1 1 0-96zM152.9 473.6c31.5-31.5 31.5-82.5 0-114s-82.5-31.5-114 0c-31.3 31.3-37.5 92-38.3 126.4-.4 14.6 11.2 26.2 25.9 25.9 34.5-.8 95.1-7 126.4-38.3zm-40.6-32c-10.1 10.1-28.5 13-41.3 13.7-8 .5-14.3-5.9-13.9-13.9 .7-12.8 3.7-31.2 13.7-41.3 11.4-11.4 30-11.4 41.4 0s11.4 30 0 41.4z" />
                                    </svg>
                                    <a href="<?php echo htmlspecialchars($item['reblogged_by']['url']); ?>" target="_blank" class="hover:underline font-semibold text-[var(--accent)]">
                                        <?php echo htmlspecialchars($item['reblogged_by']['display_name']); ?>
                                    </a>
                                    <span>hat geteilt</span>
                                </div>
                            <?php endif; ?>

                            <!-- Author Header -->
                            <div class="flex items-center gap-3" onclick="event.stopPropagation();">
                                <a href="<?php echo htmlspecialchars($item['account']['url']); ?>" target="_blank" class="flex-none">
                                    <img class="h-9 w-9 rounded-full object-cover ring-1 ring-[var(--border-color)]" src="/proxy.php?file=<?php echo urlencode($item['account']['avatar']); ?>">
                                </a>
                                <a class="flex flex-col text-sm no-underline hover:underline text-[var(--text-color)]" href="<?php echo htmlspecialchars($item['account']['url']); ?>" target="_blank">
                                    <span class="font-semibold leading-none"><?php echo htmlspecialchars($item['account']['display_name']); ?></span>
                                    <span class="text-xs text-[var(--text-muted)] mt-0.5">@<?php echo htmlspecialchars($item['account']['username']); ?></span>
                                </a>
                            </div>

                            <!-- Content -->
                            <div class="masto-content text-sm leading-relaxed text-[var(--text-color)] break-words">
                                <?php echo $item['content']; ?>
                            </div>

                            <!-- Media Attachments -->
                            <?php foreach ($item['media_attachments'] as $media): ?>
                                <div class="overflow-hidden rounded-lg border border-[var(--border-color)] max-h-60" onclick="event.stopPropagation();">
                                    <img class="w-full object-cover" src="/proxy.php?file=<?php echo urlencode($media['url']); ?>">
                                </div>
                            <?php endforeach; ?>

                            <!-- Link Preview (Open Graph) -->
                            <?php if (!empty($item['link_preview'])): $lp = $item['link_preview']; ?>
                                <a href="<?php echo htmlspecialchars($lp['url']); ?>" target="_blank" class="block overflow-hidden rounded-lg border border-[var(--border-color)] bg-[var(--bg-color)] hover:border-[var(--accent)] transition-colors duration-200 no-underline" onclick="event.stopPropagation();">
                                    <?php if (!empty($lp['image'])): ?>
                                        <img class="w-full h-36 object-cover" src="/proxy.php?file=<?php echo urlencode($lp['image']); ?>">
                                    <?php endif; ?>
                                    <div class="p-3 space-y-1">
                                        <h4 class="text-xs font-semibold text-[var(--text-color)] line-clamp-1"><?php echo $lp['title']; ?></h4>
                                        <?php if (!empty($lp['description'])): ?>
                                            <p class="text-[11px] text-[var(--text-muted)] line-clamp-2 leading-snug"><?php echo $lp['description']; ?></p>
                                        <?php endif; ?>
                                    </div>
                                </a>
                            <?php endif; ?>

                            <!-- Interaktionsleiste -->
                            <div class="flex gap-4 text-xs text-[var(--text-muted)] pt-1">
                                <span class="flex items-center gap-1" title="Favoriten">
                                    <svg class="fill-current w-3.5 h-3.5" viewBox="0 0 512 512" aria-hidden="true">
                                        <path d="M241 87.1l15 20.7 15-20.7C296 52.5 336.2 32 378.9 32 452.4 32 512 91.6 512 165.1l0 2.6c0 112.2-139.9 242.5-212.9 298.2-12.4 9.4-27.6 14.1-43.1 14.1s-30.8-4.6-43.1-14.1C139.9 410.2 0 279.9 0 167.7l0-2.6C0 91.6 59.6 32 133.1 32 175.8 32 216 52.5 241 87.1z" />
                                    </svg>
                                    <span><?php echo $item['favourites_count']; ?></span>
                                </span>
                                <span class="flex items-center gap-1" title="Reblogs">
                                    <svg class="fill-current w-3.5 h-3.5" viewBox="0 0 512 512" aria-hidden="true">
                                        <path d="M57 288l103.5 0c35.3 0 64 28.7 64 64l0 103.5c0 24.9 27.1 40.2 48.5 27.4L361.2 430c14.5-8.7 23.3-24.3 23.3-41.2l0-95C513.4 217.8 519.9 104.3 508.8 28.4 506.9 15.6 496.9 5.6 484.1 3.7 408.2-7.4 294.7-.9 218.6 128l-95 0c-16.9 0-32.5 8.8-41.2 23.3L29.6 239.5C16.8 260.9 32.1 288 57 288zM384.5 80a48 48 0 1 1 0 96 48 48 0 1 1 0-96zM152.9 473.6c31.5-31.5 31.5-82.5 0-114s-82.5-31.5-114 0c-31.3 31.3-37.5 92-38.3 126.4-.4 14.6 11.2 26.2 25.9 25.9 34.5-.8 95.1-7 126.4-38.3zm-40.6-32c-10.1 10.1-28.5 13-41.3 13.7-8 .5-14.3-5.9-13.9-13.9 .7-12.8 3.7-31.2 13.7-41.3 11.4-11.4 30-11.4 41.4 0s11.4 30 0 41.4z" />
                                    </svg>
                                    <span><?php echo $item['reblogs_count']; ?></span>
                                </span>
                                <span class="flex items-center gap-1" title="Antworten">
                                    <svg class="fill-current w-3.5 h-3.5" viewBox="0 0 512 512" aria-hidden="true">
                                        <path d="M256 480c141.4 0 256-107.5 256-240S397.4 0 256 0 0 107.5 0 240c0 54.3 19.2 104.3 51.6 144.5L2.8 476.8c-4.8 9-3.3 20 3.6 27.5s17.8 9.8 27.1 5.8l118.4-50.7C183.7 472.6 218.9 480 256 480zM152 176l208 0c13.3 0 24 10.7 24 24s-10.7 24-24 24l-208 0c-13.3 0-24-10.7-24-24s10.7-24 24-24zm0 96l112 0c13.3 0 24 10.7 24 24s-10.7 24-24 24l-112 0c-13.3 0-24-10.7-24-24s10.7-24 24-24z" />
                                    </svg>
                                    <span><?php echo $item['replies_count']; ?></span>
                                </span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Fester Footer (Nicht scrollbar) -->
    <div class="flex-none text-center mt-4 pt-4 border-t border-[var(--border-color)] text-xs text-[var(--text-muted)]">
        Powered by <a href="https://codeberg.org/RonDevHub/MastoFetch" class="hover:text-[var(--accent)] font-semibold transition-colors" target="_blank" onclick="event.stopPropagation();">MastoFetch</a> |
        <a href="https://rondev.de/donate" class="inline-flex items-center gap-1 hover:text-[var(--accent)] transition-colors align-middle" target="_blank" onclick="event.stopPropagation();">
            <svg class="heart fill-current" height="14" width="14" viewBox="0 0 540 540" aria-hidden="true">
                <path d="M308.2 488.2L494.4 302c29.2-29.2 45.6-68.9 45.6-110.2 0-86.1-69.8-155.8-155.8-155.8-41.3 0-81 16.4-110.2 45.6-2.2 2.2-5.8 2.2-8 0-29.2-29.2-68.9-45.6-110.2-45.6-86.1 0-155.8 69.8-155.8 155.8 0 41.3 16.4 81 45.6 110.2L231.8 488.2c21.1 21.1 55.3 21.1 76.4 0zM54 191.8c0 7.5-6 13.5-13.5 13.5S27 199.3 27 191.8c0-71.1 57.7-128.8 128.8-128.8 7.5 0 13.5 6 13.5 13.5S163.3 90 155.8 90C99.6 90 54 135.6 54 191.8zm258.2-72c-11.6 11.6-26.9 17.5-42.2 17.5-7.5 0-13.5-6-13.5-13.5s6-13.5 13.5-13.5c8.4 0 16.7-3.2 23.1-9.6 24.2-24.2 56.9-37.7 91.1-37.7 7.5 0 13.5 6 13.5 13.5S391.6 90 384.2 90c-27 0-52.9 10.7-72 29.8z" />
            </svg>
            <span>Donate</span>
        </a>
    </div>

    <script>
        const allowedUsernames = <?php echo json_encode($allowedUsernames); ?>;

        function forceExternalLinks() {
            document.querySelectorAll('.masto-content a').forEach(link => {
                link.setAttribute('target', '_blank');
                link.setAttribute('rel', 'noopener noreferrer');
                link.addEventListener('click', function(e) {
                    e.stopPropagation();
                });
            });
        }

        function fetchFeeds() {
            fetch('/api.php?widget=<?php echo urlencode($widgetId); ?>')
                .then(res => res.json())
                .then(res => {
                    if (res.success && res.data.length > 0) {
                        const grid = document.getElementById('content-grid');
                        const avatarContainer = document.getElementById('header-avatars');
                        grid.innerHTML = '';
                        avatarContainer.innerHTML = '';

                        let uniqueAvatars = [];

                        res.data.forEach(item => {
                            const origUsername = item.account.username.toLowerCase();

                            if (allowedUsernames.includes(origUsername) && !uniqueAvatars.includes(origUsername)) {
                                uniqueAvatars.push(origUsername);
                                avatarContainer.innerHTML += `
                                    <a href="${item.account.url}" target="_blank" onclick="event.stopPropagation();" title="@${item.account.username}">
                                        <img class="inline-block h-8 w-8 rounded-full ring-2 ring-[var(--card-bg)] object-cover" src="/proxy.php?file=${encodeURIComponent(item.account.avatar)}">
                                    </a>`;
                            }

                            let mediaHtml = '';
                            item.media_attachments.forEach(m => {
                                mediaHtml += `<div class="mt-3 overflow-hidden rounded-lg border border-[var(--border-color)] max-h-60" onclick="event.stopPropagation();"><img class="w-full object-cover" src="/proxy.php?file=${encodeURIComponent(m.url)}"></div>`;
                            });

                            let previewHtml = '';
                            if (item.link_preview) {
                                let lpImg = item.link_preview.image ? `<img class="w-full h-36 object-cover" src="/proxy.php?file=${encodeURIComponent(item.link_preview.image)}">` : '';
                                let lpDesc = item.link_preview.description ? `<p class="text-[11px] text-[var(--text-muted)] line-clamp-2 leading-snug">${item.link_preview.description}</p>` : '';

                                previewHtml = `
                                    <a href="${item.link_preview.url}" target="_blank" class="block overflow-hidden rounded-lg border border-[var(--border-color)] bg-[var(--bg-color)] hover:border-[var(--accent)] transition-colors duration-200 no-underline" onclick="event.stopPropagation();">
                                        ${lpImg}
                                        <div class="p-3 space-y-1">
                                            <h4 class="text-xs font-semibold text-[var(--text-color)] line-clamp-1">${item.link_preview.title}</h4>
                                            ${lpDesc}
                                        </div>
                                    </a>
                                `;
                            }

                            let reblogHtml = '';
                            if (item.reblogged_by) {
                                reblogHtml = `
                                    <div class="flex items-center gap-1.5 text-xs text-[var(--text-muted)] border-b border-[var(--border-color)] pb-2 mb-1" onclick="event.stopPropagation();">
                                        <svg class="fill-current w-3.5 h-3.5 text-[var(--accent)]" viewBox="0 0 512 512" aria-hidden="true">
                                            <path d="M57 288l103.5 0c35.3 0 64 28.7 64 64l0 103.5c0 24.9 27.1 40.2 48.5 27.4L361.2 430c14.5-8.7 23.3-24.3 23.3-41.2l0-95C513.4 217.8 519.9 104.3 508.8 28.4 506.9 15.6 496.9 5.6 484.1 3.7 408.2-7.4 294.7-.9 218.6 128l-95 0c-16.9 0-32.5 8.8-41.2 23.3L29.6 239.5C16.8 260.9 32.1 288 57 288zM384.5 80a48 48 0 1 1 0 96 48 48 0 1 1 0-96zM152.9 473.6c31.5-31.5 31.5-82.5 0-114s-82.5-31.5-114 0c-31.3 31.3-37.5 92-38.3 126.4-.4 14.6 11.2 26.2 25.9 25.9 34.5-.8 95.1-7 126.4-38.3zm-40.6-32c-10.1 10.1-28.5 13-41.3 13.7-8 .5-14.3-5.9-13.9-13.9 .7-12.8 3.7-31.2 13.7-41.3 11.4-11.4 30-11.4 41.4 0s11.4 30 0 41.4z"/>
                                        </svg>
                                        <a href="${item.reblogged_by.url}" target="_blank" class="hover:underline font-semibold text-[var(--accent)]">
                                            ${item.reblogged_by.display_name}
                                        </a>
                                        <span>hat geteilt</span>
                                    </div>
                                `;
                            }

                            grid.innerHTML += `
                                <div onclick="window.open('${item.url}', '_blank')" class="clickable-card flex flex-col justify-between bg-[var(--card-bg)] border border-[var(--border-color)] rounded-xl p-4 shadow-sm hover:shadow-md transition-shadow duration-200 h-full">
                                    <div class="space-y-3">
                                        ${reblogHtml}
                                        <div class="flex items-center gap-3" onclick="event.stopPropagation();">
                                            <a href="${item.account.url}" target="_blank" class="flex-none">
                                                <img class="h-9 w-9 rounded-full object-cover ring-1 ring-[var(--border-color)]" src="/proxy.php?file=${encodeURIComponent(item.account.avatar)}">
                                            </a>
                                            <a class="flex flex-col text-sm no-underline hover:underline text-[var(--text-color)]" href="${item.account.url}" target="_blank">
                                                <span class="font-semibold leading-none">${item.account.display_name}</span>
                                                <span class="text-xs text-[var(--text-muted)] mt-0.5">@${item.account.username}</span>
                                            </a>
                                        </div>
                                        <div class="masto-content text-sm leading-relaxed text-[var(--text-color)] break-words">
                                            ${item.content}
                                        </div>
                                        ${mediaHtml}
                                        ${previewHtml}
                                        <div class="flex gap-4 text-xs text-[var(--text-muted)] pt-1">
                                            <span class="flex items-center gap-1" title="Favoriten">
                                                <svg class="fill-current w-3.5 h-3.5" viewBox="0 0 512 512" aria-hidden="true">
                                                    <path d="M241 87.1l15 20.7 15-20.7C296 52.5 336.2 32 378.9 32 452.4 32 512 91.6 512 165.1l0 2.6c0 112.2-139.9 242.5-212.9 298.2-12.4 9.4-27.6 14.1-43.1 14.1s-30.8-4.6-43.1-14.1C139.9 410.2 0 279.9 0 167.7l0-2.6C0 91.6 59.6 32 133.1 32 175.8 32 216 52.5 241 87.1z"/>
                                                </svg>
                                                <span>${item.favourites_count}</span>
                                            </span>
                                            <span class="flex items-center gap-1" title="Reblogs">
                                                <svg class="fill-current w-3.5 h-3.5" viewBox="0 0 512 512" aria-hidden="true">
                                                    <path d="M57 288l103.5 0c35.3 0 64 28.7 64 64l0 103.5c0 24.9 27.1 40.2 48.5 27.4L361.2 430c14.5-8.7 23.3-24.3 23.3-41.2l0-95C513.4 217.8 519.9 104.3 508.8 28.4 506.9 15.6 496.9 5.6 484.1 3.7 408.2-7.4 294.7-.9 218.6 128l-95 0c-16.9 0-32.5 8.8-41.2 23.3L29.6 239.5C16.8 260.9 32.1 288 57 288zM384.5 80a48 48 0 1 1 0 96 48 48 0 1 1 0-96zM152.9 473.6c31.5-31.5 31.5-82.5 0-114s-82.5-31.5-114 0c-31.3 31.3-37.5 92-38.3 126.4-.4 14.6 11.2 26.2 25.9 25.9 34.5-.8 95.1-7 126.4-38.3zm-40.6-32c-10.1 10.1-28.5 13-41.3 13.7-8 .5-14.3-5.9-13.9-13.9 .7-12.8 3.7-31.2 13.7-41.3 11.4-11.4 30-11.4 41.4 0s11.4 30 0 41.4z"/>
                                                </svg>
                                                <span>${item.reblogs_count}</span>
                                            </span>
                                            <span class="flex items-center gap-1" title="Antworten">
                                                <svg class="fill-current w-3.5 h-3.5" viewBox="0 0 512 512" aria-hidden="true">
                                                    <path d="M256 480c141.4 0 256-107.5 256-240S397.4 0 256 0 0 107.5 0 240c0 54.3 19.2 104.3 51.6 144.5L2.8 476.8c-4.8 9-3.3 20 3.6 27.5s17.8 9.8 27.1 5.8l118.4-50.7C183.7 472.6 218.9 480 256 480zM152 176l208 0c13.3 0 24 10.7 24 24s-10.7 24-24 24l-208 0c-13.3 0-24-10.7-24-24s10.7-24 24-24zm0 96l112 0c13.3 0 24 10.7 24 24s-10.7 24-24 24l-112 0c-13.3 0-24-10.7-24-24s10.7-24 24-24z"/>
                                                </svg>
                                                <span>${item.replies_count}</span>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            `;
                        });

                        forceExternalLinks();
                    }
                })
                .catch(err => console.error("Fehler beim Live-Update des Feeds:", err));
        }

        document.addEventListener('DOMContentLoaded', () => {
            forceExternalLinks();

            <?php if ($isExpired): ?>
                fetchFeeds();
            <?php endif; ?>

            setInterval(fetchFeeds, 60000);
        });
    </script>
</body>

</html>