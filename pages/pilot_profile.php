<?php
session_start();
require '../includes/bd.php';

// Vérifier si l'utilisateur est connecté et est un pilote
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'pilot') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success_message = "";
$error_message = "";

// Récupérer les informations de l'utilisateur
$query = "SELECT u.* FROM users u WHERE u.id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: ../login.php");
    exit();
}

$user = $result->fetch_assoc();

// Récupérer les attestations du pilote
$query_attestations = "SELECT a.*, f.title as formation_title 
                      FROM attestations a 
                      JOIN formations f ON a.formation_id = f.id 
                      WHERE a.user_id = ? 
                      ORDER BY a.date_emission DESC";
$stmt = $conn->prepare($query_attestations);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result_attestations = $stmt->get_result();

// Récupérer les examens récents du pilote
$query_exams = "SELECT er.*, e.title as exam_title, f.title as formation_title 
               FROM exam_results er 
               JOIN exams e ON er.exam_id = e.id 
               JOIN formations f ON e.formation_id = f.id 
               WHERE er.pilot_id = ? 
               ORDER BY er.date_taken DESC 
               LIMIT 5";
$stmt = $conn->prepare($query_exams);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result_exams = $stmt->get_result();

// Traitement du formulaire de changement de mot de passe
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Vérifier si le mot de passe actuel est correct
    if (!password_verify($current_password, $user['password'])) {
        $error_message = "Le mot de passe actuel est incorrect.";
    } elseif ($new_password !== $confirm_password) {
        $error_message = "Les nouveaux mots de passe ne correspondent pas.";
    } elseif (strlen($new_password) < 8) {
        $error_message = "Le nouveau mot de passe doit contenir au moins 8 caractères.";
    } else {
        // Hasher le nouveau mot de passe
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        
        // Mettre à jour le mot de passe
        $update_query = "UPDATE users SET password = ? WHERE id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("si", $hashed_password, $user_id);
        
        if ($stmt->execute()) {
            $success_message = "Mot de passe changé avec succès.";
        } else {
            $error_message = "Erreur lors du changement de mot de passe.";
        }
    }
}

