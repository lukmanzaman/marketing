<?php
// reindex.php - Memindai seluruh folder dan file JPG/PNG, memperbarui index.json, dan memanggang index.html statis
if (php_sapi_name() !== 'cli') {
    header('Content-Type: text/html; charset=utf-8');
}

$currentDir = __DIR__;
$jsonFile = $currentDir . '/index.json';
$htmlFile = $currentDir . '/index.html';

// Baca urutan folder dari folder.txt jika ada
$orderedFolderNames = [];
if (file_exists($currentDir . '/folder.txt')) {
    $lines = file($currentDir . '/folder.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed !== '') {
            $orderedFolderNames[] = $trimmed;
        }
    }
}

// Dapatkan semua direktori
$scannedItems = scandir($currentDir);
$allDirs = [];
foreach ($scannedItems as $item) {
    if ($item === '.' || $item === '..' || $item === '.git') {
        continue;
    }
    if (is_dir($currentDir . '/' . $item)) {
        $allDirs[] = $item;
    }
}

// Gabungkan urutan
$finalFolderList = [];
foreach ($orderedFolderNames as $of) {
    if (in_array($of, $allDirs, true)) {
        $finalFolderList[] = $of;
    }
}
foreach ($allDirs as $d) {
    if (!in_array($d, $finalFolderList, true)) {
        $finalFolderList[] = $d;
    }
}

function formatBytes($bytes, $precision = 1) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));
    return round($bytes, $precision) . ' ' . $units[$pow];
}

$folderThemes = [
    ['bg' => '#FFF0F5', 'border' => '#FFD1DC', 'accent' => '#FF4D6D', 'icon' => '🌸'],
    ['bg' => '#F0F7FF', 'border' => '#CBE2FE', 'accent' => '#2563EB', 'icon' => '🌊'],
    ['bg' => '#F0FDF4', 'border' => '#BBF7D0', 'accent' => '#16A34A', 'icon' => '🌿'],
    ['bg' => '#FFFBEB', 'border' => '#FDE68A', 'accent' => '#D97706', 'icon' => '🍯'],
    ['bg' => '#FAF5FF', 'border' => '#E9D5FF', 'accent' => '#9333EA', 'icon' => '🔮'],
    ['bg' => '#FFF1F2', 'border' => '#FECDD3', 'accent' => '#E11D48', 'icon' => '🍓'],
    ['bg' => '#ECFEFF', 'border' => '#A5F3FC', 'accent' => '#0891B2', 'icon' => '💎'],
    ['bg' => '#FFF7ED', 'border' => '#FED7AA', 'accent' => '#EA580C', 'icon' => '🍊'],
    ['bg' => '#FDF4FF', 'border' => '#F5D0FE', 'accent' => '#C026D3', 'icon' => '🎀'],
    ['bg' => '#F0FDFA', 'border' => '#99F6E4', 'accent' => '#0D9488', 'icon' => '🍀'],
    ['bg' => '#FEFCE8', 'border' => '#FEF08A', 'accent' => '#CA8A04', 'icon' => '⭐'],
    ['bg' => '#F5F3FF', 'border' => '#DDD6FE', 'accent' => '#7C3AED', 'icon' => '✨'],
];

$foldersData = [];
$totalArticles = 0;
$themeIndex = 0;

foreach ($finalFolderList as $folder) {
    $dirPath = $currentDir . '/' . $folder;
    $files = @scandir($dirPath);
    if (!$files) continue;

    $images = [];
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        
        $filePath = $dirPath . '/' . $file;
        if (is_file($filePath)) {
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png'], true)) {
                $fileSize = @filesize($filePath) ?: 0;
                $cleanTitle = pathinfo($file, PATHINFO_FILENAME);
                // Bersihkan suffix .md jika ada
                if (substr($cleanTitle, -3) === '.md') {
                    $cleanTitle = substr($cleanTitle, 0, -3);
                }
                $cleanTitle = ucwords(trim(str_replace(['-', '_'], ' ', $cleanTitle)));

                $images[] = [
                    'name' => $file,
                    'cleanTitle' => $cleanTitle,
                    'ext' => $ext,
                    'url' => rawurlencode($folder) . '/' . rawurlencode($file),
                    'size' => $fileSize ? formatBytes($fileSize) : '',
                ];
            }
        }
    }

    // Hanya masukkan folder yang punya file gambar
    if (count($images) > 0) {
        $theme = $folderThemes[$themeIndex % count($folderThemes)];
        $themeIndex++;

        $folderNumber = '';
        $displayName = $folder;
        if (preg_match('/^(\d+)[-_ ]*(.*)$/', $folder, $matches)) {
            $folderNumber = $matches[1];
            $displayName = trim(str_replace(['-', '_'], ' ', $matches[2]));
            $displayName = ucwords($displayName);
        } else {
            $displayName = ucwords(str_replace(['-', '_'], ' ', $folder));
        }

        $imageCount = count($images);
        $totalArticles += $imageCount;

        $foldersData[] = [
            'rawName' => $folder,
            'number' => $folderNumber,
            'displayName' => $displayName,
            'imageCount' => $imageCount,
            'theme' => $theme,
            'images' => $images,
        ];
    }
}

$outputData = [
    'generated_at' => date('Y-m-d H:i:s'),
    'total_folders' => count($foldersData),
    'total_articles' => $totalArticles,
    'folders' => $foldersData,
];

