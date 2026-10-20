<?php
/**
 * CricketIQ - Batter Runs Predictor
 * Synchronized with data/IPL.csv
 */

// API Configuration
$api_url = "http://127.0.0.1:8000/predict";

// State variables
$prediction_result = null;
$error_message = null;
$api_offline = false;

// Load dataset (venues, opponents, players, benchmarks)
$dataset_file = __DIR__ . '/data/players.json';
$dataset_json = file_exists($dataset_file) ? file_get_contents($dataset_file) : '{"venues":[],"opponents":[],"players":[]}';
$dataset_obj = json_decode($dataset_json, true);

$venues = isset($dataset_obj['venues']) ? $dataset_obj['venues'] : [];
$opponents = isset($dataset_obj['opponents']) ? $dataset_obj['opponents'] : [];
$players_data = isset($dataset_obj['players']) ? $dataset_obj['players'] : [];
$venue_benchmarks = isset($dataset_obj['venue_benchmarks']) ? $dataset_obj['venue_benchmarks'] : [];
$opponent_benchmarks = isset($dataset_obj['opponent_benchmarks']) ? $dataset_obj['opponent_benchmarks'] : [];
$league_average = isset($dataset_obj['league_average']) ? (float)$dataset_obj['league_average'] : 26.78;
$dataset_count = count($players_data);

// Sticky Form Values
$form_data = [
    'player_name'         => isset($_POST['player_name']) ? trim($_POST['player_name']) : '',
    'selected_venue'      => isset($_POST['selected_venue']) ? trim($_POST['selected_venue']) : '',
    'selected_opponent'   => isset($_POST['selected_opponent']) ? trim($_POST['selected_opponent']) : '',
    'recent_average'      => isset($_POST['recent_average']) ? trim($_POST['recent_average']) : '',
    'recent_strike_rate'  => isset($_POST['recent_strike_rate']) ? trim($_POST['recent_strike_rate']) : '',
    'venue_average'       => isset($_POST['venue_average']) ? trim($_POST['venue_average']) : '',
    'opponent_average'    => isset($_POST['opponent_average']) ? trim($_POST['opponent_average']) : '',
    'bat_pos'             => isset($_POST['bat_pos']) ? trim($_POST['bat_pos']) : '',
    'innings'             => isset($_POST['innings']) ? trim($_POST['innings']) : '',
    'is_tailender'        => isset($_POST['is_tailender']) ? trim($_POST['is_tailender']) : ''
];

