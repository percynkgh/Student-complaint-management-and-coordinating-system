<?php

require_once '../config/security.php';
require_once '../config/complaint_functions.php';

Security::requireLogin();
if (!Security::hasRole('student')) {
    header("Location: ../unauthorized.php");
    exit();
}

$cm = new ComplaintManager();
$db = (new Database())->getConnection();

$complaint_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$error = '';
$success = '';

// Check if user can rate this complaint
$can_rate = $cm->canRateComplaint($complaint_id, $_SESSION['user_id']);

if (!$can_rate['can_rate']) {
    if (isset($can_rate['message'])) {
        $error = $can_rate['message'];
    } else {
        header("Location: my_complaints.php");
        exit();
    }
}

$complaint = $can_rate['complaint'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $ratings = [
        'speed' => (int)$_POST['speed'],
        'communication' => (int)$_POST['communication'],
        'effectiveness' => (int)$_POST['effectiveness']
    ];
    
    $feedback = Security::sanitize($_POST['feedback'] ?? '');
    $is_public = isset($_POST['is_public']) ? true : false;
    
    $result = $cm->saveRating($complaint_id, $_SESSION['user_id'], $ratings, $feedback, $is_public);
    
    if ($result['success']) {
        $success = $result['message'];
        // Redirect after 2 seconds
        header("refresh:2;url=my_complaints.php");
    } else {
        $error = $result['message'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rate Complaint - #<?php echo htmlspecialchars($complaint['complaint_number']); ?></title>
    <link rel="stylesheet" href="css/Student.css">
    <style>
        .rating-value {
            font-size: 24px;
            font-weight: bold;
            color: #021A54;
        }
        .stars {
            display: flex;
            flex-direction: row-reverse;
            justify-content: flex-end;
            gap: 8px;
            margin-top: 10px;
        }
        .stars input {
            display: none;
        }
        .stars label {
            font-size: 35px;
            color: #ddd;
            cursor: pointer;
            transition: all 0.2s;
        }
        .stars label:hover,
        .stars label:hover ~ label,
        .stars input:checked ~ label {
            color: #ffd700;
        }
        .rating-description {
            margin-top: 10px;
            font-size: 13px;
            color: #666;
            padding: 8px;
            background: #f8f9fa;
            border-radius: 5px;
        }
        .rating-section {
            margin-bottom: 30px;
            padding: 15px;
            background: #f9f9f9;
            border-radius: 10px;
        }
        .rating-label {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
            flex-wrap: wrap;
            gap: 10px;
        }
        .rating-label h3 {
            margin: 0;
            color: #333;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="rating-card">
            <div class="header">
                <div>
                    <h1>Rate Your Experience</h1>
                    <small>Help us improve by rating how your complaint was handled</small>
                </div>
                <span class="role-badge">STUDENT</span>
            </div>
            
            <?php if ($error): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="success"><?php echo htmlspecialchars($success); ?></div>
                <p style="text-align:center; margin-top:20px;">Redirecting to your complaints...</p>
            <?php endif; ?>
            
            <div class="complaint-info">
                <span class="complaint-number">#<?php echo htmlspecialchars($complaint['complaint_number']); ?></span>
                <div class="complaint-title"><?php echo htmlspecialchars($complaint['title']); ?></div>
                <p>
                    Resolved on: <?php echo date('F j, Y', strtotime($complaint['resolved_at'])); ?>
                </p>
            </div>
            
            <?php if (!$success): ?>
                <form method="POST" id="ratingForm">
                    <!-- Speed Rating -->
                    <div class="rating-section">
                        <div class="rating-label">
                            <h3>Response Speed</h3>
                            <span class="rating-value" id="speedValue">3</span>
                        </div>
                        <div class="stars">
                            <input type="radio" name="speed" value="5" id="speed5">
                            <label for="speed5">★</label>
                            <input type="radio" name="speed" value="4" id="speed4">
                            <label for="speed4">★</label>
                            <input type="radio" name="speed" value="3" id="speed3" checked>
                            <label for="speed3">★</label>
                            <input type="radio" name="speed" value="2" id="speed2">
                            <label for="speed2">★</label>
                            <input type="radio" name="speed" value="1" id="speed1">
                            <label for="speed1">★</label>
                        </div>
                        <div class="rating-description" id="speedDesc">Good, resolved within expected time</div>
                    </div>
                    
                    <!-- Communication Rating -->
                    <div class="rating-section">
                        <div class="rating-label">
                            <h3>Communication Quality</h3>
                            <span class="rating-value" id="commValue">3</span>
                        </div>
                        <div class="stars">
                            <input type="radio" name="communication" value="5" id="comm5">
                            <label for="comm5">★</label>
                            <input type="radio" name="communication" value="4" id="comm4">
                            <label for="comm4">★</label>
                            <input type="radio" name="communication" value="3" id="comm3" checked>
                            <label for="comm3">★</label>
                            <input type="radio" name="communication" value="2" id="comm2">
                            <label for="comm2">★</label>
                            <input type="radio" name="communication" value="1" id="comm1">
                            <label for="comm1">★</label>
                        </div>
                        <div class="rating-description" id="commDesc">Clear updates and good communication</div>
                    </div>
                    
                    <!-- Effectiveness Rating -->
                    <div class="rating-section">
                        <div class="rating-label">
                            <h3>Solution Effectiveness</h3>
                            <span class="rating-value" id="effValue">3</span>
                        </div>
                        <div class="stars">
                            <input type="radio" name="effectiveness" value="5" id="eff5">
                            <label for="eff5">★</label>
                            <input type="radio" name="effectiveness" value="4" id="eff4">
                            <label for="eff4">★</label>
                            <input type="radio" name="effectiveness" value="3" id="eff3" checked>
                            <label for="eff3">★</label>
                            <input type="radio" name="effectiveness" value="2" id="eff2">
                            <label for="eff2">★</label>
                            <input type="radio" name="effectiveness" value="1" id="eff1">
                            <label for="eff1">★</label>
                        </div>
                        <div class="rating-description" id="effDesc">Issue was fully resolved</div>
                    </div>
                    
                    <!-- Feedback -->
                    <div class="form-group">
                        <label>Additional Feedback (Optional)</label>
                        <textarea name="feedback" placeholder="Tell us more about your experience..." rows="4"></textarea>
                    </div>
                    
                    <!-- Public Option -->
                    <div class="checkbox-group">
                        <input type="checkbox" name="is_public" id="is_public" checked>
                        <label for="is_public">Make my rating public (shown on transparency dashboard)</label>
                    </div>
                    
                    <button type="submit" class="btn">Submit Rating</button>
                </form>
                
                <a href="my_complaints.php" class="back-link">← Skip for now</a>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // Rating descriptions based on value
        const speedDescriptions = {
            1: "Very slow, took much longer than expected",
            2: "Slow, delayed response times",
            3: "Good, resolved within expected time",
            4: "Fast, quicker than expected",
            5: "Very fast, excellent response time"
        };
        
        const commDescriptions = {
            1: "Poor communication, no updates",
            2: "Minimal updates, hard to track",
            3: "Clear updates and good communication",
            4: "Very good communication, regular updates",
            5: "Excellent communication, always informed"
        };
        
        const effDescriptions = {
            1: "Not resolved at all",
            2: "Partially resolved, still issues remain",
            3: "Issue was fully resolved",
            4: "Well resolved, very satisfied",
            5: "Perfectly resolved, exceeded expectations"
        };
        
        // Function to update rating value and description
        function updateRatingValue(category, value) {
            const valueSpan = document.getElementById(`${category}Value`);
            const descSpan = document.getElementById(`${category}Desc`);
            
            if (valueSpan) {
                valueSpan.textContent = value;
            }
            
            if (descSpan) {
                if (category === 'speed') {
                    descSpan.textContent = speedDescriptions[value] || speedDescriptions[3];
                } else if (category === 'comm') {
                    descSpan.textContent = commDescriptions[value] || commDescriptions[3];
                } else if (category === 'eff') {
                    descSpan.textContent = effDescriptions[value] || effDescriptions[3];
                }
            }
        }
        
        // Set up event listeners for all radio buttons
        document.querySelectorAll('input[name="speed"]').forEach(radio => {
            radio.addEventListener('change', function() {
                updateRatingValue('speed', this.value);
            });
        });
        
        document.querySelectorAll('input[name="communication"]').forEach(radio => {
            radio.addEventListener('change', function() {
                updateRatingValue('comm', this.value);
            });
        });
        
        document.querySelectorAll('input[name="effectiveness"]').forEach(radio => {
            radio.addEventListener('change', function() {
                updateRatingValue('eff', this.value);
            });
        });
        
        // Initialize descriptions on page load
        document.addEventListener('DOMContentLoaded', function() {
            updateRatingValue('speed', 3);
            updateRatingValue('comm', 3);
            updateRatingValue('eff', 3);
        });
    </script>
</body>
</html>