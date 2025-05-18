<?php
session_start();
require '../includes/bd.php'; // Connexion à la base de données

// Vérifier si l'utilisateur est un admin avant d'accéder à la page
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin_sol') {
    header("Location: ../index.php"); // Redirige vers la page d'accueil si l'utilisateur n'est pas admin
    exit();
}

// Récupérer toutes les attestations liées aux formations de la section SOL
$query_attestations = "SELECT a.*, 
                      CONCAT(u.first_name, ' ', u.last_name) as user_name,
                      f.title as formation_title
                      FROM attestations a
                      JOIN users u ON a.user_id = u.id
                      JOIN formations f ON a.formation_id = f.id
                      WHERE f.section = 'sol'
                      ORDER BY a.date_emission DESC";
$result_attestations = $conn->query($query_attestations);

// Récupérer tous les utilisateurs pour le formulaire d'ajout
$query_users = "SELECT id, first_name, last_name FROM users WHERE role = 'pilot' ORDER BY last_name, first_name";
$result_users = $conn->query($query_users);

// Récupérer uniquement les formations de la section SOL pour le formulaire d'ajout
$query_formations = "SELECT id, title FROM formations WHERE section = 'sol' ORDER BY title";
$result_formations = $conn->query($query_formations);

// Traitement de l'ajout d'une attestation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_attestation'])) {
   $user_id = intval($_POST['user_id']);
   $formation_id = intval($_POST['formation_id']);
   $type = trim($_POST['type']);
   $date_emission = $_POST['date_emission'];
   $date_expiration = !empty($_POST['date_expiration']) ? $_POST['date_expiration'] : NULL;
   $fichier = NULL;
   
   // Vérifier que la formation appartient à la section SOL
   $check_formation_query = "SELECT id FROM formations WHERE id = ? AND section = 'sol'";
   $check_formation_stmt = $conn->prepare($check_formation_query);
   $check_formation_stmt->bind_param("i", $formation_id);
   $check_formation_stmt->execute();
   $check_formation_result = $check_formation_stmt->get_result();
   
   if ($check_formation_result->num_rows === 0) {
       $error_message = "La formation sélectionnée n'appartient pas à la section SOL.";
   } else {
       // Vérifier si une attestation valide existe déjà pour cet utilisateur et cette formation
       $check_query = "SELECT id, statut, date_expiration FROM attestations 
                      WHERE user_id = ? AND formation_id = ? 
                      ORDER BY date_emission DESC LIMIT 1";
       $check_stmt = $conn->prepare($check_query);
       $check_stmt->bind_param("ii", $user_id, $formation_id);
       $check_stmt->execute();
       $check_result = $check_stmt->get_result();
       
       $can_add_attestation = true;
       $attestation_exists = false;
       
       if ($check_result->num_rows > 0) {
           $attestation_exists = true;
           $existing_attestation = $check_result->fetch_assoc();
           
           // Vérifier si l'attestation existante est expirée
           if ($existing_attestation['statut'] !== 'expire') {
               $can_add_attestation = false;
               $error_message = "Une attestation valide existe déjà pour ce pilote et cette formation. Vous ne pouvez ajouter une nouvelle attestation que lorsque l'attestation existante est expirée.";
           }
       }
       
       if ($can_add_attestation) {
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
                   // Stocker uniquement le nom du fichier, pas le chemin complet
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
           
           // Si une attestation existante est expirée, mettre à jour son statut pour être sûr
           if ($attestation_exists) {
               $update_query = "UPDATE attestations SET statut = 'expire' WHERE id = ?";
               $update_stmt = $conn->prepare($update_query);
               $update_stmt->bind_param("i", $existing_attestation['id']);
               $update_stmt->execute();
           }
           
           // Insérer la nouvelle attestation
           $insert_query = "INSERT INTO attestations (user_id, formation_id, type, date_emission, date_expiration, fichier, statut) 
                           VALUES (?, ?, ?, ?, ?, ?, ?)";
           $stmt = $conn->prepare($insert_query);
           $stmt->bind_param("iisssss", $user_id, $formation_id, $type, $date_emission, $date_expiration, $fichier, $statut);
           
           try {
               if ($stmt->execute()) {
                   $success_message = "Attestation SOL ajoutée avec succès.";
                   
                   // Recharger les attestations
                   $result_attestations = $conn->query($query_attestations);
               } else {
                   $error_message = "Erreur lors de l'ajout de l'attestation: " . $stmt->error;
               }
           } catch (mysqli_sql_exception $e) {
               $error_message = "Erreur lors de l'ajout de l'attestation: " . $e->getMessage();
           }
       }
   }
}

// Traitement de la suppression d'une attestation
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
   $attestation_id = intval($_GET['delete']);
   
   // Vérifier que l'attestation est liée à une formation SOL avant de la supprimer
   $check_attestation_query = "SELECT a.id, a.fichier 
                              FROM attestations a 
                              JOIN formations f ON a.formation_id = f.id 
                              WHERE a.id = ? AND f.section = 'sol'";
   $check_stmt = $conn->prepare($check_attestation_query);
   $check_stmt->bind_param("i", $attestation_id);
   $check_stmt->execute();
   $check_result = $check_stmt->get_result();
   
   if ($check_result->num_rows > 0) {
       $attestation = $check_result->fetch_assoc();
       
       // Supprimer le fichier s'il existe
       if ($attestation['fichier']) {
           $file_path = '../uploads/attestations/' . $attestation['fichier'];
           if (file_exists($file_path)) {
               unlink($file_path);
           }
       }
       
       // Supprimer l'attestation
       $delete_query = "DELETE FROM attestations WHERE id = ?";
       $stmt = $conn->prepare($delete_query);
       $stmt->bind_param("i", $attestation_id);
       
       if ($stmt->execute()) {
           header("Location: manage_attestations.php?deleted=1");
           exit();
       } else {
           $error_message = "Erreur lors de la suppression de l'attestation.";
       }
   } else {
       $error_message = "L'attestation n'existe pas ou n'est pas liée à une formation SOL.";
   }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
   <meta charset="UTF-8">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>Gestion des Attestations SOL - TTA</title>
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
   <link rel="stylesheet" href="../assets/admin_dashboard.css">
   <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.19/dist/sweetalert2.min.css">
   <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.19/dist/sweetalert2.all.min.js"></script>
   <script src="../assets/alerts.js"></script>
