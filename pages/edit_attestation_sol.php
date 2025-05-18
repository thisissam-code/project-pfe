<?php
session_start();
require '../includes/bd.php'; // Connexion à la base de données

// Vérifier si l'utilisateur est un admin avant d'accéder à la page
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin_sol') {
    header("Location: ../index.php"); // Redirige vers la page d'accueil si l'utilisateur n'est pas admin
    exit();
}

// Vérifier si un ID d'attestation est fourni
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
   header("Location: manage_attestations.php");
   exit();
}

$attestation_id = intval($_GET['id']);

// Récupérer les informations de l'attestation
$query = "SELECT a.*, f.section 
          FROM attestations a 
          JOIN formations f ON a.formation_id = f.id 
          WHERE a.id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $attestation_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
   header("Location: manage_attestations.php");
   exit();
}

$attestation = $result->fetch_assoc();

// Vérifier que l'attestation appartient à la section SOL
if ($attestation['section'] !== 'sol') {
   header("Location: manage_attestations.php");
   exit();
}

// Récupérer tous les utilisateurs pour le formulaire
$query_users = "SELECT id, first_name, last_name FROM users WHERE role = 'pilot' ORDER BY last_name, first_name";
$result_users = $conn->query($query_users);

// Récupérer uniquement les formations de la section SOL pour le formulaire
$query_formations = "SELECT id, title FROM formations WHERE section = 'sol' ORDER BY title";
$result_formations = $conn->query($query_formations);

// Traitement du formulaire de mise à jour
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
   $user_id = intval($_POST['user_id']);
   $formation_id = intval($_POST['formation_id']);
   $type = trim($_POST['type']);
   $date_emission = $_POST['date_emission'];
   $date_expiration = !empty($_POST['date_expiration']) ? $_POST['date_expiration'] : NULL;
   $fichier = $attestation['fichier']; // Garder l'ancien fichier par défaut
   
   // Vérifier que la formation appartient à la section SOL
   $check_formation_query = "SELECT id FROM formations WHERE id = ? AND section = 'sol'";
   $check_formation_stmt = $conn->prepare($check_formation_query);
   $check_formation_stmt->bind_param("i", $formation_id);
   $check_formation_stmt->execute();
   $check_formation_result = $check_formation_stmt->get_result();
   
   if ($check_formation_result->num_rows === 0) {
       $error_message = "La formation sélectionnée n'appartient pas à la section SOL.";
   } else {
      // Traitement du fichier uploadé
      if (isset($_FILES['fichier']) && $_FILES['fichier']['error'] === 0) {
          $upload_dir = '../uploads/attestations/sol/';
          
          // Créer le répertoire s'il n'existe pas
          if (!file_exists($upload_dir)) {
              mkdir($upload_dir, 0777, true);
          }
          
          $file_name = time() . '_' . basename($_FILES['fichier']['name']);
          $file_path = $upload_dir . $file_name;
          
          if (move_uploaded_file($_FILES['fichier']['tmp_name'], $file_path)) {
              // Supprimer l'ancien fichier s'il existe
              if ($attestation['fichier'] && file_exists('../uploads/attestations/' . $attestation['fichier'])) {
                  unlink('../uploads/attestations/' . $attestation['fichier']);
              }
              
              $fichier = 'sol/' . $file_name;
          }
      }
      
      // Déterminer le statut de l'attestation
      $statut = 'valide';
      if ($date_expiration) {
          $expiration_date = new DateTime($date_expiration);
          $now = new DateTime();
          $interval = $now->diff($expiration_date);
          
          if ($expiration_date < $now) {
              $statut = 'expire';
          } elseif ($interval->days <= 60) {
              $statut = 'bientot_expire';
          }
      }
      
      // Mettre à jour l'attestation
      $update_query = "UPDATE attestations SET 
                     user_id = ?, 
                     formation_id = ?, 
                     type = ?, 
                     date_emission = ?, 
                     date_expiration = ?, 
                     fichier = ?, 
                     statut = ? 
                     WHERE id = ?";
      $stmt = $conn->prepare($update_query);
      $stmt->bind_param("iisssssi", $user_id, $formation_id, $type, $date_emission, $date_expiration, $fichier, $statut, $attestation_id);
      
      if ($stmt->execute()) {
          $success_message = "Attestation mise à jour avec succès.";
          
          // Recharger les informations de l'attestation
          $stmt = $conn->prepare($query);
          $stmt->bind_param("i", $attestation_id);
          $stmt->execute();
          $result = $stmt->get_result();
          $attestation = $result->fetch_assoc();
      } else {
          $error_message = "Erreur lors de la mise à jour de l'attestation: " . $stmt->error;
      }
   }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
   <meta charset="UTF-8">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>Modifier une attestation - TTA</title>
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
   <link rel="stylesheet" href="../assets/admin_dashboard.css">
   <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.19/dist/sweetalert2.all.min.js"></script>
   <script src="../assets/alerts.js"></script>
