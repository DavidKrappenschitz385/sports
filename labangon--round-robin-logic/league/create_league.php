<?php
// league/create_league.php - Create New League
require_once '../config/database.php';
requireRole('admin');

if ($_POST) {
    $database = new Database();
    $db = $database->connect();
    
    $name = trim($_POST['name']);
    $sport_id = $_POST['sport_id'];
    $season = trim($_POST['season']);
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $registration_deadline = $_POST['registration_deadline'];
    $max_teams = $_POST['max_teams'];
    $rules = trim($_POST['rules']);
    $created_by = $_SESSION['user_id'];
    
    $query = "INSERT INTO leagues (name, sport_id, season, start_date, end_date, registration_deadline, max_teams, rules, created_by) 
              VALUES (:name, :sport_id, :season, :start_date, :end_date, :registration_deadline, :max_teams, :rules, :created_by)";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':name', $name);
    $stmt->bindParam(':sport_id', $sport_id);
    $stmt->bindParam(':season', $season);
    $stmt->bindParam(':start_date', $start_date);
    $stmt->bindParam(':end_date', $end_date);
    $stmt->bindParam(':registration_deadline', $registration_deadline);
    $stmt->bindParam(':max_teams', $max_teams);
    $stmt->bindParam(':rules', $rules);
    $stmt->bindParam(':created_by', $created_by);
    
    if ($stmt->execute()) {
        showMessage("League created successfully!", "success");
        redirect('manage_leagues.php');
    } else {
        $error = "Failed to create league!";
    }
}

