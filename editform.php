<?php
require_once 'koneksi.php';
check_login();

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: dashboard.php");
    exit();
}

$report_id = intval($_GET['id']);
$success_message = '';
$error_message = '';

// Load existing report data
try {
    // Get main report data
    $stmt = $pdo->prepare("SELECT * FROM training_reports WHERE id = ? AND created_by = ?");
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
    $stmt = $pdo->prepare("SELECT * FROM attendance WHERE report_id = ?");
    $stmt->execute([$report_id]);
    $attendance_records = $stmt->fetchAll();
    
    // Get berita acara
    $stmt = $pdo->prepare("SELECT * FROM berita_acara WHERE report_id = ?");
    $stmt->execute([$report_id]);
    $berita_acara = $stmt->fetch();
    
    // Get scores
    $stmt = $pdo->prepare("SELECT * FROM scores WHERE report_id = ? ORDER BY participant_id");
    $stmt->execute([$report_id]);
    $scores = $stmt->fetchAll();
    
    // Get documentation sections
    $stmt = $pdo->prepare("SELECT * FROM documentation_sections WHERE report_id = ? ORDER BY sort_order");
    $stmt->execute([$report_id]);
    $documentation_sections = $stmt->fetchAll();
    
} catch (PDOException $e) {
    die("Error loading report: " . $e->getMessage());
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $pdo->beginTransaction();
        
        // Update basic report information
        $report_name = sanitize_input($_POST['report_name']);
        $training_title = sanitize_input($_POST['training_title']);
        $training_date_start = $_POST['training_date_start'];
        $training_date_end = $_POST['training_date_end'];
        
        // Generate new PDF filename if report name changed
        $pdf_filename = 'Laporan_' . str_replace(' ', '_', $report_name) . '.pdf';
        
        $stmt = $pdo->prepare("
            UPDATE training_reports 
            SET report_name = ?, pdf_filename = ?, training_title = ?, 
                training_date_start = ?, training_date_end = ?, updated_at = CURRENT_TIMESTAMP 
            WHERE id = ?
        ");
        $stmt->execute([$report_name, $pdf_filename, $training_title, $training_date_start, $training_date_end, $report_id]);
        
        if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] == 0) {
            $upload_dir = 'covers';
            $cover_filename = upload_file($_FILES['cover_image'], $upload_dir, ['jpg', 'jpeg', 'png'], 'cover', $report_name);
            
            // Delete old cover file
            $stmt = $pdo->prepare("SELECT file_path FROM uploaded_files WHERE report_id = ? AND file_type = 'cover'");
            $stmt->execute([$report_id]);
            $old_cover = $stmt->fetch();
            if ($old_cover && file_exists($old_cover['file_path'])) {
                unlink($old_cover['file_path']);
            }
            
            // Update or insert new cover
            $stmt = $pdo->prepare("DELETE FROM uploaded_files WHERE report_id = ? AND file_type = 'cover'");
            $stmt->execute([$report_id]);
            
            $stmt = $pdo->prepare("
                INSERT INTO uploaded_files (report_id, file_type, file_name, file_path) 
                VALUES (?, 'cover', ?, ?)
            ");
            $stmt->execute([$report_id, $_FILES['cover_image']['name'], 'uploads/' . sanitize_filename($report_name) . '/covers/' . $cover_filename]);
            
            $stmt = $pdo->prepare("UPDATE training_reports SET cover_image = ? WHERE id = ?");
            $stmt->execute([$cover_filename, $report_id]);
        }
        
        // Clear and re-insert participants
        $stmt = $pdo->prepare("DELETE FROM participants WHERE report_id = ?");
        $stmt->execute([$report_id]);
        
        if (isset($_POST['participant_names']) && is_array($_POST['participant_names'])) {
            foreach ($_POST['participant_names'] as $index => $name) {
                if (!empty($name) && !empty($_POST['participant_institutions'][$index])) {
                    $stmt = $pdo->prepare("
                        INSERT INTO participants (report_id, participant_name, institution, sort_order) 
                        VALUES (?, ?, ?, ?)
                    ");
                    $stmt->execute([$report_id, sanitize_input($name), sanitize_input($_POST['participant_institutions'][$index]), $index]);
                }
            }
        }
        
        // Clear and re-insert attendance dates
        $stmt = $pdo->prepare("DELETE FROM attendance_dates WHERE report_id = ?");
        $stmt->execute([$report_id]);
        $stmt = $pdo->prepare("DELETE FROM attendance WHERE report_id = ?");
        $stmt->execute([$report_id]);
        
        if (isset($_POST['attendance_dates']) && is_array($_POST['attendance_dates'])) {
            foreach ($_POST['attendance_dates'] as $index => $date) {
                if (!empty($date) && !empty($_POST['attendance_days'][$index])) {
                    $stmt = $pdo->prepare("
                        INSERT INTO attendance_dates (report_id, attendance_date, day_name, sort_order) 
                        VALUES (?, ?, ?, ?)
                    ");
                    $stmt->execute([$report_id, $date, sanitize_input($_POST['attendance_days'][$index]), $index]);
                }
            }
        }
        
        // Re-insert attendance records
        if (isset($_POST['attendance']) && is_array($_POST['attendance'])) {
            $participants = $pdo->prepare("SELECT id FROM participants WHERE report_id = ? ORDER BY sort_order");
            $participants->execute([$report_id]);
            $participant_list = $participants->fetchAll();
            
            $dates = $pdo->prepare("SELECT id, attendance_date FROM attendance_dates WHERE report_id = ? ORDER BY sort_order");
            $dates->execute([$report_id]);
            $date_list = $dates->fetchAll();
            
            foreach ($participant_list as $p_index => $participant) {
                foreach ($date_list as $d_index => $date) {
                    $status = isset($_POST['attendance'][$p_index][$d_index]) ? 'HADIR' : 'TIDAK HADIR';
                    $stmt = $pdo->prepare("
                        INSERT INTO attendance (report_id, participant_id, attendance_date, status) 
                        VALUES (?, ?, ?, ?)
                    ");
                    $stmt->execute([$report_id, $participant['id'], $date['attendance_date'], $status]);
                }
            }
        }
        
        // Handle documentation uploads with organized folder structure
        if (isset($_FILES['documentation']) && is_array($_FILES['documentation']['name'])) {
            $has_new_files = false;
            foreach ($_FILES['documentation']['name'] as $index => $filename) {
                if ($_FILES['documentation']['error'][$index] == 0) {
                    $has_new_files = true;
                    break;
                }
            }
            
            if ($has_new_files) {
                // Delete old documentation files
                $stmt = $pdo->prepare("SELECT file_path FROM uploaded_files WHERE report_id = ? AND file_type = 'documentation'");
                $stmt->execute([$report_id]);
                $old_files = $stmt->fetchAll();
                foreach ($old_files as $old_file) {
                    if (file_exists($old_file['file_path'])) {
                        unlink($old_file['file_path']);
                    }
                }
                
                $stmt = $pdo->prepare("DELETE FROM uploaded_files WHERE report_id = ? AND file_type = 'documentation'");
                $stmt->execute([$report_id]);
                
                // Upload new documentation files with organized structure
                $doc_sections_count = isset($_POST['doc_dates']) ? count($_POST['doc_dates']) : 1;
                
                // Group files by documentation section (4 files per section)
                $files_per_section = [];
                $total_files = count($_FILES['documentation']['name']);
                $files_per_doc = ceil($total_files / $doc_sections_count);
                
                for ($section = 0; $section < $doc_sections_count; $section++) {
                    $section_files = [
                        'name' => [],
                        'type' => [],
                        'tmp_name' => [],
                        'error' => [],
                        'size' => []
                    ];
                    
                    $start_index = $section * $files_per_doc;
                    $end_index = min($start_index + 4, $total_files); // Max 4 files per section
                    
                    for ($i = $start_index; $i < $end_index; $i++) {
                        if (isset($_FILES['documentation']['name'][$i]) && $_FILES['documentation']['error'][$i] == 0) {
                            $section_files['name'][] = $_FILES['documentation']['name'][$i];
                            $section_files['type'][] = $_FILES['documentation']['type'][$i];
                            $section_files['tmp_name'][] = $_FILES['documentation']['tmp_name'][$i];
                            $section_files['error'][] = $_FILES['documentation']['error'][$i];
                            $section_files['size'][] = $_FILES['documentation']['size'][$i];
                        }
                    }
                    
                    // Upload files for this section
                    if (!empty($section_files['name'])) {
                        $uploaded_files = upload_documentation_files($section_files, $report['report_name'], $section);
                        
                        // Save to database
                        foreach ($uploaded_files as $file_info) {
                            $stmt = $pdo->prepare("
                                INSERT INTO uploaded_files (report_id, file_type, file_name, file_path, section_id) 
                                VALUES (?, 'documentation', ?, ?, ?)
                            ");
                            $stmt->execute([$report_id, $file_info['filename'], $file_info['path'], $section]);
                        }
                    }
                }
            }
        }
        
        $file_types = [
            'participant_scan' => 'participant_scans',
            'instructor_scan' => 'instructor_scans',
            'schedule_image' => 'schedules',
            'pretest_image' => 'pretests',
            'posttest_image' => 'posttests',
            'syllabus' => 'syllabus',
            'certificate' => 'certificates'
        ];
        
        foreach ($file_types as $type => $upload_dir) {
            if (isset($_FILES[$type]) && is_array($_FILES[$type]['name'])) {
                $has_new_files = false;
                foreach ($_FILES[$type]['name'] as $index => $filename) {
                    if ($_FILES[$type]['error'][$index] == 0) {
                        $has_new_files = true;
                        break;
                    }
                }
                
                if ($has_new_files) {
                    // Delete old files
                    $stmt = $pdo->prepare("SELECT file_path FROM uploaded_files WHERE report_id = ? AND file_type = ?");
                    $stmt->execute([$report_id, $type]);
                    $old_files = $stmt->fetchAll();
                    foreach ($old_files as $old_file) {
                        if (file_exists($old_file['file_path'])) {
                            unlink($old_file['file_path']);
                        }
                    }
                    
                    $stmt = $pdo->prepare("DELETE FROM uploaded_files WHERE report_id = ? AND file_type = ?");
                    $stmt->execute([$report_id, $type]);
                    
                    // Upload new files
                    foreach ($_FILES[$type]['name'] as $index => $filename) {
                        if ($_FILES[$type]['error'][$index] == 0) {
                            $file_data = [
                                'name' => $_FILES[$type]['name'][$index],
                                'type' => $_FILES[$type]['type'][$index],
                                'tmp_name' => $_FILES[$type]['tmp_name'][$index],
                                'error' => $_FILES[$type]['error'][$index],
                                'size' => $_FILES[$type]['size'][$index]
                            ];
                            
                            $uploaded_filename = upload_file($file_data, $upload_dir, ['jpg', 'jpeg', 'png', 'pdf'], $type, $report_name);
                            
                            $stmt = $pdo->prepare("
                                INSERT INTO uploaded_files (report_id, file_type, file_name, file_path, section_id) 
                                VALUES (?, ?, ?, ?, ?)
                            ");
                            $stmt->execute([$report_id, $type, $filename, 'uploads/' . sanitize_filename($report_name) . '/' . $upload_dir . '/' . $uploaded_filename, $index]);
                        }
                    }
                }
            }
        }
        
        // Update Berita Acara
        if (isset($_POST['berita_acara_content'])) {
            $stmt = $pdo->prepare("DELETE FROM berita_acara WHERE report_id = ?");
            $stmt->execute([$report_id]);
            
            $stmt = $pdo->prepare("
                INSERT INTO berita_acara (report_id, content, participant_mode, trainer_name, training_mode, trainer_description) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $report_id,
                sanitize_input($_POST['berita_acara_content']),
                sanitize_input($_POST['participant_mode']),
                sanitize_input($_POST['trainer_name']),
                sanitize_input($_POST['training_mode']),
                sanitize_input($_POST['trainer_description'])
            ]);
        }
        
        // Update scores
        $stmt = $pdo->prepare("DELETE FROM scores WHERE report_id = ?");
        $stmt->execute([$report_id]);
        
        if (isset($_POST['pretest_scores']) && is_array($_POST['pretest_scores'])) {
            $participants = $pdo->prepare("SELECT id FROM participants WHERE report_id = ? ORDER BY sort_order");
            $participants->execute([$report_id]);
            $participant_list = $participants->fetchAll();
            
            foreach ($participant_list as $index => $participant) {
                $pretest_score = isset($_POST['pretest_scores'][$index]) ? floatval($_POST['pretest_scores'][$index]) : null;
                $posttest_score = isset($_POST['posttest_scores'][$index]) ? floatval($_POST['posttest_scores'][$index]) : null;
                
                $stmt = $pdo->prepare("
                    INSERT INTO scores (report_id, participant_id, pretest_score, posttest_score) 
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([$report_id, $participant['id'], $pretest_score, $posttest_score]);
            }
        }
        
        // Update documentation sections
        $stmt = $pdo->prepare("DELETE FROM documentation_sections WHERE report_id = ?");
        $stmt->execute([$report_id]);
        
        if (isset($_POST['doc_dates']) && is_array($_POST['doc_dates'])) {
            foreach ($_POST['doc_dates'] as $index => $date) {
                if (!empty($date) && !empty($_POST['doc_locations'][$index])) {
                    $stmt = $pdo->prepare("
                        INSERT INTO documentation_sections (report_id, section_date, location, sort_order) 
                        VALUES (?, ?, ?, ?)
                    ");
                    $stmt->execute([$report_id, $date, sanitize_input($_POST['doc_locations'][$index]), $index]);
                }
            }
        }

        $pdo->commit();
        $success_message = 'Laporan berhasil diperbarui! <a href="reading.php?id=' . $report_id . '" target="_blank">Lihat Laporan</a>';
        
        // Reload data after update
        header("Location: editform.php?id=" . $report_id . "&success=1");
        exit();
        
    } catch (Exception $e) {
        $pdo->rollback();
        $error_message = 'Error: ' . $e->getMessage();
    }
}

if (isset($_GET['success'])) {
    $success_message = 'Laporan berhasil diperbarui! <a href="reading.php?id=' . $report_id . '" target="_blank">Lihat Laporan</a>';
}

include 'header.php';
?>

<div class="container">
    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
            <h1 style="color: #1e3a8a;">Edit Laporan Pelatihan</h1>
            <div>
                <a href="reading.php?id=<?php echo $report_id; ?>" class="btn" target="_blank">Lihat Laporan</a>
                <a href="dashboard.php" class="btn btn-danger">Kembali</a>
            </div>
        </div>
        
        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success"><?php echo $success_message; ?></div>
        <?php endif; ?>
        
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-error"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" id="trainingForm">
            <!-- Basic Information -->
            <div style="background: #f8f9fa; padding: 1.5rem; border-radius: 8px; margin-bottom: 2rem;">
                <h3 style="color: #1e3a8a; margin-bottom: 1rem;">Informasi Dasar</h3>
                
                <div class="form-group">
                    <label for="report_name">Nama Laporan *</label>
                    <input type="text" id="report_name" name="report_name" class="form-control" 
                           value="<?php echo htmlspecialchars($report['report_name']); ?>" required>
                    <small style="color: #666;">Nama file PDF akan menjadi: Laporan_[Nama_Laporan].pdf</small>
                </div>
                
                <div class="form-group">
                    <label for="cover_image">Upload Cover Laporan Baru (Kosongkan jika tidak ingin mengubah)</label>
                    <input type="file" id="cover_image" name="cover_image" class="form-control" accept="image/*">
                    <?php if ($report['cover_image']): ?>
                        <small style="color: #666;">Cover saat ini: <?php echo htmlspecialchars($report['cover_image']); ?></small>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label for="training_title">Judul Pelatihan *</label>
                    <input type="text" id="training_title" name="training_title" class="form-control" 
                           value="<?php echo htmlspecialchars($report['training_title']); ?>" required>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label for="training_date_start">Tanggal Mulai Pelatihan *</label>
                        <input type="date" id="training_date_start" name="training_date_start" class="form-control" 
                               value="<?php echo $report['training_date_start']; ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="training_date_end">Tanggal Selesai Pelatihan *</label>
                        <input type="date" id="training_date_end" name="training_date_end" class="form-control" 
                               value="<?php echo $report['training_date_end']; ?>" required>
                    </div>
                </div>
            </div>

            <!-- Section 1: Daftar Peserta -->
            <div style="background: #f8f9fa; padding: 1.5rem; border-radius: 8px; margin-bottom: 2rem;">
                <h3 style="color: #1e3a8a; margin-bottom: 1rem;">1. Daftar Peserta Pelatihan</h3>
                
                <div id="participantsList">
                    <div class="participant-row" style="display: grid; grid-template-columns: 50px 1fr 1fr 50px; gap: 1rem; margin-bottom: 1rem; align-items: end;">
                        <div><strong>No.</strong></div>
                        <div><strong>Nama</strong></div>
                        <div><strong>Institusi</strong></div>
                        <div><strong>Aksi</strong></div>
                    </div>
                    <?php foreach ($participants as $index => $participant): ?>
                    <div class="participant-row" style="display: grid; grid-template-columns: 50px 1fr 1fr 50px; gap: 1rem; margin-bottom: 1rem; align-items: end;">
                        <div><?php echo $index + 1; ?></div>
                        <div><input type="text" name="participant_names[]" class="form-control" value="<?php echo htmlspecialchars($participant['participant_name']); ?>" required></div>
                        <div><input type="text" name="participant_institutions[]" class="form-control" value="<?php echo htmlspecialchars($participant['institution']); ?>" required></div>
                        <div><button type="button" class="btn btn-danger" onclick="removeParticipant(this)">×</button></div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <button type="button" class="btn" onclick="addParticipant()">+ Tambah Peserta</button>
            </div>

            <!-- Section 2: Daftar Hadir -->
            <div style="background: #f8f9fa; padding: 1.5rem; border-radius: 8px; margin-bottom: 2rem;">
                <h3 style="color: #1e3a8a; margin-bottom: 1rem;">2. Daftar Hadir Pelatihan</h3>
                
                <div style="margin-bottom: 1rem;">
                    <h4>Tanggal Pelatihan</h4>
                    <div id="attendanceDates">
                        <?php foreach ($attendance_dates as $index => $date): ?>
                        <div class="date-row" style="display: grid; grid-template-columns: 1fr 1fr 50px; gap: 1rem; margin-bottom: 1rem; align-items: end;">
                            <div>
                                <label>Hari</label>
                                <input type="text" name="attendance_days[]" class="form-control" value="<?php echo htmlspecialchars($date['day_name']); ?>" required>
                            </div>
                            <div>
                                <label>Tanggal</label>
                                <input type="date" name="attendance_dates[]" class="form-control" value="<?php echo $date['attendance_date']; ?>" required>
                            </div>
                            <div><button type="button" class="btn btn-danger" onclick="removeDateRow(this)">×</button></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="btn" onclick="addAttendanceDate()">+ Tambah Tanggal</button>
                </div>
                
                <div style="margin-top: 2rem;">
                    <h4>Kehadiran Peserta</h4>
                    <div id="attendanceTable">
                        <?php if (!empty($participants) && !empty($attendance_dates)): ?>
                            <table class="table" style="margin-top: 1rem;">
                                <thead>
                                    <tr>
                                        <th>No.</th>
                                        <th>Nama</th>
                                        <?php foreach ($attendance_dates as $date): ?>
                                        <th><?php echo htmlspecialchars($date['day_name']); ?><br><small><?php echo $date['attendance_date']; ?></small></th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($participants as $p_index => $participant): ?>
                                    <tr>
                                        <td><?php echo $p_index + 1; ?></td>
                                        <td><?php echo htmlspecialchars($participant['participant_name']); ?></td>
                                        <?php foreach ($attendance_dates as $d_index => $date): ?>
                                        <td>
                                            <?php 
                                            $is_present = false;
                                            foreach ($attendance_records as $record) {
                                                if ($record['participant_id'] == $participant['id'] && $record['attendance_date'] == $date['attendance_date'] && $record['status'] == 'HADIR') {
                                                    $is_present = true;
                                                    break;
                                                }
                                            }
                                            ?>
                                            <label><input type="checkbox" name="attendance[<?php echo $p_index; ?>][<?php echo $d_index; ?>]" <?php echo $is_present ? 'checked' : ''; ?>> HADIR</label>
                                        </td>
                                        <?php endforeach; ?>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <p style="color: #666; font-style: italic;">Tabel kehadiran akan muncul setelah Anda menambahkan peserta dan tanggal.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- File Uploads for Attendance -->
            <div style="background: #f8f9fa; padding: 1.5rem; border-radius: 8px; margin-bottom: 2rem;">
                <h3 style="color: #1e3a8a; margin-bottom: 1rem;">Scan Absensi</h3>
                
                <div class="form-group">
                    <label>Scan Absensi Peserta (minimal 1 foto) - Kosongkan jika tidak ingin mengubah</label>
                    <input type="file" name="participant_scan[]" class="form-control" accept="image/*" multiple>
                </div>
                
                <div class="form-group">
                    <label>Scan Absensi Instruktur (minimal 1 foto) - Kosongkan jika tidak ingin mengubah</label>
                    <input type="file" name="instructor_scan[]" class="form-control" accept="image/*" multiple>
                </div>
            </div>

            <!-- Section 3: Berita Acara -->
            <div style="background: #f8f9fa; padding: 1.5rem; border-radius: 8px; margin-bottom: 2rem;">
                <h3 style="color: #1e3a8a; margin-bottom: 1rem;">3. Berita Acara</h3>
                
                <div class="form-group">
                    <label for="berita_acara_content">Isi Berita Acara</label>
                    <textarea id="berita_acara_content" name="berita_acara_content" class="form-control" 
                              rows="5" placeholder="Masukkan isi berita acara..."><?php echo htmlspecialchars($berita_acara['content'] ?? ''); ?></textarea>
                </div>
                
                <div class="form-group">
                    <label>A. Mode Kehadiran Peserta</label>
                    <div style="display: flex; gap: 1rem; margin-top: 0.5rem;">
                        <label><input type="radio" name="participant_mode" value="online" <?php echo ($berita_acara && $berita_acara['participant_mode'] == 'online') ? 'checked' : ''; ?> required> Online</label>
                        <label><input type="radio" name="participant_mode" value="offline" <?php echo ($berita_acara && $berita_acara['participant_mode'] == 'offline') ? 'checked' : ''; ?> required> Offline</label>
                        <label><input type="radio" name="participant_mode" value="hybrid" <?php echo ($berita_acara && $berita_acara['participant_mode'] == 'hybrid') ? 'checked' : ''; ?> required> Hybrid</label>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>B. Jadwal Kegiatan (Upload Foto minimal 1) - Kosongkan jika tidak ingin mengubah</label>
                    <input type="file" name="schedule_image[]" class="form-control" accept="image/*" multiple>
                </div>
                
                <div class="form-group">
                    <label for="trainer_name">C. Nama Trainer Pelatihan *</label>
                    <input type="text" id="trainer_name" name="trainer_name" class="form-control" 
                           value="<?php echo htmlspecialchars($berita_acara['trainer_name'] ?? ''); ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Mode Pelatihan</label>
                    <div style="display: flex; gap: 1rem; margin-top: 0.5rem;">
                        <label><input type="radio" name="training_mode" value="online" <?php echo ($berita_acara && $berita_acara['training_mode'] == 'online') ? 'checked' : ''; ?> required> Online</label>
                        <label><input type="radio" name="training_mode" value="offline" <?php echo ($berita_acara && $berita_acara['training_mode'] == 'offline') ? 'checked' : ''; ?> required> Offline</label>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="trainer_description">Deskripsi Trainer</label>
                    <textarea id="trainer_description" name="trainer_description" class="form-control" 
                              rows="3" placeholder="Deskripsi tentang trainer..."><?php echo htmlspecialchars($berita_acara['trainer_description'] ?? ''); ?></textarea>
                </div>
            </div>

            <!-- Section 4: Hasil PreTest dan PostTest -->
            <div style="background: #f8f9fa; padding: 1.5rem; border-radius: 8px; margin-bottom: 2rem;">
                <h3 style="color: #1e3a8a; margin-bottom: 1rem;">4. Hasil PreTest dan PostTest</h3>
                
                <div id="scoresSection">
                    <?php if (!empty($participants)): ?>
                        <h4>Nilai Pelatihan</h4>
                        <table class="table">
                            <thead>
                                <tr style="background-color: #1e3a8a; color: white;">
                                    <th>Nama</th>
                                    <th>Institusi</th>
                                    <th>PreTest</th>
                                    <th>PostTest</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($participants as $index => $participant): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($participant['participant_name']); ?></td>
                                    <td><?php echo htmlspecialchars($participant['institution']); ?></td>
                                    <td>
                                        <input type="number" name="pretest_scores[]" class="form-control" min="0" max="100" step="0.1" 
                                               value="<?php echo isset($scores[$index]) ? htmlspecialchars($scores[$index]['pretest_score']) : ''; ?>" placeholder="0.0">
                                    </td>
                                    <td>
                                        <input type="number" name="posttest_scores[]" class="form-control" min="0" max="100" step="0.1" 
                                               value="<?php echo isset($scores[$index]) ? htmlspecialchars($scores[$index]['posttest_score']) : ''; ?>" placeholder="0.0">
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p style="color: #666; font-style: italic;">Tabel nilai akan muncul setelah Anda menambahkan peserta.</p>
                    <?php endif; ?>
                </div>
                
                <div style="margin-top: 2rem;">
                    <div class="form-group">
                        <label>Upload Foto Tabel PreTest (minimal 1) - Kosongkan jika tidak ingin mengubah</label>
                        <input type="file" name="pretest_image[]" class="form-control" accept="image/*" multiple>
                    </div>
                    
                    <div class="form-group">
                        <label>Upload Foto Tabel PostTest (minimal 1) - Kosongkan jika tidak ingin mengubah</label>
                        <input type="file" name="posttest_image[]" class="form-control" accept="image/*" multiple>
                    </div>
                </div>
            </div>

            <!-- Section 5: Dokumentasi -->
            <div style="background: #f8f9fa; padding: 1.5rem; border-radius: 8px; margin-bottom: 2rem;">
                <h3 style="color: #1e3a8a; margin-bottom: 1rem;">5. Dokumentasi</h3>
                
                <div id="documentationSections">
                    <?php if (!empty($documentation_sections)): ?>
                        <?php foreach ($documentation_sections as $index => $doc_section): ?>
                        <div class="doc-section" style="border: 1px solid #ddd; padding: 1rem; border-radius: 5px; margin-bottom: 1rem;">
                            <h4>Dokumentasi <?php echo $index + 1; ?></h4>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                                <div>
                                    <label>Tanggal Dokumentasi</label>
                                    <input type="date" name="doc_dates[]" class="form-control" value="<?php echo $doc_section['section_date']; ?>" required>
                                </div>
                                <div>
                                    <label>Lokasi</label>
                                    <input type="text" name="doc_locations[]" class="form-control" value="<?php echo htmlspecialchars($doc_section['location']); ?>" placeholder="Nama lokasi" required>
                                </div>
                            </div>
                            <div>
                                <label>Upload Gambar Dokumentasi (minimal 4 foto) - Kosongkan jika tidak ingin mengubah</label>
                                <input type="file" name="documentation[]" class="form-control" accept="image/*" multiple>
                            </div>
                            <button type="button" class="btn btn-danger mt-3" onclick="removeDocSection(this)">Hapus Dokumentasi</button>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="doc-section" style="border: 1px solid #ddd; padding: 1rem; border-radius: 5px; margin-bottom: 1rem;">
                            <h4>Dokumentasi 1</h4>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                                <div>
                                    <label>Tanggal Dokumentasi</label>
                                    <input type="date" name="doc_dates[]" class="form-control" required>
                                </div>
                                <div>
                                    <label>Lokasi</label>
                                    <input type="text" name="doc_locations[]" class="form-control" placeholder="Nama lokasi" required>
                                </div>
                            </div>
                            <div>
                                <label>Upload Gambar Dokumentasi (minimal 4 foto) *</label>
                                <input type="file" name="documentation[]" class="form-control" accept="image/*" multiple required>
                            </div>
                            <button type="button" class="btn btn-danger mt-3" onclick="removeDocSection(this)">Hapus Dokumentasi</button>
                        </div>
                    <?php endif; ?>
                </div>
                
                <button type="button" class="btn" onclick="addDocumentationSection()">+ Tambah Dokumentasi</button>
            </div>

            <!-- Section 6: Syllabus -->
            <div style="background: #f8f9fa; padding: 1.5rem; border-radius: 8px; margin-bottom: 2rem;">
                <h3 style="color: #1e3a8a; margin-bottom: 1rem;">6. Syllabus & Schedule</h3>
                
                <div class="form-group">
                    <label>Upload Foto Syllabus (minimal 1) - Kosongkan jika tidak ingin mengubah</label>
                    <input type="file" name="syllabus[]" class="form-control" accept="image/*" multiple>
                </div>
            </div>

            <!-- Section 7: Certificate -->
            <div style="background: #f8f9fa; padding: 1.5rem; border-radius: 8px; margin-bottom: 2rem;">
                <h3 style="color: #1e3a8a; margin-bottom: 1rem;">7. Sertifikat Pelatihan</h3>
                
                <div class="form-group">
                    <label>Upload Foto Sertifikat - Kosongkan jika tidak ingin mengubah</label>
                    <input type="file" name="certificate[]" class="form-control" accept="image/*" multiple>
                </div>
            </div>

            <div class="text-center">
                <button type="submit" class="btn btn-success" style="font-size: 1.2rem; padding: 1rem 3rem;">
                    Update Laporan
                </button>
            </div>
        </form>
    </div>
</div>

<script>
let participantCount = <?php echo count($participants); ?>;
let docSectionCount = <?php echo count($documentation_sections) > 0 ? count($documentation_sections) : 1; ?>;

function addParticipant() {
    participantCount++;
    const participantsList = document.getElementById('participantsList');
    const newRow = document.createElement('div');
    newRow.className = 'participant-row';
    newRow.style.cssText = 'display: grid; grid-template-columns: 50px 1fr 1fr 50px; gap: 1rem; margin-bottom: 1rem; align-items: end;';
    newRow.innerHTML = `
        <div>${participantCount}</div>
        <div><input type="text" name="participant_names[]" class="form-control" placeholder="Nama peserta" required></div>
        <div><input type="text" name="participant_institutions[]" class="form-control" placeholder="Institusi" required></div>
        <div><button type="button" class="btn btn-danger" onclick="removeParticipant(this)">×</button></div>
    `;
    participantsList.appendChild(newRow);
    updateAttendanceTable();
    updateScoresTable();
}

function removeParticipant(button) {
    if (document.querySelectorAll('.participant-row').length > 2) { // Keep header + at least 1 row
        button.closest('.participant-row').remove();
        updateParticipantNumbers();
        updateAttendanceTable();
        updateScoresTable();
    }
}

function updateParticipantNumbers() {
    const rows = document.querySelectorAll('.participant-row');
    rows.forEach((row, index) => {
        if (index > 0) { // Skip header row
            const numberCell = row.querySelector('div:first-child');
            numberCell.textContent = index;
        }
    });
    participantCount = rows.length - 1;
}

function addAttendanceDate() {
    const attendanceDates = document.getElementById('attendanceDates');
    const newRow = document.createElement('div');
    newRow.className = 'date-row';
    newRow.style.cssText = 'display: grid; grid-template-columns: 1fr 1fr 50px; gap: 1rem; margin-bottom: 1rem; align-items: end;';
    newRow.innerHTML = `
        <div>
            <label>Hari</label>
            <input type="text" name="attendance_days[]" class="form-control" placeholder="Rabu" required>
        </div>
        <div>
            <label>Tanggal</label>
            <input type="date" name="attendance_dates[]" class="form-control" required>
        </div>
        <div><button type="button" class="btn btn-danger" onclick="removeDateRow(this)">×</button></div>
    `;
    attendanceDates.appendChild(newRow);
    updateAttendanceTable();
}

function removeDateRow(button) {
    if (document.querySelectorAll('.date-row').length > 1) {
        button.closest('.date-row').remove();
        updateAttendanceTable();
    }
}

function updateAttendanceTable() {
    const participants = document.querySelectorAll('input[name="participant_names[]"]');
    const dates = document.querySelectorAll('input[name="attendance_dates[]"]');
    const days = document.querySelectorAll('input[name="attendance_days[]"]');
    
    if (participants.length === 0 || dates.length === 0) return;
    
    let tableHTML = '<table class="table" style="margin-top: 1rem;"><thead><tr><th>No.</th><th>Nama</th>';
    
    // Add date headers
    days.forEach((day, index) => {
        const dayValue = day.value || `Hari ${index + 1}`;
        const dateValue = dates[index].value || 'Tanggal';
        tableHTML += `<th>${dayValue}<br><small>${dateValue}</small></th>`;
    });
    
    tableHTML += '</tr></thead><tbody>';
    
    // Add participant rows
    participants.forEach((participant, pIndex) => {
        const name = participant.value || `Peserta ${pIndex + 1}`;
        tableHTML += `<tr><td>${pIndex + 1}</td><td>${name}</td>`;
        
        dates.forEach((date, dIndex) => {
            tableHTML += `<td><label><input type="checkbox" name="attendance[${pIndex}][${dIndex}]" checked> HADIR</label></td>`;
        });
        
        tableHTML += '</tr>';
    });
    
    tableHTML += '</tbody></table>';
    document.getElementById('attendanceTable').innerHTML = tableHTML;
}

function updateScoresTable() {
    const participants = document.querySelectorAll('input[name="participant_names[]"]');
    const institutions = document.querySelectorAll('input[name="participant_institutions[]"]');
    
    if (participants.length === 0) return;
    
    let tableHTML = '<h4>Nilai Pelatihan</h4><table class="table"><thead><tr style="background-color: #1e3a8a; color: white;"><th>Nama</th><th>Institusi</th><th>PreTest</th><th>PostTest</th></tr></thead><tbody>';
    
    participants.forEach((participant, index) => {
        const name = participant.value || `Peserta ${index + 1}`;
        const institution = institutions[index] ? (institutions[index].value || 'Institusi') : 'Institusi';
        
        tableHTML += `
            <tr>
                <td>${name}</td>
                <td>${institution}</td>
                <td><input type="number" name="pretest_scores[]" class="form-control" min="0" max="100" step="0.1" placeholder="0.0"></td>
                <td><input type="number" name="posttest_scores[]" class="form-control" min="0" max="100" step="0.1" placeholder="0.0"></td>
            </tr>
        `;
    });
    
    tableHTML += '</tbody></table>';
    document.getElementById('scoresSection').innerHTML = tableHTML;
}

function addDocumentationSection() {
    docSectionCount++;
    const docSections = document.getElementById('documentationSections');
    const newSection = document.createElement('div');
    newSection.className = 'doc-section';
    newSection.style.cssText = 'border: 1px solid #ddd; padding: 1rem; border-radius: 5px; margin-bottom: 1rem;';
    newSection.innerHTML = `
        <h4>Dokumentasi ${docSectionCount}</h4>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
            <div>
                <label>Tanggal Dokumentasi</label>
                <input type="date" name="doc_dates[]" class="form-control" required>
            </div>
            <div>
                <label>Lokasi</label>
                <input type="text" name="doc_locations[]" class="form-control" placeholder="Nama lokasi" required>
            </div>
        </div>
        <div>
            <label>Upload Gambar Dokumentasi (minimal 4 foto) *</label>
            <input type="file" name="documentation[]" class="form-control" accept="image/*" multiple required>
        </div>
        <button type="button" class="btn btn-danger mt-3" onclick="removeDocSection(this)">Hapus Dokumentasi</button>
    `;
    docSections.appendChild(newSection);
}

function removeDocSection(button) {
    if (document.querySelectorAll('.doc-section').length > 1) {
        button.closest('.doc-section').remove();
        updateDocSectionNumbers();
    }
}

function updateDocSectionNumbers() {
    const sections = document.querySelectorAll('.doc-section h4');
    sections.forEach((section, index) => {
        section.textContent = `Dokumentasi ${index + 1}`;
    });
    docSectionCount = sections.length;
}

// Initialize tables when participants are added
document.addEventListener('input', function(e) {
    if (e.target.name === 'participant_names[]' || e.target.name === 'participant_institutions[]') {
        updateAttendanceTable();
        updateScoresTable();
    }
    if (e.target.name === 'attendance_days[]' || e.target.name === 'attendance_dates[]') {
        updateAttendanceTable();
    }
});
</script>

<?php include 'footer.php'; ?>
