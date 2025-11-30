<?php
// ===================================================================
// Konfigurasi & Router Utama (index.php)
// File ini berfungsi sebagai Serverless Function tunggal di Vercel
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
    // Logic fetch data untuk Daftar Surah (Index View)
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
    // Logic Mapping Juz (disini agar $chapters dapat digunakan)
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
    // Logic fetch data untuk Detail Surah (Surah View)
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

// --- TAMPILAN (VIEW) ---

if ($mode === 'surah_list') {
    // Tampilkan View Daftar Surah
    include 'views/surah_list.php';
} else {
    // Tampilkan View Detail Surah
    include 'views/surah_detail.php';
}
?>
