<?php
session_start();
require '../includes/bd.php'; // Connexion à la base de données

// Vérifier si l'utilisateur est un admin avant d'accéder à la page
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
  header("Location: ../index.php");
  exit();
}

$success_message = "";
$error_message = "";

// Vérifier si la table settings existe
$check_table = $conn->query("SHOW TABLES LIKE 'settings'");
if ($check_table->num_rows === 0) {
    // Créer la table settings si elle n'existe pas
    $create_table = "CREATE TABLE settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        site_name VARCHAR(255) NOT NULL DEFAULT 'TTA',
        site_description TEXT,
        maintenance_mode TINYINT(1) NOT NULL DEFAULT 0,
        smtp_host VARCHAR(255),
        smtp_port VARCHAR(10),
        smtp_username VARCHAR(255),
        smtp_password VARCHAR(255),
        smtp_encryption VARCHAR(10)
    )";
    
    if ($conn->query($create_table)) {
        // Insérer des valeurs par défaut
        $conn->query("INSERT INTO settings (id, site_name, site_description, maintenance_mode) VALUES (1, 'TTA', 'Tassili Travail Aérien', 0)");
    } else {
        $error_message = "Erreur lors de la création de la table des paramètres: " . $conn->error;
    }
}

// Récupérer les paramètres actuels
$query = "SELECT * FROM settings WHERE id = 1";
$result = $conn->query($query);

if ($result && $result->num_rows > 0) {
  $settings = $result->fetch_assoc();
} else {
  // Créer des paramètres par défaut si aucun n'existe
  $conn->query("INSERT INTO settings (site_name, site_description, maintenance_mode, smtp_host, smtp_port, smtp_username, smtp_password, smtp_encryption) 
               VALUES ('TTA', 'Tassili Travail Aérien', 0, '', '', '', '', '')");
  $settings = [
      'site_name' => 'TTA',
      'site_description' => 'Tassili Travail Aérien',
      'maintenance_mode' => 0,
      'smtp_host' => '',
      'smtp_port' => '',
      'smtp_username' => '',
      'smtp_password' => '',
      'smtp_encryption' => ''
  ];
}

// Traitement du formulaire des paramètres généraux
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_general'])) {
  $site_name = trim($_POST['site_name']);
  $site_description = trim($_POST['site_description']);
  $maintenance_mode = isset($_POST['maintenance_mode']) ? 1 : 0;
  
  $update_query = "UPDATE settings SET site_name = ?, site_description = ?, maintenance_mode = ? WHERE id = 1";
  $stmt = $conn->prepare($update_query);
  
  if ($stmt) {
      $stmt->bind_param("ssi", $site_name, $site_description, $maintenance_mode);
      
      if ($stmt->execute()) {
          $success_message = "Paramètres généraux mis à jour avec succès.";
          
          // Mettre à jour les paramètres locaux
          $settings['site_name'] = $site_name;
          $settings['site_description'] = $site_description;
          $settings['maintenance_mode'] = $maintenance_mode;
      } else {
          $error_message = "Erreur lors de la mise à jour des paramètres généraux: " . $stmt->error;
      }
  } else {
      $error_message = "Erreur de préparation de la requête: " . $conn->error;
  }
}

// Traitement du formulaire des paramètres d'email
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_email'])) {
  $smtp_host = trim($_POST['smtp_host']);
  $smtp_port = trim($_POST['smtp_port']);
  $smtp_username = trim($_POST['smtp_username']);
  $smtp_password = trim($_POST['smtp_password']);
  $smtp_encryption = trim($_POST['smtp_encryption']);
  
  $update_query = "UPDATE settings SET smtp_host = ?, smtp_port = ?, smtp_username = ?, smtp_password = ?, smtp_encryption = ? WHERE id = 1";
  $stmt = $conn->prepare($update_query);
  
  if ($stmt) {
      $stmt->bind_param("sssss", $smtp_host, $smtp_port, $smtp_username, $smtp_password, $smtp_encryption);
      
      if ($stmt->execute()) {
          $success_message = "Paramètres d'email mis à jour avec succès.";
          
          // Mettre à jour les paramètres locaux
          $settings['smtp_host'] = $smtp_host;
          $settings['smtp_port'] = $smtp_port;
          $settings['smtp_username'] = $smtp_username;
          $settings['smtp_password'] = $smtp_password;
          $settings['smtp_encryption'] = $smtp_encryption;
      } else {
          $error_message = "Erreur lors de la mise à jour des paramètres d'email: " . $stmt->error;
      }
  } else {
      $error_message = "Erreur de préparation de la requête: " . $conn->error;
  }
}

