<?php
// match/schedule_matches.php - Schedule Matches for League
require_once '../config/database.php';
requireRole('admin');

$league_id = $_GET['league_id'] ?? null;
if (!$league_id) {
    showMessage("League ID required!", "error");
    redirect('manage_leagues.php');
}

$database = new Database();
$db = $database->connect();

// Get league details
$league_query = "SELECT l.*, s.name as sport_name FROM leagues l 
                 JOIN sports s ON l.sport_id = s.id 
                 WHERE l.id = :id";
$league_stmt = $db->prepare($league_query);
$league_stmt->bindParam(':id', $league_id);
$league_stmt->execute();
$league = $league_stmt->fetch(PDO::FETCH_ASSOC);

if (!$league) {
    showMessage("League not found!", "error");
    redirect('manage_leagues.php');
}

// Get teams in league
$teams_query = "SELECT * FROM teams WHERE league_id = :league_id ORDER BY name";
$teams_stmt = $db->prepare($teams_query);
$teams_stmt->bindParam(':league_id', $league_id);
$teams_stmt->execute();
$teams = $teams_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get venues
$venues_query = "SELECT * FROM venues ORDER BY name";
$venues_stmt = $db->prepare($venues_query);
$venues_stmt->execute();
$venues = $venues_stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission for creating matches
if ($_POST && isset($_POST['create_schedule'])) {
    $start_date = $_POST['start_date'];
    $match_interval = $_POST['match_interval']; // days between matches
    $matches_per_day = $_POST['matches_per_day'];
    
    $team_count = count($teams);
    if ($team_count < 2) {
        $error = "Need at least 2 teams to create a schedule!";
    } else {
        // Create round-robin schedule
        $match_date = new DateTime($start_date);
        $match_counter = 0;
        
        // Generate all possible match combinations
        for ($i = 0; $i < $team_count; $i++) {
            for ($j = $i + 1; $j < $team_count; $j++) {
                $home_team = $teams[$i];
                $away_team = $teams[$j];
                
                // Assign venue (cycle through available venues)
                $venue = $venues[$match_counter % count($venues)];
                
                $insert_query = "INSERT INTO matches (league_id, home_team_id, away_team_id, venue_id, match_date) 
                                VALUES (:league_id, :home_team_id, :away_team_id, :venue_id, :match_date)";
                $insert_stmt = $db->prepare($insert_query);
                $insert_stmt->bindParam(':league_id', $league_id);
                $insert_stmt->bindParam(':home_team_id', $home_team['id']);
                $insert_stmt->bindParam(':away_team_id', $away_team['id']);
                $insert_stmt->bindParam(':venue_id', $venue['id']);
                $insert_stmt->bindParam(':match_date', $match_date->format('Y-m-d H:i:s'));
                $insert_stmt->execute();
                
                $match_counter++;
                
                // Move to next date if we've reached matches per day limit
                if ($match_counter % $matches_per_day == 0) {
                    $match_date->add(new DateInterval('P' . $match_interval . 'D'));
                }
            }
        }
        
        showMessage("Schedule created successfully! $match_counter matches scheduled.", "success");
        redirect('view_league.php?id=' . $league_id);
    }
}

// Get existing matches for this league
$matches_query = "SELECT m.*, ht.name as home_team, at.name as away_team, v.name as venue_name
                  FROM matches m
                  JOIN teams ht ON m.home_team_id = ht.id
                  JOIN teams at ON m.away_team_id = at.id
                  LEFT JOIN venues v ON m.venue_id = v.id
                  WHERE m.league_id = :league_id
                  ORDER BY m.match_date ASC";
