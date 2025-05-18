<?php
session_start();
require '../includes/bd.php';

// Vérifier si l'utilisateur est un admin avant d'accéder à la page
if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'admin_sol' && $_SESSION['role'] !== 'admin_vol')) {
    header("Location: ../login.php");
    exit();
}

// Activer l'affichage des erreurs pour le débogage
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Ajouter ces lignes au début du script, juste après les configurations d'erreur
echo "<p>Paramètres reçus: </p>";
echo "<pre>";
print_r($_GET);
echo "</pre>";

// Modifier la vérification de l'ID pour être plus explicite
if (!isset($_GET['id'])) {
    echo "Erreur: Paramètre ID manquant.";
    exit();
}

if (!is_numeric($_GET['id'])) {
    echo "Erreur: L'ID '" . htmlspecialchars($_GET['id']) . "' n'est pas un nombre valide.";
    exit();
}

$question_id = intval($_GET['id']);

echo "<h1>Suppression directe de la question #$question_id</h1>";

// Désactiver les contraintes de clé étrangère
$conn->query("SET FOREIGN_KEY_CHECKS=0");
echo "<p>Contraintes de clé étrangère désactivées.</p>";

// Supprimer les réponses associées
$delete_answers_query = "DELETE FROM answers WHERE question_id = $question_id";
$delete_answers_result = $conn->query($delete_answers_query);

if ($delete_answers_result) {
    echo "<p style='color:green;'>✓ Réponses supprimées avec succès.</p>";
} else {
    echo "<p style='color:red;'>✗ Erreur lors de la suppression des réponses: " . $conn->error . "</p>";
}

// Supprimer la question
$delete_question_query = "DELETE FROM questions WHERE id = $question_id";
$delete_question_result = $conn->query($delete_question_query);

if ($delete_question_result) {
    echo "<p style='color:green;'>✓ Question supprimée avec succès.</p>";
} else {
    echo "<p style='color:red;'>✗ Erreur lors de la suppression de la question: " . $conn->error . "</p>";
}

// Réactiver les contraintes de clé étrangère
$conn->query("SET FOREIGN_KEY_CHECKS=1");
echo "<p>Contraintes de clé étrangère réactivées.</p>";

// Afficher un lien pour retourner à la page des questions
echo "<p><a href='ajouter_question.php?deleted=1'>Retour à la liste des questions</a></p>";

// Redirection automatique après 3 secondes
echo "<script>
    setTimeout(function() {
        window.location.href = 'ajouter_question.php?deleted=1';
    }, 3000);
</script>";
?>
