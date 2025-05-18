<?php
session_start();
require '../includes/bd.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin_vol') {
    header("Location: ../index.php"); // Redirige vers la page d'accueil si l'utilisateur n'est pas admin
    exit();
}

$user_id = $_SESSION['user_id'];
$success_message = "";
$error_message = "";

// Récupérer les informations de l'utilisateur
$query = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: ../login.php");
    exit();
}

$user = $result->fetch_assoc();

// Traitement du formulaire de mise à jour du profil
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    
    // Vérifier si l'email existe déjà pour un autre utilisateur
    $check_email = "SELECT id FROM users WHERE email = ? AND id != ?";
    $stmt = $conn->prepare($check_email);
    $stmt->bind_param("si", $email, $user_id);
    $stmt->execute();
    $email_result = $stmt->get_result();
    
    if ($email_result->num_rows > 0) {
        $error_message = "Cet email est déjà utilisé par un autre compte.";
    } else {
        // Mettre à jour les informations
        $update_query = "UPDATE users SET first_name = ?, last_name = ?, email = ? WHERE id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("sssi", $first_name, $last_name, $email, $user_id);
        
        if ($stmt->execute()) {
            // Mettre à jour les informations de session
            $_SESSION['first_name'] = $first_name;
            $_SESSION['last_name'] = $last_name;
            $_SESSION['email'] = $email;
            
            $success_message = "Profil mis à jour avec succès.";
            
            // Recharger les informations de l'utilisateur
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
        } else {
            $error_message = "Erreur lors de la mise à jour du profil.";
        }
    }
}

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
    <title>Profil - TTA</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/admin_dashboard.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.19/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.19/dist/sweetalert2.all.min.js"></script>
    <script src="../components/alerts.js"></script>

</head>
<body>
    <!-- Mobile Navigation Toggle -->
    <button class="mobile-nav-toggle">
        <i class="fas fa-bars"></i>
    </button>

    <?php include '../includes/admin_vol_sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <div class="page-header">
            <h1 class="page-title">Mon Profil</h1>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="admin_dashboard_vol.php" class="breadcrumb-link">Accueil</a></li>
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
                <p>
                    <strong>Rôle:</strong> 
                    <?php 
                    switch($user['role']) {
                        case 'admin':
                            echo 'Administrateur';
                            break;
                        case 'pilot':
                            echo 'Pilote';
                            break;
                        case 'instructor':
                            echo 'Instructeur';
                            break;
                        default:
                            echo 'Utilisateur';
                    }
                    ?>
                </p>
                <p><strong>Membre depuis:</strong> <?php echo date('d/m/Y', strtotime($user['created_at'])); ?></p>
            </div>
        </div>

        <div class="profile-tabs animate-on-scroll">
            <div class="profile-tab active" data-tab="profile-info">Informations personnelles</div>
            <div class="profile-tab" data-tab="change-password">Changer le mot de passe</div>
            <div class="profile-tab" data-tab="avatar">Photo de profil</div>
        </div>

        <!-- Profile Information Tab -->
        <div id="profile-info" class="profile-content active animate-on-scroll">
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Informations personnelles</h2>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="first_name" class="form-label">Prénom</label>
                            <input type="text" id="first_name" name="first_name" class="form-control" value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="last_name" class="form-label">Nom</label>
                            <input type="text" id="last_name" name="last_name" class="form-control" value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" id="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        </div>
                        <button type="submit" name="update_profile" class="btn btn-primary">Enregistrer les modifications</button>
                    </form>
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

