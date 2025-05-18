<?php
session_start();
require '../includes/bd.php'; // Connexion à la base de données

// Vérifier si l'utilisateur est connecté et est un admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("Accès refusé.");
}

// Récupérer les instructeurs
$instructors = $conn->query("SELECT id, first_name, last_name FROM users WHERE role = 'instructor'")->fetch_all(MYSQLI_ASSOC);

// Récupérer les cours
$courses = $conn->query("SELECT id, title FROM courses")->fetch_all(MYSQLI_ASSOC);

// Récupérer les formations avec section et fonction
$formations = $conn->query("
    SELECT f.id, f.title, f.duration, f.section, f.fonction,
           CONCAT(u.first_name, ' ', u.last_name) AS instructor_name
    FROM formations f
    LEFT JOIN formation_instructors fi ON f.id = fi.formation_id
    LEFT JOIN users u ON fi.instructor_id = u.id
")->fetch_all(MYSQLI_ASSOC);

$message = "";

// Gestion du formulaire d'ajout
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_formation'])) {
    $title = trim($_POST['title']);
    $duration = intval($_POST['duration']);
    $instructor_id = intval($_POST['instructor']);
    $selected_courses = $_POST['courses'] ?? [];
    $section = $_POST['section'] !== '' ? $_POST['section'] : null;
    $fonction = $_POST['fonction'] !== '' ? $_POST['fonction'] : null;

    // Vérifier si la formation existe déjà
    $stmt = $conn->prepare("SELECT COUNT(*) FROM formations WHERE title = ?");
    $stmt->bind_param("s", $title);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();

    if ($count > 0) {
        $message = "❌ Cette formation existe déjà.";
    } else {
        // Insérer la formation avec section et fonction
        $stmt = $conn->prepare("INSERT INTO formations (title, duration, section, fonction) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("siss", $title, $duration, $section, $fonction);
        if ($stmt->execute()) {
            $formation_id = $conn->insert_id;

            // Associer l'instructeur
            $stmtInstructor = $conn->prepare("INSERT INTO formation_instructors (formation_id, instructor_id) VALUES (?, ?)");
            $stmtInstructor->bind_param("ii", $formation_id, $instructor_id);
            $stmtInstructor->execute();
            $stmtInstructor->close();

            // Associer les cours
            $stmtCourse = $conn->prepare("INSERT INTO formation_courses (formation_id, course_id) VALUES (?, ?)");
            foreach ($selected_courses as $course_id) {
                $stmtCourse->bind_param("ii", $formation_id, $course_id);
                $stmtCourse->execute();
            }
            $stmtCourse->close();

            header("Location: create_formation.php?success=1");
            exit();
        } else {
            $message = "❌ Erreur lors de l'ajout.";
        }
    }
}

// Gestion de la suppression
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_formation'])) {
    $formation_id = intval($_POST['formation_id']);
    $stmt = $conn->prepare("DELETE FROM formations WHERE id = ?");
    $stmt->bind_param("i", $formation_id);
    if ($stmt->execute()) {
        header("Location: create_formation.php?deleted=1");
        exit();
    } else {
        $message = "❌ Erreur lors de la suppression.";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gérer les Formations</title>
    <link rel="stylesheet" href="../assets/create_formation.css">
    <style>
        /* Styles supplémentaires pour les nouveaux champs */
        .form-row {
            display: flex;
            gap: 20px;
            margin-bottom: 15px;
        }
        
        .form-group {
            flex: 1;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
        }
        
        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
            color: white;
        }
        
        .badge-sol {
            background-color: #28a745;
        }
        
        .badge-vol {
            background-color: #007bff;
        }
        
        .badge-fonction {
            background-color: #6c757d;
        }
        
        .badge-none {
            background-color: #6c757d;
            opacity: 0.7;
        }
        
        .info-tooltip {
            display: inline-block;
            position: relative;
            margin-left: 5px;
            cursor: help;
        }
        
        .info-tooltip .tooltip-text {
            visibility: hidden;
            width: 200px;
            background-color: #555;
            color: #fff;
            text-align: center;
            border-radius: 6px;
            padding: 5px;
            position: absolute;
            z-index: 1;
            bottom: 125%;
            left: 50%;
            margin-left: -100px;
            opacity: 0;
            transition: opacity 0.3s;
        }
        
        .info-tooltip:hover .tooltip-text {
            visibility: visible;
            opacity: 1;
        }
    </style>
</head>
<body>
    <a href="admin_dashboard.php" class="btn-back">⬅ Retour</a>

    <div class="container">
        <h2>Créer une Formation</h2>

        <?php if (isset($_GET['success'])): ?>
            <p class="message success">✅ Formation ajoutée avec succès.</p>
        <?php endif; ?>

        <?php if (isset($_GET['deleted'])): ?>
            <p class="message success">✅ Formation supprimée avec succès.</p>
        <?php endif; ?>

        <?php if (!empty($message)): ?>
            <p class="message error"><?php echo $message; ?></p>
        <?php endif; ?>

        <form action="create_formation.php" method="POST">
            <label for="title">Titre :</label>
            <input type="text" name="title" required>

            <label for="duration">Durée (en heures) :</label>
            <input type="number" name="duration" required>

            <div class="form-row">
                <div class="form-group">
                    <label for="section">Section :
                        <span class="info-tooltip">ℹ️
                            <span class="tooltip-text">Si aucune section n'est sélectionnée, la formation sera disponible pour toutes les sections.</span>
                        </span>
                    </label>
                    <select name="section">
                        <option value="">Toutes les sections</option>
                        <option value="sol">Sol</option>
                        <option value="vol">Vol</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="fonction">Fonction :
                        <span class="info-tooltip">ℹ️
                            <span class="tooltip-text">Si aucune fonction n'est sélectionnée, la formation sera disponible pour toutes les fonctions.</span>
                        </span>
                    </label>
                    <select name="fonction">
                        <option value="">Toutes les fonctions</option>
                        <option value="BE1900D">BE1900D</option>
                        <option value="C208B">C208B</option>
                        <option value="BELL206">BELL206</option>
                        <option value="AT802">AT802</option>
                    </select>
                </div>
            </div>

            <label for="instructor">Instructeur :</label>
            <select name="instructor" required>
                <option value="">Sélectionner un instructeur</option>
                <?php foreach ($instructors as $instructor): ?>
                    <option value="<?php echo $instructor['id']; ?>">
                        <?php echo $instructor['first_name'] . " " . $instructor['last_name']; ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="courses">Sélectionner les cours :</label>
            <select name="courses[]" multiple required>
                <?php foreach ($courses as $course): ?>
                    <option value="<?php echo $course['id']; ?>"><?php echo $course['title']; ?></option>
                <?php endforeach; ?>
            </select>

            <button type="submit" name="create_formation">Ajouter</button>
        </form>
    </div>

    <div class="container">
        <h2>Liste des Formations</h2>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Nom</th>
                        <th>Durée</th>
                        <th>Section</th>
                        <th>Fonction</th>
                        <th>Instructeur</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($formations as $formation): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($formation['title']); ?></td>
                            <td><?php echo htmlspecialchars($formation['duration']); ?> heures</td>
                            <td>
                                <?php if ($formation['section']): ?>
                                    <span class="badge badge-<?php echo $formation['section']; ?>"><?php echo ucfirst($formation['section']); ?></span>
                                <?php else: ?>
                                    <span class="badge badge-none">Toutes</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($formation['fonction']): ?>
                                    <span class="badge badge-fonction"><?php echo $formation['fonction']; ?></span>
                                <?php else: ?>
                                    <span class="badge badge-none">Toutes</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $formation['instructor_name'] ?? 'Non assigné'; ?></td>
                            <td>
                                <a href="edit_formation.php?id=<?php echo $formation['id']; ?>" class="btn-edit">Modifier</a>
                                <form action="create_formation.php" method="POST" style="display:inline;">
                                    <input type="hidden" name="formation_id" value="<?php echo $formation['id']; ?>">
                                    <button type="submit" name="delete_formation" class="btn-delete" onclick="return confirm('Supprimer cette formation ?')">Supprimer</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
