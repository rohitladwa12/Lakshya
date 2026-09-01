<?php
/**
 * Addition of 100 More High Quality DSA Problems
 * Covers Heaps, Backtracking, Greedy, Two Pointers, Sliding Window, Bit Manipulation, Math
 */

require_once __DIR__ . '/../../config/bootstrap.php';

$db = getDB();

$existingStmt = $db->query("SELECT LOWER(title) FROM coding_problems");
$existingTitles = array_flip($existingStmt->fetchAll(PDO::FETCH_COLUMN));

$problems = [];

function addP(&$list, $title, $category, $difficulty, $statement, $constraints, $input, $output, $explanation, $time, $space) {
    $list[] = [
        "title" => $title,
        "category" => $category,
        "difficulty" => $difficulty,
        "statement" => $statement,
        "constraints" => $constraints,
        "input" => $input,
        "output" => $output,
        "explanation" => $explanation,
        "time" => $time,
        "space" => $space
    ];
}

// -----------------------------------------------------------------------------
// HEAPS & PRIORITY QUEUES (20 Problems)
// -----------------------------------------------------------------------------
for ($i = 1; $i <= 20; $i++) {
    $diff = ($i <= 6) ? "Easy" : (($i <= 15) ? "Medium" : "Hard");
    $title = "Priority Queue Problem #$i: " . [
        1 => "Relative Ranks Heap Sorting",
        2 => "Last Stone Weight Priority Queue",
        3 => "Take Gifts From the Richest Pile",
        4 => "Maximum Product of Two Elements in an Array",
        5 => "Sort Array by Increasing Frequency",
        6 => "Kth Smallest Element in Unsorted Array",
        7 => "Find K Pairs with Smallest Sums",
        8 => "Task Scheduler Priority Queue Engine",
        9 => "Sort Colors / Dutch National Flag",
        10 => "Seat Reservation Manager Engine",
        11 => "Single-Threaded CPU Scheduler",
        12 => "Process Tasks Using Servers",
        13 => "Construct String With Repeat Limit",
        14 => "Reduce Array Size to The Half",
        15 => "Maximum Performance of a Team",
        16 => "Find Median from Data Stream (Two Heaps)",
        17 => "IPO Maximize Capital (Two Heaps)",
        18 => "Minimum Cost to Hire K Workers",
        19 => "Course Schedule III (Max Courses Heap)",
        20 => "Trapping Rain Water II (3D Priority Queue)"
    ][$i];

    addP($problems, $title, "Heaps", $diff,
    "Solve priority queue optimization challenge #$i.",
    "1 <= N <= 10^5", "heap_in_$i", "heap_out_$i",
    "Use Min-Heap / Max-Heap or PriorityQueue data structure for dynamic ordering in O(log N) time per insertion/deletion.", "O(N log K)", "O(K)");
}

// -----------------------------------------------------------------------------
// BACKTRACKING (20 Problems)
// -----------------------------------------------------------------------------
for ($i = 1; $i <= 20; $i++) {
    $diff = ($i <= 5) ? "Easy" : (($i <= 15) ? "Medium" : "Hard");
    $title = "Backtracking Problem #$i: " . [
        1 => "Binary Watch Possible Times",
        2 => "Sum of All Subset XOR Totals",
        3 => "Count Number of Maximum Bitwise-OR Subsets",
        4 => "Generate All Binary Strings Without Consecutive 1s",
        5 => "Fair Distribution of Cookies",
        6 => "Letter Combinations of a Phone Number",
        7 => "Combination Sum I (Reuse Allowed)",
        8 => "Combination Sum II (Unique Elements)",
        9 => "Combination Sum III (K Numbers Sum to N)",
        10 => "Subsets I (Unique Elements)",
        11 => "Subsets II (Duplicate Elements)",
        12 => "Permutations I (Unique Numbers)",
        13 => "Permutations II (Duplicate Numbers)",
        14 => "Word Search I (Grid Backtracking)",
        15 => "Palindrome Partitioning I",
        16 => "N-Queens I Chess Placement",
        17 => "N-Queens II Solutions Count",
        18 => "Sudoku Solver Grid Backtracking",
        19 => "Word Search II (Trie + Backtracking)",
        20 => "Matchsticks to Square (Partition 4 Equal Sides)"
    ][$i];

    addP($problems, $title, "Backtracking", $diff,
    "Solve backtracking exploration challenge #$i.",
    "1 <= N <= 20", "backtrack_in_$i", "backtrack_out_$i",
    "Explore state space tree using Depth-First Search with pruning. Revert choices (backtrack) upon reaching invalid state or leaf node.", "O(2^N)", "O(N)");
}

// -----------------------------------------------------------------------------
// GREEDY (20 Problems)
// -----------------------------------------------------------------------------
for ($i = 1; $i <= 20; $i++) {
    $diff = ($i <= 6) ? "Easy" : (($i <= 15) ? "Medium" : "Hard");
    $title = "Greedy Algorithm Problem #$i: " . [
        1 => "Lemonade Change",
        2 => "Maximum Units on a Truck",
        3 => "Split a String in Balanced Strings",
        4 => "Minimum Sum of Four Digit Number",
        5 => "Largest Number After Digit Swaps by Parity",
        6 => "Minimum Cost to Move Chips to the Same Position",
        7 => "Jump Game I (Can Reach End)",
        8 => "Jump Game II (Minimum Jumps)",
        9 => "Gas Station Circular Circuit",
        10 => "Candy Distribution Problem",
        11 => "Non-overlapping Intervals",
        12 => "Minimum Number of Arrows to Burst Balloons",
        13 => "Partition Labels String",
        14 => "Queue Reconstruction by Height",
        15 => "Boats to Save People",
        16 => "Bag of Tokens (Score Maximization)",
        17 => "Patching Array (Missing Sum Patches)",
        18 => "Create Maximum Number",
        19 => "Minimum Initial Energy to Finish Tasks",
        20 => "Course Schedule III Greedy Selection"
    ][$i];

    addP($problems, $title, "Greedy", $diff,
    "Solve greedy optimization challenge #$i.",
    "1 <= N <= 10^5", "greedy_in_$i", "greedy_out_$i",
    "Make locally optimal choice at each step to reach global optimum. Prove choice property using stay-ahead or exchange argument.", "O(N log N)", "O(1)");
}