</head>
<body>
   <!-- Mobile Navigation Toggle -->
   <button class="mobile-nav-toggle">
       <i class="fas fa-bars"></i>
   </button>

   <?php include '../includes/admin_sol_sidebar.php'; ?>

   <!-- Main Content -->
   <div class="main-content">
       <div class="page-header">
           <h1 class="page-title">Gestion des Attestations <span class="badge bg-primary">SOL</span></h1>
           <ul class="breadcrumb">
               <li class="breadcrumb-item"><a href="admin_dashboard.php" class="breadcrumb-link">Accueil</a></li>
               <li class="breadcrumb-item">Gestion des attestations SOL</li>
           </ul>
       </div>

       <?php if (isset($_GET['deleted'])): ?>
           <script>
               document.addEventListener('DOMContentLoaded', function() {
                   showSuccess('Succès', 'Attestation SOL supprimée avec succès.');
               });
           </script>
       <?php endif; ?>

       <?php if (isset($success_message)): ?>
           <script>
               document.addEventListener('DOMContentLoaded', function() {
                   showSuccess('Succès', '<?php echo addslashes($success_message); ?>');
               });
           </script>
       <?php endif; ?>

       <?php if (isset($error_message)): ?>
           <script>
               document.addEventListener('DOMContentLoaded', function() {
                   showError('Erreur', '<?php echo addslashes($error_message); ?>');
               });
           </script>
       <?php endif; ?>

       <!-- Add Attestation Form -->
       <div class="card animate-on-scroll">
           <div class="card-header">
               <h2 class="card-title">Ajouter une attestation SOL</h2>
           </div>
           <div class="card-body">
               <form method="POST" action="" enctype="multipart/form-data">
                   <div class="form-group">
                       <label for="user_id" class="form-label">Pilote</label>
                       <select id="user_id" name="user_id" class="form-select" required>
                           <option value="">Sélectionner un pilote</option>
                           <?php if ($result_users && $result_users->num_rows > 0): ?>
                               <?php while ($user = $result_users->fetch_assoc()): ?>
                                   <option value="<?php echo $user['id']; ?>">
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
                                   <option value="<?php echo $formation['id']; ?>">
                                       <?php echo htmlspecialchars($formation['title']); ?>
                                   </option>
                               <?php endwhile; ?>
                           <?php endif; ?>
                       </select>
                   </div>
                   <div class="form-group">
                       <label for="type" class="form-label">Type d'attestation</label>
                       <select id="type" name="type" class="form-select" required>
                           <option value="interne">Interne</option>
                           <option value="externe">Externe</option>
                       </select>
                   </div>
                   <div class="form-group">
                       <label for="date_emission" class="form-label">Date d'émission</label>
                       <input type="date" id="date_emission" name="date_emission" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                   </div>
                   <div class="form-group">
                       <label for="date_expiration" class="form-label">Date d'expiration (optionnel)</label>
                       <input type="date" id="date_expiration" name="date_expiration" class="form-control">
                   </div>
                   <div class="form-group">
                       <label for="fichier" class="form-label">Fichier (PDF)</label>
                       <input type="file" id="fichier" name="fichier" class="form-control" accept=".pdf">
                   </div>
                   <button type="submit" name="add_attestation" class="btn btn-primary">Ajouter l'attestation</button>
               </form>
           </div>
       </div>

       <!-- Attestations List -->
       <div class="card animate-on-scroll">
           <div class="card-header">
               <h2 class="card-title">Liste des attestations SOL</h2>
           </div>
           <div class="card-body">
               <div class="table-container">
                   <table class="table">
                       <thead>
                           <tr>
                               <th>Pilote</th>
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
                                       <td><?php echo htmlspecialchars($attestation['user_name']); ?></td>
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
                                       <td class="table-actions">
                                           <?php if ($attestation['fichier']): ?>
                                               <a href="../uploads/attestations/<?php echo $attestation['fichier']; ?>" target="_blank" class="btn-action" data-tooltip="Voir">
                                                   <i class="fas fa-eye"></i>
                                               </a>
                                           <?php endif; ?>
                                           <a href="edit_attestation.php?id=<?php echo $attestation['id']; ?>" class="btn-action btn-edit" data-tooltip="Modifier">
                                               <i class="fas fa-edit"></i>
                                           </a>
                                           <a href="javascript:void(0);" class="btn-action btn-delete" data-tooltip="Supprimer" onclick="confirmDelete('Confirmer la suppression', 'Êtes-vous sûr de vouloir supprimer cette attestation ?', 'manage_attestations.php?delete=<?php echo $attestation['id']; ?>')">
                                               <i class="fas fa-trash"></i>
                                           </a>
                                       </td>
                                   </tr>
                               <?php endwhile; ?>
                           <?php else: ?>
                               <tr>
                                   <td colspan="7" class="text-center">Aucune attestation SOL trouvée</td>
                               </tr>
                           <?php endif; ?>
                       </tbody>
                   </table>
               </div>
           </div>
       </div>
   </div>

   <script src="../scripts/admin_dashboard.js"></script>
</body>
</html>