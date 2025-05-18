<?php
session_start();
require '../includes/bd.php'; // Connexion à la base de données

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$is_admin = ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'admin_sol' || $_SESSION['role'] === 'admin_vol');

// Vérifier si l'ID de l'examen est fourni
if (!isset($_POST['exam_id']) || !is_numeric($_POST['exam_id'])) {
    header("Location: " . ($is_admin ? "admin_dashboard.php" : "pilot_dashboard.php"));
    exit();
}

$exam_id = intval($_POST['exam_id']);
$answers = isset($_POST['answers']) ? $_POST['answers'] : [];
$time_taken = isset($_POST['time_taken']) ? intval($_POST['time_taken']) : 0;

// Récupérer les informations de l'examen
$query_exam = $conn->prepare("
    SELECT e.*, f.id as formation_id 
    FROM exams e
    JOIN formations f ON e.formation_id = f.id
    WHERE e.id = ?
");
$query_exam->bind_param("i", $exam_id);
$query_exam->execute();
$result_exam = $query_exam->get_result();

if ($result_exam->num_rows === 0) {
    header("Location: " . ($is_admin ? "admin_dashboard.php" : "pilot_dashboard.php"));
    exit();
}

$exam = $result_exam->fetch_assoc();
$formation_id = $exam['formation_id'];

// Récupérer les questions de l'examen
$query_questions = $conn->prepare("
    SELECT eq.question_id, q.question_text, eq.question_order
    FROM exam_questions eq
    JOIN questions q ON eq.question_id = q.id
    WHERE eq.exam_id = ?
    ORDER BY eq.question_order
");
$query_questions->bind_param("i", $exam_id);
$query_questions->execute();
$result_questions = $query_questions->get_result();

$total_questions = $result_questions->num_rows;
$correct_answers = 0;

// Préparer l'insertion des réponses
$insert_result = $conn->prepare("
    INSERT INTO exam_results (exam_id, pilot_id, score, status, date_taken, time_taken)
    VALUES (?, ?, ?, ?, NOW(), ?)
");

$insert_answer = $conn->prepare("
    INSERT INTO exam_result_answers (result_id, question_id, selected_answer_id, question_order)
    VALUES (?, ?, ?, ?)
");

// Calculer le score
$questions_data = [];
while ($question = $result_questions->fetch_assoc()) {
    $question_id = $question['question_id'];
    $questions_data[$question_id] = $question;
    
    // Récupérer la réponse correcte
    $query_correct = $conn->prepare("
        SELECT id FROM answers 
        WHERE question_id = ? AND is_correct = 1
    ");
    $query_correct->bind_param("i", $question_id);
    $query_correct->execute();
    $result_correct = $query_correct->get_result();
    
    if ($result_correct->num_rows > 0) {
        $correct_answer = $result_correct->fetch_assoc();
        $correct_answer_id = $correct_answer['id'];
        
        // Vérifier si la réponse de l'utilisateur est correcte
        $selected_answer_id = isset($answers[$question_id]) ? intval($answers[$question_id]) : null;
        
        if ($selected_answer_id === $correct_answer_id) {
            $correct_answers++;
        }
    }
}

// Calculer le score en pourcentage - Chaque réponse correcte vaut 1%
$score = $correct_answers; // Modification ici: le score est directement le nombre de réponses correctes

// Déterminer si l'examen est réussi ou échoué
$status = ($score >= $exam['passing_score']) ? 'passed' : 'failed';

// Enregistrer le résultat
$insert_result->bind_param("iissi", $exam_id, $user_id, $score, $status, $time_taken);
$insert_result->execute();
$result_id = $conn->insert_id;

// Enregistrer les réponses détaillées
foreach ($questions_data as $question_id => $question) {
    $selected_answer_id = isset($answers[$question_id]) ? intval($answers[$question_id]) : null;
    $question_order = $question['question_order'];
    
    $insert_answer->bind_param("iiii", $result_id, $question_id, $selected_answer_id, $question_order);
    $insert_answer->execute();
}

// Mettre à jour le statut de l'examen pour le pilote
$update_pilot_exam = $conn->prepare("
    UPDATE pilot_exams 
    SET status = ?, completed_date = NOW() 
    WHERE pilot_id = ? AND exam_id = ?
");
$update_pilot_exam->bind_param("sii", $status, $user_id, $exam_id);
$update_pilot_exam->execute();

// Si l'examen est échoué, générer automatiquement un nouvel examen
if ($status === 'failed') {
    // Inclure le fichier de génération d'examen
    require_once 'generate_exam.php';
    
    // Générer un nouvel examen
    $new_exam = generateExam($conn, $formation_id, $user_id, $exam_id);
    
    if ($new_exam['success']) {
        $new_exam_id = $new_exam['exam_id'];
        
        // Enregistrer dans les logs
        $log_query = $conn->prepare("
            INSERT INTO user_activity_log (user_id, activity_type, activity_details, timestamp)
            VALUES (?, 'exam_failed_new_generated', CONCAT('Échec à l\'examen #', ?, '. Nouvel examen #', ?, ' généré.'), NOW())
        ");
        $log_query->bind_param("iii", $user_id, $exam_id, $new_exam_id);
        $log_query->execute();
        
        // Rediriger vers la page de résultat avec un message
        header("Location: view_exam_result.php?id=$result_id&new_exam=$new_exam_id");
        exit();
    }
}

// Enregistrer dans les logs
$activity_type = ($status === 'passed') ? 'exam_passed' : 'exam_failed';
$log_query = $conn->prepare("
    INSERT INTO user_activity_log (user_id, activity_type, activity_details, timestamp)
    VALUES (?, ?, CONCAT('Examen #', ?, ' terminé avec un score de ', ?), NOW())
");
$log_query->bind_param("isis", $user_id, $activity_type, $exam_id, $score);
$log_query->execute();

// Rediriger vers la page de résultat
header("Location: view_exam_result.php?id=$result_id");
exit();
?>