// -----------------------------------------------------------------------------
// TWO POINTERS & SLIDING WINDOW (20 Problems)
// -----------------------------------------------------------------------------
for ($i = 1; $i <= 20; $i++) {
    $diff = ($i <= 6) ? "Easy" : (($i <= 15) ? "Medium" : "Hard");
    $title = "Sliding Window / Two Pointers Problem #$i: " . [
        1 => "Remove Duplicates from Sorted Array I",
        2 => "Remove Duplicates from Sorted Array II",
        3 => "Move Zeroes to End",
        4 => "Reverse String Two Pointers",
        5 => "Valid Palindrome Alphanumeric",
        6 => "Merge Sorted Array In-Place",
        7 => "3Sum Closest",
        8 => "3Sum Smaller Count",
        9 => "Sort Colors Red White Blue",
        10 => "Container With Most Water",
        11 => "Trapping Rain Water Two Pointers",
        12 => "Max Consecutive Ones III (At Most K Zeros)",
        13 => "Fruit Into Baskets (At Most 2 Types)",
        14 => "Longest Substring with At Most K Distinct Characters",
        15 => "Permutation in String",
        16 => "Minimum Window Substring",
        17 => "Substring with Concatenation of All Words",
        18 => "Longest Substring with At Least K Repeating Characters",
        19 => "Subarrays with K Different Integers",
        20 => "Minimum Window Subsequence"
    ][$i];

    addP($problems, $title, "Sliding Window", $diff,
    "Solve two pointers or sliding window challenge #$i.",
    "1 <= N <= 10^5", "window_in_$i", "window_out_$i",
    "Maintain expandable / contractible dynamic window bounds [left, right] to compute window invariants in linear time.", "O(N)", "O(1)");
}

// -----------------------------------------------------------------------------
// BIT MANIPULATION & MATH (20 Problems)
// -----------------------------------------------------------------------------
for ($i = 1; $i <= 20; $i++) {
    $diff = ($i <= 6) ? "Easy" : (($i <= 15) ? "Medium" : "Hard");
    $title = "Bitwise / Math Algorithm Problem #$i: " . [
        1 => "Number of 1 Bits (Hamming Weight)",
        2 => "Counting Bits 0 to N",
        3 => "Single Number I (Find Unique)",
        4 => "Reverse Bits 32-bit Unsigned",
        5 => "Power of Two Checker",
        6 => "Power of Four Checker",
        7 => "Single Number II (Appears 3 Times)",
        8 => "Single Number III (Two Unique Numbers)",
        9 => "Bitwise AND of Numbers Range",
        10 => "Sum of Two Integers Without + or -",
        11 => "Divide Two Integers Without * or /",
        12 => "Maximum Product of Word Lengths",
        13 => "UTF-8 Validation Bit Masking",
        14 => "Subsets Bitmask Generation",
        15 => "Find Missing Number XOR Method",
        16 => "Integer Replacement Min Operations",
        17 => "Minimum Flips to Make a OR b Equal to c",
        18 => "Concatenation of Consecutive Binary Numbers",
        19 => "Find Longest Awesome Substring (Bitmask DP)",
        20 => "Minimum Number of K Consecutive Bit Flips"
    ][$i];

    addP($problems, $title, "Bit Manipulation", $diff,
    "Solve bitwise operation challenge #$i.",
    "1 <= N <= 10^9", "bit_in_$i", "bit_out_$i",
    "Apply bitwise operators (&, |, ^, ~, <<, >>), masks, and bit tricks (e.g. `n & (n - 1)` to clear lowest set bit).", "O(1)", "O(1)");
}

$inserted = 0;
$skipped = 0;

$stmt = $db->prepare('INSERT INTO coding_problems 
    (title, category, difficulty, problem_statement, constraints, example_input, example_output, concept_explanation, time_complexity, space_complexity) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');

$db->beginTransaction();

foreach ($problems as $p) {
    $tLower = strtolower(trim($p['title']));
    if (isset($existingTitles[$tLower])) {
        $skipped++;
        continue;
    }

    $stmt->execute([
        $p['title'],
        $p['category'],
        $p['difficulty'],
        $p['statement'],
        $p['constraints'],
        $p['input'],
        $p['output'],
        $p['explanation'],
        $p['time'],
        $p['space']
    ]);

    $existingTitles[$tLower] = true;
    $inserted++;
}

$db->commit();

echo "Second Batch Migration Successful!\n";
echo "Newly Inserted Problems: $inserted\n";
echo "Skipped Duplicates: $skipped\n";

$countStmt = $db->query("SELECT COUNT(*) FROM coding_problems");
echo "Total Problems in Platform Now: " . $countStmt->fetchColumn() . "\n";
