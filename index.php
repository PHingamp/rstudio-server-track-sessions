<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>RStudio Server sessions</title>
    <meta http-equiv="refresh" content="60">
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        h1 { color: #333; }
        table { border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        tr:nth-child(even) { background-color: #f9f9f9; }
        .table-container {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
        }
        .subtable {
            width: 30%;
            border-collapse: collapse;
            margin: 0 10px;
        }
        .subtable th, .subtable td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        .subtable th {
            background-color: #f2f2f2;
        }
        .subtable tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .subtable-caption {
            text-align: center;
            font-weight: bold;
            margin-bottom: 5px;
        }
    </style>
</head>
<body>
    <h1>RStudio server</h1>
    <h2>Cumulative session durations</h2>
    <div class='table-container'>
<?php

// Database file path
$dbFile = '/path/to/user_sessions.db';

// Function to render a subtable
function renderSubtable($subtable) {
    echo "
        <div>
            <table class='subtable'>
                <thead>
                    <tr><th>User</th><th>Active</th><th>Hours</th><th>Sessions</th></tr>
                </thead>
                <tbody>";
    if ($subtable) {
        // display rows
        foreach ($subtable as $row) {
            echo "<tr><td>$row[user]</td><td style=\"text-align:center\">".
            ($row['is_active']?'✅':'').
            "</td><td>".($row['hrs'] + 1).
            "</td><td>$row[sessions]</td></tr>";
        }
    } else {
        echo "<tr><td colspan=4>No data found or table does not exist.</td></tr>";
    }
        echo "
                </tbody>
            </table>
        </div>";
}

// Connect to the SQLite database
try {
    $db = new SQLite3($dbFile);
    $db->exec("PRAGMA foreign_keys = ON"); // Optional: Enable foreign keys if needed

    // Query to fetch all data from the table
    $query = "SELECT user,minutes/60 as hrs,sessions, is_active FROM user_totals order by minutes DESC";
    $result = $db->query($query);
    // Fetch all rows into an array
    $rows = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $rows[] = $row;
    }
    $totalRows = count($rows);
    $rowsPerTable = ceil($totalRows / 3);
    $subtable1 = array_slice($rows, 0, $rowsPerTable);
    $subtable2 = array_slice($rows, $rowsPerTable, $rowsPerTable);
    $subtable3 = array_slice($rows, $rowsPerTable * 2);
    // Render the three subtables side by side
    renderSubtable($subtable1);
    renderSubtable($subtable2);
    renderSubtable($subtable3);
?>

    </div>
    <h2>Session history</h2>
    <table>
        <thead>
            <tr><th>User</th><th>Active</th><th>Duration (min)</th>
            <th>Time stamp</th><th>Start time</th><th>PID</th><th>CPU time</th>
            <th>CPU max %</th><th>RAM max %</th><th>Instance</th></tr>
        </thead>
        <tbody>
<?php
    // Query to fetch all data from the table
    $query = "SELECT * FROM user_sessions order by timestamp DESC limit 200";
    $result = $db->query($query);

    if ($result) {

        // Fetch and display rows
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            echo "<tr><td>$row[user]</td><td style=\"text-align:center\">".
            ($row['is_active']?'✅':'').
            "</td><td>$row[minutes]</td><td>$row[timestamp]</td><td>$row[start]</td>".
            "<td>$row[pid]</td><td>$row[time]</td><td>$row[cpu]</td><td>$row[mem]</td>".
            "<td>$row[hostname]</td></tr>";
        }
    } else {
        echo "<tr><td colspan=9>No data found or table does not exist.</td></tr>";
    }
    echo "</tbody>
    </table>";
    // Close the database connection
    $db->close();
} catch (Exception $e) {
    // Display error message if something goes wrong
    echo "</div><h1>Error</h1>
          <p>" . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
        
</body>
</html>



