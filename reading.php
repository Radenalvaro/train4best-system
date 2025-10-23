<?php
require_once 'koneksi.php';
check_login();

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: dashboard.php");
    exit();
}

$report_id = intval($_GET['id']);

try {
    // Get main report data
    $stmt = $pdo->prepare("
        SELECT tr.*, u.username as created_by_name 
        FROM training_reports tr 
        LEFT JOIN users u ON tr.created_by = u.id 
        WHERE tr.id = ? AND tr.created_by = ?
    ");
    $stmt->execute([$report_id, $_SESSION['user_id']]);
    $report = $stmt->fetch();
    
    if (!$report) {
        header("Location: dashboard.php");
        exit();
    }
    
    // Get participants
    $stmt = $pdo->prepare("SELECT * FROM participants WHERE report_id = ? ORDER BY sort_order");
    $stmt->execute([$report_id]);
    $participants = $stmt->fetchAll();
    
    // Get attendance dates
    $stmt = $pdo->prepare("SELECT * FROM attendance_dates WHERE report_id = ? ORDER BY sort_order");
    $stmt->execute([$report_id]);
    $attendance_dates = $stmt->fetchAll();
    
    // Get attendance records
    $stmt = $pdo->prepare("
        SELECT a.*, p.participant_name 
        FROM attendance a 
        LEFT JOIN participants p ON a.participant_id = p.id 
        WHERE a.report_id = ?
    ");
    $stmt->execute([$report_id]);
    $attendance_records = $stmt->fetchAll();
    
    // Get uploaded files
    $stmt = $pdo->prepare("SELECT * FROM uploaded_files WHERE report_id = ? ORDER BY file_type, section_id");
    $stmt->execute([$report_id]);
    $uploaded_files = $stmt->fetchAll();
    
    // Group files by type
    $files_by_type = [];
    foreach ($uploaded_files as $file) {
        $files_by_type[$file['file_type']][] = $file;
    }
    
    // Get berita acara
    $stmt = $pdo->prepare("SELECT * FROM berita_acara WHERE report_id = ?");
    $stmt->execute([$report_id]);
    $berita_acara = $stmt->fetch();
    
    // Get scores
    $stmt = $pdo->prepare("
        SELECT s.*, p.participant_name, p.institution 
        FROM scores s 
        LEFT JOIN participants p ON s.participant_id = p.id 
        WHERE s.report_id = ? 
        ORDER BY p.sort_order
    ");
    $stmt->execute([$report_id]);
    $scores = $stmt->fetchAll();
    
    // Get documentation sections
    $stmt = $pdo->prepare("SELECT * FROM documentation_sections WHERE report_id = ? ORDER BY sort_order");
    $stmt->execute([$report_id]);
    $documentation_sections = $stmt->fetchAll();
    
    // Get certificates
    $stmt = $pdo->prepare("SELECT * FROM uploaded_files WHERE report_id = ? AND file_type = 'certificate' ORDER BY section_id");
    $stmt->execute([$report_id]);
    $certificates = $stmt->fetchAll();
    
} catch (PDOException $e) {
    die("Error loading report: " . $e->getMessage());
}

function display_images($files, $base_path = '') {
    if (empty($files)) return '';
    
    $html = '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin: 1rem 0;">';
    foreach ($files as $file) {
        $file_path = $file['file_path'];
        $html .= '<div style="text-align: center;">';
        if (file_exists($file_path)) {
            $html .= '<img src="' . $file_path . '" style="max-width: 100%; height: auto; border: 1px solid #ddd; border-radius: 5px;" alt="Gambar">';
        } else {
            $html .= '<div style="padding: 20px; border: 2px dashed #ccc; text-align: center; color: #666;">Gambar tidak ditemukan</div>';
        }
        $html .= '</div>';
    }
    $html .= '</div>';
    return $html;
}

function display_images_2x2($files, $base_path = '') {
    if (empty($files)) return '';
    
    usort($files, function($a, $b) {
        return intval($a['section_id']) - intval($b['section_id']);
    });
    
    $html = '<div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.5rem; margin: 2rem 0; max-width: 100%;">';
    
    // Limit to first 4 images for perfect 2x2 grid
    $limited_files = array_slice($files, 0, 4);
    
    foreach ($limited_files as $index => $file) {
        $file_path = $file['file_path'];
        
        $html .= '<div style="text-align: center; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 8px rgba(0,0,0,0.1);">';
        $html .= '<div style="aspect-ratio: 4/3; overflow: hidden; border-radius: 10px; border: 2px solid #e0e0e0;">';
        
        if (file_exists($file_path)) {
            $html .= '<img src="' . $file_path . '" style="width: 100%; height: 100%; object-fit: cover;" alt="Dokumentasi ' . ($index + 1) . '">';
        } else {
            $html .= '<div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; background-color: #f8f9fa; color: #666; font-size: 0.8rem; padding: 10px; text-align: center;">
                        <div>Gambar tidak ditemukan</div>
                      </div>';
        }
        $html .= '</div>';
        $html .= '<div style="margin-top: 0.75rem; font-size: 1rem; color: #333; font-weight: 600; background-color: #f8f9fa; padding: 0.5rem; border-radius: 5px;">Gambar ' . ($index + 1) . '</div>';
        $html .= '</div>';
    }
    
    // If less than 4 images, add empty placeholders to maintain grid structure
    $remaining_slots = 4 - count($limited_files);
    for ($i = 0; $i < $remaining_slots; $i++) {
        $placeholder_number = count($limited_files) + $i + 1;
        $html .= '<div style="text-align: center; border-radius: 10px; overflow: hidden;">';
        $html .= '<div style="aspect-ratio: 4/3; border: 2px dashed #d0d0d0; border-radius: 10px; display: flex; align-items: center; justify-content: center; background-color: #fafafa; color: #999; font-size: 0.9rem;">Slot Kosong</div>';
        $html .= '<div style="margin-top: 0.75rem; font-size: 1rem; color: #999; font-weight: 600; background-color: #f0f0f0; padding: 0.5rem; border-radius: 5px;">Gambar ' . $placeholder_number . '</div>';
        $html .= '</div>';
    }
    
    $html .= '</div>';
    return $html;
}

function display_images_single($files, $base_path = '') {
    if (empty($files)) return '';
    
    $html = '<div style="display: flex; flex-direction: column; gap: 1.5rem; margin: 1rem 0;">';
    foreach ($files as $file) {
        $file_path = $file['file_path'];
        $html .= '<div style="text-align: center;">';
        if (file_exists($file_path)) {
            $html .= '<img src="' . $file_path . '" style="max-width: 100%; height: auto; border: 1px solid #ddd; border-radius: 5px; margin-bottom: 0.5rem;" alt="Gambar">';
        } else {
            $html .= '<div style="padding: 20px; border: 2px dashed #ccc; text-align: center; color: #666; margin-bottom: 0.5rem;">Gambar tidak ditemukan</div>';
        }
        $html .= '</div>';
    }
    $html .= '</div>';
    return $html;
}

function display_certificates($files, $base_path = '') {
    if (empty($files)) return '';
    
    $html = '<div class="section">';
    $html .= '<h3>Sertifikat Pelatihan</h3>';
    $html .= '<div class="certificate-container">';
    foreach ($files as $index => $file) {
        if (is_array($file)) {
            $file_path = $file['file_path'];
            $section_id = isset($file['section_id']) ? $file['section_id'] : $index;
        } else {
            $file_path = $base_path . $file;
            $section_id = $index;
        }
        
        $html .= '<div class="certificate-item" style="margin-bottom: 30px; page-break-inside: avoid;">';
        if (file_exists($file_path)) {
            $html .= '<img src="' . $file_path . '" alt="Sertifikat ' . ($section_id + 1) . '" style="width: 100%; max-width: 800px; height: auto; border-radius: 10px; box-shadow: 0 6px 12px rgba(0,0,0,0.15); display: block; margin: 0 auto;">';
        } else {
            $html .= '<div style="padding: 40px; text-align: center; background-color: #f8f9fa; border-radius: 10px;">Sertifikat tidak ditemukan</div>';
        }
        $html .= '</div>';
    }
    $html .= '</div>';
    $html .= '</div>';
    return $html;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($report['report_name']); ?> - Train4Best</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js" integrity="sha512-GsLlZN/3F2ErC5ifS5QtgpiJtWd43JWSuIgh7mbzZ8zBps+dvLusV+eNQATqgA/HdeKFVgA5v3S/cIrLF7QnIg==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Times New Roman', serif;
            line-height: 1.6;
            color: #333;
            background-color: #f5f5f5;
        }

        .container {
            max-width: 210mm;
            margin: 0 auto;
            background: white;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }

        .print-button:hover {
            background: #1e3a8a;
        }

        #invoice {
            padding: 0;
            background: white;
        }

        /* Cover Page */
        .cover-page {
            width: 210mm;
            height: 297mm;
            display: flex;
            align-items: center;
            justify-content: center;
            page-break-after: always;
            position: relative;
            margin: 0;
            padding: 0;
            overflow: hidden;
        }

        .cover-image {
            width: 210mm;
            height: 297mm;
            object-fit: cover;
            object-position: center;
        }

        /* Content Pages */
        .content-page {
            padding: 20mm;
            min-height: 257mm;
            position: relative;
        }

        .page-header {
            text-align: center;
            margin-bottom: 2rem;
            border-bottom: 2px solid #1e3a8a;
            padding-bottom: 1rem;
        }

        .page-header h1 {
            color: #1e3a8a;
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
        }

        .page-header h2 {
            color: #666;
            font-size: 1.2rem;
            font-weight: normal;
        }

        /* Table of Contents */
        .toc {
            margin: 2rem 0;
        }

        .toc h3 {
            color: #1e3a8a;
            margin-bottom: 1rem;
            font-size: 1.3rem;
        }

        .toc-item {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px dotted #ccc;
        }

        /* Tables */
        .report-table {
            width: 100%;
            border-collapse: collapse;
            margin: 1rem 0;
            font-size: 0.9rem;
        }

        .report-table th {
            background-color: #1e3a8a !important;
            color: white !important;
            padding: 0.75rem;
            text-align: left;
            font-weight: bold;
            border: 1px solid #1e3a8a;
            -webkit-print-color-adjust: exact !important;
            color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        .report-table td {
            padding: 0.75rem;
            border: 1px solid #ddd;
            vertical-align: top;
        }

        .report-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        /* Sections */
        .section {
            margin: 2rem 0;
            page-break-inside: avoid;
        }

        .section h3 {
            color: #1e3a8a;
            font-size: 1.2rem;
            margin-bottom: 1rem;
            border-left: 4px solid #1e3a8a;
            padding-left: 1rem;
        }

        .section h4 {
            color: #555;
            font-size: 1rem;
            margin: 1rem 0 0.5rem 0;
        }

        /* Images */
        .image-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin: 1rem 0;
        }

        .image-item {
            text-align: center;
        }

        .image-item img {
            max-width: 100%;
            height: auto;
            border: 1px solid #ddd;
            border-radius: 3px;
        }

        .image-caption {
            font-size: 0.8rem;
            color: #333;
            margin-top: 0.5rem;
            font-style: italic;
            font-weight: bold;
        }

        /* Certificates */
        .certificate-container {
            display: flex;
            flex-direction: column;
            gap: 30px;
            align-items: center;
        }

        .certificate-item {
            margin-bottom: 30px;
            page-break-inside: avoid;
            width: 100%;
            text-align: center;
        }

        .certificate-item img {
            width: 100%;
            max-width: 800px;
            height: auto;
            border-radius: 10px;
            box-shadow: 0 6px 12px rgba(0,0,0,0.15);
            display: block;
            margin: 0 auto;
        }

        .certificate-caption {
            text-align: center;
            margin-top: 15px;
            font-weight: bold;
            font-size: 16px;
        }

        /* Footer */
        .page-footer {
            position: absolute;
            bottom: 15mm;
            left: 20mm;
            right: 20mm;
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: #999;
            font-size: 0.8rem;
            border-top: 1px solid #eee;
            padding-top: 0.5rem;
        }

        .footer-left {
            text-align: left;
        }

        .footer-right {
            text-align: right;
        }

        /* Page breaks */
        .page-break {
            page-break-before: always;
        }

        /* Print styles */
        @media print {
            .print-button {
                display: none;
            }
            
            body {
                background: white;
                margin: 0;
                padding: 0;
                -webkit-print-color-adjust: exact !important;
                color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            
            .container {
                box-shadow: none;
                max-width: none;
                margin: 0;
                padding: 0;
            }
            
            .report-table th {
                background-color: #1e3a8a !important;
                color: white !important;
                -webkit-print-color-adjust: exact !important;
                color-adjust: exact !important;
                print-color-adjust: exact !important;
                background: #1e3a8a !important;
            }
            
            .cover-page {
                width: 210mm;
                height: 297mm;
                margin: 0 !important;
                padding: 0 !important;
                border: none !important;
                box-shadow: none !important;
            }
            
            .cover-image {
                width: 210mm !important;
                height: 297mm !important;
                margin: 0 !important;
                padding: 0 !important;
                border: none !important;
            }
            
            @page:first {
                margin: 0;
                padding: 0;
            }
        }
    </style>
</head>
<body>

    <div class="container">
        <div id="invoice">
            <!-- Cover Page -->
            <?php if (isset($files_by_type['cover']) && !empty($files_by_type['cover'])): ?>
            <div class="cover-page">
                <img src="<?php echo $files_by_type['cover'][0]['file_path']; ?>" 
                     alt="Cover" class="cover-image">
            </div>
            <?php endif; ?>

            <!-- Table of Contents -->
            <div class="content-page">
                <div class="page-header">
                    <h1><?php echo htmlspecialchars($report['report_name']); ?></h1>
                    <h2><?php echo htmlspecialchars($report['training_title']); ?></h2>
                </div>

                <div class="toc">
                    <h3>DAFTAR ISI</h3>
                    <div class="toc-item"><span>1. Daftar Peserta Pelatihan</span><span>3</span></div>
                    <div class="toc-item"><span>2. Daftar Hadir Pelatihan</span><span>4</span></div>
                    <div class="toc-item"><span>3. Berita Acara</span><span>5</span></div>
                    <div class="toc-item"><span>4. Hasil Pre Test dan Post Test</span><span>6</span></div>
                    <div class="toc-item"><span>5. Dokumentasi</span><span>7</span></div>
                    <div class="toc-item"><span>6. Syllabus & Schedule</span><span>8</span></div>
                    <div class="toc-item"><span>7. Sertifikat Pelatihan</span><span>9</span></div>
                </div>

                <div class="toc">
                    <h3>DAFTAR GRAFIK</h3>
                    <div class="toc-item"><span>Grafik 1. Grade Ranges Pretest Peserta Pelatihan <?php echo htmlspecialchars($report['training_title']); ?></span><span>6</span></div>
                    <div class="toc-item"><span>Grafik 2. Grade Ranges Post Test Peserta Pelatihan <?php echo htmlspecialchars($report['training_title']); ?></span><span>6</span></div>
                </div>

                <div class="page-footer">
                    <div class="footer-left">
                        <div style="font-weight: bold;">Train4Best</div>
                        <div>PT Kekar Karya Indonesia</div>
                    </div>
                    <div class="footer-right">2</div>
                </div>
            </div>

            <!-- Section 1: Daftar Peserta -->
            <div class="content-page page-break">
                <div class="section">
                    <h3>1. DAFTAR PESERTA PELATIHAN</h3>
                    
                    <p><strong>Judul Pelatihan:</strong> <?php echo htmlspecialchars($report['training_title']); ?></p>
                    <p><strong>Tanggal Pelatihan:</strong> 
                        <?php echo date('d F Y', strtotime($report['training_date_start'])); ?> - 
                        <?php echo date('d F Y', strtotime($report['training_date_end'])); ?>
                    </p>

                    <table class="report-table">
                        <thead>
                            <tr>
                                <th>No.</th>
                                <th>Nama</th>
                                <th>Institusi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($participants as $index => $participant): ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td><?php echo htmlspecialchars($participant['participant_name']); ?></td>
                                <td><?php echo htmlspecialchars($participant['institution']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="page-footer">
                    <div class="footer-left">
                        <div style="font-weight: bold;">Train4Best</div>
                        <div>PT Kekar Karya Indonesia</div>
                    </div>
                    <div class="footer-right">3</div>
                </div>
            </div>

            <!-- Section 2: Daftar Hadir -->
            <div class="content-page page-break">
                <div class="section">
                    <h3>2. DAFTAR HADIR PELATIHAN</h3>
                    
                    <?php if (!empty($attendance_dates)): ?>
                    <table class="report-table">
                        <thead>
                            <tr>
                                <th>No.</th>
                                <th>Nama</th>
                                <?php foreach ($attendance_dates as $date): ?>
                                <th><?php echo htmlspecialchars($date['day_name']); ?><br>
                                    <small><?php echo date('d M Y', strtotime($date['attendance_date'])); ?></small>
                                </th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($participants as $index => $participant): ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td><?php echo htmlspecialchars($participant['participant_name']); ?></td>
                                <?php foreach ($attendance_dates as $date): ?>
                                <td>
                                    <?php
                                    $status = 'TIDAK HADIR';
                                    foreach ($attendance_records as $record) {
                                        if ($record['participant_id'] == $participant['id'] && 
                                            $record['attendance_date'] == $date['attendance_date']) {
                                            $status = $record['status'];
                                            break;
                                        }
                                    }
                                    echo $status;
                                    ?>
                                </td>
                                <?php endforeach; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>

                    <!-- Scan Absensi -->
                    <?php if (isset($files_by_type['participant_scan'])): ?>
                    <h4>Gambar 1. Scan Absensi Peserta <?php echo htmlspecialchars($report['training_title']); ?></h4>
                    <?php echo display_images($files_by_type['participant_scan']); ?>
                    <?php endif; ?>

                    <?php if (isset($files_by_type['instructor_scan'])): ?>
                    <h4>Gambar 2. Scan Absensi Instruktur</h4>
                    <?php echo display_images($files_by_type['instructor_scan']); ?>
                    <?php endif; ?>
                </div>

                <div class="page-footer">
                    <div class="footer-left">
                        <div style="font-weight: bold;">Train4Best</div>
                        <div>PT Kekar Karya Indonesia</div>
                    </div>
                    <div class="footer-right">4</div>
                </div>
            </div>

            <!-- Section 3: Berita Acara -->
            <div class="content-page page-break">
                <div class="section">
                    <h3>3. BERITA ACARA</h3>
                    
                    <?php if ($berita_acara): ?>
                    <p style="text-align: justify; margin-bottom: 1.5rem;">
                        <?php echo nl2br(htmlspecialchars($berita_acara['content'])); ?>
                    </p>

                    <p><strong>A. Jumlah Peserta:</strong> 
                        <?php echo count($participants); ?> peserta hadir pada lokasi 
                        (<?php echo ucfirst($berita_acara['participant_mode']); ?>)
                    </p>

                    <h4>B. Jadwal Kegiatan:</h4>
                    <?php if (isset($files_by_type['schedule_image'])): ?>
                        <?php echo display_images($files_by_type['schedule_image']); ?>
                    <?php endif; ?>

                    <h4>C. Instruktur:</h4>
                    <p><strong>Trainer Pelatihan:</strong> <?php echo htmlspecialchars($berita_acara['trainer_name']); ?></p>
                    <p><strong>Mode Pelatihan:</strong> <?php echo ucfirst($berita_acara['training_mode']); ?></p>
                    <?php if ($berita_acara['trainer_description']): ?>
                    <p style="text-align: justify;">
                        <?php echo nl2br(htmlspecialchars($berita_acara['trainer_description'])); ?>
                    </p>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>

                <div class="page-footer">
                    <div class="footer-left">
                        <div style="font-weight: bold;">Train4Best</div>
                        <div>PT Kekar Karya Indonesia</div>
                    </div>
                    <div class="footer-right">5</div>
                </div>
            </div>

            <!-- Section 4: Hasil PreTest dan PostTest -->
            <div class="content-page page-break">
                <div class="section">
                    <h3>4. HASIL PRETEST DAN POSTTEST</h3>
                    
                    <h4>Nilai Pelatihan - <?php echo htmlspecialchars($report['training_title']); ?></h4>
                    
                    <?php if (!empty($scores)): ?>
                    <table class="report-table">
                        <thead>
                            <tr>
                                <th>Nama</th>
                                <th>Institusi</th>
                                <th>PreTest</th>
                                <th>PostTest</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($scores as $score): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($score['participant_name']); ?></td>
                                <td><?php echo htmlspecialchars($score['institution']); ?></td>
                                <td><?php echo $score['pretest_score'] ? number_format($score['pretest_score'], 1) : '-'; ?></td>
                                <td><?php echo $score['posttest_score'] ? number_format($score['posttest_score'], 1) : '-'; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>

                    <!-- PreTest Table -->
                    <h4>Tabel 5. Hasil Pretest Peserta Pelatihan</h4>
                    <table class="report-table">
                        <thead>
                            <tr>
                                <th>Nama</th>
                                <th>Institusi</th>
                                <th>Nilai PreTest</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($scores as $score): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($score['participant_name']); ?></td>
                                <td><?php echo htmlspecialchars($score['institution']); ?></td>
                                <td><?php echo $score['pretest_score'] ? number_format($score['pretest_score'], 1) : '-'; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <?php if (isset($files_by_type['pretest_image'])): ?>
                    <?php echo display_images($files_by_type['pretest_image']); ?>
                    <?php endif; ?>

                    <!-- PostTest Table -->
                    <h4>Tabel 6. Hasil Post Test Peserta Pelatihan <?php echo htmlspecialchars($report['training_title']); ?></h4>
                    <table class="report-table">
                        <thead>
                            <tr>
                                <th>Nama</th>
                                <th>Institusi</th>
                                <th>Nilai PostTest</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($scores as $score): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($score['participant_name']); ?></td>
                                <td><?php echo htmlspecialchars($score['institution']); ?></td>
                                <td><?php echo $score['posttest_score'] ? number_format($score['posttest_score'], 1) : '-'; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <?php if (isset($files_by_type['posttest_image'])): ?>
                    <?php echo display_images($files_by_type['posttest_image']); ?>
                    <?php endif; ?>
                </div>

                <div class="page-footer">
                    <div class="footer-left">
                        <div style="font-weight: bold;">Train4Best</div>
                        <div>PT Kekar Karya Indonesia</div>
                    </div>
                    <div class="footer-right">6</div>
                </div>
            </div>

            <!-- Section 5: Dokumentasi -->
            <div class="content-page page-break">
                <div class="section">
                    <h3>5. DOKUMENTASI</h3>
                    
                    <?php 
                    if (!empty($documentation_sections)): 
                        foreach ($documentation_sections as $index => $doc_section):
                            $section_files = [];
                            if (isset($files_by_type['documentation'])) {
                                foreach ($files_by_type['documentation'] as $file) {
                                    if (intval($file['section_id']) == $index) {
                                        $section_files[] = $file;
                                    }
                                }
                            }
                    ?>
                    
                    <h4>Dokumentasi Pelatihan <?php echo htmlspecialchars($report['training_title']); ?> - <?php echo ($index + 1); ?></h4>
                    <p><strong>Hari/Tanggal:</strong> <?php echo date('l, d F Y', strtotime($doc_section['section_date'])); ?></p>
                    <p><strong>Lokasi:</strong> <?php echo htmlspecialchars($doc_section['location']); ?></p>
                    
                    <?php 
                    if (!empty($section_files)) {
                        echo display_images_2x2($section_files);
                    } else {
                        echo '<div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.5rem; margin: 2rem 0; max-width: 100%;">';
                        for ($i = 1; $i <= 4; $i++) {
                            echo '<div style="text-align: center; border-radius: 10px; overflow: hidden;">';
                            echo '<div style="aspect-ratio: 4/3; border: 2px dashed #d0d0d0; border-radius: 10px; display: flex; align-items: center; justify-content: center; background-color: #fafafa; color: #999; font-size: 0.9rem;">Slot Kosong</div>';
                            echo '<div style="margin-top: 0.75rem; font-size: 1rem; color: #999; font-weight: 600; background-color: #f0f0f0; padding: 0.5rem; border-radius: 5px;">Gambar ' . $i . '</div>';
                            echo '</div>';
                        }
                        echo '</div>';
                    }
                    ?>
                    
                    <?php 
                        endforeach;
                    else: 
                        if (isset($files_by_type['documentation']) && !empty($files_by_type['documentation'])): 
                    ?>
                    
                    <h4>Dokumentasi Pelatihan <?php echo htmlspecialchars($report['training_title']); ?> - 1</h4>
                    <p><strong>Hari/Tanggal:</strong> <?php echo date('l, d F Y', strtotime($report['training_date_start'])); ?></p>
                    <p><strong>Lokasi:</strong> <?php echo htmlspecialchars($report['training_location'] ?? 'jakarta'); ?></p>
                    
                    <?php 
                    echo display_images_2x2($files_by_type['documentation']); 
                    ?>
                    
                    <?php else: ?>
                    
                    <h4>Dokumentasi Pelatihan <?php echo htmlspecialchars($report['training_title']); ?> - 1</h4>
                    <p><strong>Hari/Tanggal:</strong> <?php echo date('l, d F Y', strtotime($report['training_date_start'])); ?></p>
                    <p><strong>Lokasi:</strong> jakarta</p>
                    
                    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.5rem; margin: 2rem 0; max-width: 100%;">
                        <?php for ($i = 1; $i <= 4; $i++): ?>
                        <div style="text-align: center; border-radius: 10px; overflow: hidden;">
                            <div style="aspect-ratio: 4/3; border: 2px dashed #d0d0d0; border-radius: 10px; display: flex; align-items: center; justify-content: center; background-color: #fafafa; color: #999999; font-size: 0.9rem;">Slot Kosong</div>
                            <div style="margin-top: 0.75rem; font-size: 1rem; color: #999999; font-weight: 600; background-color: #f0f0f0; padding: 0.5rem; border-radius: 5px;">Gambar <?php echo $i; ?></div>
                        </div>
                        <?php endfor; ?>
                    </div>
                    
                    <?php 
                        endif;
                    endif; 
                    ?>
                </div>

                <div class="page-footer">
                    <div class="footer-left">
                        <div style="font-weight: bold;">Train4Best</div>
                        <div>PT Kekar Karya Indonesia</div>
                    </div>
                    <div class="footer-right">7</div>
                </div>
            </div>

            <!-- Section 6: Syllabus -->
            <div class="content-page page-break">
                <div class="section">
                    <h3>6. SYLLABUS & SCHEDULE</h3>
                    
                    <?php if (isset($files_by_type['syllabus'])): ?>
                    <?php 
                    echo display_images_single($files_by_type['syllabus']); 
                    ?>
                    <?php endif; ?>
                </div>

                <div class="page-footer">
                    <div class="footer-left">
                        <div style="font-weight: bold;">Train4Best</div>
                        <div>PT Kekar Karya Indonesia</div>
                    </div>
                    <div class="footer-right">8</div>
                </div>
            </div>

            <!-- Section 7: Certificate -->
            <div class="content-page page-break">
                <?php if (!empty($certificates)): ?>
                    <?php 
                    echo display_certificates($certificates); 
                    ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function download_pdf() {
            const pdf = document.getElementById("invoice");
            const filename = "<?php echo str_replace(' ', '_', $report['report_name']); ?>.pdf";
            
            const opt = {
                margin: 0,
                filename: filename,
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { scale: 2, useCORS: true },
                jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
            };
            
            html2pdf().from(pdf).set(opt).save();
        }
    </script>
</body>
</html>