// Traitement du test d'email
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_email'])) {
  $test_email = trim($_POST['test_email_address']);
  
  // Ici, vous pouvez implémenter l'envoi d'un email de test
  // Pour l'instant, nous simulons juste un succès
  $success_message = "Email de test envoyé à $test_email. Vérifiez votre boîte de réception.";
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Paramètres - TTA</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="../assets/admin_dashboard.css">
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
          <h1 class="page-title">Paramètres</h1>
          <ul class="breadcrumb">
              <li class="breadcrumb-item"><a href="admin_dashboard.php" class="breadcrumb-link">Accueil</a></li>
              <li class="breadcrumb-item">Paramètres</li>
          </ul>
      </div>

      <?php if (!empty($success_message)): ?>
          <div class="alert alert-success">
              <?php echo $success_message; ?>
          </div>
      <?php endif; ?>

      <?php if (!empty($error_message)): ?>
          <div class="alert alert-danger">
              <?php echo $error_message; ?>
          </div>
      <?php endif; ?>

      <div class="profile-tabs animate-on-scroll">
          <div class="profile-tab active" data-tab="general-settings">Paramètres généraux</div>
          <div class="profile-tab" data-tab="email-settings">Paramètres d'email</div>
      </div>

      <!-- General Settings Tab -->
      <div id="general-settings" class="profile-content active animate-on-scroll">
          <div class="card">
              <div class="card-header">
                  <h2 class="card-title">Paramètres généraux</h2>
              </div>
              <div class="card-body">
                  <form method="POST" action="">
                      <div class="form-group">
                          <label for="site_name" class="form-label">Nom du site</label>
                          <input type="text" id="site_name" name="site_name" class="form-control" value="<?php echo htmlspecialchars($settings['site_name']); ?>" required>
                      </div>
                      <div class="form-group">
                          <label for="site_description" class="form-label">Description du site</label>
                          <textarea id="site_description" name="site_description" class="form-control" rows="3"><?php echo htmlspecialchars($settings['site_description']); ?></textarea>
                      </div>
                      <div class="form-group">
                          <label class="form-label d-flex align-items-center">
                              <input type="checkbox" name="maintenance_mode" <?php echo $settings['maintenance_mode'] ? 'checked' : ''; ?>>
                              <span class="ml-2">Mode maintenance</span>
                          </label>
                          <small class="text-muted">Lorsque activé, seuls les administrateurs peuvent accéder au site.</small>
                      </div>
                      <button type="submit" name="update_general" class="btn btn-primary">Enregistrer les modifications</button>
                  </form>
              </div>
          </div>
      </div>

      <!-- Email Settings Tab -->
      <div id="email-settings" class="profile-content animate-on-scroll">
          <div class="card">
              <div class="card-header">
                  <h2 class="card-title">Paramètres d'email</h2>
              </div>
              <div class="card-body">
                  <form method="POST" action="">
                      <div class="form-group">
                          <label for="smtp_host" class="form-label">Serveur SMTP</label>
                          <input type="text" id="smtp_host" name="smtp_host" class="form-control" value="<?php echo htmlspecialchars($settings['smtp_host']); ?>">
                      </div>
                      <div class="form-group">
                          <label for="smtp_port" class="form-label">Port SMTP</label>
                          <input type="text" id="smtp_port" name="smtp_port" class="form-control" value="<?php echo htmlspecialchars($settings['smtp_port']); ?>">
                      </div>
                      <div class="form-group">
                          <label for="smtp_username" class="form-label">Nom d'utilisateur SMTP</label>
                          <input type="text" id="smtp_username" name="smtp_username" class="form-control" value="<?php echo htmlspecialchars($settings['smtp_username']); ?>">
                      </div>
                      <div class="form-group">
                          <label for="smtp_password" class="form-label">Mot de passe SMTP</label>
                          <input type="password" id="smtp_password" name="smtp_password" class="form-control" value="<?php echo htmlspecialchars($settings['smtp_password']); ?>">
                      </div>
                      <div class="form-group">
                          <label for="smtp_encryption" class="form-label">Chiffrement SMTP</label>
                          <select id="smtp_encryption" name="smtp_encryption" class="form-select">
                              <option value="" <?php echo $settings['smtp_encryption'] === '' ? 'selected' : ''; ?>>Aucun</option>
                              <option value="tls" <?php echo $settings['smtp_encryption'] === 'tls' ? 'selected' : ''; ?>>TLS</option>
                              <option value="ssl" <?php echo $settings['smtp_encryption'] === 'ssl' ? 'selected' : ''; ?>>SSL</option>
                          </select>
                      </div>
                      <button type="submit" name="update_email" class="btn btn-primary">Enregistrer les modifications</button>
                  </form>

                  <hr>

                  <h3>Tester la configuration</h3>
                  <form method="POST" action="" class="mt-3">
                      <div class="form-group">
                          <label for="test_email_address" class="form-label">Adresse email de test</label>
                          <input type="email" id="test_email_address" name="test_email_address" class="form-control" required>
                      </div>
                      <button type="submit" name="test_email" class="btn btn-secondary">Envoyer un email de test</button>
                  </form>
              </div>
          </div>
      </div>
  </div>

  <script src="../scripts/admin_dashboard.js"></script>
  <script>
      document.addEventListener('DOMContentLoaded', function() {
          // Settings tabs functionality
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
      });
  </script>
</body>
</html>