// Traitement de l'upload d'avatar
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_avatar'])) {
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === 0) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $max_size = 2 * 1024 * 1024; // 2MB
        
        if (!in_array($_FILES['avatar']['type'], $allowed_types)) {
            $error_message = "Seuls les fichiers JPG, PNG et GIF sont autorisés.";
        } elseif ($_FILES['avatar']['size'] > $max_size) {
            $error_message = "La taille du fichier ne doit pas dépasser 2MB.";
        } else {
            $upload_dir = '../uploads/avatars/';
            
            // Créer le répertoire s'il n'existe pas
            if (!file_exists($upload_dir)) {
                if (!mkdir($upload_dir, 0777, true)) {
                    $error_message = "Impossible de créer le répertoire d'upload.";
                    goto upload_end;
                }
            }
            
            // Générer un nom de fichier unique
            $file_extension = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
            $file_name = $user_id . '_' . time() . '.' . $file_extension;
            $target_file = $upload_dir . $file_name;
            
            if (move_uploaded_file($_FILES['avatar']['tmp_name'], $target_file)) {
                // Mettre à jour l'avatar dans la base de données
                $avatar_path = 'uploads/avatars/' . $file_name;
                
                // Vérifier si la colonne avatar existe
                $check_avatar_column = $conn->query("SHOW COLUMNS FROM users LIKE 'avatar'");
                if ($check_avatar_column->num_rows === 0) {
                    // Ajouter la colonne si elle n'existe pas
                    $conn->query("ALTER TABLE users ADD COLUMN avatar VARCHAR(255) DEFAULT NULL");
                }
                
                $update_query = "UPDATE users SET avatar = ? WHERE id = ?";
                $stmt = $conn->prepare($update_query);
                
                if ($stmt) {
                    $stmt->bind_param("si", $avatar_path, $user_id);
                    
                    if ($stmt->execute()) {
                        // Mettre à jour l'avatar dans la session
                        $_SESSION['avatar'] = $avatar_path;
                        
                        $success_message = "Avatar mis à jour avec succès.";
                        
                        // Recharger les informations de l'utilisateur
                        $stmt = $conn->prepare($query);
                        $stmt->bind_param("i", $user_id);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $user = $result->fetch_assoc();
                    } else {
                        $error_message = "Erreur lors de la mise à jour de l'avatar dans la base de données.";
                    }
                } else {
                    $error_message = "Erreur de préparation de la requête: " . $conn->error;
                }
            } else {
                $error_message = "Erreur lors de l'upload du fichier.";
            }
        }
    } else {
        $error_message = "Veuillez sélectionner un fichier.";
    }
    
    upload_end: // Point de sortie pour les erreurs d'upload
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Pilote - TTA</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/admin_dashboard.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.19/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.19/dist/sweetalert2.all.min.js"></script>
    <script src="../components/alerts.js"></script>
    <style>
        .profile-header {
            display: flex;
            align-items: center;
            background-color: #fff;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }
        
        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            overflow: hidden;
            background-color: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            font-weight: bold;
            margin-right: 20px;
            flex-shrink: 0;
        }
        
        .profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .profile-info {
            flex-grow: 1;
        }
        
        .profile-info h2 {
            margin-top: 0;
            margin-bottom: 10px;
            color: var(--primary);
        }
        
        .profile-info p {
            margin: 5px 0;
            color: #6c757d;
        }
        
        .profile-tabs {
            display: flex;
            background-color: #fff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }
        
        .profile-tab {
            padding: 15px 20px;
            cursor: pointer;
            transition: background-color 0.3s;
            font-weight: 500;
            text-align: center;
            flex-grow: 1;
        }
        
        .profile-tab:hover {
            background-color: #f8f9fa;
        }
        
        .profile-tab.active {
            background-color: var(--primary);
            color: white;
        }
        
        .profile-content {
            display: none;
        }
        
        .profile-content.active {
            display: block;
        }
        
        .avatar-upload {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .avatar-preview {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            overflow: hidden;
            margin-bottom: 20px;
        }
        
        .avatar-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .table-responsive {
            overflow-x: auto;
        }
        
        .badge {
            display: inline-block;
            padding: 0.25em 0.6em;
            font-size: 75%;
            font-weight: 700;
            line-height: 1;
            text-align: center;
            white-space: nowrap;
            vertical-align: baseline;
            border-radius: 0.25rem;
            color: #fff;
        }
        
        .bg-success {
            background-color: #28a745;
        }
        
        .bg-danger {
            background-color: #dc3545;
        }
        
        .bg-warning {
            background-color: #ffc107;
            color: #212529;
        }
        
        .license-info {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            border-left: 4px solid #0a3d91;
        }
        
        .license-info p {
            margin: 5px 0;
        }
        
        .license-info .license-expiry {
            font-weight: bold;
        }
        
        .license-info .license-expiry.valid {
            color: #28a745;
        }
        
        .license-info .license-expiry.expiring {
            color: #ffc107;
        }
        
        .license-info .license-expiry.expired {
            color: #dc3545;
        }
    </style>
</head>
<body>
    <!-- Mobile Navigation Toggle -->
    <button class="mobile-nav-toggle">
        <i class="fas fa-bars"></i>
    </button>

    <?php include '../includes/pilote_sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <div class="page-header">
            <h1 class="page-title">Mon Profil Pilote</h1>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="pilot_dashboard.php" class="breadcrumb-link">Accueil</a></li>
                <li class="breadcrumb-item">Mon profil</li>
            </ul>
        </div>

        <?php if (!empty($success_message)): ?>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    showSuccess('Succès', '<?php echo addslashes($success_message); ?>');
                });
            </script>
        <?php endif; ?>

        <?php if (!empty($error_message)): ?>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    showError('Erreur', '<?php echo addslashes($error_message); ?>');
                });
            </script>
        <?php endif; ?>

        <div class="profile-header animate-on-scroll">
            <div class="profile-avatar">
                <?php if (!empty($user['avatar'])): ?>
                    <img src="../<?php echo htmlspecialchars($user['avatar']); ?>" alt="Avatar">
                <?php else: ?>
                    <?php echo strtoupper(substr($user['first_name'], 0, 1)); ?>
                <?php endif; ?>
            </div>
            <div class="profile-info">
                <h2><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h2>
                <p><?php echo htmlspecialchars($user['email']); ?></p>
                <p><strong>Fonction:</strong> <?php echo isset($user['fonction']) ? htmlspecialchars($user['fonction']) : 'Non spécifiée'; ?></p>
                <p><strong>Section:</strong> <?php echo isset($user['section']) ? ucfirst(htmlspecialchars($user['section'])) : 'Non spécifiée'; ?></p>
                <p><strong>Membre depuis:</strong> <?php echo date('d/m/Y', strtotime($user['created_at'])); ?></p>
                
                <?php if (isset($user['license_number']) && !empty($user['license_number'])): ?>
                    <p><strong>Licence:</strong> <?php echo htmlspecialchars($user['license_number']); ?></p>
                    <?php if (isset($user['license_expiry']) && !empty($user['license_expiry'])): 
                        $expiry_date = new DateTime($user['license_expiry']);
                        $now = new DateTime();
                        $interval = $now->diff($expiry_date);
                        $expiry_class = '';
                        $expiry_text = '';
                        
                        if ($expiry_date < $now) {
                            $expiry_class = 'expired';
                            $expiry_text = 'Expirée depuis ' . $interval->days . ' jour(s)';
                        } elseif ($interval->days <= 60) {
                            $expiry_class = 'expiring';
                            $expiry_text = 'Expire dans ' . $interval->days . ' jour(s)';
                        } else {
                            $expiry_class = 'valid';
                            $expiry_text = 'Valide jusqu\'au ' . date('d/m/Y', strtotime($user['license_expiry']));
                        }
                    ?>
                        <p><strong>Validité:</strong> <span class="license-expiry <?php echo $expiry_class; ?>"><?php echo $expiry_text; ?></span></p>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Onglets de navigation -->
        <div class="profile-tabs animate-on-scroll">
            <div class="profile-tab active" data-tab="attestations">Attestations</div>
            <div class="profile-tab" data-tab="exams">Examens</div>
            <div class="profile-tab" data-tab="change-password">Mot de passe</div>
            <div class="profile-tab" data-tab="avatar">Photo de profil</div>
        </div>

        <!-- Attestations Tab -->
        <div id="attestations" class="profile-content active animate-on-scroll">
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Mes Attestations</h2>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Formation</th>
                                    <th>Type</th>
                                    <th>Date d'émission</th>
                                    <th>Date d'expiration</th>
                                    <th>Statut</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($result_attestations && $result_attestations->num_rows > 0): ?>
                                    <?php while ($attestation = $result_attestations->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($attestation['formation_title']); ?></td>
                                            <td><?php echo $attestation['type'] === 'interne' ? 'Interne' : 'Externe'; ?></td>
                                            <td><?php echo date('d/m/Y', strtotime($attestation['date_emission'])); ?></td>
                                            <td>
                                                <?php echo $attestation['date_expiration'] ? date('d/m/Y', strtotime($attestation['date_expiration'])) : 'Non applicable'; ?>
                                            </td>
                                            <td>
                                                <?php if ($attestation['statut'] === 'valide'): ?>
                                                    <span class="badge bg-success">Valide</span>
                                                <?php elseif ($attestation['statut'] === 'bientot_expire'): ?>
                                                    <span class="badge bg-warning">Expire bientôt</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">Expirée</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <a href="attestation_details.php?id=<?php echo $attestation['id']; ?>" class="btn btn-sm btn-primary">
                                                    <i class="fas fa-eye"></i> Voir
                                                </a>
                                                <?php if ($attestation['fichier']): ?>
                                                    <a href="../uploads/attestations/<?php echo $attestation['fichier']; ?>" class="btn btn-sm btn-success" download>
                                                        <i class="fas fa-download"></i> Télécharger
                                                    </a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center">Aucune attestation trouvée</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Exams Tab -->
        <div id="exams" class="profile-content animate-on-scroll">
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Mes Examens Récents</h2>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Examen</th>
                                    <th>Formation</th>
                                    <th>Date</th>
                                    <th>Score</th>
                                    <th>Statut</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($result_exams && $result_exams->num_rows > 0): ?>
                                    <?php while ($exam = $result_exams->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($exam['exam_title']); ?></td>
                                            <td><?php echo htmlspecialchars($exam['formation_title']); ?></td>
                                            <td><?php echo date('d/m/Y H:i', strtotime($exam['date_taken'])); ?></td>
                                            <td><?php echo $exam['score']; ?>%</td>
                                            <td>
                                                <?php if ($exam['status'] === 'passed'): ?>
                                                    <span class="badge bg-success">Réussi</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">Échoué</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <a href="view_exam_result.php?id=<?php echo $exam['id']; ?>" class="btn btn-sm btn-primary">
                                                    <i class="fas fa-eye"></i> Voir résultat
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center">Aucun examen récent</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Change Password Tab -->
        <div id="change-password" class="profile-content animate-on-scroll">
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Changer le mot de passe</h2>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="current_password" class="form-label">Mot de passe actuel</label>
                            <input type="password" id="current_password" name="current_password" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="new_password" class="form-label">Nouveau mot de passe</label>
                            <input type="password" id="new_password" name="new_password" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="confirm_password" class="form-label">Confirmer le nouveau mot de passe</label>
                            <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                        </div>
                        <button type="submit" name="change_password" class="btn btn-primary">Changer le mot de passe</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Avatar Tab -->
        <div id="avatar" class="profile-content animate-on-scroll">
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Photo de profil</h2>
                </div>
                <div class="card-body">
                    <div class="avatar-upload">
                        <div class="avatar-preview">
                            <?php if (!empty($user['avatar'])): ?>
                                <img id="avatarPreview" src="../<?php echo htmlspecialchars($user['avatar']); ?>" alt="Avatar">
                            <?php else: ?>
                                <div id="avatarPreview" style="background-color: var(--primary); color: white; display: flex; align-items: center; justify-content: center; font-size: 2rem; font-weight: bold; width: 100%; height: 100%;">
                                    <?php echo strtoupper(substr($user['first_name'], 0, 1)); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <form method="POST" action="" enctype="multipart/form-data">
                        <div class="form-group">
                            <label for="avatar" class="form-label">Choisir une nouvelle photo</label>
                            <input type="file" id="avatarUpload" name="avatar" class="form-control" accept="image/jpeg, image/png, image/gif">
                            <small class="text-muted">Formats acceptés: JPG, PNG, GIF. Taille max: 2MB</small>
                        </div>
                        <button type="submit" name="upload_avatar" class="btn btn-primary">Mettre à jour la photo</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="../scripts/admin_dashboard.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Profile tabs functionality
            const tabs = document.querySelectorAll('.profile-tab');
            const contents = document.querySelectorAll('.profile-content');
            
            tabs.forEach(tab => {
                tab.addEventListener('click', function() {
                    const tabId = this.getAttribute('data-tab');
                    
                    // Remove active class from all tabs and contents
                    tabs.forEach(t => t.classList.remove('active'));
                    contents.forEach(c => c.classList.remove('active'));
                    
                    // Add active class to clicked tab and corresponding content
                    this.classList.add('active');
                    document.getElementById(tabId).classList.add('active');
                });
            });
            
            // Avatar preview
            const avatarUpload = document.getElementById('avatarUpload');
            const avatarPreview = document.getElementById('avatarPreview');
            
            if (avatarUpload && avatarPreview) {
                avatarUpload.addEventListener('change', function() {
                    if (this.files && this.files[0]) {
                        const reader = new FileReader();
                        
                        reader.onload = function(e) {
                            if (avatarPreview.tagName === 'IMG') {
                                avatarPreview.src = e.target.result;
                            } else {
                                // Create an image element if it doesn't exist
                                const img = document.createElement('img');
                                img.id = 'avatarPreview';
                                img.src = e.target.result;
                                img.alt = 'Avatar';
                                
                                // Replace the div with the image
                                avatarPreview.parentNode.replaceChild(img, avatarPreview);
                            }
                        };
                        
                        reader.readAsDataURL(this.files[0]);
                    }
                });
            }
        });
    </script>
</body>
</html>
