<?php
// ===================================================================
// Konfigurasi & Router Utama
// File ini berfungsi sebagai pengganti index.php dan surah.php
// ===================================================================

$api_url = 'https://equran.id/api/v2/surat'; 
$error = null;
$chapters = [];
$juz_list = []; 

// Tentukan mode: 'surah_list' (index) atau 'surah_detail'
$mode = 'surah_list';
$nomor_surah = $_GET['id'] ?? null;
$nomor_surah_int = (int) $nomor_surah;

if ($nomor_surah && is_numeric($nomor_surah) && $nomor_surah_int >= 1 && $nomor_surah_int <= 114) {
    $mode = 'surah_detail';
}

// --- Logic Data Fetching ---

$ch = curl_init();

if ($mode === 'surah_list') {
    // 1. Fetch data untuk daftar Surah (Index View)
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20); 
    $response = curl_exec($ch);
    
    if (curl_errno($ch)) {
        $error = 'Gagal terhubung ke API: ' . curl_error($ch);
    } else {
        $data = json_decode($response, true);
        if (isset($data['data']) && is_array($data['data'])) {
            $chapters = $data['data'];
        } else {
            $error = 'Data surah tidak ditemukan atau format respon API tidak sesuai.';
        }
    }

    // 2. Mapping data Juz Akurat
    $juz_mapping = [
        1 => ['Surat Al-Fatihah', 1], 2 => ['Surat Al-Baqarah', 142], 3 => ['Surat Al-Baqarah', 253], 4 => ['Surat Ali Imran', 93],
        5 => ['Surat An-Nisa\'', 24], 6 => ['Surat An-Nisa\'', 148], 7 => ['Surat Al-Ma\'idah', 83], 8 => ['Surat Al-An\'am', 111],
        9 => ['Surat Al-A\'raf', 88], 10 => ['Surat Al-Anfal', 41], 11 => ['Surat At-Taubah', 93], 12 => ['Surat Hud', 6],
        13 => ['Surat Yusuf', 53], 14 => ['Surat Al-Hijr', 1], 15 => ['Surat Al-Isra', 1], 16 => ['Surat Al-Kahf', 75],
        17 => ['Surat Al-Anbiya', 1], 18 => ['Surat Al-Mu\'minun', 1], 19 => ['Surat Al-Furqan', 21], 20 => ['Surat An-Naml', 56],
        21 => ['Surat Al-Ankabut', 46], 22 => ['Surat Al-Ahzab', 31], 23 => ['Surat Yasin', 28], 24 => ['Surat Az-Zumar', 32],
        25 => ['Surat Fussilat', 47], 26 => ['Surat Al-Ahqaf', 1], 27 => ['Surat Adz Dzariyat', 31], 28 => ['Surat Al-Mujadilah', 1],
        29 => ['Surat Al-Mulk', 1], 30 => ['Surat An-Naba\'', 1],
    ];
    
    foreach ($juz_mapping as $nomor => $data) {
        list($surah_nama_latin, $ayat_awal) = $data;
        $surah_info = array_filter($chapters, function($chapter) use ($surah_nama_latin) {
            $normalized_api_name = strtolower(str_replace([' ', '\''], '', $chapter['namaLatin']));
            $normalized_map_name = strtolower(str_replace([' ', '\''], '', str_replace(['Surat ', 'Al-'], '', $surah_nama_latin)));
            return str_starts_with($normalized_api_name, $normalized_map_name);
        });

        $surah_id = !empty($surah_info) ? array_values($surah_info)[0]['nomor'] : '#';
        $juz_list[] = [
            'nomor' => $nomor,
            'nama_latin' => "Juz {$nomor}",
            'keterangan' => "Mulai: {$surah_nama_latin} Ayat {$ayat_awal}",
            'surah_id' => $surah_id,
            'ayat_awal' => $ayat_awal,
        ];
    }
    
} else {
    // 1. Fetch data untuk Detail Surah (Surah View)
    $surah_data = null;
    $api_detail_url = $api_url . '/' . $nomor_surah;

    curl_setopt($ch, CURLOPT_URL, $api_detail_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20); 
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    $data = json_decode($response, true);

    if (!isset($data['data']) || $http_code != 200) {
        die("<h1>Gagal memuat data Surah.</h1><p>Status: $http_code. Coba periksa koneksi atau URL API.</p>");
    }

    $surah_data = $data['data'];

    $nama_latin     = $surah_data['namaLatin'] ?? 'Tidak diketahui';
    $nama_arab      = $surah_data['nama'] ?? '';
    $arti           = $surah_data['arti'] ?? '';
    $jumlah_ayat    = $surah_data['jumlahAyat'] ?? '';
    $tempat_turun   = $surah_data['tempatTurun'] ?? '';
    $ayat           = $surah_data['ayat'] ?? [];

    $prev_surah_id = $nomor_surah_int > 1 ? $nomor_surah_int - 1 : null;
    $next_surah_id = $nomor_surah_int < 114 ? $nomor_surah_int + 1 : null;
    $surah_number_padded = str_pad($nomor_surah, 3, '0', STR_PAD_LEFT);

    $qari_list = [ '01' => 'Abdullah Al-Juhany', '02' => 'Abdul Muhsin Al-Qasim', '03' => 'Abdurrahman As-Sudais', '04' => 'Ibrahim Al-Dossari', '05' => 'Misyari Rasyid Al-Afasy', ];
    $default_qari = '05'; 
    $audio_full_url = $surah_data['audioFull'][$default_qari] ?? null;
}
curl_close($ch);

