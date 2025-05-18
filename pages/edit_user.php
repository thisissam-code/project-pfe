<?php
session_start();
require '../includes/bd.php';

// Vérifier si l'utilisateur est un admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

// Vérifier si l'ID est fourni
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: manage_users.php");
    exit();
}

$user_id = $_GET['id'];

// Récupérer les informations de l'utilisateur
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
if (!$stmt) {
    die("Error preparing statement: " . $conn->error);
}

$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: manage_users.php");
    exit();
}

$user = $result->fetch_assoc();

// Traitement du formulaire de mise à jour
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = htmlspecialchars(trim($_POST['first_name']));
    $last_name = htmlspecialchars(trim($_POST['last_name']));
    $email = htmlspecialchars(trim($_POST['email']));
    $role = $_POST['role'];
    $entreprise = htmlspecialchars(trim($_POST['entreprise']));
    $section = isset($_POST['section']) ? $_POST['section'] : null;
    $fonction = isset($_POST['fonction']) ? $_POST['fonction'] : null;
    
    // Validation
    $errors = [];
    
    if (empty($first_name) || empty($last_name) || empty($email) || empty($role)) {
        $errors[] = "Tous les champs sont obligatoires.";
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "L'adresse email n'est pas valide.";
    }
    
    // Vérifier si l'email existe déjà pour un autre utilisateur
    $check_email = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    if (!$check_email) {
        die("Error preparing email check: " . $conn->error);
    }
    
    $check_email->bind_param("si", $email, $user_id);
    $check_email->execute();
    $email_result = $check_email->get_result();
    
    if ($email_result->num_rows > 0) {
        $errors[] = "Cet email est déjà utilisé par un autre compte.";
    }
    
    // Si pas d'erreurs, mettre à jour l'utilisateur
    if (empty($errors)) {
        $update = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, role = ?, entreprise = ?, section = ?, fonction = ? WHERE id = ?");
        if (!$update) {
            die("Error preparing update: " . $conn->error);
        }
        
        $update->bind_param("sssssssi", $first_name, $last_name, $email, $role, $entreprise, $section, $fonction, $user_id);
        
        if ($update->execute()) {
            $success_message = "L'utilisateur a été mis à jour avec succès.";
            
            // Recharger les informations de l'utilisateur
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
        } else {
            $errors[] = "Une erreur est survenue lors de la mise à jour de l'utilisateur.";
        }
    }
}

