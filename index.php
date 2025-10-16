<?php

// =================================================================
// 1. قائمة القنوات - تم إعدادها للعمل في بيئة Codespaces
// =================================================================

$channels = [
    // === القناة 1: قناة MP4 التجريبية (للتحقق من أن المشغل يعمل) ===
    [
        'id'        => 1, 
        'name'      => 'ملخص فيديو MP4', 
        'group'     => 'اختبار مشغل', 
        'url'       => 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/Sintel.mp4',
        'thumb_url' => 'https://hd.wallpaperswide.com/thumbs/uefa_champions_league-t2.jpg',
        'proxy'     => false // لا تحتاج وكيل
    ],
    
    // === القناة 2: قناة M3U8 (يجب أن تعمل الآن عبر proxy.php الداخلي) ===
    [
        'id'        => 2, 
        'name'      => 'قناة اختبار Apple (للتأكد)', 
        'group'     => 'اختبار البث المباشر', 
        'url'       => 'https://bitdash-a.akamaihd.net/content/sintel/hls/playlist.m3u8', 
        'thumb_url' => 'https://tv.assets.pressassociation.io/a58b04d0-c64d-556b-bb3c-a0d478871248.jpg',
        'proxy'     => true // تحتاج إلى وكيل (proxy.php)
    ],

    // === القناة 3: قناة NASA Live Stream (يجب أن تعمل الآن عبر proxy.php الداخلي) ===
    [
        'id'        => 3, 
        'name'      => 'NASA Live Stream', 
        'group'     => 'اختبار البث المباشر', 
        'url'       => 'https://nasa-i.akamaihd.net/hls/live/253509/NASA-NTV1-Public/master.m3u8', 
        'thumb_url' => 'https://upload.wikimedia.org/wikipedia/commons/e/e5/NASA_logo.svg',
        'proxy'     => true // تحتاج إلى وكيل (proxy.php)
    ]
];

// دالة العثور على القناة
function get_channel_by_id($id, $list) {
    foreach ($list as $channel) {
        if ($channel['id'] == $id) {
            return $channel;
        }
    }
    return null;
}

// دالة تجميع القنوات حسب المجموعة
function group_channels($list) {
    $grouped = [];
    foreach ($list as $channel) {
        $grouped[$channel['group']][] = $channel;
    }
    return $grouped;
}

// تحديد القناة المراد تشغيلها
$current_channel = null;
if (isset($_GET['id'])) {
    $current_channel = get_channel_by_id($_GET['id'], $channels);
}

// =================================================================
// 2. التصميم (HTML/CSS)
// =================================================================