// --- START HTML OUTPUT ---

if ($mode === 'surah_list') {
    // =======================================================
    // TAMPILAN UNTUK DAFTAR SURAH (INDEX VIEW)
    // =======================================================
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Surah Al-Qur'an</title>
    
    <script src="https://cdn.tailwindcss.com"></script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Amiri:wght@400;700&family=Inter:wght@400;700&display=swap" rel="stylesheet">
    
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'quran-bg': '#F9FAFB', 
                        'quran-primary': '#1D5D1D', 
                        'quran-accent': '#38A169',
                    },
                    fontFamily: {
                        'sans': ['Inter', 'sans-serif'],
                        'arabic-amiri': ['Amiri', 'serif'], 
                        'arabic-naskh': ['Noto Naskh Arabic', 'serif'], 
                    }
                }
            }
        }
    </script>
    <style>
        .font-arabic {
            font-family: 'Amiri', serif; 
            font-size: 2rem; 
            line-height: 2.5rem;
            direction: rtl;
        }
        .surah-card-wrapper {
             box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05), 0 1px 2px 0 rgba(0, 0, 0, 0.02);
        }
        .surah-card-wrapper:hover {
             box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>

<body class="bg-quran-bg font-sans">

    <nav class="bg-white shadow-sm sticky top-0 z-10 border-b border-gray-200">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex justify-between items-center">
            <span class="text-gray-800 text-xl font-bold">Al-Qur'an Indonesia</span>
            <span class="text-quran-primary text-sm font-medium">114 Surah</span>
        </div>
    </nav>

    <div class="container mx-auto p-4 sm:p-6 lg:p-8 max-w-4xl">
        
        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                <strong class="font-bold">Error API:</strong>
                <span class="block sm:inline"><?= $error ?></span>
            </div>
        <?php endif; ?>

        <div id="last-read-card" class="bg-white p-4 mb-6 rounded-lg border border-quran-accent hidden">
            <p class="text-xs text-gray-500 mb-1">Terakhir Dibaca:</p>
            <a href="#" id="last-read-link" class="text-lg font-bold text-quran-primary hover:text-quran-accent transition-colors duration-150">
                <span id="last-read-surah"></span> (<span id="last-read-ayat"></span>)
            </a>
        </div>

        <div class="mb-6">
            <input type="text" id="search-input" placeholder="Cari Surah atau Juz (e.g. Al-Baqarah)"
                   class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-quran-accent transition-shadow">
        </div>
        
        <div class="flex border-b border-gray-200 mb-6">
            <button id="tab-surah" data-tab="surah" class="tab-button px-4 py-2 text-sm font-medium border-b-2 border-quran-accent text-quran-primary transition-colors duration-200">
                Daftar Surah
            </button>
            <button id="tab-juz" data-tab="juz" class="tab-button px-4 py-2 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700 transition-colors duration-200">
                Daftar Juz (1-30)
            </button>
        </div>

        <div id="content-surah" class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <?php 
            if (!empty($chapters)): 
                foreach ($chapters as $chapter): 
                    $nomor = htmlspecialchars($chapter['nomor'] ?? '#');
                    $nama_latin = htmlspecialchars($chapter['namaLatin'] ?? 'Nama Surah');
                    $nama_arab = htmlspecialchars($chapter['nama'] ?? 'اسم السورة');
                    $arti = htmlspecialchars($chapter['arti'] ?? 'Terjemahan');
                    $jumlah_ayat = htmlspecialchars($chapter['jumlahAyat'] ?? '?');
                    $tempat_turun = htmlspecialchars($chapter['tempatTurun'] ?? '?');
            ?>
                <a href="?id=<?= $nomor ?>" 
                   class="surah-card-wrapper surah-item block p-4 bg-white rounded-lg border border-gray-200 
                          hover:border-quran-accent transition duration-200 ease-in-out"
                   data-search="<?= strtolower($nama_latin . ' ' . $arti) ?>"
                   data-nomor="<?= $nomor ?>">
                    
                    <div class="flex items-center justify-between">
                        
                        <div class="flex items-center space-x-3">
                            <div class="w-8 h-8 rounded-full bg-quran-accent flex items-center justify-center text-white text-sm font-semibold flex-shrink-0">
                                <?= $nomor ?>
                            </div>
                            
                            <div>
                                <h2 class="text-lg font-bold text-gray-800">
                                    <?= $nama_latin ?> 
                                </h2>
                                <p class="text-xs text-gray-500 mt-0.5">
                                    <?= $arti ?> &bull; <?= $jumlah_ayat ?> Ayat
                                </p>
                            </div>
                        </div>
                        
                        <div class="text-quran-primary font-arabic text-2xl">
                            <?= $nama_arab ?>
                        </div>
                    </div>
                </a>
            <?php 
                endforeach; 
            else:
                echo '<p class="text-center text-gray-500 col-span-3">Gagal memuat data Surah dari API.</p>';
            endif; 
            ?>
        </div>
        
        <div id="content-juz" class="grid grid-cols-1 md:grid-cols-2 gap-3 hidden">
            <?php foreach ($juz_list as $juz): ?>
                <a href="?id=<?= $juz['surah_id'] ?>#ayat-<?= $juz['ayat_awal'] ?>" 
                   class="surah-card-wrapper juz-item block p-4 bg-white rounded-lg border border-gray-200 
                          hover:border-quran-accent transition duration-200 ease-in-out"
                   data-search="<?= strtolower($juz['nama_latin'] . ' ' . $juz['keterangan']) ?>">
                    
                    <div class="flex items-center justify-between">
                        
                        <div class="flex items-center space-x-3">
                            <div class="w-8 h-8 rounded-full bg-gray-500 flex items-center justify-center text-white text-sm font-semibold flex-shrink-0">
                                J-<?= $juz['nomor'] ?>
                            </div>
                            
                            <div>
                                <h2 class="text-lg font-bold text-gray-800">
                                    <?= $juz['nama_latin'] ?> 
                                </h2>
                                <p class="text-xs text-gray-500 mt-0.5">
                                    <?= $juz['keterangan'] ?>
                                </p>
                            </div>
                        </div>
                        
                        <div class="text-quran-primary font-arabic text-xl">
                            <?= 'الجزء ' . $juz['nomor'] ?>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
        
    </div>
    
    <footer class="mt-12 py-5 bg-white border-t border-gray-200 text-center text-gray-600 text-sm">
        <p>Al-Qur'an Digital Project | Dibuat dengan PHP, Tailwind CSS, dan API EQuran.id</p>
    </footer>
    
    <script>
        const searchInput = document.getElementById('search-input');
        const contentSurah = document.getElementById('content-surah');
        const contentJuz = document.getElementById('content-juz');
        const tabSurah = document.getElementById('tab-surah');
        const tabJuz = document.getElementById('tab-juz');
        const surahItems = document.querySelectorAll('.surah-item');
        const juzItems = document.querySelectorAll('.juz-item');
        const lastReadCard = document.getElementById('last-read-card');
        const lastReadLink = document.getElementById('last-read-link');
        
        let activeTab = 'surah';

        const loadLastRead = () => {
            const lastRead = localStorage.getItem('lastRead');
            if (lastRead) {
                const data = JSON.parse(lastRead);
                lastReadCard.classList.remove('hidden');
                document.getElementById('last-read-surah').textContent = data.surahName;
                document.getElementById('last-read-ayat').textContent = `Ayat ${data.ayatNumber}`;
                lastReadLink.href = `?id=${data.surahId}#ayat-${data.ayatNumber}`;
            }
        };

        const filterItems = (query) => {
            const items = activeTab === 'surah' ? surahItems : juzItems;
            items.forEach(item => {
                const searchData = item.getAttribute('data-search');
                if (searchData.includes(query.toLowerCase()) || query.trim() === '') {
                    item.style.display = 'block';
                } else {
                    item.style.display = 'none';
                }
            });
        };

        const switchTab = (tab) => {
            activeTab = tab;
            const isSurah = tab === 'surah';

            contentSurah.classList.toggle('hidden', !isSurah);
            contentJuz.classList.toggle('hidden', isSurah);

            tabSurah.classList.toggle('border-quran-accent', isSurah);
            tabSurah.classList.toggle('text-quran-primary', isSurah);
            tabSurah.classList.toggle('border-transparent', !isSurah);
            tabSurah.classList.toggle('text-gray-500', !isSurah);
            
            tabJuz.classList.toggle('border-quran-accent', !isSurah);
            tabJuz.classList.toggle('text-quran-primary', !isSurah);
            tabJuz.classList.toggle('border-transparent', isSurah);
            tabJuz.classList.toggle('text-gray-500', isSurah);

            searchInput.value = '';
            filterItems('');
        };

        tabSurah.addEventListener('click', () => switchTab('surah'));
        tabJuz.addEventListener('click', () => switchTab('juz'));
        searchInput.addEventListener('keyup', (e) => filterItems(e.target.value));

        document.addEventListener('DOMContentLoaded', () => {
            loadLastRead();
            switchTab('surah');
        });
    </script>
</body>
</html>
<?php
} else {
    // =======================================================
    // TAMPILAN UNTUK DETAIL SURAH (SURAH VIEW)
    // =======================================================
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $nama_latin ?> - Al-Qur'an Modern</title>

    <script src="https://cdn.tailwindcss.com"></script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Amiri:wght@400;700&family=Noto+Naskh+Arabic:wght@400;700&family=Scheherazade+New:wght@400;700&family=Reem+Kufi+Ink:wght@400;700&family=Aref+Ruqaa:wght@400;700&family=Inter:wght@400;700&family=Poppins:wght@400;700&family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    
    <script>
        tailwind.config = {
            darkMode: 'class', 
            theme: {
                extend: {
                    colors: {
                        'quran-primary': '#1D5D1D', 
                        'quran-accent': '#38A169',
                        'dark-bg': '#111827',     
                        'dark-card': '#1F2937',
                        'tajwid-idgham': '#9B59B6', 
                        'tajwid-ghunnah': '#27AE60',
                        'tajwid-mad': '#3498DB', 
                        'tajwid-qalqalah': '#F1C40F',
                        'tajwid-ikhfa': '#E74C3C', 
                    },
                    fontFamily: {
                        'latin-sans': ['Inter', 'sans-serif'],
                        'latin-serif': ['Georgia', 'serif'],
                        'latin-mono': ['monospace'],
                        'latin-poppins': ['Poppins', 'sans-serif'],
                        'latin-roboto': ['Roboto', 'sans-serif'],
                        'arabic-amiri': ['Amiri', 'serif'],
                        'arabic-naskh': ['Noto Naskh Arabic', 'serif'],
                        'arabic-kufi': ['Scheherazade New', 'serif'],
                        'arabic-reem': ['Reem Kufi Ink', 'serif'],
                        'arabic-ruqaa': ['Aref Ruqaa', 'serif'],
                    }
                }
            }
        }
    </script>

    <style>
        :root {
            --latin-font: 'Inter', sans-serif;
            --arabic-font: 'Amiri', serif;
            --arabic-font-size: 2.0rem; 
        }
        
        .font-latin-dynamic { font-family: var(--latin-font); }
        .font-arabic-dynamic { 
            font-family: var(--arabic-font);
            direction: rtl;
        }
        .text-ayat-arabic {
            font-size: var(--arabic-font-size);
            line-height: 3.5rem;
            text-align: right;
            user-select: none;
            transition: font-size 0.2s; 
        }
        .tajwid-idgham { color: theme('colors.tajwid-idgham'); }
        .tajwid-ghunnah { color: theme('colors.tajwid-ghunnah'); }
        .tajwid-mad { color: theme('colors.tajwid-mad'); }
        .tajwid-qalqalah { color: theme('colors.tajwid-qalqalah'); }
        .tajwid-ikhfa { color: theme('colors.tajwid-ikhfa'); }
        
        .hide-transliteration .transliteration-text { display: none !important; }
        .hide-translation .translation-text { display: none !important; }
        .hide-tajwid .text-ayat-arabic span[class*="tajwid-"] { color: inherit !important; }
        .play-ayat-btn { background-color: transparent; border: none; }
    </style>
</head>

<body class="font-latin-dynamic bg-gray-100 dark:bg-dark-bg transition-colors duration-300">

    <audio id="audio-player" src="<?= $audio_full_url ?>" preload="none"></audio>
    <audio id="ayat-audio-player" preload="none"></audio>

    <header class="bg-white dark:bg-dark-card shadow-sm sticky top-0 z-20 border-b border-gray-200 dark:border-gray-700">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            
            <a href="?id=" class="text-quran-accent hover:text-quran-primary text-sm font-medium flex items-center space-x-1">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                </svg>
                <span>Daftar Surah</span>
            </a>

            <div class="flex items-center space-x-6 text-gray-700 dark:text-gray-300">
                
                <div class="flex space-x-2 text-sm font-medium">
                    <a href="?id=<?= $prev_surah_id ?>" 
                       class="nav-link <?= $prev_surah_id ? 'hover:text-quran-accent' : 'opacity-40 cursor-default' ?>">
                       &lt;
                    </a>
                    <span class="text-quran-primary dark:text-white font-bold">QS. <?= $nomor_surah ?></span>
                    <a href="?id=<?= $next_surah_id ?>" 
                       class="nav-link <?= $next_surah_id ? 'hover:text-quran-accent' : 'opacity-40 cursor-default' ?>">
                       &gt;
                    </a>
                </div>

                <button id="settings-toggle" class="text-quran-accent hover:text-quran-primary p-2 rounded-full transition-colors duration-200">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 0 0 2.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 0 0 1.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 0 0-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 0 0-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 0 0-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 0 0-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 0 0 1.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065Z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                    </svg>
                    <span class="sr-only">Pengaturan</span>
                </button>
            </div>
        </div>
    </header>
    
    <div id="settings-panel" class="hidden fixed top-0 right-0 h-full w-full max-w-xs bg-white dark:bg-gray-800 shadow-2xl p-6 z-30 transition-transform duration-300 transform translate-x-full">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-xl font-bold text-gray-800 dark:text-white">Pengaturan</h3>
            <button id="close-settings" class="text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <div class="space-y-6">
            <div class="p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                <label for="font-size-slider" class="block text-sm font-medium mb-2 text-gray-700 dark:text-gray-300">Ukuran Font</label>
                <input type="range" min="1.5" max="3.5" step="0.25" value="2.0" id="font-size-slider" class="w-full h-2 bg-quran-accent rounded-lg appearance-none cursor-pointer dark:bg-gray-600">
            </div>

            <div class="flex justify-between items-center p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                <span class="text-gray-700 dark:text-gray-300 font-medium">Quran Tajweed</span>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" id="tajwid-toggle" class="sr-only peer">
                    <div class="w-11 h-6 bg-gray-300 peer-checked:after:translate-x-full peer-checked:bg-quran-primary dark:bg-gray-600 rounded-full after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border after:border-gray-300 after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-500"></div>
                </label>
            </div>
            
            <div class="flex justify-between items-center p-3 bg-gray-50 dark:bg-gray-700 rounded-lg opacity-50 cursor-not-allowed">
                <span class="text-gray-700 dark:text-gray-300 font-medium">Quran Isyarat</span>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" disabled class="sr-only peer">
                    <div class="w-11 h-6 bg-gray-300 rounded-full after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border after:border-gray-300 after:rounded-full after:h-5 after:w-5 after:transition-all"></div>
                </label>
            </div>

            <div class="flex justify-between items-center p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                <span class="text-gray-700 dark:text-gray-300 font-medium">Transliterasi (Latin)</span>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" id="transliteration-toggle" class="sr-only peer">
                    <div class="w-11 h-6 bg-gray-300 peer-checked:after:translate-x-full peer-checked:bg-quran-primary dark:bg-gray-600 rounded-full after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border after:border-gray-300 after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-500"></div>
                </label>
            </div>

            <div class="flex justify-between items-center p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                <span class="text-gray-700 dark:text-gray-300 font-medium">Terjemahan Bahasa</span>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" id="translation-toggle" checked class="sr-only peer">
                    <div class="w-11 h-6 bg-gray-300 peer-checked:after:translate-x-full peer-checked:bg-quran-primary dark:bg-gray-600 rounded-full after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border after:border-gray-300 after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-500"></div>
                </label>
            </div>
            
            <div class="p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                <span class="block text-sm font-medium mb-2 text-gray-700 dark:text-gray-300">Pilih Bahasa Terjemah</span>
                <div class="flex flex-wrap gap-2">
                    <button class="lang-btn bg-quran-accent text-white px-3 py-1 rounded-full text-xs font-semibold" data-lang="ID">ID</button>
                    <button class="lang-btn bg-gray-200 text-gray-700 px-3 py-1 rounded-full text-xs font-semibold" data-lang="EN">EN</button>
                    <button class="lang-btn bg-gray-200 text-gray-700 px-3 py-1 rounded-full text-xs font-semibold" data-lang="MY">MY</button>
                </div>
            </div>

            <div class="p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                <label for="qari-select-settings" class="block text-sm font-medium mb-1 text-gray-700 dark:text-gray-300">Pilih Qori</label>
                <select id="qari-select-settings" class="w-full p-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                    <?php foreach($qari_list as $key => $name): ?>
                        <option value="<?= $key ?>">
                            <?= $name ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="flex justify-between items-center p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                <span class="text-gray-700 dark:text-gray-300 font-medium">Tema Gelap</span>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" id="theme-toggle" class="sr-only peer">
                    <div class="w-11 h-6 bg-gray-300 peer-checked:after:translate-x-full peer-checked:bg-quran-primary dark:bg-gray-600 rounded-full after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border after:border-gray-300 after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-500"></div>
                </label>
            </div>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 p-4 sticky top-16 z-10 shadow-md transition-colors duration-300">
        <div class="max-w-4xl mx-auto flex justify-between items-center">
            <span id="current-qari-display" class="font-semibold text-sm text-gray-700 dark:text-gray-300">
                Murottal: <?= $qari_list[$default_qari] ?>
            </span>
            <button id="play-pause-btn" 
                    class="bg-quran-primary text-white p-2 rounded-full flex items-center shadow-lg hover:bg-quran-accent transition-colors duration-200 disabled:opacity-50"
                    <?= $audio_full_url ? '' : 'disabled' ?>>
                <span id="audio-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.348a1.125 1.125 0 0 1 0 1.972l-11.54 6.347a1.125 1.125 0 0 1-1.667-.985V5.653Z" />
                    </svg>
                </span>
                <span id="audio-status" class="ml-2 font-medium">Putar Surah</span>
            </button>
        </div>
    </div>


    <div class="container mx-auto p-4 sm:p-6 lg:p-8 max-w-4xl">
        
        <div class="text-center mb-8">
            <p class="font-arabic-dynamic text-3xl font-bold text-gray-900 dark:text-white"><?= $nama_arab ?></p>
            <h1 class="text-xl font-bold text-gray-800 dark:text-gray-200 mt-1"><?= $nama_latin ?></h1>
            <p class="text-sm text-gray-500 dark:text-gray-500"><?= $tempat_turun ?> &bull; <?= $jumlah_ayat ?> Ayat</p>
        </div>
        
        <?php if ($nomor_surah_int != 1 && $nomor_surah_int != 9): ?>
        <div class="text-center my-8">
            <p class="font-arabic-dynamic text-3xl text-gray-900 dark:text-gray-100">بِسْمِ اللَّهِ الرَّحْمَنِ الرَّحِيم</p>
        </div>
        <?php endif; ?>


        <div class="space-y-8" id="ayat-container">
            <?php foreach ($ayat as $a): ?>
                <div class="p-6 rounded-lg shadow-sm border border-gray-200 bg-white dark:bg-dark-card dark:border-gray-700 transition-colors duration-300" id="ayat-<?= $a['nomorAyat'] ?>">
                    
                    <div class="flex justify-between items-center mb-4">
                        <div class="flex items-center space-x-3 text-gray-500 dark:text-gray-400">
                             <button class="play-ayat-btn hover:text-quran-accent transition-colors duration-150" data-ayat="<?= $a['nomorAyat'] ?>">
                                 <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 12a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z" />
                                </svg>
                             </button>
                             <span class="text-sm font-semibold"><?= htmlspecialchars($a['nomorAyat']) ?></span>
                        </div>
                         
                        <button class="text-gray-500 dark:text-gray-400 hover:text-quran-accent transition-colors duration-150">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.75a.75.75 0 1 0 0 1.5.75.75 0 0 0 0-1.5ZM12 12.75a.75.75 0 1 0 0 1.5.75.75 0 0 0 0-1.5ZM12 18.75a.75.75 0 1 0 0 1.5.75.75 0 0 0 0-1.5Z" />
                            </svg>
                        </button>
                    </div>

                    <p class="font-arabic-dynamic text-ayat-arabic mb-3 text-gray-900 dark:text-gray-100 leading-relaxed" data-ayat-nomor="<?= $a['nomorAyat'] ?>">
                        <?php 
                            $teks_arab = htmlspecialchars($a['teksArab']);
                            $words = explode(' ', $teks_arab);
                            
                            foreach ($words as $index => $word) {
                                $class = 'word';
                                if ($nomor_surah == 1 && $a['nomorAyat'] == 4 && $index == 1) { $class .= ' tajwid-ghunnah'; } 
                                elseif ($nomor_surah == 1 && $a['nomorAyat'] == 7 && $index == 5) { $class .= ' tajwid-mad'; }
                                
                                echo "<span class='$class'>$word</span> ";
                            }
                        ?>
                    </p>
                    
                    <p class="transliteration-text text-quran-accent dark:text-quran-accent text-sm italic font-medium" data-ayat-nomor="<?= $a['nomorAyat'] ?>">
                        <?= htmlspecialchars($a['teksLatin'] ?? '') ?>
                    </p>

                    <p class="translation-text text-base text-gray-700 dark:text-gray-300 mt-3 border-t border-dashed border-gray-200 dark:border-gray-700 pt-3">
                        <?= htmlspecialchars($a['teksIndonesia']) ?>
                    </p>

                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <footer class="mt-12 py-5 bg-quran-primary text-center text-white text-sm dark:bg-gray-900">
        <p>Web Al-Qur'an Modern | Dibuat dengan PHP dan Tailwind CSS</p>
    </footer>

    <script>
        const html = document.documentElement;
        const qariList = <?= json_encode($qari_list) ?>;
        const surahNumberPadded = '<?= $surah_number_padded ?>';
        const surahAudioBaseUrl = 'https://cdn.equran.id/audio-full/'; 
        
        const themeToggle = document.getElementById('theme-toggle');
        const qariSelectSettings = document.getElementById('qari-select-settings');
        const fontSizeSlider = document.getElementById('font-size-slider');
        const tajwidToggle = document.getElementById('tajwid-toggle');
        const transliterationToggle = document.getElementById('transliteration-toggle');
        const translationToggle = document.getElementById('translation-toggle');
        const ayatAudioPlayer = document.getElementById('ayat-audio-player');
        
        const settingsToggle = document.getElementById('settings-toggle');
        const settingsPanel = document.getElementById('settings-panel');
        const closeSettings = document.getElementById('close-settings');
        
        const toggleVisibility = () => {
            html.classList.toggle('hide-transliteration', !transliterationToggle.checked);
            html.classList.toggle('hide-translation', !translationToggle.checked);
            html.classList.toggle('hide-tajwid', !tajwidToggle.checked);

            localStorage.setItem('showTransliteration', transliterationToggle.checked);
            localStorage.setItem('showTranslation', translationToggle.checked);
            localStorage.setItem('showTajwid', tajwidToggle.checked);
        };
        
        const updateFontSize = (size) => {
            document.documentElement.style.setProperty('--arabic-font-size', `${size}rem`);
            localStorage.setItem('arabicFontSize', size);
        };
        
        const updateAudioQari = (qariId) => {
            localStorage.setItem('selectedQari', qariId);
        };

        const playAyat = (ayatNumber) => {
            const qariId = qariSelectSettings.value;
            let qariPath;
            if (qariId === '01') qariPath = 'Abdullah-Al-Juhany';
            else if (qariId === '02') qariPath = 'Abdul-Muhsin-Al-Qasim';
            else if (qariId === '03') qariPath = 'Abdurrahman-as-Sudais';
            else if (qariId === '04') qariPath = 'Ibrahim-Al-Dossari';
            else if (qariId === '05') qariPath = 'Misyari-Rasyid-Al-Afasy';
            
            const ayatPadded = String(ayatNumber).padStart(3, '0');
            const newUrl = `${surahAudioBaseUrl}${qariPath}/${surahNumberPadded}${ayatPadded}.mp3`;
            
            ayatAudioPlayer.src = newUrl;
            ayatAudioPlayer.play();
            
            document.querySelectorAll('.play-ayat-btn').forEach(btn => {
                btn.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 12a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z" /></svg>`;
            });
            const currentBtn = document.querySelector(`.play-ayat-btn[data-ayat="${ayatNumber}"]`);
            if(currentBtn) {
                 currentBtn.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-quran-accent"><path stroke-linecap="round" stroke-linejoin="round" d="M14.25 9v6m-4.5-6v6" /></svg>`;
            }
            
            ayatAudioPlayer.addEventListener('ended', () => {
                 currentBtn.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 12a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z" /></svg>`;
            }, { once: true });
        };
        

        const initSettings = () => {
            const savedFontSize = localStorage.getItem('arabicFontSize') || '2.0';
            fontSizeSlider.value = savedFontSize;
            updateFontSize(savedFontSize);
            fontSizeSlider.addEventListener('input', (e) => updateFontSize(e.target.value));

            transliterationToggle.checked = localStorage.getItem('showTransliteration') !== 'false';
            translationToggle.checked = localStorage.getItem('showTranslation') !== 'false';
            tajwidToggle.checked = localStorage.getItem('showTajwid') === 'true';

            transliterationToggle.addEventListener('change', toggleVisibility);
            translationToggle.addEventListener('change', toggleVisibility);
            tajwidToggle.addEventListener('change', toggleVisibility);
            
            toggleVisibility();

            const savedTheme = localStorage.getItem('theme');
            if (savedTheme === 'dark' || (!savedTheme && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                html.classList.add('dark');
                themeToggle.checked = true;
            }
            themeToggle.addEventListener('change', () => {
                if (themeToggle.checked) { html.classList.add('dark'); localStorage.setItem('theme', 'dark'); } 
                else { html.classList.remove('dark'); localStorage.setItem('theme', 'light'); }
            });
            
            const savedQari = localStorage.getItem('selectedQari') || '<?= $default_qari ?>';
            qariSelectSettings.value = savedQari;
            updateAudioQari(savedQari);
            qariSelectSettings.addEventListener('change', (e) => updateAudioQari(e.target.value));

            document.querySelectorAll('.play-ayat-btn').forEach(btn => {
                btn.addEventListener('click', (e) => {
                     const ayatNum = e.currentTarget.getAttribute('data-ayat');
                     playAyat(ayatNum);
                });
            });

            settingsToggle.addEventListener('click', () => {
                settingsPanel.classList.remove('translate-x-full', 'hidden');
                settingsPanel.classList.add('translate-x-0');
            });

            closeSettings.addEventListener('click', () => {
                settingsPanel.classList.remove('translate-x-0');
                settingsPanel.classList.add('translate-x-full', 'hidden');
            });
            
            const saveLastRead = (ayatNum = 1) => {
                const surahId = '<?= $nomor_surah ?>';
                const surahName = '<?= addslashes($nama_latin) ?>';
                const lastReadData = {
                    surahId: surahId,
                    surahName: surahName,
                    ayatNumber: ayatNum
                };
                localStorage.setItem('lastRead', JSON.stringify(lastReadData));
            };
            
            if (window.location.hash) {
                const targetId = window.location.hash.substring(1);
                const targetElement = document.getElementById(targetId);
                if (targetElement) {
                     targetElement.scrollIntoView({ behavior: 'smooth', block: 'start' });
                     saveLastRead(targetId.replace('ayat-', '')); 
                }
            } else {
                 saveLastRead();
            }
        };
        
        document.addEventListener('DOMContentLoaded', initSettings);
    </script>
</body>
</html>
<?php
}