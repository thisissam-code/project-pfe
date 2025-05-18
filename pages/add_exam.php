<?php
session_start();
require '../includes/bd.php';

// Vérifier si l'utilisateur est un admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

// Récupérer la liste des formations au lieu des cours
$formations_query = "SELECT id, title FROM formations ORDER BY title";
$formations = $conn->query($formations_query);

// Traitement du formulaire d'ajout d'examen
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = htmlspecialchars(trim($_POST['title']));
    $description = htmlspecialchars(trim($_POST['description']));
    $formation_id = $_POST['formation_id']; // Changé de course_id à formation_id
    $date = $_POST['date'];
    $duration = $_POST['duration'];
    $passing_score = $_POST['passing_score'];
    
    // Validation
    $errors = [];
    
    if (empty($title) || empty($description) || empty($formation_id) || empty($date) || empty($duration) || empty($passing_score)) {
        $errors[] = "Tous les champs sont obligatoires.";
    }
    
    if (!is_numeric($duration) || $duration <= 0) {
        $errors[] = "La durée doit être un nombre positif.";
    }
    
    if (!is_numeric($passing_score) || $passing_score < 0 || $passing_score > 100) {
        $errors[] = "Le score de réussite doit être un pourcentage entre 0 et 100.";
    }
    
    // Si pas d'erreurs, ajouter l'examen
    if (empty($errors)) {
        $stmt = $conn->prepare("INSERT INTO exams (title, description, formation_id, date, duration, passing_score, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("ssissi", $title, $description, $formation_id, $date, $duration, $passing_score);
        
        if ($stmt->execute()) {
            $success_message = "L'examen a été ajouté avec succès.";
            // Redirection après 2 secondes
            echo "<script>
                Swal.fire({
                    icon: 'success',
                    title: 'Succès!',
                    text: '$success_message',
                    timer: 2000,
                    showConfirmButton: false
                }).then(function() {
                    window.location = 'manage_exams.php';
                });
            </script>";
        } else {
            $errors[] = "Une erreur est survenue lors de l'ajout de l'examen.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajouter un examen - TTA</title>
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
    <div class="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-logo-container">
                <img src="https://hebbkx1anhila5yf.public.blob.vercel-storage.com/Logo-white-cvZ5xEip7mVkwQl9zQfZ2jTD3YYsgA.png" alt="TTA Logo" class="sidebar-logo">
                <span class="sidebar-brand">TTA Admin</span>
            </div>
            <button class="sidebar-toggle" title="Toggle Sidebar">
                <i class="fas fa-chevron-left"></i>
            </button>
        </div>
        <ul class="sidebar-menu">
            <li class="sidebar-item">
                <a href="admin_dashboard.php" class="sidebar-link">
                    <i class="fas fa-tachometer-alt sidebar-icon"></i>
                    <span class="sidebar-text">Tableau de bord</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="manage_users.php" class="sidebar-link">
                    <i class="fas fa-users sidebar-icon"></i>
                    <span class="sidebar-text">Gestion des utilisateurs</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="manage_courses.php" class="sidebar-link">
                    <i class="fas fa-book sidebar-icon"></i>
                    <span class="sidebar-text">Gestion des cours</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="manage_exams.php" class="sidebar-link active">
                    <i class="fas fa-file-alt sidebar-icon"></i>
                    <span class="sidebar-text">Gestion des examens</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="reports.php" class="sidebar-link">
                    <i class="fas fa-chart-bar sidebar-icon"></i>
                    <span class="sidebar-text">Rapports</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="profile.php" class="sidebar-link">
                    <i class="fas fa-user sidebar-icon"></i>
                    <span class="sidebar-text">Mon Profil</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="settings.php" class="sidebar-link">
                    <i class="fas fa-cog sidebar-icon"></i>
                    <span class="sidebar-text">Paramètres</span>
                </a>
            </li>
        </ul>
        <div class="sidebar-footer">
            <div class="sidebar-user">
                <div class="sidebar-user-avatar">
                    <?php echo strtoupper(substr($_SESSION['first_name'], 0, 1)); ?>
                </div>
                <div class="sidebar-user-info">
                    <div class="sidebar-user-name"><?php echo $_SESSION['first_name'] . ' ' . $_SESSION['last_name']; ?></div>
                    <div class="sidebar-user-role">Administrateur</div>
                </div>
            </div>
            <a href="../pages/logout.php" class="sidebar-link mt-2">
                <i class="fas fa-sign-out-alt sidebar-icon"></i>
                <span class="sidebar-text">Déconnexion</span>
            </a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="page-header">
            <h1 class="page-title">Ajouter un examen</h1>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="admin_dashboard.php" class="breadcrumb-link">Accueil</a></li>
                <li class="breadcrumb-item"><a href="manage_exams.php" class="breadcrumb-link">Gestion des examens</a></li>
                <li class="breadcrumb-item">Ajouter un examen</li>
            </ul>
        </div>

        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Informations de l'examen</h2>
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
                    <div class="form-group">
                        <label for="title" class="form-label">Titre de l'examen</label>
                        <input type="text" id="title" name="title" class="form-control" value="<?php echo isset($title) ? htmlspecialchars($title) : ''; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="formation_id" class="form-label">Formation associée</label>
                        <select id="formation_id" name="formation_id" class="form-select" required>
                            <option value="">Sélectionner une formation</option>
                            <?php if ($formations && $formations->num_rows > 0): ?>
                                <?php while ($formation = $formations->fetch_assoc()): ?>
                                    <option value="<?php echo $formation['id']; ?>" <?php echo isset($formation_id) && $formation_id == $formation['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($formation['title']); ?>
                                    </option>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="description" class="form-label">Description</label>
                        <textarea id="description" name="description" class="form-control" rows="4" required><?php echo isset($description) ? htmlspecialchars($description) : ''; ?></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="form-group" style="flex: 1; margin-right: 15px;">
                            <label for="date" class="form-label">Date de l'examen</label>
                            <input type="date" id="date" name="date" class="form-control" value="<?php echo isset($date) ? $date : date('Y-m-d'); ?>" required>
                        </div>
                        
                        <div class="form-group" style="flex: 1; margin-right: 15px;">
                            <label for="duration" class="form-label">Durée (minutes)</label>
                            <input type="number" id="duration" name="duration" class="form-control" value="<?php echo isset($duration) ? $duration : '60'; ?>" min="1" required>
                        </div>
                        
                        <div class="form-group" style="flex: 1;">
                            <label for="passing_score" class="form-label">Score de réussite (%)</label>
                            <input type="number" id="passing_score" name="passing_score" class="form-control" value="<?php echo isset($passing_score) ? $passing_score : '70'; ?>" min="0" max="100" required>
                        </div>
                    </div>
                    
                    <div class="form-group d-flex justify-content-between">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Ajouter l'examen
                        </button>
                        <a href="manage_exams.php" class="btn btn-outline-primary">
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
</body>
</html>