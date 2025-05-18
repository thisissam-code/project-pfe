<?php
session_start();
require '../includes/bd.php'; // Connexion à la base de données

// Vérifier si l'utilisateur est un admin avant d'accéder à la page
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin_vol') {
    header("Location: ../index.php");
    exit();
}

$success_message = "";
$error_message = "";

// Vérifier si la table formations existe
try {
    $check_table = $conn->query("SHOW TABLES LIKE 'formations'");
    if ($check_table->num_rows === 0) {
        // Créer la table formations si elle n'existe pas
        $sql_formations = "CREATE TABLE IF NOT EXISTS formations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            duration INT NOT NULL,
            section ENUM('sol', 'vol') DEFAULT NULL,
            fonction ENUM('BE1900D', 'C208B', 'BELL206', 'AT802') DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        
        if (!$conn->query($sql_formations)) {
            throw new Exception($conn->error);
        }
        
        // Créer la table formation_instructors si elle n'existe pas
        $sql_formation_instructors = "CREATE TABLE IF NOT EXISTS formation_instructors (
            id INT AUTO_INCREMENT PRIMARY KEY,
            formation_id INT NOT NULL,
            instructor_id INT NOT NULL,
            FOREIGN KEY (formation_id) REFERENCES formations(id) ON DELETE CASCADE,
            FOREIGN KEY (instructor_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE KEY unique_formation_instructor (formation_id, instructor_id)
        )";
        
        if (!$conn->query($sql_formation_instructors)) {
            throw new Exception($conn->error);
        }
        
        // Créer la table formation_courses si elle n'existe pas
        $sql_formation_courses = "CREATE TABLE IF NOT EXISTS formation_courses (
            id INT AUTO_INCREMENT PRIMARY KEY,
            formation_id INT NOT NULL,
            course_id INT NOT NULL,
            FOREIGN KEY (formation_id) REFERENCES formations(id) ON DELETE CASCADE,
            FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
            UNIQUE KEY unique_formation_course (formation_id, course_id)
        )";
        
        if (!$conn->query($sql_formation_courses)) {
            throw new Exception($conn->error);
        }
    } else {
        // Vérifier si les colonnes section et fonction existent
        $check_section = $conn->query("SHOW COLUMNS FROM formations LIKE 'section'");
        $check_fonction = $conn->query("SHOW COLUMNS FROM formations LIKE 'fonction'");
        
        // Ajouter la colonne section si elle n'existe pas
        if ($check_section->num_rows === 0) {
            $sql_add_section = "ALTER TABLE formations ADD COLUMN section ENUM('sol', 'vol') DEFAULT NULL";
            if (!$conn->query($sql_add_section)) {
                throw new Exception($conn->error);
            }
        }
        
        // Ajouter la colonne fonction si elle n'existe pas
        if ($check_fonction->num_rows === 0) {
            $sql_add_fonction = "ALTER TABLE formations ADD COLUMN fonction ENUM('BE1900D', 'C208B', 'BELL206', 'AT802') DEFAULT NULL";
            if (!$conn->query($sql_add_fonction)) {
                throw new Exception($conn->error);
            }
        }
    }
} catch (Exception $e) {
    $error_message = "Erreur lors de la vérification/création des tables: " . $e->getMessage();
}

// Récupérer toutes les formations
try {
    $query_formations = "SELECT f.id, f.title, f.duration, f.section, f.fonction, f.created_at, 
                    COUNT(DISTINCT fc.course_id) AS course_count,
                    COUNT(DISTINCT fi.instructor_id) AS instructor_count
                    FROM formations f
                    LEFT JOIN formation_courses fc ON f.id = fc.formation_id
                    LEFT JOIN formation_instructors fi ON f.id = fi.formation_id
                    WHERE f.section = 'vol'
                    GROUP BY f.id
                    ORDER BY f.created_at DESC";
    $result_formations = $conn->query($query_formations);
    
    if (!$result_formations) {
        throw new Exception($conn->error);
    }
} catch (Exception $e) {
    $error_message = "Error preparing formations query: " . $e->getMessage();
}

