<?php
session_start();
require 'includes/bd.php'; // Connexion à la base de données

// Vérifier si le formulaire a été soumis
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = htmlspecialchars(trim($_POST['email']));
  $password = htmlspecialchars(trim($_POST['password']));

  if (!empty($email) && !empty($password)) {
      // Vérifier si l'utilisateur existe
      $query = $conn->prepare("SELECT * FROM users WHERE email = ?");
      if (!$query) {
          $_SESSION['message'] = "Erreur de préparation de la requête: " . $conn->error;
      } else {
          $query->bind_param("s", $email);
          $query->execute();
          $result = $query->get_result();

          if ($result->num_rows === 1) {
              $user = $result->fetch_assoc();

              if (password_verify($password, $user['password'])) {
                  // Sécurisation de la session
                  session_regenerate_id(true);

                  // Stocker les informations utilisateur en session
                  $_SESSION['user_id'] = $user['id'];
                  $_SESSION['first_name'] = $user['first_name'];
                  $_SESSION['last_name'] = $user['last_name'];
                  $_SESSION['email'] = $user['email'];
                  $_SESSION['role'] = $user['role'];
                  
                  // Stocker l'avatar dans la session s'il existe
                  if (isset($user['avatar']) && !empty($user['avatar'])) {
                      $_SESSION['avatar'] = $user['avatar'];
                  }

                  // Mettre à jour la dernière connexion
                  $updateQuery = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                  if ($updateQuery) {
                      $updateQuery->bind_param("i", $user['id']);
                      $updateQuery->execute();
                  }

                  // Enregistrer les informations de connexion
                  $ip_address = $_SERVER['REMOTE_ADDR'];
                  $browser = $_SERVER['HTTP_USER_AGENT'];
                  $log_query = $conn->prepare("INSERT INTO login_logs (user_id, ip_address, browser) VALUES (?, ?, ?)");
                  if ($log_query) {
                      $log_query->bind_param("iss", $user['id'], $ip_address, $browser);
                      $log_query->execute();
                  }

                  // Rediriger selon le rôle
                  switch ($user['role']) {
                      case 'admin':
                          header("Location: pages/admin_dashboard.php");
                          exit();
                          case 'admin_sol':
                            header("Location: pages/admin_dashboard_sol.php");
                            exit();
                            case 'admin_vol':
                              header("Location: pages/admin_dashboard_vol.php");
                              exit();
                      case 'pilot':
                          header("Location: pages/pilote_dashboard.php");
                          exit();
                      case 'instructor':
                          header("Location: pages/instructor_dashboard.php");
                          exit();
                      default:
                          $_SESSION['message'] = "Rôle utilisateur inconnu.";
                  }
              } else {
                  $_SESSION['message'] = "Mot de passe incorrect.";
              }
          } else {
              $_SESSION['message'] = "Utilisateur non trouvé.";
          }
      }
  } else {
      $_SESSION['message'] = "Veuillez remplir tous les champs.";
  }
}

// Récupérer le message d'erreur
$message = isset($_SESSION['message']) ? $_SESSION['message'] : "";
unset($_SESSION['message']); // Supprimer le message après affichage
?>

<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Connexion - TTA</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.19/dist/sweetalert2.min.css">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.19/dist/sweetalert2.all.min.js"></script>
  <script src="assets/alerts.js"></script>
  <link rel="stylesheet" href="assets/login.css">
  <link rel="icon" href="images/TTAicon.ico">
