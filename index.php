<?php
// Prevent PHP errors/warnings from leaking into the generated HTML file
error_reporting(0);
ini_set('display_errors', '0');

// --- DYNAMIC GALLERY GENERATOR ---

// Auto-detect Mac/Windows line endings in CSVs
ini_set('auto_detect_line_endings', TRUE);

// HELPER: Bulletproof ID matching engine. 
function normalizeId($string) {
    $string = pathinfo($string, PATHINFO_FILENAME); // Remove extension
    return str_replace(['-', '_', ' ', "'", '’', '"'], '', mb_strtolower(trim($string), 'UTF-8'));
}

// SMART PARSER FOR ARTWORKS (Title, Desc, Size, Year)
function parseArtworksCsv($csvPath) {
    $data = [];
    if (file_exists($csvPath)) {
        $firstLine = @file_get_contents($csvPath, false, null, 0, 500);
        $delimiter = (strpos($firstLine, ';') !== false) ? ';' : ',';

        if (($handle = @fopen($csvPath, "r")) !== FALSE) {
            $headers = fgetcsv($handle, 10000, $delimiter);
            if ($headers) {
                $colMap = ['title' => -1, 'desc' => -1, 'size' => -1, 'year' => -1];
                
                foreach ($headers as $k => $v) {
                    $val = strtolower(trim($v));
                    if (strpos($val, 'title') !== false || strpos($val, 'file') !== false) $colMap['title'] = $k;
                    elseif (strpos($val, 'desc') !== false || strpos($val, 'disc') !== false || strpos($val, 'medium') !== false) $colMap['desc'] = $k;
                    elseif (strpos($val, 'size') !== false || strpos($val, 'dimen') !== false) $colMap['size'] = $k;
                    elseif (strpos($val, 'year') !== false || strpos($val, 'date') !== false) $colMap['year'] = $k;
                }

                if ($colMap['title'] === -1) $colMap['title'] = 1;
                if ($colMap['desc'] === -1) $colMap['desc'] = 2;
                if ($colMap['size'] === -1) $colMap['size'] = 3;
                if ($colMap['year'] === -1) $colMap['year'] = 4;

                while (($row = fgetcsv($handle, 10000, $delimiter)) !== FALSE) {
                    $idCol = isset($row[$colMap['title']]) ? $row[$colMap['title']] : '';
                    if (!empty($idCol)) {
                        $key = normalizeId($idCol);
                        $data[$key] = [
                            'title' => trim($idCol),
                            'desc'  => isset($row[$colMap['desc']]) ? trim($row[$colMap['desc']]) : '',
                            'size'  => isset($row[$colMap['size']]) ? trim($row[$colMap['size']]) : '',
                            'year'  => isset($row[$colMap['year']]) ? trim($row[$colMap['year']]) : ''
                        ];
                    }
                }
            }
            fclose($handle);
        }
    }
    return $data;
}

// SMART PARSER FOR COLLECTIONS (ID, Desc, Year)
function parseCollectionsCsv($csvPath) {
    $data = [];
    if (file_exists($csvPath)) {
        $firstLine = @file_get_contents($csvPath, false, null, 0, 500);
        $delimiter = (strpos($firstLine, ';') !== false) ? ';' : ',';

        if (($handle = @fopen($csvPath, "r")) !== FALSE) {
            $headers = fgetcsv($handle, 10000, $delimiter);
            if ($headers) {
                $colMap = ['id' => 0, 'desc' => 1, 'year' => 2];
                $hasHeaders = false;
                foreach ($headers as $k => $v) {
                    $val = strtolower(trim($v));
                    if (in_array($val, ['id', 'folder', 'name', 'title'])) { $colMap['id'] = $k; $hasHeaders = true; }
                    elseif (in_array($val, ['desc', 'description', 'text', 'info'])) { $colMap['desc'] = $k; $hasHeaders = true; }
                    elseif (in_array($val, ['year', 'date'])) { $colMap['year'] = $k; $hasHeaders = true; }
                }

                if (!$hasHeaders) {
                    $idCol = isset($headers[$colMap['id']]) ? $headers[$colMap['id']] : '';
                    if (!empty($idCol)) {
                        $key = normalizeId($idCol);
                        $data[$key] = [
                            'desc' => isset($headers[$colMap['desc']]) ? trim($headers[$colMap['desc']]) : '',
                            'year' => isset($headers[$colMap['year']]) ? trim($headers[$colMap['year']]) : ''
                        ];
                    }
                }

                while (($row = fgetcsv($handle, 10000, $delimiter)) !== FALSE) {
                    $idCol = isset($row[$colMap['id']]) ? $row[$colMap['id']] : '';
                    if (!empty($idCol)) {
                        $key = normalizeId($idCol);
                        $data[$key] = [
                            'desc' => isset($row[$colMap['desc']]) ? trim($row[$colMap['desc']]) : '',
                            'year' => isset($row[$colMap['year']]) ? trim($row[$colMap['year']]) : ''
                        ];
                    }
                }
            }
            fclose($handle);
        }
    }
    return $data;
}