// 1. Simpan index.json
$jsonEncoded = json_encode($outputData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
file_put_contents($jsonFile, $jsonEncoded);

// 2. Fungsi memanggang baked index.html mandiri
function generateBakedHtml($outputData) {
    $folders = $outputData['folders'];
    $foldersJson = json_encode($folders, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $generatedAt = htmlspecialchars($outputData['generated_at']);
    $totalFolders = (int)$outputData['total_folders'];
    $totalArticles = (int)$outputData['total_articles'];

    // Render kartu folder statis awal
    $cardsHtml = '';
    if (empty($folders)) {
        $cardsHtml = '<div class="empty-box"><div class="empty-icon">📂🌸</div><h3 class="empty-title">Belum Ada Artikel</h3></div>';
    } else {
        foreach ($folders as $idx => $folder) {
            $rawNameAttr = htmlspecialchars($folder['rawName']);
            $accent = htmlspecialchars($folder['theme']['accent']);
            $numberBadge = !empty($folder['number']) ? '<span class="number-badge">#' . htmlspecialchars($folder['number']) . '</span>' : '';
            $displayName = htmlspecialchars($folder['displayName']);
            $count = (int)$folder['imageCount'];

            // Buat item list dropdown untuk kartu di home
            $cardListItems = '';
            foreach ($folder['images'] as $imgIdx => $img) {
                $artNo = $imgIdx + 1;
                $t = htmlspecialchars($img['cleanTitle'] ?: $img['name']);
                $cardListItems .= '<div class="card-dropdown-item" onclick="event.stopPropagation(); openReader(\'' . addslashes($folder['rawName']) . '\', ' . $artNo . ');"><span class="picker-item-num">#' . $artNo . '</span><span class="picker-item-title">' . $t . '</span></div>';
            }

            $cardsHtml .= '
            <div 
                class="folder-card" 
                data-rawname="' . $rawNameAttr . '"
                style="--theme-accent: ' . $accent . ';"
            >
                <div class="card-click-area" onclick="openReader(\'' . addslashes($folder['rawName']) . '\');">
                    <div class="card-top">
                        <div class="folder-icon-box"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 20h16a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.93a2 2 0 0 1-1.66-.9l-.82-1.2A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13c0 1.1.9 2 2 2Z"/></svg></div>
                        <div class="folder-info">
                            ' . $numberBadge . '
                            <h2 class="folder-title">' . $displayName . '</h2>
                        </div>
                    </div>
                </div>

                <div class="card-bottom-row">
                    <!-- Dropdown List di Home Card -->
                    <div class="card-dropdown-wrapper">
                        <button type="button" class="btn-card-list" onclick="event.stopPropagation(); toggleCardDropdown(this);">
                            <span>Daftar Artikel (' . $count . ')</span>
                            <span class="picker-arrow">▾</span>
                        </button>
                        <div class="card-dropdown-menu">
                            <div class="card-dropdown-header">
                                <span>' . $displayName . '</span>
                                <span>' . $count . ' Item</span>
                            </div>
                            <div class="card-dropdown-list">
                                ' . $cardListItems . '
                            </div>
                        </div>
                    </div>

                    <button type="button" class="btn-read" onclick="openReader(\'' . addslashes($folder['rawName']) . '\');">
                        <span>Buka</span>
                    </button>
                </div>
            </div>';
        }
    }

    $template = <<<'HTML_TPL'
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <title>VIS Marketing • Vertical Infographic Stream</title>

    <!-- Primary Meta Tags -->
    <meta name="title" content="VIS Marketing • Vertical Infographic Stream">
    <meta name="description" content="Eksplorasi visual 1.000+ konsep marketing, psikologi bias kognitif, framework strategi, dan metrik bisnis dalam format continuous vertical stream 4K. The Productive Doomscroll.">
    <meta name="keywords" content="marketing, visual marketing, infografis marketing, framework bisnis, bias kognitif, copywriting, metrik bisnis, productive doomscroll, VIS engine">
    <meta name="author" content="Lukman Zaman">
    <meta name="theme-color" content="#0F1117">

    <!-- Favicon & Icons -->
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <link rel="icon" type="image/png" sizes="32x32" href="favicon.png">
    <link rel="apple-touch-icon" href="favicon.png">

    <!-- Open Graph / Facebook / WhatsApp / LinkedIn -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://lukmanzaman.github.io/marketing/">
    <meta property="og:title" content="VIS Marketing • Vertical Infographic Stream">
    <meta property="og:description" content="Eksplorasi visual 1.000+ konsep marketing, psikologi bias kognitif, framework strategi, dan metrik bisnis dalam format continuous vertical stream 4K. The Productive Doomscroll.">
    <meta property="og:image" content="https://lukmanzaman.github.io/marketing/og-image.jpg">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="VIS Marketing - Vertical Infographic Stream">
    <meta property="og:site_name" content="VIS Marketing">
    <meta property="og:locale" content="id_ID">

    <!-- Twitter Card / X -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:url" content="https://lukmanzaman.github.io/marketing/">
    <meta name="twitter:title" content="VIS Marketing • Vertical Infographic Stream">
    <meta name="twitter:description" content="Eksplorasi visual 1.000+ konsep marketing, psikologi bias kognitif, framework strategi, dan metrik bisnis dalam format continuous vertical stream 4K. The Productive Doomscroll.">
    <meta name="twitter:image" content="https://lukmanzaman.github.io/marketing/og-image.jpg">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>

        :root {
            --font-main: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            --bg-body: #F8F9FA;
            --card-bg: #FFFFFF;
            --card-border: #E5E7EB;
            --text-title: #0F172A;
            --text-body: #334155;
            --text-muted: #64748B;
            --border-soft: #E2E8F0;
            --shadow-card: 0 1px 3px rgba(0, 0, 0, 0.04), 0 6px 16px rgba(0, 0, 0, 0.02);
            --shadow-hover: 0 12px 28px rgba(15, 23, 42, 0.08), 0 4px 10px rgba(0, 0, 0, 0.03);
            --radius-sm: 8px;
            --radius-md: 12px;
            --radius-lg: 16px;
            --radius-full: 9999px;
            --primary: #2563EB;
            --primary-hover: #1D4ED8;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            -webkit-tap-highlight-color: transparent;
        }

        html {
            width: 100%;
            min-height: 100%;
            overflow-x: hidden;
            overflow-y: auto;
        }

        body {
            width: 100%;
            min-height: 100%;
            font-family: var(--font-main);
            color: var(--text-body);
            background-color: var(--bg-body);
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
        }

        body.view-home {
            background-color: var(--bg-body);
        }

        body.view-reader {
            background-color: #0F1117;
        }

        .container {
            max-width: 1180px;
            margin: 0 auto;
            padding: 3.5rem 1.5rem 5rem;
        }

        
        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.35rem 0.85rem;
            background: #EEF2F6;
            border: 1px solid #E2E8F0;
            border-radius: var(--radius-full);
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.06em;
            color: var(--primary);
            margin-bottom: 1.25rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
        }

        .hero-badge-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.2);
        }

        .hero {
            text-align: center;
            margin-bottom: 2.75rem;
        }

        .hero-title {
            font-size: clamp(2rem, 5vw, 3rem);
            font-weight: 800;
            color: var(--text-title);
            letter-spacing: -0.03em;
            margin-bottom: 0.5rem;
        }

        .hero-desc {
            font-size: 0.98rem;
            color: var(--text-muted);
            max-width: 540px;
            margin: 0 auto;
            line-height: 1.5;
        }

        /* SEARCH BAR */
        .search-wrapper {
            position: relative;
            max-width: 640px;
            margin: 0 auto 3rem;
            z-index: 100;
        }

        .search-input-box {
            position: relative;
            display: flex;
            align-items: center;
            background: #FFFFFF;
            border: 1px solid var(--border-soft);
            border-radius: var(--radius-full);
            padding: 0.45rem 0.65rem 0.45rem 1.25rem;
            box-shadow: var(--shadow-card);
            transition: all 0.2s ease;
        }

        .search-input-box:focus-within {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15), 0 8px 20px rgba(0, 0, 0, 0.06);
        }

        .search-icon-left {
            color: var(--text-muted);
            margin-right: 0.75rem;
            display: flex;
            align-items: center;
        }

        .search-input {
            flex: 1;
            border: none;
            outline: none;
            background: transparent;
            font-family: var(--font-main);
            font-size: 0.95rem;
            font-weight: 500;
            color: var(--text-title);
        }

        .search-input::placeholder {
            color: #94A3B8;
        }

        .search-clear-btn {
            background: #F1F5F9;
            border: none;
            color: var(--text-muted);
            font-size: 0.85rem;
            width: 26px;
            height: 26px;
            border-radius: 50%;
            cursor: pointer;
            display: none;
            align-items: center;
            justify-content: center;
            transition: all 0.15s ease;
        }

        .search-clear-btn:hover {
            background: #E2E8F0;
            color: var(--text-title);
        }

        .search-dropdown {
            position: absolute;
            top: calc(100% + 8px);
            left: 0;
            right: 0;
            background: #FFFFFF;
            border: 1px solid var(--border-soft);
            border-radius: var(--radius-md);
            box-shadow: 0 16px 36px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            display: none;
            z-index: 1000;
        }

        .search-dropdown.active {
            display: block;
        }

        .dropdown-header {
            padding: 0.65rem 1rem;
            background: #F8FAFC;
            border-bottom: 1px solid var(--border-soft);
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-muted);
            display: flex;
            justify-content: space-between;
        }

        .search-result-item {
            display: flex;
            align-items: center;
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #F1F5F9;
            cursor: pointer;
            transition: background 0.15s ease;
        }

        .search-result-item:hover, .search-result-item.selected {
            background: #F8FAFC;
        }

        .result-folder-icon {
            color: var(--primary);
            margin-right: 0.85rem;
            display: flex;
            align-items: center;
        }

        .result-text-block {
            flex: 1;
            min-width: 0;
        }

        .result-title-line {
            font-size: 0.88rem;
            font-weight: 600;
            color: var(--text-title);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .result-path-folder {
            color: var(--primary);
        }

        .result-path-line {
            font-size: 0.75rem;
            color: var(--text-muted);
            margin-top: 0.15rem;
        }

        .result-arrow {
            color: #CBD5E1;
            font-size: 1.1rem;
            margin-left: 0.5rem;
            transition: transform 0.15s ease, color 0.15s ease;
        }

        .search-result-item:hover .result-arrow {
            color: var(--primary);
            transform: translateX(3px);
        }

        .match-mark {
            background: #DBEAFE;
            color: #1D4ED8;
            padding: 0.1rem 0.25rem;
            border-radius: 4px;
            font-weight: 600;
        }

        .dropdown-empty {
            padding: 1.75rem;
            text-align: center;
            font-size: 0.85rem;
            color: var(--text-muted);
        }

        /* GRID & FOLDER CARDS */
        .folders-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(330px, 1fr));
            gap: 1.35rem;
        }

        .folder-card {
            background: var(--card-bg);
            border-radius: var(--radius-lg);
            border: 1px solid var(--card-border);
            box-shadow: var(--shadow-card);
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            overflow: visible;
            transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .folder-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--theme-accent, var(--primary));
            border-radius: var(--radius-lg) var(--radius-lg) 0 0;
            opacity: 0.8;
            transition: opacity 0.2s ease;
        }

        .folder-card:hover {
            transform: translateY(-3px);
            border-color: #CBD5E1;
            box-shadow: var(--shadow-hover);
        }

        .card-click-area {
            padding: 1.35rem 1.35rem 0.85rem;
            cursor: pointer;
        }

        .card-top {
            display: flex;
            align-items: flex-start;
            gap: 0.85rem;
            margin-bottom: 0.75rem;
        }

        .folder-icon-box {
            width: 44px;
            height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #F8FAFC;
            border: 1px solid var(--border-soft);
            border-radius: var(--radius-md);
            color: var(--theme-accent, var(--primary));
            flex-shrink: 0;
            transition: transform 0.2s ease;
        }

        .folder-card:hover .folder-icon-box {
            transform: scale(1.05);
            background: #FFFFFF;
        }

        .folder-info {
            flex: 1;
            min-width: 0;
        }

        .number-badge {
            display: inline-block;
            font-size: 0.72rem;
            font-weight: 700;
            color: var(--text-muted);
            background: #F1F5F9;
            padding: 0.1rem 0.45rem;
            border-radius: 4px;
            margin-bottom: 0.35rem;
            letter-spacing: 0.04em;
        }

        .folder-title {
            font-size: 1.05rem;
            font-weight: 700;
            color: var(--text-title);
            line-height: 1.35;
        }

        .card-bottom-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 1.35rem 1.25rem;
            position: relative;
        }

        .card-dropdown-wrapper {
            position: relative;
        }

        .btn-card-list {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            background: #F8FAFC;
            border: 1px solid var(--border-soft);
            color: var(--text-body);
            font-size: 0.78rem;
            font-weight: 600;
            padding: 0.42rem 0.8rem;
            border-radius: var(--radius-full);
            cursor: pointer;
            transition: all 0.15s ease;
        }

        .btn-card-list:hover, .btn-card-list.active {
            background: #FFFFFF;
            border-color: var(--primary);
            color: var(--primary);
        }

        .card-dropdown-menu {
            position: absolute;
            bottom: calc(100% + 8px);
            left: 0;
            width: 280px;
            background: #FFFFFF;
            border: 1px solid var(--border-soft);
            border-radius: var(--radius-md);
            box-shadow: 0 16px 36px rgba(0, 0, 0, 0.12);
            overflow: hidden;
            display: none;
            z-index: 1000;
        }

        .card-dropdown-menu.show {
            display: block;
        }

        .card-dropdown-header {
            padding: 0.6rem 0.95rem;
            background: #F8FAFC;
            border-bottom: 1px solid var(--border-soft);
            font-size: 0.72rem;
            font-weight: 700;
            color: var(--text-title);
            display: flex;
            justify-content: space-between;
        }

        .card-dropdown-list {
            max-height: 180px;
            overflow-y: auto;
            padding: 0.25rem 0;
        }

        .card-dropdown-list::-webkit-scrollbar {
            width: 5px;
        }
        .card-dropdown-list::-webkit-scrollbar-thumb {
            background: #CBD5E1;
            border-radius: 4px;
        }

        .card-dropdown-item {
            padding: 0.48rem 0.95rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--text-body);
            font-size: 0.8rem;
            cursor: pointer;
            transition: background 0.15s ease;
        }

        .card-dropdown-item:hover {
            background: #F1F5F9;
            color: var(--primary);
        }

        .btn-read {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            background: var(--primary);
            color: #FFFFFF;
            border: none;
            font-size: 0.82rem;
            font-weight: 600;
            padding: 0.45rem 1.05rem;
            border-radius: var(--radius-full);
            cursor: pointer;
            box-shadow: 0 2px 8px rgba(37, 99, 235, 0.25);
            transition: all 0.15s ease;
        }

        .btn-read:hover {
            background: var(--primary-hover);
            transform: translateY(-1px);
        }

        .footer-bar {
            text-align: center;
            padding: 3.5rem 0 1rem;
            font-size: 0.82rem;
            color: var(--text-muted);
        }

        /* ========================================================
           READER VIEW (DOOM-SCROLL 100% WIDTH, 1:3 ASPECT RATIO)
           ======================================================== */
        .reader-nav {
            position: fixed;
            top: 14px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 9999;
            display: flex;
            align-items: center;
            gap: 0.55rem;
            background: rgba(15, 17, 23, 0.92);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            padding: 0.35rem 0.85rem;
            border-radius: var(--radius-full);
            border: 1px solid rgba(255, 255, 255, 0.15);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.6);
            max-width: 95vw;
            opacity: 1;
            visibility: visible;
            transition: opacity 0.35s ease, transform 0.35s ease, visibility 0.35s ease;
        }

        .reader-nav.nav-hidden {
            opacity: 0 !important;
            visibility: hidden !important;
            pointer-events: none !important;
            transform: translateX(-50%) translateY(-24px) !important;
        }

        

        .nav-fullscreen-btn, .nav-back-btn {
            background: rgba(255, 255, 255, 0.08);
            color: #FFFFFF;
            border: 1px solid rgba(255, 255, 255, 0.12);
            font-size: 0.82rem;
            font-weight: 600;
            padding: 0.35rem 0.75rem;
            border-radius: var(--radius-full);
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            transition: all 0.15s ease;
        }

        .nav-fullscreen-btn:hover, .nav-back-btn:hover {
            background: rgba(255, 255, 255, 0.18);
        }

        .nav-title-text {
            font-size: 0.88rem;
            font-weight: 700;
            color: #FFFFFF;
            max-width: 220px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .nav-picker-btn {
            background: rgba(255, 255, 255, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: #FFFFFF;
            font-size: 0.75rem;
            font-weight: 600;
            padding: 0.35rem 0.75rem;
            border-radius: var(--radius-full);
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            transition: all 0.15s ease;
        }

        .nav-picker-btn:hover, .nav-picker-btn.active {
            background: #FFFFFF;
            color: #0F172A;
        }

        .nav-picker-dropdown {
            position: absolute;
            top: calc(100% + 12px);
            left: 50%;
            transform: translateX(-50%);
            width: 290px;
            background: #1E222D;
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: var(--radius-md);
            box-shadow: 0 16px 40px rgba(0, 0, 0, 0.8);
            overflow: hidden;
            display: none;
        }

        .nav-picker-dropdown.show {
            display: block;
        }

        .nav-picker-header {
            padding: 0.55rem 0.85rem;
            background: rgba(255, 255, 255, 0.04);
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            font-size: 0.72rem;
            font-weight: 700;
            color: #94A3B8;
            display: flex;
            justify-content: space-between;
        }

        .picker-total-badge {
            background: rgba(255, 255, 255, 0.15);
            color: #FFFFFF;
            padding: 0.1rem 0.45rem;
            border-radius: var(--radius-full);
            font-size: 0.68rem;
        }

        .nav-picker-list {
            max-height: 180px;
            overflow-y: auto;
            padding: 0.3rem 0;
        }

        .nav-picker-list::-webkit-scrollbar {
            width: 5px;
        }
        .nav-picker-list::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.2);
            border-radius: 4px;
        }

        .nav-picker-item {
            display: flex;
            align-items: center;
            gap: 0.55rem;
            padding: 0.48rem 0.85rem;
            color: #CBD5E1;
            font-size: 0.78rem;
            cursor: pointer;
            transition: all 0.15s ease;
        }

        .nav-picker-item:hover {
            background: rgba(255, 255, 255, 0.08);
            color: #FFFFFF;
        }

        .nav-picker-item.active {
            background: var(--primary);
            color: #FFFFFF;
            font-weight: 700;
        }

        .picker-item-num {
            font-size: 0.7rem;
            font-weight: 700;
            color: #94A3B8;
            background: rgba(255, 255, 255, 0.08);
            padding: 0.1rem 0.35rem;
            border-radius: 4px;
        }

        .nav-picker-item.active .picker-item-num {
            background: rgba(255, 255, 255, 0.25);
            color: #FFFFFF;
        }

        .picker-item-title {
            flex: 1;
            min-width: 0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .restore-toast {
            position: fixed;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%) translateY(30px);
            background: #0F172A;
            color: #FFFFFF;
            border: 1px solid rgba(255, 255, 255, 0.15);
            padding: 0.55rem 1.25rem;
            border-radius: var(--radius-full);
            font-size: 0.85rem;
            font-weight: 600;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.4);
            z-index: 10000;
            opacity: 0;
            pointer-events: none;
            transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        .restore-toast.show {
            opacity: 1;
            transform: translateX(-50%) translateY(0);
            pointer-events: auto;
        }

        .doom-feed {
            width: 100%;
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            align-items: stretch;
            background-color: #0F1117;
        }

        .article-frame {
            width: 100%;
            display: block;
            margin: 0;
            padding: 0;
            border-bottom: 1px solid #0F1117;
            background-color: #151821;
            position: relative;
            line-height: 0;
            aspect-ratio: 1 / 3;
            overflow: hidden;
        }

        .article-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            margin: 0;
            padding: 0;
            opacity: 0;
            transition: opacity 0.15s ease-out;
        }

        .article-img.is-loaded {
            opacity: 1;
        }

        

        
    </style>
