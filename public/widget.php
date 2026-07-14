<?php
require_once __DIR__ . '/../src/MastoCache.php';

$widgetId = $_GET['id'] ?? '';
$configPath = __DIR__ . '/../config/accounts.json';

if (!file_exists($configPath)) {
    exit('Konfiguration fehlt.');
}

$config = json_decode(file_get_contents($configPath), true);
if (!isset($config['widgets'][$widgetId])) {
    exit('Widget nicht gefunden.');
}

$widget = $config['widgets'][$widgetId];
$firstAccountKey = $widget['accounts'][0] ?? null;
$theme = ($firstAccountKey && isset($config['accounts'][$firstAccountKey]['theme'])) ? $config['accounts'][$firstAccountKey]['theme'] : 'dark';

$mastoCache = new MastoCache();
$isExpired = $mastoCache->isCacheExpiredForWidget($widget, $config['accounts']);
$feedData = [];

if (!$isExpired) {
    $feedData = $mastoCache->getWidgetData($widget, $config['accounts'], false);
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($widget['title']); ?></title>
    <style>
        :root {
            --bg-color: #121212; --card-bg: #1e1e1e; --text-color: #e0e0e0;
            --text-muted: #a0a0a0; --border-color: #333; --accent: #8c8dff;
        }
        html[data-theme="light"] {
            --bg-color: #f5f5f5; --card-bg: #ffffff; --text-color: #222222;
            --text-muted: #666666; --border-color: #dddddd; --accent: #5856d6;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: var(--bg-color); color: var(--text-color); padding: 10px; overflow-x: hidden; }
        
        .header-bar { display: flex; justify-content: space-between; align-items: center; padding-bottom: 15px; border-bottom: 1px solid var(--border-color); margin-bottom: 15px; }
        .logo { font-weight: bold; font-size: 1.2rem; color: var(--accent); text-decoration: none; display: flex; align-items: center; gap: 5px; }
        .avatars { display: flex; gap: -8px; }
        .avatars img { width: 32px; height: 32px; border-radius: 50%; border: 2px solid var(--card-bg); object-fit: cover; }
        
        /* Layout-Grid */
        .masonry-wrapper { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; align-items: start; }
        @media (max-width: 600px) { .masonry-wrapper { grid-template-columns: 1fr; } }
        
        .card { background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 8px; padding: 12px; display: flex; flex-direction: column; gap: 8px; break-inside: avoid; }
        .card-header { display: flex; align-items: center; gap: 10px; }
        .card-avatar { width: 36px; height: 36px; border-radius: 50%; object-fit: cover; }
        .card-user { display: flex; flex-direction: column; text-decoration: none; color: var(--text-color); }
        .card-name { font-weight: 600; font-size: 0.9rem; }
        .card-username { font-size: 0.75rem; color: var(--text-muted); }
        .card-body { font-size: 0.85rem; line-height: 1.4; word-break: break-word; }
        .card-body a { color: var(--accent); text-decoration: none; }
        .card-media img { width: 100%; border-radius: 6px; margin-top: 5px; object-fit: cover; max-height: 250px; }
        .card-footer { display: flex; gap: 15px; font-size: 0.75rem; color: var(--text-muted); margin-top: 5px; border-top: 1px solid var(--border-color); padding-top: 8px; }
        .card-footer span { display: flex; align-items: center; gap: 4px; }
        
        /* Loader */
        .loader-container { display: none; text-align: center; padding: 40px 0; width: 100%; grid-column: 1 / -1; }
        .shimmer { background: linear-gradient(90deg, var(--card-bg) 25%, var(--border-color) 50%, var(--card-bg) 75%); background-size: 200% 100%; animation: loading 1.5s infinite; height: 120px; border-radius: 8px; }
        @keyframes loading { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }
        
        .widget-footer { text-align: center; margin-top: 20px; padding-top: 10px; border-top: 1px solid var(--border-color); font-size: 0.7rem; color: var(--text-muted); }
        .widget-footer a { color: var(--text-muted); text-decoration: none; font-weight: bold; }
        .widget-footer a:hover { color: var(--accent); }
    </style>
</head>
<html data-theme="<?php echo $theme; ?>">
<body>

    <div class="header-bar">
        <a href="#" class="logo">⚓ MastoFetch</a>
        <div class="avatars" id="header-avatars">
            <?php if (!$isExpired): 
                $shown = [];
                foreach ($feedData as $item): 
                    if (in_array($item['account']['username'], $shown)) continue;
                    $shown[] = $item['account']['username']; ?>
                    <img src="proxy.php?file=<?php echo urlencode($item['account']['avatar']); ?>" alt="">
                <?php endforeach; 
            endif; ?>
        </div>
    </div>

    <div class="masonry-wrapper" id="content-grid">
        <?php if ($isExpired): ?>
            <div class="loader-container" id="widget-loader" style="display: block;">
                <div class="shimmer"></div>
            </div>
        <?php else: ?>
            <?php foreach ($feedData as $item): ?>
                <div class="card">
                    <div class="card-header">
                        <img class="card-avatar" src="proxy.php?file=<?php echo urlencode($item['account']['avatar']); ?>">
                        <a class="card-user" href="<?php echo htmlspecialchars($item['account']['url']); ?>" target="_blank">
                            <span class="card-name"><?php echo htmlspecialchars($item['account']['display_name']); ?></span>
                            <span class="card-username">@<?php echo htmlspecialchars($item['account']['username']); ?></span>
                        </a>
                    </div>
                    <div class="card-body">
                        <?php echo $item['content']; ?>
                        <?php foreach ($item['media_attachments'] as $media): ?>
                            <div class="card-media"><img src="proxy.php?file=<?php echo urlencode($media['url']); ?>"></div>
                        <?php endforeach; ?>
                    </div>
                    <div class="card-footer">
                        <span>❤️ <?php echo $item['favourites_count']; ?></span>
                        <span>🔁 <?php echo $item['reblogs_count']; ?></span>
                        <span>💬 <?php echo $item['replies_count']; ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="widget-footer">
        Powered by <a href="https://commitcloud.net/RonDevHub/MastoFetch" target="_blank">MastoFetch</a> | <a href="https://deine-donate-seite.de" target="_blank">Donate</a>
    </div>

    <script>
        // Chronologisches Masonry Grid Script (Berechnung der Zeilenabstände basierend auf Inhalt)
        function resizeGridItems() {
            const grid = document.getElementById('content-grid');
            const items = grid.getElementsByClassName('card');
            for (let item of items) {
                item.style.gridRowEnd = "auto";
                const rowGap = 12;
                const rowHeight = 10;
                const rowSpan = Math.ceil((item.querySelector('.card-header').offsetHeight + item.querySelector('.card-body').offsetHeight + (item.querySelector('.card-footer') ? item.querySelector('.card-footer').offsetHeight : 0) + rowGap) / (rowHeight + rowGap));
                item.style.gridRowEnd = "span " + rowSpan;
            }
        }

        window.addEventListener("load", resizeGridItems);
        window.addEventListener("resize", resizeGridItems);

        // Asynchrones Live-Polling bei abgelaufenem Cache
        <?php if ($isExpired): ?>
        fetch('api.php?widget=<?php echo urlencode($widgetId); ?>')
            .then(res => res.json())
            .then(res => {
                if(res.success && res.data.length > 0) {
                    const grid = document.getElementById('content-grid');
                    const avatarContainer = document.getElementById('header-avatars');
                    grid.innerHTML = '';
                    avatarContainer.innerHTML = '';
                    
                    let uniqueAvatars = [];
                    
                    res.data.forEach(item => {
                        if(!uniqueAvatars.includes(item.account.avatar)) {
                            uniqueAvatars.push(item.account.avatar);
                            avatarContainer.innerHTML += `<img src="proxy.php?file=${encodeURIComponent(item.account.avatar)}">`;
                        }
                        
                        let mediaHtml = '';
                        item.media_attachments.forEach(m => {
                            mediaHtml += `<div class="card-media"><img src="proxy.php?file=${encodeURIComponent(m.url)}"></div>`;
                        });

                        grid.innerHTML += `
                            <div class="card">
                                <div class="card-header">
                                    <img class="card-avatar" src="proxy.php?file=${encodeURIComponent(item.account.avatar)}">
                                    <a class="card-user" href="${item.account.url}" target="_blank">
                                        <span class="card-name">${item.account.display_name}</span>
                                        <span class="card-username">@${item.account.username}</span>
                                    </a>
                                </div>
                                <div class="card-body">
                                    ${item.content}
                                    ${mediaHtml}
                                </div>
                                <div class="card-footer">
                                    <span>❤️ ${item.favourites_count}</span>
                                    <span>🔁 ${item.reblogs_count}</span>
                                    <span>💬 ${item.replies_count}</span>
                                </div>
                            </div>
                        `;
                    });
                    setTimeout(resizeGridItems, 100);
                }
            });
        <?php endif; ?>
    </script>
</body>
</html>