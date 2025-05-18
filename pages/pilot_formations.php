<?php
session_start();
require '../includes/bd.php'; // Connexion à la base de données

// Vérifier si l'utilisateur est un pilote avant d'accéder à la page
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'pilot') {
    header("Location: ../index.php");
    exit();
}

$pilot_id = $_SESSION['user_id'];
$pilot_section = $_SESSION['section'] ?? null;
$pilot_fonction = $_SESSION['fonction'] ?? null;

// Vérifier si les variables sont définies
if ($pilot_section === null || $pilot_fonction === null) {
    // Récupérer les informations depuis la base de données si elles ne sont pas dans la session
    $query_user = $conn->prepare("SELECT section, fonction FROM users WHERE id = ?");
    $query_user->bind_param("i", $pilot_id);
    $query_user->execute();
    $result_user = $query_user->get_result();
    
    if ($user_data = $result_user->fetch_assoc()) {
        $pilot_section = $user_data['section'];
        $pilot_fonction = $user_data['fonction'];
        
        // Mettre à jour la session
        $_SESSION['section'] = $pilot_section;
        $_SESSION['fonction'] = $pilot_fonction;
    }
}

// Initialiser le filtre de section
$section_filter = isset($_GET['section']) ? $_GET['section'] : 'all';

// Vérifier s'il y a des formations dans la base de données
$check_formations = $conn->query("SELECT COUNT(*) as count FROM formations");
$formations_count = $check_formations->fetch_assoc()['count'];

