<?php
session_start();
require '../includes/bd.php'; // Connexion à la base de données

// Vérifier si l'utilisateur est connecté et est un instructeur
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'instructor') {
    header("Location: ../login.php");
    exit();
}

$instructor_id = $_SESSION['user_id'];

// Récupérer les informations de l'instructeur
$query_instructor = $conn->prepare("
    SELECT first_name, last_name 
    FROM users 
    WHERE id = ? AND role = 'instructor'
");
$query_instructor->bind_param("i", $instructor_id);
$query_instructor->execute();
$result_instructor = $query_instructor->get_result();

if ($result_instructor->num_rows === 0) {
    header("Location: ../login.php");
    exit();
}

$instructor = $result_instructor->fetch_assoc();

// Filtres
$formation_filter = isset($_GET['formation']) ? intval($_GET['formation']) : 0;
$section_filter = isset($_GET['section']) ? $_GET['section'] : '';
$fonction_filter = isset($_GET['fonction']) ? $_GET['fonction'] : '';
$search_filter = isset($_GET['search']) ? $_GET['search'] : '';

// Construire la requête avec les filtres
$query_pilots = "
    SELECT DISTINCT u.id, u.first_name, u.last_name, u.email, u.section, u.fonction,
           COUNT(DISTINCT er.id) as exam_count,
           SUM(CASE WHEN er.status = 'passed' THEN 1 ELSE 0 END) as passed_count,
           MAX(er.date_taken) as last_exam_date,
           COUNT(DISTINCT a.id) as attestation_count
    FROM users u
    JOIN exam_results er ON u.id = er.pilot_id
    JOIN exams e ON er.exam_id = e.id
    JOIN formations f ON e.formation_id = f.id
    JOIN formation_instructors fi ON f.id = fi.formation_id
    LEFT JOIN attestations a ON u.id = a.user_id AND a.formation_id = f.id
    WHERE fi.instructor_id = ? AND u.role = 'pilot'
";

// Ajouter les filtres à la requête
$params = array($instructor_id);
$types = "i";

if ($formation_filter > 0) {
    $query_pilots .= " AND f.id = ?";
    $params[] = $formation_filter;
    $types .= "i";
}

if (!empty($section_filter)) {
    $query_pilots .= " AND u.section = ?";
    $params[] = $section_filter;
    $types .= "s";
}

if (!empty($fonction_filter)) {
    $query_pilots .= " AND u.fonction = ?";
    $params[] = $fonction_filter;
    $types .= "s";
}

if (!empty($search_filter)) {
    $query_pilots .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
    $search_param = "%$search_filter%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "sss";
}

$query_pilots .= " GROUP BY u.id ORDER BY last_exam_date DESC";

// Préparer et exécuter la requête
$stmt = $conn->prepare($query_pilots);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result_pilots = $stmt->get_result();

// Récupérer les formations de l'instructeur pour le filtre
$query_formations = $conn->prepare("
    SELECT DISTINCT f.id, f.title
    FROM formations f
    JOIN formation_instructors fi ON f.id = fi.formation_id
    WHERE fi.instructor_id = ?
    ORDER BY f.title
");
$query_formations->bind_param("i", $instructor_id);
$query_formations->execute();
$result_formations = $query_formations->get_result();

// Récupérer les sections et fonctions disponibles pour les filtres
$query_sections = $conn->query("SELECT DISTINCT section FROM users WHERE role = 'pilot' AND section IS NOT NULL ORDER BY section");
$query_fonctions = $conn->query("SELECT DISTINCT fonction FROM users WHERE role = 'pilot' AND fonction IS NOT NULL ORDER BY fonction");

// Enregistrer l'activité
$log_query = $conn->prepare("
    INSERT INTO user_activity_log (user_id, activity_type, activity_details, timestamp)
    VALUES (?, 'view_pilots', 'Consultation de la liste des pilotes', NOW())
");
$log_query->bind_param("i", $instructor_id);
$log_query->execute();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes Pilotes - TTA</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/admin_dashboard.css">
    <style>
        .filter-container {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .filter-form {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: flex-end;
        }
        
        .filter-group {
            flex: 1;
            min-width: 200px;
        }
        
        .filter-buttons {
            display: flex;
            gap: 10px;
        }
        
        .table-responsive {
            overflow-x: auto;
        }
        
        .table {
            width: 100%;
            margin-bottom: 1rem;
            color: #212529;
            border-collapse: collapse;
        }
        
        .table th,
        .table td {
            padding: 0.75rem;
            vertical-align: top;
            border-top: 1px solid #dee2e6;
        }
        
        .table thead th {
            vertical-align: bottom;
            border-bottom: 2px solid #dee2e6;
            background-color: #f8f9fa;
        }
        
        .table tbody tr:hover {
            background-color: rgba(0, 0, 0, 0.03);
        }
        
        .badge {
            display: inline-block;
            padding: 0.25em 0.6em;
            font-size: 75%;
            font-weight: 700;
            line-height: 1;
            text-align: center;
            white-space: nowrap;
            vertical-align: baseline;
            border-radius: 0.25rem;
            color: #fff;
        }
        
        .bg-success {
            background-color: #28a745;
        }
        
        .bg-danger {
            background-color: #dc3545;
        }
        
        .bg-warning {
            background-color: #ffc107;
            color: #212529;
        }
        
        .bg-info {
            background-color: #17a2b8;
        }
        
        .bg-secondary {
            background-color: #6c757d;
        }
        
        .table-actions {
            display: flex;
            gap: 5px;
        }
        
        .btn-action {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
            line-height: 1.5;
            border-radius: 0.2rem;
            color: #fff;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-view {
            background-color: #0a3d91;
        }
        
        .btn-action:hover {
            opacity: 0.9;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            background-color: #f8f9fa;
            border-radius: 8px;
            margin-top: 20px;
        }
        
        .empty-state i {
            font-size: 3rem;
            color: #6c757d;
            margin-bottom: 15px;
        }
        
        .empty-state h3 {
            margin-bottom: 10px;
            color: #343a40;
        }
        
        .empty-state p {
            color: #6c757d;
            max-width: 500px;
            margin: 0 auto;
        }
    </style>
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
            <h1 class="page-title">Mes Pilotes</h1>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="instructor_dashboard.php" class="breadcrumb-link">Accueil</a></li>
                <li class="breadcrumb-item">Pilotes</li>
            </ul>
        </div>

        <!-- Filter Section -->
        <div class="filter-container">
            <h3><i class="fas fa-filter"></i> Filtrer les pilotes</h3>
            <form method="GET" action="" class="filter-form">
                <div class="filter-group">
                    <label for="formation" class="form-label">Formation</label>
                    <select id="formation" name="formation" class="form-select">
                        <option value="0">Toutes les formations</option>
                        <?php while ($formation = $result_formations->fetch_assoc()): ?>
                            <option value="<?php echo $formation['id']; ?>" <?php echo ($formation_filter === $formation['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($formation['title']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="section" class="form-label">Section</label>
                    <select id="section" name="section" class="form-select">
                        <option value="">Toutes les sections</option>
                        <?php while ($section = $query_sections->fetch_assoc()): ?>
                            <option value="<?php echo $section['section']; ?>" <?php echo ($section_filter === $section['section']) ? 'selected' : ''; ?>>
                                <?php echo ucfirst(htmlspecialchars($section['section'])); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="fonction" class="form-label">Fonction</label>
                    <select id="fonction" name="fonction" class="form-select">
                        <option value="">Toutes les fonctions</option>
                        <?php while ($fonction = $query_fonctions->fetch_assoc()): ?>
                            <option value="<?php echo $fonction['fonction']; ?>" <?php echo ($fonction_filter === $fonction['fonction']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($fonction['fonction']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="search" class="form-label">Recherche</label>
                    <input type="text" id="search" name="search" class="form-control" placeholder="Rechercher par nom ou email..." value="<?php echo htmlspecialchars($search_filter); ?>">
                </div>
                
                <div class="filter-buttons">
                    <button type="submit" class="btn btn-primary">Filtrer</button>
                    <a href="instructor_pilots.php" class="btn btn-secondary">Réinitialiser</a>
                </div>
            </form>
        </div>

        <!-- Pilots Table -->
        <?php if ($result_pilots && $result_pilots->num_rows > 0): ?>
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Nom</th>
                                    <th>Email</th>
                                    <th>Section</th>
                                    <th>Fonction</th>
                                    <th>Dernier examen</th>
                                    <th>Examens passés</th>
                                    <th>Attestations</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($pilot = $result_pilots->fetch_assoc()): ?>
                                    <?php 
                                        $success_rate = $pilot['exam_count'] > 0 ? round(($pilot['passed_count'] / $pilot['exam_count']) * 100) : 0;
                                        $badge_class = '';
                                        if ($success_rate >= 80) {
                                            $badge_class = 'bg-success';
                                        } elseif ($success_rate >= 50) {
                                            $badge_class = 'bg-warning';
                                        } else {
                                            $badge_class = 'bg-danger';
                                        }
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($pilot['first_name'] . ' ' . $pilot['last_name']); ?></td>
                                        <td><?php echo htmlspecialchars($pilot['email']); ?></td>
                                        <td><?php echo $pilot['section'] ? ucfirst(htmlspecialchars($pilot['section'])) : '-'; ?></td>
                                        <td><?php echo $pilot['fonction'] ? htmlspecialchars($pilot['fonction']) : '-'; ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($pilot['last_exam_date'])); ?></td>
                                        <td>
                                            <?php echo $pilot['passed_count']; ?>/<?php echo $pilot['exam_count']; ?>
                                            <span class="badge <?php echo $badge_class; ?>"><?php echo $success_rate; ?>%</span>
                                        </td>
                                        <td><?php echo $pilot['attestation_count']; ?></td>
                                        <td class="table-actions">
                                            <a href="instructor_pilot_details.php?id=<?php echo $pilot['id']; ?>" class="btn-action btn-view">
                                                <i class="fas fa-eye"></i> Voir
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-users"></i>
                <h3>Aucun pilote trouvé</h3>
                <p>Aucun pilote ne correspond à vos critères de recherche ou n'a passé d'examen dans vos formations.</p>
            </div>
        <?php endif; ?>
    </div>

    <script src="../scripts/admin_dashboard.js"></script>
</body>
</html>