// Traitement du formulaire de changement de mot de passe
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validation
    $password_errors = [];
    
    if (empty($new_password)) {
        $password_errors[] = "Le nouveau mot de passe est obligatoire.";
    } elseif (strlen($new_password) < 8) {
        $password_errors[] = "Le mot de passe doit contenir au moins 8 caractères.";
    } elseif ($new_password !== $confirm_password) {
        $password_errors[] = "Les mots de passe ne correspondent pas.";
    }
    
    // Si pas d'erreurs, mettre à jour le mot de passe
    if (empty($password_errors)) {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        
        $update_password = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        if (!$update_password) {
            die("Error preparing password update: " . $conn->error);
        }
        
        $update_password->bind_param("si", $hashed_password, $user_id);
        
        if ($update_password->execute()) {
            $password_success = "Le mot de passe a été mis à jour avec succès.";
        } else {
            $password_errors[] = "Une erreur est survenue lors de la mise à jour du mot de passe.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier un utilisateur - TTA</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/admin_dashboard.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="../components/alerts.js"></script>

</head>
<body>
    <!-- Mobile Navigation Toggle -->
    <button class="mobile-nav-toggle">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Sidebar -->
    <?php include '../includes/admin_sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <div class="page-header">
            <h1 class="page-title">Modifier un utilisateur</h1>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="admin_dashboard.php" class="breadcrumb-link">Accueil</a></li>
                <li class="breadcrumb-item"><a href="manage_users.php" class="breadcrumb-link">Gestion des utilisateurs</a></li>
                <li class="breadcrumb-item">Modifier un utilisateur</li>
            </ul>
        </div>

        <div class="card mb-4">
            <div class="card-header">
                <h2 class="card-title">Informations de l'utilisateur</h2>
            </div>
            <div class="card-body">
                <?php if (isset($success_message)): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i>
                        <ul style="margin-bottom: 0; padding-left: 20px;">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo $error; ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <form action="" method="POST">
                    <div class="row">
                        <div class="form-group" style="flex: 1; margin-right: 15px;">
                            <label for="first_name" class="form-label">Prénom</label>
                            <input type="text" id="first_name" name="first_name" class="form-control" value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                        </div>
                        
                        <div class="form-group" style="flex: 1;">
                            <label for="last_name" class="form-label">Nom</label>
                            <input type="text" id="last_name" name="last_name" class="form-control" value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" id="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="entreprise" class="form-label">Entreprise</label>
                        <input type="text" id="entreprise" name="entreprise" class="form-control" value="<?php echo htmlspecialchars($user['entreprise'] ?? ''); ?>">
                    </div>
                    
                    <div class="row">
                        <div class="form-group" style="flex: 1; margin-right: 15px;">
                            <label for="section" class="form-label">Section</label>
                            <select id="section" name="section" class="form-select">
                                <option value="">Sélectionner une section</option>
                                <option value="sol" <?php echo ($user['section'] === 'sol') ? 'selected' : ''; ?>>Sol</option>
                                <option value="vol" <?php echo ($user['section'] === 'vol') ? 'selected' : ''; ?>>Vol</option>
                            </select>
                        </div>
                        
                        <div class="form-group" style="flex: 1;">
                            <label for="fonction" class="form-label">Fonction</label>
                            <select id="fonction" name="fonction" class="form-select">
                                <option value="">Sélectionner une fonction</option>
                                <option value="BE1900D" <?php echo ($user['fonction'] === 'BE1900D') ? 'selected' : ''; ?>>BE1900D</option>
                                <option value="C208B" <?php echo ($user['fonction'] === 'C208B') ? 'selected' : ''; ?>>C208B</option>
                                <option value="BELL206" <?php echo ($user['fonction'] === 'BELL206') ? 'selected' : ''; ?>>BELL206</option>
                                <option value="AT802" <?php echo ($user['fonction'] === 'AT802') ? 'selected' : ''; ?>>AT802</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="role" class="form-label">Rôle</label>
                        <select id="role" name="role" class="form-select" required>
                            <option value="instructor" <?php echo $user['role'] === 'instructor' ? 'selected' : ''; ?>>Instructeur</option>
                            <option value="pilot" <?php echo $user['role'] === 'pilot' ? 'selected' : ''; ?>>Pilote</option>
                            <option value="admin_sol" <?php echo $user['role'] === 'admin_sol' ? 'selected' : ''; ?>>Admin Sol</option>
                            <option value="admin_vol" <?php echo $user['role'] === 'admin_vol' ? 'selected' : ''; ?>>Admin Vol</option>
                        </select>
                    </div>
                    
                    <div class="form-group d-flex justify-content-between">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Enregistrer les modifications
                        </button>
                        <a href="manage_users.php" class="btn btn-outline-primary">
                            <i class="fas fa-arrow-left"></i> Retour
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Changer le mot de passe</h2>
            </div>
            <div class="card-body">
                <?php if (isset($password_success)): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> <?php echo $password_success; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($password_errors)): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i>
                        <ul style="margin-bottom: 0; padding-left: 20px;">
                            <?php foreach ($password_errors as $error): ?>
                                <li><?php echo $error; ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <form action="" method="POST">
                    <div class="form-group">
                        <label for="new_password" class="form-label">Nouveau mot de passe</label>
                        <input type="password" id="new_password" name="new_password" class="form-control" required>
                        <small class="text-muted">Le mot de passe doit contenir au moins 8 caractères.</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password" class="form-label">Confirmer le mot de passe</label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                    </div>
                    
                    <button type="submit" name="change_password" class="btn btn-primary">
                        <i class="fas fa-key"></i> Changer le mot de passe
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script src="../scripts/admin_dashboard.js"></script>
    <script src="../components/alerts.js"></script>
    
    <?php if (isset($success_message)): ?>
    <script>
      document.addEventListener('DOMContentLoaded', function() {
        showSuccess('Succès', '<?php echo addslashes($success_message); ?>');
      });
    </script>
    <?php endif; ?>
    
    <?php if (!empty($errors)): ?>
    <script>
      document.addEventListener('DOMContentLoaded', function() {
        showValidationErrors([
          <?php foreach ($errors as $error): ?>
            '<?php echo addslashes($error); ?>',
          <?php endforeach; ?>
        ]);
      });
    </script>
    <?php endif; ?>
    
    <?php if (isset($password_success)): ?>
    <script>
      document.addEventListener('DOMContentLoaded', function() {
        showSuccess('Succès', '<?php echo addslashes($password_success); ?>');
      });
    </script>
    <?php endif; ?>
    
    <?php if (!empty($password_errors)): ?>
    <script>
      document.addEventListener('DOMContentLoaded', function() {
        showValidationErrors([
          <?php foreach ($password_errors as $error): ?>
            '<?php echo addslashes($error); ?>',
          <?php endforeach; ?>
        ]);
      });
    </script>
    <?php endif; ?>
    <script src="../scripts/user_form.js"></script>
</body>
</html>
