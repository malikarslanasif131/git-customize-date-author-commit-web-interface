<?php
/**
 * Git Commit Web Interface
 * A simple web-based UI for creating Git commits with custom dates and times
 */

// Configuration - adjust Git path and user details as needed
$gitPath = 'C:\\Program Files\\Git\\bin\\git.exe'; // Windows path
// For Linux/Mac, use: $gitPath = 'git';

// Git user configuration (set these to your details)
$gitUserName = 'malikarslanasif';
$gitUserEmail = 'malikarslanasif131@gmail.com';

// Initialize variables
$message = '';
$commitDate = '';
$commitTime = '';
$userNameInput = '';
$userEmailInput = '';
$success = '';
$error = '';

// Check if Git is available
function checkGitInstallation($gitPath)
{
    $gitCheck = shell_exec("\"$gitPath\" --version 2>&1");
    return !empty($gitCheck) && strpos(strtolower($gitCheck), 'git version') !== false;
}

// Validate date format
function validateDate($date)
{
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

// Validate time format
function validateTime($time)
{
    $t = DateTime::createFromFormat('H:i', $time);
    return $t && $t->format('H:i') === $time;
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    $message = trim($_POST['commit_message'] ?? '');
    $commitDate = trim($_POST['commit_date'] ?? '');
    $commitTime = trim($_POST['commit_time'] ?? '00:00');
    $userNameInput = trim($_POST['user_name'] ?? '');
    $userEmailInput = trim($_POST['user_email'] ?? '');

    // Server-side validation
    if (empty($message)) {
        $error = 'Commit message is required.';
    } elseif (empty($commitDate)) {
        $error = 'Commit date is required.';
    } elseif (!validateDate($commitDate)) {
        $error = 'Invalid date format. Please use YYYY-MM-DD format.';
    } elseif (!validateTime($commitTime)) {
        $error = 'Invalid time format. Please use HH:MM format.';
    } elseif (empty($userNameInput)) {
        $error = 'Git user name is required.';
    } elseif (empty($userEmailInput)) {
        $error = 'Git user email is required.';
    } elseif (!filter_var($userEmailInput, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        // Check if Git is installed
        if (!checkGitInstallation($gitPath)) {
            $error = 'Git is not installed or not found at the specified path.';
        } else {
            // Check if we're in a Git repository
            $gitCheck = shell_exec("\"$gitPath\" rev-parse --git-dir 2>&1");
            if (strpos($gitCheck, 'fatal: not a git repository') !== false) {
                $error = 'This directory is not a Git repository. Please run "git init" first.';
            } else {
                try {
                    // Format the date and time for Git
                    $dateTime = DateTime::createFromFormat('Y-m-d H:i', $commitDate . ' ' . $commitTime);
                    $gitDateFormat = $dateTime->format('Y-m-d\TH:i:s');

                    // Escape user inputs for shell execution
                    $escapedMessage = escapeshellarg($message);
                    $escapedDate = escapeshellarg($gitDateFormat);

                    // Stage all changes
                    $addOutput = shell_exec("\"$gitPath\" add . 2>&1");

                    // Check if there are changes to commit
                    $statusOutput = shell_exec("\"$gitPath\" status --porcelain 2>&1");
                    if (empty(trim($statusOutput))) {
                        // No changes detected, append to commit log file
                        $commitFileName = 'commit_log.txt';
                        $logEntry = "\n" . str_repeat("=", 50) . "\n";
                        $logEntry .= "Commit created on: " . $dateTime->format('Y-m-d H:i:s') . "\n";
                        $logEntry .= "Commit message: " . $message . "\n";
                        $logEntry .= "Author: " . $userNameInput . " <" . $userEmailInput . ">\n";
                        $logEntry .= "Timestamp: " . time() . " (" . date('Y-m-d H:i:s T') . ")\n";
                        $logEntry .= str_repeat("=", 50) . "\n";

                        // Append to the log file (create if doesn't exist)
                        if (file_put_contents($commitFileName, $logEntry, FILE_APPEND | LOCK_EX) === false) {
                            $error = 'Failed to write to commit log file. Please check directory permissions.';
                        } else {
                            // Stage the commit log file
                            $addOutput = shell_exec("\"$gitPath\" add \"$commitFileName\" 2>&1");

                            // Verify the file was staged
                            $statusOutput = shell_exec("\"$gitPath\" status --porcelain 2>&1");
                            if (empty(trim($statusOutput))) {
                                $error = 'Failed to stage the commit log file.';
                            } else {
                                // File was updated and staged successfully, proceed with commit
                                $success .= "Updated commit log file: $commitFileName. ";
                            }
                        }
                    }

                    // Proceed with commit if we have changes (either existing or newly created)
                    if (empty($error)) {
                        // Use user-provided Git identity
                        $escapedUserName = escapeshellarg($userNameInput);
                        $escapedUserEmail = escapeshellarg($userEmailInput);

                        // Set environment variables for Git date and user identity
                        $envVars = "SET GIT_AUTHOR_DATE=$escapedDate && SET GIT_COMMITTER_DATE=$escapedDate && ";
                        $envVars .= "SET GIT_AUTHOR_NAME=$escapedUserName && SET GIT_AUTHOR_EMAIL=$escapedUserEmail && ";
                        $envVars .= "SET GIT_COMMITTER_NAME=$escapedUserName && SET GIT_COMMITTER_EMAIL=$escapedUserEmail && ";
                        // For Linux/Mac use: 
                        // $envVars = "GIT_AUTHOR_DATE=$escapedDate GIT_COMMITTER_DATE=$escapedDate ";
                        // $envVars .= "GIT_AUTHOR_NAME=$escapedUserName GIT_AUTHOR_EMAIL=$escapedUserEmail ";
                        // $envVars .= "GIT_COMMITTER_NAME=$escapedUserName GIT_COMMITTER_EMAIL=$escapedUserEmail ";

                        // Perform the commit
                        $commitCommand = $envVars . "\"$gitPath\" commit -m $escapedMessage 2>&1";
                        $commitOutput = shell_exec($commitCommand);

                        if ($commitOutput && strpos($commitOutput, 'error') === false && strpos($commitOutput, 'fatal') === false) {
                            $success .= 'Commit successful! ' . trim($commitOutput);
                            // Clear form fields after successful commit
                            $message = '';
                            $commitDate = '';
                            $commitTime = '';
                            $userNameInput = '';
                            $userEmailInput = '';
                        } else {
                            $error = 'Commit failed: ' . ($commitOutput ?: 'Unknown error occurred.');
                        }
                    }
                } catch (Exception $e) {
                    $error = 'Error processing commit: ' . $e->getMessage();
                }
            }
        }
    }
}

// Get current date and time for default values
$currentDate = date('Y-m-d');
$currentTime = date('H:i');
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Git Commit Interface</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 600px;
            margin: 50px auto;
            padding: 20px;
            background-color: #f5f5f5;
        }

        .container {
            background-color: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        h1 {
            color: #333;
            text-align: center;
            margin-bottom: 30px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #555;
        }

        input[type="text"],
        input[type="date"],
        input[type="time"],
        textarea {
            width: 100%;
            padding: 10px;
            border: 2px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            box-sizing: border-box;
        }

        textarea {
            height: 80px;
            resize: vertical;
        }

        input:focus,
        textarea:focus {
            outline: none;
            border-color: #4CAF50;
        }

        .date-time-group {
            display: flex;
            gap: 15px;
        }

        .date-time-group .form-group {
            flex: 1;
        }

        .submit-btn {
            background-color: #4CAF50;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            width: 100%;
        }

        .submit-btn:hover {
            background-color: #45a049;
        }

        .success {
            background-color: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            border: 1px solid #c3e6cb;
        }

        .error {
            background-color: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
        }

        .info {
            background-color: #d1ecf1;
            color: #0c5460;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            border: 1px solid #bee5eb;
            font-size: 14px;
        }

        .required {
            color: #dc3545;
        }

        @media (max-width: 480px) {
            .date-time-group {
                flex-direction: column;
                gap: 0;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <h1>🚀 Git Commit Interface</h1>

        <div class="info">
            <strong>Instructions:</strong> This interface allows you to create Git commits with custom dates and times.
            If no changes are detected, entries will be automatically appended to 'commit_log.txt'.
        </div>

        <?php if ($success): ?>
            <div class="success">
                <strong>Success!</strong> <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="error">
                <strong>Error!</strong> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label for="commit_message">
                    Commit Message <span class="required">*</span>
                </label>
                <textarea id="commit_message" name="commit_message" placeholder="Enter your commit message..."
                    required><?php echo htmlspecialchars($message); ?></textarea>
            </div>

            <div class="date-time-group">
                <div class="form-group">
                    <label for="user_name">
                        Git User Name <span class="required">*</span>
                    </label>
                    <input type="text" id="user_name" name="user_name" placeholder="e.g., masif5"
                        value="<?php echo htmlspecialchars($userNameInput ?: $gitUserName); ?>" required>
                </div>

                <div class="form-group">
                    <label for="user_email">
                        Git User Email <span class="required">*</span>
                    </label>
                    <input type="email" id="user_email" name="user_email"
                        placeholder="e.g., masif@azlaantechnologies.com"
                        value="<?php echo htmlspecialchars($userEmailInput ?: $gitUserEmail); ?>" required>
                </div>
            </div>

            <div class="date-time-group">
                <div class="form-group">
                    <label for="commit_date">
                        Commit Date <span class="required">*</span>
                    </label>
                    <input type="date" id="commit_date" name="commit_date"
                        value="<?php echo htmlspecialchars($commitDate ?: $currentDate); ?>" required>
                </div>

                <div class="form-group">
                    <label for="commit_time">
                        Commit Time <span class="required">*</span>
                    </label>
                    <input type="time" id="commit_time" name="commit_time"
                        value="<?php echo htmlspecialchars($commitTime ?: $currentTime); ?>" required>
                </div>
            </div>

            <div class="form-group">
                <button type="submit" name="submit" class="submit-btn">
                    📝 Create Commit
                </button>
            </div>
        </form>

        <div class="info">
            <strong>Current Directory:</strong> <?php echo htmlspecialchars(getcwd()); ?><br>
            <strong>Git Status:</strong>
            <?php
            if (checkGitInstallation($gitPath)) {
                $gitStatus = shell_exec("\"$gitPath\" rev-parse --git-dir 2>&1");
                if (strpos($gitStatus, 'fatal') === false) {
                    echo '✅ Git repository detected';
                } else {
                    echo '❌ Not a Git repository';
                }
            } else {
                echo '❌ Git not found';
            }
            ?>
        </div>
    </div>
</body>

</html>