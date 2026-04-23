<?php
// pages/users.php — Kullanıcı yönetimi (Sadece Admin)
$pageTitle = 'Kullanıcılar';
$activePage = 'users';
require_once __DIR__ . '/../includes/header.php';
requireAdmin();

$db = getDB();
$error = '';
$success = '';

// Kullanıcı silme (sadece depocu)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'delete') {
        $userId = (int)($_POST['user_id'] ?? 0);
        $currentUser = currentUser();
        
        if ($userId > 0) {
            // Silinecek kullanıcının bilgilerini al
            $userToDelete = $db->prepare("SELECT * FROM users WHERE id = ?");
            $userToDelete->execute([$userId]);
            $userToDelete = $userToDelete->fetch();
            
            if ($userToDelete) {
                // Admin kullanıcıları silinemez
                if ($userToDelete['role'] === 'admin') {
                    $error = 'Admin kullanıcıları silinemez.';
                } elseif ($userToDelete['username'] === $currentUser['username']) {
                    $error = 'Kendi hesabınızı silemezsiniz.';
                } else {
                    try {
                        $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
                        $stmt->execute([$userId]);
                        $success = "Kullanıcı '{$userToDelete['username']}' silindi.";
                    } catch (PDOException $e) {
                        $error = 'Kullanıcı silinirken hata oluştu.';
                    }
                }
            } else {
                $error = 'Kullanıcı bulunamadı.';
            }
        }
    }
    
    // Yeni kullanıcı ekle
    if ($_POST['action'] === 'create') {
        $username = trim($_POST['username'] ?? '');
        $fullname = trim($_POST['full_name'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $role = $_POST['role'] ?? 'depocu';
        
        if (strlen($username) < 3) {
            $error = 'Kullanıcı adı en az 3 karakter olmalı.';
        } elseif (strlen($password) < 6) {
            $error = 'Şifre en az 6 karakter olmalı.';
        } elseif (empty($fullname)) {
            $error = 'Ad soyad gerekli.';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            try {
                $stmt = $db->prepare("INSERT INTO users (username, password, full_name, role, active) VALUES (?, ?, ?, ?, 1)");
                $stmt->execute([$username, $hash, $fullname, $role]);
                $success = "Kullanıcı '{$username}' eklendi.";
            } catch (PDOException $e) {
                $error = 'Bu kullanıcı adı zaten mevcut veya geçersiz rol.';
            }
        }
    }
}

// Kullanıcıları listele
$users = $db->query("SELECT * FROM users ORDER BY role, username")->fetchAll();

// Tüm test verilerini sil (kullanıcılar hariç)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_data'])) {
    requireAdmin();
    try {
        $db->exec("DELETE FROM order_items");
        $db->exec("DELETE FROM orders");
        $db->exec("DELETE FROM production_order_items");
        $db->exec("DELETE FROM production_orders");
        $db->exec("DELETE FROM bom_items");
        $db->exec("DELETE FROM stock_movements");
        $db->exec("DELETE FROM products");
        $success = "Tüm test verileri silindi. (Ürünler, Siparişler, Üretimler, Stok hareketleri, Reçeteler)";
    } catch (PDOException $e) {
        $error = "Veriler silinirken hata: " . $e->getMessage();
    }
}

// AJAX: Kullanıcı güncelleme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    requireAdmin();
    
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_user') {
        $userId = (int)($_POST['user_id'] ?? 0);
        $fullname = trim($_POST['full_name'] ?? '');
        $role = $_POST['role'] ?? 'depocu';
        $active = isset($_POST['active']) ? 1 : 0;
        $password = $_POST['password'] ?? '';
        
        if (!$userId || strlen($fullname) < 2) {
            echo json_encode(['success' => false, 'error' => 'Geçersiz veri']);
            exit;
        }
        
        // Admin koruması
        $check = $db->prepare("SELECT role FROM users WHERE id = ?");
        $check->execute([$userId]);
        $user = $check->fetch();
        
        if (!$user || $user['role'] === 'admin') {
            echo json_encode(['success' => false, 'error' => 'Admin kullanıcı düzenlenemez']);
            exit;
        }
        
        try {
            if ($password) {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $db->prepare("UPDATE users SET full_name = ?, role = ?, active = ?, password = ? WHERE id = ?");
                $stmt->execute([$fullname, $role, $active, $hash, $userId]);
            } else {
                $stmt = $db->prepare("UPDATE users SET full_name = ?, role = ?, active = ? WHERE id = ?");
                $stmt->execute([$fullname, $role, $active, $userId]);
            }
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => 'Güncellenemedi']);
        }
        exit;
    }
}
?>