function getGalleryItems() {
    $items = [];
    $baseDir = __DIR__;
    $infoDir = $baseDir . DIRECTORY_SEPARATOR . 'info';
    
    $cvDirName = 'hayan C-V & prass';
    if (is_dir($baseDir . DIRECTORY_SEPARATOR . 'hayan C-V & press')) {
        $cvDirName = 'hayan C-V & press';
    }

    $artworksInfo = parseArtworksCsv($infoDir . DIRECTORY_SEPARATOR . 'Artworks.csv');
    $collectionsInfo = parseCollectionsCsv($infoDir . DIRECTORY_SEPARATOR . 'Collections.csv');

    // 1. Scan Main Decades
    $decades = ['1970', '1980', '1990', '2000', '2010', '2020'];
    foreach ($decades as $decade) {
        $decadePath = $baseDir . DIRECTORY_SEPARATOR . $decade;
        if (is_dir($decadePath)) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($decadePath));
            foreach ($iterator as $fileInfo) {
                if ($fileInfo->isFile() && in_array(strtolower($fileInfo->getExtension()), ['jpg', 'jpeg', 'png', 'gif'])) {
                    $path = $fileInfo->getPathname();
                    
                    $catStr = strtolower(basename(dirname($path)));
                    $category = 'painting'; 
                    if (strpos($catStr, 'print') !== false) $category = 'Printmaking';
                    if (strpos($catStr, 'paper') !== false) $category = 'on-paper';

                    $fileNameWithoutExt = pathinfo($fileInfo->getFilename(), PATHINFO_FILENAME);
                    $title = ucwords(str_replace(['-', '_'], ' ', $fileNameWithoutExt));
                    $lookupKey = normalizeId($fileInfo->getFilename());

                    $rawTextParts = [];
                    $displayYear = $decade; 

                    if (isset($artworksInfo[$lookupKey])) {
                        $artData = $artworksInfo[$lookupKey];
                        if (!empty($artData['title'])) $title = $artData['title'];
                        if (!empty($artData['desc'])) $rawTextParts[] = $artData['desc'];
                        if (!empty($artData['size'])) $rawTextParts[] = $artData['size'];
                        if (!empty($artData['year'])) {
                            $displayYear = $artData['year']; 
                            $rawTextParts[] = $artData['year']; 
                        }
                    }

                    $rawText = implode("\n", $rawTextParts);
                    
                    if (empty($rawText)) {
                        if ($category === 'Printmaking') $rawText = 'Etching';
                        elseif ($category === 'painting') $rawText = 'Oil on Canvas';
                        elseif ($category === 'on-paper') $rawText = 'Mixed Media on Paper';
                    }

                    $relativePath = substr($path, strlen($baseDir) + 1);
                    $webUrl = str_replace('\\', '/', $relativePath);
                    $encodedUrl = implode('/', array_map('rawurlencode', explode('/', $webUrl)));

                    $items[] = [
                        'url' => $encodedUrl,
                        'title' => $title,
                        'category' => $category,
                        'subcat' => $decade . 's', 
                        'year' => $displayYear,
                        'raw_text' => $rawText
                    ];
                }
            }
        }
    }

    // 2. Scan ART-BOOK
    $artBookPath = $baseDir . DIRECTORY_SEPARATOR . $cvDirName . DIRECTORY_SEPARATOR . 'ART-BOOK';
    if (is_dir($artBookPath)) {
        $bookImagesMap = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($artBookPath));
        foreach ($iterator as $fileInfo) {
            if ($fileInfo->isFile() && in_array(strtolower($fileInfo->getExtension()), ['jpg', 'jpeg', 'png'])) {
                $dir = dirname($fileInfo->getPathname());
                $bookImagesMap[$dir][] = $fileInfo->getPathname();
            }
        }

        uksort($bookImagesMap, 'strnatcasecmp');

        foreach ($bookImagesMap as $dir => $images) {
            $bookFolderName = basename($dir);
            $exactYear = '2004'; 
            if (preg_match('/(2004|2010|2012|2020)/', $dir, $matches)) {
                $exactYear = $matches[1];
            }
            
            $decade = '2000';
            if ($exactYear == '2010' || $exactYear == '2012') $decade = '2010';
            elseif ($exactYear == '2020') $decade = '2020';
            $subcat = $decade . 's';
            
            $cleanName = preg_replace('/-\d{4}$/', '', $bookFolderName);
            $cleanName = preg_replace('/^ART-BOOK\s*-?/i', '', $cleanName);
            $bookTitle = ucwords(str_replace(['-', '_'], ' ', $cleanName));

            $lookupKeyFull = normalizeId($bookFolderName);
            $lookupKeyClean = normalizeId($cleanName);
            
            $rawText = 'Art Book Collection';
            $displayYear = $exactYear;

            if (isset($collectionsInfo[$lookupKeyFull])) {
                $colData = $collectionsInfo[$lookupKeyFull];
                if (!empty($colData['desc'])) $rawText = $colData['desc'];
                if (!empty($colData['year'])) $displayYear = $colData['year'];
            } elseif (isset($collectionsInfo[$lookupKeyClean])) {
                $colData = $collectionsInfo[$lookupKeyClean];
                if (!empty($colData['desc'])) $rawText = $colData['desc'];
                if (!empty($colData['year'])) $displayYear = $colData['year'];
            }

            usort($images, function($a, $b) {
                return strnatcasecmp(basename($a), basename($b));
            });

            $pages = [];
            $coverIndex = -1;

            foreach ($images as $index => $path) {
                $relativePath = substr($path, strlen($baseDir) + 1);
                $webUrl = str_replace('\\', '/', $relativePath);
                $encodedUrl = implode('/', array_map('rawurlencode', explode('/', $webUrl)));
                $pages[] = $encodedUrl;
                if (strpos(strtolower(basename($path)), 'cover.') === 0) {
                    $coverIndex = $index;
                }
            }

            if ($coverIndex !== -1) {
                $coverImage = $pages[$coverIndex]; 
                unset($pages[$coverIndex]); 
                array_unshift($pages, $coverImage); 
                $pages = array_values($pages);
            } elseif (count($pages) > 0) {
                $coverImage = $pages[0];
            } else {
                $coverImage = '';
            }

            if ($coverImage) {
                $items[] = [
                    'url' => $coverImage,
                    'title' => $bookTitle,
                    'category' => 'art-book',
                    'subcat' => $subcat,
                    'year' => $displayYear, 
                    'is_book' => true,
                    'book_contents' => json_encode($pages),
                    'raw_text' => $rawText
                ];
            }
        }
    }

    // 3. Scan Portfolio
    $portfolioPath = $baseDir . DIRECTORY_SEPARATOR . $cvDirName . DIRECTORY_SEPARATOR . 'Portfolio Box Work\'s';
    if (is_dir($portfolioPath)) {
        $portfolioImagesMap = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($portfolioPath));
        foreach ($iterator as $fileInfo) {
            if ($fileInfo->isFile() && in_array(strtolower($fileInfo->getExtension()), ['jpg', 'jpeg', 'png'])) {
                $dir = dirname($fileInfo->getPathname());
                $portfolioImagesMap[$dir][] = $fileInfo->getPathname();
            }
        }

        uksort($portfolioImagesMap, 'strnatcasecmp');

        foreach ($portfolioImagesMap as $dir => $images) {
            $portfolioFolderName = basename($dir);
            
            if (preg_match('/^Portfolio Box Work\'s \d{4}$/i', $portfolioFolderName)) { continue; }

            $exactYear = '1990'; 
            if (preg_match('/(1990|2000|2010|2020)/', $dir, $matches)) {
                $exactYear = $matches[1];
            }
            $subcat = $exactYear . 's';
            
            $cleanName = preg_replace('/-\d{4}$/', '', $portfolioFolderName); 
            $cleanName = preg_replace('/\(.*?\)/', '', $cleanName); 
            $cleanName = str_replace(['-', '_'], ' ', $cleanName); 
            $portfolioTitle = trim(ucwords(strtolower($cleanName)));

            $lookupKeyFull = normalizeId($portfolioFolderName);
            $lookupKeyClean = normalizeId($cleanName);
            
            $rawText = 'Portfolio Collection';
            $displayYear = $exactYear;

            if (isset($collectionsInfo[$lookupKeyFull])) {
                $colData = $collectionsInfo[$lookupKeyFull];
                if (!empty($colData['desc'])) $rawText = $colData['desc'];
                if (!empty($colData['year'])) $displayYear = $colData['year'];
            } elseif (isset($collectionsInfo[$lookupKeyClean])) {
                $colData = $collectionsInfo[$lookupKeyClean];
                if (!empty($colData['desc'])) $rawText = $colData['desc'];
                if (!empty($colData['year'])) $displayYear = $colData['year'];
            }

            usort($images, function($a, $b) {
                return strnatcasecmp(basename($a), basename($b));
            });

            $pages = [];
            $coverIndex = -1;

            foreach ($images as $index => $path) {
                $relativePath = substr($path, strlen($baseDir) + 1);
                $webUrl = str_replace('\\', '/', $relativePath);
                $encodedUrl = implode('/', array_map('rawurlencode', explode('/', $webUrl)));
                $pages[] = $encodedUrl;
                
                if (strpos(strtolower(basename($path)), 'cover.') === 0) {
                    $coverIndex = $index;
                }
            }

            if ($coverIndex !== -1) {
                $coverImage = $pages[$coverIndex]; 
                unset($pages[$coverIndex]); 
                array_unshift($pages, $coverImage); 
                $pages = array_values($pages);
            } elseif (count($pages) > 0) {
                $coverImage = $pages[0];
            } else {
                $coverImage = '';
            }

            if ($coverImage) {
                $items[] = [
                    'url' => $coverImage,
                    'title' => $portfolioTitle,
                    'category' => 'portfolio',
                    'subcat' => $subcat,
                    'year' => $displayYear,
                    'is_book' => true, 
                    'book_contents' => json_encode($pages),
                    'raw_text' => $rawText
                ];
            }
        }
    }

    return $items;
}

