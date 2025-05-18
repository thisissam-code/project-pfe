<?php
session_start();
require '../includes/bd.php'; // Connexion à la base de données

// Vérifier si l'utilisateur est un admin avant d'accéder à la page
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

// Vérifier si un ID de cours est fourni
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: manage_courses.php");
    exit();
}

$course_id = intval($_GET['id']);

// Récupérer les informations du cours
$query = "SELECT * FROM courses WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $course_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: manage_courses.php");
    exit();
}

$course = $result->fetch_assoc();

// Traitement de la mise à jour du cours
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_course'])) {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $duration = intval($_POST['duration']);
    
    // Vérifier si le titre existe déjà pour un autre cours
    $check_query = "SELECT id FROM courses WHERE title = ? AND id != ?";
    $stmt = $conn->prepare($check_query);
    $stmt->bind_param("si", $title, $course_id);
    $stmt->execute();
    $check_result = $stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $error_message = "Un cours avec ce titre existe déjà.";
    } else {
        // Initialiser avec les valeurs existantes
        $file_path = $course['file_path'];
        $file_format = $course['file_format'];
        
        // Vérification et gestion de l'upload du nouveau fichier (si fourni)
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
                $web_path = "/uploads/courses/" . $new_file_name;
                
                // Supprimer l'ancien fichier si un nouveau est téléchargé
                if (!empty($course['file_path'])) {
                    $old_file = $_SERVER['DOCUMENT_ROOT'] . $course['file_path'];
                    if (file_exists($old_file)) {
                        unlink($old_file);
                    }
                }
                
                if (move_uploaded_file($file_tmp, $server_path)) {
                    // Utiliser le chemin web pour l'enregistrement dans la base de données
                    $file_path = $web_path;
                    $file_format = $file_ext;
                } else {
                    $error_message = "Erreur lors de l'upload du fichier.";
                }
            } else {
                $error_message = "Format de fichier non autorisé.";
            }
        }
        
        if (!isset($error_message)) {
            // Mettre à jour le cours
            $update_query = "UPDATE courses SET title = ?, description = ?, duration = ?, file_path = ?, file_format = ? WHERE id = ?";
            $stmt = $conn->prepare($update_query);
            $stmt->bind_param("ssissi", $title, $description, $duration, $file_path, $file_format, $course_id);
            
            if ($stmt->execute()) {
                header("Location: manage_courses.php?updated=1");
                exit();
            } else {
                $error_message = "Erreur lors de la mise à jour du cours: " . $stmt->error;
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier un Cours - TTA</title>
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

    <?php include '../includes/admin_sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <div class="page-header">
            <h1 class="page-title">Modifier un Cours</h1>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="admin_dashboard.php" class="breadcrumb-link">Accueil</a></li>
                <li class="breadcrumb-item"><a href="manage_courses.php" class="breadcrumb-link">Gestion des cours</a></li>
                <li class="breadcrumb-item">Modifier un cours</li>
            </ul>
        </div>

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

        <!-- Edit Course Form -->
        <div class="card animate-on-scroll">
            <div class="card-header">
                <h2 class="card-title">Modifier le cours</h2>
            </div>
            <div class="card-body">
                <form method="POST" action="" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="title" class="form-label">Titre du cours</label>
                        <input type="text" id="title" name="title" class="form-control" value="<?php echo htmlspecialchars($course['title']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="description" class="form-label">Description</label>
                        <textarea id="description" name="description" class="form-control" rows="4" required><?php echo htmlspecialchars($course['description']); ?></textarea>
                    </div>
                    <div class="form-group">
                        <label for="course_file" class="form-label">Support du cours (PDF, DOCX, PPTX, MP4, MP3)</label>
                        <?php if (!empty($course['file_path'])): ?>
                            <div class="current-file">
                                <p>Fichier actuel: <a href="<?php echo htmlspecialchars($course['file_path']); ?>" target="_blank"><?php echo basename($course['file_path']); ?></a></p>
                            </div>
                        <?php endif; ?>
                        <input type="file" id="course_file" name="course_file" class="form-control" accept=".pdf,.docx,.pptx,.mp4,.mp3">
                        <small class="form-text text-muted">Laissez vide pour conserver le fichier actuel.</small>
                    </div>
                    <div class="form-group">
                        <label for="duration" class="form-label">Durée (en heures)</label>
                        <input type="number" id="duration" name="duration" class="form-control" min="1" value="<?php echo $course['duration']; ?>" required>
                    </div>
                    <div class="form-actions">
                        <button type="submit" name="update_course" class="btn btn-primary">Mettre à jour</button>
                        <a href="manage_courses.php" class="btn btn-secondary">Annuler</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="../scripts/admin_dashboard.js"></script>
</body>
</html>