<div class="row g-3">
    <!-- Yeni Kullanıcı Ekle -->
    <div class="col-12 col-md-6">
        <div class="card">
            <div class="card-header">
                <strong>Yeni Kullanıcı Ekle</strong>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                <div class="alert alert-danger py-2"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                <div class="alert alert-success py-2"><?= htmlspecialchars($success) ?></div>
                <?php endif; ?>
                
                <form method="post">
                    <input type="hidden" name="action" value="create">
                    <div class="mb-3">
                        <label class="form-label">Kullanıcı Adı</label>
                        <input type="text" name="username" class="form-control" required autocomplete="username">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Ad Soyad</label>
                        <input type="text" name="full_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Şifre</label>
                        <input type="password" name="password" class="form-control" required autocomplete="new-password">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Rol</label>
                        <select name="role" class="form-select">
                            <option value="depocu">Depocu</option>
                            <option value="muhasebe">Muhasebe</option>
                            <option value="admin">Admin</option>
                        </select>
                        <small class="text-muted">
                            <strong>Depocu:</strong> Sipariş hazırla, stok girişi, üretim<br>
                            <strong>Muhasebe:</strong> Sipariş oluştur, takip et<br>
                            <strong>Admin:</strong> Tüm yetkiler
                        </small>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-person-plus"></i> Kullanıcı Ekle
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Kullanıcı Listesi -->
    <div class="col-12 col-md-6">
    <div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
    <strong>Kullanıcı Listesi</strong>
    <span class="badge bg-secondary"><?= count($users) ?></span>
    </div>
    <ul class="list-group list-group-flush">
    <?php foreach ($users as $u): ?>
    <li class="list-group-item d-flex justify-content-between align-items-center">
    <div>
    <strong><?= htmlspecialchars($u['username']) ?></strong>
    <?php if ($u['role'] === 'admin'): ?>
    <span class="badge bg-primary">Admin</span>
    <?php else: ?>
    <span class="badge bg-success">Depocu</span>
    <?php endif; ?>
    <br>
    <small class="text-muted"><?= htmlspecialchars($u['full_name']) ?></small>
    <?php if ($u['active']): ?>
    <span class="badge bg-success">Aktif</span>
    <?php else: ?>
    <span class="badge bg-secondary">Pasif</span>
    <?php endif; ?>
    </div>
<div class="d-flex align-items-center gap-2">
    <small class="text-muted"><?= date('d.m.Y H:i', strtotime($u['created_at'])) ?></small>
    <?php if ($u['role'] !== 'admin'): ?>
    <button class="btn btn-sm btn-outline-primary" onclick="editUser(<?= $u['id'] ?>, '<?= htmlspecialchars($u['username']) ?>', '<?= htmlspecialchars($u['full_name']) ?>', '<?= $u['role'] ?>', <?= $u['active'] ?>)">
        <i class="bi bi-pencil"></i>
    </button>
    <form method="post" class="ms-1" onsubmit="return confirm('<?= htmlspecialchars($u['username']) ?> kullanıcısını silmek istediğinizden emin misiniz?');">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
    <button type="submit" class="btn btn-sm btn-outline-danger" title="Sil">
    <i class="bi bi-trash"></i>
    </button>
    </form>
    <?php endif; ?>
</div>
    </li>
    <?php endforeach; ?>
    </ul>
    </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<!-- Düzenleme Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Kullanıcı Düzenle</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="editUserId">
                <div class="mb-3">
                    <label class="form-label">Kullanıcı Adı</label>
                    <input type="text" id="editUsername" class="form-control" readonly>
                </div>
                <div class="mb-3">
                    <label class="form-label">Ad Soyad</label>
                    <input type="text" id="editFullname" class="form-control">
                </div>
                <div class="mb-3">
                    <label class="form-label">Rol</label>
                    <select id="editRole" class="form-select">
                        <option value="depocu">Depocu</option>
                        <option value="muhasebe">Muhasebe</option>
                    </select>
                </div>
                <div class="mb-3">
                    <div class="form-check">
                        <input type="checkbox" id="editActive" class="form-check-input" value="1">
                        <label class="form-check-label">Aktif</label>
                    </div>
                </div>
                <hr>
                <div class="mb-3">
                    <label class="form-label">Yeni Şifre (boş bırakılırsa değişmez)</label>
                    <input type="password" id="editPassword" class="form-control" placeholder="••••••••">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                <button type="button" class="btn btn-primary" onclick="saveUser()">Kaydet</button>
            </div>
        </div>
    </div>
</div>

<script>
let editUserModal;

document.addEventListener('DOMContentLoaded', function() {
    editUserModal = new bootstrap.Modal(document.getElementById('editUserModal'));
});

function editUser(id, username, fullname, role, active) {
    document.getElementById('editUserId').value = id;
    document.getElementById('editUsername').value = username;
    document.getElementById('editFullname').value = fullname;
    document.getElementById('editRole').value = role;
    document.getElementById('editActive').checked = active === 1;
    document.getElementById('editPassword').value = '';
    editUserModal.show();
}

async function saveUser() {
    const id = document.getElementById('editUserId').value;
    const fullname = document.getElementById('editFullname').value;
    const role = document.getElementById('editRole').value;
    const active = document.getElementById('editActive').checked ? 1 : 0;
    const password = document.getElementById('editPassword').value;
    
    const formData = new FormData();
    formData.append('ajax', '1');
    formData.append('action', 'update_user');
    formData.append('user_id', id);
    formData.append('full_name', fullname);
    formData.append('role', role);
    formData.append('active', active);
    
    if (password) {
        formData.append('password', password);
    }
    
    const res = await fetch('users.php', {
        method: 'POST',
        body: formData
    }).then(r => r.json());
    
    if (res.success) {
        editUserModal.hide();
        showToast('Kullanıcı güncellendi', 'success');
        setTimeout(() => location.reload(), 1000);
    } else {
        alert(res.error || 'Hata');
    }
}
</script>
