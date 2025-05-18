<?php
session_start();
require '../includes/bd.php'; // Connexion à la base de données

// Vérifier si l'utilisateur est un admin avant d'accéder à la page
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin_vol') {
    header("Location: ../index.php");
    exit();
}

// Vérifier si un ID de formation est fourni
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: manage_formations_vol.php");
    exit();
}

$formation_id = intval($_GET['id']);
$success_message = "";
$error_message = "";

// Récupérer les informations de la formation
$query = "SELECT * FROM formations WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $formation_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: manage_formations_vol.php");
    exit();
}

$formation = $result->fetch_assoc();

// Récupérer les cours associés à la formation
$formation_courses = [];
$query_formation_courses = "SELECT course_id FROM formation_courses WHERE formation_id = ?";
$stmt = $conn->prepare($query_formation_courses);
$stmt->bind_param("i", $formation_id);
$stmt->execute();
$result_formation_courses = $stmt->get_result();

while ($row = $result_formation_courses->fetch_assoc()) {
    $formation_courses[] = $row['course_id'];
}

// Récupérer les instructeurs associés à la formation
$formation_instructors = [];
$query_formation_instructors = "SELECT instructor_id FROM formation_instructors WHERE formation_id = ?";
$stmt = $conn->prepare($query_formation_instructors);
$stmt->bind_param("i", $formation_id);
$stmt->execute();
$result_formation_instructors = $stmt->get_result();

while ($row = $result_formation_instructors->fetch_assoc()) {
    $formation_instructors[] = $row['instructor_id'];
}

// Récupérer tous les cours pour le formulaire d'édition
try {
    $query_courses = "SELECT id, title FROM courses ORDER BY title";
    $result_courses = $conn->query($query_courses);
    
    if (!$result_courses) {
        throw new Exception($conn->error);
    }
} catch (Exception $e) {
    $error_message = "Error preparing courses query: " . $e->getMessage();
}

// Récupérer tous les instructeurs pour le formulaire d'édition
try {
    $query_instructors = "SELECT id, first_name, last_name FROM users WHERE role = 'instructor' ORDER BY last_name, first_name";
    $result_instructors = $conn->query($query_instructors);
    
    if (!$result_instructors) {
        throw new Exception($conn->error);
    }
} catch (Exception $e) {
    $error_message = "Error preparing instructors query: " . $e->getMessage();
}