</head>
<body>
   <!-- Mobile Navigation Toggle -->
   <button class="mobile-nav-toggle">
       <i class="fas fa-bars"></i>
   </button>

   <!-- Sidebar -->
   <?php include '../includes/admin_sol_sidebar.php'; ?>

   <!-- Main Content -->
   <div class="main-content">
       <div class="page-header">
           <h1 class="page-title">Modifier une attestation <span class="badge bg-primary">SOL</span></h1>
           <ul class="breadcrumb">
               <li class="breadcrumb-item"><a href="admin_dashboard_sol.php" class="breadcrumb-link">Accueil</a></li>
               <li class="breadcrumb-item"><a href="manage_attestations_sol.php" class="breadcrumb-link">Gestion des attestations</a></li>
               <li class="breadcrumb-item">Modifier une attestation</li>
           </ul>
       </div>

       <?php if (isset($success_message)): ?>
           <script>
               document.addEventListener('DOMContentLoaded', function() {
                   Swal.fire({
                       title: 'Succès!',
                       text: '<?php echo addslashes($success_message); ?>',
                       icon: 'success',
                       confirmButtonText: 'OK'
                   });
               });
           </script>
       <?php endif; ?>

       <?php if (isset($error_message)): ?>
           <script>
               document.addEventListener('DOMContentLoaded', function() {
                   Swal.fire({
                       title: 'Erreur!',
                       text: '<?php echo addslashes($error_message); ?>',
                       icon: 'error',
                       confirmButtonText: 'OK'
                   });
               });
           </script>
       <?php endif; ?>

       <div class="card animate-on-scroll">
           <div class="card-header">
               <h2 class="card-title">Informations de l'attestation</h2>
           </div>
           <div class="card-body">
               <form method="POST" action="" enctype="multipart/form-data">
                   <div class="form-group">
                       <label for="user_id" class="form-label">Pilote</label>
                       <select id="user_id" name="user_id" class="form-select" required>
                           <option value="">Sélectionner un pilote</option>
                           <?php if ($result_users && $result_users->num_rows > 0): ?>
                               <?php while ($user = $result_users->fetch_assoc()): ?>
                                   <option value="<?php echo $user['id']; ?>" <?php echo $attestation['user_id'] == $user['id'] ? 'selected' : ''; ?>>
                                       <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                                   </option>
                               <?php endwhile; ?>
                           <?php endif; ?>
                       </select>
                   </div>
                   <div class="form-group">
                       <label for="formation_id" class="form-label">Formation SOL</label>
                       <select id="formation_id" name="formation_id" class="form-select" required>
                           <option value="">Sélectionner une formation</option>
                           <?php if ($result_formations && $result_formations->num_rows > 0): ?>
                               <?php while ($formation = $result_formations->fetch_assoc()): ?>
                                   <option value="<?php echo $formation['id']; ?>" <?php echo $attestation['formation_id'] == $formation['id'] ? 'selected' : ''; ?>>
                                       <?php echo htmlspecialchars($formation['title']); ?>
                                   </option>
                               <?php endwhile; ?>
                           <?php endif; ?>
                       </select>
                   </div>
                   <div class="form-group">
                       <label for="type" class="form-label">Type d'attestation</label>
                       <select id="type" name="type" class="form-select" required>
                           <option value="interne" <?php echo $attestation['type'] === 'interne' ? 'selected' : ''; ?>>Interne</option>
                           <option value="externe" <?php echo $attestation['type'] === 'externe' ? 'selected' : ''; ?>>Externe</option>
                       </select>
                   </div>
                   <div class="form-group">
                       <label for="date_emission" class="form-label">Date d'émission</label>
                       <input type="date" id="date_emission" name="date_emission" class="form-control" value="<?php echo date('Y-m-d', strtotime($attestation['date_emission'])); ?>" required>
                   </div>
                   <div class="form-group">
                       <label for="date_expiration" class="form-label">Date d'expiration (optionnel)</label>
                       <input type="date" id="date_expiration" name="date_expiration" class="form-control" value="<?php echo $attestation['date_expiration'] ? date('Y-m-d', strtotime($attestation['date_expiration'])) : ''; ?>">
                   </div>
                   <div class="form-group">
                       <label for="fichier" class="form-label">Fichier (PDF)</label>
                       <?php if ($attestation['fichier']): ?>
                           <p>Fichier actuel: <a href="../uploads/attestations/<?php echo $attestation['fichier']; ?>" target="_blank"><?php echo basename($attestation['fichier']); ?></a></p>
                       <?php endif; ?>
                       <input type="file" id="fichier" name="fichier" class="form-control" accept=".pdf">
                       <small class="text-muted">Laissez vide pour conserver le fichier actuel.</small>
                   </div>
                   <div class="d-flex justify-content-between">
                       <a href="manage_attestations_sol.php" class="btn btn-secondary">Annuler</a>
                       <button type="submit" class="btn btn-primary">Enregistrer les modifications</button>
                   </div>
               </form>
           </div>
       </div>
   </div>

   <script src="../scripts/admin_dashboard.js"></script>
</body>
</html>