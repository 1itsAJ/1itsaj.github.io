<?php
// Prevent PHP errors/warnings from leaking into the generated HTML file
error_reporting(0);
ini_set('display_errors', '0');

// --- DYNAMIC PRESS GALLERY GENERATOR ---

ini_set('auto_detect_line_endings', TRUE);

// HELPER: Bulletproof ID matching engine. 
if (!function_exists('normalizeId')) {
    function normalizeId($string) {
        $string = pathinfo($string, PATHINFO_FILENAME);
        return str_replace(['-', '_', ' ', "'", '’', '"'], '', mb_strtolower(trim($string), 'UTF-8'));
    }
}

// Dedicated Press Parser (Detects Title, Description, Summary, Date, and Newspaper columns)
function parsePressCsv($csvPath) {
    $data = ['info' => [], 'titles' => [], 'summaries' => [], 'dates' => [], 'newspapers' => []];
    if (file_exists($csvPath)) {
        $firstLine = @file_get_contents($csvPath, false, null, 0, 500);
        $delimiter = (strpos($firstLine, ';') !== false) ? ';' : ',';

        if (($handle = @fopen($csvPath, "r")) !== FALSE) {
            $headers = fgetcsv($handle, 10000, $delimiter);
            if ($headers) {
                $idIndex = 0; // Default ID is col A
                $titleIndex = -1;
                $descIndex = -1;
                $summaryIndex = -1;
                $dateIndex = -1;
                $newspaperIndex = -1;

                foreach ($headers as $k => $v) {
                    $val = strtolower(trim($v));
                    if ($val === 'title') $titleIndex = $k;
                    elseif (in_array($val, ['description', 'info', 'text', 'desc'])) $descIndex = $k;
                    elseif (strpos($val, 'summar') !== false || strpos($val, 'summer') !== false) $summaryIndex = $k;
                    elseif (strpos($val, 'date') !== false || strpos($val, 'year') !== false) $dateIndex = $k;
                    elseif (in_array($val, ['newspaper', 'source', 'publication', 'magazine', 'publisher'])) $newspaperIndex = $k;
                }

                if ($titleIndex === -1) $titleIndex = 1; 
                if ($descIndex === -1) $descIndex = ($titleIndex === 1) ? 2 : 1;

                while (($row = fgetcsv($handle, 10000, $delimiter)) !== FALSE) {
                    $idCol = isset($row[$idIndex]) ? $row[$idIndex] : '';
                    if (!empty($idCol)) {
                        $key = normalizeId($idCol);
                        
                        if (isset($row[$titleIndex]) && trim($row[$titleIndex]) !== '') {
                            $data['titles'][$key] = trim($row[$titleIndex]);
                        }
                        if (isset($row[$descIndex]) && trim($row[$descIndex]) !== '') {
                            $data['info'][$key] = trim($row[$descIndex]);
                        }
                        if ($summaryIndex !== -1 && isset($row[$summaryIndex]) && trim($row[$summaryIndex]) !== '') {
                            $data['summaries'][$key] = trim($row[$summaryIndex]);
                        }
                        if ($dateIndex !== -1 && isset($row[$dateIndex]) && trim($row[$dateIndex]) !== '') {
                            $data['dates'][$key] = trim($row[$dateIndex]);
                        }
                        if ($newspaperIndex !== -1 && isset($row[$newspaperIndex]) && trim($row[$newspaperIndex]) !== '') {
                            $data['newspapers'][$key] = trim($row[$newspaperIndex]);
                        }
                    }
                }
            }
            fclose($handle);
        }
    }
    return $data;
}

