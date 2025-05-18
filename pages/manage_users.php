<?php
session_start();
require '../includes/bd.php'; // Connexion à la base de données

// Vérifier si l'utilisateur est un admin avant d'accéder à la page
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
  header("Location: ../index.php");
  exit();
}

// Récupérer tous les utilisateurs
$query_users = "SELECT id, first_name, last_name, email, role, created_at, last_login, entreprise, section, fonction FROM users ORDER BY created_at DESC";
$result_users = $conn->query($query_users);

// Traitement de la suppression d'un utilisateur
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
  $user_id = intval($_GET['delete']);
  
  // Vérifier que l'utilisateur n'est pas l'admin actuel
  if ($user_id != $_SESSION['user_id']) {
      $delete_query = "DELETE FROM users WHERE id = ?";
      $stmt = $conn->prepare($delete_query);
      $stmt->bind_param("i", $user_id);
      
      if ($stmt->execute()) {
          header("Location: manage_users.php?deleted=1");
          exit();
      } else {
          $error_message = "Erreur lors de la suppression de l'utilisateur.";
      }
  } else {
      $error_message = "Vous ne pouvez pas supprimer votre propre compte.";
  }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Gestion des Utilisateurs - TTA</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="../assets/admin_dashboard.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.19/dist/sweetalert2.min.css">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.19/dist/sweetalert2.all.min.js"></script>
  <script src="../components/alerts.js"></script>
</head>
<body>
<?php include '../includes/admin_sidebar.php'; ?>
  <!-- Mobile Navigation Toggle -->
  <button class="mobile-nav-toggle">
      <i class="fas fa-bars"></i>
  </button>

  

  <!-- Main Content -->
  <div class="main-content">
      <div class="page-header">
          <h1 class="page-title">Gestion des Utilisateurs</h1>
          <ul class="breadcrumb">
              <li class="breadcrumb-item"><a href="admin_dashboard.php" class="breadcrumb-link">Accueil</a></li>
              <li class="breadcrumb-item">Gestion des utilisateurs</li>
          </ul>
      </div>

      <?php if (isset($_GET['deleted'])): ?>
          <div class="alert alert-success">
              Utilisateur supprimé avec succès.
          </div>
      <?php endif; ?>

      <?php if (isset($error_message)): ?>
          <div class="alert alert-danger">
              <?php echo $error_message; ?>
          </div>
      <?php endif; ?>

      <!-- Users Management -->
      <div class="card animate-on-scroll">
          <div class="card-header">
              <h2 class="card-title">Liste des Utilisateurs</h2>
              <a href="add_user.php" class="btn btn-primary btn-sm">
                  <i class="fas fa-plus"></i> Ajouter un utilisateur
              </a>
          </div>
          <div class="card-body">
              <div class="table-container">
                  <table class="table">
                      <thead>
                          <tr>
                              <th>Nom</th>
                              <th>Email</th>
                              <th>Rôle</th>
                              <th>Date d'inscription</th>
                              <th>Dernière connexion</th>
                              <th>Entreprise</th>
                              <th>Section</th>
                              <th>Fonction</th>
                              <th>Actions</th>
                          </tr>
                      </thead>
                      <tbody>
                          <?php if ($result_users && $result_users->num_rows > 0): ?>
                              <?php while ($user = $result_users->fetch_assoc()): ?>
                                  <tr>
                                      <td><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></td>
                                      <td><?php echo htmlspecialchars($user['email']); ?></td>
                                      <td>
                                          <?php if ($user['role'] === 'admin'): ?>
                                              <span class="badge bg-primary">Admin</span>
                                          <?php elseif ($user['role'] === 'pilot'): ?>
                                              <span class="badge bg-secondary">Pilote</span>
                                          <?php elseif ($user['role'] === 'instructor'): ?>
                                              <span class="badge bg-info">Instructeur</span>
                                          <?php endif; ?>
                                      </td>
                                      <td><?php echo date('d/m/Y', strtotime($user['created_at'])); ?></td>
                                      <td><?php echo $user['last_login'] ? date('d/m/Y H:i', strtotime($user['last_login'])) : 'Jamais'; ?></td>
                                      <td><?php echo htmlspecialchars($user['entreprise']); ?></td>
                                      <td>
                                        <?php 
                                         if ($user['section'] === 'sol') echo 'Sol';
                                         elseif ($user['section'] === 'vol') echo 'Vol';
                                         else echo '—';
                                         ?>
                                      </td>
                                      <td><?php echo htmlspecialchars($user['fonction']); ?></td>
                                      <td class="table-actions">
                                          <a href="view_user.php?id=<?php echo $user['id']; ?>" class="btn-action" data-tooltip="Voir">
                                              <i class="fas fa-eye"></i>
                                          </a>
                                          <a href="edit_user.php?id=<?php echo $user['id']; ?>" class="btn-action btn-edit" data-tooltip="Modifier">
                                              <i class="fas fa-edit"></i>
                                          </a>
                                          <a href="manage_users.php?delete=<?php echo $user['id']; ?>" class="btn-action btn-delete" data-tooltip="Supprimer" data-item-name="l'utilisateur <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>">
                                              <i class="fas fa-trash"></i>
                                          </a>
                                      </td>
                                  </tr>
                              <?php endwhile; ?>
                          <?php else: ?>
                              <tr>
                                  <td colspan="6" class="text-center">Aucun utilisateur trouvé</td>
                              </tr>
                          <?php endif; ?>
                      </tbody>
                  </table>
              </div>
          </div>
      </div>

      <!-- Connection Logs (Security Table) -->
      <div class="card animate-on-scroll">
          <div class="card-header">
              <h2 class="card-title">Historique des Connexions</h2>
          </div>
          <div class="card-body">
              <div class="table-container">
                  <table class="table">
                      <thead>
                          <tr>
                              <th>Utilisateur</th>
                              <th>Date</th>
                              <th>Adresse IP</th>
                              <th>Navigateur</th>
                              <th>Statut</th>
                          </tr>
                      </thead>
                  </table>
              </div>
          </div>
      </div>
  </div>

  <script src="../scripts/admin_dashboard.js"></script>
  
</body>
</html>

