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

// Vérifier la structure de la table exams
$check_formation_id = $conn->query("SHOW COLUMNS FROM exams LIKE 'formation_id'");
if ($check_formation_id->num_rows === 0) {
    $conn->query("ALTER TABLE exams ADD COLUMN formation_id INT NOT NULL");
}


$check_date = $conn->query("SHOW COLUMNS FROM exams LIKE 'date'");
if ($check_date->num_rows === 0) {
    // Ajouter la colonne si elle n'existe pas
    $conn->query("ALTER TABLE exams ADD COLUMN date DATE DEFAULT CURRENT_DATE");
}

$check_duration = $conn->query("SHOW COLUMNS FROM exams LIKE 'duration'");
if ($check_duration->num_rows === 0) {
    // Ajouter la colonne si elle n'existe pas
    $conn->query("ALTER TABLE exams ADD COLUMN duration INT DEFAULT 0");
}

// Récupérer tous les examens
try {
    // Vérifier si la table courses existe
    $check_courses = $conn->query("SHOW TABLES LIKE 'courses'");
    if ($check_courses->num_rows > 0) {
        $query_exams = "SELECT e.id, e.title, 
                IFNULL(e.date, CURRENT_DATE) as date, 
                IFNULL(e.duration, 0) as duration, 
                f.title as formation_title 
                FROM exams e 
                LEFT JOIN formations f ON e.formation_id = f.id 
                ORDER BY e.id DESC";

    } else {
        $query_exams = "SELECT e.id, e.title, 
                IFNULL(e.date, CURRENT_DATE) as date, 
                IFNULL(e.duration, 0) as duration, 
                IFNULL(f.title, 'Non assigné') as formation_title 
                FROM exams e 
                LEFT JOIN exam_courses ec ON e.id = ec.exam_id
                LEFT JOIN formation_courses fc ON ec.course_id = fc.course_id
                LEFT JOIN formations f ON fc.formation_id = f.id
                ORDER BY e.id DESC";


    }
    
    $result_exams = $conn->query($query_exams);
    
    if (!$result_exams) {
        throw new Exception($conn->error);
    }
} catch (Exception $e) {
    $error_message = "Error preparing exams query: " . $e->getMessage();
}

// Récupérer tous les cours pour le formulaire d'ajout
try {
    $query_formations = "SELECT id, title FROM formations ORDER BY title";
    $result_formations = $conn->query($query_formations);

    
    if (!$result_formations) {
        throw new Exception($conn->error);
    }
} catch (Exception $e) {
    $error_message = "Error preparing formation query: " . $e->getMessage();
}

// Traitement de l'ajout d'un examen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_exam'])) {
    $title = trim($_POST['title']);
    $formation_id = intval($_POST['formation_id']);
    $date = $_POST['date'];
    $duration = intval($_POST['duration']);
    $description = trim($_POST['description']);
    
    // Vérifier si l'examen existe déjà
    $check_query = "SELECT id FROM exams WHERE title = ? AND formation_id = ?";
    $stmt = $conn->prepare($check_query);
    $stmt->bind_param("si", $title, $formation_id);
    $stmt->execute();
    $check_result = $stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $error_message = "Un examen avec ce titre existe déjà pour cette formation.";
    } else {
        // Ajouter l'examen
        $insert_query = "INSERT INTO exams (title, formation_id, date, duration, description) VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($insert_query);
        $stmt->bind_param("sisss", $title, $formation_id, $date, $duration, $description);

        if ($stmt->execute()) {
            $success_message = "Examen ajouté avec succès.";
            // Réinitialiser les champs du formulaire
            $title = $date = $description = "";
            $formation_id = $duration = 0;
        } else {
            $error_message = "Erreur lors de l'ajout de l'examen: " . $stmt->error;
        }
    }
}

// Traitement de la suppression d'un examen
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $exam_id = intval($_GET['delete']);
    
    $delete_query = "DELETE FROM exams WHERE id = ?";
    $stmt = $conn->prepare($delete_query);
    $stmt->bind_param("i", $exam_id);
    
    if ($stmt->execute()) {
        header("Location: manage_exams.php?deleted=1");
        exit();
    } else {
        $error_message = "Erreur lors de la suppression de l'examen.";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Examens - TTA</title>
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
            <h1 class="page-title">Gestion des Examens</h1>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="admin_dashboard.php" class="breadcrumb-link">Accueil</a></li>
                <li class="breadcrumb-item">Gestion des examens</li>
            </ul>
        </div>

        <?php if (isset($_GET['deleted'])): ?>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    showSuccess('Succès', 'Examen supprimé avec succès.');
                });
            </script>
        <?php endif; ?>

        <?php if (!empty($success_message)): ?>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    showSuccess('Succès', '<?php echo addslashes($success_message); ?>');
                });
            </script>
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

        <!-- Add Exam Form -->
        <div class="card animate-on-scroll">
            <div class="card-header">
                <h2 class="card-title">Ajouter un examen</h2>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="title" class="form-label">Titre de l'examen</label>
                        <input type="text" id="title" name="title" class="form-control" value="<?php echo isset($title) ? htmlspecialchars($title) : ''; ?>" required>
                    </div>
                    <div class="form-group">
                    <label for="formation_id" class="form-label">Formation associée</label>
                     <select id="formation_id" name="formation_id" class="form-select" required>

                            <option value="">Sélectionner une formation</option>
                            <?php if ($result_formations && $result_formations->num_rows > 0): ?>
                                <?php while ($formation = $result_formations->fetch_assoc()): ?>
                                    <option value="<?php echo $formation['id']; ?>" <?php echo isset($formation_id) && $formation_id == $formation['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($formation['title']); ?>
                                    </option>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="date" class="form-label">Date de l'examen</label>
                        <input type="date" id="date" name="date" class="form-control" value="<?php echo isset($date) ? $date : date('Y-m-d'); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="duration" class="form-label">Durée (en minutes)</label>
                        <input type="number" id="duration" name="duration" class="form-control" min="1" value="<?php echo isset($duration) ? $duration : '60'; ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="description" class="form-label">Description</label>
                        <textarea id="description" name="description" class="form-control" rows="4"><?php echo isset($description) ? htmlspecialchars($description) : ''; ?></textarea>
                    </div>
                    <button type="submit" name="add_exam" class="btn btn-primary">Ajouter l'examen</button>
                </form>
            </div>
        </div>

        <!-- Exams List -->
        <div class="card animate-on-scroll">
            <div class="card-header">
                <h2 class="card-title">Liste des examens</h2>
            </div>
            <div class="card-body">
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Titre</th>
                                <th>Cours</th>
                                <th>Date</th>
                                <th>Durée</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result_exams && $result_exams->num_rows > 0): ?>
                                <?php while ($exam = $result_exams->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($exam['title']); ?></td>
                                        <td><?php echo htmlspecialchars($exam['formation_title']); ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($exam['date'])); ?></td>
                                        <td><?php echo $exam['duration']; ?> minutes</td>
                                        <td class="table-actions">
                                            <a href="view_exam.php?id=<?php echo $exam['id']; ?>" class="btn-action" data-tooltip="Voir">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="edit_exam.php?id=<?php echo $exam['id']; ?>" class="btn-action btn-edit" data-tooltip="Modifier">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="javascript:void(0);" class="btn-action btn-delete" data-tooltip="Supprimer" onclick="confirmDelete('Confirmer la suppression', 'Êtes-vous sûr de vouloir supprimer cet examen ?', 'manage_exams.php?delete=<?php echo $exam['id']; ?>')">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center">Aucun examen trouvé</td>
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