</head>
<body>
  <!-- Splash screen with logo -->
  <div class="splash-screen" id="splashScreen">
    <img
      src="https://hebbkx1anhila5yf.public.blob.vercel-storage.com/Logo-white-Zz4qtDwceaNCIkzak7rp8OcHdexOZS.png"
      alt="Tassili Travail Aérien Logo"
      class="logo"
      id="logo"
    />
  </div>

  <!-- Navbar -->
  <nav class="navbar">
    <a href="https://tassilitravailaerien.dz/en/">
      <img src="https://hebbkx1anhila5yf.public.blob.vercel-storage.com/Logo-white-Zz4qtDwceaNCIkzak7rp8OcHdexOZS.png" 
           alt="Tassili Travail Aérien" class="navbar-logo">
    </a>
    <ul class="navbar-menu" id="navbarMenu">
      <li><a href="#" class="active">HOME</a></li>
      <li><a href="#">ABOUT US</a></li>
      <li><a href="#">FLEET</a></li>
      <li><a href="#">SERVICES</a></li>
      <li><a href="#">NEWS</a></li>
      <li><a href="#">CONTACT</a></li>
    </ul>
    <div class="navbar-icons">
      <a href="#"><i class="fas fa-search"></i></a>
      <a href="#"><i class="fas fa-globe"></i></a>
    </div>
    <button class="mobile-menu-btn" id="mobileMenuBtn">
      <i class="fas fa-bars"></i>
    </button>
  </nav>

  <!-- Main Content -->
  <div class="content-wrapper">
    <!-- Main container with form and image -->
    <div class="main-container" id="loginForm">
      <!-- Left side - Form -->
      <div class="form-container">
        <img
          src="https://hebbkx1anhila5yf.public.blob.vercel-storage.com/download%20%287%29-St03fvkXTcUVsvMwBJ8C5xYsQtvcyf.jpeg"
          alt="Tassili Travail Aérien Logo"
          class="form-logo"
        />
        <h1 class="welcome-heading">Connexion</h1>
        
        <!-- ✅ Affichage du message d'erreur -->
        <?php if (!empty($message)): ?>
          <script>
            document.addEventListener('DOMContentLoaded', function() {
              showError('Erreur de connexion', '<?= addslashes($message) ?>');
            });
          </script>
        <?php endif; ?>
        
        <form method="POST">
          <div class="input-group">
            <label for="email">Email</label>
            <i class="fas fa-envelope input-icon"></i>
            <input
              type="email"
              id="email"
              name="email"
              placeholder="Entrez votre email"
              required
            />
          </div>
          <div class="input-group">
            <label for="password">Mot de passe</label>
            <i class="fas fa-lock input-icon"></i>
            <input
              type="password"
              id="password"
              name="password"
              placeholder="Entrez votre mot de passe"
              required
            />
            <i class="fas fa-eye toggle-password" id="togglePassword"></i>
          </div>
          <button type="submit">Se connecter</button>
        </form>
      </div>

      <!-- Right side - Image placeholder -->
      <div class="image-container">
        <div class="decorative-circle circle-1"></div>
        <div class="decorative-circle circle-2"></div>
        <img class="planeimage" src="https://hebbkx1anhila5yf.public.blob.vercel-storage.com/10-XE7wzk0lYmq5Gx82yML9HGNyTXlhdr.webp" alt="Tassili Aircraft" />
        <div class="text-overlay">
          <h1><span class="blue-text">E-LEARNING</span> <span class="orange-text">PILOTS</span><br></h1>
          <h1 class="blue-text">PLATFORM</h1>
          <p class="welcome-subtext">Enhance your aviation knowledge with expert-led courses and real-world simulations.</p>
        </div>
      </div>
    </div>
  </div>

  <!-- Footer -->
  <footer class="footer" id="footer">
    <div class="footer-container">
      <div class="footer-column">
        <img src="https://hebbkx1anhila5yf.public.blob.vercel-storage.com/Logo-white-Zz4qtDwceaNCIkzak7rp8OcHdexOZS.png" alt="Tassili Travail Aérien" class="footer-logo">
        <div class="social-icons">
          <a href="#"><i class="fab fa-facebook-f"></i></a>
          <a href="#"><i class="fab fa-linkedin-in"></i></a>
          <a href="#"><i class="fab fa-instagram"></i></a>
          <a href="#"><i class="fab fa-youtube"></i></a>
          <a href="#"><i class="fab fa-twitter"></i></a>
        </div>
      </div>
      <div class="footer-column">
        <h3 class="footer-title">Nos services</h3>
        <ul class="footer-links">
          <li><a href="#">Transport de passagers</a></li>
          <li><a href="#">Évacuation médicale</a></li>
          <li><a href="#">Photographie aérienne</a></li>
          <li><a href="#">Surveillance et contrôle</a></li>
          <li><a href="#">Lutte contre les incendies</a></li>
          <li><a href="#">Traitement et pulvérisation aérienne</a></li>
          <li><a href="#">Lavage d'isolateurs haute tension</a></li>
          <li><a href="#">Relevés topographiques</a></li>
        </ul>
      </div>
      <div class="footer-column">
        <h3 class="footer-title">Accès rapide</h3>
        <ul class="footer-links">
          <li><a href="#">À propos de nous</a></li>
          <li><a href="#">Notre flotte</a></li>
          <li><a href="#">Contact</a></li>
          <li><a href="#">Services</a></li>
          <li><a href="#">Actualités</a></li>
          <li><a href="#">Carrières</a></li>
        </ul>
      </div>
      <div class="footer-column">
        <h3 class="footer-title">Contactez-nous</h3>
        <div class="footer-contact">
          <i class="fas fa-map-marker-alt"></i>
          <p>Quad Smar road BP 78H<br>Dar El Beida, Alger</p>
        </div>
        <div class="footer-contact">
          <i class="fas fa-envelope"></i>
          <p>marketing@tta.dz</p>
        </div>
        <div class="footer-contact">
          <i class="fas fa-phone-alt"></i>
          <p>+213 (0)660 633 666</p>
        </div>
      </div>
    </div>
    <div class="footer-bottom">
      <p>2025 © TOUS DROITS RÉSERVÉS - TASSILI TRAVAIL AÉRIEN</p>
    </div>
  </footer>
  
  <script>
  document.addEventListener("DOMContentLoaded", function () {
    const splashScreen = document.getElementById("splashScreen");
    const logo = document.getElementById("logo");
    const loginForm = document.getElementById("loginForm");
    const togglePassword = document.getElementById("togglePassword");
    const password = document.getElementById("password");
    const mobileMenuBtn = document.getElementById("mobileMenuBtn");
    const navbarMenu = document.getElementById("navbarMenu");
    const footer = document.getElementById("footer");
    const footerColumns = document.querySelectorAll(".footer-column");
    const footerBottom = document.querySelector(".footer-bottom");

    // Add a slight animation to the logo
    setTimeout(() => {
      logo.style.transform = "scale(1.05)";

      setTimeout(() => {
        logo.style.transform = "scale(1)";
      }, 300);
    }, 500);

    // Start the transition after a delay
    setTimeout(() => {
      // Fade out the splash screen
      splashScreen.style.opacity = "0";

      // Show the login form
      loginForm.classList.add("visible");

      // Make footer elements visible
      footerColumns.forEach(column => {
        column.classList.add("visible");
      });
      footerBottom.classList.add("visible");

      // Remove the splash screen from the flow after animation completes
      setTimeout(() => {
        splashScreen.style.display = "none";
      }, 1500);
    }, 2500); // Wait 2.5 seconds before starting the transition

    // Toggle password visibility
    togglePassword.addEventListener("click", function() {
      const type = password.getAttribute("type") === "password" ? "text" : "password";
      password.setAttribute("type", type);
      
      // Toggle eye icon
      this.classList.toggle("fa-eye");
      this.classList.toggle("fa-eye-slash");
    });

    // Mobile menu toggle
    mobileMenuBtn.addEventListener("click", function() {
      navbarMenu.classList.toggle("active");
    });
  });
</script>
<script src="scripts/login.js"></script>
</body>
</html>