function getPressItems() {
    $items = [];
    $baseDir = __DIR__;
    $infoDir = $baseDir . DIRECTORY_SEPARATOR . 'info';
    
    // Determine exact CV directory name handling both "press" and "prass" typos
    $cvDirName = 'hayan C-V & prass';
    if (is_dir($baseDir . DIRECTORY_SEPARATOR . 'hayan C-V & press')) {
        $cvDirName = 'hayan C-V & press';
    }

    $pressData = parsePressCsv($infoDir . DIRECTORY_SEPARATOR . 'press.csv');
    $pressInfo = $pressData['info'];
    $pressTitles = $pressData['titles'];
    $pressSummaries = $pressData['summaries'];
    $pressDates = $pressData['dates'];
    $pressNewspapers = $pressData['newspapers'];

    $pressPath = $baseDir . DIRECTORY_SEPARATOR . $cvDirName . DIRECTORY_SEPARATOR . 'press';

    if (is_dir($pressPath)) {
        $iterator = new DirectoryIterator($pressPath);
        $pressSingleItems = [];
        $pressMultiPageMap = [];

        foreach ($iterator as $fileInfo) {
            if ($fileInfo->isDot()) continue;

            if ($fileInfo->isDir()) {
                $dirPath = $fileInfo->getPathname();
                $dirName = $fileInfo->getFilename();
                $subImages = [];
                
                $subIterator = new DirectoryIterator($dirPath);
                foreach ($subIterator as $subFile) {
                    if ($subFile->isFile() && in_array(strtolower($subFile->getExtension()), ['jpg', 'jpeg', 'png', 'gif'])) {
                        $subImages[] = $subFile->getPathname();
                    }
                }
                if (!empty($subImages)) {
                    $pressMultiPageMap[$dirName] = $subImages;
                }
            } elseif ($fileInfo->isFile() && in_array(strtolower($fileInfo->getExtension()), ['jpg', 'jpeg', 'png', 'gif'])) {
                $pressSingleItems[] = $fileInfo->getPathname();
            }
        }

        // Process single press items (Articles)
        foreach ($pressSingleItems as $path) {
            $fileNameWithoutExt = pathinfo($path, PATHINFO_FILENAME);
            $lookupKey = normalizeId(basename($path));
            
            $title = isset($pressTitles[$lookupKey]) ? $pressTitles[$lookupKey] : ucwords(str_replace(['-', '_'], ' ', $fileNameWithoutExt));
            $rawText = isset($pressInfo[$lookupKey]) ? $pressInfo[$lookupKey] : '';
            $summaryText = isset($pressSummaries[$lookupKey]) ? $pressSummaries[$lookupKey] : '';
            $dateText = isset($pressDates[$lookupKey]) ? $pressDates[$lookupKey] : '';
            $newspaperText = isset($pressNewspapers[$lookupKey]) ? $pressNewspapers[$lookupKey] : 'Newspaper';

            $relativePath = substr($path, strlen($baseDir) + 1);
            $webUrl = str_replace('\\', '/', $relativePath);
            $encodedUrl = implode('/', array_map('rawurlencode', explode('/', $webUrl)));

            $items[] = [
                'url' => $encodedUrl,
                'title' => $title,
                'category' => 'press',
                'subcat' => '', 
                'raw_text' => $rawText,
                'summary' => $summaryText,
                'date' => $dateText,
                'newspaper' => $newspaperText
            ];
        }

        // Process multi-page press items (Magazines)
        foreach ($pressMultiPageMap as $dirName => $images) {
            $lookupKey = normalizeId($dirName);
            
            $title = isset($pressTitles[$lookupKey]) ? $pressTitles[$lookupKey] : ucwords(str_replace(['-', '_'], ' ', $dirName));
            $rawText = isset($pressInfo[$lookupKey]) ? $pressInfo[$lookupKey] : '';
            $summaryText = isset($pressSummaries[$lookupKey]) ? $pressSummaries[$lookupKey] : '';
            $dateText = isset($pressDates[$lookupKey]) ? $pressDates[$lookupKey] : '';
            $newspaperText = isset($pressNewspapers[$lookupKey]) ? $pressNewspapers[$lookupKey] : 'Newspaper';

            usort($images, function($a, $b) {
                return strnatcasecmp(basename($a), basename($b));
            });

            $pages = [];
            foreach ($images as $path) {
                $relativePath = substr($path, strlen($baseDir) + 1);
                $webUrl = str_replace('\\', '/', $relativePath);
                $encodedUrl = implode('/', array_map('rawurlencode', explode('/', $webUrl)));
                $pages[] = $encodedUrl;
            }

            if (count($pages) > 0) {
                $items[] = [
                    'url' => $pages[0],
                    'title' => $title,
                    'category' => 'press',
                    'subcat' => '', 
                    'is_book' => true,
                    'book_contents' => json_encode($pages),
                    'raw_text' => $rawText,
                    'summary' => $summaryText,
                    'date' => $dateText,
                    'newspaper' => $newspaperText
                ];
            }
        }
    }

    return $items;
}