// Récupérer tous les cours pour le formulaire d'ajout
try {
    $query_courses = "SELECT id, title FROM courses ORDER BY title";
    $result_courses = $conn->query($query_courses);
    
    if (!$result_courses) {
        throw new Exception($conn->error);
    }
} catch (Exception $e) {
    $error_message = "Error preparing courses query: " . $e->getMessage();
}

// Récupérer tous les instructeurs pour le formulaire d'ajout
try {
    $query_instructors = "SELECT id, first_name, last_name FROM users WHERE role = 'instructor' ORDER BY last_name, first_name";
    $result_instructors = $conn->query($query_instructors);
    
    if (!$result_instructors) {
        throw new Exception($conn->error);
    }
} catch (Exception $e) {
    $error_message = "Error preparing instructors query: " . $e->getMessage();
}

// Traitement de l'ajout d'une formation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_formation'])) {
    $title = trim($_POST['title']);
    $duration = intval($_POST['duration']);
    $section = 'vol'; // Forcer la section à 'vol'
    $fonction = isset($_POST['fonction']) ? $_POST['fonction'] : null;
    $courses = isset($_POST['courses']) ? $_POST['courses'] : [];
    $instructors = isset($_POST['instructors']) ? $_POST['instructors'] : [];
    
    // Vérifier si la formation existe déjà
    $check_query = "SELECT id FROM formations WHERE title = ?";
    $stmt = $conn->prepare($check_query);
    $stmt->bind_param("s", $title);
    $stmt->execute();
    $check_result = $stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $error_message = "Une formation avec ce titre existe déjà.";
    } else {
        // Ajouter la formation
        $insert_query = "INSERT INTO formations (title, duration, section, fonction) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($insert_query);
        $stmt->bind_param("siss", $title, $duration, $section, $fonction);
        
        if ($stmt->execute()) {
            $formation_id = $conn->insert_id;
            
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
            
            // Créer automatiquement un examen pour cette formation
            $exam_title = "Examen - " . $title;
            $exam_description = "Examen de validation pour la formation " . $title;
            $exam_duration = 60; // Durée par défaut en minutes
            $exam_passing_score = 70; // Score de passage par défaut
            $exam_date = date('Y-m-d'); // Date du jour

            $insert_exam_query = "INSERT INTO exams (formation_id, title, description, duration, passing_score, date) 
                                 VALUES (?, ?, ?, ?, ?, ?)";
            $stmt_exam = $conn->prepare($insert_exam_query);
            $stmt_exam->bind_param("ississ", $formation_id, $exam_title, $exam_description, $exam_duration, $exam_passing_score, $exam_date);

            if ($stmt_exam->execute()) {
                $exam_id = $conn->insert_id;
                
                // Récupérer des questions existantes pour cette formation (si disponibles)
                $query_questions = "SELECT id FROM questions WHERE formation_id = ? ORDER BY RAND() LIMIT 10";
                $stmt_questions = $conn->prepare($query_questions);
                $stmt_questions->bind_param("i", $formation_id);
                $stmt_questions->execute();
                $result_questions = $stmt_questions->get_result();
                
                // Si des questions existent, les associer à l'examen
                if ($result_questions->num_rows > 0) {
                    $insert_exam_question = "INSERT INTO exam_questions (exam_id, question_id, question_order) VALUES (?, ?, ?)";
                    $stmt_exam_question = $conn->prepare($insert_exam_question);
                    
                    $order = 1;
                    while ($question = $result_questions->fetch_assoc()) {
                        $stmt_exam_question->bind_param("iii", $exam_id, $question['id'], $order);
                        $stmt_exam_question->execute();
                        $order++;
                    }
                    
                    $success_message = "Formation et examen associé créés avec succès.";
                } else {
                    $success_message = "Formation créée avec succès. Aucune question disponible pour créer l'examen automatiquement.";
                }
            } else {
                $success_message = "Formation ajoutée avec succès, mais l'examen n'a pas pu être créé automatiquement.";
            }
            
            
            // Recharger les formations
            $result_formations = $conn->query($query_formations);
        } else {
            $error_message = "Erreur lors de l'ajout de la formation.";
        }
    }
}

