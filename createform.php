<?php
require_once 'koneksi.php';
check_login();

$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $pdo->beginTransaction();
        
        // Basic report information
        $report_name = sanitize_input($_POST['report_name']);
        $training_title = sanitize_input($_POST['training_title']);
        $training_date_start = $_POST['training_date_start'];
        $training_date_end = $_POST['training_date_end'];
        
        // Generate PDF filename
        $pdf_filename = 'Laporan_' . str_replace(' ', '_', $report_name) . '.pdf';
        
        // Insert main report
        $stmt = $pdo->prepare("
            INSERT INTO training_reports (report_name, pdf_filename, training_title, training_date_start, training_date_end, created_by) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$report_name, $pdf_filename, $training_title, $training_date_start, $training_date_end, $_SESSION['user_id']]);
        $report_id = $pdo->lastInsertId();
        
        if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] == 0) {
            $upload_dir = 'uploads/covers';
            $cover_filename = upload_file($_FILES['cover_image'], $upload_dir, ['jpg', 'jpeg', 'png'], 'cover', $report_name);
            
            $stmt = $pdo->prepare("
                INSERT INTO uploaded_files (report_id, file_type, file_name, file_path) 
                VALUES (?, 'cover', ?, ?)
            ");
            $stmt->execute([$report_id, $_FILES['cover_image']['name'], 'uploads/' . sanitize_filename($report_name) . '/covers/' . $cover_filename]);
            
            // Update report with cover image
            $stmt = $pdo->prepare("UPDATE training_reports SET cover_image = ? WHERE id = ?");
            $stmt->execute([$cover_filename, $report_id]);
        }
        
        // Handle participants
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
        
        // Handle attendance dates
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
        
        // Handle attendance records
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
            // Get documentation sections count
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
                    $uploaded_files = upload_documentation_files($section_files, $report_name, $section);
                    
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
        
        // Handle other file uploads (keep existing logic for non-documentation files)
        $file_types = [
            'participant_scan' => 'uploads/participant_scans',
            'instructor_scan' => 'uploads/instructor_scans',
            'schedule_image' => 'uploads/schedules',
            'pretest_image' => 'uploads/pretests',
            'posttest_image' => 'uploads/posttests',
            'syllabus' => 'uploads/syllabus',
            'certificate' => 'uploads/certificates'
        ];
        
        foreach ($file_types as $type => $upload_dir) {
            if (isset($_FILES[$type]) && is_array($_FILES[$type]['name'])) {
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
                        $stmt->execute([$report_id, $type, $filename, 'uploads/' . sanitize_filename($report_name) . '/' . basename($upload_dir) . '/' . $uploaded_filename, $index]);
                    }
                }
            }
        }
        
        // Handle Berita Acara
        if (isset($_POST['berita_acara_content'])) {
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
        
        // Handle scores
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
        
        // Handle documentation sections
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
        $success_message = 'Laporan berhasil dibuat! <a href="reading.php?id=' . $report_id . '" target="_blank">Lihat Laporan</a>';
        
    } catch (Exception $e) {
        $pdo->rollback();
        $error_message = 'Error: ' . $e->getMessage();
    }
}

include 'header.php';
?>