$galleryItems = getPressItems();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hayan Art | Press & Publications</title>
    <script>
        // GitHub Pages Clean URL Restorer
        (function() {
            let redirect = sessionStorage.getItem('ghp_redirect');
            if (redirect) {
                sessionStorage.removeItem('ghp_redirect');
                window.history.replaceState(null, null, redirect);
            }
        })();
    </script>
    <!-- Load Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Lora:wght@400;500;600;700&family=Open+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Open Sans"', 'sans-serif'],
                        serif: ['Lora', 'serif'],
                    },
                    colors: {
                        dark: '#121212',
                        brand: '#2c2b29'
                    }
                }
            }
        }
    </script>
    <style>
        html { scroll-behavior: smooth; }
        .gallery-item { transition: all 0.4s ease-in-out; }
        .gallery-item.hidden-item { display: none !important; }
        .gallery-item.show-item { animation: popIn 0.4s ease-out forwards; }

        @keyframes popIn {
            0% { opacity: 0; transform: scale(0.95); }
            100% { opacity: 1; transform: scale(1); }
        }

        /* CUSTOM SCROLLBAR FOR SUMMARY AREAS */
        .elegant-scrollbar::-webkit-scrollbar {
            width: 6px;
        }
        .elegant-scrollbar::-webkit-scrollbar-track {
            background: transparent;
        }
        .elegant-scrollbar::-webkit-scrollbar-thumb {
            background-color: #A8A296;
            border-radius: 10px;
        }
        .elegant-scrollbar::-webkit-scrollbar-thumb:hover {
            background-color: #8c867c;
        }
    </style>
