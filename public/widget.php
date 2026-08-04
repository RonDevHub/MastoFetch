<?php
require_once __DIR__ . '/../src/MastoCache.php';

$configPath = __DIR__ . '/../config/accounts.json';

if (!file_exists($configPath)) {
    exit('Konfiguration fehlt.');
}

$config = json_decode(file_get_contents($configPath), true);

// --- Saubere Pfad-Ermittlung (Routing) ---
$widgetId = '';

// Aufruf über die schöne URL (z.B. /widget/multi_widget)
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (preg_match('#^/widget/([^/]+)$#', $requestUri, $matches)) {
    $widgetId = $matches[1];
}

// Fallback für die direkte URL (z.B. widget.php?id=multi_widget)
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

function getVisibilityIcon(string $visibility): array
{
    return match ($visibility) {
        'public' => [
            'label' => 'Öffentlich',
            'svg'   => '<svg class="w-3.5 h-3.5 fill-current" viewBox="0 0 512 512"><path d="M256 464C141.1 464 48 370.9 48 256S141.1 48 256 48c3.5 0 6.9 .1 10.3 .3L232.5 73.6c-5.4 4-8.5 10.4-8.5 17.1l0 9.1c0 6.8 5.5 12.3 12.3 12.3 2.4 0 4.8-.7 6.8-2.1l41.8-27.9c2-1.3 4.4-2.1 6.8-2.1l1 0c6.2 0 11.3 5.1 11.3 11.3 0 3-1.2 5.9-3.3 8l-19.9 19.9c-5.8 5.8-12.9 10.2-20.7 12.8l-26.5 8.8c-5.8 1.9-9.6 7.3-9.6 13.4 0 3.7-1.5 7.3-4.1 10l-17.9 17.9c-6.4 6.4-9.9 15-9.9 24l0 4.3c0 16.4 13.6 29.7 29.9 29.7 11 0 21.2-6.2 26.1-16l4-8.1c2.4-4.8 7.4-7.9 12.8-7.9 4.5 0 8.7 2.1 11.4 5.7l16.3 21.7c2.1 2.9 5.5 4.5 9.1 4.5 8.4 0 13.9-8.9 10.1-16.4l-1.1-2.3c-3.5-7 0-15.5 7.5-18l21.2-7.1c7.6-2.5 12.7-9.6 12.7-17.6 0-10.3 8.3-18.6 18.6-18.6l29.4 0c8.8 0 16 7.2 16 16s-7.2 16-16 16l-20.7 0c-7.2 0-14.2 2.9-19.3 8l-4.7 4.7c-2.1 2.1-3.3 5-3.3 8 0 6.2 5.1 11.3 11.3 11.3l11.3 0c6 0 11.8 2.4 16 6.6l6.5 6.5c1.8 1.8 2.8 4.3 2.8 6.8s-1 5-2.8 6.8l-7.5 7.5C386 262 384 266.9 384 272s2 10 5.7 13.7L408 304c10.2 10.2 24.1 16 38.6 16l7.3 0c-4.1 12.6-9.3 24.7-15.6 36.1-3.7-2.6-8.2-4.1-13-4.1-6 0-11.8-2.4-16-6.6L396 332c-7.7-7.7-18-12-28.9-12-9.7 0-19.2-3.5-26.6-9.8L314 287.4c-11.6-9.9-26.4-15.4-41.6-15.4l-20.9 0c-12.6 0-25 3.7-35.5 10.7L188.5 301c-17.8 11.9-28.5 31.9-28.5 53.3l0 3.2c0 17 6.7 33.3 18.7 45.3l16 16c8.5 8.5 20 13.3 32 13.3l21.3 0c13.3 0 24 10.7 24 24 0 2.5 .4 5 1.1 7.3-5.7 .5-11.4 .7-17.1 .7zm0 48a256 256 0 1 0 0-512 256 256 0 1 0 0 512zM187.3 123.3c6.2-6.2 6.2-16.4 0-22.6s-16.4-6.2-22.6 0l-32 32c-6.2 6.2-6.2 16.4 0 22.6s16.4 6.2 22.6 0l32-32z"/></svg>'
        ],
        'unlisted' => [
            'label' => 'Nicht gelistet (Öffentlich still)',
            'svg'   => '<svg class="w-3.5 h-3.5 fill-current" viewBox="0 0 512 512"><path d="M239.3 48.7c-107.1 8.5-191.3 98.1-191.3 207.3 0 114.9 93.1 208 208 208 33.3 0 64.7-7.8 92.6-21.7-103.4-23.4-180.6-115.8-180.6-226.3 0-65.8 27.4-125.1 71.3-167.3zM0 256c0-141.4 114.6-256 256-256 19.4 0 38.4 2.2 56.7 6.3 9.9 2.2 17.3 10.5 18.5 20.5s-4 19.8-13.1 24.4c-60.6 30.2-102.1 92.7-102.1 164.8 0 101.6 82.4 184 184 184 5 0 9.9-.2 14.8-.6 10.1-.8 19.6 4.8 23.8 14.1s2 20.1-5.3 27.1C387.3 484.8 324.8 512 256 512 114.6 512 0 397.4 0 256z"/></svg>'
        ],
        default => [
            'label' => 'Öffentlich',
            'svg'   => '<svg class="w-3.5 h-3.5 fill-current" viewBox="0 0 512 512"><path d="M256 464C141.1 464 48 370.9 48 256S141.1 48 256 48c3.5 0 6.9 .1 10.3 .3L232.5 73.6c-5.4 4-8.5 10.4-8.5 17.1l0 9.1c0 6.8 5.5 12.3 12.3 12.3 2.4 0 4.8-.7 6.8-2.1l41.8-27.9c2-1.3 4.4-2.1 6.8-2.1l1 0c6.2 0 11.3 5.1 11.3 11.3 0 3-1.2 5.9-3.3 8l-19.9 19.9c-5.8 5.8-12.9 10.2-20.7 12.8l-26.5 8.8c-5.8 1.9-9.6 7.3-9.6 13.4 0 3.7-1.5 7.3-4.1 10l-17.9 17.9c-6.4 6.4-9.9 15-9.9 24l0 4.3c0 16.4 13.6 29.7 29.9 29.7 11 0 21.2-6.2 26.1-16l4-8.1c2.4-4.8 7.4-7.9 12.8-7.9 4.5 0 8.7 2.1 11.4 5.7l16.3 21.7c2.1 2.9 5.5 4.5 9.1 4.5 8.4 0 13.9-8.9 10.1-16.4l-1.1-2.3c-3.5-7 0-15.5 7.5-18l21.2-7.1c7.6-2.5 12.7-9.6 12.7-17.6 0-10.3 8.3-18.6 18.6-18.6l29.4 0c8.8 0 16 7.2 16 16s-7.2 16-16 16l-20.7 0c-7.2 0-14.2 2.9-19.3 8l-4.7 4.7c-2.1 2.1-3.3 5-3.3 8 0 6.2 5.1 11.3 11.3 11.3l11.3 0c6 0 11.8 2.4 16 6.6l6.5 6.5c1.8 1.8 2.8 4.3 2.8 6.8s-1 5-2.8 6.8l-7.5 7.5C386 262 384 266.9 384 272s2 10 5.7 13.7L408 304c10.2 10.2 24.1 16 38.6 16l7.3 0c-4.1 12.6-9.3 24.7-15.6 36.1-3.7-2.6-8.2-4.1-13-4.1-6 0-11.8-2.4-16-6.6L396 332c-7.7-7.7-18-12-28.9-12-9.7 0-19.2-3.5-26.6-9.8L314 287.4c-11.6-9.9-26.4-15.4-41.6-15.4l-20.9 0c-12.6 0-25 3.7-35.5 10.7L188.5 301c-17.8 11.9-28.5 31.9-28.5 53.3l0 3.2c0 17 6.7 33.3 18.7 45.3l16 16c8.5 8.5 20 13.3 32 13.3l21.3 0c13.3 0 24 10.7 24 24 0 2.5 .4 5 1.1 7.3-5.7 .5-11.4 .7-17.1 .7zm0 48a256 256 0 1 0 0-512 256 256 0 1 0 0 512zM187.3 123.3c6.2-6.2 6.2-16.4 0-22.6s-16.4-6.2-22.6 0l-32 32c-6.2 6.2-6.2 16.4 0 22.6s16.4 6.2 22.6 0l32-32z"/></svg>'
        ]
    };
}