<div class="container">
    <div class="card">
        <h1 style="color: #1e3a8a; margin-bottom: 2rem;">Buat Laporan Pelatihan Baru</h1>
        
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
                           placeholder="Contoh: Pengertian IC BGA" required>
                    <small style="color: #666;">Nama file PDF akan menjadi: Laporan_[Nama_Laporan].pdf</small>
                </div>
                
                <div class="form-group">
                    <label for="cover_image">Upload Cover Laporan *</label>
                    <input type="file" id="cover_image" name="cover_image" class="form-control" 
                           accept="image/*" required>
                    <small style="color: #666;">Cover akan menutupi satu halaman penuh PDF</small>
                </div>
                
                <div class="form-group">
                    <label for="training_title">Judul Pelatihan *</label>
                    <input type="text" id="training_title" name="training_title" class="form-control" required>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label for="training_date_start">Tanggal Mulai Pelatihan *</label>
                        <input type="date" id="training_date_start" name="training_date_start" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="training_date_end">Tanggal Selesai Pelatihan *</label>
                        <input type="date" id="training_date_end" name="training_date_end" class="form-control" required>
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
                    <div class="participant-row" style="display: grid; grid-template-columns: 50px 1fr 1fr 50px; gap: 1rem; margin-bottom: 1rem; align-items: end;">
                        <div>1</div>
                        <div><input type="text" name="participant_names[]" class="form-control" placeholder="Nama peserta" required></div>
                        <div><input type="text" name="participant_institutions[]" class="form-control" placeholder="Institusi" required></div>
                        <div><button type="button" class="btn btn-danger" onclick="removeParticipant(this)">×</button></div>
                    </div>
                </div>
                
                <button type="button" class="btn" onclick="addParticipant()">+ Tambah Peserta</button>
            </div>

            <!-- Section 2: Daftar Hadir -->
            <div style="background: #f8f9fa; padding: 1.5rem; border-radius: 8px; margin-bottom: 2rem;">
                <h3 style="color: #1e3a8a; margin-bottom: 1rem;">2. Daftar Hadir Pelatihan</h3>
                
                <div style="margin-bottom: 1rem;">
                    <h4>Tanggal Pelatihan</h4>
                    <div id="attendanceDates">
                        <div class="date-row" style="display: grid; grid-template-columns: 1fr 1fr 50px; gap: 1rem; margin-bottom: 1rem; align-items: end;">
                            <div>
                                <label>Hari</label>
                                <input type="text" name="attendance_days[]" class="form-control" placeholder="Selasa" required>
                            </div>
                            <div>
                                <label>Tanggal</label>
                                <input type="date" name="attendance_dates[]" class="form-control" required>
                            </div>
                            <div><button type="button" class="btn btn-danger" onclick="removeDateRow(this)">×</button></div>
                        </div>
                    </div>
                    <button type="button" class="btn" onclick="addAttendanceDate()">+ Tambah Tanggal</button>
                </div>
                
                <div style="margin-top: 2rem;">
                    <h4>Kehadiran Peserta</h4>
                    <div id="attendanceTable">
                        <p style="color: #666; font-style: italic;">Tabel kehadiran akan muncul setelah Anda menambahkan peserta dan tanggal.</p>
                    </div>
                </div>
            </div>

            <!-- File Uploads for Attendance -->
            <div style="background: #f8f9fa; padding: 1.5rem; border-radius: 8px; margin-bottom: 2rem;">
                <h3 style="color: #1e3a8a; margin-bottom: 1rem;">Scan Absensi</h3>
                
                <div class="form-group">
                    <label>Scan Absensi Peserta (minimal 1 foto) *</label>
                    <input type="file" name="participant_scan[]" class="form-control" accept="image/*" multiple required>
                </div>
                
                <div class="form-group">
                    <label>Scan Absensi Instruktur (minimal 1 foto) *</label>
                    <input type="file" name="instructor_scan[]" class="form-control" accept="image/*" multiple required>
                </div>
            </div>

            <!-- Section 3: Berita Acara -->
            <div style="background: #f8f9fa; padding: 1.5rem; border-radius: 8px; margin-bottom: 2rem;">
                <h3 style="color: #1e3a8a; margin-bottom: 1rem;">3. Berita Acara</h3>
                
                <div class="form-group">
                    <label for="berita_acara_content">Isi Berita Acara</label>
                    <textarea id="berita_acara_content" name="berita_acara_content" class="form-control" 
                              rows="5" placeholder="Masukkan isi berita acara..."></textarea>
                </div>
                
                <div class="form-group">
                    <label>A. Mode Kehadiran Peserta</label>
                    <div style="display: flex; gap: 1rem; margin-top: 0.5rem;">
                        <label><input type="radio" name="participant_mode" value="online" required> Online</label>
                        <label><input type="radio" name="participant_mode" value="offline" required> Offline</label>
                        <label><input type="radio" name="participant_mode" value="hybrid" required> Hybrid</label>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>B. Jadwal Kegiatan (Upload Foto minimal 1) *</label>
                    <input type="file" name="schedule_image[]" class="form-control" accept="image/*" multiple required>
                </div>
                
                <div class="form-group">
                    <label for="trainer_name">C. Nama Trainer Pelatihan *</label>
                    <input type="text" id="trainer_name" name="trainer_name" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>Mode Pelatihan</label>
                    <div style="display: flex; gap: 1rem; margin-top: 0.5rem;">
                        <label><input type="radio" name="training_mode" value="online" required> Online</label>
                        <label><input type="radio" name="training_mode" value="offline" required> Offline</label>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="trainer_description">Deskripsi Trainer</label>
                    <textarea id="trainer_description" name="trainer_description" class="form-control" 
                              rows="3" placeholder="Deskripsi tentang trainer..."></textarea>
                </div>
            </div>

            <!-- Section 4: Hasil PreTest dan PostTest -->
            <div style="background: #f8f9fa; padding: 1.5rem; border-radius: 8px; margin-bottom: 2rem;">
                <h3 style="color: #1e3a8a; margin-bottom: 1rem;">4. Hasil PreTest dan PostTest</h3>
                
                <div id="scoresSection">
                    <p style="color: #666; font-style: italic;">Tabel nilai akan muncul setelah Anda menambahkan peserta.</p>
                </div>
                
                <div style="margin-top: 2rem;">
                    <div class="form-group">
                        <label>Upload Foto Tabel PreTest (minimal 1) *</label>
                        <input type="file" name="pretest_image[]" class="form-control" accept="image/*" multiple required>
                    </div>
                    
                    <div class="form-group">
                        <label>Upload Foto Tabel PostTest (minimal 1) *</label>
                        <input type="file" name="posttest_image[]" class="form-control" accept="image/*" multiple required>
                    </div>
                </div>
            </div>

            <!-- Section 5: Dokumentasi -->
            <div style="background: #f8f9fa; padding: 1.5rem; border-radius: 8px; margin-bottom: 2rem;">
                <h3 style="color: #1e3a8a; margin-bottom: 1rem;">5. Dokumentasi</h3>
                
                <div id="documentationSections">
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
                </div>
                
                <button type="button" class="btn" onclick="addDocumentationSection()">+ Tambah Dokumentasi</button>
            </div>

            <!-- Section 6: Syllabus -->
            <div style="background: #f8f9fa; padding: 1.5rem; border-radius: 8px; margin-bottom: 2rem;">
                <h3 style="color: #1e3a8a; margin-bottom: 1rem;">6. Syllabus & Schedule</h3>
                
                <div class="form-group">
                    <label>Upload Foto Syllabus (minimal 1) *</label>
                    <input type="file" name="syllabus[]" class="form-control" accept="image/*" multiple required>
                </div>
            </div>

            <!-- Section 7: Certificate -->
            <div style="background: #f8f9fa; padding: 1.5rem; border-radius: 8px; margin-bottom: 2rem;">
                <h3 style="color: #1e3a8a; margin-bottom: 1rem;">7. Sertifikat Pelatihan</h3>
                
                <div class="form-group">
                    <label>Upload Foto Sertifikat *</label>
                    <input type="file" name="certificate[]" class="form-control" accept="image/*" multiple required>
                </div>
            </div>

            <div class="text-center">
                <button type="submit" class="btn btn-success" style="font-size: 1.2rem; padding: 1rem 3rem;">
                    Generate Laporan
                </button>
            </div>
        </form>
    </div>
</div>

<script>
let participantCount = 1;
let docSectionCount = 1;

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
