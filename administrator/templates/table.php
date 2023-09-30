<?php
function generateDynamicTable($tableHeading, $tableData, $tableName) {
    echo '<div class="body">';
    echo '<div class="container">';
    echo '<h2>' . $tableName . '</h2>';
    echo '<div class="table-responsive">';
    echo '<table class="table table-striped table-bordered">';
    echo '<thead>';
    echo '<tr>';
    
    // Generate table headings dynamically from the $tableHeading array
    foreach ($tableHeading as $heading) {
        echo '<th>' . $heading . '</th>';
    }
    
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';
    
    $id = 1; // Initialize the ID as 1
    
    // Generate table rows and data dynamically from the $tableData array
    foreach ($tableData as $row) {
        echo '<tr>';
        
        // Output the incrementing ID
        echo '<td>' . $id++ . '</td>';
        
        foreach ($row as $data) {
            echo '<td>' . $data . '</td>';
        }
        echo '</tr>';
    }
    
    echo '</tbody>';
    echo '</table>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
}

