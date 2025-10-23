<?php
require_once 'koneksi.php';
check_login();

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: dashboard.php");
    exit();
}

$report_id = intval($_GET['id']);

// Verify ownership
try {
    $stmt = $pdo->prepare("SELECT * FROM training_reports WHERE id = ? AND created_by = ?");
    $stmt->execute([$report_id, $_SESSION['user_id']]);
    $report = $stmt->fetch();
    
    if (!$report) {
        header("Location: dashboard.php");
        exit();
    }
} catch (PDOException $e) {
    header("Location: dashboard.php");
    exit();
}

// Handle deletion confirmation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirm_delete'])) {
    try {
        $pdo->beginTransaction();
        
        // Get all uploaded files to delete from filesystem
        try {
            $stmt = $pdo->prepare("SELECT file_path FROM uploaded_files WHERE report_id = ?");
            $stmt->execute([$report_id]);
            $files = $stmt->fetchAll();
            
            // Delete files from filesystem
            foreach ($files as $file) {
                if (file_exists($file['file_path'])) {
                    unlink($file['file_path']);
                }
            }
        } catch (PDOException $e) {
            // Table might not exist, continue
        }
        
        $tables_with_report_id = [
            'uploaded_files',
            'documentation_sections', 
            'scores',
            'berita_acara',
            'attendance',
            'attendance_dates',
            'participants'
        ];
        
        // Delete from tables that reference report_id with better error handling
        foreach ($tables_with_report_id as $table) {
            try {
                // First check if table exists
                $check_table = $pdo->prepare("SHOW TABLES LIKE ?");
                $check_table->execute([$table]);
                
                if ($check_table->rowCount() > 0) {
                    // Check if report_id column exists
                    $check_column = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE 'report_id'");
                    $check_column->execute();
                    
                    if ($check_column->rowCount() > 0) {
                        // Table and column exist, safe to delete
                        $stmt = $pdo->prepare("DELETE FROM `$table` WHERE report_id = ?");
                        $stmt->execute([$report_id]);
                    } else {
                        // Try with 'id' column as fallback for some tables
                        $check_id_column = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE 'id'");
                        $check_id_column->execute();
                        
                        if ($check_id_column->rowCount() > 0) {
                            $stmt = $pdo->prepare("DELETE FROM `$table` WHERE id = ?");
                            $stmt->execute([$report_id]);
                        }
                    }
                }
            } catch (PDOException $e) {
                // Log the error but continue with other tables
                error_log("Warning: Could not delete from table $table: " . $e->getMessage());
            }
        }
        
        // Delete the main training report (uses 'id' not 'report_id')
        $stmt = $pdo->prepare("DELETE FROM training_reports WHERE id = ?");
        $stmt->execute([$report_id]);
        
        $pdo->commit();
        
        // Redirect with success message
        header("Location: dashboard.php?deleted=1");
        exit();
        
    } catch (Exception $e) {
        $pdo->rollback();
        $error_message = 'Error deleting report: ' . $e->getMessage();
    }
}

include 'header.php';
?>

<div class="container">
    <div class="card">
        <div style="text-align: center; padding: 2rem;">
            <div style="font-size: 4rem; color: #e74c3c; margin-bottom: 1rem;">⚠️</div>
            <h1 style="color: #e74c3c; margin-bottom: 1rem;">Konfirmasi Penghapusan</h1>
            <p style="font-size: 1.1rem; margin-bottom: 2rem; color: #666;">
                Apakah Anda yakin ingin menghapus laporan berikut?
            </p>
            
            <div style="background: #f8f9fa; padding: 1.5rem; border-radius: 8px; margin-bottom: 2rem; text-align: left;">
                <h3 style="color: #1e3a8a; margin-bottom: 0.5rem;">
                    <?php echo htmlspecialchars($report['report_name']); ?>
                </h3>
                <p style="color: #666; margin-bottom: 0.5rem;">
                    <strong>Judul Pelatihan:</strong> <?php echo htmlspecialchars($report['training_title']); ?>
                </p>
                <p style="color: #666; margin-bottom: 0.5rem;">
                    <strong>Tanggal Pelatihan:</strong> 
                    <?php echo date('d F Y', strtotime($report['training_date_start'])); ?> - 
                    <?php echo date('d F Y', strtotime($report['training_date_end'])); ?>
                </p>
                <p style="color: #666;">
                    <strong>Dibuat:</strong> <?php echo date('d F Y H:i', strtotime($report['created_at'])); ?>
                </p>
            </div>
            
            <?php if (isset($error_message)): ?>
                <div class="alert alert-error" style="margin-bottom: 2rem;">
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>
            
            <div style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 1rem; border-radius: 5px; margin-bottom: 2rem;">
                <p style="color: #856404; margin: 0;">
                    <strong>Peringatan:</strong> Tindakan ini tidak dapat dibatalkan. 
                    Semua data termasuk peserta, nilai, dokumentasi, dan file yang diupload akan dihapus permanen.
                </p>
            </div>
            
            <form method="POST" style="display: inline-block;">
                <input type="hidden" name="confirm_delete" value="1">
                <button type="submit" class="btn btn-danger" style="margin-right: 1rem; font-size: 1.1rem; padding: 1rem 2rem;"
                        onclick="return confirm('Apakah Anda benar-benar yakin? Tindakan ini tidak dapat dibatalkan!')">
                    Ya, Hapus Laporan
                </button>
            </form>
            
            <a href="dashboard.php" class="btn" style="font-size: 1.1rem; padding: 1rem 2rem;">
                Batal
            </a>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
