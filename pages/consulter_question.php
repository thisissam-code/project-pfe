<?php
session_start();
require '../includes/bd.php'; // Connexion à la base de données

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'instructor') {
    die("Accès refusé.");
}

$instructeur_id = $_SESSION['user_id']; // ID de l'instructeur connecté

// Récupérer les formations assignées à cet instructeur
$sql_formations = "SELECT f.id, f.title FROM formations f 
                   JOIN formation_instructors fi ON f.id = fi.formation_id
                   WHERE fi.instructor_id = ?";
$stmt = $conn->prepare($sql_formations);
$stmt->bind_param("i", $instructeur_id);
$stmt->execute();
$result_formations = $stmt->get_result();

$formations = [];
while ($row = $result_formations->fetch_assoc()) {
    $formations[] = $row;
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Questions des Formations</title>
    <link rel="stylesheet" href="../assets/consulter_question.css">
</head>
<body>
    <h2>Questions de mes Formations</h2>

    <?php foreach ($formations as $formation): ?>
        <?php
        $formation_id = $formation['id'];
        $formation_title = htmlspecialchars($formation['title']);

        // Récupération des questions de cette formation
        $sql_questions = "SELECT id, question_text FROM questions WHERE formation_id = ?";
        $stmt = $conn->prepare($sql_questions);
        $stmt->bind_param("i", $formation_id);
        $stmt->execute();
        $result_questions = $stmt->get_result();
        ?>
        
        <?php while ($question = $result_questions->fetch_assoc()): ?>
            <div class="question-card">
                <div class="formation-title"><?= $formation_title ?></div>
                <div class="question-text"><?= htmlspecialchars($question['question_text']) ?></div>

                <?php
                // Récupération des réponses de cette question
                $question_id = $question['id'];
                $sql_answers = "SELECT answer_text, is_correct FROM answers WHERE question_id = ?";
                $stmt_answers = $conn->prepare($sql_answers);
                $stmt_answers->bind_param("i", $question_id);
                $stmt_answers->execute();
                $result_answers = $stmt_answers->get_result();

                while ($answer = $result_answers->fetch_assoc()):
                    $answer_text = htmlspecialchars($answer['answer_text']);
                    $is_correct = $answer['is_correct'];
                    $class = $is_correct ? 'correct' : 'incorrect';
                    ?>
                    <div class="answer <?= $class ?>"><?= $answer_text ?></div>
                <?php endwhile;
                $stmt_answers->close();
                ?>
            </div>
        <?php endwhile;
        $stmt->close(); ?>
    <?php endforeach; ?>
</body>
</html>