</head>
<body class="view-home">

    <!-- ==========================================
         HOME VIEW: LIBRARY GRID & LIVE SEARCH
         ========================================== -->
    <div id="homeView">
        <div class="container">
            <header class="hero">
                <div class="hero-badge">
                    <span class="hero-badge-dot"></span>
                    <span>VIS ENGINE • VERTICAL INFOGRAPHIC STREAM</span>
                </div>
                <h1 class="hero-title">VIS Marketing</h1>
                <p class="hero-desc">###TOTAL_FOLDERS### Kategori • ###TOTAL_ARTICLES### Konsep Visual 4K • The Productive Doomscroll</p>

                <!-- REAL-TIME SEARCH -->
                <div class="search-wrapper" id="searchWrapper">
                    <div class="search-input-box">
                        <span class="search-icon-left"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg></span>
                        <input 
                            type="text" 
                            id="globalSearchInput" 
                            class="search-input" 
                            placeholder="Cari nama file artikel..." 
                            autocomplete="off"
                            spellcheck="false"
                        >
                        <button type="button" id="clearSearchBtn" class="search-clear-btn" title="Hapus">✕</button>
                    </div>

                    <!-- Dropdown Matches -->
                    <div class="search-dropdown" id="searchDropdown">
                        <div class="dropdown-header">
                            <span>Hasil Pencarian (Max 3):</span>
                            <span id="searchMatchCount">0 cocok</span>
                        </div>
                        <div id="searchResultList"></div>
                    </div>
                </div>
            </header>

            <main class="folders-grid" id="foldersGrid">
