<?php
session_start();
require '../includes/bd.php'; // Connexion à la base de données

// Vérifier si l'utilisateur est un admin avant d'accéder à la page
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin_vol') {
    header("Location: ../index.php");
    exit();
}

$check_duration = $conn->query("SHOW COLUMNS FROM courses LIKE 'duration'");
if ($check_duration->num_rows === 0) {
    // Ajouter la colonne si elle n'existe pas
    $conn->query("ALTER TABLE courses ADD COLUMN duration INT DEFAULT 0");
}

// Récupérer tous les cours
try {
    $query_courses = "SELECT id, title, description, 
              IFNULL(duration, 0) AS duration, 
              created_at, 
              file_path, 
              file_format
              FROM courses
              ORDER BY created_at DESC";
    
    $result_courses = $conn->query($query_courses);
    
    if (!$result_courses) {
        throw new Exception($conn->error);
    }
} catch (Exception $e) {
    $error_message = "Error preparing courses query: " . $e->getMessage();
}

// Traitement de l'ajout d'un cours
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_course'])) {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $duration = intval($_POST['duration']);
    $instructor_id = isset($_POST['instructor_id']) ? intval($_POST['instructor_id']) : 0;

    // Vérifier si le cours existe déjà
    $check_query = "SELECT id FROM courses WHERE title = ?";
    $stmt = $conn->prepare($check_query);
    $stmt->bind_param("s", $title);
    $stmt->execute();
    $check_result = $stmt->get_result();

    if ($check_result->num_rows > 0) {
        $error_message = "Un cours avec ce titre existe déjà.";
    } else {
        $file_path = null;
        $file_ext = null;

        // Vérification et gestion de l'upload du fichier
        if (isset($_FILES['course_file']) && $_FILES['course_file']['error'] === 0) {
            $allowed_extensions = ['pdf', 'docx', 'pptx', 'mp4', 'mp3'];
            $file_name = $_FILES['course_file']['name'];
            $file_tmp = $_FILES['course_file']['tmp_name'];
            $file_size = $_FILES['course_file']['size'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

            if (in_array($file_ext, $allowed_extensions)) {
                // Chemin physique pour enregistrer le fichier
                $upload_dir = "../uploads/courses/";
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }

                $new_file_name = uniqid() . "." . $file_ext;
                $server_path = $upload_dir . $new_file_name;
                
                // Chemin pour l'accès web (ce qui sera stocké dans la base de données)
                $web_path = "../uploads/courses/" . $new_file_name;

                if (move_uploaded_file($file_tmp, $server_path)) {
                    // Utiliser le chemin web pour l'enregistrement dans la base de données
                    $file_path = $web_path;
                } else {
                    $error_message = "Erreur lors de l'upload du fichier.";
                }
            } else {
                $error_message = "Format de fichier non autorisé.";
            }
        }

        if (!isset($error_message)) {
            // Ajouter le cours avec le fichier (si présent)
            $insert_query = "INSERT INTO courses (title, description, duration, file_path, file_format) VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($insert_query);
            $stmt->bind_param("ssisd", $title, $description, $duration, $file_path, $file_ext);

            if ($stmt->execute()) {
                // Récupérer l'ID du dernier cours ajouté
                $last_course_id = $conn->insert_id;
                
                // Rediriger vers la page de gestion des cours après l'ajout
                header("Location: manage_courses_vol.php?added=1");
                exit();
            } else {
                $error_message = "Erreur lors de l'ajout du cours: " . $stmt->error;
            }
        }
    }
}