$matches_stmt = $db->prepare($matches_query);
$matches_stmt->bindParam(':league_id', $league_id);
$matches_stmt->execute();
$existing_matches = $matches_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Schedule Matches - <?php echo htmlspecialchars($league['name']); ?></title>
    <style>
        .container { max-width: 1000px; margin: 50px auto; padding: 20px; }
        .form-section { background: #f8f9fa; padding: 20px; border-radius: 5px; margin-bottom: 20px; }
        .form-group { margin-bottom: 15px; display: inline-block; margin-right: 20px; }
        label { display: block; margin-bottom: 5px; }
        input, select { padding: 8px; }
        .btn { background: #007bff; color: white; padding: 10px 20px; border: none; cursor: pointer; text-decoration: none; border-radius: 3px; }
        .btn-danger { background: #dc3545; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #f8f9fa; }
        .error { color: red; margin-bottom: 15px; }
        .info-box { background: #d1ecf1; border: 1px solid #bee5eb; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Schedule Matches - <?php echo htmlspecialchars($league['name']); ?></h2>
        
        <?php displayMessage(); ?>
        <?php if (isset($error)) echo "<div class='error'>$error</div>"; ?>
        
        <div class="info-box">
            <p><strong>League:</strong> <?php echo htmlspecialchars($league['name']); ?> (<?php echo htmlspecialchars($league['sport_name']); ?>)</p>
            <p><strong>Teams:</strong> <?php echo count($teams); ?></p>
            <p><strong>Potential Matches:</strong> <?php echo count($teams) * (count($teams) - 1) / 2; ?> (round-robin)</p>
            <p><strong>Current Matches:</strong> <?php echo count($existing_matches); ?></p>
        </div>
        
        <?php if (count($teams) >= 2): ?>
        <div class="form-section">
            <h3>Create Round-Robin Schedule</h3>
            <form method="POST">
                <div class="form-group">
                    <label>Start Date:</label>
                    <input type="date" name="start_date" value="<?php echo date('Y-m-d', strtotime('+1 week')); ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Days Between Match Days:</label>
                    <select name="match_interval">
                        <option value="1">Daily</option>
                        <option value="3">Every 3 days</option>
                        <option value="7" selected>Weekly</option>
                        <option value="14">Bi-weekly</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Matches Per Day:</label>
                    <select name="matches_per_day">
                        <option value="1">1 match</option>
                        <option value="2" selected>2 matches</option>
                        <option value="3">3 matches</option>
                        <option value="4">4 matches</option>
                    </select>
                </div>
                
                <br>
                <button type="submit" name="create_schedule" class="btn">Generate Schedule</button>
            </form>
        </div>
        <?php else: ?>
        <div class="info-box" style="background: #f8d7da; border-color: #f5c6cb;">
            <p>Need at least 2 teams to create a schedule. Current teams: <?php echo count($teams); ?></p>
        </div>
        <?php endif; ?>
        
        <?php if (count($existing_matches) > 0): ?>
        <h3>Current Match Schedule</h3>
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Home Team</th>
                    <th>Away Team</th>
                    <th>Venue</th>
                    <th>Status</th>
                    <th>Result</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($existing_matches as $match): ?>
                <tr>
                    <td><?php echo date('M j, Y g:i A', strtotime($match['match_date'])); ?></td>
                    <td><?php echo htmlspecialchars($match['home_team']); ?></td>
                    <td><?php echo htmlspecialchars($match['away_team']); ?></td>
                    <td><?php echo htmlspecialchars($match['venue_name'] ?? 'TBD'); ?></td>
                    <td><?php echo ucfirst($match['status']); ?></td>
                    <td>
                        <?php if ($match['status'] == 'completed'): ?>
                            <?php echo $match['home_score']; ?> - <?php echo $match['away_score']; ?>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="edit_match.php?id=<?php echo $match['id']; ?>" class="btn">Edit</a>
                        <?php if ($match['status'] == 'scheduled'): ?>
                            <a href="record_result.php?id=<?php echo $match['id']; ?>" class="btn" style="background: #28a745;">Record Result</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
        
        <p><a href="view_league.php?id=<?php echo $league_id; ?>">← Back to League</a></p>
    </div>
</body>
</html>

<?php
// match/record_result.php - Record Match Results
if (isset($_GET['id']) && !isset($_POST['create_schedule'])) {
    $match_id = $_GET['id'];
    
    // Handle form submission
    if ($_POST && isset($_POST['record_result'])) {
        $home_score = $_POST['home_score'];
        $away_score = $_POST['away_score'];
        $notes = trim($_POST['notes']);
        
        $update_query = "UPDATE matches SET home_score = :home_score, away_score = :away_score, 
                         notes = :notes, status = 'completed' WHERE id = :id";
        $update_stmt = $db->prepare($update_query);
        $update_stmt->bindParam(':home_score', $home_score);
        $update_stmt->bindParam(':away_score', $away_score);
        $update_stmt->bindParam(':notes', $notes);
        $update_stmt->bindParam(':id', $match_id);
        
        if ($update_stmt->execute()) {
            // Update team standings
            $match_query = "SELECT * FROM matches WHERE id = :id";
            $match_stmt = $db->prepare($match_query);
            $match_stmt->bindParam(':id', $match_id);
            $match_stmt->execute();
            $match = $match_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($home_score > $away_score) {
                // Home team wins
                $db->exec("UPDATE teams SET wins = wins + 1, points = points + 3 WHERE id = " . $match['home_team_id']);
                $db->exec("UPDATE teams SET losses = losses + 1 WHERE id = " . $match['away_team_id']);
            } elseif ($away_score > $home_score) {
                // Away team wins
                $db->exec("UPDATE teams SET wins = wins + 1, points = points + 3 WHERE id = " . $match['away_team_id']);
                $db->exec("UPDATE teams SET losses = losses + 1 WHERE id = " . $match['home_team_id']);
            } else {
                // Draw
                $db->exec("UPDATE teams SET draws = draws + 1, points = points + 1 WHERE id IN (" . $match['home_team_id'] . ", " . $match['away_team_id'] . ")");
            }
            
            showMessage("Match result recorded successfully!", "success");
            redirect('view_league.php?id=' . $match['league_id']);
        } else {
            $error = "Failed to record result!";
        }
    }
    
    // Get match details
    $match_query = "SELECT m.*, ht.name as home_team, at.name as away_team, v.name as venue_name, l.name as league_name
                    FROM matches m
                    JOIN teams ht ON m.home_team_id = ht.id
                    JOIN teams at ON m.away_team_id = at.id
                    LEFT JOIN venues v ON m.venue_id = v.id
                    JOIN leagues l ON m.league_id = l.id
                    WHERE m.id = :id";
    $match_stmt = $db->prepare($match_query);
    $match_stmt->bindParam(':id', $match_id);
    $match_stmt->execute();
    $match = $match_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$match) {
        showMessage("Match not found!", "error");
        redirect('manage_leagues.php');
    }
?>

<!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Record Match Result - Sports League</title>
    <style>
        .container { max-width: 600px; margin: 50px auto; padding: 20px; }
        .match-info { background: #f8f9fa; padding: 20px; border-radius: 5px; margin-bottom: 20px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; }
        input, textarea { width: 100%; padding: 8px; }
        .score-inputs { display: flex; align-items: center; gap: 20px; }
        .score-inputs input { width: 80px; text-align: center; font-size: 18px; }
        .vs { font-size: 24px; font-weight: bold; }
        .btn { background: #007bff; color: white; padding: 10px 20px; border: none; cursor: pointer; text-decoration: none; border-radius: 3px; }
        .error { color: red; margin-bottom: 15px; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Record Match Result</h2>
        
        <?php displayMessage(); ?>
        <?php if (isset($error)) echo "<div class='error'>$error</div>"; ?>
        
        <div class="match-info">
            <h3><?php echo htmlspecialchars($match['league_name']); ?></h3>
            <p><strong>Date:</strong> <?php echo date('M j, Y g:i A', strtotime($match['match_date'])); ?></p>
            <p><strong>Venue:</strong> <?php echo htmlspecialchars($match['venue_name'] ?? 'TBD'); ?></p>
        </div>
        
        <form method="POST">
            <div class="form-group">
                <label>Final Score:</label>
                <div class="score-inputs">
                    <div style="text-align: center;">
                        <strong><?php echo htmlspecialchars($match['home_team']); ?></strong><br>
                        <input type="number" name="home_score" min="0" max="50" required>
                    </div>
                    <div class="vs">VS</div>
                    <div style="text-align: center;">
                        <strong><?php echo htmlspecialchars($match['away_team']); ?></strong><br>
                        <input type="number" name="away_score" min="0" max="50" required>
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label>Match Notes (Optional):</label>
                <textarea name="notes" rows="4" placeholder="Any additional notes about the match..."></textarea>
            </div>
            
            <button type="submit" name="record_result" class="btn">Record Result</button>
            <a href="view_league.php?id=<?php echo $match['league_id']; ?>" class="btn" style="background: #6c757d;">Cancel</a>
        </form>
    </div>
</body>
</html>

<?php
}
?> = $db->prepare($insert_query);
                $insert_stmt->bindParam(':league_id', $league_id);
                $insert_stmt->bindParam(':home_team_id', $home_team['id']);
                $insert_stmt->bindParam(':away_team_id', $away_team['id']);
                $insert_stmt