###CARDS_HTML###
            </main>

            <footer class="footer-bar">
                <p>Marketing Visual Vault • Baked Standalone HTML • Total ###TOTAL_ARTICLES### Artikel (###TOTAL_FOLDERS### Folder)</p>
                <p style="font-size: 0.75rem; margin-top: 0.25rem; opacity: 0.7;">Diperbarui: ###GENERATED_AT###</p>
            </footer>
        </div>
    </div>

    <!-- ==========================================
         READER VIEW: CONTINUOUS DOOM-SCROLL
         ========================================== -->
    <div id="readerView" style="display: none;">
        <nav class="reader-nav" id="readerNav">
            <button type="button" class="nav-back-btn" id="navBackBtn">
                <span>‹</span>
                <span>Kembali</span>
            </button>
            <span class="nav-title-text" id="navFolderTitle"></span>
            
            <!-- DROPDOWN ARTICLE SELECTOR / GOTO IN READER -->
            <div class="nav-picker-wrapper" id="navPickerWrapper">
                <button type="button" class="nav-picker-btn" id="navPickerBtn" title="Pilih artikel untuk langsung lompat (Goto)">
                    <span>📄</span>
                    <span class="picker-label" id="navCounter">Pilih Artikel ▾</span>
                    <span class="picker-arrow">▾</span>
                </button>
                <div class="nav-picker-dropdown" id="navPickerDropdown">
                    <div class="nav-picker-header">
                        <span>Daftar Artikel (Lompat Cepat)</span>
                        <span class="picker-total-badge" id="pickerTotalBadge">0 Item</span>
                    </div>
                    <div class="nav-picker-list" id="navPickerList"></div>
                </div>
            </div>

            <!-- FULLSCREEN BUTTON -->
            <button type="button" class="nav-fullscreen-btn" id="navFullscreenBtn" title="Mode Layar Penuh (Fullscreen)">
                <span id="fullscreenIcon">⛶</span>
            </button>
        </nav>

        <div class="restore-toast" id="restoreToast">
            <span>📍</span>
            <span id="restoreToastText">Melanjutkan posisi membaca</span>
        </div>

        <main class="doom-feed" id="doomFeed"></main>

        </div>

    <script>
        // Data baked dari reindex
        const libraryData = ###FOLDERS_JSON###;
        // Base URL absolut untuk gambar (GitHub Pages)
        const IMAGE_BASE_URL = 'https://lukmanzaman.github.io/marketing/';

        const folderMap = {};
        const searchableArticles = [];

        libraryData.forEach(folder => {
            folderMap[folder.rawName] = folder;
            folder.images.forEach((img, idx) => {
                if (img.url && !img.url.startsWith('http://') && !img.url.startsWith('https://')) {
                    img.url = IMAGE_BASE_URL + img.url.replace(/^\/+/, '');
                }
                searchableArticles.push({
                    folderRaw: folder.rawName,
                    folderName: folder.displayName,
                    folderIcon: folder.theme ? folder.theme.icon : '📁',
                    fileName: img.name,
                    cleanTitle: img.cleanTitle || img.name,
                    articleIndex: idx + 1
                });
            });
        });

        // Disable browser's auto scroll override for manual SPA control
        if ('scrollRestoration' in history) {
            history.scrollRestoration = 'manual';
        }

        // Disable browser's auto scroll override for manual SPA control
        if ('scrollRestoration' in history) {
            history.scrollRestoration = 'manual';
        }

        // DOM Elements Cache
        const homeView = document.getElementById('homeView');
        const readerView = document.getElementById('readerView');
        const doomFeed = document.getElementById('doomFeed');
        const nav = document.getElementById('readerNav');
        const navBackBtn = document.getElementById('navBackBtn');
        const navTitle = document.getElementById('navFolderTitle');
        const navPickerBtn = document.getElementById('navPickerBtn');
        const navCounter = document.getElementById('navCounter');
        const navPickerDropdown = document.getElementById('navPickerDropdown');
        const pickerTotalBadge = document.getElementById('pickerTotalBadge');
        const navPickerList = document.getElementById('navPickerList');
        const restoreToast = document.getElementById('restoreToast');
        const restoreToastText = document.getElementById('restoreToastText');

        // State variables
        let currentActiveFolder = null;
        let currentActiveArtIdx = 1;
        let homeScrollY = 0;
        let imageObserver = null;
        let isScrollTicking = false;
        let isRestoringScroll = false;
        let saveScrollTimeout = null;

        // Visibility Controls (Default: lenyap, toggle via tap/click)
        function showNav() {
            nav.classList.remove('nav-hidden');
        }

        function hideNav() {
            closePickerDropdown();
            nav.classList.add('nav-hidden');
        }

        function toggleNav() {
            if (nav.classList.contains('nav-hidden')) {
                showNav();
            } else {
                hideNav();
            }
        }

        // ================= INDEPENDENT PER-FOLDER DUAL STORAGE (LOCALSTORAGE + COOKIES) =================
        function setCookie(name, value, days) {
            try {
                const d = new Date();
                d.setTime(d.getTime() + ((days || 365) * 24 * 60 * 60 * 1000));
                const expires = "expires=" + d.toUTCString();
                document.cookie = name + "=" + encodeURIComponent(value) + ";" + expires + ";path=/;SameSite=Lax";
            } catch(e) {}
        }

        function getCookie(name) {
            try {
                const nameEQ = name + "=";
                const ca = document.cookie.split(';');
                for (let i = 0; i < ca.length; i++) {
                    let c = ca[i];
                    while (c.charAt(0) === ' ') c = c.substring(1, c.length);
                    if (c.indexOf(nameEQ) === 0) return decodeURIComponent(c.substring(nameEQ.length, c.length));
                }
            } catch(e) {}
            return null;
        }

        // Master Position Store: Menyimpan posisi tiap folder secara independen & presisi per pixel
        let folderPositions = {};

        function loadAllPositions() {
            let loaded = {};
            try {
                const raw = localStorage.getItem('mkt_vault_positions');
                if (raw) loaded = JSON.parse(raw) || {};
            } catch(e) {}

            if (Object.keys(loaded).length === 0) {
                const cookieRaw = getCookie('mkt_vault_positions');
                if (cookieRaw) {
                    try { loaded = JSON.parse(cookieRaw) || {}; } catch(e) {}
                }
            }

            // Fallback per-folder keys
            if (typeof libraryData !== 'undefined') {
                libraryData.forEach(f => {
                    if (!loaded[f.rawName]) {
                        try {
                            const single = localStorage.getItem('mkt_pos_' + f.rawName) || getCookie('mkt_pos_' + f.rawName);
                            if (single) {
                                loaded[f.rawName] = JSON.parse(single);
                            }
                        } catch(e) {}
                    }
                });
            }

            return loaded;
        }

        folderPositions = loadAllPositions();

        function saveCurrentPosition(forceSync) {
            if (!currentActiveFolder || isRestoringScroll) return;
            const fKey = currentActiveFolder.rawName;
            const artIdx = currentActiveArtIdx || 1;
            const scrollY = Math.round(window.scrollY);

            folderPositions[fKey] = {
                artIdx: artIdx,
                scrollY: scrollY,
                total: currentActiveFolder.imageCount,
                ts: Date.now()
            };

            const serialized = JSON.stringify(folderPositions);
            try { localStorage.setItem('mkt_vault_positions', serialized); } catch(e) {}
            try { localStorage.setItem('mkt_pos_' + fKey, JSON.stringify(folderPositions[fKey])); } catch(e) {}
            setCookie('mkt_vault_positions', serialized, 365);
            setCookie('mkt_pos_' + fKey, JSON.stringify(folderPositions[fKey]), 365);
        }

        // Flush position on window unload/pagehide
        window.addEventListener('beforeunload', () => saveCurrentPosition(true));
        window.addEventListener('pagehide', () => saveCurrentPosition(true));

        function escapeHtml(str) {
            if (!str) return '';
            return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }

        function parseParams() {
            const hash = window.location.hash.replace(/^#/, '');
            const params = new URLSearchParams(hash);
            return {
                baca: params.get('baca') || null,
                art: parseInt(params.get('art'), 10) || 0
            };
        }

        function setRoute(folderRawName, artIdx) {
            if (folderRawName) {
                let newHash = 'baca=' + folderRawName;
                if (artIdx > 0) newHash += '&art=' + artIdx;
                if (window.location.hash !== '#' + newHash) {
                    history.pushState(null, '', '#' + newHash);
                }
            } else {
                if (window.location.hash) {
                    history.pushState(null, '', window.location.pathname + window.location.search);
                }
            }
        }

        function openReader(rawName, targetArt, updateHash) {
            if (updateHash === undefined) updateHash = true;
            const folder = folderMap[rawName];
            if (!folder) return;
            renderReader(folder, targetArt || 0);
            if (updateHash) {
                setRoute(rawName, targetArt || 0);
            }
        }

        function closeReader(updateHash) {
            if (updateHash === undefined) updateHash = true;
            if (currentActiveFolder) {
                saveCurrentPosition(true);
            }
            renderHome();
            if (updateHash) {
                setRoute(null);
            }
        }

        function renderHome() {
            if (currentActiveFolder) {
                saveCurrentPosition(true);
            }
            currentActiveFolder = null;
            closePickerDropdown();
            hideNav();

            document.body.className = 'view-home';
            readerView.style.display = 'none';
            homeView.style.display = 'block';
            document.title = 'VIS Marketing • Vertical Infographic Stream';

            if (imageObserver) {
                imageObserver.disconnect();
                imageObserver = null;
            }

            window.scrollTo({ top: homeScrollY, behavior: 'instant' });
        }

        // ================= SMART PROACTIVE PRELOADER PIPELINE =================
        const preloadedUrls = new Set();

        function preloadImageUrl(url) {
            if (!url || preloadedUrls.has(url)) return;
            preloadedUrls.add(url);
            const temp = new Image();
            temp.decoding = 'async';
            temp.src = url;
        }

        function loadFrameImage(idx) {
            if (!currentActiveFolder || !currentActiveFolder.images) return;
            if (idx < 1 || idx > currentActiveFolder.images.length) return;

            const frame = document.getElementById('art-' + idx);
            if (!frame) return;

            const img = frame.querySelector('img.article-img');
            if (!img) return;

            const realSrc = img.getAttribute('data-src');
            if (!realSrc) return;

            if (img.src !== realSrc) {
                img.src = realSrc;
            }

            if (img.complete && img.naturalWidth > 0) {
                img.classList.add('is-loaded');
                frame.classList.add('loaded');
            } else {
                img.onload = () => {
                    img.classList.add('is-loaded');
                    frame.classList.add('loaded');
                };
            }
        }

        // Proactive Runway: Selalu memuat 5 gambar ke depan dan 2 di belakang artikel aktif
        function preloadRunway(currentIdx) {
            if (!currentActiveFolder || !currentActiveFolder.images) return;
            const total = currentActiveFolder.images.length;
            
            const start = Math.max(1, currentIdx - 2);
            const end = Math.min(total, currentIdx + 5);

            for (let i = start; i <= end; i++) {
                loadFrameImage(i);
                const item = currentActiveFolder.images[i - 1];
                if (item && item.url) {
                    preloadImageUrl(item.url);
                }
            }

            // Lookahead background network cache (+6 s/d +8)
            for (let j = end + 1; j <= Math.min(total, end + 3); j++) {
                const item = currentActiveFolder.images[j - 1];
                if (item && item.url) {
                    preloadImageUrl(item.url);
                }
            }
        }

        function setupLazyObserver() {
            const lazyImages = doomFeed.querySelectorAll('.lazy-img');
            if ('IntersectionObserver' in window) {
                if (imageObserver) imageObserver.disconnect();
                imageObserver = new IntersectionObserver((entries, observer) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            const img = entry.target;
                            const idx = parseInt(img.closest('.article-frame')?.getAttribute('data-index'), 10);
                            if (idx) preloadRunway(idx);
                        }
                    });
                }, {
                    root: null,
                    rootMargin: '4000px 0px 4000px 0px',
                    threshold: 0.001
                });
                lazyImages.forEach(img => imageObserver.observe(img));
            }
        }

        // Native BoundingClientRect Tracker untuk deteksi artikel aktif 100% presisi
        function getCurrentActiveArticleIndex() {
            const frames = doomFeed.children;
            if (!frames || frames.length === 0) return 1;
            const mid = window.innerHeight * 0.4;
            for (let i = 0; i < frames.length; i++) {
                const rect = frames[i].getBoundingClientRect();
                if (rect.top <= mid && rect.bottom > mid) {
                    return i + 1;
                }
            }
            if (frames[0] && frames[0].offsetHeight > 10) {
                const h = frames[0].offsetHeight;
                return Math.max(1, Math.min(frames.length, Math.floor((window.scrollY + mid) / h) + 1));
            }
            return 1;
        }

        function renderReader(folder, targetArt) {
            if (!folder) return;
            if (currentActiveFolder && currentActiveFolder !== folder) {
                saveCurrentPosition(true);
            }

            isRestoringScroll = true;
            homeScrollY = window.scrollY;
            currentActiveFolder = folder;

            // Muat data posisi per-folder yang tersimpan di storage
            folderPositions = loadAllPositions();

            let targetArtIdx = 1;
            let targetScrollY = 0;
            let isExactPixelRestore = false;

            if (targetArt && targetArt > 0) {
                // User meminta lompat ke artikel tertentu (misal dari menu daftar artikel)
                targetArtIdx = targetArt;
                isExactPixelRestore = false;
            } else if (folderPositions[folder.rawName]) {
                // Membuka kembali folder: gunakan koordinat EXACT PIXEL asli!
                const pos = folderPositions[folder.rawName];
                if (pos) {
                    if (pos.scrollY && pos.scrollY > 0) {
                        targetScrollY = pos.scrollY;
                        isExactPixelRestore = true;
                    }
                    if (pos.artIdx && pos.artIdx >= 1) {
                        targetArtIdx = pos.artIdx;
                    }
                }
            }

            currentActiveArtIdx = targetArtIdx;

            document.body.className = 'view-reader';
            homeView.style.display = 'none';
            readerView.style.display = 'block';
            document.title = folder.displayName + ' • Baca Visual Marketing';

            navTitle.textContent = folder.displayName;
            navCounter.textContent = 'Art. ' + targetArtIdx + ' / ' + folder.imageCount;
            pickerTotalBadge.textContent = folder.imageCount + ' Item';

            // Default lenyap saat masuk reader
            hideNav();

            // Build Dropdown Article Picker List
            let pickerHtml = '';
            folder.images.forEach((img, i) => {
                const idx = i + 1;
                const activeCls = idx === targetArtIdx ? 'active' : '';
                pickerHtml += '<div class="nav-picker-item ' + activeCls + '" data-art="' + idx + '" onclick="jumpToArticle(' + idx + ')">';
                pickerHtml += '<span class="picker-item-num">#' + idx + '</span>';
                pickerHtml += '<span class="picker-item-title" title="' + escapeHtml(img.cleanTitle || img.name) + '">' + escapeHtml(img.cleanTitle || img.name) + '</span>';
                pickerHtml += '</div>';
            });
            navPickerList.innerHTML = pickerHtml;

            // Build feed items
            let feedHtml = '';
            folder.images.forEach((img, i) => {
                const idx = i + 1;
                feedHtml += '<div class="article-frame" id="art-' + idx + '" data-index="' + idx + '">';
                feedHtml += '<img class="article-img lazy-img" src="data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 1 3\'%3E%3C/svg%3E" data-src="' + img.url + '" alt="' + escapeHtml(img.name) + '" decoding="async">';
                feedHtml += '</div>';
            });
            doomFeed.innerHTML = feedHtml;

            setupLazyObserver();
            preloadRunway(targetArtIdx);

            // ================= PEMULIHAN EXACT PIXEL SCROLL =================
            if (isExactPixelRestore && targetScrollY > 0) {
                // Restore ke posisi pixel presisi tempat terakhir user membaca!
                const doPixelRestore = () => {
                    window.scrollTo({ top: targetScrollY, behavior: 'instant' });
                    const activeIdx = getCurrentActiveArticleIndex();
                    updateActiveArticleState(activeIdx);
                    preloadRunway(activeIdx);
                    restoreToastText.textContent = 'Melanjutkan posisi membaca (Artikel #' + activeIdx + ' of ' + folder.imageCount + ')';
                    restoreToast.classList.add('show');
                    setTimeout(() => restoreToast.classList.remove('show'), 2800);
                };

                doPixelRestore();
                requestAnimationFrame(() => {
                    doPixelRestore();
                    setTimeout(() => {
                        doPixelRestore();
                        isRestoringScroll = false;
                    }, 100);
                });
            } else if (targetArtIdx > 1) {
                // Lompat ke artikel target (puncak gambar artikel tersebut)
                const doElementJump = () => {
                    const targetEl = document.getElementById('art-' + targetArtIdx);
                    if (targetEl) {
                        targetEl.scrollIntoView({ behavior: 'instant', block: 'start' });
                    }
                    updateActiveArticleState(targetArtIdx);
                    preloadRunway(targetArtIdx);
                    restoreToastText.textContent = 'Lompat ke Artikel #' + targetArtIdx + ' of ' + folder.imageCount;
                    restoreToast.classList.add('show');
                    setTimeout(() => restoreToast.classList.remove('show'), 2800);
                };

                doElementJump();
                requestAnimationFrame(() => {
                    doElementJump();
                    setTimeout(() => {
                        doElementJump();
                        isRestoringScroll = false;
                    }, 100);
                });
            } else {
                window.scrollTo({ top: 0, behavior: 'instant' });
                setTimeout(() => {
                    isRestoringScroll = false;
                }, 100);
            }
        }

        // Dropdown toggle & Goto helper
        function togglePickerDropdown() {
            const isOpen = navPickerDropdown.classList.contains('show');
            if (isOpen) {
                closePickerDropdown();
            } else {
                navPickerDropdown.classList.add('show');
                navPickerBtn.classList.add('active');
                const activeItem = navPickerList.querySelector('.nav-picker-item.active');
                if (activeItem) {
                    activeItem.scrollIntoView({ block: 'nearest' });
                }
            }
        }

        function closePickerDropdown() {
            navPickerDropdown.classList.remove('show');
            navPickerBtn.classList.remove('active');
        }

        navPickerBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            togglePickerDropdown();
        });

        window.jumpToArticle = function(artIdx, smooth) {
            if (smooth === undefined) smooth = true;
            closePickerDropdown();
            preloadRunway(artIdx);
            const targetEl = document.getElementById('art-' + artIdx);
            if (targetEl) {
                targetEl.scrollIntoView({ behavior: smooth ? 'smooth' : 'instant', block: 'start' });
                updateActiveArticleState(artIdx);
                saveCurrentPosition(true);
                restoreToastText.textContent = 'Lompat ke Artikel #' + artIdx + ' of ' + currentActiveFolder.imageCount;
                restoreToast.classList.add('show');
                setTimeout(() => restoreToast.classList.remove('show'), 2400);
            }
        };

        function updateActiveArticleState(artIdx) {
            currentActiveArtIdx = artIdx;
            if (currentActiveFolder) {
                navCounter.textContent = 'Art. ' + artIdx + ' / ' + currentActiveFolder.imageCount;
            }
            const items = navPickerList.querySelectorAll('.nav-picker-item');
            items.forEach(item => {
                const itemIdx = parseInt(item.getAttribute('data-art'), 10);
                item.classList.toggle('active', itemIdx === artIdx);
            });
        }

        // Back button handlers
        navBackBtn.addEventListener('click', () => closeReader(true));

        // Fullscreen toggle handler
        const navFullscreenBtn = document.getElementById('navFullscreenBtn');
        function toggleFullscreen() {
            if (!document.fullscreenElement) {
                document.documentElement.requestFullscreen().catch(err => {});
            } else {
                if (document.exitFullscreen) {
                    document.exitFullscreen().catch(err => {});
                }
            }
        }

        if (navFullscreenBtn) {
            navFullscreenBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                toggleFullscreen();
            });
        }

        document.addEventListener('fullscreenchange', () => {
            const icon = document.getElementById('fullscreenIcon');
            if (icon) {
                icon.textContent = document.fullscreenElement ? '✕' : '⛶';
            }
            if (navFullscreenBtn) {
                navFullscreenBtn.title = document.fullscreenElement ? 'Keluar Fullscreen (Esc)' : 'Mode Layar Penuh (Fullscreen)';
            }
        });

        // Route sync
        function handleRouteChange() {
            const { baca, art } = parseParams();
            if (baca && folderMap[baca]) {
                openReader(baca, art, false);
            } else {
                renderHome();
            }
        }
        window.addEventListener('hashchange', handleRouteChange);
        window.addEventListener('popstate', handleRouteChange);

        // Scroll listener with real-time RAF tracking & continuous runway preloading
        function handleReaderScroll() {
            if (!currentActiveFolder || isRestoringScroll) return;

            // Sembunyikan navbar saat user mulai scroll membaca
            if (!nav.classList.contains('nav-hidden')) {
                hideNav();
            }

            if (!isScrollTicking) {
                isScrollTicking = true;
                requestAnimationFrame(() => {
                    if (!currentActiveFolder || isRestoringScroll) {
                        isScrollTicking = false;
                        return;
                    }

                    const activeIdx = getCurrentActiveArticleIndex();

                    if (activeIdx !== currentActiveArtIdx) {
                        updateActiveArticleState(activeIdx);
                    }

                    // Preload runway selalu 5 gambar ke depan di setiap posisi scroll!
                    preloadRunway(activeIdx);

                    isScrollTicking = false;
                });
            }

            if (saveScrollTimeout) clearTimeout(saveScrollTimeout);
            saveScrollTimeout = setTimeout(() => {
                if (!currentActiveFolder || isRestoringScroll) return;
                saveCurrentPosition(false);
            }, 100);
        }
        window.addEventListener('scroll', handleReaderScroll, { passive: true });

        // Tap/Click Screen Listener: Tekan atas muncul, tekan bagian lain lenyap
        document.addEventListener('click', (e) => {
            if (!currentActiveFolder) return;

            if (e.target.closest('#readerNav') || e.target.closest('#restoreToast')) {
                return;
            }

            if (e.clientY <= 90) {
                toggleNav();
            } else {
                hideNav();
            }
        });

        // ================= SEARCH IMPLEMENTATION =================
        const searchInput = document.getElementById('globalSearchInput');
        const searchClearBtn = document.getElementById('clearSearchBtn');
        const searchDropdown = document.getElementById('searchDropdown');
        const resultList = document.getElementById('searchResultList');
        const resultCount = document.getElementById('searchMatchCount');

        let searchItems = [];
        let selectedIndex = -1;

        libraryData.forEach(folder => {
            folder.images.forEach((img, idx) => {
                searchItems.push({
                    folderRawName: folder.rawName,
                    folderDisplayName: folder.displayName,
                    folderTheme: folder.theme,
                    folderNumber: folder.number,
                    articleIndex: idx + 1,
                    fileName: img.name,
                    cleanTitle: img.cleanTitle || img.name,
                    searchKey: (img.name + ' ' + (img.cleanTitle || '') + ' ' + folder.displayName).toLowerCase()
                });
            });
        });

        function performSearch(query) {
            const q = query.trim().toLowerCase();
            if (!q) {
                closeSearch();
                return;
            }

            const terms = q.split(/\s+/).filter(t => t.length > 0);
            const matches = searchItems.filter(item => {
                return terms.every(term => item.searchKey.includes(term));
            });

            renderSearchResults(matches, terms, q);
        }

        function renderSearchResults(results, terms, rawQuery) {
            selectedIndex = -1;
            if (results.length === 0) {
                if (resultCount) resultCount.textContent = '0 Hasil';
                resultList.innerHTML = '<div class="dropdown-empty">Tidak ada artikel yang cocok dengan "<strong>' + escapeHtml(rawQuery) + '</strong>"</div>';
                searchDropdown.classList.add('active');
                return;
            }

            const maxResults = 15;
            const shownResults = results.slice(0, maxResults);
            if (resultCount) resultCount.textContent = results.length + ' Hasil' + (results.length > maxResults ? ' (Top 15)' : '');

            let html = '';
            shownResults.forEach((res, idx) => {
                let displayTitle = escapeHtml(res.cleanTitle);
                terms.forEach(term => {
                    if (!term) return;
                    const regex = new RegExp('(' + escapeRegExp(term) + ')', 'gi');
                    displayTitle = displayTitle.replace(regex, '<span class="match-mark">$1</span>');
                });

                html += '<div class="search-result-item" data-index="' + idx + '" data-folder="' + res.folderRawName + '" data-art="' + res.articleIndex + '">';
                html += '<span class="result-folder-icon">';
                html += '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 20h16a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.93a2 2 0 0 1-1.66-.9l-.82-1.2A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13c0 1.1.9 2 2 2Z"/></svg>';
                html += '</span>';
                html += '<div class="result-text-block">';
                html += '<div class="result-title-line">' + displayTitle + '</div>';
                html += '<div class="result-path-line">';
                html += '<span class="result-path-folder">' + escapeHtml(res.folderDisplayName) + '</span> • Artikel #' + res.articleIndex;
                html += '</div>';
                html += '</div>';
                html += '<span class="result-arrow">›</span>';
                html += '</div>';
            });

            resultList.innerHTML = html;
            searchDropdown.classList.add('active');

            const items = resultList.querySelectorAll('.search-result-item');
            items.forEach(el => {
                el.addEventListener('click', () => {
                    const fName = el.getAttribute('data-folder');
                    const aIdx = parseInt(el.getAttribute('data-art'), 10);
                    closeSearch();
                    openReader(fName, aIdx);
                });
            });
        }

        function escapeRegExp(string) {
            return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        }

        function closeSearch() {
            searchDropdown.classList.remove('active');
            resultList.innerHTML = '';
            selectedIndex = -1;
        }

        searchInput.addEventListener('input', () => {
            const val = searchInput.value;
            if (searchClearBtn) searchClearBtn.style.display = val ? 'flex' : 'none';
            performSearch(val);
        });

        if (searchClearBtn) {
            searchClearBtn.addEventListener('click', () => {
                searchInput.value = '';
                searchClearBtn.style.display = 'none';
                closeSearch();
                searchInput.focus();
            });
        }

        searchInput.addEventListener('keydown', (e) => {
            if (!searchDropdown.classList.contains('active')) return;
            const items = resultList.querySelectorAll('.search-result-item');
            if (!items.length) return;

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                selectedIndex = (selectedIndex + 1) % items.length;
                updateSelection(items);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                selectedIndex = (selectedIndex - 1 + items.length) % items.length;
                updateSelection(items);
            } else if (e.key === 'Enter') {
                e.preventDefault();
                if (selectedIndex >= 0 && selectedIndex < items.length) {
                    items[selectedIndex].click();
                } else if (items.length > 0) {
                    items[0].click();
                }
            } else if (e.key === 'Escape') {
                closeSearch();
            }
        });

        function updateSelection(items) {
            items.forEach((item, i) => {
                if (i === selectedIndex) {
                    item.classList.add('selected');
                    item.scrollIntoView({ block: 'nearest' });
                } else {
                    item.classList.remove('selected');
                }
            });
        }

        document.addEventListener('click', (e) => {
            if (!e.target.closest('#searchWrapper')) {
                closeSearch();
            }
            if (!e.target.closest('.card-dropdown-wrapper')) {
                document.querySelectorAll('.card-dropdown-menu.show').forEach(m => m.classList.remove('show'));
                document.querySelectorAll('.btn-card-list.active').forEach(b => b.classList.remove('active'));
            }
        });

        window.toggleCardDropdown = function(btn) {
            const wrapper = btn.closest('.card-dropdown-wrapper');
            const menu = wrapper.querySelector('.card-dropdown-menu');
            const wasOpen = menu.classList.contains('show');

            document.querySelectorAll('.card-dropdown-menu.show').forEach(m => m.classList.remove('show'));
            document.querySelectorAll('.btn-card-list.active').forEach(b => b.classList.remove('active'));

            if (!wasOpen) {
                menu.classList.add('show');
                btn.classList.add('active');
            }
        };

        document.addEventListener('DOMContentLoaded', () => {
            const { baca, art } = parseParams();
            if (baca && folderMap[baca]) {
                openReader(baca, art, false);
            } else {
                renderHome();
            }
        });

    </script>