function formatMastoTime(string $isoDate): array
{
    $timestamp = strtotime($isoDate);
    $diff = time() - $timestamp;

    if ($diff < 0) {
        $diff = 0;
    }

    if ($diff < 60) {
        $relative = $diff . ' Sek.';
    } elseif ($diff < 3600) {
        $relative = floor($diff / 60) . ' Min.';
    } elseif ($diff < 86400) {
        $relative = floor($diff / 3600) . ' Std.';
    } elseif ($diff < 604800) { // Unter 7 Tage
        $relative = floor($diff / 86400) . ' T.';
    } else {
        // Älter als 7 Tage -> z. B. "22. Jul."
        $months = ['Jan', 'Feb', 'Mär', 'Apr', 'Mai', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dez'];
        $monthName = $months[(int)date('n', $timestamp) - 1];
        $relative = date('j', $timestamp) . '. ' . $monthName;
    }

    $fullTooltip = date('d.m.Y, H:i', $timestamp) . ' Uhr';

    return [
        'relative' => $relative,
        'full'     => $fullTooltip
    ];
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
    <link rel="stylesheet" href="/assets/style.css">
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

                            <?php
                            $timeData = formatMastoTime($item['created_at']);
                            $visData  = getVisibilityIcon($item['visibility'] ?? 'public');
                            ?>

                            <!-- Author Header -->
                            <div class="flex items-center justify-between gap-3" onclick="event.stopPropagation();">
                                <div class="flex items-center gap-3">
                                    <a href="<?php echo htmlspecialchars($item['account']['url']); ?>" target="_blank" class="flex-none">
                                        <img class="h-9 w-9 rounded-full object-cover ring-1 ring-[var(--border-color)]" src="/proxy.php?file=<?php echo urlencode($item['account']['avatar']); ?>">
                                    </a>
                                    <a class="flex flex-col text-sm no-underline hover:underline text-[var(--text-color)]" href="<?php echo htmlspecialchars($item['account']['url']); ?>" target="_blank">
                                        <span class="font-semibold leading-none"><?php echo htmlspecialchars($item['account']['display_name']); ?></span>
                                        <span class="text-xs text-[var(--text-muted)] mt-0.5">@<?php echo htmlspecialchars($item['account']['username']); ?></span>
                                    </a>
                                </div>

                                <!-- Rechtsbündiger Bereich für Sichtbarkeits-Icon & Zeitstempel -->
                                <div class="flex items-center gap-1.5 text-xs text-[var(--text-muted)] flex-none">

                                    <!-- Zeitstempel mit Tooltip -->
                                    <a href="<?php echo htmlspecialchars($item['url']); ?>"
                                        target="_blank"
                                        title="<?php echo htmlspecialchars($timeData['full']); ?>"
                                        class="hover:underline text-[var(--text-muted)]">
                                        <?php echo htmlspecialchars($timeData['relative']); ?>
                                    </a>
                                    <!-- Sichtbarkeits-Icon mit Tooltip -->
                                    <span title="<?php echo htmlspecialchars($visData['label']); ?>" class="inline-flex items-center opacity-70 hover:opacity-100 cursor-help">
                                        <?php echo $visData['svg']; ?>
                                    </span>
                                </div>
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

        function escapeHtml(str) {
            if (!str) return '';
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function getVisibilityIconJs(visibility) {
            switch (visibility) {
                case 'unlisted':
                    return {
                        label: 'Nicht gelistet (Öffentlich still)',
                            svg: '<svg class="w-3.5 h-3.5 fill-current" viewBox="0 0 512 512"><path d="M239.3 48.7c-107.1 8.5-191.3 98.1-191.3 207.3 0 114.9 93.1 208 208 208 33.3 0 64.7-7.8 92.6-21.7-103.4-23.4-180.6-115.8-180.6-226.3 0-65.8 27.4-125.1 71.3-167.3zM0 256c0-141.4 114.6-256 256-256 19.4 0 38.4 2.2 56.7 6.3 9.9 2.2 17.3 10.5 18.5 20.5s-4 19.8-13.1 24.4c-60.6 30.2-102.1 92.7-102.1 164.8 0 101.6 82.4 184 184 184 5 0 9.9-.2 14.8-.6 10.1-.8 19.6 4.8 23.8 14.1s2 20.1-5.3 27.1C387.3 484.8 324.8 512 256 512 114.6 512 0 397.4 0 256z"/></svg>'
                    };
                case 'public':
                default:
                    return {
                        label: 'Öffentlich',
                            svg: '<svg class="w-3.5 h-3.5 fill-current" viewBox="0 0 512 512"><path d="M256 464C141.1 464 48 370.9 48 256S141.1 48 256 48c3.5 0 6.9 .1 10.3 .3L232.5 73.6c-5.4 4-8.5 10.4-8.5 17.1l0 9.1c0 6.8 5.5 12.3 12.3 12.3 2.4 0 4.8-.7 6.8-2.1l41.8-27.9c2-1.3 4.4-2.1 6.8-2.1l1 0c6.2 0 11.3 5.1 11.3 11.3 0 3-1.2 5.9-3.3 8l-19.9 19.9c-5.8 5.8-12.9 10.2-20.7 12.8l-26.5 8.8c-5.8 1.9-9.6 7.3-9.6 13.4 0 3.7-1.5 7.3-4.1 10l-17.9 17.9c-6.4 6.4-9.9 15-9.9 24l0 4.3c0 16.4 13.6 29.7 29.9 29.7 11 0 21.2-6.2 26.1-16l4-8.1c2.4-4.8 7.4-7.9 12.8-7.9 4.5 0 8.7 2.1 11.4 5.7l16.3 21.7c2.1 2.9 5.5 4.5 9.1 4.5 8.4 0 13.9-8.9 10.1-16.4l-1.1-2.3c-3.5-7 0-15.5 7.5-18l21.2-7.1c7.6-2.5 12.7-9.6 12.7-17.6 0-10.3 8.3-18.6 18.6-18.6l29.4 0c8.8 0 16 7.2 16 16s-7.2 16-16 16l-20.7 0c-7.2 0-14.2 2.9-19.3 8l-4.7 4.7c-2.1 2.1-3.3 5-3.3 8 0 6.2 5.1 11.3 11.3 11.3l11.3 0c6 0 11.8 2.4 16 6.6l6.5 6.5c1.8 1.8 2.8 4.3 2.8 6.8s-1 5-2.8 6.8l-7.5 7.5C386 262 384 266.9 384 272s2 10 5.7 13.7L408 304c10.2 10.2 24.1 16 38.6 16l7.3 0c-4.1 12.6-9.3 24.7-15.6 36.1-3.7-2.6-8.2-4.1-13-4.1-6 0-11.8-2.4-16-6.6L396 332c-7.7-7.7-18-12-28.9-12-9.7 0-19.2-3.5-26.6-9.8L314 287.4c-11.6-9.9-26.4-15.4-41.6-15.4l-20.9 0c-12.6 0-25 3.7-35.5 10.7L188.5 301c-17.8 11.9-28.5 31.9-28.5 53.3l0 3.2c0 17 6.7 33.3 18.7 45.3l16 16c8.5 8.5 20 13.3 32 13.3l21.3 0c13.3 0 24 10.7 24 24 0 2.5 .4 5 1.1 7.3-5.7 .5-11.4 .7-17.1 .7zm0 48a256 256 0 1 0 0-512 256 256 0 1 0 0 512zM187.3 123.3c6.2-6.2 6.2-16.4 0-22.6s-16.4-6.2-22.6 0l-32 32c-6.2 6.2-6.2 16.4 0 22.6s16.4 6.2 22.6 0l32-32z"/></svg>'
                    };
            }
        }

        function formatMastoTimeJs(isoDate) {
            const date = new Date(isoDate);
            const timestamp = date.getTime();
            const now = Date.now();
            let diff = Math.floor((now - timestamp) / 1000);

            if (isNaN(diff) || diff < 0) {
                diff = 0;
            }

            let relative = '';
            if (diff < 60) {
                relative = diff + ' Sek.';
            } else if (diff < 3600) {
                relative = Math.floor(diff / 60) + ' Min.';
            } else if (diff < 86400) {
                relative = Math.floor(diff / 3600) + ' Std.';
            } else if (diff < 604800) {
                relative = Math.floor(diff / 86400) + ' T.';
            } else {
                const months = ['Jan', 'Feb', 'Mär', 'Apr', 'Mai', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dez'];
                relative = date.getDate() + '. ' + months[date.getMonth()];
            }

            const day = String(date.getDate()).padStart(2, '0');
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const year = date.getFullYear();
            const hours = String(date.getHours()).padStart(2, '0');
            const minutes = String(date.getMinutes()).padStart(2, '0');
            const fullTooltip = `${day}.${month}.${year}, ${hours}:${minutes} Uhr`;

            return {
                relative: relative,
                full: fullTooltip
            };
        }

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
                                    <a href="${escapeHtml(item.account.url)}" target="_blank" onclick="event.stopPropagation();" title="@${escapeHtml(item.account.username)}">
                                        <img class="inline-block h-8 w-8 rounded-full ring-2 ring-[var(--card-bg)] object-cover" src="/proxy.php?file=${encodeURIComponent(item.account.avatar)}">
                                    </a>`;
                            }

                            let mediaHtml = '';
                            if (item.media_attachments) {
                                item.media_attachments.forEach(m => {
                                    mediaHtml += `<div class="overflow-hidden rounded-lg border border-[var(--border-color)] max-h-60" onclick="event.stopPropagation();"><img class="w-full object-cover" src="/proxy.php?file=${encodeURIComponent(m.url)}"></div>`;
                                });
                            }

                            let previewHtml = '';
                            if (item.link_preview) {
                                let lpImg = item.link_preview.image ? `<img class="w-full h-36 object-cover" src="/proxy.php?file=${encodeURIComponent(item.link_preview.image)}">` : '';
                                let lpDesc = item.link_preview.description ? `<p class="text-[11px] text-[var(--text-muted)] line-clamp-2 leading-snug">${escapeHtml(item.link_preview.description)}</p>` : '';

                                previewHtml = `
                                    <a href="${escapeHtml(item.link_preview.url)}" target="_blank" class="block overflow-hidden rounded-lg border border-[var(--border-color)] bg-[var(--bg-color)] hover:border-[var(--accent)] transition-colors duration-200 no-underline" onclick="event.stopPropagation();">
                                        ${lpImg}
                                        <div class="p-3 space-y-1">
                                            <h4 class="text-xs font-semibold text-[var(--text-color)] line-clamp-1">${escapeHtml(item.link_preview.title)}</h4>
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
                                        <a href="${escapeHtml(item.reblogged_by.url)}" target="_blank" class="hover:underline font-semibold text-[var(--accent)]">
                                            ${escapeHtml(item.reblogged_by.display_name)}
                                        </a>
                                        <span>hat geteilt</span>
                                    </div>
                                `;
                            }

                            const timeDataJs = formatMastoTimeJs(item.created_at);
                            const visDataJs = getVisibilityIconJs(item.visibility || 'public');

                            grid.innerHTML += `
                                <div onclick="window.open('${escapeHtml(item.url)}', '_blank')" class="clickable-card flex flex-col justify-between bg-[var(--card-bg)] border border-[var(--border-color)] rounded-xl p-4 shadow-sm hover:shadow-md transition-shadow duration-200 h-full">
                                    <div class="space-y-3">
                                        ${reblogHtml}
                                        <div class="flex items-center justify-between gap-3" onclick="event.stopPropagation();">
                                            <div class="flex items-center gap-3">
                                                <a href="${escapeHtml(item.account.url)}" target="_blank" class="flex-none">
                                                    <img class="h-9 w-9 rounded-full object-cover ring-1 ring-[var(--border-color)]" src="/proxy.php?file=${encodeURIComponent(item.account.avatar)}">
                                                </a>
                                                <a class="flex flex-col text-sm no-underline hover:underline text-[var(--text-color)]" href="${escapeHtml(item.account.url)}" target="_blank">
                                                    <span class="font-semibold leading-none">${escapeHtml(item.account.display_name)}</span>
                                                    <span class="text-xs text-[var(--text-muted)] mt-0.5">@${escapeHtml(item.account.username)}</span>
                                                </a>
                                            </div>
                                            <div class="flex items-center gap-1.5 text-xs text-[var(--text-muted)] flex-none">
                                                <a href="${escapeHtml(item.url)}" 
                                                   target="_blank" 
                                                   title="${escapeHtml(timeDataJs.full)}" 
                                                   class="hover:underline text-[var(--text-muted)]">
                                                    ${escapeHtml(timeDataJs.relative)}
                                                </a>
                                                <span title="${escapeHtml(visDataJs.label)}" class="inline-flex items-center opacity-70 hover:opacity-100 cursor-help">
                                                    ${visDataJs.svg}
                                                </span>
                                            </div>
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