// Traitement de la mise à jour d'une formation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_formation'])) {
    $title = trim($_POST['title']);
    $duration = intval($_POST['duration']);
    $section = 'vol'; // Forcer la section à 'vol'
    $fonction = isset($_POST['fonction']) ? $_POST['fonction'] : null;
    $courses = isset($_POST['courses']) ? $_POST['courses'] : [];
    $instructors = isset($_POST['instructors']) ? $_POST['instructors'] : [];
    
    // Vérifier si le titre existe déjà pour une autre formation
    $check_query = "SELECT id FROM formations WHERE title = ? AND id != ?";
    $stmt = $conn->prepare($check_query);
    $stmt->bind_param("si", $title, $formation_id);
    $stmt->execute();
    $check_result = $stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $error_message = "Une formation avec ce titre existe déjà.";
    } else {
        // Mettre à jour la formation
        $update_query = "UPDATE formations SET title = ?, duration = ?, section = ?, fonction = ? WHERE id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("sissi", $title, $duration, $section, $fonction, $formation_id);
        
        if ($stmt->execute()) {
            // Supprimer les associations existantes
            $conn->query("DELETE FROM formation_courses WHERE formation_id = $formation_id");
            $conn->query("DELETE FROM formation_instructors WHERE formation_id = $formation_id");
            
            // Associer les cours à la formation
            if (!empty($courses)) {
                $insert_courses = "INSERT INTO formation_courses (formation_id, course_id) VALUES (?, ?)";
                $stmt_courses = $conn->prepare($insert_courses);
                
                foreach ($courses as $course_id) {
                    $stmt_courses->bind_param("ii", $formation_id, $course_id);
                    $stmt_courses->execute();
                }
            }
            
            // Associer les instructeurs à la formation
            if (!empty($instructors)) {
                $insert_instructors = "INSERT INTO formation_instructors (formation_id, instructor_id) VALUES (?, ?)";
                $stmt_instructors = $conn->prepare($insert_instructors);
                
                foreach ($instructors as $instructor_id) {
                    $stmt_instructors->bind_param("ii", $formation_id, $instructor_id);
                    $stmt_instructors->execute();
                }
            }
            
            $success_message = "Formation mise à jour avec succès.";
            
            // Mettre à jour les informations de la formation après la mise à jour
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $formation_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $formation = $result->fetch_assoc();
            
            // Mettre à jour les cours associés
            $formation_courses = [];
            $stmt = $conn->prepare($query_formation_courses);
            $stmt->bind_param("i", $formation_id);
            $stmt->execute();
            $result_formation_courses = $stmt->get_result();
            
            while ($row = $result_formation_courses->fetch_assoc()) {
                $formation_courses[] = $row['course_id'];
            }
            
            // Mettre à jour les instructeurs associés
            $formation_instructors = [];
            $stmt = $conn->prepare($query_formation_instructors);
            $stmt->bind_param("i", $formation_id);
            $stmt->execute();
            $result_formation_instructors = $stmt->get_result();
            
            while ($row = $result_formation_instructors->fetch_assoc()) {
                $formation_instructors[] = $row['instructor_id'];
            }
        } else {
            $error_message = "Erreur lors de la mise à jour de la formation: " . $stmt->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier une Formation - TTA</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/admin_dashboard.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.19/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.19/dist/sweetalert2.all.min.js"></script>
    <script src="../assets/alerts.js"></script>
    <style>
        /* Styles spécifiques pour la gestion des formations */
        .select-multiple {
            height: 150px;
        }
        
        .formation-details {
            margin-bottom: 1rem;
        }
        
        .formation-details dt {
            font-weight: bold;
            margin-bottom: 0.25rem;
        }
        
        .formation-details dd {
            margin-bottom: 0.75rem;
        }
    </style>
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
            <h1 class="page-title">Modifier une Formation</h1>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="admin_dashboard_vol.php" class="breadcrumb-link">Accueil</a></li>
                <li class="breadcrumb-item"><a href="manage_formations_vol.php" class="breadcrumb-link">Gestion des formations</a></li>
                <li class="breadcrumb-item">Modifier une formation</li>
            </ul>
        </div>

        <?php if (!empty($success_message)): ?>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    showSuccess('Succès', '<?php echo addslashes($success_message); ?>');
                });
            </script>
            <div class="alert alert-success">
                <?php echo $success_message; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($error_message)): ?>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    showError('Erreur', '<?php echo addslashes($error_message); ?>');
                });
            </script>
            <div class="alert alert-danger">
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <!-- Formulaire d'édition de formation -->
        <div class="card animate-on-scroll">
            <div class="card-header">
                <h2 class="card-title">Modifier la formation</h2>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="title" class="form-label">Titre de la formation</label>
                        <input type="text" id="title" name="title" class="form-control" value="<?php echo htmlspecialchars($formation['title']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="duration" class="form-label">Durée (en heures)</label>
                        <input type="number" id="duration" name="duration" class="form-control" value="<?php echo $formation['duration']; ?>" min="1" required>
                    </div>
                    
                    <div class="row">
                        <div class="form-group col-md-6">
                            <label for="section_display" class="form-label">Section</label>
                            <input type="text" id="section_display" class="form-control" value="Vol" readonly>
                            <input type="hidden" name="section" value="vol">
                        </div>
                        
                        <div class="form-group col-md-6">
                            <label for="fonction" class="form-label">Fonction</label>
                            <select id="fonction" name="fonction" class="form-select">
                                <option value="">Sélectionner une fonction</option>
                                <option value="BE1900D" <?php echo ($formation['fonction'] === 'BE1900D') ? 'selected' : ''; ?>>BE1900D</option>
                                <option value="C208B" <?php echo ($formation['fonction'] === 'C208B') ? 'selected' : ''; ?>>C208B</option>
                                <option value="BELL206" <?php echo ($formation['fonction'] === 'BELL206') ? 'selected' : ''; ?>>BELL206</option>
                                <option value="AT802" <?php echo ($formation['fonction'] === 'AT802') ? 'selected' : ''; ?>>AT802</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="courses" class="form-label">Cours associés</label>
                        <select id="courses" name="courses[]" class="form-select select-multiple" multiple>
                            <?php if ($result_courses && $result_courses->num_rows > 0): ?>
                                <?php 
                                // Reset result pointer
                                $result_courses->data_seek(0);
                                while ($course = $result_courses->fetch_assoc()): 
                                ?>
                                    <option value="<?php echo $course['id']; ?>" <?php echo in_array($course['id'], $formation_courses) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($course['title']); ?>
                                    </option>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </select>
                        <small class="text-muted">Maintenez la touche Ctrl (ou Cmd sur Mac) pour sélectionner plusieurs cours.</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="instructors" class="form-label">Instructeurs</label>
                        <select id="instructors" name="instructors[]" class="form-select select-multiple" multiple>
                            <?php if ($result_instructors && $result_instructors->num_rows > 0): ?>
                                <?php 
                                // Reset result pointer
                                $result_instructors->data_seek(0);
                                while ($instructor = $result_instructors->fetch_assoc()): 
                                ?>
                                    <option value="<?php echo $instructor['id']; ?>" <?php echo in_array($instructor['id'], $formation_instructors) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($instructor['first_name'] . ' ' . $instructor['last_name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </select>
                        <small class="text-muted">Maintenez la touche Ctrl (ou Cmd sur Mac) pour sélectionner plusieurs instructeurs.</small>
                    </div>
                    
                    <div class="d-flex justify-content-between">
                        <a href="manage_formations_vol.php" class="btn btn-secondary">Annuler</a>
                        <button type="submit" name="update_formation" class="btn btn-primary">Enregistrer les modifications</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="../scripts/admin_dashboard.js"></script>
</body>
</html>