</body>
</html>
HTML_TPL;

    return str_replace(
        ['###FOLDERS_JSON###', '###CARDS_HTML###', '###GENERATED_AT###', '###TOTAL_FOLDERS###', '###TOTAL_ARTICLES###'],
        [$foldersJson, $cardsHtml, $generatedAt, $totalFolders, $totalArticles],
        $template
    );
}

$htmlContent = generateBakedHtml($outputData);
file_put_contents($htmlFile, $htmlContent);

// Tampilan CLI jika dijalankan di Terminal
if (php_sapi_name() === 'cli') {
    echo "=== REINDEX SELESAI ===\n";
    echo "Total Folder Aktif  : " . count($foldersData) . "\n";
    echo "Total Artikel Visual: " . $totalArticles . "\n";
    echo "JSON Cache          : index.json (" . formatBytes(strlen($jsonEncoded)) . ")\n";
    echo "Baked Standalone    : index.html (" . formatBytes(strlen($htmlContent)) . ")\n";
    echo "Lokasi index.html   : " . $htmlFile . "\n";
    exit(0);
}

// Response JSON jika diminta melalui API/AJAX
if (isset($_GET['format']) && $_GET['format'] === 'json') {
    header('Content-Type: application/json');
    echo $jsonEncoded;
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reindex Selesai • VIS Marketing</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;600;700&family=Quicksand:wght@600;700;800&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: #0F1117;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            color: #E2E8F0;
        }
        .card {
            background: #1A1D27;
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 24px;
            box-shadow: 0 12px 32px rgba(0,0,0,0.5);
            max-width: 520px;
            width: 100%;
            padding: 2.25rem 2rem;
            text-align: center;
        }
        .badge-icon {
            font-size: 3rem;
            margin-bottom: 0.75rem;
            display: inline-block;
            animation: bounce 1.5s infinite alternate ease-in-out;
        }
        @keyframes bounce {
            0% { transform: translateY(0); }
            100% { transform: translateY(-8px); }
        }
        h1 {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 1.6rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
            color: #FFFFFF;
        }
        p {
            color: #94A3B8;
            font-size: 0.92rem;
            margin-bottom: 1.5rem;
            line-height: 1.45;
        }
        .stats-box {
            background: #14161F;
            border: 1px dashed rgba(255,255,255,0.12);
            border-radius: 16px;
            padding: 1rem;
            margin-bottom: 1.75rem;
            display: flex;
            justify-content: space-around;
            text-align: center;
        }
        .stat-val {
            font-size: 1.35rem;
            font-weight: 800;
            color: #FF5A87;
        }
        .stat-lbl {
            font-size: 0.75rem;
            color: #94A3B8;
            font-weight: 600;
        }
        .btn-group {
            display: flex;
            flex-direction: column;
            gap: 0.65rem;
        }
        .btn-row {
            display: flex;
            gap: 0.65rem;
        }
        .btn-main {
            flex: 1;
            background: linear-gradient(135deg, #FF758C 0%, #FF5A87 100%);
            color: #FFFFFF;
            text-decoration: none;
            font-size: 0.95rem;
            font-weight: 700;
            padding: 0.75rem 1rem;
            border-radius: 9999px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.4rem;
            box-shadow: 0 4px 14px rgba(255, 90, 135, 0.3);
            transition: transform 0.2s ease;
        }
        .btn-main:hover {
            transform: translateY(-2px);
        }
        .btn-sec {
            flex: 1;
            background: rgba(255,255,255,0.06);
            color: #E2E8F0;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 700;
            padding: 0.75rem 1rem;
            border-radius: 9999px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.4rem;
            transition: background 0.2s ease;
        }
        .btn-sec:hover {
            background: rgba(255,255,255,0.12);
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="badge-icon">⚡✨</div>
        <h1>Reindex Berhasil!</h1>
        <p>File <code>index.json</code> dan <strong><code>index.html</code> (VIS Marketing)</strong> telah diperbarui dengan data <strong><?= $totalArticles ?> artikel</strong> terbaru.</p>

        <div class="stats-box">
            <div>
                <div class="stat-val"><?= count($foldersData) ?></div>
                <div class="stat-lbl">Folder Aktif</div>
            </div>
            <div>
                <div class="stat-val"><?= $totalArticles ?></div>
                <div class="stat-lbl">Total Artikel</div>
            </div>
            <div>
                <div class="stat-val"><?= formatBytes(strlen($htmlContent)) ?></div>
                <div class="stat-lbl">Ukuran HTML</div>
            </div>
        </div>

        <div class="btn-group">
            <a href="index.html" class="btn-main">
                <span>⚡</span>
                <span>Buka VIS Marketing</span>
            </a>
            <div class="btn-row">
                <a href="reindex.php" class="btn-sec">
                    <span>🔄</span>
                    <span>Reindex Lagi</span>
                </a>
            </div>
        </div>
    </div>
</body>
</html>