if ($current_channel) {
    // === قالب المشاهدة (Player Page) ===
    $channel_name = htmlspecialchars($current_channel['name']);
    $group_name = htmlspecialchars($current_channel['group']);
    $thumb_url = htmlspecialchars($current_channel['thumb_url']);
    
    // تحديد رابط البث: هل يحتاج Proxy أم لا؟
    $stream_url = htmlspecialchars($current_channel['url']);
    $player_type = "video/mp4";
    $hls_script = "";

    if ($current_channel['proxy']) {
        // إذا كان يحتاج Proxy، نرسل الرابط الأصلي إلى ملف proxy.php
        $stream_url = "proxy.php?url=" . urlencode($stream_url);
        $player_type = "application/x-mpegURL";
        
        // تفعيل مكتبة Hls.js لتشغيل M3U8
        $hls_script = <<<SCRIPT
        <script src="https://cdn.jsdelivr.net/npm/hls.js@1"></script>
        <script>
            var video = document.getElementById('my-video');
            if (Hls.isSupported()) {
                var hls = new Hls();
                hls.loadSource('$stream_url');
                hls.attachMedia(video);
                hls.on(Hls.Events.MANIFEST_PARSED, function() {
                    video.play().catch(e => { console.error("Autoplay prevented:", e); });
                });
            } else if (video.canPlayType('application/vnd.apple.mpegurl')) {
                video.src = '$stream_url';
                video.addEventListener('loadedmetadata', function() {
                    video.play().catch(e => { console.error("Autoplay prevented:", e); });
                });
            } else {
                console.error("HLS is not supported on this browser.");
            }
        </script>
SCRIPT;
    } else {
        // لا يحتاج Proxy، تشغيل MP4 مباشرة
        $hls_script = <<<SCRIPT
        <script>
            var video = document.getElementById('my-video');
            video.onloadedmetadata = function() {
                video.play().catch(e => { console.error("Autoplay prevented:", e); });
            };
        </script>
SCRIPT;
    }

    echo <<<HTML
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Evo - مشاهدة: $channel_name</title>
    <style>
        :root { --bg-color: #121212; --text-color: #e0e0e0; --accent-color: #00bcd4; --secondary-accent: #ff9800; }
        body { font-family: Arial, sans-serif; background-color: var(--bg-color); color: var(--text-color); padding: 0; margin: 0; text-align: center; overflow-y: scroll; }
        .ambient-background { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-size: cover; background-position: center; filter: blur(10px); opacity: 0.3; transition: background-image 1s ease-in-out; z-index: -1; }
        .content { padding: 20px; max-width: 1200px; margin: 0 auto; }
        h1 { color: #fff; margin-bottom: 10px; text-shadow: 0 0 5px #000; }
        .group-tag { display: inline-block; background-color: rgba(51, 51, 51, 0.7); color: var(--accent-color); padding: 5px 15px; border-radius: 5px; margin-bottom: 20px; font-size: 1em; }
        #player-container { max-width: 100%; margin: 0 auto; box-shadow: 0 0 30px rgba(0, 0, 0, 0.8); border: 2px solid var(--accent-color); }
        #my-video { min-height: 500px; width: 100%; background: #000; } 
        .back-link-icon { position: fixed; top: 20px; right: 20px; background: rgba(255, 152, 0, 0.9); color: #fff; text-decoration: none; padding: 10px 15px; border-radius: 8px; font-size: 1.1em; font-weight: bold; box-shadow: 0 4px 10px rgba(0, 0, 0, 0.5); transition: background 0.3s; z-index: 1000; }
        .back-link-icon:hover { background: var(--accent-color); }
    </style>
</head>
<body>
    <div class="ambient-background" id="ambient-bg" style="background-image: url('$thumb_url');"></div>
    <a href="index.php" class="back-link-icon">← العودة للقائمة</a>

    <div class="content">
        <h1>$channel_name</h1>
        <span class="group-tag">$group_name</span>
        
        <div id="player-container">
            <video
                id="my-video"
                src="$stream_url"
                controls
                autoplay
                playsinline
                preload="auto"
                poster="$thumb_url" 
                type="$player_type" 
            >
                <p>عذراً، لا يمكن تشغيل هذا الفيديو. حاول فتح رابط البث مباشرة.</p>
            </video>
        </div>
    </div>
    
    $hls_script
</body>
</html>
HTML;
    
} else {
    // === قالب الصفحة الرئيسية (Index Page) - القائمة ===
    $channels_by_group = group_channels($channels);

    echo <<<HTML
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EvoTV | القنوات والأفلام</title>
    <style>
        :root { --bg-color: #0d0d0d; --card-bg: #1e1e1e; --text-color: #e0e0e0; --accent-color: #00bcd4; --secondary-accent: #ff9800; --glow-color: rgba(0, 188, 212, 0.4); --shadow-color: rgba(0, 0, 0, 0.7); }
        body { font-family: 'Arial', sans-serif; background: var(--bg-color); color: var(--text-color); padding: 0; margin: 0; text-align: right; background-image: url('https://raw.githubusercontent.com/gist/EvoTV-Assets/1b6a38622c1b26f5d8e75916f1577918/dark-texture.png'); background-attachment: fixed; }
        header { background: rgba(13, 13, 13, 0.85); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); position: sticky; top: 0; z-index: 1000; padding: 15px 30px; box-shadow: 0 4px 15px var(--shadow-color); display: flex; justify-content: center; align-items: center; }
        .logo { color: var(--accent-color); font-size: 1.8em; font-weight: bold; letter-spacing: 1px; display: flex; align-items: center; }
        .logo i { font-style: normal; margin-left: 8px; color: var(--secondary-accent); font-size: 1.2em; }
        .logo span { color: var(--secondary-accent); }
        .container { padding: 30px; max-width: 1400px; margin: 0 auto; }
        h2 { color: var(--secondary-accent); border-bottom: 2px solid #333; padding-bottom: 5px; margin-top: 30px; margin-bottom: 20px; font-size: 1.8em; animation: fadeIn 1s ease-out; text-align: center; }
        .channel-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 25px; margin-bottom: 40px; }
        .channel-card { position: relative; height: 200px; border-radius: 12px; overflow: hidden; transition: transform 0.3s, box-shadow 0.3s, border 0.3s; box-shadow: 0 6px 15px var(--shadow-color); cursor: pointer; animation: fadeIn 1s ease-out; }
        .channel-card:hover { transform: scale(1.05); box-shadow: 0 0 30px var(--glow-color); border: 1px solid var(--accent-color); }
        .channel-link { text-decoration: none; display: block; height: 100%; position: relative; }
        .channel-image { width: 100%; height: 100%; object-fit: cover; transition: transform 0.5s; }
        .channel-card:hover .channel-image { transform: scale(1.1); }
        .channel-info { position: absolute; bottom: 0; width: 100%; padding: 15px; background: linear-gradient(to top, rgba(0, 0, 0, 0.95), rgba(0, 0, 0, 0.2)); color: var(--text-color); text-align: right; transition: padding 0.3s; }
        .channel-name { font-weight: bold; font-size: 1.4em; margin-bottom: 5px; color: #fff; }
        .channel-group-title { font-size: 0.9em; color: var(--accent-color); }
        .play-button { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%) scale(0.8); width: 70px; height: 70px; background: rgba(255, 152, 0, 0.95); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 35px; color: #fff; opacity: 0; transition: opacity 0.3s, transform 0.3s; }
        .channel-card:hover .play-button { opacity: 1; transform: translate(-50%, -50%) scale(1); }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    </style>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
</head>
<body>
    <header>
        <div class="logo">Evo<span>TV</span><i class="material-icons">tv</i></div>
    </header>
    
    <div class="container">
HTML;
    
    // عرض القنوات حسب المجموعة
    foreach ($channels_by_group as $group_name => $channel_list) {
        echo "<h2>" . htmlspecialchars($group_name) . "</h2>";
        echo "<div class='channel-grid'>";
        foreach ($channel_list as $channel) {
            $name = htmlspecialchars($channel['name']);
            $thumb_url = htmlspecialchars($channel['thumb_url']);
            $id = htmlspecialchars($channel['id']);

            echo <<<CARD
                <div class="channel-card">
                    <a href="?id={$id}" class="channel-link">
                        <img src="{$thumb_url}" alt="{$name}" class="channel-image">
                        <div class="channel-info">
                            <div class="channel-name">{$name}</div>
                            <span class="channel-group-title">{$group_name}</span>
                        </div>
                        <div class="play-button">▶</div>
                    </a>
                </div>
CARD;
        }
        echo "</div>";
    }

    echo <<<HTML_END
    </div>
</body>
</html>
HTML_END;
}
?>
