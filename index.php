<?php
// index.php - Fast Marketing Visual Library with index.json cache and real-time search
$currentDir = __DIR__;
$jsonFile = $currentDir . '/index.json';

// Function to scan and build index.json if not present
function generateIndexJson($currentDir, $jsonFile) {
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

    $scannedItems = scandir($currentDir);
    $allDirs = [];
    foreach ($scannedItems as $item) {
        if ($item === '.' || $item === '..' || $item === '.git') continue;
        if (is_dir($currentDir . '/' . $item)) $allDirs[] = $item;
    }

    $finalFolderList = [];
    foreach ($orderedFolderNames as $of) {
        if (in_array($of, $allDirs, true)) $finalFolderList[] = $of;
    }
    foreach ($allDirs as $d) {
        if (!in_array($d, $finalFolderList, true)) $finalFolderList[] = $d;
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
                    $cleanTitle = ucwords(trim(str_replace(['-', '_'], ' ', $cleanTitle)));

                    $images[] = [
                        'name' => $file,
                        'cleanTitle' => $cleanTitle,
                        'ext' => $ext,
                        'url' => rawurlencode($folder) . '/' . rawurlencode($file),
                        'size' => $fileSize ? round($fileSize / 1048576, 1) . ' MB' : '',
                    ];
                }
            }
        }

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

    @file_put_contents($jsonFile, json_encode($outputData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    return $outputData;
}

// Load data from index.json or auto-generate
$indexData = null;
if (file_exists($jsonFile)) {
    $rawJson = @file_get_contents($jsonFile);
    $indexData = @json_decode($rawJson, true);
}

if (!$indexData || !isset($indexData['folders'])) {
    $indexData = generateIndexJson($currentDir, $jsonFile);
}

$foldersWithImages = $indexData['folders'] ?? [];
$folderMap = [];
foreach ($foldersWithImages as $f) {
    $folderMap[$f['rawName']] = $f;
}

// Check reader mode (?baca=folder_name)
$activeFolderParam = isset($_GET['baca']) ? trim($_GET['baca']) : '';
$targetArticleParam = isset($_GET['art']) ? (int)$_GET['art'] : 0;
$activeFolder = null;

if ($activeFolderParam !== '' && isset($folderMap[$activeFolderParam])) {
    $activeFolder = $folderMap[$activeFolderParam];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <title><?= $activeFolder ? htmlspecialchars($activeFolder['displayName']) . ' • Baca Visual Marketing' : '🎀 Marketing Visual Library' ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Quicksand:wght@600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --font-main: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            --font-cute: 'Quicksand', sans-serif;
            --bg-body: #FFFDF9;
            --card-bg: #FFFFFF;
            --text-title: #2B2D42;
            --text-body: #4A5568;
            --text-muted: #718096;
            --border-soft: #F1E5D8;
            --shadow-card: 0 4px 14px rgba(180, 140, 120, 0.06);
            --shadow-hover: 0 12px 24px -4px rgba(255, 101, 132, 0.15), 0 4px 10px rgba(180, 140, 120, 0.06);
            --radius-sm: 10px;
            --radius-md: 16px;
            --radius-lg: 20px;
            --radius-full: 9999px;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        html, body {
            width: 100%;
            min-height: 100%;
            background-color: <?= $activeFolder ? '#000000' : 'var(--bg-body)' ?>;
            font-family: var(--font-main);
            color: var(--text-body);
            -webkit-font-smoothing: antialiased;
        }

        /* ========================================================
           HOME VIEW (LIBRARY LIST & REAL-TIME SEARCH)
           ======================================================== */
        <?php if (!$activeFolder): ?>
        body {
            background-image: 
                radial-gradient(circle at 10% 15%, #FFEBF0 0%, transparent 35%),
                radial-gradient(circle at 90% 20%, #EBF4FF 0%, transparent 40%),
                radial-gradient(circle at 50% 90%, #F5ECFF 0%, transparent 45%);
            background-attachment: fixed;
            line-height: 1.5;
        }

        .decor-item {
            position: fixed;
            pointer-events: none;
            z-index: 0;
            opacity: 0.5;
            user-select: none;
            animation: floatMove 12s ease-in-out infinite alternate;
        }
        @keyframes floatMove {
            0% { transform: translateY(0) rotate(0deg) scale(1); }
            50% { transform: translateY(-16px) rotate(8deg) scale(1.04); }
            100% { transform: translateY(10px) rotate(-6deg) scale(0.96); }
        }

        .container {
            max-width: 1100px;
            margin: 0 auto;
            padding: 2.5rem 1.25rem 4rem;
            position: relative;
            z-index: 1;
        }

        /* HEADER & SEARCH */
        .hero {
            text-align: center;
            margin-bottom: 2rem;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .hero-title {
            font-family: var(--font-cute);
            font-size: clamp(1.8rem, 3.8vw, 2.5rem);
            font-weight: 800;
            color: var(--text-title);
            letter-spacing: -0.02em;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            line-height: 1.2;
            margin-bottom: 1.25rem;
        }

        /* REAL-TIME SEARCH BAR & DROPDOWN */
        .search-wrapper {
            position: relative;
            width: 100%;
            max-width: 480px;
            margin: 0 auto;
        }

        .search-input-box {
            position: relative;
            display: flex;
            align-items: center;
        }

        .search-icon-left {
            position: absolute;
            left: 1.1rem;
            font-size: 1.05rem;
            pointer-events: none;
            color: #A0AEC0;
        }

        .search-input {
            width: 100%;
            padding: 0.72rem 2.5rem 0.72rem 2.85rem;
            background: #FFFFFF;
            border: 1.5px solid #EAD8C7;
            border-radius: var(--radius-full);
            font-family: var(--font-main);
            font-size: 0.92rem;
            color: var(--text-title);
            outline: none;
            box-shadow: 0 4px 14px rgba(180, 140, 120, 0.08);
            transition: all 0.22s ease;
        }

        .search-input:focus {
            border-color: #FF8FAB;
            box-shadow: 0 6px 18px rgba(255, 143, 171, 0.22);
            background: #FFFFFF;
        }

        .search-clear-btn {
            position: absolute;
            right: 1rem;
            background: none;
            border: none;
            color: #A0AEC0;
            cursor: pointer;
            font-size: 0.95rem;
            display: none;
            padding: 0.2rem;
        }

        .search-clear-btn:hover {
            color: var(--text-title);
        }

        /* SEARCH RESULTS DROPDOWN (MAX 3 MATCHES) */
        .search-dropdown {
            position: absolute;
            top: calc(100% + 8px);
            left: 0;
            right: 0;
            background: #FFFFFF;
            border: 1.5px solid #F1E5D8;
            border-radius: var(--radius-md);
            box-shadow: 0 16px 36px rgba(180, 140, 120, 0.18);
            overflow: hidden;
            z-index: 100;
            display: none;
            text-align: left;
            animation: fadeInDown 0.18s ease-out;
        }

        @keyframes fadeInDown {
            from { opacity: 0; transform: translateY(-6px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .search-dropdown.active {
            display: block;
        }

        .dropdown-header {
            padding: 0.5rem 0.9rem;
            background: #FDF9F5;
            border-bottom: 1px solid #F1E5D8;
            font-size: 0.75rem;
            font-weight: 700;
            color: var(--text-muted);
            font-family: var(--font-cute);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .search-result-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            text-decoration: none;
            color: var(--text-title);
            border-bottom: 1px solid #F8EFE6;
            transition: background 0.15s ease;
            cursor: pointer;
        }

        .search-result-item:last-child {
            border-bottom: none;
        }

        .search-result-item:hover, .search-result-item.selected {
            background: #FFF5F8;
        }

        .result-folder-icon {
            font-size: 1.3rem;
            flex-shrink: 0;
        }

        .result-text-block {
            flex: 1;
            min-width: 0;
        }

        .result-title-line {
            font-size: 0.88rem;
            font-weight: 700;
            color: #2B2D42;
            font-family: var(--font-cute);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .result-path-line {
            font-size: 0.78rem;
            color: var(--text-muted);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            margin-top: 0.1rem;
        }

        .result-path-folder {
            color: #FF5A87;
            font-weight: 600;
        }

        .result-arrow {
            font-size: 0.85rem;
            color: #FF8FAB;
            flex-shrink: 0;
            font-weight: 800;
        }

        .match-mark {
            background-color: #FED7E2;
            color: #DB2777;
            padding: 0.05rem 0.2rem;
            border-radius: 4px;
            font-weight: 800;
        }

        .dropdown-empty {
            padding: 1.25rem 1rem;
            text-align: center;
            color: var(--text-muted);
            font-size: 0.85rem;
        }

        /* FOLDERS GRID */
        .folders-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1.25rem;
        }

        .folder-card {
            background: var(--card-bg);
            border-radius: var(--radius-lg);
            border: 1.5px solid var(--border-soft);
            box-shadow: var(--shadow-card);
            padding: 1.25rem;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            gap: 1rem;
            position: relative;
            z-index: 1;
            transition: transform 0.22s cubic-bezier(0.34, 1.56, 0.64, 1), box-shadow 0.22s ease, border-color 0.22s ease;
            color: inherit;
        }

        .folder-card.active-dropdown,
        .folder-card:has(.card-dropdown-menu.show) {
            z-index: 999;
        }

        .folder-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--theme-accent, #FF6584);
            opacity: 0.85;
            border-radius: var(--radius-lg) var(--radius-lg) 0 0;
            transition: height 0.2s ease;
        }

        .folder-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-hover);
            border-color: #F8B4C4;
        }

        .folder-card:hover::before {
            height: 6px;
        }

        .card-top {
            display: flex;
            align-items: center;
            gap: 0.85rem;
        }

        .folder-icon-box {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.6rem;
            flex-shrink: 0;
            transition: transform 0.25s ease;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.03);
        }

        .folder-card:hover .folder-icon-box {
            transform: scale(1.1) rotate(5deg);
        }

        .folder-info {
            flex: 1;
            min-width: 0;
        }

        .number-badge {
            font-family: var(--font-cute);
            font-size: 0.72rem;
            font-weight: 800;
            padding: 0.12rem 0.5rem;
            border-radius: var(--radius-full);
            background: #F3E8FF;
            color: #7C3AED;
            display: inline-block;
            margin-bottom: 0.2rem;
        }

        .folder-title {
            font-family: var(--font-cute);
            font-size: 1.12rem;
            font-weight: 700;
            color: var(--text-title);
            line-height: 1.25;
            word-break: break-word;
        }

        .card-click-area {
            cursor: pointer;
        }

        .card-bottom-row {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding-top: 0.75rem;
            border-top: 1px dashed #F1E5D8;
            position: relative;
        }

        /* CARD DROPDOWN LIST (HOME VIEW) */
        .card-dropdown-wrapper {
            position: relative;
            flex: 1;
        }

        .btn-card-list {
            width: 100%;
            background: #FDF4F6;
            color: #BE123C;
            border: 1.2px solid #FECDD3;
            font-family: var(--font-cute);
            font-size: 0.8rem;
            font-weight: 700;
            padding: 0.45rem 0.75rem;
            border-radius: var(--radius-full);
            display: inline-flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.35rem;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-card-list:hover, .btn-card-list.active {
            background: #FFE4EC;
            border-color: #FF5A87;
            color: #9F1239;
            box-shadow: 0 2px 8px rgba(255, 90, 135, 0.2);
        }

        .btn-card-list .picker-arrow {
            font-size: 0.65rem;
            transition: transform 0.2s ease;
        }

        .btn-card-list.active .picker-arrow {
            transform: rotate(180deg);
        }

        .card-dropdown-menu {
            position: absolute;
            top: calc(100% + 6px);
            left: 0;
            right: 0;
            width: 100%;
            min-width: 270px;
            background: #FFFFFF;
            border: 1.5px solid #F1E5D8;
            border-radius: var(--radius-md);
            box-shadow: 0 14px 36px rgba(180, 140, 120, 0.22);
            z-index: 100;
            display: none;
            overflow: hidden;
            animation: fadeInDown 0.18s ease-out;
        }

        .card-dropdown-menu.show {
            display: block;
        }

        .card-dropdown-header {
            padding: 0.5rem 0.8rem;
            background: #FDF9F5;
            border-bottom: 1px solid #F1E5D8;
            font-size: 0.72rem;
            font-weight: 700;
            color: var(--text-muted);
            font-family: var(--font-cute);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .card-dropdown-list {
            max-height: 175px; /* Maksimal 5 item terlihat sebelum scroll */
            overflow-y: auto;
            overscroll-behavior: contain;
            padding: 0.25rem 0;
        }

        .card-dropdown-list::-webkit-scrollbar {
            width: 5px;
        }
        .card-dropdown-list::-webkit-scrollbar-thumb {
            background: #FFB3C6;
            border-radius: 4px;
        }

        .card-dropdown-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.45rem 0.8rem;
            color: var(--text-title);
            font-size: 0.8rem;
            cursor: pointer;
            transition: background 0.15s ease, color 0.15s ease;
            border-bottom: 1px solid #FAF0E6;
        }

        .card-dropdown-item:last-child {
            border-bottom: none;
        }

        .card-dropdown-item:hover {
            background: #FFF0F5;
            color: #FF4D6D;
        }

        .btn-read {
            background: linear-gradient(135deg, #FF758C 0%, #FF5A87 100%);
            color: #FFFFFF;
            border: none;
            padding: 0.45rem 0.95rem;
            border-radius: var(--radius-full);
            font-family: var(--font-cute);
            font-size: 0.82rem;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            box-shadow: 0 3px 10px rgba(255, 90, 135, 0.22);
            transition: all 0.2s ease;
            text-decoration: none;
            flex-shrink: 0;
        }

        .btn-read:hover {
            background: linear-gradient(135deg, #FF5A87 0%, #E64372 100%);
            transform: translateY(-1px);
            box-shadow: 0 5px 14px rgba(255, 90, 135, 0.32);
        }

        .empty-box {
            grid-column: 1 / -1;
            text-align: center;
            padding: 3.5rem 1.5rem;
            background: #FFFFFF;
            border-radius: var(--radius-lg);
            border: 2px dashed #EBDCCF;
        }

        .empty-icon {
            font-size: 3rem;
            margin-bottom: 0.6rem;
        }

        .empty-title {
            font-family: var(--font-cute);
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--text-title);
        }

        .footer-bar {
            text-align: center;
            margin-top: 3.5rem;
            font-size: 0.82rem;
            color: var(--text-muted);
        }

        .footer-reindex-link {
            color: #FF6584;
            text-decoration: none;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            margin-top: 0.4rem;
            padding: 0.2rem 0.6rem;
            border-radius: var(--radius-full);
            background: #FFF0F5;
            border: 1px solid #FFE4EC;
            transition: all 0.2s ease;
        }

        .footer-reindex-link:hover {
            background: #FF6584;
            color: #FFFFFF;
            border-color: #FF6584;
        }

        @media (max-width: 640px) {
            .container { padding: 1.75rem 1rem 3rem; }
            .folders-grid { grid-template-columns: 1fr; }
        }

        /* ========================================================
           DOOM-SCROLL CONTINUOUS READER (1:3 ASPECT RATIO, 100% WIDTH)
           ======================================================== */
        <?php else: ?>
        body {
            background-color: #000000;
            color: #FFFFFF;
            overflow-x: hidden;
            margin: 0;
            padding: 0;
        }

        .reader-nav {
            position: fixed;
            top: 14px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 9999;
            display: flex;
            align-items: center;
            gap: 0.55rem;
            background: rgba(18, 18, 20, 0.88);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            padding: 0.4rem 0.85rem;
            border-radius: var(--radius-full);
            border: 1px solid rgba(255, 255, 255, 0.15);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.6);
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

        .reader-nav:hover, .reader-nav:focus-within {
            opacity: 1 !important;
            visibility: visible !important;
            pointer-events: auto !important;
            transform: translateX(-50%) translateY(0) !important;
        }

        .nav-fullscreen-btn {
            background: rgba(255, 255, 255, 0.15);
            color: #FFFFFF;
            border: none;
            font-size: 0.92rem;
            padding: 0.28rem 0.55rem;
            border-radius: var(--radius-full);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: background 0.2s ease, transform 0.15s ease;
        }

        .nav-fullscreen-btn:hover {
            background: #FF6584;
            transform: scale(1.08);
        }

        .nav-back-btn {
            background: rgba(255, 255, 255, 0.15);
            color: #FFFFFF;
            text-decoration: none;
            font-family: var(--font-cute);
            font-size: 0.85rem;
            font-weight: 700;
            padding: 0.3rem 0.75rem;
            border-radius: var(--radius-full);
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            transition: background 0.2s ease, transform 0.15s ease;
        }

        .nav-back-btn:hover {
            background: #FF6584;
            transform: scale(1.04);
        }

        .nav-title-text {
            font-family: var(--font-cute);
            font-size: 0.85rem;
            font-weight: 700;
            color: #F3F4F6;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 180px;
        }

        /* DROPDOWN ARTICLE SELECTOR / GOTO LIST */
        .nav-picker-wrapper {
            position: relative;
            display: inline-flex;
            align-items: center;
        }

        .nav-picker-btn {
            background: rgba(255, 101, 132, 0.22);
            color: #FF8FAB;
            border: 1px solid rgba(255, 101, 132, 0.4);
            font-family: var(--font-cute);
            font-size: 0.78rem;
            font-weight: 800;
            padding: 0.25rem 0.65rem;
            border-radius: var(--radius-full);
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            cursor: pointer;
            white-space: nowrap;
            transition: all 0.2s ease;
        }

        .nav-picker-btn:hover, .nav-picker-btn.active {
            background: #FF5A87;
            color: #FFFFFF;
            border-color: #FF5A87;
            box-shadow: 0 0 12px rgba(255, 90, 135, 0.5);
        }

        .nav-picker-btn .picker-arrow {
            font-size: 0.68rem;
            transition: transform 0.2s ease;
        }

        .nav-picker-btn.active .picker-arrow {
            transform: rotate(180deg);
        }

        .nav-picker-dropdown {
            position: absolute;
            top: calc(100% + 10px);
            right: 0;
            width: 300px;
            max-width: 88vw;
            background: rgba(18, 18, 22, 0.96);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1.5px solid rgba(255, 101, 132, 0.35);
            border-radius: var(--radius-md);
            box-shadow: 0 16px 40px rgba(0, 0, 0, 0.75), 0 0 24px rgba(255, 90, 135, 0.18);
            overflow: hidden;
            z-index: 10001;
            display: none;
            animation: fadeInDown 0.18s ease-out;
        }

        .nav-picker-dropdown.show {
            display: block;
        }

        .nav-picker-header {
            padding: 0.55rem 0.85rem;
            background: rgba(255, 255, 255, 0.05);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            font-size: 0.72rem;
            font-weight: 700;
            color: #9CA3AF;
            font-family: var(--font-cute);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .picker-total-badge {
            background: rgba(255, 101, 132, 0.25);
            color: #FF8FAB;
            padding: 0.1rem 0.45rem;
            border-radius: var(--radius-full);
            font-size: 0.68rem;
        }

        .nav-picker-list {
            max-height: 175px; /* Maksimal 5 item terlihat sebelum scroll */
            overflow-y: auto;
            overscroll-behavior: contain;
            padding: 0.3rem 0;
        }

        /* Custom sleek scrollbar for dropdown */
        .nav-picker-list::-webkit-scrollbar {
            width: 6px;
        }
        .nav-picker-list::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.02);
        }
        .nav-picker-list::-webkit-scrollbar-thumb {
            background: rgba(255, 101, 132, 0.4);
            border-radius: 4px;
        }
        .nav-picker-list::-webkit-scrollbar-thumb:hover {
            background: #FF5A87;
        }

        .nav-picker-item {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            padding: 0.48rem 0.85rem;
            color: #E5E7EB;
            font-size: 0.8rem;
            text-decoration: none;
            cursor: pointer;
            transition: background 0.15s ease, color 0.15s ease;
            border-left: 3px solid transparent;
        }

        .nav-picker-item:hover {
            background: rgba(255, 101, 132, 0.15);
            color: #FFFFFF;
        }

        .nav-picker-item.active {
            background: rgba(255, 90, 135, 0.22);
            color: #FF8FAB;
            border-left-color: #FF5A87;
            font-weight: 700;
        }

        .picker-item-num {
            font-family: var(--font-cute);
            font-weight: 800;
            font-size: 0.72rem;
            color: #FF8FAB;
            background: rgba(255, 255, 255, 0.08);
            padding: 0.1rem 0.4rem;
            border-radius: 6px;
            flex-shrink: 0;
        }

        .nav-picker-item.active .picker-item-num {
            background: #FF5A87;
            color: #FFFFFF;
        }

        .picker-item-title {
            flex: 1;
            min-width: 0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            font-family: var(--font-main);
            line-height: 1.3;
        }

        .restore-toast {
            position: fixed;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%) translateY(30px);
            background: rgba(30, 30, 36, 0.92);
            color: #FFFFFF;
            border: 1px solid #FF6584;
            padding: 0.55rem 1.25rem;
            border-radius: var(--radius-full);
            font-family: var(--font-cute);
            font-size: 0.85rem;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            box-shadow: 0 8px 24px rgba(255, 101, 132, 0.25);
            z-index: 10000;
            opacity: 0;
            pointer-events: none;
            transition: all 0.35s cubic-bezier(0.34, 1.56, 0.64, 1);
            backdrop-filter: blur(8px);
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
            background-color: #000000;
        }

        .article-frame {
            width: 100%;
            display: block;
            margin: 0;
            padding: 0;
            border-bottom: 1px solid #000000;
            background-color: #080808;
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
            transition: opacity 0.28s ease-in-out;
        }

        .article-img.is-loaded {
            opacity: 1;
        }

        .article-frame::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(180deg, #111114 0%, #1a1a1f 50%, #111114 100%);
            z-index: 1;
            pointer-events: none;
            opacity: 1;
            transition: opacity 0.3s ease;
        }

        .article-frame.loaded::after {
            opacity: 0;
            display: none;
        }

        .feed-footer {
            background-color: #0d0d11;
            padding: 4rem 1.5rem 6rem;
            text-align: center;
            color: #9CA3AF;
        }

        .finish-badge {
            font-size: 2.2rem;
            margin-bottom: 0.75rem;
            display: inline-block;
        }

        .finish-title {
            font-family: var(--font-cute);
            font-size: 1.35rem;
            font-weight: 800;
            color: #FFFFFF;
            margin-bottom: 0.4rem;
        }

        .finish-sub {
            font-size: 0.9rem;
            color: #8E8EA0;
            margin-bottom: 1.75rem;
        }

        .btn-finish-back {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: linear-gradient(135deg, #FF758C 0%, #FF5A87 100%);
            color: #FFFFFF;
            text-decoration: none;
            font-family: var(--font-cute);
            font-size: 0.95rem;
            font-weight: 700;
            padding: 0.75rem 1.75rem;
            border-radius: var(--radius-full);
            box-shadow: 0 6px 18px rgba(255, 90, 135, 0.35);
            transition: transform 0.2s ease;
        }

        .btn-finish-back:hover {
            transform: translateY(-2px);
        }

        .btn-scroll-top {
            position: fixed;
            bottom: 24px;
            right: 20px;
            z-index: 9999;
            background: rgba(255, 255, 255, 0.9);
            color: #1F2937;
            border: none;
            width: 44px;
            height: 44px;
            border-radius: var(--radius-full);
            font-size: 1.25rem;
            font-weight: bold;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.4);
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.25s ease, transform 0.2s ease;
        }

        .btn-scroll-top.visible {
            opacity: 1;
            pointer-events: auto;
        }

        .btn-scroll-top:hover {
            background: #FF6584;
            color: #FFFFFF;
            transform: scale(1.1);
        }
        <?php endif; ?>
    </style>
</head>
<body>

<?php if (!$activeFolder): ?>
    <!-- ========================================================
         HOME VIEW (LIBRARY LIST & REAL-TIME SEARCH)
         ======================================================== -->
    <div class="decor-item" style="top: 8%; left: 4%; font-size: 2.2rem;">🌸</div>
    <div class="decor-item" style="top: 18%; right: 5%; font-size: 2rem; animation-delay: -2s;">✨</div>
    <div class="decor-item" style="bottom: 12%; left: 5%; font-size: 2rem; animation-delay: -5s;">🧁</div>
    <div class="decor-item" style="bottom: 22%; right: 6%; font-size: 2.2rem; animation-delay: -8s;">🎨</div>

    <div class="container">
        <header class="hero">
            <h1 class="hero-title">🎀 Marketing Visual Library</h1>

            <!-- REAL-TIME SEARCH INPUT WITH DROPDOWN (MAX 3 MATCHES) -->
            <div class="search-wrapper" id="searchWrapper">
                <div class="search-input-box">
                    <span class="search-icon-left">🔍</span>
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

                <!-- Preview Dropdown -->
                <div class="search-dropdown" id="searchDropdown">
                    <div class="dropdown-header">
                        <span>Hasil Pencarian (Max 3):</span>
                        <span id="searchMatchCount">0 cocok</span>
                    </div>
                    <div id="searchResultList"></div>
                </div>
            </div>
        </header>
        <main class="folders-grid">
            <?php if (empty($foldersWithImages)): ?>
                <div class="empty-box">
                    <div class="empty-icon">📂🌸</div>
                    <h3 class="empty-title">Belum Ada Artikel</h3>
                </div>
            <?php else: ?>
                <?php foreach ($foldersWithImages as $idx => $folder): ?>
                    <div 
                        class="folder-card" 
                        data-rawname="<?= htmlspecialchars($folder['rawName']) ?>"
                        style="--theme-accent: <?= $folder['theme']['accent'] ?>;"
                    >
                        <div class="card-click-area" onclick="location.href='?baca=<?= rawurlencode($folder['rawName']) ?>';">
                            <div class="card-top">
                                <div class="folder-icon-box" style="background-color: <?= $folder['theme']['bg'] ?>; border: 1.5px solid <?= $folder['theme']['border'] ?>;">
                                    <?= $folder['theme']['icon'] ?>
                                </div>
                                <div class="folder-info">
                                    <?php if (!empty($folder['number'])): ?>
                                        <span class="number-badge">#<?= htmlspecialchars($folder['number']) ?></span>
                                    <?php endif; ?>
                                    <h2 class="folder-title"><?= htmlspecialchars($folder['displayName']) ?></h2>
                                </div>
                            </div>
                        </div>

                        <div class="card-bottom-row">
                            <!-- Dropdown List di Home Card -->
                            <div class="card-dropdown-wrapper">
                                <button type="button" class="btn-card-list" onclick="event.stopPropagation(); toggleCardDropdown(this);">
                                    <span>📑 Daftar Artikel (<?= $folder['imageCount'] ?>)</span>
                                    <span class="picker-arrow">▾</span>
                                </button>
                                <div class="card-dropdown-menu">
                                    <div class="card-dropdown-header">
                                        <span><?= htmlspecialchars($folder['displayName']) ?></span>
                                        <span><?= $folder['imageCount'] ?> Item</span>
                                    </div>
                                    <div class="card-dropdown-list">
                                        <?php foreach ($folder['images'] as $imgIdx => $img): ?>
                                            <div class="card-dropdown-item" onclick="event.stopPropagation(); location.href='?baca=<?= rawurlencode($folder['rawName']) ?>&art=<?= $imgIdx + 1 ?>';">
                                                <span class="picker-item-num">#<?= $imgIdx + 1 ?></span>
                                                <span class="picker-item-title"><?= htmlspecialchars($img['cleanTitle'] ?: $img['name']) ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>

                            <a href="?baca=<?= rawurlencode($folder['rawName']) ?>" class="btn-read">
                                <span>📖 Baca</span>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </main>

        <footer class="footer-bar">
            <p>Marketing Visual Vault 🌸 • Cache Aktif (index.json)</p>
            <a href="reindex.php" class="footer-reindex-link" title="Perbarui index.json jika ada folder/file baru">
                <span>🔄</span> Perbarui Index (reindex.php)
            </a>
        </footer>
    </div>

    <script>
        window.toggleCardDropdown = function(btn) {
            const card = btn.closest('.folder-card');
            const menu = btn.nextElementSibling;
            const isShown = menu.classList.contains('show');
            document.querySelectorAll('.card-dropdown-menu.show').forEach(m => m.classList.remove('show'));
            document.querySelectorAll('.btn-card-list.active').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.folder-card.active-dropdown').forEach(c => c.classList.remove('active-dropdown'));

            if (!isShown) {
                menu.classList.add('show');
                btn.classList.add('active');
                if (card) card.classList.add('active-dropdown');
            }
        };

        document.addEventListener('click', (e) => {
            if (!e.target.closest('.card-dropdown-wrapper')) {
                document.querySelectorAll('.card-dropdown-menu.show').forEach(m => m.classList.remove('show'));
                document.querySelectorAll('.btn-card-list.active').forEach(b => b.classList.remove('active'));
                document.querySelectorAll('.folder-card.active-dropdown').forEach(c => c.classList.remove('active-dropdown'));
            }
        });

        // Data injected from index.json for 0ms client-side search
        const libraryData = <?= json_encode($foldersWithImages, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;

        // Flatten searchable article items
        const searchableArticles = [];
        libraryData.forEach(folder => {
            folder.images.forEach((img, idx) => {
                searchableArticles.push({
                    folderRaw: folder.rawName,
                    folderName: folder.displayName,
                    folderIcon: folder.theme ? folder.theme.icon : '📁',
                    fileName: img.name,
                    cleanTitle: img.cleanTitle || img.name,
                    articleIndex: idx + 1,
                    url: `?baca=${encodeURIComponent(folder.rawName)}&art=${idx + 1}`
                });
            });
        });

        const searchInput = document.getElementById('globalSearchInput');
        const clearBtn = document.getElementById('clearSearchBtn');
        const dropdown = document.getElementById('searchDropdown');
        const resultList = document.getElementById('searchResultList');
        const matchCountEl = document.getElementById('searchMatchCount');
        const searchWrapper = document.getElementById('searchWrapper');

        let selectedResultIndex = -1;

        // Highlight matching query helper
        function highlightMatch(text, query) {
            if (!query) return escapeHtml(text);
            const regex = new RegExp(`(${escapeRegex(query)})`, 'gi');
            return escapeHtml(text).replace(regex, '<mark class="match-mark">$1</mark>');
        }

        function escapeHtml(str) {
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }

        function escapeRegex(str) {
            return str.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        }

        function performSearch() {
            const query = searchInput.value.trim().toLowerCase();
            clearBtn.style.display = query ? 'block' : 'none';

            if (!query) {
                dropdown.classList.remove('active');
                resultList.innerHTML = '';
                selectedResultIndex = -1;
                return;
            }

            // Filter matches on fileName, cleanTitle, or folderName
            const matches = searchableArticles.filter(item => {
                return item.fileName.toLowerCase().includes(query) ||
                       item.cleanTitle.toLowerCase().includes(query) ||
                       item.folderName.toLowerCase().includes(query);
            });

            // Limit to exactly max 3 matches
            const topMatches = matches.slice(0, 3);
            selectedResultIndex = -1;

            if (topMatches.length === 0) {
                matchCountEl.textContent = '0 cocok';
                resultList.innerHTML = `<div class="dropdown-empty">Tidak ada artikel yang cocok dengan "<strong>${escapeHtml(query)}</strong>" 🌸</div>`;
            } else {
                matchCountEl.textContent = `${matches.length} ditemukan (menampilkan ${topMatches.length})`;
                resultList.innerHTML = topMatches.map((item, i) => `
                    <a href="${item.url}" class="search-result-item" data-index="${i}">
                        <div class="result-folder-icon">${item.folderIcon}</div>
                        <div class="result-text-block">
                            <div class="result-title-line">
                                <span class="result-path-folder">${highlightMatch(item.folderName, query)}</span>
                                <span style="color: #A0AEC0; margin: 0 0.25rem;">/</span>
                                <span>${highlightMatch(item.fileName, query)}</span>
                            </div>
                            <div class="result-path-line">
                                <span>📄 Artikel #${item.articleIndex}</span>
                            </div>
                        </div>
                        <span class="result-arrow">→</span>
                    </a>
                `).join('');
            }

            dropdown.classList.add('active');
        }

        searchInput.addEventListener('input', performSearch);
        searchInput.addEventListener('focus', () => {
            if (searchInput.value.trim()) dropdown.classList.add('active');
        });

        clearBtn.addEventListener('click', () => {
            searchInput.value = '';
            dropdown.classList.remove('active');
            clearBtn.style.display = 'none';
            searchInput.focus();
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', (e) => {
            if (!searchWrapper.contains(e.target)) {
                dropdown.classList.remove('active');
            }
        });

        // Keyboard navigation (ArrowDown, ArrowUp, Enter, Escape)
        searchInput.addEventListener('keydown', (e) => {
            const items = dropdown.querySelectorAll('.search-result-item');
            if (e.key === 'Escape') {
                dropdown.classList.remove('active');
            } else if (e.key === 'ArrowDown') {
                if (items.length > 0) {
                    e.preventDefault();
                    selectedResultIndex = (selectedResultIndex + 1) % items.length;
                    updateSelectedDropdownItem(items);
                }
            } else if (e.key === 'ArrowUp') {
                if (items.length > 0) {
                    e.preventDefault();
                    selectedResultIndex = (selectedResultIndex - 1 + items.length) % items.length;
                    updateSelectedDropdownItem(items);
                }
            } else if (e.key === 'Enter') {
                if (selectedResultIndex >= 0 && items[selectedResultIndex]) {
                    e.preventDefault();
                    items[selectedResultIndex].click();
                } else if (items.length > 0) {
                    e.preventDefault();
                    items[0].click();
                }
            }
        });

        function updateSelectedDropdownItem(items) {
            items.forEach((item, idx) => {
                item.classList.toggle('selected', idx === selectedResultIndex);
            });
        }
    </script>

<?php else: ?>
    <!-- ========================================================
         DOOM-SCROLL CONTINUOUS VERTICAL READER (1:3 ASPECT RATIO)
         ======================================================== -->
    
    <!-- Floating Minimal Top Navigation -->
    <nav class="reader-nav" id="readerNav">
        <a href="index.php" class="nav-back-btn">
            <span>‹</span>
            <span>Kembali</span>
        </a>
        <span class="nav-title-text"><?= htmlspecialchars($activeFolder['displayName']) ?></span>

        <!-- DROPDOWN ARTICLE SELECTOR / GOTO -->
        <div class="nav-picker-wrapper" id="navPickerWrapper">
            <button type="button" class="nav-picker-btn" id="navPickerBtn" title="Pilih artikel untuk langsung lompat (Goto)">
                <span>📄</span>
                <span class="picker-label" id="navCounter">Art. 1 / <?= $activeFolder['imageCount'] ?></span>
                <span class="picker-arrow">▾</span>
            </button>
            <div class="nav-picker-dropdown" id="navPickerDropdown">
                <div class="nav-picker-header">
                    <span>Daftar Artikel (Lompat Cepat)</span>
                    <span class="picker-total-badge" id="pickerTotalBadge"><?= $activeFolder['imageCount'] ?> Item</span>
                </div>
                <div class="nav-picker-list" id="navPickerList">
                    <?php foreach ($activeFolder['images'] as $idx => $img): ?>
                        <div class="nav-picker-item <?= $idx === 0 ? 'active' : '' ?>" data-art="<?= $idx + 1 ?>">
                            <span class="picker-item-num">#<?= $idx + 1 ?></span>
                            <span class="picker-item-title" title="<?= htmlspecialchars($img['cleanTitle'] ?: $img['name']) ?>"><?= htmlspecialchars($img['cleanTitle'] ?: $img['name']) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- FULLSCREEN BUTTON -->
        <button type="button" class="nav-fullscreen-btn" id="navFullscreenBtn" title="Mode Layar Penuh (Fullscreen)">
            <span id="fullscreenIcon">⛶</span>
        </button>
    </nav>

    <!-- Restored position toast -->
    <div class="restore-toast" id="restoreToast">
        <span>📍</span>
        <span id="restoreToastText">Melanjutkan posisi membaca</span>
    </div>

    <!-- Continuous 100% Full-Width Doom-Scroll Feed -->
    <main class="doom-feed" id="doomFeed">
        <?php foreach ($activeFolder['images'] as $i => $img): ?>
            <div class="article-frame" id="art-<?= $i + 1 ?>" data-index="<?= $i + 1 ?>" data-src="<?= htmlspecialchars($img['url']) ?>">
                <!-- High-performance Lazy Loaded Image -->
                <img 
                    class="article-img lazy-img" 
                    src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 1 3'%3E%3C/svg%3E" 
                    data-src="<?= htmlspecialchars($img['url']) ?>" 
                    alt="<?= htmlspecialchars($img['name']) ?>" 
                    loading="lazy"
                    decoding="async"
                >
            </div>
        <?php endforeach; ?>
    </main>

    <!-- Bottom Finish Section -->
    <footer class="feed-footer">
        <div class="finish-badge">🎉✨</div>
        <h3 class="finish-title">Semua Artikel Selesai Dibaca!</h3>
        <p class="finish-sub">Total <?= $activeFolder['imageCount'] ?> artikel visual di folder ini telah ditampilkan.</p>
        <a href="index.php" class="btn-finish-back">
            <span>🌸</span>
            <span>Kembali ke Library</span>
        </a>
    </footer>

    <!-- Floating Scroll To Top Button -->
    <button type="button" class="btn-scroll-top" id="btnScrollTop" title="Kembali ke atas" onclick="window.scrollTo({top: 0, behavior: 'smooth'})">
        ↑
    </button>

    <script>
        const currentFolderKey = 'mkt_pos_<?= addslashes($activeFolder['rawName']) ?>';
        const totalArticles = <?= (int)$activeFolder['imageCount'] ?>;
        const targetArticleParam = <?= (int)$targetArticleParam ?>;

        function setCookie(name, value, days = 365) {
            const d = new Date();
            d.setTime(d.getTime() + (days * 24 * 60 * 60 * 1000));
            const expires = "expires=" + d.toUTCString();
            document.cookie = encodeURIComponent(name) + "=" + encodeURIComponent(value) + ";" + expires + ";path=/;SameSite=Lax";
            try { localStorage.setItem(name, value); } catch (e) {}
        }

        function getCookie(name) {
            const cname = encodeURIComponent(name) + "=";
            const decodedCookie = decodeURIComponent(document.cookie);
            const ca = decodedCookie.split(';');
            for (let i = 0; i < ca.length; i++) {
                let c = ca[i].trim();
                if (c.indexOf(cname) === 0) return c.substring(cname.length, c.length);
            }
            try {
                const ls = localStorage.getItem(name);
                if (ls) return ls;
            } catch(e) {}
            return "";
        }

        document.addEventListener('DOMContentLoaded', () => {
            const lazyImages = document.querySelectorAll('.lazy-img');
            const frames = document.querySelectorAll('.article-frame');
            const nav = document.getElementById('readerNav');
            const navCounter = document.getElementById('navCounter');
            const navPickerBtn = document.getElementById('navPickerBtn');
            const navPickerDropdown = document.getElementById('navPickerDropdown');
            const navPickerList = document.getElementById('navPickerList');
            const navPickerWrapper = document.getElementById('navPickerWrapper');
            const btnScrollTop = document.getElementById('btnScrollTop');
            const restoreToast = document.getElementById('restoreToast');
            const restoreToastText = document.getElementById('restoreToastText');

            let currentActiveArtIdx = 1;

            // 1. Lazy Loader
            if ('IntersectionObserver' in window) {
                const imageObserver = new IntersectionObserver((entries, observer) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            const img = entry.target;
                            const frame = img.closest('.article-frame');
                            const realSrc = img.getAttribute('data-src');

                            if (realSrc) {
                                img.src = realSrc;
                                img.onload = () => {
                                    img.classList.add('is-loaded');
                                    if (frame) frame.classList.add('loaded');
                                };
                            }

                            observer.unobserve(img);
                        }
                    });
                }, {
                    root: null,
                    rootMargin: '800px 0px 800px 0px',
                    threshold: 0.01
                });

                lazyImages.forEach(img => imageObserver.observe(img));
            } else {
                lazyImages.forEach(img => {
                    img.src = img.getAttribute('data-src');
                    img.onload = () => {
                        img.classList.add('is-loaded');
                        img.closest('.article-frame')?.classList.add('loaded');
                    };
                });
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

            if (navPickerBtn) {
                navPickerBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    togglePickerDropdown();
                });
            }

            document.addEventListener('click', (e) => {
                if (navPickerWrapper && !navPickerWrapper.contains(e.target)) {
                    closePickerDropdown();
                }
            });

            function updateActiveArticleState(artIdx) {
                currentActiveArtIdx = artIdx;
                if (navCounter) {
                    navCounter.textContent = `Art. ${artIdx} / ${totalArticles}`;
                }
                if (navPickerList) {
                    const items = navPickerList.querySelectorAll('.nav-picker-item');
                    items.forEach(item => {
                        const itemIdx = parseInt(item.getAttribute('data-art'), 10);
                        item.classList.toggle('active', itemIdx === artIdx);
                    });
                }
            }

            function jumpToArticle(artIdx, smooth = true) {
                closePickerDropdown();
                const targetEl = document.getElementById('art-' + artIdx);
                if (targetEl) {
                    targetEl.scrollIntoView({ behavior: smooth ? 'smooth' : 'instant' });
                    updateActiveArticleState(artIdx);
                    restoreToastText.textContent = `Lompat ke Artikel #${artIdx} of ${totalArticles}`;
                    restoreToast.classList.add('show');
                    setTimeout(() => restoreToast.classList.remove('show'), 2400);
                }
            }

            if (navPickerList) {
                navPickerList.addEventListener('click', (e) => {
                    const item = e.target.closest('.nav-picker-item');
                    if (item) {
                        const artIdx = parseInt(item.getAttribute('data-art'), 10);
                        jumpToArticle(artIdx, true);
                    }
                });
            }

            // 2. Position Restoration (Target Article Param from Search OR Cookie Position)
            setTimeout(() => {
                if (targetArticleParam > 0) {
                    jumpToArticle(targetArticleParam, false);
                } else {
                    // Restore from Cookie
                    const savedPositionRaw = getCookie(currentFolderKey);
                    if (savedPositionRaw) {
                        try {
                            const savedPos = JSON.parse(savedPositionRaw);
                            if (savedPos && (savedPos.scrollY > 80 || savedPos.artIdx > 1)) {
                                if (savedPos.scrollY) {
                                    window.scrollTo({ top: savedPos.scrollY, behavior: 'instant' });
                                } else if (savedPos.artIdx) {
                                    const targetEl = document.getElementById('art-' + savedPos.artIdx);
                                    if (targetEl) targetEl.scrollIntoView({ behavior: 'instant' });
                                }

                                if (savedPos.artIdx) {
                                    updateActiveArticleState(savedPos.artIdx);
                                    restoreToastText.textContent = `Melanjutkan dari Artikel #${savedPos.artIdx} of ${totalArticles}`;
                                    restoreToast.classList.add('show');
                                    setTimeout(() => restoreToast.classList.remove('show'), 2800);
                                }
                            }
                        } catch (e) {}
                    }
                }
            }, 60);

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

            // 3. Save Reading Position on Scroll
            let saveTimeout = null;
            let navShowTimeout = null;
            let lastScrollY = window.scrollY;

            function calculateActiveArticleAndSave() {
                const scrollY = window.scrollY;
                const viewportCenter = scrollY + (window.innerHeight / 2);

                let currentArtIdx = 1;
                frames.forEach((frame) => {
                    const top = frame.offsetTop;
                    const height = frame.offsetHeight;
                    if (viewportCenter >= top && viewportCenter < (top + height)) {
                        currentArtIdx = parseInt(frame.getAttribute('data-index'), 10) || 1;
                    }
                });

                updateActiveArticleState(currentArtIdx);

                const posData = {
                    scrollY: Math.round(scrollY),
                    artIdx: currentArtIdx,
                    total: totalArticles,
                    ts: Date.now()
                };

                setCookie(currentFolderKey, JSON.stringify(posData), 365);
            }

            window.addEventListener('scroll', () => {
                const currentScrollY = window.scrollY;
                
                if (currentScrollY > 600) {
                    btnScrollTop.classList.add('visible');
                } else {
                    btnScrollTop.classList.remove('visible');
                }

                // Saat scroll turun: langsung sembunyikan 100% (opacity 0)
                if (currentScrollY > 100 && currentScrollY > lastScrollY && !navPickerDropdown.classList.contains('show')) {
                    if (navShowTimeout) {
                        clearTimeout(navShowTimeout);
                        navShowTimeout = null;
                    }
                    nav.classList.add('nav-hidden');
                } else if (currentScrollY < lastScrollY && !navPickerDropdown.classList.contains('show')) {
                    // Saat scroll naik:
                    if (currentScrollY < 100) {
                        // Di paling atas layar, munculkan langsung
                        if (navShowTimeout) {
                            clearTimeout(navShowTimeout);
                            navShowTimeout = null;
                        }
                        nav.classList.remove('nav-hidden');
                    } else if (nav.classList.contains('nav-hidden') && !navShowTimeout) {
                        // Beri jeda 3 detik saat scroll naik sebelum memunculkan navbar kembali
                        navShowTimeout = setTimeout(() => {
                            nav.classList.remove('nav-hidden');
                            navShowTimeout = null;
                        }, 3000);
                    }
                }

                lastScrollY = currentScrollY;

                if (saveTimeout) clearTimeout(saveTimeout);
                saveTimeout = setTimeout(calculateActiveArticleAndSave, 150);
            }, { passive: true });

            // Munculkan navbar jika kursor digerakkan ke bagian atas layar (< 50px)
            document.addEventListener('mousemove', (e) => {
                if (e.clientY < 50) {
                    if (navShowTimeout) {
                        clearTimeout(navShowTimeout);
                        navShowTimeout = null;
                    }
                    nav.classList.remove('nav-hidden');
                }
            });

            window.addEventListener('pagehide', calculateActiveArticleAndSave);
            window.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'hidden') calculateActiveArticleAndSave();
            });
        });
    </script>
<?php endif; ?>

</body>
</html>
