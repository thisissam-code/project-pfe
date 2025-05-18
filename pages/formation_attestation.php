<?php
session_start();
require '../includes/bd.php'; // Connexion à la base de données

// Vérifier si l'utilisateur est un instructeur avant d'accéder à la page
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'instructor') {
    header("Location: ../index.php");
    exit();
}

// Vérifier si un ID de formation est fourni
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: instructor_formations.php");
    exit();
}

$formation_id = intval($_GET['id']);
$instructor_id = $_SESSION['user_id'];

// Vérifier si l'instructeur est associé à cette formation
$check_instructor = $conn->prepare("
    SELECT COUNT(*) as count 
    FROM formation_instructors 
    WHERE formation_id = ? AND instructor_id = ?
");
$check_instructor->bind_param("ii", $formation_id, $instructor_id);
$check_instructor->execute();
$result_check = $check_instructor->get_result();
$is_instructor = $result_check->fetch_assoc()['count'] > 0;

if (!$is_instructor) {
    header("Location: instructor_formations.php");
    exit();
}

// Récupérer les informations de la formation
$query_formation = $conn->prepare("SELECT * FROM formations WHERE id = ?");
$query_formation->bind_param("i", $formation_id);
$query_formation->execute();
$formation = $query_formation->get_result()->fetch_assoc();

// Récupérer toutes les attestations liées à cette formation
$query_attestations = $conn->prepare("
    SELECT a.id, a.title, a.date_created, a.status, 
           u.id as user_id, u.first_name, u.last_name
    FROM attestations a
    JOIN users u ON a.user_id = u.id
    WHERE a.formation_id = ?
    ORDER BY a.date_created DESC
");
$query_attestations->bind_param("i", $formation_id);
$query_attestations->execute();
$result_attestations = $query_attestations->get_result();

// Récupérer les étudiants de la formation qui n'ont pas encore d'attestation
$query_students_without_attestation = $conn->prepare("
    SELECT u.id, u.first_name, u.last_name
    FROM users u
    JOIN formation_users fu ON u.id = fu.user_id
    WHERE fu.formation_id = ?
    AND u.id NOT IN (
        SELECT user_id FROM attestations WHERE formation_id = ?
    )
");
$query_students_without_attestation->bind_param("ii", $formation_id, $formation_id);
$query_students_without_attestation->execute();
$result_students = $query_students_without_attestation->get_result();

// Traitement de l'ajout d'une attestation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_attestation'])) {
    $title = trim($_POST['title']);
    $user_id = intval($_POST['user_id']);
    $status = 'pending'; // Par défaut, l'attestation est en attente
    
    // Vérifier si l'étudiant est inscrit à la formation
    $check_student = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM formation_users 
        WHERE formation_id = ? AND user_id = ?
    ");
    $check_student->bind_param("ii", $formation_id, $user_id);
    $check_student->execute();
    $is_student = $check_student->get_result()->fetch_assoc()['count'] > 0;
    
    if (!$is_student) {
        $error_message = "L'étudiant sélectionné n'est pas inscrit à cette formation.";
    } else {
        // Vérifier si une attestation existe déjà pour cet étudiant dans cette formation
        $check_attestation = $conn->prepare("
            SELECT COUNT(*) as count 
            FROM attestations 
            WHERE formation_id = ? AND user_id = ?
        ");
        $check_attestation->bind_param("ii", $formation_id, $user_id);
        $check_attestation->execute();
        $attestation_exists = $check_attestation->get_result()->fetch_assoc()['count'] > 0;
        
        if ($attestation_exists) {
            $error_message = "Une attestation existe déjà pour cet étudiant dans cette formation.";
        } else {
            // Ajouter l'attestation
            $insert_query = "INSERT INTO attestations (title, formation_id, user_id, status, date_created) VALUES (?, ?, ?, ?, NOW())";
            $stmt = $conn->prepare($insert_query);
            $stmt->bind_param("siis", $title, $formation_id, $user_id, $status);
            
            if ($stmt->execute()) {
                $success_message = "Attestation ajoutée avec succès.";
                
                // Recharger les attestations
                $query_attestations->execute();
                $result_attestations = $query_attestations->get_result();
                
                // Recharger les étudiants sans attestation
                $query_students_without_attestation->execute();
                $result_students = $query_students_without_attestation->get_result();
            } else {
                $error_message = "Erreur lors de l'ajout de l'attestation.";
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
    <title>Attestations de la Formation - TTA</title>
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

    <?php include '../includes/instructor_sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <div class="page-header">
            <h1 class="page-title">Attestations de la Formation</h1>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="instructor_dashboard.php" class="breadcrumb-link">Accueil</a></li>
                <li class="breadcrumb-item"><a href="instructor_formations.php" class="breadcrumb-link">Mes formations</a></li>
                <li class="breadcrumb-item"><a href="view_formation.php?id=<?php echo $formation_id; ?>" class="breadcrumb-link"><?php echo htmlspecialchars($formation['title']); ?></a></li>
                <li class="breadcrumb-item">Attestations</li>
            </ul>
        </div>

        <?php if (isset($success_message)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
            </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <!-- Add Attestation Form -->
        <?php if ($result_students && $result_students->num_rows > 0): ?>
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Ajouter une attestation</h2>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="title" class="form-label">Titre de l'attestation</label>
                            <input type="text" id="title" name="title" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="user_id" class="form-label">Étudiant</label>
                            <select id="user_id" name="user_id" class="form-select" required>
                                <option value="">Sélectionner un étudiant</option>
                                <?php while ($student = $result_students->fetch_assoc()): ?>
                                    <option value="<?php echo $student['id']; ?>">
                                        <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <button type="submit" name="add_attestation" class="btn btn-primary">Ajouter l'attestation</button>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <!-- Attestations List -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Liste des attestations</h2>
            </div>
            <div class="card-body">
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Titre</th>
                                <th>Étudiant</th>
                                <th>Date de création</th>
                                <th>Statut</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result_attestations && $result_attestations->num_rows > 0): ?>
                                <?php while ($attestation = $result_attestations->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($attestation['title']); ?></td>
                                        <td>
                                            <a href="view_student.php?id=<?php echo $attestation['user_id']; ?>">
                                                <?php echo htmlspecialchars($attestation['first_name'] . ' ' . $attestation['last_name']); ?>
                                            </a>
                                        </td>
                                        <td><?php echo date('d/m/Y', strtotime($attestation['date_created'])); ?></td>
                                        <td>
                                            <?php if ($attestation['status'] === 'completed'): ?>
                                                <span class="badge bg-success">Complétée</span>
                                            <?php elseif ($attestation['status'] === 'pending'): ?>
                                                <span class="badge bg-warning">En attente</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Non démarrée</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="table-actions">
                                            <a href="view_attestation.php?id=<?php echo $attestation['id']; ?>" class="btn-action" data-tooltip="Voir">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="edit_attestation.php?id=<?php echo $attestation['id']; ?>" class="btn-action btn-edit" data-tooltip="Modifier">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="print_attestation.php?id=<?php echo $attestation['id']; ?>" class="btn-action" data-tooltip="Imprimer" target="_blank">
                                                <i class="fas fa-print"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center">Aucune attestation trouvée pour cette formation</td>
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