// When no player is selected, reset venue, opponent, and match setup to basic empty initial form
if (empty($form_data['player_name'])) {
    $form_data['selected_venue']     = '';
    $form_data['selected_opponent']  = '';
    $form_data['recent_average']     = '';
    $form_data['recent_strike_rate'] = '';
    $form_data['venue_average']      = '';
    $form_data['opponent_average']   = '';
    $form_data['bat_pos']            = '';
    $form_data['innings']            = '';
    $form_data['is_tailender']       = '';
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $validation_errors = [];

    // Server-side sync: if a player is selected, auto-populate all 4 metrics directly from the dataset
    if (!empty($form_data['player_name'])) {
        $player_found = false;
        foreach ($players_data as $p) {
            if ($p['name'] === $form_data['player_name'] || (isset($p['short_name']) && $p['short_name'] === $form_data['player_name'])) {
                $player_found = true;
                $form_data['player_name'] = $p['name'];
                $form_data['recent_average'] = (string)$p['recent_average'];
                $form_data['recent_strike_rate'] = (string)$p['recent_strike_rate'];
                if (!empty($form_data['selected_venue'])) {
                    $v = $form_data['selected_venue'];
                    if (isset($p['venues'][$v])) {
                        $form_data['venue_average'] = (string)$p['venues'][$v];
                    } elseif (isset($venue_benchmarks[$v])) {
                        $factor = $venue_benchmarks[$v] / max(1.0, $league_average);
                        $form_data['venue_average'] = (string)round($p['venue_average'] * $factor, 2);
                    } else {
                        $form_data['venue_average'] = (string)$p['venue_average'];
                    }
                } else {
                    $form_data['venue_average'] = (string)$p['venue_average'];
                }
                if (!empty($form_data['selected_opponent'])) {
                    $opp = $form_data['selected_opponent'];
                    if (!empty($p['invalid_opponents']) && in_array($opp, $p['invalid_opponents'])) {
                        $validation_errors[] = "Invalid Matchup: " . htmlspecialchars($p['name']) . " has played for " . htmlspecialchars($opp) . " and cannot play against them as an opponent. Please choose a different opponent.";
                    } elseif (isset($p['opponents'][$opp])) {
                        $form_data['opponent_average'] = (string)$p['opponents'][$opp];
                    } elseif (isset($opponent_benchmarks[$opp])) {
                        $factor = $opponent_benchmarks[$opp] / max(1.0, $league_average);
                        $form_data['opponent_average'] = (string)round($p['opponent_average'] * $factor, 2);
                    } else {
                        $form_data['opponent_average'] = (string)$p['opponent_average'];
                    }
                } else {
                    $form_data['opponent_average'] = (string)$p['opponent_average'];
                }
                break;
            }
        }
        if (!$player_found) {
            $validation_errors[] = "Selected player was not found in the dataset. Please search and select a valid player.";
        }
    } else {
        $validation_errors[] = "Please select a player to automatically calculate Recent Average and Strike Rate.";
    }

    if (empty($validation_errors)) {
        if ($form_data['recent_average'] === '' || !is_numeric($form_data['recent_average'])) {
            $validation_errors[] = "Please enter a valid Recent Average.";
        }
        if ($form_data['recent_strike_rate'] === '' || !is_numeric($form_data['recent_strike_rate'])) {
            $validation_errors[] = "Please enter a valid Recent Strike Rate.";
        }
        if ($form_data['venue_average'] === '' || !is_numeric($form_data['venue_average'])) {
            $validation_errors[] = "Please enter a valid Venue Average.";
        }
        if ($form_data['opponent_average'] === '' || !is_numeric($form_data['opponent_average'])) {
            $validation_errors[] = "Please enter a valid Opponent Average.";
        }
    }
    if ($form_data['bat_pos'] === '' || !filter_var($form_data['bat_pos'], FILTER_VALIDATE_INT, ["options" => ["min_range" => 1, "max_range" => 11]])) {
        $validation_errors[] = "Batting position must be between 1 and 11.";
    }
    if (!in_array($form_data['innings'], ['1', '2'])) {
        $validation_errors[] = "Please select an innings.";
    }
    if (!in_array($form_data['is_tailender'], ['0', '1'])) {
        $validation_errors[] = "Please select tailender status.";
    }

    if (empty($validation_errors)) {
        $payload = [
            'recent_average'     => (float) $form_data['recent_average'],
            'recent_strike_rate' => (float) $form_data['recent_strike_rate'],
            'venue_average'      => (float) $form_data['venue_average'],
            'opponent_average'   => (float) $form_data['opponent_average'],
            'bat_pos'            => (int) $form_data['bat_pos'],
            'innings'            => (int) $form_data['innings'],
            'is_tailender'       => (int) $form_data['is_tailender']
        ];

        $json_payload = json_encode($payload);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $api_url,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $json_payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Accept: application/json',
                'Content-Length: ' . strlen($json_payload)
            ],
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT        => 8
        ]);

        $raw_response = curl_exec($ch);
        $curl_errno   = curl_errno($ch);
        $curl_error   = curl_error($ch);
        $http_code    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($curl_errno !== 0) {
            $api_offline = true;
            $error_message = "Could not connect to the prediction server. Please make sure the Python server is running.";
        } elseif ($http_code >= 400) {
            $api_response_data = json_decode($raw_response, true);
            $detail = isset($api_response_data['detail']) ? $api_response_data['detail'] : 'Server error occurred.';
            $error_message = "Error: " . htmlspecialchars(is_array($detail) ? json_encode($detail) : $detail);
        } else {
            $api_response_data = json_decode($raw_response, true);
            if (json_last_error() === JSON_ERROR_NONE && isset($api_response_data['status']) && $api_response_data['status'] === 'success') {
                $raw_expected_runs = (int) $api_response_data['expected_runs'];
                $raw_probability   = (float) $api_response_data['half_century_probability_percent'];
                $exact_runs        = isset($api_response_data['exact_expected_runs']) ? (float) $api_response_data['exact_expected_runs'] : (float) $raw_expected_runs;

                // Direct output from trained ML models (Random Forest & XGBoost)
                $prediction_result = [
                    'status'                           => 'success',
                    'expected_runs'                    => $raw_expected_runs,
                    'exact_expected_runs'              => $exact_runs,
                    'half_century_probability_percent' => $raw_probability
                ];
            } else {
                $error_message = "Unexpected response from prediction server.";
            }
        }
    } else {
        $error_message = implode("<br>", $validation_errors);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CricketIQ - Batter Runs Predictor</title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@600;700;800&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: #0b0f19;
            color: #ffffff;
            min-height: 100vh;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            text-rendering: optimizeLegibility;
        }

        /* High-contrast solid typography */
        h1, h2, h3, h4, h5, h6, .brand-font {
            font-family: 'Outfit', sans-serif;
            color: #ffffff !important;
            font-weight: 700;
            text-shadow: none !important;
            -webkit-text-fill-color: initial !important;
        }

        p, span, label, small, div {
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        /* High-contrast solid text utilities */
        .text-muted {
            color: #cbd5e1 !important;
            opacity: 1 !important;
        }

        .text-light {
            color: #f8fafc !important;
        }

        .text-white {
            color: #ffffff !important;
        }

        .text-success {
            color: #34d399 !important; /* Bright Emerald */
        }

        .text-info {
            color: #38bdf8 !important; /* Bright Sky Blue */
        }

        .text-warning {
            color: #fbbf24 !important; /* Bright Amber */
        }

        .text-danger {
            color: #f87171 !important; /* Bright Coral Red */
        }

        .navbar-custom {
            background: #111827;
            border-bottom: 1px solid #1f2937;
        }

        .card-custom {
            background: #111827;
            border: 1px solid #1f2937;
            border-radius: 1.25rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
        }

        .card-header-styled {
            background: transparent;
            border-bottom: 1px solid #1f2937;
            padding: 1.25rem 1.5rem;
        }

        /* Section Headings - Uniform Solid Pure White with Emerald Accent */
        .section-header {
            color: #ffffff !important;
            font-size: 0.95rem;
            letter-spacing: 0.05em;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.45rem;
        }
        .section-header i {
            color: #10b981 !important;
        }

        /* Search Box */
        .search-wrapper {
            position: relative;
        }

        .search-input-group {
            background: #1e293b;
            border: 2px solid #334155;
            border-radius: 1rem;
            padding: 0.35rem 0.6rem;
            transition: all 0.2s ease;
        }
        .search-input-group:focus-within {
            border-color: #10b981;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.35);
        }

        .search-input-group input {
            background: transparent !important;
            border: none !important;
            color: #ffffff !important;
            box-shadow: none !important;
            font-size: 1.05rem;
            font-weight: 600;
            padding: 0.65rem 0.5rem;
        }
        .search-input-group input::placeholder {
            color: #94a3b8 !important;
            opacity: 1 !important;
        }

        .search-dropdown {
            position: absolute;
            top: calc(100% + 8px);
            left: 0;
            right: 0;
            background: #0f172a;
            border: 1px solid #334155;
            border-radius: 1rem;
            max-height: 320px;
            overflow-y: auto;
            z-index: 1050;
            box-shadow: 0 20px 45px rgba(0, 0, 0, 0.8);
            display: none;
        }

        .search-item {
            padding: 0.85rem 1.1rem;
            cursor: pointer;
            border-bottom: 1px solid #1e293b;
            display: flex;
            align-items: center;
            justify-content: space-between;
            color: #ffffff;
        }
        .search-item:last-child {
            border-bottom: none;
        }
        .search-item:hover, .search-item.active {
            background: #1e293b;
        }
        .search-item .text-muted {
            color: #cbd5e1 !important;
        }

        .player-badge-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background: #064e3b;
            border: 1px solid #10b981;
            border-radius: 2rem;
            color: #ffffff !important;
            font-size: 0.95rem;
            font-weight: 600;
        }

        .form-label {
            font-weight: 600;
            font-size: 0.95rem;
            color: #ffffff !important;
            margin-bottom: 0.45rem;
        }
        .form-label .text-muted, .label-subtext {
            color: #cbd5e1 !important;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .form-control, .form-select {
            background-color: #1e293b !important;
            border: 1.5px solid #475569 !important;
            color: #ffffff !important;
            border-radius: 0.75rem;
            padding: 0.75rem 1rem;
            font-size: 1.05rem;
            font-weight: 600;
        }

        .form-control:focus, .form-select:focus {
            background-color: #1e293b !important;
            border-color: #10b981 !important;
            color: #ffffff !important;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.3) !important;
        }

        .form-control::placeholder {
            color: #94a3b8 !important;
            opacity: 1 !important;
            font-weight: 400;
        }

        .form-select option {
            background-color: #1e293b;
            color: #ffffff;
        }

        .form-select option[value=""] {
            color: #94a3b8;
        }

        .input-highlight {
            border-color: #10b981 !important;
            box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.4) !important;
            background-color: #1e293b !important;
        }

        /* Readonly inputs: solid, high-contrast, pure white font */
        .form-control[readonly] {
            background-color: #0f172a !important;
            border: 1.5px solid #475569 !important;
            color: #ffffff !important;
            font-weight: 700 !important;
            font-size: 1.15rem !important;
        }

        .form-control[readonly]:focus {
            box-shadow: none !important;
            border-color: #64748b !important;
        }

        .input-hint-text {
            font-size: 0.88rem !important;
            font-weight: 500;
            color: #cbd5e1 !important;
            margin-top: 0.45rem;
            line-height: 1.4;
        }

        .preset-badge {
            cursor: pointer;
            font-size: 0.88rem;
            font-weight: 600;
            padding: 0.45rem 0.95rem;
            border-radius: 2rem;
            border: 1px solid #475569;
            background: #1e293b;
            color: #ffffff !important;
            transition: all 0.2s ease;
        }
        .preset-badge:hover {
            background: #059669;
            border-color: #10b981;
            color: #ffffff !important;
        }

        .btn-predict {
            background: #10b981 !important;
            color: #ffffff !important;
            font-weight: 700;
            font-size: 1.05rem;
            padding: 0.85rem 1.75rem;
            border-radius: 0.85rem;
            border: none;
            box-shadow: 0 6px 20px rgba(16, 185, 129, 0.35);
            transition: all 0.2s ease;
        }
        .btn-predict:hover {
            background: #059669 !important;
            color: #ffffff !important;
            transform: translateY(-1px);
        }

        /* Result Display Card */
        .result-hud {
            position: relative;
            background: #0f172a !important;
            border: 1px solid #10b981;
            border-radius: 1.5rem;
            overflow: hidden;
            box-shadow: 0 20px 45px rgba(0, 0, 0, 0.6);
        }

        .result-hud::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: #10b981;
        }

        .metric-score {
            font-size: 5rem;
            font-weight: 800;
            line-height: 1;
            color: #ffffff !important;
            text-shadow: none !important;
        }

        .progress-container {
            background: #1e293b;
            border: 1.5px solid #475569;
            border-radius: 1rem;
            padding: 4px;
        }

        .custom-progress-bar {
            height: 1.25rem;
            border-radius: 0.75rem;
            background: #10b981;
            transition: width 1s ease;
        }

        .stat-pill {
            background: #1e293b !important;
            border: 1px solid #334155 !important;
            border-radius: 0.85rem;
            padding: 1.15rem 1.35rem;
        }

        .stat-pill .small, #hudMatchOutlook {
            color: #f1f5f9 !important;
            font-size: 0.98rem !important;
            line-height: 1.6;
        }
    </style>