$galleryItems = getGalleryItems();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hayan Art | Artist Portfolio</title>
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

        .elegant-scrollbar::-webkit-scrollbar { width: 6px; }
        .elegant-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .elegant-scrollbar::-webkit-scrollbar-thumb { background-color: #A8A296; border-radius: 10px; }
        .elegant-scrollbar::-webkit-scrollbar-thumb:hover { background-color: #8c867c; }
    </style>
</head>
<body class="bg-[#D8D5CD] text-gray-900 antialiased font-sans">

    <nav class="fixed w-full top-0 z-50 bg-[#D8D5CD]/80 backdrop-blur-md border-b border-[#A8A296]">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-20">
                <div class="flex-shrink-0 flex items-center">
                    <a href="index.html" class="text-2xl font-serif font-semibold tracking-wide">Hayan Art</a>
                </div>
                <div class="hidden md:flex space-x-8 items-center">
                    <a href="index.html#home" class="text-black hover:opacity-70 transition-opacity font-medium">Home</a>
                    
                    <div class="relative group">
                        <button class="text-black hover:opacity-70 transition-opacity font-medium flex items-center gap-1 focus:outline-none">
                            Artworks
                            <svg class="w-4 h-4 transition-transform group-hover:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                        </button>
                        <div class="absolute left-0 mt-2 w-48 bg-[#E5DFD3] border border-[#A8A296] rounded-md shadow-lg opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-300 transform origin-top-left -translate-y-2 group-hover:translate-y-0">
                            <div class="py-1">
                                <a href="index.html#portfolio" onclick="document.querySelector('[data-filter=\'Printmaking\']').click()" class="block px-4 py-2 text-sm text-black hover:bg-[#D8D5CD]">Printmaking</a>
                                <a href="index.html#portfolio" onclick="document.querySelector('[data-filter=\'painting\']').click()" class="block px-4 py-2 text-sm text-black hover:bg-[#D8D5CD]">Painting</a>
                                <a href="index.html#portfolio" onclick="document.querySelector('[data-filter=\'on-paper\']').click()" class="block px-4 py-2 text-sm text-black hover:bg-[#D8D5CD]">On Paper</a>
                            </div>
                        </div>
                    </div>

                    <div class="relative group">
                        <button class="text-black hover:opacity-70 transition-opacity font-medium flex items-center gap-1 focus:outline-none">
                            Collections
                            <svg class="w-4 h-4 transition-transform group-hover:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                        </button>
                        <div class="absolute left-0 mt-2 w-48 bg-[#E5DFD3] border border-[#A8A296] rounded-md shadow-lg opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-300 transform origin-top-left -translate-y-2 group-hover:translate-y-0">
                            <div class="py-1">
                                <a href="index.html#portfolio" onclick="document.querySelector('[data-filter=\'art-book\']').click()" class="block px-4 py-2 text-sm text-black hover:bg-[#D8D5CD]">Art Book</a>
                                <a href="index.html#portfolio" onclick="document.querySelector('[data-filter=\'portfolio\']').click()" class="block px-4 py-2 text-sm text-black hover:bg-[#D8D5CD]">Portfolio</a>
                            </div>
                        </div>
                    </div>
                    
                    <a href="press.html" class="text-black hover:opacity-70 transition-opacity font-medium">Press</a>
                    <a href="cv.html" class="text-black hover:opacity-70 transition-opacity font-medium">CV</a>
                </div>
            </div>
        </div>
    </nav>

    <section id="home" class="pt-20 w-full min-h-[90vh] flex flex-col md:flex-row bg-brand">
        <div class="w-full md:w-1/2 relative min-h-[50vh] md:min-h-full">
            <img src="1970/Painting/doleful man.jpg" onerror="this.src='https://images.unsplash.com/photo-1579783902614-a3fb3927b6a5?q=80&w=1200&auto=format&fit=crop'" class="absolute inset-0 w-full h-full object-cover object-top" alt="Hayan Art Painting">
            <div class="absolute inset-0 bg-gradient-to-t from-black/90 via-black/20 to-transparent"></div>
            
            <div class="absolute bottom-8 left-8 md:bottom-16 md:left-12 text-white">
                <h1 class="text-5xl md:text-7xl font-serif mb-3 tracking-wide">Hayan Art</h1>
                <p class="text-lg md:text-xl font-light text-gray-200">Fine Art Painter | Original Oil Paintings</p>
            </div>
        </div>

        <div class="w-full md:w-1/2 bg-brand flex items-center justify-center p-12 md:p-24 text-gray-100 relative shadow-[inset_10px_0_20px_rgba(0,0,0,0.5)]">
            <div class="absolute inset-0 opacity-[0.15]" style="background-image: url('data:image/svg+xml,%3Csvg viewBox=%220 0 200 200%22 xmlns=%22http://www.w3.org/2000/svg%22%3E%3Cfilter id=%22noiseFilter%22%3E%3CfeTurbulence type=%22fractalNoise%22 baseFrequency=%220.85%22 numOctaves=%223%22 stitchTiles=%22stitch%22/%3E%3C/filter%3E%3Crect width=%22100%25%22 height=%22100%25%22 filter=%22url(%23noiseFilter)%22/%3E%3C/svg%3E');"></div>
            
            <div class="relative z-10 max-w-lg">
                <p class="text-xl md:text-2xl font-serif leading-relaxed md:leading-loose text-gray-300">
                    For over 30 years, Hayan has explored themes of heritage, nature, and emotion through oil and acrylic paintings. His work has been exhibited in numerous galleries and private collections...
                </p>
            </div>
        </div>
    </section>

    <section id="portfolio" class="py-20 px-4 max-w-[90rem] mx-auto min-h-screen">
        <div class="text-center mb-12">
            <h2 class="text-3xl font-serif font-bold text-black mb-8">Selected Works</h2>
            
            <div class="flex flex-wrap justify-center gap-3 md:gap-4 mb-4" id="filter-buttons">
                <button class="filter-btn px-6 py-2 rounded-full border border-black bg-black text-white font-medium shadow-sm transition-all" data-filter="Printmaking">Printmaking</button>
                <button class="filter-btn px-6 py-2 rounded-full border border-[#A8A296] bg-[#E5DFD3] text-black hover:border-black font-medium transition-all" data-filter="painting">Painting</button>
                <button class="filter-btn px-6 py-2 rounded-full border border-[#A8A296] bg-[#E5DFD3] text-black hover:border-black font-medium transition-all" data-filter="on-paper">On Paper</button>
                <button class="filter-btn px-6 py-2 rounded-full border border-[#A8A296] bg-[#E5DFD3] text-black hover:border-black font-medium transition-all" data-filter="art-book">Art Book</button>
                <button class="filter-btn px-6 py-2 rounded-full border border-[#A8A296] bg-[#E5DFD3] text-black hover:border-black font-medium transition-all" data-filter="portfolio">Portfolio</button>
            </div>

            <div class="hidden flex-wrap justify-center gap-2 md:gap-3 mb-8 transition-all duration-300" id="sub-filter-buttons"></div>
            
            <!-- BOOK/PORTFOLIO HEADER -->
            <div id="book-view-header" class="hidden flex-col w-full max-w-5xl mx-auto mb-8 relative bg-transparent transition-all duration-300">
                
                <button id="close-book-view" class="absolute -top-4 right-0 md:-right-8 p-2 text-gray-500 hover:text-black transition-colors focus:outline-none z-10">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>

                <div class="flex flex-col md:flex-row gap-8 lg:gap-12 w-full mt-4">
                    
                    <div class="w-full md:w-1/3 lg:w-1/4 flex-shrink-0 flex justify-center md:justify-start items-start">
                        <img id="book-header-cover" src="" class="w-full max-w-[280px] h-auto object-cover shadow-[0_10px_30px_rgba(0,0,0,0.15)] bg-white p-2 blur-md cursor-zoom-in hover:scale-[1.02] transition-all duration-700" alt="Cover Image" onload="this.classList.remove('blur-md')">
                    </div>
                    
                    <div class="w-full md:w-2/3 lg:w-3/4 flex flex-col justify-start text-left pt-2">
                        <!-- REDUCED TITLE SIZE (text-lg md:text-xl) -->
                        <h2 class="text-lg md:text-xl font-serif text-black mb-4 flex flex-wrap items-baseline gap-3">
                            <span id="book-header-title" class="font-bold"></span> 
                            <span id="book-header-year" class="text-gray-500 font-light text-base"></span>
                        </h2>
                        
                        <hr class="border-t border-black w-full mb-6">

                        <div id="book-header-desc" class="font-sans text-[15px] text-gray-800 whitespace-pre-wrap leading-relaxed max-w-3xl max-h-[40vh] overflow-y-auto elegant-scrollbar pr-4"></div>
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
        </div>

        <div class="flex flex-wrap justify-center gap-4 md:gap-6 relative" id="gallery-grid">
            <?php foreach ($galleryItems as $item): ?>
                <div class="gallery-item group relative h-[250px] md:h-[350px] flex-none overflow-hidden rounded-md bg-gray-200 show-item shadow-sm hover:shadow-xl cursor-pointer <?php echo isset($item['is_book']) ? 'book-trigger' : 'lightbox-trigger'; ?>" 
                     data-category="<?php echo htmlspecialchars($item['category']); ?>" 
                     data-subcat="<?php echo htmlspecialchars($item['subcat']); ?>"
                     data-year="<?php echo htmlspecialchars($item['year']); ?>"
                     data-title="<?php echo htmlspecialchars($item['title']); ?>"
                     data-info="<?php echo htmlspecialchars($item['raw_text'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                     <?php if(isset($item['is_book'])) echo "data-book-contents='" . htmlspecialchars($item['book_contents'], ENT_QUOTES, 'UTF-8') . "'"; ?>
                     <?php if(isset($item['is_book'])) echo "data-book-title='" . htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') . "'"; ?>
                     >
                    
                    <img src="<?php echo $item['url']; ?>" 
                         alt="<?php echo htmlspecialchars($item['title']); ?>" 
                         class="h-full w-auto block transition-all duration-700 group-hover:scale-105 blur-md"
                         onload="this.classList.remove('blur-md')"
                         loading="lazy">
                    
                    <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/20 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex flex-col justify-end p-6">
                        
                        <span class="text-gray-300 text-xs font-semibold tracking-wider uppercase mb-1 drop-shadow-md">
                            <?php echo htmlspecialchars(ucwords(str_replace('-', ' ', $item['category']))) . ' • ' . htmlspecialchars($item['year']); ?>
                        </span>

                        <h3 class="text-white text-lg font-serif font-bold">
                            <?php echo htmlspecialchars($item['title']); ?>
                        </h3>

                        <?php if(isset($item['is_book'])): ?>
                            <span class="block mt-1 text-sm font-sans font-normal text-gray-300 group-hover:text-white transition-colors">
                                <?php echo $item['category'] === 'portfolio' ? 'View Portfolio →' : 'View Book →'; ?>
                            </span>
                        <?php endif; ?>

                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- ELEGANT SPLIT LIGHTBOX -->
    <div id="lightbox" class="fixed inset-0 z-[100] bg-[#D8D5CD] hidden opacity-0 transition-opacity duration-300 overflow-y-auto">
        <button id="lightbox-close" class="fixed top-6 right-8 md:top-10 md:right-12 text-black hover:opacity-60 focus:outline-none z-[101] transition-opacity bg-[#D8D5CD]/80 rounded-full p-2">
            <svg class="w-10 h-10 md:w-12 md:h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path></svg>
        </button>

        <div class="min-h-screen flex flex-col md:flex-row w-full max-w-[90rem] mx-auto px-6 md:px-16 items-center justify-center gap-10 md:gap-24 relative z-10 py-24 md:py-12">
            <div class="w-full md:w-1/2 flex justify-center md:justify-end items-center">
                <img id="lightbox-img" src="" alt="Enlarged Art" class="max-w-full max-h-[60vh] md:max-h-[85vh] object-contain shadow-2xl bg-transparent blur-md cursor-zoom-in hover:scale-[1.02] transition-all duration-700" onload="this.classList.remove('blur-md')">
            </div>
            
            <div class="w-full md:w-1/2 flex flex-col justify-center text-left">
                <!-- REDUCED TITLE SIZE (text-lg md:text-xl) -->
                <h2 class="text-lg md:text-xl font-serif text-black mb-4">
                    <span id="lightbox-title">Artwork Title</span>
                </h2>
                
                <hr class="border-t border-black w-full mb-6">

                <div id="lightbox-info" class="font-sans text-[14px] md:text-[15px] text-gray-800 mb-10 md:mb-16 whitespace-pre-wrap leading-relaxed font-medium max-h-[50vh] overflow-y-auto elegant-scrollbar pr-4"></div>

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
                        <a href="index.html#home" class="hover:opacity-70 hover:underline transition-all">Home</a>
                        <a href="cv.html" class="hover:opacity-70 hover:underline transition-all">About</a>
                        <a href="mailto:hello@hayan.art" class="hover:opacity-70 hover:underline transition-all">Contact</a>
                    </div>
                    <div class="flex flex-col gap-1.5 mb-6 sm:mb-0">
                        <a href="https://www.facebook.com/hayan.abduljabbar" class="hover:opacity-70 hover:underline transition-all" target="_blank">Facebook</a>
                        <a href="#" class="hover:opacity-70 hover:underline transition-all">Twitter</a>
                        <a href="#" class="hover:opacity-70 hover:underline transition-all">LinkedIn</a>
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

    <<script>
        document.addEventListener('DOMContentLoaded', () => {
            const filterButtons = document.querySelectorAll('.filter-btn');
            const subFilterContainer = document.getElementById('sub-filter-buttons');
            const galleryItems = document.querySelectorAll('.gallery-item');
            const filterButtonsContainer = document.getElementById('filter-buttons');
            const bookViewHeader = document.getElementById('book-view-header');
            const closeBookViewBtn = document.getElementById('close-book-view');
            const grid = document.getElementById('gallery-grid');
            
            let activeMainFilter = 'Printmaking';
            let activeSubFilter = 'All Eras';
            let isBookViewActive = false;
            let currentBookIndex = -1;
            let currentVisibleBooks = [];

            const subCategoriesMap = {
                'Printmaking': ['All Eras', '1970s', '1980s', '1990s', '2000s', '2010s', '2020s'],
                'painting': ['All Eras', '1970s', '1980s', '1990s', '2000s', '2010s', '2020s'],
                'on-paper': ['All Eras', '1970s', '1980s', '1990s', '2000s', '2010s', '2020s'],
                'art-book': ['All Eras', '2000s', '2010s', '2020s'],
                'portfolio': ['All Eras', '1990s', '2000s', '2010s', '2020s']
            };

            // ---- CLEAN URL ROUTER (No hashes, no index.html) ----
            function syncCleanUrl() {
                if (isBookViewActive) return; 
                const cleanMain = activeMainFilter.toLowerCase().replace(/\s+/g, '-');
                const cleanSub = activeSubFilter.toLowerCase().replace(/\s+/g, '-');
                const newPath = `/${cleanMain}/${cleanSub}`;
                
                if (window.location.pathname !== newPath) {
                    window.history.replaceState(null, null, newPath);
                }
            }

            function updateGallery() {
                grid.style.display = 'none';
                currentVisibleBooks = [];

                galleryItems.forEach(item => {
                    const itemCategory = item.getAttribute('data-category');
                    const itemSubcat = item.getAttribute('data-subcat');
                    const matchesMain = activeMainFilter === itemCategory;
                    
                    let matchesSub = true;
                    if (subCategoriesMap[activeMainFilter] && activeSubFilter !== 'All Eras') {
                        matchesSub = activeSubFilter === itemSubcat;
                    }

                    if (matchesMain && matchesSub) {
                        item.classList.remove('hidden-item');
                        item.classList.add('show-item');
                        item.style.display = ''; 
                        if (item.classList.contains('book-trigger')) {
                            currentVisibleBooks.push(item);
                        }
                    } else {
                        item.classList.remove('show-item');
                        item.classList.add('hidden-item');
                        item.style.display = 'none'; 
                    }
                });

                void grid.offsetHeight;
                grid.style.display = '';
                syncCleanUrl();
            }

            function renderSubFilters(mainCategory) {
                subFilterContainer.innerHTML = '';
                if (subCategoriesMap[mainCategory]) {
                    subFilterContainer.classList.remove('hidden');
                    subFilterContainer.classList.add('flex');

                    subCategoriesMap[mainCategory].forEach(sub => {
                        const btn = document.createElement('button');
                        btn.setAttribute('data-subfilter', sub);
                        btn.textContent = sub;
                        if (activeSubFilter === sub) {
                            btn.className = 'sub-filter-btn px-4 py-1.5 rounded-full border border-black bg-black text-white text-sm font-medium transition-all shadow-sm';
                        } else {
                            btn.className = 'sub-filter-btn px-4 py-1.5 rounded-full border border-[#A8A296] bg-[#E5DFD3] text-black hover:border-black text-sm font-medium transition-all';
                        }
                        subFilterContainer.appendChild(btn);
                    });

                    const newSubBtns = subFilterContainer.querySelectorAll('.sub-filter-btn');
                    newSubBtns.forEach(button => {
                        button.addEventListener('click', () => {
                            newSubBtns.forEach(btn => {
                                btn.classList.remove('bg-black', 'text-white', 'border-black', 'shadow-sm');
                                btn.classList.add('bg-[#E5DFD3]', 'text-black', 'border-[#A8A296]');
                            });
                            
                            button.classList.remove('bg-[#E5DFD3]', 'text-black', 'border-[#A8A296]');
                            button.classList.add('bg-black', 'text-white', 'border-black', 'shadow-sm');
                            activeSubFilter = button.getAttribute('data-subfilter');
                            updateGallery();
                        });
                    });
                } else {
                    subFilterContainer.classList.add('hidden');
                    subFilterContainer.classList.remove('flex');
                }
            }

            filterButtons.forEach(button => {
                button.addEventListener('click', () => {
                    if (isBookViewActive) {
                        isBookViewActive = false;
                        document.querySelectorAll('.temp-book-page').forEach(el => el.remove());
                        bookViewHeader.classList.remove('flex');
                        bookViewHeader.classList.add('hidden');
                    }

                    filterButtons.forEach(btn => {
                        btn.classList.remove('bg-black', 'text-white', 'border-black', 'shadow-sm');
                        btn.classList.add('bg-[#E5DFD3]', 'text-black', 'border-[#A8A296]');
                    });
                    
                    button.classList.remove('bg-[#E5DFD3]', 'text-black', 'border-[#A8A296]');
                    button.classList.add('bg-black', 'text-white', 'border-black', 'shadow-sm');

                    activeMainFilter = button.getAttribute('data-filter');
                    
                    if (subCategoriesMap[activeMainFilter]) {
                        activeSubFilter = subCategoriesMap[activeMainFilter][0]; 
                    }
                    
                    renderSubFilters(activeMainFilter);
                    updateGallery();
                });
            });

            // LOAD FILTER STATE FROM CLEAN URL PATH (e.g. /painting/2020s)
            const pathSegments = window.location.pathname.split('/').filter(Boolean);
            if (pathSegments.length > 0) {
                const targetMain = Array.from(filterButtons).find(b => b.getAttribute('data-filter').toLowerCase().replace(/\s+/g, '-') === pathSegments[0]);
                if (targetMain) {
                    activeMainFilter = targetMain.getAttribute('data-filter');
                    if (pathSegments.length > 1 && subCategoriesMap[activeMainFilter]) {
                        const targetSub = subCategoriesMap[activeMainFilter].find(s => s.toLowerCase().replace(/\s+/g, '-') === decodeURIComponent(pathSegments[1]));
                        if (targetSub) activeSubFilter = targetSub;
                    }
                }
            }

            filterButtons.forEach(btn => {
                if (btn.getAttribute('data-filter') === activeMainFilter) {
                    btn.classList.remove('bg-[#E5DFD3]', 'text-black', 'border-[#A8A296]');
                    btn.classList.add('bg-black', 'text-white', 'border-black', 'shadow-sm');
                } else {
                    btn.classList.remove('bg-black', 'text-white', 'border-black', 'shadow-sm');
                    btn.classList.add('bg-[#E5DFD3]', 'text-black', 'border-[#A8A296]');
                }
            });
            renderSubFilters(activeMainFilter);
            updateGallery();

            document.addEventListener('click', (e) => {
                const trigger = e.target.closest('.book-trigger');
                if (!trigger || e.target.closest('.filter-btn')) return;
                
                const index = currentVisibleBooks.indexOf(trigger);
                if (index > -1) openBookView(index);
            });

            function openBookView(index) {
                currentBookIndex = index;
                const trigger = currentVisibleBooks[index];
                
                const imagesRaw = trigger.getAttribute('data-book-contents');
                const title = trigger.getAttribute('data-book-title');
                const year = trigger.getAttribute('data-year');
                const rawDesc = trigger.getAttribute('data-info');
                
                const coverImgEl = document.getElementById('book-header-cover');
                coverImgEl.classList.add('blur-md');
                coverImgEl.src = trigger.querySelector('img').src;

                const parsedDesc = (rawDesc || '').replace(/\*\*(.*?)\*\*/g, '<strong class="text-black font-semibold">$1</strong>');

                isBookViewActive = true;
                filterButtonsContainer.classList.add('hidden');
                subFilterContainer.classList.remove('flex');
                subFilterContainer.classList.add('hidden');
                
                galleryItems.forEach(item => {
                    item.classList.remove('show-item');
                    item.classList.add('hidden-item');
                    item.style.display = 'none'; 
                });

                document.getElementById('book-header-title').textContent = title;
                if (!year || isNaN(year)) {
                    document.getElementById('book-header-year').textContent = '';
                } else {
                    document.getElementById('book-header-year').textContent = year;
                }
                
                document.getElementById('book-header-desc').innerHTML = parsedDesc;
                
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
                            pageDiv.className = 'temp-book-page lightbox-trigger cursor-pointer gallery-item group relative h-[250px] md:h-[350px] flex-none overflow-hidden rounded-md bg-[#D1C9BB] show-item shadow-sm hover:shadow-xl';
                            const labelText = i === 0 ? 'Cover' : 'Page ' + i;

                            pageDiv.setAttribute('data-title', title + ' - ' + labelText);
                            pageDiv.setAttribute('data-info', '');

                            pageDiv.innerHTML = `
                                <img src="${imgUrl}" alt="${labelText}" class="h-full w-auto block transition-all duration-700 group-hover:scale-105 blur-md" onload="this.classList.remove('blur-md')" loading="lazy">
                                <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/20 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex flex-col justify-end p-6">
                                    <span class="text-white text-lg font-serif font-bold">${labelText}</span>
                                </div>
                            `;
                            grid.appendChild(pageDiv);
                        });
                    } catch (err) {}
                }
                document.getElementById('portfolio').scrollIntoView({ behavior: 'smooth' });
                
                const cleanMain = activeMainFilter.toLowerCase().replace(/\s+/g, '-');
                const cleanTitle = title.toLowerCase().replace(/\s+/g, '-');
                window.history.replaceState(null, null, `/${cleanMain}/view-${cleanTitle}`);
            }

            closeBookViewBtn.addEventListener('click', () => {
                isBookViewActive = false;
                document.querySelectorAll('.temp-book-page').forEach(el => el.remove());
                bookViewHeader.classList.remove('flex');
                bookViewHeader.classList.add('hidden');
                
                filterButtonsContainer.classList.remove('hidden');
                if (subCategoriesMap[activeMainFilter]) {
                    subFilterContainer.classList.remove('hidden');
                    subFilterContainer.classList.add('flex');
                }
                updateGallery();
            });

            document.getElementById('prev-book-btn').addEventListener('click', () => {
                if (currentBookIndex > 0) openBookView(currentBookIndex - 1);
            });
            document.getElementById('next-book-btn').addEventListener('click', () => {
                if (currentBookIndex < currentVisibleBooks.length - 1) openBookView(currentBookIndex + 1);
            });

            // --- Elegant Split Lightbox Logic ---
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
                const parsedDesc = rawDesc.replace(/\*\*(.*?)\*\*/g, '<strong class="text-black font-semibold">$1</strong>');
                document.getElementById('lightbox-info').innerHTML = parsedDesc;

                document.getElementById('lightbox-prev').disabled = (index === 0);
                document.getElementById('lightbox-next').disabled = (index === currentLightboxItems.length - 1);
            }

            grid.addEventListener('click', (e) => {
                const item = e.target.closest('.lightbox-trigger');
                if (!item) return;
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

            // --- PURE FULLSCREEN ZOOM OVERLAY ---
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