// Récupérer les formations pour lesquelles le pilote a déjà une attestation valide
$query_valid_attestations = $conn->prepare("
    SELECT formation_id 
    FROM attestations 
    WHERE user_id = ? 
    AND (statut = 'valide' OR statut = 'bientot_expire')
");
$query_valid_attestations->bind_param("i", $pilot_id);
$query_valid_attestations->execute();
$result_valid_attestations = $query_valid_attestations->get_result();

$excluded_formations = [];
while ($row = $result_valid_attestations->fetch_assoc()) {
    $excluded_formations[] = $row['formation_id'];
}

// Construire la clause d'exclusion
$exclusion_clause = "";
if (!empty($excluded_formations)) {
    $exclusion_clause = " AND f.id NOT IN (" . implode(',', $excluded_formations) . ")";
}

// Construire la clause de filtrage par section
$section_clause = "";
if ($section_filter !== 'all') {
    $section_clause = " AND (f.section = '$section_filter')";
}

// Récupérer toutes les formations compatibles avec la section et la fonction du pilote
// et exclure celles pour lesquelles le pilote a déjà une attestation valide
$query_formations = $conn->prepare("
    SELECT f.id, f.title, f.duration, f.section, f.fonction, f.created_at,
           COUNT(DISTINCT fc.course_id) AS course_count,
           (SELECT COUNT(*) FROM exams WHERE formation_id = f.id) AS exam_count,
           (SELECT COUNT(*) FROM exam_results er JOIN exams e ON er.exam_id = e.id 
            WHERE e.formation_id = f.id AND er.pilot_id = ? AND er.status = 'passed') AS passed_exams
    FROM formations f
    LEFT JOIN formation_courses fc ON f.id = fc.formation_id
    WHERE (f.fonction = ? OR f.fonction IS NULL OR ? IS NULL)
    " . $exclusion_clause . $section_clause . "
    GROUP BY f.id
    ORDER BY f.created_at DESC
");
$query_formations->bind_param("iss", $pilot_id, $pilot_fonction, $pilot_fonction);
$query_formations->execute();
$result_formations = $query_formations->get_result();

// Récupérer les statistiques des formations
$query_stats = $conn->prepare("
    SELECT 
        COUNT(DISTINCT f.id) as total_formations,
        COUNT(DISTINCT fc.course_id) as total_courses,
        COUNT(DISTINCT e.id) as total_exams,
        SUM(CASE WHEN er.status = 'passed' THEN 1 ELSE 0 END) as passed_exams
    FROM formations f
    LEFT JOIN formation_courses fc ON f.id = fc.formation_id
    LEFT JOIN exams e ON f.id = e.formation_id
    LEFT JOIN exam_results er ON e.id = er.exam_id AND er.pilot_id = ?
    WHERE (f.fonction = ? OR f.fonction IS NULL OR ? IS NULL)
    " . $exclusion_clause . $section_clause . "
");
$query_stats->bind_param("iss", $pilot_id, $pilot_fonction, $pilot_fonction);
$query_stats->execute();
$stats = $query_stats->get_result()->fetch_assoc();

// Récupérer les formations récemment consultées
$query_recent = $conn->prepare("
    SELECT f.id, f.title, f.section, f.fonction, 
           MAX(fv.view_date) as last_viewed
    FROM formation_views fv
    JOIN formations f ON fv.formation_id = f.id
    WHERE fv.user_id = ?
    AND f.id NOT IN (
        SELECT formation_id FROM attestations 
        WHERE user_id = ? AND (statut = 'valide' OR statut = 'bientot_expire')
    )
    GROUP BY f.id
    ORDER BY last_viewed DESC
    LIMIT 3
");
$query_recent->bind_param("ii", $pilot_id, $pilot_id);
$query_recent->execute();
$result_recent = $query_recent->get_result();

// Enregistrer la consultation de la page des formations
$query_log_view = $conn->prepare("
    INSERT INTO user_activity_log (user_id, activity_type, activity_details, timestamp)
    VALUES (?, 'view_formations', 'Consultation de la liste des formations', NOW())
");
$query_log_view->bind_param("i", $pilot_id);
$query_log_view->execute();

// Récupérer le nombre de formations par section pour les statistiques
$query_section_stats = $conn->prepare("
    SELECT 
        SUM(CASE WHEN f.section = 'sol' THEN 1 ELSE 0 END) as sol_count,
        SUM(CASE WHEN f.section = 'vol' THEN 1 ELSE 0 END) as vol_count,
        SUM(CASE WHEN f.section IS NULL THEN 1 ELSE 0 END) as all_count
    FROM formations f
    WHERE (f.fonction = ? OR f.fonction IS NULL OR ? IS NULL)
    " . $exclusion_clause . "
");
$query_section_stats->bind_param("ss", $pilot_fonction, $pilot_fonction);
$query_section_stats->execute();
$section_stats = $query_section_stats->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes Formations - TTA</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/admin_dashboard.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.19/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.19/dist/sweetalert2.all.min.js"></script>
    <script src="../components/alerts.js"></script>
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background-color: #fff;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            margin: 10px 0;
        }
        
        .stat-label {
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .total-formations {
            border-top: 4px solid #0a3d91;
        }
        
        .total-courses {
            border-top: 4px solid #28a745;
        }
        
        .total-exams {
            border-top: 4px solid #dc3545;
        }
        
        .passed-exams {
            border-top: 4px solid #ffc107;
        }
        
        .formation-card {
            margin-bottom: 20px;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            background-color: #fff;
        }
        
        .formation-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .formation-header {
            padding: 15px 20px;
            background-color: #f8f9fa;
            border-bottom: 1px solid #e9ecef;
        }
        
        .formation-title {
            margin: 0;
            font-size: 1.25rem;
            color: #0a3d91;
        }
        
        .formation-body {
            padding: 20px;
        }
        
        .formation-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .info-item {
            display: flex;
            flex-direction: column;
        }
        
        .info-label {
            font-size: 0.85rem;
            color: #6c757d;
            margin-bottom: 5px;
        }
        
        .info-value {
            font-weight: 500;
        }
        
        .formation-progress {
            margin-bottom: 15px;
        }
        
        .progress-bar-container {
            height: 8px;
            background-color: #e9ecef;
            border-radius: 4px;
            overflow: hidden;
            margin-top: 5px;
        }
        
        .progress-bar {
            height: 100%;
            background-color: #0a3d91;
        }
        
        .formation-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
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
        
        .bg-secondary {
            background-color: #6c757d;
        }
        
        .recent-formations {
            margin-bottom: 30px;
        }
        
        .recent-title {
            margin-bottom: 15px;
            font-size: 1.25rem;
            color: #0a3d91;
        }
        
        .recent-list {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .recent-item {
            background-color: #fff;
            border-radius: 8px;
            padding: 15px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            flex: 1;
            min-width: 200px;
            transition: transform 0.2s ease;
        }
        
        .recent-item:hover {
            transform: translateY(-3px);
        }
        
        .recent-item-title {
            font-weight: 500;
            margin-bottom: 5px;
            color: #0a3d91;
        }
        
        .recent-item-info {
            font-size: 0.85rem;
            color: #6c757d;
        }
        
        .search-filter {
            margin-bottom: 20px;
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .search-input {
            flex: 1;
            min-width: 200px;
        }
        
        .filter-select {
            min-width: 150px;
        }

        .section-title {
            margin-bottom: 15px;
            font-size: 1.5rem;
            color: #0a3d91;
        }
        
        .info-banner {
            background-color: #f8f9fa;
            border-left: 4px solid #0a3d91;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        
        /* Styles pour les filtres */
        .filter-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .filter-title {
            font-weight: 500;
            margin-right: 15px;
            color: #0a3d91;
        }
        
        .filter-options {
            display: flex;
            gap: 10px;
        }
        
        .filter-btn {
            padding: 8px 15px;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            background-color: #fff;
            color: #495057;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .filter-btn:hover {
            background-color: #e9ecef;
        }
        
        .filter-btn.active {
            background-color: #0a3d91;
            color: #fff;
            border-color: #0a3d91;
        }
        
        .section-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
            color: white;
            margin-left: 10px;
        }
        
        .section-badge-sol {
            background-color: #28a745;
        }
        
        .section-badge-vol {
            background-color: #007bff;
        }
        
        .section-badge-all {
            background-color: #6c757d;
        }
        
        .section-stats {
            display: flex;
            gap: 10px;
            margin-left: auto;
        }
        
        .section-stat {
            display: flex;
            align-items: center;
            font-size: 0.85rem;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <!-- Mobile Navigation Toggle -->
    <button class="mobile-nav-toggle">
        <i class="fas fa-bars"></i>
    </button>

    <?php include '../includes/pilote_sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <div class="page-header">
            <h1 class="page-title">Mes Formations</h1>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="pilot_dashboard.php" class="breadcrumb-link">Accueil</a></li>
                <li class="breadcrumb-item">Formations</li>
            </ul>
        </div>

        <!-- Information Banner -->
        <div class="info-banner">
            <i class="fas fa-info-circle"></i> 
            Cette page affiche uniquement les formations pour lesquelles vous n'avez pas encore d'attestation valide. 
            Pour voir vos attestations, consultez la page <a href="pilot_attestations.php">Mes Attestations</a>.
        </div>

        <!-- Filter Section -->
        <div class="filter-container">
            <div class="filter-title">Filtrer par section:</div>
            <div class="filter-options">
                <a href="?section=all" class="filter-btn <?php echo $section_filter === 'all' ? 'active' : ''; ?>">
                    Toutes les sections
                </a>
                <a href="?section=sol" class="filter-btn <?php echo $section_filter === 'sol' ? 'active' : ''; ?>">
                    SOL
                </a>
                <a href="?section=vol" class="filter-btn <?php echo $section_filter === 'vol' ? 'active' : ''; ?>">
                    VOL
                </a>
            </div>
            <div class="section-stats">
                <div class="section-stat">
                    <span class="section-badge section-badge-sol"></span>
                    SOL: <?php echo $section_stats['sol_count'] ?? 0; ?>
                </div>
                <div class="section-stat">
                    <span class="section-badge section-badge-vol"></span>
                    VOL: <?php echo $section_stats['vol_count'] ?? 0; ?>
                </div>
                <div class="section-stat">
                    <span class="section-badge section-badge-all"></span>
                    Génériques: <?php echo $section_stats['all_count'] ?? 0; ?>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card total-formations">
                <div class="stat-label">Formations disponibles</div>
                <div class="stat-value"><?php echo $stats['total_formations'] ?? 0; ?></div>
            </div>
            <div class="stat-card total-courses">
                <div class="stat-label">Cours disponibles</div>
                <div class="stat-value"><?php echo $stats['total_courses'] ?? 0; ?></div>
            </div>
            <div class="stat-card total-exams">
                <div class="stat-label">Examens disponibles</div>
                <div class="stat-value"><?php echo $stats['total_exams'] ?? 0; ?></div>
            </div>
            <div class="stat-card passed-exams">
                <div class="stat-label">Examens réussis</div>
                <div class="stat-value"><?php echo $stats['passed_exams'] ?? 0; ?></div>
            </div>
        </div>

        <!-- Recently Viewed Formations -->
        <?php if ($result_recent && $result_recent->num_rows > 0): ?>
        <div class="recent-formations">
            <h2 class="recent-title">Formations récemment consultées</h2>
            <div class="recent-list">
                <?php while ($recent = $result_recent->fetch_assoc()): ?>
                    <a href="formation_details.php?id=<?php echo $recent['id']; ?>" class="recent-item">
                        <div class="recent-item-title">
                            <?php echo htmlspecialchars($recent['title']); ?>
                            <?php if ($recent['section']): ?>
                                <span class="section-badge section-badge-<?php echo $recent['section']; ?>">
                                    <?php echo strtoupper($recent['section']); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="recent-item-info">
                            <?php if ($recent['section']): ?>
                                <span>Section: <?php echo ucfirst(htmlspecialchars($recent['section'])); ?></span>
                            <?php endif; ?>
                            <?php if ($recent['fonction']): ?>
                                <span> | Fonction: <?php echo htmlspecialchars($recent['fonction']); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="recent-item-info">
                            Dernière consultation: <?php echo date('d/m/Y H:i', strtotime($recent['last_viewed'])); ?>
                        </div>
                    </a>
                <?php endwhile; ?>
            </div>
        </div>
        <?php endif; ?>

        <h3 class="section-title">
            Formations disponibles 
            <?php if ($section_filter !== 'all'): ?>
                <span class="section-badge section-badge-<?php echo $section_filter; ?>">
                    Section <?php echo strtoupper($section_filter); ?>
                </span>
            <?php endif; ?>
            pour votre fonction: <?php echo htmlspecialchars($pilot_fonction); ?>
        </h3>
        
        <!-- Formations List -->
        <div class="row" id="formations-container">
            <?php if ($result_formations && $result_formations->num_rows > 0): ?>
                <?php while ($formation = $result_formations->fetch_assoc()): ?>
                    <?php 
                        // Calculer le pourcentage de progression
                        $progress = 0;
                        if ($formation['exam_count'] > 0) {
                            $progress = ($formation['passed_exams'] / $formation['exam_count']) * 100;
                        }
                    ?>
                    <div class="col-md-6 formation-item">
                        <div class="formation-card">
                            <div class="formation-header">
                                <h3 class="formation-title">
                                    <?php echo htmlspecialchars($formation['title']); ?>
                                    <?php if ($formation['section']): ?>
                                        <span class="section-badge section-badge-<?php echo $formation['section']; ?>">
                                            <?php echo strtoupper($formation['section']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="section-badge section-badge-all">GÉNÉRIQUE</span>
                                    <?php endif; ?>
                                </h3>
                            </div>
                            <div class="formation-body">
                                <div class="formation-info">
                                    <div class="info-item">
                                        <span class="info-label">Section</span>
                                        <span class="info-value"><?php echo $formation['section'] ? ucfirst(htmlspecialchars($formation['section'])) : 'Toutes'; ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Fonction</span>
                                        <span class="info-value"><?php echo $formation['fonction'] ? htmlspecialchars($formation['fonction']) : 'Toutes'; ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Durée</span>
                                        <span class="info-value"><?php echo $formation['duration']; ?> heures</span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Cours</span>
                                        <span class="info-value"><?php echo $formation['course_count']; ?></span>
                                    </div>
                                </div>
                                
                                <div class="formation-progress">
                                    <div class="d-flex justify-content-between">
                                        <span class="info-label">Progression</span>
                                        <span class="info-value"><?php echo round($progress); ?>%</span>
                                    </div>
                                    <div class="progress-bar-container">
                                        <div class="progress-bar" style="width: <?php echo $progress; ?>%"></div>
                                    </div>
                                </div>
                                
                                <div class="formation-actions">
                                    <a href="formation_details.php?id=<?php echo $formation['id']; ?>" class="btn btn-primary">
                                        <i class="fas fa-eye"></i> Consulter
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> 
                        <?php if ($section_filter !== 'all'): ?>
                            Aucune formation de section <?php echo strtoupper($section_filter); ?> disponible pour votre fonction, ou vous avez déjà des attestations valides pour toutes les formations disponibles.
                        <?php else: ?>
                            Aucune formation disponible pour votre section et fonction, ou vous avez déjà des attestations valides pour toutes les formations disponibles.
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="../scripts/admin_dashboard.js"></script>
</body>
</html>