// Traitement de la suppression d'un cours
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $course_id = intval($_GET['delete']);
    
    // Récupérer le chemin du fichier avant la suppression
    $get_file_query = "SELECT file_path FROM courses WHERE id = ?";
    $stmt = $conn->prepare($get_file_query);
    $stmt->bind_param("i", $course_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $course = $result->fetch_assoc();
        $file_to_delete = $_SERVER['DOCUMENT_ROOT'] . $course['file_path'];
        
        // Supprimer le fichier physique si le chemin existe
        if ($course['file_path'] && file_exists($file_to_delete)) {
            unlink($file_to_delete);
        }
    }
    
    // Supprimer l'entrée dans la base de données
    $delete_query = "DELETE FROM courses WHERE id = ?";
    $stmt = $conn->prepare($delete_query);
    $stmt->bind_param("i", $course_id);
    
    if ($stmt->execute()) {
        header("Location: manage_courses_vol.php?deleted=1");
        exit();
    } else {
        $error_message = "Erreur lors de la suppression du cours.";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Cours - TTA</title>
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
            <h1 class="page-title">Gestion des Cours</h1>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="admin_dashboard_vol.php" class="breadcrumb-link">Accueil</a></li>
                <li class="breadcrumb-item">Gestion des cours</li>
            </ul>
        </div>

        <?php if (isset($_GET['added'])): ?>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    showSuccess('Succès', 'Cours ajouté avec succès.');
                });
            </script>
        <?php endif; ?>

        <?php if (isset($_GET['deleted'])): ?>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    showSuccess('Succès', 'Cours supprimé avec succès.');
                });
            </script>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    showError('Erreur', '<?php echo addslashes($error_message); ?>');
                });
            </script>
            <div class="alert alert-danger">
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <!-- Add Course Form -->
        <div class="card animate-on-scroll">
            <div class="card-header">
                <h2 class="card-title">Ajouter un cours</h2>
            </div>
            <div class="card-body">
                <form method="POST" action="" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="title" class="form-label">Titre du cours</label>
                        <input type="text" id="title" name="title" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="description" class="form-label">Description</label>
                        <textarea id="description" name="description" class="form-control" rows="4" required></textarea>
                    </div>
                    <div class="form-group">
                        <label for="course_file" class="form-label">Support du cours (PDF, DOCX, PPTX, MP4, MP3)</label>
                        <input type="file" id="course_file" name="course_file" class="form-control" accept=".pdf,.docx,.pptx,.mp4,.mp3" required>
                    </div>

                    <div class="form-group">
                        <label for="duration" class="form-label">Durée (en heures)</label>
                        <input type="number" id="duration" name="duration" class="form-control" min="1" required>
                    </div>
                    <button type="submit" name="add_course" class="btn btn-primary">Ajouter le cours</button>
                </form>
            </div>
        </div>

        <!-- Courses List -->
        <div class="card animate-on-scroll">
            <div class="card-header">
                <h2 class="card-title">Liste des cours</h2>
            </div>
            <div class="card-body">
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Titre</th>
                                <th>Description</th>
                                <th>Durée</th>
                                <th>Support</th>
                                <th>Date de création</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result_courses && $result_courses->num_rows > 0): ?>
                                <?php while ($course = $result_courses->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($course['title']); ?></td>
                                        <td><?php echo htmlspecialchars(substr($course['description'], 0, 100)) . (strlen($course['description']) > 100 ? '...' : ''); ?></td>
                                        <td><?php echo $course['duration']; ?> heures</td>
                                        <td>
                                            <?php if (!empty($course['file_path'])): ?>
                                                <a href="<?php echo htmlspecialchars($course['file_path']); ?>" target="_blank">Voir le support</a>
                                            <?php else: ?>
                                                Aucun fichier
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo date('d/m/Y', strtotime($course['created_at'])); ?></td>
                                        <td class="table-actions">
                                            <a href="edit_courses_vol.php?id=<?php echo $course['id']; ?>" class="btn-action btn-edit" data-tooltip="Modifier">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <!-- Remplacer le bouton de suppression par un lien direct -->
                                            <a href="manage_courses_vol.php?delete=<?php echo $course['id']; ?>" class="btn-action btn-delete" data-tooltip="Supprimer" onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce cours?');">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center">Aucun cours trouvé</td>
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