</head>
<body class="bg-[#D8D5CD] text-gray-900 antialiased font-sans">

    <nav class="fixed w-full top-0 z-50 bg-[#D8D5CD]/80 backdrop-blur-md border-b border-[#A8A296]">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-20">
                <div class="flex-shrink-0 flex items-center">
                    <a href="/" class="text-2xl font-serif font-semibold tracking-wide">Hayan Art</a>
                </div>
                <div class="hidden md:flex space-x-8 items-center">
                    <a href="/" class="text-black hover:opacity-70 transition-opacity font-medium">Home</a>
                    
                    <div class="relative group">
                        <a href="/#portfolio" class="text-black hover:opacity-70 transition-opacity font-medium flex items-center gap-1 focus:outline-none">
                            Artworks
                            <svg class="w-4 h-4 transition-transform group-hover:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                        </a>
                        <div class="absolute left-0 mt-2 w-48 bg-[#E5DFD3] border border-[#A8A296] rounded-md shadow-lg opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-300 transform origin-top-left -translate-y-2 group-hover:translate-y-0">
                            <div class="py-1">
                                <a href="/printmaking/all-eras" class="block px-4 py-2 text-sm text-black hover:bg-[#D8D5CD]">Printmaking</a>
                                <a href="/painting/all-eras" class="block px-4 py-2 text-sm text-black hover:bg-[#D8D5CD]">Painting</a>
                                <a href="/on-paper/all-eras" class="block px-4 py-2 text-sm text-black hover:bg-[#D8D5CD]">On Paper</a>
                            </div>
                        </div>
                    </div>

                    <div class="relative group">
                        <a href="/#portfolio" class="text-black hover:opacity-70 transition-opacity font-medium flex items-center gap-1 focus:outline-none">
                            Collections
                            <svg class="w-4 h-4 transition-transform group-hover:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                        </a>
                        <div class="absolute left-0 mt-2 w-48 bg-[#E5DFD3] border border-[#A8A296] rounded-md shadow-lg opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-300 transform origin-top-left -translate-y-2 group-hover:translate-y-0">
                            <div class="py-1">
                                <a href="/art-book/all-eras" class="block px-4 py-2 text-sm text-black hover:bg-[#D8D5CD]">Art Book</a>
                                <a href="/portfolio/all-eras" class="block px-4 py-2 text-sm text-black hover:bg-[#D8D5CD]">Portfolio</a>
                            </div>
                        </div>
                    </div>
                    
                    <a href="/press" class="text-black hover:opacity-70 transition-opacity font-medium">Press</a>
                    <a href="/cv" class="text-black hover:opacity-70 transition-opacity font-medium">CV</a>
                </div>
            </div>
        </div>
    </nav>

    <section class="pt-32 pb-20 px-4 max-w-[90rem] mx-auto min-h-screen">
        <div class="text-center mb-16">
            <h1 class="text-4xl md:text-5xl font-serif font-bold text-black mb-4">Press & Publications</h1>
            <p class="text-lg text-gray-700 font-medium">Articles, magazines, and press features highlighting Hayan's work.</p>
        </div>

        <!-- MAG READER HEADER -->
        <div id="book-view-header" class="hidden flex-col w-full max-w-5xl mx-auto mb-16 relative bg-transparent transition-all duration-300">
            
            <button id="close-book-view" class="absolute -top-4 right-0 md:-right-8 p-2 text-gray-500 hover:text-black transition-colors focus:outline-none z-10">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>

            <div class="flex flex-col md:flex-row gap-8 lg:gap-12 w-full mt-4">
                
                <div class="w-full md:w-1/3 lg:w-1/4 flex-shrink-0 flex justify-center md:justify-start items-start">
                    <img id="book-header-cover" src="" class="w-full max-w-[280px] h-auto object-cover shadow-[0_10px_30px_rgba(0,0,0,0.15)] bg-white p-2 blur-md cursor-zoom-in hover:scale-[1.02] transition-all duration-700" alt="Cover Image" onload="this.classList.remove('blur-md')">
                </div>
                
                <div class="w-full md:w-2/3 lg:w-3/4 flex flex-col justify-start text-left pt-2">
                    <h2 class="text-lg md:text-xl font-serif text-black mb-4 flex flex-wrap items-baseline gap-3">
                        <span id="book-header-title" class="font-bold"></span> 
                        <span id="book-header-year" class="text-gray-500 font-light text-base"></span>
                        <span id="book-header-newspaper" class="text-gray-500 font-medium text-base ml-1"></span>
                    </h2>
                    
                    <hr class="border-t border-black w-full mb-6">
                    
                    <div id="book-header-desc" class="font-sans text-[15px] text-gray-700 whitespace-pre-wrap leading-relaxed max-w-3xl max-h-[40vh] overflow-y-auto elegant-scrollbar pr-4"></div>
                </div>
            </div>

            <div class="flex justify-between items-center w-full mt-14 pt-4 border-t border-[#A8A296] font-sans font-medium">
                <button id="prev-book-btn" class="flex items-center gap-2 text-sm text-gray-600 hover:text-black transition-colors py-2 focus:outline-none">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
                    Previous
                </button>
                <button id="next-book-btn" class="flex items-center gap-2 text-sm text-gray-600 hover:text-black transition-colors py-2 focus:outline-none">
                    Next 
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                </button>
            </div>
        </div>

        <!-- GRID OF ITEMS (Forced 2:2 / 1:1 Aspect Ratio) -->
        <div id="gallery-grid" class="flex flex-wrap justify-center gap-4 md:gap-6 relative">
            <?php foreach ($galleryItems as $item): ?>
                <div class="gallery-item group relative h-[250px] md:h-[350px] aspect-[2.5/2] flex-none overflow-hidden rounded-md bg-gray-200 show-item shadow-sm hover:shadow-xl cursor-pointer <?php echo isset($item['is_book']) ? 'book-trigger' : 'lightbox-trigger'; ?>" 
                     data-title="<?php echo htmlspecialchars($item['title']); ?>"
                     data-info="<?php echo htmlspecialchars($item['raw_text'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                     data-summary="<?php echo htmlspecialchars($item['summary'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                     data-date="<?php echo htmlspecialchars($item['date'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                     data-newspaper="<?php echo htmlspecialchars($item['newspaper'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                     <?php if(isset($item['is_book'])) echo "data-book-contents='" . htmlspecialchars($item['book_contents'], ENT_QUOTES, 'UTF-8') . "'"; ?>
                     <?php if(isset($item['is_book'])) echo "data-book-title='" . htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') . "'"; ?>
                     >
                    
                    <img src="<?php echo $item['url']; ?>" 
                         alt="<?php echo htmlspecialchars($item['title']); ?>" 
                         class="h-full w-full object-cover block transition-all duration-700 group-hover:scale-105 blur-md"
                         onload="this.classList.remove('blur-md')"
                         loading="lazy">
                    
                    <div class="absolute inset-0 bg-gradient-to-t from-black/90 via-black/40 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex flex-col justify-end p-6">

                        <h3 class="text-white text-lg font-serif font-bold drop-shadow-md">
                            <?php echo htmlspecialchars($item['title']); ?>
                        </h3>

                        <?php if (!empty($item['summary'])): ?>
                            <p class="text-gray-200 text-sm mt-2 font-sans font-light line-clamp-3 drop-shadow-sm">
                                <?php echo htmlspecialchars($item['summary']); ?>
                            </p>
                        <?php endif; ?>

                        <div class="mt-4 flex flex-row items-center gap-4 transition-transform duration-300 transform translate-y-4 opacity-0 group-hover:translate-y-0 group-hover:opacity-100">
                            <span class="inline-block px-5 py-2 bg-[#D8D5CD] text-black text-xs font-bold uppercase tracking-wider rounded-sm shadow-sm shrink-0">
                                Read Now
                            </span>
                            <?php if (!empty($item['date'])): ?>
                                <span class="text-gray-100 text-sm font-sans font-semibold drop-shadow-sm tracking-wide shrink-0">
                                    <?php echo htmlspecialchars($item['date']); ?>
                                </span>
                            <?php endif; ?>
                            <?php if (!empty($item['newspaper'])): ?>
                                <span class="text-gray-200 text-sm font-sans font-medium drop-shadow-sm tracking-wide line-clamp-1">
                                    <?php echo (!empty($item['date']) ? '• ' : '') . htmlspecialchars($item['newspaper']); ?>
                                </span>
                            <?php endif; ?>
                        </div>

                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- ELEGANT LIGHTBOX -->
    <div id="lightbox" class="fixed inset-0 z-[100] bg-[#D8D5CD] hidden opacity-0 transition-opacity duration-300 overflow-y-auto">
        <button id="lightbox-close" class="fixed top-6 right-8 md:top-10 md:right-12 text-black hover:opacity-60 focus:outline-none z-[101] transition-opacity bg-[#D8D5CD]/80 rounded-full p-2">
            <svg class="w-10 h-10 md:w-12 md:h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path></svg>
        </button>

        <div class="min-h-screen flex flex-col md:flex-row w-full max-w-[90rem] mx-auto px-6 md:px-16 items-center justify-center gap-10 md:gap-24 relative z-10 py-24 md:py-12">
            <div class="w-full md:w-1/2 flex justify-center md:justify-end items-center">
                <img id="lightbox-img" src="" alt="Enlarged Art" class="max-w-full max-h-[60vh] md:max-h-[85vh] object-contain shadow-2xl bg-transparent blur-md cursor-zoom-in hover:scale-[1.02] transition-all duration-700" onload="this.classList.remove('blur-md')">
            </div>
            
            <div class="w-full md:w-1/2 flex flex-col justify-center text-left">
                <h2 class="text-lg md:text-xl font-serif text-black mb-4 font-bold flex flex-wrap items-baseline gap-3">
                    <span id="lightbox-title">Artwork Title</span>
                    <span id="lightbox-date" class="text-gray-600 font-light text-base"></span>
                    <span id="lightbox-newspaper" class="text-gray-600 font-medium text-base ml-1"></span>
                </h2>
                
                <hr class="border-t border-black w-full mb-6">
                
                <div id="lightbox-info" class="font-sans text-black mb-10 md:mb-16 whitespace-pre-wrap font-medium max-h-[50vh] overflow-y-auto elegant-scrollbar pr-4"></div>

                <div class="flex items-center justify-start gap-8 md:gap-16 text-[14px] md:text-[15px] font-sans font-semibold text-black">
                    <button id="lightbox-prev" class="hover:opacity-60 transition-opacity flex items-center gap-2 focus:outline-none disabled:opacity-30 disabled:cursor-not-allowed uppercase tracking-wider py-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"></path></svg>
                        Previous
                    </button>
                    <button id="lightbox-next" class="hover:opacity-60 transition-opacity flex items-center gap-2 focus:outline-none disabled:opacity-30 disabled:cursor-not-allowed uppercase tracking-wider py-2">
                        Next 
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"></path></svg>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- PURE FULLSCREEN ZOOM OVERLAY -->
    <div id="fullscreen-overlay" class="fixed inset-0 z-[200] bg-black/95 hidden opacity-0 transition-opacity duration-300 flex items-center justify-center p-4 cursor-zoom-out">
        <button id="fullscreen-close" class="absolute top-6 right-8 md:top-10 md:right-12 text-white hover:opacity-60 focus:outline-none z-[201] transition-opacity p-2">
            <svg class="w-10 h-10 md:w-12 md:h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path></svg>
        </button>
        <img id="fullscreen-img" src="" class="max-w-full max-h-full object-contain drop-shadow-2xl">
    </div>

    <footer id="contact" class="bg-[#D8D5CD] pt-16 pb-8 text-black relative border-t border-[#A8A296]">
        <div class="absolute inset-0 opacity-[0.04] pointer-events-none" style="background-image: url('data:image/svg+xml,%3Csvg viewBox=%220 0 200 200%22 xmlns=%22http://www.w3.org/2000/svg%22%3E%3Cfilter id=%22noiseFilter%22%3E%3CfeTurbulence type=%22fractalNoise%22 baseFrequency=%220.85%22 numOctaves=%223%22 stitchTiles=%22stitch%22/%3E%3C/filter%3E%3Crect width=%22100%25%22 height=%22100%25%22 filter=%22url(%23noiseFilter)%22/%3E%3C/svg%3E');"></div>
        
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            <div class="flex flex-col md:flex-row justify-between items-start mb-16">
                <div class="w-full md:w-1/3 mb-10 md:mb-0">
                    <div class="mb-2">
                        <svg width="40" height="24" viewBox="0 0 40 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <circle cx="12" cy="12" r="12" fill="black"/>
                            <path d="M26 0V24C32.6274 24 38 18.6274 38 12C38 5.37258 32.6274 0 26 0Z" fill="black"/>
                        </svg>
                    </div>
                    <h2 class="text-2xl font-sans font-medium tracking-wide">Hayan Art</h2>
                </div>

                <div class="w-full md:w-2/3 flex flex-col sm:flex-row justify-between md:justify-end md:gap-24 font-sans text-[15px] leading-relaxed">
                    <div class="flex flex-col gap-1.5 mb-6 sm:mb-0">
                        <a href="/" class="hover:opacity-70 hover:underline transition-all">Home</a>
                        <a href="/cv" class="hover:opacity-70 hover:underline transition-all">About</a>
                    </div>
                    <div class="flex flex-col gap-1.5 mb-6 sm:mb-0">
                        <a href="https://www.facebook.com/hayan.abduljabbar" class="hover:opacity-70 hover:underline transition-all" target="_blank">Facebook</a>
                        <a href="https://www.instagram.com/hayan_art/" class="hover:opacity-70 hover:underline transition-all" target="_blank">Instagram</a>
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <p>Tel. +964 770 392 6787</p>
                        <p>Baghdad, Iraq</p>
                    </div>
                </div>
            </div>

            <div class="relative flex flex-col md:flex-row items-center justify-center text-[13px] md:text-sm mt-12 pt-8 border-t border-black/10">
                <p class="md:absolute md:left-0 mb-4 md:mb-0">Proudly designed by <a href="#" class="underline hover:opacity-70 font-medium">Shams Hayan</a></p>
                <p>&copy; 2026 Hayan. All Rights Reserved.</p>
            </div>
        </div>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const galleryItems = document.querySelectorAll('.gallery-item');
            const bookViewHeader = document.getElementById('book-view-header');
            const closeBookViewBtn = document.getElementById('close-book-view');
            const grid = document.getElementById('gallery-grid');
            
            let isBookViewActive = false;
            let currentBookIndex = -1;
            
            const currentVisibleBooks = Array.from(document.querySelectorAll('.book-trigger'));

            document.addEventListener('click', (e) => {
                const trigger = e.target.closest('.book-trigger');
                if (!trigger) return;
                
                const index = currentVisibleBooks.indexOf(trigger);
                if (index > -1) {
                    openBookView(index);
                }
            });

            function openBookView(index) {
                currentBookIndex = index;
                const trigger = currentVisibleBooks[index];
                
                const imagesRaw = trigger.getAttribute('data-book-contents');
                const title = trigger.getAttribute('data-book-title');
                const rawDesc = trigger.getAttribute('data-info');
                const rawSummary = trigger.getAttribute('data-summary');
                const date = trigger.getAttribute('data-date');
                const newspaper = trigger.getAttribute('data-newspaper');
                
                const coverImgEl = document.getElementById('book-header-cover');
                coverImgEl.classList.add('blur-md');
                coverImgEl.src = trigger.querySelector('img').src;
                
                const parsedDesc = (rawDesc || '').replace(/\*\*(.*?)\*\*/g, '<strong class="text-black font-semibold">$1</strong>');
                const parsedSummary = (rawSummary || '').replace(/\*\*(.*?)\*\*/g, '<strong class="text-black font-semibold">$1</strong>');

                isBookViewActive = true;
                
                galleryItems.forEach(item => {
                    item.classList.remove('show-item');
                    item.classList.add('hidden-item');
                    item.style.display = 'none'; 
                });

                document.getElementById('book-header-title').textContent = title;
                
                if (date) {
                    document.getElementById('book-header-year').textContent = date;
                } else {
                    document.getElementById('book-header-year').textContent = '';
                }

                if (newspaper) {
                    document.getElementById('book-header-newspaper').textContent = (date ? '• ' : '') + newspaper;
                } else {
                    document.getElementById('book-header-newspaper').textContent = '';
                }
                
                let combinedHtml = '';
                if (parsedSummary) combinedHtml += `<p class="text-xl md:text-2xl text-black mb-4 leading-relaxed">${parsedSummary}</p>`;
                if (parsedDesc) combinedHtml += `<p class="text-base md:text-lg text-gray-700 leading-relaxed">${parsedDesc}</p>`;
                
                document.getElementById('book-header-desc').innerHTML = combinedHtml;
                
                document.getElementById('prev-book-btn').style.visibility = (currentBookIndex > 0) ? 'visible' : 'hidden';
                document.getElementById('next-book-btn').style.visibility = (currentBookIndex < currentVisibleBooks.length - 1) ? 'visible' : 'hidden';

                bookViewHeader.classList.remove('hidden');
                bookViewHeader.classList.add('flex');

                document.querySelectorAll('.temp-book-page').forEach(el => el.remove());
                
                if (imagesRaw) {
                    try {
                        const images = JSON.parse(imagesRaw);
                        images.forEach((imgUrl, i) => {
                            const pageDiv = document.createElement('div');
                            // Apply aspect-[2.5/2] to dynamically created pages as well
                            pageDiv.className = 'temp-book-page lightbox-trigger cursor-pointer gallery-item group relative h-[250px] md:h-[350px] aspect-[2.5/2] flex-none overflow-hidden rounded-md bg-[#D8D5CD] show-item shadow-sm hover:shadow-xl';
                            const labelText = i === 0 ? 'Cover' : 'Page ' + i;

                            pageDiv.setAttribute('data-title', title + ' - ' + labelText);
                            pageDiv.setAttribute('data-info', '');

                            pageDiv.innerHTML = `
                                <img src="${imgUrl}" alt="${labelText}" class="h-full w-full object-cover block transition-all duration-700 group-hover:scale-105 blur-md" onload="this.classList.remove('blur-md')" loading="lazy">
                                <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/20 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex flex-col justify-end p-6">
                                    <span class="text-white text-lg font-serif font-bold">${labelText}</span>
                                </div>
                            `;
                            grid.appendChild(pageDiv);
                        });
                    } catch (err) {}
                }
                
                window.scrollTo({ top: 0, behavior: 'smooth' });
                const cleanTitle = title.toLowerCase().replace(/\s+/g, '-');
                window.history.replaceState(null, null, '/press/view-' + cleanTitle);
            }

            closeBookViewBtn.addEventListener('click', () => {
                isBookViewActive = false;
                document.querySelectorAll('.temp-book-page').forEach(el => el.remove());
                bookViewHeader.classList.remove('flex');
                bookViewHeader.classList.add('hidden');
                window.history.replaceState(null, null, '/press'); 
                
                galleryItems.forEach(item => {
                    item.classList.remove('hidden-item');
                    item.classList.add('show-item');
                    item.style.display = ''; 
                });
            });

            document.getElementById('prev-book-btn').addEventListener('click', () => {
                if (currentBookIndex > 0) openBookView(currentBookIndex - 1);
            });
            document.getElementById('next-book-btn').addEventListener('click', () => {
                if (currentBookIndex < currentVisibleBooks.length - 1) openBookView(currentBookIndex + 1);
            });

            // --- Elegant Lightbox ---
            const lightbox = document.getElementById('lightbox');
            const lightboxImg = document.getElementById('lightbox-img');
            
            let currentLightboxItems = [];
            let currentLightboxIndex = -1;

            function updateLightboxUI(index) {
                const item = currentLightboxItems[index];
                const img = item.querySelector('img');
                
                lightboxImg.classList.add('blur-md');
                lightboxImg.src = img.src;
                
                const rawTitle = item.getAttribute('data-title') || 'Artwork';
                const titleParts = rawTitle.split(' - ');
                document.getElementById('lightbox-title').textContent = titleParts[0]; 
                
                const rawDesc = item.getAttribute('data-info') || '';
                const rawSummary = item.getAttribute('data-summary') || '';
                const date = item.getAttribute('data-date') || '';
                const newspaper = item.getAttribute('data-newspaper') || '';

                if (date) {
                    document.getElementById('lightbox-date').textContent = date;
                } else {
                    document.getElementById('lightbox-date').textContent = '';
                }

                if (newspaper) {
                    document.getElementById('lightbox-newspaper').textContent = (date ? '• ' : '') + newspaper;
                } else {
                    document.getElementById('lightbox-newspaper').textContent = '';
                }
                
                let combinedHtml = '';
                if (rawSummary) combinedHtml += `<p class="text-xl md:text-2xl text-black mb-4 leading-relaxed">${rawSummary}</p>`;
                if (rawDesc) combinedHtml += `<p class="text-base md:text-lg text-gray-800 leading-relaxed">${rawDesc}</p>`;
                
                document.getElementById('lightbox-info').innerHTML = combinedHtml;

                document.getElementById('lightbox-prev').disabled = (index === 0);
                document.getElementById('lightbox-next').disabled = (index === currentLightboxItems.length - 1);
            }

            grid.addEventListener('click', (e) => {
                const item = e.target.closest('.lightbox-trigger');
                if (!item || item.classList.contains('book-trigger')) return;

                e.preventDefault(); 

                if (isBookViewActive) {
                    currentLightboxItems = Array.from(document.querySelectorAll('.temp-book-page.lightbox-trigger'));
                } else {
                    currentLightboxItems = Array.from(document.querySelectorAll('.gallery-item.show-item.lightbox-trigger'));
                }
                
                currentLightboxIndex = currentLightboxItems.indexOf(item);
                if (currentLightboxIndex > -1) {
                    updateLightboxUI(currentLightboxIndex);
                    lightbox.classList.remove('hidden');
                    document.body.style.overflow = 'hidden'; 
                    setTimeout(() => lightbox.classList.remove('opacity-0'), 20);
                }
            });

            document.getElementById('lightbox-prev').addEventListener('click', (e) => {
                e.stopPropagation();
                if (currentLightboxIndex > 0) {
                    currentLightboxIndex--;
                    updateLightboxUI(currentLightboxIndex);
                }
            });

            document.getElementById('lightbox-next').addEventListener('click', (e) => {
                e.stopPropagation();
                if (currentLightboxIndex < currentLightboxItems.length - 1) {
                    currentLightboxIndex++;
                    updateLightboxUI(currentLightboxIndex);
                }
            });

            function closeLightbox() {
                lightbox.classList.add('opacity-0');
                document.body.style.overflow = ''; 
                setTimeout(() => {
                    lightbox.classList.add('hidden');
                    lightboxImg.src = '';
                }, 300);
            }

            document.getElementById('lightbox-close').addEventListener('click', closeLightbox);
            
            lightbox.addEventListener('click', (e) => {
                if (e.target === lightbox || e.target.classList.contains('min-h-screen')) {
                    closeLightbox();
                }
            });

            // --- FULLSCREEN ZOOM LOGIC ---
            const fullscreenOverlay = document.getElementById('fullscreen-overlay');
            const fullscreenImg = document.getElementById('fullscreen-img');
            let zoomLevel = 1;

            function openFullscreen(src) {
                fullscreenImg.src = src;
                zoomLevel = 1;
                fullscreenImg.style.transform = `scale(${zoomLevel})`;
                fullscreenImg.style.transformOrigin = `center center`;
                fullscreenOverlay.classList.remove('hidden');
                setTimeout(() => fullscreenOverlay.classList.remove('opacity-0'), 20);
            }

            function closeFullscreen() {
                fullscreenOverlay.classList.add('opacity-0');
                setTimeout(() => {
                    fullscreenOverlay.classList.add('hidden');
                    fullscreenImg.src = '';
                }, 300);
            }

            fullscreenOverlay.addEventListener('wheel', (e) => {
                e.preventDefault();
                const zoomSpeed = 0.15;
                if (e.deltaY < 0) zoomLevel += zoomSpeed; 
                else zoomLevel -= zoomSpeed; 
                
                zoomLevel = Math.max(0.5, Math.min(zoomLevel, 5));
                fullscreenImg.style.transform = `scale(${zoomLevel})`;
                fullscreenImg.style.transition = 'transform 0.1s ease-out';
            }, { passive: false });

            fullscreenOverlay.addEventListener('mousemove', (e) => {
                if (zoomLevel > 1) {
                    const rect = fullscreenOverlay.getBoundingClientRect();
                    const x = ((e.clientX - rect.left) / rect.width) * 100;
                    const y = ((e.clientY - rect.top) / rect.height) * 100;
                    fullscreenImg.style.transformOrigin = `${x}% ${y}%`;
                } else {
                    fullscreenImg.style.transformOrigin = `center center`;
                }
            });

            document.getElementById('lightbox-img').addEventListener('click', (e) => {
                e.stopPropagation();
                openFullscreen(e.target.src);
            });

            document.getElementById('book-header-cover').addEventListener('click', (e) => {
                e.stopPropagation();
                openFullscreen(e.target.src);
            });

            fullscreenOverlay.addEventListener('click', closeFullscreen);
            document.getElementById('fullscreen-close').addEventListener('click', (e) => {
                e.stopPropagation();
                closeFullscreen();
            });
            
            document.addEventListener('keydown', (e) => {
                if (!fullscreenOverlay.classList.contains('hidden')) {
                    if (e.key === 'Escape') closeFullscreen();
                    return; 
                }

                if (!lightbox.classList.contains('hidden')) {
                    if (e.key === 'Escape') closeLightbox();
                    if (e.key === 'ArrowLeft' && currentLightboxIndex > 0) {
                        currentLightboxIndex--;
                        updateLightboxUI(currentLightboxIndex);
                    }
                    if (e.key === 'ArrowRight' && currentLightboxIndex < currentLightboxItems.length - 1) {
                        currentLightboxIndex++;
                        updateLightboxUI(currentLightboxIndex);
                    }
                }
            });
        });
    </script>
</body>
</html>