// Get sports for dropdown
$database = new Database();
$db = $database->connect();
$sports_query = "SELECT * FROM sports ORDER BY name";
$sports_stmt = $db->prepare($sports_query);
$sports_stmt->execute();
$sports = $sports_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Create League - Sports League</title>
    <style>
        .container { max-width: 600px; margin: 50px auto; padding: 20px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; }
        input, select, textarea { width: 100%; padding: 8px; margin-bottom: 10px; }
        textarea { height: 100px; }
        .btn { background: #007bff; color: white; padding: 10px 20px; border: none; cursor: pointer; }
        .error { color: red; margin-bottom: 15px; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Create New League</h2>
        
        <?php displayMessage(); ?>
        <?php if (isset($error)) echo "<div class='error'>$error</div>"; ?>
        
        <form method="POST">
            <div class="form-group">
                <label>League Name:</label>
                <input type="text" name="name" required>
            </div>
            
            <div class="form-group">
                <label>Sport:</label>
                <select name="sport_id" required>
                    <option value="">Select Sport</option>
                    <?php foreach ($sports as $sport): ?>
                        <option value="<?php echo $sport['id']; ?>"><?php echo $sport['name']; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>Season:</label>
                <input type="text" name="season" placeholder="e.g., Spring 2024" required>
            </div>
            
            <div class="form-group">
                <label>Start Date:</label>
                <input type="date" name="start_date" required>
            </div>
            
            <div class="form-group">
                <label>End Date:</label>
                <input type="date" name="end_date" required>
            </div>
            
            <div class="form-group">
                <label>Registration Deadline:</label>
                <input type="date" name="registration_deadline" required>
            </div>
            
            <div class="form-group">
                <label>Maximum Teams:</label>
                <input type="number" name="max_teams" value="16" min="2" max="32">
            </div>
            
            <div class="form-group">
                <label>League Rules:</label>
                <textarea name="rules" placeholder="Enter league rules and regulations..."></textarea>
            </div>
            
            <button type="submit" class="btn">Create League</button>
            <a href="../admin/manage_leagues.php" class="btn" style="background: #6c757d; text-decoration: none;">Cancel</a>
        </form>
    </div>
</body>
</html>

<?php
// league/manage_leagues.php - Manage Leagues
if (!isset($_POST['name'])) {
    require_once '../config/database.php';
    requireRole('admin');
    
    $database = new Database();
    $db = $database->connect();
    
    // Get all leagues with sport information
    $query = "SELECT l.*, s.name as sport_name, 
                      (SELECT COUNT(*) FROM teams WHERE league_id = l.id) as team_count
              FROM leagues l 
              JOIN sports s ON l.sport_id = s.id 
              ORDER BY l.created_at DESC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $leagues = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Manage Leagues - Sports League</title>
    <style>
        .container { max-width: 1000px; margin: 50px auto; padding: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #f8f9fa; }
        .btn { background: #007bff; color: white; padding: 5px 10px; text-decoration: none; border-radius: 3px; margin-right: 5px; }
        .btn-success { background: #28a745; }
        .btn-warning { background: #ffc107; color: black; }
        .btn-danger { background: #dc3545; }
        .status { padding: 3px 8px; border-radius: 12px; color: white; font-size: 12px; }
        .status-draft { background: #6c757d; }
        .status-open { background: #28a745; }
        .status-active { background: #007bff; }
        .status-completed { background: #17a2b8; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Manage Leagues</h2>
        
        <?php displayMessage(); ?>
        
        <a href="create_league.php" class="btn">Create New League</a>
        
        <table>
            <thead>
                <tr>
                    <th>League Name</th>
                    <th>Sport</th>
                    <th>Season</th>
                    <th>Teams</th>
                    <th>Status</th>
                    <th>Registration Deadline</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($leagues as $league): ?>
                <tr>
                    <td><?php echo htmlspecialchars($league['name']); ?></td>
                    <td><?php echo htmlspecialchars($league['sport_name']); ?></td>
                    <td><?php echo htmlspecialchars($league['season']); ?></td>
                    <td><?php echo $league['team_count']; ?>/<?php echo $league['max_teams']; ?></td>
                    <td><span class="status status-<?php echo $league['status']; ?>"><?php echo ucfirst($league['status']); ?></span></td>
                    <td><?php echo date('M j, Y', strtotime($league['registration_deadline'])); ?></td>
                    <td>
                        <a href="view_league.php?id=<?php echo $league['id']; ?>" class="btn">View</a>
                        <a href="edit_league.php?id=<?php echo $league['id']; ?>" class="btn btn-warning">Edit</a>
                        <a href="schedule_matches.php?league_id=<?php echo $league['id']; ?>" class="btn btn-success">Schedule</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>

<?php
}
?>

<?php
// league/view_league.php - View League Details
if (isset($_GET['id']) && !isset($_POST['name'])) {
    require_once '../config/database.php';
    requireLogin();
    
    $league_id = $_GET['id'];
    $database = new Database();
    $db = $database->connect();
    
    // Get league details
    $query = "SELECT l.*, s.name as sport_name, u.first_name, u.last_name
              FROM leagues l 
              JOIN sports s ON l.sport_id = s.id 
              JOIN users u ON l.created_by = u.id
              WHERE l.id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $league_id);
    $stmt->execute();
    $league = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$league) {
        showMessage("League not found!", "error");
        redirect('manage_leagues.php');
    }
    
    // Get teams in this league
    $teams_query = "SELECT t.*, u.first_name, u.last_name, u.username
                    FROM teams t
                    JOIN users u ON t.owner_id = u.id
                    WHERE t.league_id = :league_id
                    ORDER BY t.points DESC, t.wins DESC";
    $teams_stmt = $db->prepare($teams_query);
    $teams_stmt->bindParam(':league_id', $league_id);
    $teams_stmt->execute();
    $teams = $teams_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get recent matches
    $matches_query = "SELECT m.*, ht.name as home_team, at.name as away_team, v.name as venue_name
                      FROM matches m
                      JOIN teams ht ON m.home_team_id = ht.id
                      JOIN teams at ON m.away_team_id = at.id
                      LEFT JOIN venues v ON m.venue_id = v.id
                      WHERE m.league_id = :league_id
                      ORDER BY m.match_date DESC
                      LIMIT 10";
    $matches_stmt = $db->prepare($matches_query);
    $matches_stmt->bindParam(':league_id', $league_id);
    $matches_stmt->execute();
    $matches = $matches_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?php echo htmlspecialchars($league['name']); ?> - Sports League</title>
    <style>
        .container { max-width: 1000px; margin: 50px auto; padding: 20px; }
        .league-header { background: #f8f9fa; padding: 20px; border-radius: 5px; margin-bottom: 20px; }
        .tabs { display: flex; margin-bottom: 20px; }
        .tab { padding: 10px 20px; background: #e9ecef; margin-right: 5px; cursor: pointer; }
        .tab.active { background: #007bff; color: white; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #f8f9fa; }
        .match-status { padding: 3px 8px; border-radius: 12px; color: white; font-size: 12px; }
        .match-scheduled { background: #6c757d; }
        .match-completed { background: #28a745; }
    </style>
    <script>
        function showTab(tabName) {
            // Hide all tab contents
            var contents = document.getElementsByClassName('tab-content');
            for (var i = 0; i < contents.length; i++) {
                contents[i].classList.remove('active');
            }
            
            // Remove active class from all tabs
            var tabs = document.getElementsByClassName('tab');
            for (var i = 0; i < tabs.length; i++) {
                tabs[i].classList.remove('active');
            }
            
            // Show selected tab content and mark tab as active
            document.getElementById(tabName).classList.add('active');
            event.target.classList.add('active');
        }
    </script>
</head>
<body>
    <div class="container">
        <div class="league-header">
            <h1><?php echo htmlspecialchars($league['name']); ?></h1>
            <p><strong>Sport:</strong> <?php echo htmlspecialchars($league['sport_name']); ?></p>
            <p><strong>Season:</strong> <?php echo htmlspecialchars($league['season']); ?></p>
            <p><strong>Duration:</strong> <?php echo date('M j, Y', strtotime($league['start_date'])); ?> - <?php echo date('M j, Y', strtotime($league['end_date'])); ?></p>
            <p><strong>Registration Deadline:</strong> <?php echo date('M j, Y', strtotime($league['registration_deadline'])); ?></p>
            <p><strong>Teams:</strong> <?php echo count($teams); ?>/<?php echo $league['max_teams']; ?></p>
        </div>
        
        <div class="tabs">
            <div class="tab active" onclick="showTab('standings')">Standings</div>
            <div class="tab" onclick="showTab('matches')">Matches</div>
            <div class="tab" onclick="showTab('teams')">Teams</div>
            <div class="tab" onclick="showTab('rules')">Rules</div>
        </div>
        
        <div id="standings" class="tab-content active">
            <h3>League Standings</h3>
            <table>
                <thead>
                    <tr>
                        <th>Pos</th>
                        <th>Team</th>
                        <th>Played</th>
                        <th>Won</th>
                        <th>Drawn</th>
                        <th>Lost</th>
                        <th>Points</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $position = 1; foreach ($teams as $team): ?>
                    <tr>
                        <td><?php echo $position++; ?></td>
                        <td><?php echo htmlspecialchars($team['name']); ?></td>
                        <td><?php echo $team['wins'] + $team['draws'] + $team['losses']; ?></td>
                        <td><?php echo $team['wins']; ?></td>
                        <td><?php echo $team['draws']; ?></td>
                        <td><?php echo $team['losses']; ?></td>
                        <td><strong><?php echo $team['points']; ?></strong></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div id="matches" class="tab-content">
            <h3>Recent Matches</h3>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Home Team</th>
                        <th>Score</th>
                        <th>Away Team</th>
                        <th>Venue</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($matches as $match): ?>
                    <tr>
                        <td><?php echo date('M j, Y', strtotime($match['match_date'])); ?></td>
                        <td><?php echo htmlspecialchars($match['home_team']); ?></td>
                        <td>
                            <?php if ($match['status'] == 'completed'): ?>
                                <?php echo $match['home_score']; ?> - <?php echo $match['away_score']; ?>
                            <?php else: ?>
                                vs
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($match['away_team']); ?></td>
                        <td><?php echo htmlspecialchars($match['venue_name'] ?? 'TBD'); ?></td>
                        <td><span class="match-status match-<?php echo $match['status']; ?>"><?php echo ucfirst($match['status']); ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div id="teams" class="tab-content">
            <h3>Teams</h3>
            <table>
                <thead>
                    <tr>
                        <th>Team Name</th>
                        <th>Owner</th>
                        <th>Record</th>
                        <th>Points</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($teams as $team): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($team['name']); ?></td>
                        <td><?php echo htmlspecialchars($team['first_name'] . ' ' . $team['last_name']); ?></td>
                        <td><?php echo $team['wins']; ?>W - <?php echo $team['draws']; ?>D - <?php echo $team['losses']; ?>L</td>
                        <td><?php echo $team['points']; ?></td>
                        <td><a href="view_team.php?id=<?php echo $team['id']; ?>" class="btn">View Team</a></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div id="rules" class="tab-content">
            <h3>League Rules</h3>
            <div style="background: #f8f9fa; padding: 20px; border-radius: 5px;">
                <?php if ($league['rules']): ?>
                    <?php echo nl2br(htmlspecialchars($league['rules'])); ?>
                <?php else: ?>
                    <p>No specific rules defined for this league.</p>
                <?php endif; ?>
            </div>
        </div>
        
        <p><a href="manage_leagues.php">← Back to Leagues</a></p>
    </div>
</body>
</html>

<?php
}
?>