</head>
<body>

    <!-- Header -->
    <nav class="navbar navbar-custom py-3 sticky-top">
        <div class="container d-flex justify-content-between align-items-center">
            <a class="navbar-brand text-white d-flex align-items-center gap-2 m-0" href="index.php">
                <i class="bi bi-bullseye text-success fs-4"></i>
                <span class="brand-font fw-bold fs-4">Cricket<span class="text-success">IQ</span></span>
            </a>
            <span class="small fw-semibold text-white px-3 py-1 rounded-pill" style="background: #1e293b; border: 1px solid #475569;"><?= $dataset_count ?> Players Available</span>
        </div>
    </nav>

    <!-- Main Container -->
    <main class="container my-4 my-lg-5">
        
        <!-- Welcome Banner -->
        <div class="row mb-4">
            <div class="col-12 text-center text-lg-start d-flex flex-column flex-lg-row align-items-center justify-content-between gap-3">
                <div>
                    <h1 class="h3 fw-bold mb-1 text-white">Batter Runs Predictor</h1>
                    <p class="mb-0" style="color: #cbd5e1; font-size: 1rem; font-weight: 500;">Predict player performance for an upcoming match</p>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="text-white small fw-bold">Quick Pick:</span>
                    <div class="d-flex flex-wrap gap-1">
                        <button type="button" class="preset-badge" onclick="selectPlayerByName('MS Dhoni')">MS Dhoni</button>
                        <button type="button" class="preset-badge" onclick="selectPlayerByName('Virat Kohli')">Virat Kohli</button>
                        <button type="button" class="preset-badge" onclick="selectPlayerByName('Rohit Sharma')">Rohit Sharma</button>
                        <button type="button" class="preset-badge" onclick="selectPlayerByName('David Warner')">David Warner</button>
                        <button type="button" class="preset-badge" onclick="selectPlayerByName('KL Rahul')">KL Rahul</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Error Notifications -->
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger border-0 bg-danger bg-opacity-10 text-danger-emphasis d-flex align-items-center gap-2 p-3 rounded-3 mb-4" role="alert">
                <i class="bi bi-exclamation-circle-fill text-danger"></i>
                <div><?= $error_message ?></div>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            
            <!-- LEFT COLUMN: Search & Form -->
            <div class="col-lg-7">
                <div class="card card-custom h-100">
                    <div class="card-header card-header-styled">
                        <h5 class="mb-0 fw-bold text-white"><i class="bi bi-person-fill text-success me-2"></i>Player & Match Details</h5>
                    </div>

                    <div class="card-body p-4">
                        
                        <!-- PLAYER SEARCH -->
                        <div class="search-wrapper mb-4">
                            <label for="playerSearchInput" class="form-label">Search Player</label>
                            <div class="search-input-group d-flex align-items-center">
                                <span class="px-2 text-success"><i class="bi bi-search"></i></span>
                                <input type="text" id="playerSearchInput" class="form-control" 
                                       placeholder="Type player name (e.g. Dhoni, Kohli, Rohit, Warner...)" 
                                       value="<?= htmlspecialchars($form_data['player_name']) ?>"
                                       autocomplete="off">
                                <button type="button" class="btn btn-link text-white p-2 text-decoration-none" id="clearSearchBtn" onclick="clearPlayerSearch()" style="display: <?= !empty($form_data['player_name']) ? 'block' : 'none' ?>;">
                                    <i class="bi bi-x-circle-fill fs-5"></i>
                                </button>
                            </div>

                            <!-- Autocomplete Dropdown -->
                            <div class="search-dropdown" id="searchDropdown"></div>

                            <!-- Selected Player Tag -->
                            <div class="mt-2" id="activePlayerBadgeContainer" style="display: <?= !empty($form_data['player_name']) ? 'block' : 'none' ?>;">
                                <div class="player-badge-pill">
                                    <i class="bi bi-check-circle-fill text-success"></i>
                                    <span id="activePlayerBadgeText"><?= htmlspecialchars($form_data['player_name']) ?></span>
                                </div>
                            </div>
                        </div>

                        <hr class="border-secondary border-opacity-25 mb-4">

                        <form method="POST" action="index.php" id="predictionForm" class="needs-validation" novalidate>
                            
                            <input type="hidden" name="player_name" id="selectedPlayerName" value="<?= htmlspecialchars($form_data['player_name']) ?>">

                            <!-- 1. Recent Form -->
                            <div class="mb-4">
                                <h6 class="section-header text-uppercase mb-3">
                                    <i class="bi bi-activity"></i>1. Recent Form
                                </h6>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="recent_average" class="form-label d-flex justify-content-between align-items-center">
                                            <span>Recent Average</span>
                                            <span class="label-subtext">Last 10 Innings</span>
                                        </label>
                                        <input type="number" step="0.01" min="0" class="form-control" 
                                               id="recent_average" name="recent_average" 
                                               placeholder="Select a player" 
                                               value="<?= htmlspecialchars($form_data['recent_average']) ?>" readonly required>
                                    </div>

                                    <div class="col-md-6">
                                        <label for="recent_strike_rate" class="form-label d-flex justify-content-between align-items-center">
                                            <span>Recent Strike Rate</span>
                                            <span class="label-subtext">Last 10 Innings</span>
                                        </label>
                                        <input type="number" step="0.01" min="0" class="form-control" 
                                               id="recent_strike_rate" name="recent_strike_rate" 
                                               placeholder="Select a player" 
                                               value="<?= htmlspecialchars($form_data['recent_strike_rate']) ?>" readonly required>
                                    </div>
                                </div>
                            </div>

                            <!-- 2. Venue & Opponent Selection with Automatic Averages -->
                            <div class="mb-4">
                                <h6 class="section-header text-uppercase mb-3">
                                    <i class="bi bi-geo-alt-fill"></i>2. Venue & Opponent
                                </h6>
                                <div class="row g-3">
                                    
                                    <!-- Venue Select & Venue Average -->
                                    <div class="col-md-6">
                                        <label for="selected_venue" class="form-label">
                                            <span>Select Venue</span>
                                        </label>
                                        <select class="form-select mb-2" id="selected_venue" name="selected_venue" onchange="onVenueChange()">
                                            <option value="">All Venues (Career Overall Average)</option>
                                            <optgroup label="IPL Stadiums (India)">
                                                <?php foreach ($venues as $v): ?>
                                                    <?php if (strpos($v, '(UAE)') === false && strpos($v, '(IPL 2009)') === false): ?>
                                                        <option value="<?= htmlspecialchars($v) ?>" <?= ($form_data['selected_venue'] === $v) ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars($v) ?>
                                                        </option>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            </optgroup>
                                            <optgroup label="Overseas IPL Grounds (UAE & 2009)">
                                                <?php foreach ($venues as $v): ?>
                                                    <?php if (strpos($v, '(UAE)') !== false || strpos($v, '(IPL 2009)') !== false): ?>
                                                        <option value="<?= htmlspecialchars($v) ?>" <?= ($form_data['selected_venue'] === $v) ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars($v) ?>
                                                        </option>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            </optgroup>
                                        </select>

                                        <label for="venue_average" class="form-label">
                                            <span>Venue Batting Average</span>
                                        </label>
                                        <input type="number" step="0.01" min="0" class="form-control" 
                                               id="venue_average" name="venue_average" 
                                               placeholder="Awaiting player selection" 
                                               value="<?= htmlspecialchars($form_data['venue_average']) ?>" readonly required>
                                        <small class="input-hint-text d-block" id="venue_hint"></small>
                                    </div>

                                    <!-- Opponent Select & Opponent Average -->
                                    <div class="col-md-6">
                                        <label for="selected_opponent" class="form-label">
                                            <span>Select Opponent</span>
                                        </label>
                                        <select class="form-select mb-2" id="selected_opponent" name="selected_opponent" onchange="onOpponentChange()">
                                            <option value="">All Opponents (Career Overall Average)</option>
                                            <?php 
                                                 $cur_invalid = [];
                                                if (!empty($form_data['player_name'])) {
                                                    foreach ($players_data as $pl) {
                                                        if ($pl['name'] === $form_data['player_name'] || (isset($pl['short_name']) && $pl['short_name'] === $form_data['player_name'])) {
                                                            $cur_invalid = isset($pl['invalid_opponents']) ? $pl['invalid_opponents'] : [];
                                                            break;
                                                        }
                                                    }
                                                }
                                            ?>
                                            <?php foreach ($opponents as $opp): ?>
                                                <?php $is_opp_invalid = in_array($opp, $cur_invalid); ?>
                                                <option value="<?= htmlspecialchars($opp) ?>" 
                                                        data-original-label="<?= htmlspecialchars($opp) ?>"
                                                        <?= ($form_data['selected_opponent'] === $opp && !$is_opp_invalid) ? 'selected' : '' ?>
                                                        <?= $is_opp_invalid ? 'disabled' : '' ?>>
                                                    <?= htmlspecialchars($opp) . ($is_opp_invalid ? ' (Cannot face own team)' : '') ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>

                                        <label for="opponent_average" class="form-label">
                                            <span>Opponent Matchup Average</span>
                                        </label>
                                        <input type="number" step="0.01" min="0" class="form-control" 
                                               id="opponent_average" name="opponent_average" 
                                               placeholder="Awaiting player selection" 
                                               value="<?= htmlspecialchars($form_data['opponent_average']) ?>" readonly required>
                                        <small class="input-hint-text d-block" id="opponent_hint"></small>
                                    </div>

                                </div>
                            </div>

                            <!-- 3. Match Setup -->
                            <div class="mb-4">
                                <h6 class="section-header text-uppercase mb-3">
                                    <i class="bi bi-sliders"></i>3. Match Setup
                                </h6>
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label for="bat_pos" class="form-label">Batting Position</label>
                                        <select class="form-select" id="bat_pos" name="bat_pos" required>
                                            <option value="" disabled <?= ($form_data['bat_pos'] === '') ? 'selected' : '' ?>>Select position</option>
                                            <?php for ($i = 1; $i <= 11; $i++): ?>
                                                <option value="<?= $i ?>" <?= ($form_data['bat_pos'] !== '' && (int)$form_data['bat_pos'] === $i) ? 'selected' : '' ?>>
                                                    #<?= $i ?> <?= ($i <= 2 ? '(Opener)' : ($i <= 4 ? '(Top Order)' : ($i <= 7 ? '(Middle Order)' : '(Tailender)'))) ?>
                                                </option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>

                                    <div class="col-md-4">
                                        <label for="innings" class="form-label">Innings</label>
                                        <select class="form-select" id="innings" name="innings" required>
                                            <option value="" disabled <?= ($form_data['innings'] === '') ? 'selected' : '' ?>>Select innings</option>
                                            <option value="1" <?= ($form_data['innings'] === '1') ? 'selected' : '' ?>>1st Innings (Setting Target)</option>
                                            <option value="2" <?= ($form_data['innings'] === '2') ? 'selected' : '' ?>>2nd Innings (Chasing)</option>
                                        </select>
                                    </div>

                                    <div class="col-md-4">
                                        <label for="is_tailender" class="form-label">Tailender?</label>
                                        <select class="form-select" id="is_tailender" name="is_tailender" required>
                                            <option value="" disabled <?= ($form_data['is_tailender'] === '') ? 'selected' : '' ?>>Select option</option>
                                            <option value="0" <?= ($form_data['is_tailender'] === '0') ? 'selected' : '' ?>>No</option>
                                            <option value="1" <?= ($form_data['is_tailender'] === '1') ? 'selected' : '' ?>>Yes</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- Action Buttons -->
                            <div class="d-flex align-items-center gap-2 pt-2">
                                <button type="submit" class="btn btn-predict flex-grow-1">
                                    Predict Runs
                                </button>
                                <button type="button" class="btn px-3 py-2 rounded-3 text-white" style="background: #1e293b; border: 1.5px solid #475569;" onclick="resetForm()" title="Reset">
                                    <i class="bi bi-arrow-counterclockwise fs-5"></i>
                                </button>
                            </div>

                        </form>
                    </div>
                </div>
            </div>

            <!-- RIGHT COLUMN: Prediction Display -->
            <div class="col-lg-5">
                <!-- RESULT CARD -->
                <div id="hudResultCard" class="result-hud p-4 p-md-5 text-center h-100 d-flex flex-column justify-content-between">
                    <div>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="badge bg-success bg-opacity-25 text-success border border-success border-opacity-50 px-3 py-1 rounded-pill fw-semibold">
                                <i class="bi bi-graph-up me-1"></i>Match Prediction
                            </span>
                            <span class="text-white small fw-semibold" id="hudMatchSetup">
                                <?= (!empty($form_data['bat_pos']) && !empty($form_data['innings']))
                                    ? 'Position #' . htmlspecialchars($form_data['bat_pos']) . ' &bull; ' . ($form_data['innings'] == '1' ? '1st Innings' : '2nd Innings')
                                    : 'Awaiting match setup' ?>
                            </span>
                        </div>

                        <!-- Player & Matchup Header -->
                        <h4 class="fw-bold mb-2 text-white fs-3" id="hudPlayerName"><?= !empty($form_data['player_name']) ? htmlspecialchars($form_data['player_name']) : 'Batter' ?></h4>

                        <div class="mb-3 d-flex flex-wrap justify-content-center gap-2" id="hudMatchupBadges">
                            <span class="badge border border-secondary text-white small px-3 py-2" id="hudVenueBadge" style="background: #1e293b; display: <?= !empty($form_data['selected_venue']) ? 'inline-block' : 'none' ?>;">
                                <i class="bi bi-geo-alt text-success me-1"></i><span id="hudVenueBadgeText"><?= htmlspecialchars($form_data['selected_venue']) ?></span>
                            </span>
                            <span class="badge border border-secondary text-white small px-3 py-2" id="hudOpponentBadge" style="background: #1e293b; display: <?= !empty($form_data['selected_opponent']) ? 'inline-block' : 'none' ?>;">
                                <i class="bi bi-shield text-warning me-1"></i>vs <span id="hudOpponentBadgeText"><?= htmlspecialchars($form_data['selected_opponent']) ?></span>
                            </span>
                        </div>

                        <!-- Expected Runs -->
                        <div class="py-2">
                            <span class="text-white text-uppercase small fw-bold d-block mb-1" style="letter-spacing: 0.08em;">Expected Runs</span>
                            <div class="metric-score mb-1" id="hudExpectedRuns">
                                <?= $prediction_result !== null ? htmlspecialchars($prediction_result['expected_runs']) : '--' ?>
                            </div>
                            <span class="text-white small fw-medium" id="hudExactRunsSubtitle" style="color: #cbd5e1 !important;">
                                Estimated score for this match
                            </span>
                        </div>

                        <hr class="my-4 border-secondary border-opacity-25">

                        <!-- Half Century Probability -->
                        <?php 
                            $prob = $prediction_result !== null ? (float) $prediction_result['half_century_probability_percent'] : 0;
                            $prob_class = ($prediction_result !== null) ? (($prob >= 50) ? 'text-success' : (($prob >= 25) ? 'text-warning' : 'text-info')) : 'text-white';
                            $chance_text = ($prediction_result !== null) ? (($prob >= 50) ? 'High Chance' : (($prob >= 25) ? 'Moderate Chance' : 'Low Chance')) : 'Awaiting Selection';
                        ?>
                        <div class="text-start">
                            <div class="d-flex justify-content-between align-items-baseline mb-2">
                                <span class="text-white text-uppercase small fw-bold" style="letter-spacing: 0.05em;">Chance of Scoring 50+</span>
                                <span class="fs-4 fw-bold brand-font <?= $prob_class ?>" id="hudProbabilityPercent"><?= $prediction_result !== null ? number_format($prob, 1) . '%' : '--' ?></span>
                            </div>

                            <div class="progress-container mb-2">
                                <div class="custom-progress-bar" id="hudProgressBar" style="width: <?= $prediction_result !== null ? min(100, max(4, $prob)) : 0 ?>%;"></div>
                            </div>

                            <div class="d-flex justify-content-between align-items-center mt-2 small">
                                <span class="text-white fw-semibold">Likelihood:</span>
                                <span class="badge border border-secondary <?= $prob_class ?> fw-bold px-3 py-1" id="hudLikelihoodBadge" style="background: #1e293b;"><?= $chance_text ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Match Outlook Note -->
                    <div class="mt-4 pt-3 text-start">
                        <div class="stat-pill">
                            <span class="fw-bold small text-white d-block mb-1 fs-6">Match Outlook</span>
                            <p class="small mb-0" id="hudMatchOutlook" style="color: #f1f5f9 !important; font-size: 0.98rem; line-height: 1.6;">
                                <?php if ($prediction_result !== null && $prediction_result['expected_runs'] >= 50): ?>
                                    Strong chance of playing a match-winning innings based on this venue and opponent matchup.
                                <?php elseif ($prediction_result !== null && $prediction_result['expected_runs'] >= 25): ?>
                                    Good chance of a solid 25–40 run score in these conditions.
                                <?php elseif ($prediction_result !== null): ?>
                                    Expected to play a brief lower-order role or score few runs.
                                <?php else: ?>
                                    Select a player, venue, and opponent to view outlook.
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

        </div>

    </main>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        const DATASET = <?= $dataset_json ?>;
        const DATASET_PLAYERS = DATASET.players || [];
        const VENUE_BENCHMARKS = DATASET.venue_benchmarks || {};
        const OPPONENT_BENCHMARKS = DATASET.opponent_benchmarks || {};
        const LEAGUE_AVERAGE = parseFloat(DATASET.league_average) || 26.78;

        const searchInput = document.getElementById('playerSearchInput');
        const searchDropdown = document.getElementById('searchDropdown');
        const clearBtn = document.getElementById('clearSearchBtn');
        const selectedPlayerHidden = document.getElementById('selectedPlayerName');
        const badgeContainer = document.getElementById('activePlayerBadgeContainer');
        const badgeText = document.getElementById('activePlayerBadgeText');

        const venueSelect = document.getElementById('selected_venue');
        const venueAverageInput = document.getElementById('venue_average');
        const venueHint = document.getElementById('venue_hint');

        const opponentSelect = document.getElementById('selected_opponent');
        const opponentAverageInput = document.getElementById('opponent_average');
        const opponentHint = document.getElementById('opponent_hint');

        let currentSelectedPlayer = null;
        let currentFocus = -1;
        let livePredictDebounce = null;

        // Initialize from pre-filled sticky state if available
        window.addEventListener('DOMContentLoaded', () => {
            const currentName = selectedPlayerHidden.value.trim();
            if (currentName) {
                currentSelectedPlayer = DATASET_PLAYERS.find(p => p.name === currentName || p.short_name === currentName) || null;
                if (currentSelectedPlayer) {
                    updateOpponentDropdownOptions();
                    updateVenueAverage();
                    updateOpponentAverage();
                }
            } else {
                clearPlayerSearch();
            }
        });

        // Search Input Handler
        searchInput.addEventListener('input', function() {
            const query = this.value.trim().toLowerCase();
            clearBtn.style.display = query ? 'block' : 'none';

            if (!query) {
                searchDropdown.style.display = 'none';
                clearPlayerSearch();
                return;
            }

            const matches = DATASET_PLAYERS.filter(p => 
                p.name.toLowerCase().includes(query) || 
                (p.short_name && p.short_name.toLowerCase().includes(query))
            );

            if (matches.length === 0) {
                searchDropdown.innerHTML = `<div class="p-3 text-muted text-center small">No player found for "${this.value}".</div>`;
                searchDropdown.style.display = 'block';
                return;
            }

            let html = '';
            matches.slice(0, 8).forEach((p, index) => {
                html += `
                    <div class="search-item" onclick="applyPlayerStats('${p.name.replace(/'/g, "\\'")}')" data-index="${index}">
                        <div>
                            <div class="fw-bold text-white fs-6">${p.name}</div>
                            <small class="text-white" style="color: #cbd5e1 !important;">${p.total_runs.toLocaleString()} runs &bull; Pos #${p.bat_pos}</small>
                        </div>
                        <div class="text-end small">
                            <span class="badge border border-secondary text-white" style="background: #1e293b;">Avg: ${p.recent_average}</span>
                            <span class="badge border border-secondary text-white ms-1" style="background: #1e293b;">SR: ${p.recent_strike_rate}</span>
                        </div>
                    </div>
                `;
            });

            searchDropdown.innerHTML = html;
            searchDropdown.style.display = 'block';
            currentFocus = -1;
        });

        // Apply Player Selection
        function applyPlayerStats(playerName) {
            const player = DATASET_PLAYERS.find(p => p.name === playerName || p.short_name === playerName);
            if (!player) return;

            currentSelectedPlayer = player;

            searchInput.value = player.name;
            selectedPlayerHidden.value = player.name;
            clearBtn.style.display = 'block';
            searchDropdown.style.display = 'none';

            badgeText.textContent = `${player.name} (${player.total_runs.toLocaleString()} runs, Pos #${player.bat_pos})`;
            badgeContainer.style.display = 'block';

            // Reset venue and opponent selections to basic form for newly selected player
            venueSelect.value = '';
            opponentSelect.value = '';

            // Fill form basics
            document.getElementById('recent_average').value = player.recent_average;
            document.getElementById('recent_strike_rate').value = player.recent_strike_rate;
            document.getElementById('bat_pos').value = player.bat_pos;
            document.getElementById('innings').value = (player.innings !== undefined && player.innings !== null) ? player.innings : '1';
            document.getElementById('is_tailender').value = (player.is_tailender !== undefined && player.is_tailender !== null) ? player.is_tailender : (player.bat_pos >= 8 ? '1' : '0');

            // Update Opponent dropdown options to disable invalid opponents for this player
            updateOpponentDropdownOptions();

            // Update Venue and Opponent Averages with formal career baselines
            updateVenueAverage();
            updateOpponentAverage();

            // Highlight flash
            const fields = [
                document.getElementById('recent_average'),
                document.getElementById('recent_strike_rate'),
                venueAverageInput,
                opponentAverageInput,
                document.getElementById('bat_pos'),
                document.getElementById('innings'),
                document.getElementById('is_tailender')
            ];
            fields.forEach(el => {
                el.classList.add('input-highlight');
                setTimeout(() => el.classList.remove('input-highlight'), 600);
            });

            triggerLivePrediction();
        }

        // When Venue changes in dropdown
        function onVenueChange() {
            if (!currentSelectedPlayer) {
                venueSelect.value = '';
                venueAverageInput.value = '';
                venueHint.innerHTML = '';
                return;
            }
            updateVenueAverage();
            flashInput(venueAverageInput);
            triggerLivePrediction();
        }

        function updateVenueAverage() {
            if (!currentSelectedPlayer) {
                venueSelect.value = '';
                venueAverageInput.value = '';
                venueHint.innerHTML = '';
                return;
            }

            const selectedVenue = venueSelect.value.trim();

            if (!selectedVenue) {
                venueAverageInput.value = currentSelectedPlayer.venue_average;
                venueHint.innerHTML = `<span class="text-white"><i class="bi bi-check2-circle text-success me-1"></i>Career overall batting average across all venues: <strong class="text-white">${currentSelectedPlayer.venue_average}</strong></span>`;
                return;
            }

            // Check if player has personal matches at this venue in dataset
            if (currentSelectedPlayer.venues && currentSelectedPlayer.venues[selectedVenue] !== undefined) {
                const avg = currentSelectedPlayer.venues[selectedVenue];
                venueAverageInput.value = avg;
                venueHint.innerHTML = `<span class="text-white"><i class="bi bi-geo-alt-fill text-success me-1"></i>${currentSelectedPlayer.name}'s historical record at this venue: <strong class="text-white">${avg}</strong></span>`;
            } else {
                // Ground scoring conditions adjusted to player
                const groundAvg = VENUE_BENCHMARKS[selectedVenue] || LEAGUE_AVERAGE;
                const factor = groundAvg / LEAGUE_AVERAGE;
                const adjustedAvg = Math.round((currentSelectedPlayer.venue_average * factor) * 100) / 100;
                venueAverageInput.value = adjustedAvg;
                venueHint.innerHTML = `<span class="text-white"><i class="bi bi-geo-alt text-success me-1"></i>Adjusted venue benchmark average for this ground: <strong class="text-white">${adjustedAvg}</strong></span>`;
            }
        }

        function updateOpponentDropdownOptions() {
            const invalidList = (currentSelectedPlayer && currentSelectedPlayer.invalid_opponents) ? currentSelectedPlayer.invalid_opponents : [];
            const options = opponentSelect.querySelectorAll('option');

            options.forEach(opt => {
                if (!opt.value) return; // Keep placeholder
                const baseName = opt.getAttribute('data-original-label') || opt.value;
                opt.setAttribute('data-original-label', baseName);

                if (invalidList.includes(opt.value)) {
                    opt.disabled = true;
                    opt.textContent = `${baseName} (Cannot face own team)`;
                    // If this invalid opponent was selected, reset it!
                    if (opponentSelect.value === opt.value) {
                        opponentSelect.value = '';
                        opponentAverageInput.value = (currentSelectedPlayer ? currentSelectedPlayer.opponent_average : '');
                        opponentHint.innerHTML = `<span class="text-warning fw-bold"><i class="bi bi-exclamation-triangle-fill me-1"></i>${currentSelectedPlayer.name} has represented ${baseName} and cannot play against them. Opponent reset.</span>`;
                        const oppBadge = document.getElementById('hudOpponentBadge');
                        if (oppBadge) oppBadge.style.display = 'none';
                    }
                } else {
                    opt.disabled = false;
                    opt.textContent = baseName;
                }
            });
        }

        // When Opponent changes in dropdown
        function onOpponentChange() {
            if (!currentSelectedPlayer) {
                opponentSelect.value = '';
                opponentAverageInput.value = '';
                opponentHint.innerHTML = '';
                return;
            }

            const selectedOpponent = opponentSelect.value.trim();

            if (currentSelectedPlayer.invalid_opponents && currentSelectedPlayer.invalid_opponents.includes(selectedOpponent)) {
                opponentSelect.value = '';
                opponentAverageInput.value = currentSelectedPlayer.opponent_average;
                opponentHint.innerHTML = `<span class="text-danger fw-bold"><i class="bi bi-x-circle-fill me-1"></i>Invalid Matchup: ${currentSelectedPlayer.name} has represented ${selectedOpponent} and cannot be scheduled against their own team.</span>`;
                const oppBadge = document.getElementById('hudOpponentBadge');
                if (oppBadge) oppBadge.style.display = 'none';
                triggerLivePrediction();
                return;
            }

            updateOpponentAverage();
            flashInput(opponentAverageInput);
            triggerLivePrediction();
        }

        function updateOpponentAverage() {
            if (!currentSelectedPlayer) {
                opponentSelect.value = '';
                opponentAverageInput.value = '';
                opponentHint.innerHTML = '';
                return;
            }

            const selectedOpponent = opponentSelect.value.trim();

            if (!selectedOpponent) {
                opponentAverageInput.value = currentSelectedPlayer.opponent_average;
                opponentHint.innerHTML = `<span class="text-white"><i class="bi bi-check2-circle text-success me-1"></i>Career overall batting average against all opponents: <strong class="text-white">${currentSelectedPlayer.opponent_average}</strong></span>`;
                return;
            }

            // Check if player has personal matches against this opponent in dataset
            if (currentSelectedPlayer.opponents && currentSelectedPlayer.opponents[selectedOpponent] !== undefined) {
                const avg = currentSelectedPlayer.opponents[selectedOpponent];
                opponentAverageInput.value = avg;
                opponentHint.innerHTML = `<span class="text-white"><i class="bi bi-shield-fill text-success me-1"></i>${currentSelectedPlayer.name}'s historical record against ${selectedOpponent}: <strong class="text-white">${avg}</strong></span>`;
            } else {
                // Team bowling record adjusted to player
                const teamAvg = OPPONENT_BENCHMARKS[selectedOpponent] || LEAGUE_AVERAGE;
                const factor = teamAvg / LEAGUE_AVERAGE;
                const adjustedAvg = Math.round((currentSelectedPlayer.opponent_average * factor) * 100) / 100;
                opponentAverageInput.value = adjustedAvg;
                opponentHint.innerHTML = `<span class="text-white"><i class="bi bi-shield text-info me-1"></i>Adjusted resistance benchmark against ${selectedOpponent}: <strong class="text-white">${adjustedAvg}</strong></span>`;
            }
        }

        // Real-time live prediction
        function triggerLivePrediction() {
            clearTimeout(livePredictDebounce);
            livePredictDebounce = setTimeout(async () => {
                const recentAvg = parseFloat(document.getElementById('recent_average').value);
                const recentSR  = parseFloat(document.getElementById('recent_strike_rate').value);
                const venueAvg  = parseFloat(venueAverageInput.value);
                const oppAvg    = parseFloat(opponentAverageInput.value);
                const batPos    = parseInt(document.getElementById('bat_pos').value, 10);
                const innings   = parseInt(document.getElementById('innings').value, 10);
                const tailender = parseInt(document.getElementById('is_tailender').value, 10);

                if (isNaN(recentAvg) || isNaN(recentSR) || isNaN(venueAvg) || isNaN(oppAvg) || isNaN(batPos) || isNaN(innings) || isNaN(tailender)) {
                    return;
                }

                if (currentSelectedPlayer && currentSelectedPlayer.invalid_opponents && currentSelectedPlayer.invalid_opponents.includes(opponentSelect.value.trim())) {
                    return; // Prevent prediction on invalid matchup
                }

                const payload = {
                    recent_average: recentAvg,
                    recent_strike_rate: recentSR,
                    venue_average: venueAvg,
                    opponent_average: oppAvg,
                    bat_pos: batPos,
                    innings: innings,
                    is_tailender: tailender
                };

                try {
                    const response = await fetch('http://127.0.0.1:8000/predict', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify(payload)
                    });

                    if (response.ok) {
                        const data = await response.json();
                        if (data.status === 'success') {
                            renderPredictionHUD(data, payload);
                        }
                    }
                } catch (err) {
                    console.log('Prediction update error:', err);
                }
            }, 120);
        }

        // Render Prediction HUD dynamically
        function renderPredictionHUD(data, payload) {
            const playerName = selectedPlayerHidden.value.trim() || 'Batter';
            const venue = venueSelect.value.trim();
            const opponent = opponentSelect.value.trim();

            document.getElementById('hudPlayerName').textContent = playerName;
            document.getElementById('hudMatchSetup').textContent = `Position #${payload.bat_pos} • ${payload.innings === 1 ? '1st Innings' : '2nd Innings'}`;

            const venueBadge = document.getElementById('hudVenueBadge');
            const oppBadge = document.getElementById('hudOpponentBadge');
            if (venue) {
                document.getElementById('hudVenueBadgeText').textContent = venue;
                venueBadge.style.display = 'inline-block';
            } else {
                venueBadge.style.display = 'none';
            }
            if (opponent) {
                document.getElementById('hudOpponentBadgeText').textContent = opponent;
                oppBadge.style.display = 'inline-block';
            } else {
                oppBadge.style.display = 'none';
            }

            // Expected Runs
            const runsEl = document.getElementById('hudExpectedRuns');
            runsEl.textContent = data.expected_runs;

            const exactSubtitle = document.getElementById('hudExactRunsSubtitle');
            exactSubtitle.textContent = 'Estimated score for this match';

            // Probability Percentage (Direct Model Output)
            const prob = parseFloat(data.half_century_probability_percent);
            const probEl = document.getElementById('hudProbabilityPercent');
            probEl.textContent = `${prob.toFixed(1)}%`;
            probEl.className = `fs-4 fw-bold brand-font ${prob >= 50 ? 'text-success' : (prob >= 25 ? 'text-warning' : 'text-info')}`;

            const progressBar = document.getElementById('hudProgressBar');
            progressBar.style.width = `${Math.min(100, Math.max(4, prob))}%`;

            const likelihoodBadge = document.getElementById('hudLikelihoodBadge');
            const chanceText = prob >= 50 ? 'High' : (prob >= 25 ? 'Moderate' : 'Low');
            const chanceClass = prob >= 50 ? 'text-success' : (prob >= 25 ? 'text-warning' : 'text-info');
            likelihoodBadge.textContent = `${chanceText} Chance`;
            likelihoodBadge.className = `badge border border-secondary ${chanceClass} fw-bold px-3 py-1`;
            likelihoodBadge.style.background = '#1e293b';

            // Match Outlook
            const outlookEl = document.getElementById('hudMatchOutlook');
            if (data.expected_runs >= 50) {
                outlookEl.textContent = `Strong chance of playing a match-winning innings based on this venue and opponent matchup.`;
            } else if (data.expected_runs >= 25) {
                outlookEl.textContent = `Good chance of a solid 25–40 run score in these conditions.`;
            } else {
                outlookEl.textContent = `Expected to play a brief lower-order role or score few runs.`;
            }
        }

        // Attach live input listeners to match setup fields
        ['recent_average', 'recent_strike_rate', 'bat_pos', 'innings', 'is_tailender'].forEach(id => {
            const el = document.getElementById(id);
            if (el) {
                el.addEventListener('input', triggerLivePrediction);
                el.addEventListener('change', triggerLivePrediction);
            }
        });

        function flashInput(el) {
            el.classList.add('input-highlight');
            setTimeout(() => el.classList.remove('input-highlight'), 600);
        }

        function selectPlayerByName(playerName) {
            const player = DATASET_PLAYERS.find(p => p.name === playerName || p.short_name === playerName);
            if (player) {
                applyPlayerStats(player.name);
            }
        }

        function clearPlayerSearch() {
            currentSelectedPlayer = null;
            searchInput.value = '';
            selectedPlayerHidden.value = '';
            document.getElementById('recent_average').value = '';
            document.getElementById('recent_strike_rate').value = '';
            venueSelect.value = '';
            opponentSelect.value = '';
            venueAverageInput.value = '';
            opponentAverageInput.value = '';
            document.getElementById('bat_pos').value = '';
            document.getElementById('innings').value = '';
            document.getElementById('is_tailender').value = '';
            clearBtn.style.display = 'none';
            searchDropdown.style.display = 'none';
            badgeContainer.style.display = 'none';
            venueHint.innerHTML = '';
            opponentHint.innerHTML = '';
            updateOpponentDropdownOptions();

            // Reset HUD display fields
            const runsEl = document.getElementById('hudExpectedRuns');
            if (runsEl) runsEl.textContent = '--';
            const nameEl = document.getElementById('hudPlayerName');
            if (nameEl) nameEl.textContent = 'Batter';
            const setupEl = document.getElementById('hudMatchSetup');
            if (setupEl) setupEl.textContent = 'Awaiting match setup';
            const probEl = document.getElementById('hudProbabilityPercent');
            if (probEl) {
                probEl.textContent = '--';
                probEl.className = 'fs-4 fw-bold brand-font text-white';
            }
            const progEl = document.getElementById('hudProgressBar');
            if (progEl) progEl.style.width = '0%';
            const likeEl = document.getElementById('hudLikelihoodBadge');
            if (likeEl) {
                likeEl.textContent = 'Awaiting Selection';
                likeEl.className = 'badge border border-secondary text-white fw-bold px-3 py-1';
            }
            const outlookEl = document.getElementById('hudMatchOutlook');
            if (outlookEl) outlookEl.textContent = 'Select a player, venue, and opponent to view outlook.';
            const vBadge = document.getElementById('hudVenueBadge');
            const oBadge = document.getElementById('hudOpponentBadge');
            if (vBadge) vBadge.style.display = 'none';
            if (oBadge) oBadge.style.display = 'none';
        }

        document.addEventListener('click', function(e) {
            if (!e.target.closest('.search-wrapper')) {
                searchDropdown.style.display = 'none';
            }
        });

        searchInput.addEventListener('keydown', function(e) {
            const items = searchDropdown.getElementsByClassName('search-item');
            if (e.key === 'ArrowDown') {
                currentFocus++;
                addActive(items);
            } else if (e.key === 'ArrowUp') {
                currentFocus--;
                addActive(items);
            } else if (e.key === 'Enter') {
                if (currentFocus > -1 && items[currentFocus]) {
                    e.preventDefault();
                    items[currentFocus].click();
                }
            } else if (e.key === 'Escape') {
                searchDropdown.style.display = 'none';
            }
        });

        function addActive(items) {
            if (!items || items.length === 0) return;
            removeActive(items);
            if (currentFocus >= items.length) currentFocus = 0;
            if (currentFocus < 0) currentFocus = items.length - 1;
            items[currentFocus].classList.add('active');
            items[currentFocus].scrollIntoView({ block: 'nearest' });
        }

        function removeActive(items) {
            for (let i = 0; i < items.length; i++) {
                items[i].classList.remove('active');
            }
        }

        function resetForm() {
            clearPlayerSearch();
            document.getElementById('predictionForm').reset();
            document.getElementById('bat_pos').value = '';
            document.getElementById('innings').value = '';
            document.getElementById('is_tailender').value = '';
        }

        (() => {
            'use strict';
            const forms = document.querySelectorAll('.needs-validation');
            Array.from(forms).forEach(form => {
                form.addEventListener('submit', event => {
                    if (!form.checkValidity()) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                    form.classList.add('was-validated');
                }, false);
            });
        })();
    </script>
</body>
</html>
