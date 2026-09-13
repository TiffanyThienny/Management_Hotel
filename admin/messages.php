<?php
require_once '../config/init.php';
require_once '../config/database.php';
checkAdminAuth();

$is_owner = isOwner();
$is_receptionist = isReceptionist();
$is_admin = isAdmin();

$page_title = $is_receptionist ? "Kirim Pesan ke Owner & Manajemen" : "Pesan Masuk dari Staf & Resepsionis";
include '../includes/header.php';

// Handle POST actions
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // 1. Receptionist / Staff sending a new message to owner
    if ($action === 'send_message') {
        $subject = trim(sanitizeInput($_POST['subject'] ?? ''));
        $category = sanitizeInput($_POST['category'] ?? 'operasional');
        $priority = sanitizeInput($_POST['priority'] ?? 'normal');
        $message = trim(sanitizeInput($_POST['message'] ?? ''));
        
        if (empty($subject) || empty($message)) {
            setFlashMessage('error', 'Subjek dan isi pesan wajib diisi.');
        } else {
            $sender_id = $_SESSION['user_id'];
            $sender_name = $_SESSION['full_name'] ?? $_SESSION['username'];
            $sender_role = $_SESSION['role'];
            
            $stmt = $db->prepare("INSERT INTO owner_messages (sender_id, sender_name, sender_role, subject, category, priority, message, status, created_at) VALUES (:sid, :sname, :srole, :subject, :cat, :prio, :msg, 'unread', NOW())");
            $res = $stmt->execute([
                'sid' => $sender_id,
                'sname' => $sender_name,
                'srole' => $sender_role,
                'subject' => $subject,
                'cat' => $category,
                'prio' => $priority,
                'msg' => $message
            ]);
            
            if ($res) {
                setFlashMessage('success', 'Pesan berhasil dikirim langsung ke Owner!');
            } else {
                setFlashMessage('error', 'Gagal mengirim pesan. Silakan coba lagi.');
            }
        }
        redirect('messages.php');
    }
    
    // 2. Owner / Admin replying to message or changing status
    if ($action === 'reply_message' && ($is_owner || $is_admin)) {
        $msg_id = intval($_POST['message_id'] ?? 0);
        $reply_text = trim(sanitizeInput($_POST['reply_message'] ?? ''));
        $new_status = sanitizeInput($_POST['new_status'] ?? 'replied');
        
        if ($msg_id > 0 && !empty($reply_text)) {
            $stmt = $db->prepare("UPDATE owner_messages SET reply_message = :reply, status = :status, updated_at = NOW() WHERE id = :id");
            $stmt->execute([
                'reply' => $reply_text,
                'status' => $new_status,
                'id' => $msg_id
            ]);
            setFlashMessage('success', 'Tanggapan pesan berhasil dikirim ke staf.');
        }
        redirect('messages.php');
    }
    
    // 3. Mark as Read / Resolved
    if ($action === 'update_status' && ($is_owner || $is_admin)) {
        $msg_id = intval($_POST['message_id'] ?? 0);
        $new_status = sanitizeInput($_POST['new_status'] ?? 'read');
        if ($msg_id > 0) {
            $stmt = $db->prepare("UPDATE owner_messages SET status = :status WHERE id = :id");
            $stmt->execute(['status' => $new_status, 'id' => $msg_id]);
            setFlashMessage('success', 'Status pesan diperbarui.');
        }
        redirect('messages.php');
    }
}

