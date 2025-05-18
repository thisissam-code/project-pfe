<?php
session_start();
require '../includes/bd.php'; // Connexion à la base de données

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'instructor') {
    die("Accès refusé.");
}

// Vérifier si un ID de formation est fourni
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("ID de formation non fourni.");
}

$formation_id = intval($_GET['id']);

// Récupérer les détails de la formation
$query = "SELECT title, duration FROM formations WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $formation_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    die("Formation introuvable.");
}

$formation = $result->fetch_assoc();

// Récupérer les cours associés à cette formation, y compris le fichier support (PDF)
$query = "SELECT c.id, c.title, c.description, c.file_path 
          FROM courses c 
          JOIN formation_courses fc ON c.id = fc.course_id 
          WHERE fc.formation_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $formation_id);
$stmt->execute();
$courses_result = $stmt->get_result();

// Récupérer l'instructeur de la formation
$query = "SELECT u.first_name, u.last_name, u.email 
          FROM users u 
          JOIN formation_instructors fi ON u.id = fi.instructor_id 
          WHERE fi.formation_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $formation_id);
$stmt->execute();
$instructor_result = $stmt->get_result();
$instructor = $instructor_result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Détails de la Formation</title>
    <link rel="stylesheet" href="../assets/consulter_formation.css"> <!-- Ajoutez votre fichier CSS -->
</head>
<body>

    <h2>Formation : <?= htmlspecialchars($formation['title']) ?></h2>
    <p><strong>Durée :</strong> <?= $formation['duration'] ?> heures</p>

    <?php if ($instructor): ?>
        <p><strong>Instructeur :</strong> <?= htmlspecialchars($instructor['first_name']) . " " . htmlspecialchars($instructor['last_name']) ?> (<?= htmlspecialchars($instructor['email']) ?>)</p>
    <?php else: ?>
        <p><em>Aucun instructeur assigné.</em></p>
    <?php endif; ?>

    <h3>Cours inclus :</h3>
    <ul>
        <?php while ($course = $courses_result->fetch_assoc()): ?>
            <li>
                <strong><?= htmlspecialchars($course['title']) ?></strong><br>
                <em><?= nl2br(htmlspecialchars($course['description'])) ?></em><br>

                <?php if (!empty($course['file_path'])): ?>
                    📄 <a href="<?= htmlspecialchars($course['file_path']) ?>" target="_blank">Voir le support (PDF)</a>
                <?php else: ?>
                    <em>Aucun support disponible</em>
                <?php endif; ?>
            </li>
        <?php endwhile; ?>
    </ul>

    <a href="instructor_dashboard.php">Retour aux formations</a>

</body>
</html>