// Traitement de la suppression d'une formation
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $formation_id = intval($_GET['delete']);
    
    // Vérifier que la formation est de type 'vol'
    $check_query = "SELECT id FROM formations WHERE id = ? AND section = 'vol'";
    $stmt = $conn->prepare($check_query);
    $stmt->bind_param("i", $formation_id);
    $stmt->execute();
    $check_result = $stmt->get_result();

    if ($check_result->num_rows === 0) {
        $error_message = "Cette formation n'est pas de type 'vol' ou n'existe pas.";
    } else {
        $delete_query = "DELETE FROM formations WHERE id = ?";
        $stmt = $conn->prepare($delete_query);
        $stmt->bind_param("i", $formation_id);
        
        if ($stmt->execute()) {
            header("Location: manage_formations_vol.php?deleted=1");
            exit();
        } else {
            $error_message = "Erreur lors de la suppression de la formation.";
        }
    }
}

// Récupérer les détails d'une formation pour l'édition
$formation = null;
$formation_courses = [];
$formation_instructors = [];

if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $formation_id = intval($_GET['edit']);
    
    // Récupérer les informations de la formation
    $query = "SELECT * FROM formations WHERE id = ? AND section = 'vol'";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $formation_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $formation = $result->fetch_assoc();
        
        // Récupérer les cours associés
        $query_formation_courses = "SELECT course_id FROM formation_courses WHERE formation_id = ?";
        $stmt = $conn->prepare($query_formation_courses);
        $stmt->bind_param("i", $formation_id);
        $stmt->execute();
        $result_formation_courses = $stmt->get_result();
        
        while ($row = $result_formation_courses->fetch_assoc()) {
            $formation_courses[] = $row['course_id'];
        }
        
        // Récupérer les instructeurs associés
        $query_formation_instructors = "SELECT instructor_id FROM formation_instructors WHERE formation_id = ?";
        $stmt = $conn->prepare($query_formation_instructors);
        $stmt->bind_param("i", $formation_id);
        $stmt->execute();
        $result_formation_instructors = $stmt->get_result();
        
        while ($row = $result_formation_instructors->fetch_assoc()) {
            $formation_instructors[] = $row['instructor_id'];
        }
    }
}