// Fetch messages list
if ($is_receptionist) {
    // Receptionist sees messages sent by themselves
    $user_id = $_SESSION['user_id'];
    $stmt = $db->prepare("SELECT * FROM owner_messages WHERE sender_id = :uid ORDER BY created_at DESC");
    $stmt->execute(['uid' => $user_id]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    // Owner / Admin sees all messages
    $filter_status = sanitizeInput($_GET['status'] ?? '');
    $filter_prio = sanitizeInput($_GET['priority'] ?? '');
    
    $where_clauses = [];
    $params = [];
    
    if (!empty($filter_status)) {
        $where_clauses[] = "status = :st";
        $params['st'] = $filter_status;
    }
    if (!empty($filter_prio)) {
        $where_clauses[] = "priority = :pr";
        $params['pr'] = $filter_prio;
    }
    
    $where_sql = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";
    $stmt = $db->prepare("SELECT * FROM owner_messages $where_sql ORDER BY CASE priority WHEN 'mendesak' THEN 1 WHEN 'penting' THEN 2 ELSE 3 END, created_at DESC");
    $stmt->execute($params);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Counts for Owner/Admin
$unread_total = $db->query("SELECT COUNT(*) FROM owner_messages WHERE status = 'unread'")->fetchColumn();
$urgent_total = $db->query("SELECT COUNT(*) FROM owner_messages WHERE priority = 'mendesak' AND status != 'resolved'")->fetchColumn();
$resolved_total = $db->query("SELECT COUNT(*) FROM owner_messages WHERE status = 'resolved'")->fetchColumn();
?>

<style>
    .card-custom {
        border-radius: 18px;
        border: 1px solid #e2e8f0;
        background: #ffffff;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.03);
    }
    .badge-priority-mendesak {
        background-color: #fee2e2;
        color: #dc2626;
        border: 1px solid #fca5a5;
    }
    .badge-priority-penting {
        background-color: #fef3c7;
        color: #d97706;
        border: 1px solid #fde68a;
    }
    .badge-priority-normal {
        background-color: #f1f5f9;
        color: #475569;
        border: 1px solid #cbd5e1;
    }
    .message-card {
        border-radius: 14px;
        border: 1px solid #e2e8f0;
        transition: all 0.25s ease;
    }
    .message-card.unread {
        border-left: 5px solid #4f46e5;
        background-color: #faf5ff;
    }
    .message-card.mendesak {
        border-left: 5px solid #ef4444;
        background-color: #fff5f5;
    }
</style>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar Navigation -->
        <?php include '../includes/admin-sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
            
            <!-- Header Banner -->
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 p-4 rounded-4" 
                 style="background: <?php echo $is_owner ? 'linear-gradient(135deg, #1e1b4b 0%, #312e81 100%)' : ($is_receptionist ? 'linear-gradient(135deg, #0f172a 0%, #1e293b 100%)' : 'linear-gradient(135deg, #1e293b 0%, #334155 100%)'); ?>; color: #ffffff;">
                <div>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <span class="badge <?php echo $is_owner ? 'bg-warning text-dark' : 'bg-info text-dark'; ?> rounded-pill px-3 py-1 fw-bold">
                            <i class="fas <?php echo $is_owner ? 'fa-crown' : 'fa-paper-plane'; ?> me-1"></i> 
                            <?php echo $is_owner ? 'OWNER INBOX' : ($is_receptionist ? 'FRONT DESK DISPATCH' : 'INTERNAL MESSAGING'); ?>
                        </span>
                        <span class="text-white-50 small">Komunikasi Internal & Catatan Operasional</span>
                    </div>
                    <h2 class="fw-extrabold mb-1" style="font-family: 'Outfit', sans-serif;">
                        <?php echo $is_receptionist ? 'Kirim Catatan & Pesan ke Owner' : 'Kotak Pesan Staf & Resepsionis'; ?>
                    </h2>
                    <p class="mb-0 text-white-50">
                        <?php echo $is_receptionist ? 'Sampaikan laporan penting, kebutuhan mendesak, atau komplain tamu langsung kepada Pemilik Hotel (Owner).' : 'Pantau langsung catatan harian, kebutuhan mendesak, dan informasi terkini dari staf resepsionis.'; ?>
                    </p>
                </div>
                <?php if ($is_receptionist): ?>
                <button type="button" class="btn btn-warning btn-md rounded-pill px-4 fw-bold shadow-sm mt-3 mt-md-0" data-bs-toggle="modal" data-bs-target="#modalNewMessage">
                    <i class="fas fa-plus-circle me-1"></i> Tulis Pesan Baru
                </button>
                <?php endif; ?>
            </div>

            <?php if ($is_owner || $is_admin): ?>
            <!-- Owner / Admin Overview Stats -->
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="card-custom p-3 d-flex align-items-center justify-content-between">
                        <div>
                            <span class="text-uppercase text-muted small fw-bold">Pesan Belum Dibaca</span>
                            <h3 class="fw-extrabold mb-0 mt-1 text-primary"><?php echo $unread_total; ?></h3>
                        </div>
                        <div class="bg-primary bg-opacity-10 text-primary p-3 rounded-circle fs-4">
                            <i class="fas fa-envelope-open-text"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card-custom p-3 d-flex align-items-center justify-content-between">
                        <div>
                            <span class="text-uppercase text-muted small fw-bold">Kategori Mendesak (Urgent)</span>
                            <h3 class="fw-extrabold mb-0 mt-1 text-danger"><?php echo $urgent_total; ?></h3>
                        </div>
                        <div class="bg-danger bg-opacity-10 text-danger p-3 rounded-circle fs-4">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card-custom p-3 d-flex align-items-center justify-content-between">
                        <div>
                            <span class="text-uppercase text-muted small fw-bold">Pesan Selesai / Ditanggapi</span>
                            <h3 class="fw-extrabold mb-0 mt-1 text-success"><?php echo $resolved_total; ?></h3>
                        </div>
                        <div class="bg-success bg-opacity-10 text-success p-3 rounded-circle fs-4">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters for Owner/Admin -->
            <div class="card-custom p-3 mb-4">
                <form method="GET" action="" class="row g-2 align-items-center">
                    <div class="col-auto">
                        <span class="fw-bold small text-muted"><i class="fas fa-filter me-1"></i>Filter:</span>
                    </div>
                    <div class="col-auto">
                        <select name="status" class="form-select form-select-sm rounded-pill" onchange="this.form.submit()">
                            <option value="">Semua Status</option>
                            <option value="unread" <?php echo ($filter_status === 'unread') ? 'selected' : ''; ?>>Belum Dibaca</option>
                            <option value="read" <?php echo ($filter_status === 'read') ? 'selected' : ''; ?>>Sudah Dibaca</option>
                            <option value="replied" <?php echo ($filter_status === 'replied') ? 'selected' : ''; ?>>Sudah Ditanggapi</option>
                            <option value="resolved" <?php echo ($filter_status === 'resolved') ? 'selected' : ''; ?>>Selesai (Resolved)</option>
                        </select>
                    </div>
                    <div class="col-auto">
                        <select name="priority" class="form-select form-select-sm rounded-pill" onchange="this.form.submit()">
                            <option value="">Semua Prioritas</option>
                            <option value="mendesak" <?php echo ($filter_prio === 'mendesak') ? 'selected' : ''; ?>>🔴 Mendesak</option>
                            <option value="penting" <?php echo ($filter_prio === 'penting') ? 'selected' : ''; ?>>🟡 Penting</option>
                            <option value="normal" <?php echo ($filter_prio === 'normal') ? 'selected' : ''; ?>>⚪ Normal</option>
                        </select>
                    </div>
                    <?php if (!empty($filter_status) || !empty($filter_prio)): ?>
                    <div class="col-auto">
                        <a href="messages.php" class="btn btn-sm btn-outline-secondary rounded-pill">Reset Filter</a>
                    </div>
                    <?php endif; ?>
                </form>
            </div>
            <?php endif; ?>

            <!-- Messages List -->
            <div class="card-custom p-4 mb-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-bold text-dark mb-0">
                        <i class="fas fa-list-ul text-primary me-2"></i>
                        <?php echo $is_receptionist ? 'Daftar Pesan & Tanggapan dari Owner' : 'Daftar Pesan Masuk dari Resepsionis / Staf'; ?>
                    </h6>
                    <span class="badge bg-light text-dark border"><?php echo count($messages); ?> Pesan</span>
                </div>

                <?php if (empty($messages)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-inbox fs-1 text-muted opacity-50 mb-3 d-block"></i>
                        <h6 class="fw-bold text-dark">Belum ada pesan internal.</h6>
                        <p class="text-muted small">
                            <?php echo $is_receptionist ? 'Gunakan tombol "Tulis Pesan Baru" di atas untuk mengirim catatan ke Owner.' : 'Tidak ada pesan dari staf saat ini.'; ?>
                        </p>
                    </div>
                <?php else: ?>
                    <div class="d-flex flex-column gap-3">
                        <?php foreach ($messages as $msg): 
                            $is_unread = $msg['status'] === 'unread';
                            $is_urgent = $msg['priority'] === 'mendesak';
                            $card_class = $is_urgent ? 'mendesak' : ($is_unread ? 'unread' : '');
                        ?>
                        <div class="message-card p-3 <?php echo $card_class; ?>">
                            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
                                <div>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="badge badge-priority-<?php echo $msg['priority']; ?> rounded-pill px-2 py-1 small fw-bold">
                                            <?php echo ucfirst($msg['priority']); ?>
                                        </span>
                                        <span class="badge bg-light text-secondary border rounded-pill px-2 py-1 small">
                                            <i class="fas fa-tag me-1"></i><?php echo ucfirst(str_replace('_', ' ', $msg['category'])); ?>
                                        </span>
                                        <?php if ($msg['status'] === 'unread'): ?>
                                            <span class="badge bg-danger rounded-pill px-2 py-1 small">Baru</span>
                                        <?php elseif ($msg['status'] === 'replied'): ?>
                                            <span class="badge bg-info text-dark rounded-pill px-2 py-1 small">Ditanggapi</span>
                                        <?php elseif ($msg['status'] === 'resolved'): ?>
                                            <span class="badge bg-success rounded-pill px-2 py-1 small"><i class="fas fa-check me-1"></i>Selesai</span>
                                        <?php endif; ?>
                                    </div>
                                    <h5 class="fw-bold text-dark mt-2 mb-1"><?php echo htmlspecialchars($msg['subject']); ?></h5>
                                    <small class="text-muted">
                                        <i class="fas fa-user-circle me-1"></i> <strong><?php echo htmlspecialchars($msg['sender_name']); ?></strong> (<?php echo ucfirst($msg['sender_role']); ?>) &bull; <i class="far fa-clock ms-2 me-1"></i> <?php echo date('d M Y, H:i', strtotime($msg['created_at'])); ?>
                                    </small>
                                </div>

                                <!-- Owner/Admin Fast Actions -->
                                <?php if ($is_owner || $is_admin): ?>
                                <div class="d-flex gap-1">
                                    <button type="button" class="btn btn-sm btn-primary rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#modalReply<?php echo $msg['id']; ?>">
                                        <i class="fas fa-reply me-1"></i> Tanggapi
                                    </button>
                                    <?php if ($msg['status'] !== 'resolved'): ?>
                                    <form method="POST" action="" class="d-inline">
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="message_id" value="<?php echo $msg['id']; ?>">
                                        <input type="hidden" name="new_status" value="resolved">
                                        <button type="submit" class="btn btn-sm btn-outline-success rounded-pill px-2" title="Tandai Selesai">
                                            <i class="fas fa-check"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                            </div>

                            <!-- Message Content -->
                            <div class="p-3 bg-white rounded-3 border mt-2 text-dark" style="font-size: 0.92rem; line-height: 1.6;">
                                <?php echo nl2br(htmlspecialchars($msg['message'])); ?>
                            </div>

                            <!-- Owner Reply Section if exists -->
                            <?php if (!empty($msg['reply_message'])): ?>
                            <div class="mt-3 p-3 rounded-3 border" style="background-color: #fefce8; border-color: #fde047 !important;">
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <span class="badge bg-warning text-dark fw-bold"><i class="fas fa-crown me-1"></i> Tanggapan Owner / Manajemen</span>
                                    <small class="text-muted"><?php echo date('d M Y, H:i', strtotime($msg['updated_at'])); ?></small>
                                </div>
                                <div class="text-dark small" style="line-height: 1.6;">
                                    <?php echo nl2br(htmlspecialchars($msg['reply_message'])); ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Reply Modal for Owner/Admin -->
                        <?php if ($is_owner || $is_admin): ?>
                        <div class="modal fade" id="modalReply<?php echo $msg['id']; ?>" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content rounded-4 border-0 shadow">
                                    <form method="POST" action="">
                                        <input type="hidden" name="action" value="reply_message">
                                        <input type="hidden" name="message_id" value="<?php echo $msg['id']; ?>">
                                        <div class="modal-header border-0 pb-0">
                                            <h5 class="modal-title fw-bold"><i class="fas fa-reply text-primary me-2"></i>Tanggapi Pesan Staf</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="p-2 rounded bg-light mb-3">
                                                <small class="text-muted d-block">Subjek: <strong><?php echo htmlspecialchars($msg['subject']); ?></strong></small>
                                                <small class="text-muted d-block">Dari: <?php echo htmlspecialchars($msg['sender_name']); ?> (<?php echo ucfirst($msg['sender_role']); ?>)</small>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label small fw-semibold text-dark">Isi Tanggapan / Instruksi Anda *</label>
                                                <textarea name="reply_message" class="form-control" rows="4" placeholder="Tulis instruksi atau konfirmasi untuk staf..." required><?php echo htmlspecialchars($msg['reply_message'] ?? ''); ?></textarea>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label small fw-semibold text-dark">Status Pesan</label>
                                                <select name="new_status" class="form-select">
                                                    <option value="replied" <?php echo ($msg['status'] === 'replied') ? 'selected' : ''; ?>>Sudah Ditanggapi (In Progress)</option>
                                                    <option value="resolved" <?php echo ($msg['status'] === 'resolved') ? 'selected' : ''; ?>>Selesai (Resolved / Tuntas)</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="modal-footer border-0 pt-0">
                                            <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Batal</button>
                                            <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold">
                                                <i class="fas fa-paper-plane me-1"></i> Kirim Tanggapan
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

        </main>
    </div>
</div>

<!-- Modal Compose New Message for Receptionist -->
<?php if ($is_receptionist): ?>
<div class="modal fade" id="modalNewMessage" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content rounded-4 border-0 shadow">
            <form method="POST" action="">
                <input type="hidden" name="action" value="send_message">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold text-dark"><i class="fas fa-paper-plane text-warning me-2"></i>Tulis Pesan / Catatan ke Owner</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-dark">Kategori Pesan *</label>
                            <select name="category" class="form-select rounded-3" required>
                                <option value="operasional">Operasional Front Desk</option>
                                <option value="laporan_harian">Laporan Harian / Shift</option>
                                <option value="urgent">Mendesak / Butuh Keputusan Cepat</option>
                                <option value="permintaan_dana">Permintaan Dana / Petty Cash</option>
                                <option value="komplain_tamu">Keluhan / Komplain Tamu VIP</option>
                                <option value="lainnya">Lainnya</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-dark">Tingkat Prioritas *</label>
                            <select name="priority" class="form-select rounded-3" required>
                                <option value="normal">⚪ Normal (Informasi Rutin)</option>
                                <option value="penting">🟡 Penting (Perlu Diperhatikan)</option>
                                <option value="mendesak">🔴 Mendesak (Butuh Tindakan Segera)</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold text-dark">Subjek / Judul Pesan *</label>
                            <input type="text" name="subject" class="form-control rounded-3" placeholder="Contoh: Laporan Penutupan Shift Pagi & Kebutuhan Kas Kecil" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold text-dark">Isi Pesan / Rincian Catatan *</label>
                            <textarea name="message" class="form-control rounded-3" rows="5" placeholder="Tuliskan catatan, informasi, atau permohonan secara jelas untuk Bapak Owner..." required></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-warning rounded-pill px-4 fw-bold">
                        <i class="fas fa-paper-plane me-1"></i> Kirim Pesan Sekarang
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
