<?php
require_once 'koneksi.php';
check_login();

// Get all training reports for the current user
try {
    $stmt = $pdo->prepare("
        SELECT tr.*, u.username as created_by_name,
               COUNT(p.id) as participant_count
        FROM training_reports tr 
        LEFT JOIN users u ON tr.created_by = u.id 
        LEFT JOIN participants p ON tr.id = p.report_id
        WHERE tr.created_by = ? 
        GROUP BY tr.id
        ORDER BY tr.created_at DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $reports = $stmt->fetchAll();
} catch (PDOException $e) {
    $reports = [];
    $error_message = "Error loading reports: " . $e->getMessage();
}

include 'header.php';
?>

<div class="container">
    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
            <div>
                <h1 style="color: #1e3a8a; margin-bottom: 0.5rem;">Dashboard</h1>
                <p style="color: #666;">Selamat datang di sistem manajemen laporan pelatihan Train4Best</p>
            </div>
            <a href="createform.php" class="btn btn-success" style="font-size: 1.1rem; padding: 1rem 2rem;">
                <span style="margin-right: 0.5rem;">+</span> Create New Report
            </a>
        </div>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-error">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <?php if (empty($reports)): ?>
            <div style="text-align: center; padding: 3rem; color: #666;">
                <div style="font-size: 4rem; margin-bottom: 1rem; opacity: 0.3;">📄</div>
                <h3 style="margin-bottom: 1rem;">Belum Ada Laporan</h3>
                <p style="margin-bottom: 2rem;">Mulai dengan membuat laporan pelatihan pertama Anda.</p>
                <a href="createform.php" class="btn">Buat Laporan Baru</a>
            </div>
        <?php else: ?>
            <div style="margin-bottom: 2rem;">
                <h2 style="color: #333; margin-bottom: 1rem;">Laporan Pelatihan Anda</h2>
                <p style="color: #666;">Total: <?php echo count($reports); ?> laporan</p>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 1.5rem;">
                <?php foreach ($reports as $report): ?>
                    <div style="background: white; border: 2px solid #e1e8ed; border-radius: 10px; padding: 1.5rem; transition: all 0.3s ease; cursor: pointer;" 
                         onclick="window.open('reading.php?id=<?php echo $report['id']; ?>', '_blank')"
                         onmouseover="this.style.borderColor='#1e3a8a'; this.style.transform='translateY(-5px)'; this.style.boxShadow='0 10px 25px rgba(0,0,0,0.1)'"
                         onmouseout="this.style.borderColor='#e1e8ed'; this.style.transform='translateY(0)'; this.style.boxShadow='none'">
                        
                        <div style="display: flex; justify-content: between; align-items: flex-start; margin-bottom: 1rem;">
                            <div style="flex: 1;">
                                <h3 style="color: #1e3a8a; margin-bottom: 0.5rem; font-size: 1.2rem;">
                                    <?php echo htmlspecialchars($report['report_name']); ?>
                                </h3>
                                <?php if ($report['training_title']): ?>
                                    <p style="color: #666; margin-bottom: 0.5rem; font-size: 0.9rem;">
                                        <?php echo htmlspecialchars($report['training_title']); ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                            <div style="display: flex; gap: 0.5rem;">
                                <a href="editform.php?id=<?php echo $report['id']; ?>" 
                                   style="padding: 0.5rem; background-color: #f39c12; color: white; text-decoration: none; border-radius: 5px; font-size: 0.8rem;"
                                   onclick="event.stopPropagation();">Edit</a>
                                <a href="delete.php?id=<?php echo $report['id']; ?>" 
                                   style="padding: 0.5rem; background-color: #e74c3c; color: white; text-decoration: none; border-radius: 5px; font-size: 0.8rem;"
                                   onclick="event.stopPropagation(); return confirm('Apakah Anda yakin ingin menghapus laporan ini?');">Delete</a>
                            </div>
                        </div>

                        <div style="display: flex; justify-content: space-between; align-items: center; padding-top: 1rem; border-top: 1px solid #e1e8ed;">
                            <div style="display: flex; gap: 1rem; font-size: 0.85rem; color: #666;">
                                <span>👥 <?php echo $report['participant_count']; ?> peserta</span>
                                <?php if ($report['training_date_start']): ?>
                                    <span>📅 <?php echo date('d/m/Y', strtotime($report['training_date_start'])); ?></span>
                                <?php endif; ?>
                            </div>
                            <span style="font-size: 0.8rem; color: #999;">
                                <?php echo date('d M Y', strtotime($report['created_at'])); ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'footer.php'; ?>