// Traitement de la mise à jour d'une formation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_formation'])) {
    $formation_id = intval($_POST['formation_id']);
    $title = trim($_POST['title']);
    $duration = intval($_POST['duration']);
    $section = isset($_POST['section']) ? $_POST['section'] : null;
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
            
            // Rediriger vers la liste des formations
            header("Location: manage_formations_vol.php?updated=1");
            exit();
        } else {
            $error_message = "Erreur lors de la mise à jour de la formation.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Formations - TTA</title>
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

        .btn-view {
            color: #3498db;
        }
        .btn-view:hover {
            color: #2980b9;
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
            <h1 class="page-title">Gestion des Formations</h1>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="admin_dashboard_vol.php" class="breadcrumb-link">Accueil</a></li>
                <li class="breadcrumb-item">Gestion des formations</li>
            </ul>
        </div>

        <?php if (isset($_GET['deleted'])): ?>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    showSuccess('Succès', 'Formation supprimée avec succès.');
                });
            </script>
        <?php endif; ?>

        <?php if (isset($_GET['updated'])): ?>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    showSuccess('Succès', 'Formation mise à jour avec succès.');
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

        <!-- Formulaire d'édition de formation -->
        <?php if ($formation): ?>
            <div class="card animate-on-scroll">
                <div class="card-header">
                    <h2 class="card-title">Modifier la formation</h2>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <input type="hidden" name="formation_id" value="<?php echo $formation['id']; ?>">
                        
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
                                <label for="section" class="form-label">Section</label>
                                <select id="section" name="section" class="form-select">
                                    <option value="">Sélectionner une section</option>
                                    <option value="sol" <?php echo ($formation['section'] === 'sol') ? 'selected' : ''; ?>>Sol</option>
                                    <option value="vol" <?php echo ($formation['section'] === 'vol') ? 'selected' : ''; ?>>Vol</option>
                                </select>
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
        <?php else: ?>
            <!-- Formulaire d'ajout de formation -->
            <div class="card animate-on-scroll">
                <div class="card-header">
                    <h2 class="card-title">Ajouter une formation</h2>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="title" class="form-label">Titre de la formation</label>
                            <input type="text" id="title" name="title" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="duration" class="form-label">Durée (en heures)</label>
                            <input type="number" id="duration" name="duration" class="form-control" min="1" required>
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
                                    <option value="BE1900D">BE1900D</option>
                                    <option value="C208B">C208B</option>
                                    <option value="BELL206">BELL206</option>
                                    <option value="AT802">AT802</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="courses" class="form-label">Cours associés</label>
                            <select id="courses" name="courses[]" class="form-select select-multiple" multiple>
                                <?php if ($result_courses && $result_courses->num_rows > 0): ?>
                                    <?php while ($course = $result_courses->fetch_assoc()): ?>
                                        <option value="<?php echo $course['id']; ?>">
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
                                    <?php while ($instructor = $result_instructors->fetch_assoc()): ?>
                                        <option value="<?php echo $instructor['id']; ?>">
                                            <?php echo htmlspecialchars($instructor['first_name'] . ' ' . $instructor['last_name']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                <?php endif; ?>
                            </select>
                            <small class="text-muted">Maintenez la touche Ctrl (ou Cmd sur Mac) pour sélectionner plusieurs instructeurs.</small>
                        </div>
                        
                        <button type="submit" name="add_formation" class="btn btn-primary">Ajouter la formation</button>
                    </form>
                </div>
            </div>

            <!-- Liste des formations -->
            <div class="card animate-on-scroll">
                <div class="card-header">
                    <h2 class="card-title">Liste des formations</h2>
                </div>
                <div class="card-body">
                    <div class="table-container">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Titre</th>
                                    <th>Durée</th>
                                    <th>Section</th>
                                    <th>Fonction</th>
                                    <th>Cours</th>
                                    <th>Instructeurs</th>
                                    <th>Date de création</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($result_formations && $result_formations->num_rows > 0): ?>
                                    <?php while ($formation = $result_formations->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($formation['title']); ?></td>
                                            <td><?php echo $formation['duration']; ?> heures</td>
                                            <td><?php echo $formation['section'] ? htmlspecialchars($formation['section']) : '-'; ?></td>
                                            <td><?php echo $formation['fonction'] ? htmlspecialchars($formation['fonction']) : '-'; ?></td>
                                            <td><?php echo $formation['course_count']; ?> cours</td>
                                            <td><?php echo $formation['instructor_count']; ?> instructeur(s)</td>
                                            <td><?php echo date('d/m/Y', strtotime($formation['created_at'])); ?></td>
                                            <td class="table-actions">
                                                <a href="view_formations_vol.php?id=<?php echo $formation['id']; ?>" class="btn-action btn-view" data-tooltip="Voir">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="edit_formation_vol.php?id=<?php echo $formation['id']; ?>" class="btn-action btn-edit" data-tooltip="Modifier">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <!-- Remplacer le bouton de suppression par un lien direct -->
                                                <a href="manage_formations_vol.php?delete=<?php echo $formation['id']; ?>" class="btn-action btn-delete" data-tooltip="Supprimer" onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette formation?');">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8" class="text-center">Aucune formation trouvée</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="../scripts/admin_dashboard.js"></script>
</body>
</html>
