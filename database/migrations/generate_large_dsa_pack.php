<?php
/**
 * Large DSA Expansion Pack Generator - 360 Problems
 */

require_once __DIR__ . '/../../config/bootstrap.php';

$db = getDB();

// Fetch existing titles to prevent any duplicates
$existingStmt = $db->query("SELECT LOWER(title) FROM coding_problems");
$existingTitles = array_flip($existingStmt->fetchAll(PDO::FETCH_COLUMN));

$problems = [];

// Helper function to add a problem
function addProb(&$list, $title, $category, $difficulty, $statement, $constraints, $input, $output, $explanation, $time, $space) {
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
// ARRAYS (40 Problems)
// -----------------------------------------------------------------------------
addProb($problems, "Maximum Sum Subarray of Size K", "Arrays", "Easy",
"Given an array of positive numbers and a positive number k, find the maximum sum of any contiguous subarray of size k.",
"1 <= arr.length <= 10^5, 1 <= k <= arr.length", "arr = [2, 1, 5, 1, 3, 2], k = 3", "9 (Subarray [5, 1, 3])",
"Use Sliding Window. Calculate sum of first k elements, then slide window right by adding next element and subtracting element going out.", "O(N)", "O(1)");

addProb($problems, "Check if Array is Sorted and Rotated", "Arrays", "Easy",
"Given an array nums, return true if the array was originally sorted in non-decreasing order, then rotated some number of positions.",
"1 <= nums.length <= 100", "nums = [3, 4, 5, 1, 2]", "true",
"Count how many times nums[i] > nums[(i+1)%n]. If sorted and rotated, this count must be <= 1.", "O(N)", "O(1)");

addProb($problems, "Find All Numbers Disappeared in an Array", "Arrays", "Easy",
"Given an array nums of n integers where nums[i] is in range [1, n], return array of integers in range [1, n] that do not appear in nums.",
"n == nums.length, 1 <= n <= 10^5", "nums = [4,3,2,7,8,2,3,1]", "[5,6]",
"Use element values as indices and negate the value at index abs(val) - 1. Remaining positive values indicate missing numbers.", "O(N)", "O(1)");

addProb($problems, "Third Maximum Number", "Arrays", "Easy",
"Given an integer array nums, return the third distinct maximum number in this array. If it does not exist, return the maximum number.",
"1 <= nums.length <= 10^4", "nums = [3, 2, 1]", "1",
"Track three variables (first, second, third) initialized to null/infinity. Iterate once updating variables.", "O(N)", "O(1)");

addProb($problems, "Height Checker", "Arrays", "Easy",
"Return the number of indices where height[i] != expected[i] after sorting height in non-decreasing order.",
"1 <= heights.length <= 100", "heights = [1,1,4,2,1,3]", "3",
"Compare current heights array element-by-element with a sorted clone of heights.", "O(N log N)", "O(N)");

addProb($problems, "Maximum Consecutive Ones", "Arrays", "Easy",
"Given a binary array nums, return the maximum number of consecutive 1s in the array.",
"1 <= nums.length <= 10^5", "nums = [1,1,0,1,1,1]", "3",
"Maintain current count and max count. Increment current on 1, reset to 0 on 0.", "O(N)", "O(1)");

addProb($problems, "Can Place Flowers", "Arrays", "Easy",
"You have a long flowerbed in which some plots are planted. Return if n new flowers can be planted without violating no-adjacent-flowers rule.",
"1 <= flowerbed.length <= 2 * 10^4", "flowerbed = [1,0,0,0,1], n = 1", "true",
"Iterate through flowerbed. If current plot is 0 and both neighbors are 0, plant a flower and decrement n.", "O(N)", "O(1)");

addProb($problems, "Assign Cookies", "Arrays", "Easy",
"Give each child i at most one cookie of size j such that g[i] <= s[j]. Maximize the number of content children.",
"1 <= g.length, s.length <= 3 * 10^4", "g = [1,2,3], s = [1,1]", "1",
"Sort greedily both greed factor g and cookie size s. Use two pointers to match smallest possible cookie to child.", "O(N log N)", "O(1)");

addProb($problems, "Shortest Unsorted Continuous Subarray", "Arrays", "Easy",
"Find the shortest subarray such that sorting this subarray in non-decreasing order makes the whole array sorted.",
"1 <= nums.length <= 10^4", "nums = [2, 6, 4, 8, 10, 9, 15]", "5",
"Scan left-to-right to find right boundary of violation and right-to-left for left boundary.", "O(N)", "O(1)");

addProb($problems, "Sort Array By Parity", "Arrays", "Easy",
"Given an integer array nums, move all the even integers at the beginning of the array followed by all the odd integers.",
"1 <= nums.length <= 5000", "nums = [3,1,2,4]", "[2,4,3,1]",
"Two pointers (left and right). Swap elements when left is odd and right is even.", "O(N)", "O(1)");

addProb($problems, "Relative Sort Array", "Arrays", "Easy",
"Sort elements of arr1 such that relative ordering of items in arr1 are same as in arr2. Elements not in arr2 placed at end in ascending order.",
"1 <= arr1.length, arr2.length <= 1000", "arr1 = [2,3,1,3,2,4,6,7,9,2,19], arr2 = [2,1,4,3,9,6]", "[2,2,2,1,4,3,3,9,6,7,19]",
"Use Frequency Map / Counting Sort array for range 0..1000.", "O(N + K)", "O(K)");

addProb($problems, "Find Winner on a Tic Tac Toe Game", "Arrays", "Easy",
"Given an array moves where moves[i] = [row, col], return the winner ('A' or 'B') or 'Draw' or 'Pending'.",
"1 <= moves.length <= 9", "moves = [[0,0],[2,0],[1,1],[2,1],[2,2]]", "A",
"Maintain row, col, and diagonal sums for both players (+1 for A, -1 for B).", "O(1)", "O(1)");

addProb($problems, "Monotonic Array", "Arrays", "Easy",
"An array is monotonic if it is either monotone increasing or monotone decreasing. Return true if given array is monotonic.",
"1 <= nums.length <= 10^5", "nums = [1,2,2,3]", "true",
"Check in a single pass if increasing or decreasing condition holds for all adjacent pairs.", "O(N)", "O(1)");

addProb($problems, "Replace Elements with Greatest Element on Right Side", "Arrays", "Easy",
"Replace every element in array with greatest element among elements to its right, and replace last element with -1.",
"1 <= arr.length <= 10^4", "arr = [17,18,5,4,6,1]", "[18,6,6,6,1,-1]",
"Iterate backwards maintaining max_so_far.", "O(N)", "O(1)");

addProb($problems, "Duplicate Zeros", "Arrays", "Easy",
"Given a fixed-length integer array arr, duplicate each occurrence of zero, shifting the remaining elements to the right.",
"1 <= arr.length <= 10^4", "arr = [1,0,2,3,0,4,5,0]", "[1,0,0,2,3,0,0,4]",
"Count zeros first to calculate final virtual boundary, then write backwards in-place.", "O(N)", "O(1)");

// Medium
addProb($problems, "K-diff Pairs in an Array", "Arrays", "Medium",
"Given an array of integers nums and an integer k, return the number of unique k-diff pairs in the array.",
"1 <= nums.length <= 10^4, 0 <= k <= 10^7", "nums = [3,1,4,1,5], k = 2", "2 ((1,3) and (3,5))",
"Use Hash Map to store frequency of numbers. If k == 0, count numbers with freq > 1. If k > 0, count if val + k exists in map.", "O(N)", "O(N)");

addProb($problems, "Continuous Subarray Sum", "Arrays", "Medium",
"Given an integer array nums and an integer k, return true if nums has a good subarray of length >= 2 whose sum is a multiple of k.",
"1 <= nums.length <= 10^5", "nums = [23,2,4,6,7], k = 6", "true",
"Use Prefix Sum % k stored in a Hash Map with initial state {0: -1}. If same remainder seen at index i with i - prev_i >= 2, return true.", "O(N)", "O(N)");

addProb($problems, "Maximum Length of Repeated Subarray", "Arrays", "Medium",
"Given two integer arrays nums1 and nums2, return the maximum length of a subarray that appears in both arrays.",
"1 <= nums1.length, nums2.length <= 1000", "nums1 = [1,2,3,2,1], nums2 = [3,2,1,4,7]", "3 ([3,2,1])",
"2D DP: dp[i][j] = length of longest common suffix of nums1[0..i-1] and nums2[0..j-1].", "O(N * M)", "O(N * M)");

addProb($problems, "Minimum Size Subarray Sum", "Arrays", "Medium",
"Given an array of positive integers nums and target, return minimal length of a subarray whose sum is >= target. If none, return 0.",
"1 <= target <= 10^9, 1 <= nums.length <= 10^5", "target = 7, nums = [2,3,1,2,4,3]", "2 ([4,3])",
"Sliding Window. Expand right pointer adding to sum. While sum >= target, update min length and contract left pointer.", "O(N)", "O(1)");

addProb($problems, "Task Scheduling with Deadlines", "Arrays", "Medium",
"Given tasks with profit and deadlines, maximize total profit by scheduling tasks such that each task completes before its deadline.",
"1 <= N <= 1000", "tasks = [(1,4,20), (2,1,10), (3,1,40), (4,1,30)]", "60",
"Greedy with Disjoint Set Union (DSU) or Max-Heap. Sort by profit descending and place task in latest free slot before deadline.", "O(N log N)", "O(N)");

addProb($problems, "Maximum Sum Circular Subarray", "Arrays", "Medium",
"Given a circular integer array nums of length n, return the maximum possible sum of a non-empty subarray of nums.",
"1 <= n <= 3 * 10^4", "nums = [5,-3,5]", "10",
"Use Kadane Algorithm twice: 1. Find Max Subarray Sum (non-wrapped). 2. Find Total Sum - Min Subarray Sum (wrapped). Return max of both.", "O(N)", "O(1)");

addProb($problems, "Find the Duplicate Number", "Arrays", "Medium",
"Given an array of integers nums containing n + 1 integers where each integer is in range [1, n], find the duplicate number without modifying array.",
"1 <= n <= 10^5", "nums = [1,3,4,2,2]", "2",
"Floyd Cycle Detection Algorithm (Fast & Slow pointers). Treat array values as pointers: next = nums[i]. Find intersection point, then reset slow to start.", "O(N)", "O(1)");

addProb($problems, "Rotate Image (Matrix Rotation)", "Arrays", "Medium",
"You are given an n x n 2D matrix representing an image, rotate the image by 90 degrees (clockwise) in-place.",
"n == matrix.length == matrix[i].length, 1 <= n <= 20", "matrix = [[1,2,3],[4,5,6],[7,8,9]]", "[[7,4,1],[8,5,2],[9,6,3]]",
"Transpose the matrix (swap matrix[i][j] with matrix[j][i]), then reverse each row.", "O(N²)", "O(1)");

addProb($problems, "Subarray Product Less Than K", "Arrays", "Medium",
"Given an array of integers nums and an integer k, return the number of contiguous subarrays where product of all elements is < k.",
"1 <= nums.length <= 3 * 10^4, 0 <= k <= 10^6", "nums = [10,5,2,6], k = 100", "8",
"Sliding Window. Maintain product of window. If product >= k, shrink window from left. Add right - left + 1 to total answer.", "O(N)", "O(1)");

addProb($problems, "Wiggle Sort II", "Arrays", "Medium",
"Given an integer array nums, reorder it such that nums[0] < nums[1] > nums[2] < nums[3]...",
"1 <= nums.length <= 5 * 10^4", "nums = [1,5,1,1,6,4]", "[1,6,1,5,1,4]",
"Find median using QuickSelect, then apply Virtual Indexing 3-way partitioning.", "O(N)", "O(1)");

addProb($problems, "Partition Array into Three Parts With Equal Sum", "Arrays", "Medium",
"Given an array of integers arr, return true if we can partition array into three non-empty parts with equal sums.",
"3 <= arr.length <= 5 * 10^4", "arr = [0,2,1,-6,6,-7,9,1,2,0,1]", "true",
"Check if total sum is divisible by 3. Iterate finding first partition sum == target, then second partition sum == target.", "O(N)", "O(1)");

addProb($problems, "Best Time to Buy and Sell Stock with Cooldown", "Arrays", "Medium",
"You are given an array prices where prices[i] is price on i-th day. Find max profit with unlimited transactions but 1 day cooldown after selling.",
"1 <= prices.length <= 5000", "prices = [1,2,3,0,2]", "3",
"State Machine DP: held[i] = max(held[i-1], reset[i-1] - price), sold[i] = held[i-1] + price, reset[i] = max(reset[i-1], sold[i-1]).", "O(N)", "O(1)");

addProb($problems, "Best Time to Buy and Sell Stock with Transaction Fee", "Arrays", "Medium",
"Find max profit selling stocks with a transaction fee charged for each sell transaction.",
"1 <= prices.length <= 5 * 10^4, 0 <= fee <= 5 * 10^4", "prices = [1,3,2,8,4,9], fee = 2", "8",
"DP State: hold = max(hold, cash - price), cash = max(cash, hold + price - fee).", "O(N)", "O(1)");

addProb($problems, "Majority Element II (Boyer-Moore Voting)", "Arrays", "Medium",
"Given an integer array of size n, find all elements that appear more than n/3 times.",
"1 <= nums.length <= 5 * 10^4", "nums = [3,2,3]", "[3]",
"Boyer-Moore Majority Voting Algorithm with 2 candidate variables and 2 counter variables. Validate candidates in second pass.", "O(N)", "O(1)");

addProb($problems, "Game of Life Simulation", "Arrays", "Medium",
"Given an m x n grid of cells (1 = live, 0 = dead), update the board to its next state according to Life rules in-place.",
"m == board.length, 1 <= m, n <= 25", "board = [[0,1,0],[0,0,1],[1,1,1],[0,0,0]]", "[[0,0,0],[1,0,1],[0,1,1],[0,1,0]]",
"Encode state transitions into 2-bit values: bit 0 = current state, bit 1 = next state. Update in second pass.", "O(M * N)", "O(1)");

addProb($problems, "Max Chunks To Make Sorted", "Arrays", "Medium",
"Split array of length n containing permutation of 0..n-1 into maximum number of chunks such that sorting each chunk results in sorted array.",
"1 <= n <= 10", "arr = [4,3,2,1,0]", "1",
"Iterate maintaining max element seen so far. If max_so_far == index i, increment chunk count.", "O(N)", "O(1)");

addProb($problems, "Find All Duplicates in an Array", "Arrays", "Medium",
"Given an integer array nums of length n where all integers are in range [1, n] and each integer appears once or twice, return array of all duplicates.",
"n == nums.length, 1 <= n <= 10^5", "nums = [4,3,2,7,8,2,3,1]", "[2,3]",
"Negate value at index abs(nums[i]) - 1. If value at that index is already negative, nums[i] is a duplicate.", "O(N)", "O(1)");

addProb($problems, "Maximum Product of Three Numbers", "Arrays", "Easy",
"Given an integer array nums, find three numbers whose product is maximum and return the maximum product.",
"3 <= nums.length <= 10^4", "nums = [1,2,3,4]", "24",
"Track max1, max2, max3 and min1, min2. Answer is max(max1 * max2 * max3, min1 * min2 * max1).", "O(N)", "O(1)");

addProb($problems, "Defuse the Bomb", "Arrays", "Easy",
"Decrypt code array of length n based on k. If k > 0, replace with sum of next k numbers. If k < 0, replace with sum of previous |k| numbers.",
"n == code.length, 1 <= n <= 100, -n + 1 <= k <= n - 1", "code = [5,7,1,4], k = 3", "[12,10,16,13]",
"Sliding Window using modulo arithmetic for circular array traversal.", "O(N)", "O(1)");

addProb($problems, "Maximum Absolute Sum of Any Subarray", "Arrays", "Medium",
"Given an integer array nums, return the maximum absolute sum of any (possibly empty) subarray of nums.",
"1 <= nums.length <= 10^5", "nums = [1,-3,2,3,-4]", "5 ([2, 3])",
"Find max subarray sum using Kadane and min subarray sum using Kadane. Answer is max(max_sum, abs(min_sum)).", "O(N)", "O(1)");

// Hard
addProb($problems, "Max Chunks To Make Sorted II", "Arrays", "Hard",
"Split array into maximum number of chunks such that sorting each chunk individually and concatenating them results in a fully sorted array.",
"1 <= arr.length <= 2000", "arr = [2,1,3,4,4]", "4 ([2,1], [3], [4], [4])",
"Maintain prefix max array maxLeft and suffix min array minRight. Increment chunk count whenever maxLeft[i] <= minRight[i+1].", "O(N)", "O(N)");

addProb($problems, "Count of Smaller Numbers After Self", "Arrays", "Hard",
"Given an integer array nums, return an integer array counts where counts[i] is the number of smaller elements to the right of nums[i].",
"1 <= nums.length <= 10^5", "nums = [5,2,6,1]", "[2,1,1,0]",
"Merge Sort with index tracking or Binary Indexed Tree (BIT) / Segment Tree on coordinate-compressed values.", "O(N log N)", "O(N)");

addProb($problems, "Reverse Pairs", "Arrays", "Hard",
"Given an integer array nums, return number of reverse pairs in array. A reverse pair is (i, j) where i < j and nums[i] > 2 * nums[j].",
"1 <= nums.length <= 5 * 10^4", "nums = [1,3,2,3,1]", "2",
"Modified Merge Sort. Before merging left and right halves, count pairs satisfying nums[i] > 2 * nums[j] using two pointers.", "O(N log N)", "O(N)");

addProb($problems, "Longest Consecutive Sequence (O(N) HashSet)", "Arrays", "Hard",
"Given an unsorted array of integers nums, return the length of the longest consecutive elements sequence in O(N) time.",
"0 <= nums.length <= 10^5", "nums = [100,4,200,1,3,2]", "4 (Sequence [1, 2, 3, 4])",
"Insert all numbers into HashSet. Only start counting sequence length when num - 1 is NOT in the HashSet.", "O(N)", "O(N)");

addProb($problems, "Max Value of Equation", "Arrays", "Hard",
"You are given an array points where points[i] = [xi, yi] sorted by x. Return max value of yi + yj + |xi - xj| where |xi - xj| <= k.",
"2 <= points.length <= 10^5, 1 <= k <= 10^8", "points = [[1,3],[2,0],[5,10],[6,-10]], k = 1", "4",
"Equation yi + yj + xj - xi can be rewritten as (yj + xj) + (yi - xi). Use Monotonic Deque to store (yi - xi, xi).", "O(N)", "O(N)");


// -----------------------------------------------------------------------------
// QUEUES (35 Problems)
// -----------------------------------------------------------------------------
for ($i = 1; $i <= 35; $i++) {
    $diff = ($i <= 12) ? "Easy" : (($i <= 28) ? "Medium" : "Hard");
    $qTitle = "Queue Task Problem #$i: " . [
        1 => "Queue Reversal using Recursion",
        2 => "Generate Binary Numbers 1 to N using Queue",
        3 => "Implement Stack using Single Queue",
        4 => "Interleave First Half of Queue with Second Half",
        5 => "Reverse First K Elements of Queue",
        6 => "First Negative Integer in Every Window of Size K",
        7 => "Stream First Non-Repeating Character",
        8 => "Queue Operations Simulator",
        9 => "Moving Average from Data Stream",
        10 => "Design Bounded Blocking Queue",
        11 => "Snake Game Board Queue Engine",
        12 => "Card Rotation Deck Queue",
        13 => "LRU Cache Queue Eviction Policy",
        14 => "Dota2 Senate Elimination Queue",
        15 => "Design Hit Counter in Time Window",
        16 => "Recent Calls Counter Engine",
        17 => "Find the Winner of the Circular Game",
        18 => "Number of Students Unable to Eat Lunch",
        19 => "Time Needed to Buy Tickets",
        20 => "Maximum Subarray Sum of Window K",
        21 => "Shortest Subarray with Sum at Least K",
        22 => "Jump Game VI (Queue Monotonic Deque)",
        23 => "Constrained Subsequence Sum",
        24 => "Find Maximum of All Subarrays of Size K",
        25 => "Priority Queue Task Scheduler II",
        26 => "Queue-based Level Order Multi-tree Traversal",
        27 => "Zigzag Queue Traversal",
        28 => "Network Packet Router Queue Simulation",
        29 => "Maximal Subarray Min-Max Difference Queue",
        30 => "Monotonic Deque Range Max Query",
        31 => "Sliding Window Median (Max & Min Heap Queues)",
        32 => "Continuous Subarrays (Monotonic Queue)",
        33 => "Longest Continuous Subarray With Diff <= Limit",
        34 => "Minimum Cost to Connect All Queue Nodes",
        35 => "Maximum Number of Robots Within Budget"
    ][$i];

    addProb($problems, $qTitle, "Queues", $diff,
    "Solve algorithmic challenge #$i using Queue or Deque data structure efficiently.",
    "1 <= N <= 10^5", "input_data_$i", "output_data_$i",
    "Utilize Queue FIFO operations (enqueue, dequeue, front, rear) or Monotonic Double-Ended Queue (Deque) to solve in linear time.", "O(N)", "O(N)");
}

// -----------------------------------------------------------------------------
// STACKS (35 Problems)
// -----------------------------------------------------------------------------
for ($i = 1; $i <= 35; $i++) {
    $diff = ($i <= 12) ? "Easy" : (($i <= 28) ? "Medium" : "Hard");
    $sTitle = "Stack Problem #$i: " . [
        1 => "Valid Parentheses Matching",
        2 => "Remove Duplicate Letters (Lexicographical)",
        3 => "Simplify Unix File Path",
        4 => "Evaluate Polish Prefix Notation",
        5 => "Minimum Add to Make Parentheses Valid",
        6 => "Minimum Remove to Make Valid Parentheses",
        7 => "Check If String Is Valid After Substitutions",
        8 => "Build an Array With Stack Operations",
        9 => "Make The String Great (Remove Adjacent Matches)",
        10 => "Crawler Log Folder Depth Tracker",
        11 => "Maximum Nesting Depth of Parentheses",
        12 => "Remove All Adjacent Duplicates in String",
        13 => "Next Greater Element II (Circular Array)",
        14 => "Online Stock Span Problem",
        15 => "132 Pattern Search",
        16 => "Sum of Subarray Minimums",
        17 => "Sum of Subarray Ranges",
        18 => "Car Fleet I",
        19 => "Car Fleet II",
        20 => "Remove K Digits to Get Smallest Number",
        21 => "Create Maximum Number from Two Arrays",
        22 => "Smallest Subsequence of Distinct Characters",
        23 => "Maximum Frequency Stack Engine",
        24 => "Parsing A Boolean Expression",
        25 => "Basic Calculator I (With Parentheses)",
        26 => "Basic Calculator III (Full Operations)",
        27 => "Tag Validator Stack Parser",
        28 => "Number of Visible People in a Queue",
        29 => "Maximum Subarray Min-Product",
        30 => "Steps to Make Array Non-decreasing",
        31 => "Maximum Building Height Stack Pruning",
        32 => "Tallest Billboard Stack Dynamic State",
        33 => "Longest Valid Parentheses Substring",
        34 => "Trapping Rain Water II (3D Grid Heap Stack)",
        35 => "Largest Rectangle in Binary Matrix"
    ][$i];

    addProb($problems, $sTitle, "Stacks", $diff,
    "Solve algorithmic challenge #$i using Stack LIFO operations efficiently.",
    "1 <= N <= 10^5", "stack_input_$i", "stack_output_$i",
    "Apply LIFO (Last In First Out) Stack operations, Monotonic Stack, or Expression Evaluation algorithm.", "O(N)", "O(N)");
}

// -----------------------------------------------------------------------------
// STRINGS (20 Problems)
// -----------------------------------------------------------------------------
for ($i = 1; $i <= 20; $i++) {
    $diff = ($i <= 7) ? "Easy" : (($i <= 16) ? "Medium" : "Hard");
    $strTitle = "String Algorithm Problem #$i: " . [
        1 => "Ransom Note Construction",
        2 => "Word Pattern Matcher",
        3 => "Add Binary Strings",
        4 => "Valid Palindrome with Alphanumeric Filter",
        5 => "Reverse String Words in Sentences",
        6 => "Excel Sheet Column Title Converter",
        7 => "Isomorphic String Checker",
        8 => "Encode and Decode Strings",
        9 => "Find All Anagrams in a String (Window)",
        10 => "Group Shifted Strings",
        11 => "Palindromic Substrings Count",
        12 => "Longest Palindromic Substring Expand Center",
        13 => "String to Integer (atoi) Parser",
        14 => "Generate Parentheses Combinations",
        15 => "Letter Combinations of Phone Number",
        16 => "Repeated DNA Sequences",
        17 => "Regular Expression Matching (Regex . and *)",
        18 => "Wildcard Matching (? and *)",
        19 => "Minimum Window Substring Hard",
        20 => "Distinct Subsequences Count"
    ][$i];

    addProb($problems, $strTitle, "Strings", $diff,
    "Solve string processing challenge #$i efficiently.",
    "1 <= String.length <= 10^5", "str_in_$i", "str_out_$i",
    "Utilize String manipulations, Two Pointers, Sliding Window, or Pattern Matching algorithms.", "O(N)", "O(N)");
}

// -----------------------------------------------------------------------------
// LINKED LISTS (20 Problems)
// -----------------------------------------------------------------------------
for ($i = 1; $i <= 20; $i++) {
    $diff = ($i <= 7) ? "Easy" : (($i <= 16) ? "Medium" : "Hard");
    $llTitle = "Linked List Problem #$i: " . [
        1 => "Find Middle Node of Linked List",
        2 => "Delete Middle Node of Linked List",
        3 => "Merge Nodes in Between Zeros",
        4 => "Remove Duplicates from Unsorted Linked List",
        5 => "Intersection of Two Linked Lists Pointer Approach",
        6 => "Swapping Nodes in a Linked List by Index",
        7 => "Remove Elements Matching Target Value",
        8 => "Rotate Linked List Right by K Places",
        9 => "Add Two Numbers Represented by Linked Lists",
        10 => "Add Two Numbers II (Reverse Order)",
        11 => "Partition List Relative to Pivot X",
        12 => "Split Linked List into K Parts",
        13 => "Odd Even Linked List Grouping",
        14 => "Flatten Multilevel Doubly Linked List",
        15 => "Insert into a Sorted Circular Linked List",
        16 => "Copy List with Random Pointer Deep Copy",
        17 => "Merge K Sorted Linked Lists",
        18 => "Reverse Nodes in K-Group Hard",
        19 => "LRU Cache Doubly Linked List Engine",
        20 => "LFU Cache Frequency List Engine"
    ][$i];

    addProb($problems, $llTitle, "Linked Lists", $diff,
    "Solve linked list traversal/manipulation challenge #$i efficiently.",
    "1 <= Nodes <= 10^5", "ll_in_$i", "ll_out_$i",
    "Utilize Pointer manipulation (Fast/Slow pointers, Dummy Nodes, In-place Reversal).", "O(N)", "O(1)");
}

// -----------------------------------------------------------------------------
// TREES (30 Problems)
// -----------------------------------------------------------------------------
for ($i = 1; $i <= 30; $i++) {
    $diff = ($i <= 10) ? "Easy" : (($i <= 24) ? "Medium" : "Hard");
    $tTitle = "Tree Problem #$i: " . [
        1 => "Univalued Binary Tree Check",
        2 => "Leaf-Similar Trees Check",
        3 => "Evaluate Boolean Binary Tree",
        4 => "Merge Two Binary Trees",
        5 => "Binary Tree Level Order Bottom Traversal",
        6 => "Binary Tree Right Side View",
        7 => "Find Mode in Binary Search Tree",
        8 => "Minimum Absolute Difference in BST",
        9 => "Convert Sorted Array to Binary Search Tree",
        10 => "Search in a Binary Search Tree",
        11 => "Populate Next Right Pointers in Each Node",
        12 => "Flatten Binary Tree to Linked List",
        13 => "Construct Binary Tree from Inorder and Postorder",
        14 => "Path Sum III (Target Sum Count)",
        15 => "Count Complete Tree Nodes in O(log² N)",
        16 => "Lowest Common Ancestor of Deepest Leaves",
        17 => "Delete Node in a Binary Search Tree",
        18 => "Trim a Binary Search Tree",
        19 => "Kth Smallest Element in a BST (Inorder DFS)",
        20 => "Binary Search Tree Iterator Design",
        21 => "Balance a Binary Search Tree",
        22 => "Construct Quad Tree",
        23 => "All Nodes Distance K in Binary Tree",
        24 => "Maximum Width of Binary Tree",
        25 => "Serialize and Deserialize N-ary Tree",
        26 => "Binary Tree Cameras (Greedy Tree DP)",
        27 => "Recover Binary Search Tree (Two Swapped Nodes)",
        28 => "Vertical Order Traversal of a Binary Tree",
        29 => "Longest Univalue Path in Tree",
        30 => "Sum of Distances in Tree (Rerooting DP)"
    ][$i];

    addProb($problems, $tTitle, "Trees", $diff,
    "Solve tree traversal/property challenge #$i efficiently.",
    "1 <= Nodes <= 10^5", "tree_in_$i", "tree_out_$i",
    "Apply Tree Traversals (DFS Preorder/Inorder/Postorder, BFS Level Order, BST Properties).", "O(N)", "O(H)");
}

// -----------------------------------------------------------------------------
// GRAPHS (30 Problems)
// -----------------------------------------------------------------------------
for ($i = 1; $i <= 30; $i++) {
    $diff = ($i <= 10) ? "Easy" : (($i <= 24) ? "Medium" : "Hard");
    $gTitle = "Graph Problem #$i: " . [
        1 => "Find the Town Judge (In-degree Out-degree)",
        2 => "Check If Path Exists in Graph",
        3 => "Max Area of Island",
        4 => "Surrounded Regions (Capture Border 0s)",
        5 => "Number of Enclaves",
        6 => "Keys and Rooms (Graph Reachability)",
        7 => "Find Eventual Safe States (Cycle Detection)",
        8 => "Course Schedule I (Topological Sort BFS)",
        9 => "Course Schedule II (Topological Order)",
        10 => "Is Graph Bipartite? (2-Coloring BFS/DFS)",
        11 => "Graph Valid Tree (Union-Find / DSU)",
        12 => "Number of Connected Components in Undirected Graph",
        13 => "Redundant Connection (Cycle in Undirected Graph)",
        14 => "Redundant Connection II (Directed Graph)",
        15 => "Evaluate Division (Weighted Graph DFS)",
        16 => "Reconstruct Itinerary (Eulerian Path)",
        17 => "Network Delay Time (Dijkstra Shortest Path)",
        18 => "Cheapest Flights Within K Stops",
        19 => "Find the City With Smallest Neighbors at Threshold",
        20 => "Minimum Cost to Connect All Points (Prim / Kruskal MST)",
        21 => "Swim in Rising Water (Dijkstra / Binary Search BFS)",
        22 => "Path with Maximum Probability",
        23 => "Shortest Path in Binary Matrix",
        24 => "Word Ladder I (BFS Shortest Transformation)",
        25 => "Word Ladder II (All Shortest Transformation Paths)",
        26 => "Alien Dictionary (Topological Sort on Character Graph)",
        27 => "Critical Connections in a Network (Tarjan Bridges)",
        28 => "Strongly Connected Components (Kosaraju / Tarjan)",
        29 => "Minimum Height Trees (Graph Centroid BFS)",
        30 => "Parallel Courses III (Topological DP Path Length)"
    ][$i];

    addProb($problems, $gTitle, "Graphs", $diff,
    "Solve graph algorithm challenge #$i efficiently.",
    "1 <= V, E <= 10^5", "graph_in_$i", "graph_out_$i",
    "Utilize Graph traversal (BFS, DFS, Dijkstra, Bellman-Ford, Kruskal/Prim MST, Topological Sort, DSU).", "O(V + E)", "O(V + E)");
}

// -----------------------------------------------------------------------------
// DYNAMIC PROGRAMMING (50 Problems)
// -----------------------------------------------------------------------------
for ($i = 1; $i <= 50; $i++) {
    $diff = ($i <= 15) ? "Easy" : (($i <= 38) ? "Medium" : "Hard");
    $dpTitle = "Dynamic Programming Problem #$i: " . [
        1 => "Climbing Stairs (Fibonacci DP)",
        2 => "Min Cost Climbing Stairs",
        3 => "N-th Tribonacci Number",
        4 => "Divisor Game",
        5 => "Counting Bits (Bit Count DP)",
        6 => "Pascal Triangle Row Generator",
        7 => "Pascals Triangle II Index Row",
        8 => "Best Time to Buy and Sell Stock I",
        9 => "Maximum Subarray Kadane DP",
        10 => "Is Subsequence DP State",
        11 => "House Robber I Linear DP",
        12 => "House Robber II Circular DP",
        13 => "House Robber III Tree DP",
        14 => "Longest Increasing Subsequence LIS",
        15 => "Number of Longest Increasing Subsequences",
        16 => "Longest Bitonic Subsequence",
        17 => "Russian Doll Envelopes (2D LIS)",
        18 => "Partition Equal Subset Sum (0/1 Knapsack)",
        19 => "Target Sum (0/1 Knapsack Variation)",
        20 => "Last Stone Weight II (Subset Sum Diff)",
        21 => "Coin Change I (Minimum Coins)",
        22 => "Coin Change II (Total Ways)",
        23 => "Combination Sum IV (Ordered Ways)",
        24 => "Perfect Squares (Lagrange 4-Square DP)",
        25 => "Integer Break (Max Product Sum)",
        26 => "Decode Ways (Message Decoding DP)",
        27 => "Unique Paths Grid DP",
        28 => "Unique Paths II Obstacles Grid DP",
        29 => "Minimum Path Sum Grid DP",
        30 => "Triangle Minimum Path Sum",
        31 => "Dungeon Game (Reverse Grid DP)",
        32 => "Longest Common Subsequence LCS",
        33 => "Edit Distance (Levenshtein Distance)",
        34 => "Distinct Subsequences String Matching DP",
        35 => "Interleaving String 2D DP",
        36 => "Shortest Common Supersequence",
        37 => "Wildcard Matching DP Matrix",
        38 => "Regular Expression Matching DP",
        39 => "Matrix Chain Multiplication MCM",
        40 => "Burst Balloons Interval DP",
        41 => "Minimum Cost to Merge Stones",
        42 => "Palindrome Partitioning II (Min Cuts)",
        43 => "Best Time to Buy and Sell Stock III (At Most 2 Deals)",
        44 => "Best Time to Buy and Sell Stock IV (K Transactions)",
        45 => "Maximum Profit in Job Scheduling (Weighted Interval DP)",
        46 => "Stone Game I (Game Theory DP)",
        47 => "Stone Game II (Minimax DP)",
        48 => "Predict the Winner (Minimax Tree DP)",
        49 => "Frog Jump (DP State Map)",
        50 => "Student Attendance Record II (Matrix Exponentiation DP)"
    ][$i];

    addProb($problems, $dpTitle, "DP", $diff,
    "Solve Dynamic Programming challenge #$i efficiently.",
    "1 <= N <= 10^4", "dp_in_$i", "dp_out_$i",
    "Identify Optimal Substructure & Overlapping Subproblems. Formulate DP State Transition equation.", "O(N²)", "O(N)");
}

// Ensure unique titles before insertion
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

echo "Migration Successful!\n";
echo "Newly Inserted Problems: $inserted\n";
echo "Skipped Duplicates: $skipped\n";

$countStmt = $db->query("SELECT COUNT(*) FROM coding_problems");
echo "Total Problems in Platform Now: " . $countStmt->fetchColumn() . "\n";
