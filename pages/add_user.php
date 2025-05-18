<?php
session_start();
require '../includes/bd.php';

// Vérifier si l'utilisateur est un admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

// Traitement du formulaire d'ajout d'utilisateur
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = htmlspecialchars(trim($_POST['first_name']));
    $last_name = htmlspecialchars(trim($_POST['last_name']));
    $email = htmlspecialchars(trim($_POST['email']));
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role = $_POST['role'];
    $entreprise = isset($_POST['entreprise']) ? htmlspecialchars(trim($_POST['entreprise'])) : null;
    $section = isset($_POST['section']) ? $_POST['section'] : null;
    $fonction = isset($_POST['fonction']) ? $_POST['fonction'] : null;

    // Validation
    $errors = [];
    
    if (empty($first_name) || empty($last_name) || empty($email) || empty($password) || empty($role)) {
        $errors[] = "Tous les champs sont obligatoires.";
    }
    
    if ($password !== $confirm_password) {
        $errors[] = "Les mots de passe ne correspondent pas.";
    }
    
    if (strlen($password) < 8) {
        $errors[] = "Le mot de passe doit contenir au moins 8 caractères.";
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "L'adresse email n'est pas valide.";
    }
    
    // Vérifier si l'email existe déjà
    $check_email = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $check_email->bind_param("s", $email);
    $check_email->execute();
    $email_result = $check_email->get_result();
    
    if ($email_result->num_rows > 0) {
        $errors[] = "Cet email est déjà utilisé.";
    }
    
    // Si pas d'erreurs, ajouter l'utilisateur
    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $created_at = date('Y-m-d H:i:s');
        
        $stmt = $conn->prepare("INSERT INTO users (first_name, last_name, email, password, role, entreprise, section, fonction, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssssss", $first_name, $last_name, $email, $hashed_password, $role, $entreprise, $section, $fonction, $created_at);
        
        if ($stmt->execute()) {
            $success_message = "L'utilisateur a été ajouté avec succès.";
        } else {
            $errors[] = "Une erreur est survenue lors de l'ajout de l'utilisateur.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajouter un utilisateur - TTA</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/admin_dashboard.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
            <h1 class="page-title">Ajouter un utilisateur</h1>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="admin_dashboard.php" class="breadcrumb-link">Accueil</a></li>
                <li class="breadcrumb-item"><a href="manage_users.php" class="breadcrumb-link">Gestion des utilisateurs</a></li>
                <li class="breadcrumb-item">Ajouter un utilisateur</li>
            </ul>
        </div>

        <div class="card">
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
                            <input type="text" id="first_name" name="first_name" class="form-control" value="<?php echo isset($first_name) ? htmlspecialchars($first_name) : ''; ?>" required>
                        </div>
                        
                        <div class="form-group" style="flex: 1;">
                            <label for="last_name" class="form-label">Nom</label>
                            <input type="text" id="last_name" name="last_name" class="form-control" value="<?php echo isset($last_name) ? htmlspecialchars($last_name) : ''; ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" id="email" name="email" class="form-control" value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="role" class="form-label">Rôle</label>
                        <select id="role" name="role" class="form-select" required>
                            <option value="">Sélectionner un rôle</option>
                            <option value="admin_sol" <?php echo isset($role) && $role === 'admin_sol' ? 'selected' : ''; ?>>Administrateur_SOL</option>
                            <option value="admin_vol" <?php echo isset($role) && $role === 'admin_vol' ? 'selected' : ''; ?>>Administrateur_VOL</option>
                            <option value="instructor" <?php echo isset($role) && $role === 'instructor' ? 'selected' : ''; ?>>Instructeur</option>
                            <option value="pilot" <?php echo isset($role) && $role === 'pilot' ? 'selected' : ''; ?>>Pilote</option>
                        </select>
                    </div>
                    <div class="form-group">
                     <label for="entreprise" class="form-label">Entreprise</label>
                     <input type="text" id="entreprise" name="entreprise" class="form-control" value="<?php echo isset($entreprise) ? htmlspecialchars($entreprise) : ''; ?>">
                    </div>
                    <div class="form-group">
                     <label for="section" class="form-label">Section</label>
                     <select id="section" name="section" class="form-select">
                        <option value="">Sélectionner une section</option>
                        <option value="sol" <?php echo (isset($section) && $section === 'sol') ? 'selected' : ''; ?>>Sol</option>
                        <option value="vol" <?php echo (isset($section) && $section === 'vol') ? 'selected' : ''; ?>>Vol</option>
                     </select>
                    </div>
                    <div class="form-group">
                     <label for="fonction" class="form-label">Fonction</label>
                       <select id="fonction" name="fonction" class="form-select">
                          <option value="">Sélectionner une fonction</option>
                          <option value="BE1900D" <?php echo (isset($fonction) && $fonction === 'BE1900D') ? 'selected' : ''; ?>>BE1900D</option>
                          <option value="C208B" <?php echo (isset($fonction) && $fonction === 'C208B') ? 'selected' : ''; ?>>C208B</option>
                          <option value="BELL206" <?php echo (isset($fonction) && $fonction === 'BELL206') ? 'selected' : ''; ?>>BELL206</option>
                          <option value="AT802" <?php echo (isset($fonction) && $fonction === 'AT802') ? 'selected' : ''; ?>>AT802</option>
                       </select>
                   </div>

                    <div class="form-group">
                        <label for="password" class="form-label">Mot de passe</label>
                        <input type="password" id="password" name="password" class="form-control" required>
                        <small class="text-muted">Le mot de passe doit contenir au moins 8 caractères.</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password" class="form-label">Confirmer le mot de passe</label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                    </div>
                    
                    <div class="form-group d-flex justify-content-between">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Ajouter l'utilisateur
                        </button>
                        <a href="manage_users.php" class="btn btn-outline-primary">
                            <i class="fas fa-arrow-left"></i> Retour
                        </a>
                    </div>
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
  <script src="../scripts/user_form.js"></script>
</body>
</html>

