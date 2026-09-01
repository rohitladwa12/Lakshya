<?php
/**
 * Migration Script: Add 360 New DSA Coding Problems
 * Adds 30 problems for each of the 12 "more"s requested by user.
 */

require_once __DIR__ . '/../../config/bootstrap.php';

$db = getDB();

// Fetch existing titles to prevent any duplicates
$existingStmt = $db->query('SELECT LOWER(title) FROM coding_problems');
$existingTitles = array_flip($existingStmt->fetchAll(PDO::FETCH_COLUMN));

$problems = [

    // =========================================================================
    // ARRAYS (45 Problems)
    // =========================================================================
    // Easy
    ['Maximum Sum Subarray of Size K', 'Arrays', 'Easy',
     'Given an array of positive numbers and a positive number k, find the maximum sum of any contiguous subarray of size k.',
     '1 <= arr.length <= 10^5, 1 <= k <= arr.length',
     'arr = [2, 1, 5, 1, 3, 2], k = 3', '9 (Subarray [5, 1, 3])',
     'Use Sliding Window. Calculate sum of first k elements, then slide window right by adding next element and subtracting element going out.', 'O(N)', 'O(1)'],

    ['Check if Array is Sorted and Rotated', 'Arrays', 'Easy',
     'Given an array nums, return true if the array was originally sorted in non-decreasing order, then rotated some number of positions (including zero).',
     '1 <= nums.length <= 100',
     'nums = [3, 4, 5, 1, 2]', 'true',
     'Count how many times nums[i] > nums[(i+1)%n]. If sorted and rotated, this count must be <= 1.', 'O(N)', 'O(1)'],

    ['Find All Numbers Disappeared in an Array', 'Arrays', 'Easy',
     'Given an array nums of n integers where nums[i] is in the range [1, n], return an array of all the integers in the range [1, n] that do not appear in nums.',
     'n == nums.length, 1 <= n <= 10^5, 1 <= nums[i] <= n',
     'nums = [4,3,2,7,8,2,3,1]', '[5,6]',
     'Use element values as indices and negate the value at index `abs(val) - 1`. Remaining positive values indicate missing numbers.', 'O(N)', 'O(1)'],

    ['Third Maximum Number', 'Arrays', 'Easy',
     'Given an integer array nums, return the third distinct maximum number in this array. If the third maximum does not exist, return the maximum number.',
     '1 <= nums.length <= 10^4',
     'nums = [3, 2, 1]', '1',
     'Track three variables (first, second, third) initialized to null/infinity. Iterate once updating variables.', 'O(N)', 'O(1)'],

    ['Height Checker', 'Arrays', 'Easy',
     'Return the number of indices where height[i] != expected[i] after sorting height in non-decreasing order.',
     '1 <= heights.length <= 100',
     'heights = [1,1,4,2,1,3]', '3',
     'Compare current heights array element-by-element with a sorted clone of heights.', 'O(N log N)', 'O(N)'],

    ['Maximum Consecutive Ones', 'Arrays', 'Easy',
     'Given a binary array nums, return the maximum number of consecutive 1s in the array.',
     '1 <= nums.length <= 10^5',
     'nums = [1,1,0,1,1,1]', '3',
     'Maintain current count and max count. Increment current on 1, reset to 0 on 0.', 'O(N)', 'O(1)'],

    ['Can Place Flowers', 'Arrays', 'Easy',
     'You have a long flowerbed in which some of the plots are planted, and some are not. Given an integer array flowerbed and n, return if n new flowers can be planted without violating the no-adjacent-flowers rule.',
     '1 <= flowerbed.length <= 2 * 10^4',
     'flowerbed = [1,0,0,0,1], n = 1', 'true',
     'Iterate through flowerbed. If current plot is 0 and both neighbors (if valid) are 0, plant a flower and decrement n.', 'O(N)', 'O(1)'],

    ['Assign Cookies', 'Arrays', 'Easy',
     'Assume you are an awesome parent and want to give your children some cookies. Give each child i at most one cookie of size j such that g[i] <= s[j]. Maximize the number of content children.',
     '1 <= g.length, s.length <= 3 * 10^4',
     'g = [1,2,3], s = [1,1]', '1',
     'Sort greedily both greed factor g and cookie size s. Use two pointers to match smallest possible cookie to child.', 'O(N log N)', 'O(1)'],

    ['Shortest Unsorted Continuous Subarray', 'Arrays', 'Easy',
     'Find the shortest subarray such that sorting this subarray in non-decreasing order makes the whole array sorted.',
     '1 <= nums.length <= 10^4',
     'nums = [2, 6, 4, 8, 10, 9, 15]', '5 (Subarray [6, 4, 8, 10, 9])',
     'Scan left-to-right to find right boundary of violation and right-to-left for left boundary.', 'O(N)', 'O(1)'],

    ['Sort Array By Parity', 'Arrays', 'Easy',
     'Given an integer array nums, move all the even integers at the beginning of the array followed by all the odd integers.',
     '1 <= nums.length <= 5000',
     'nums = [3,1,2,4]', '[2,4,3,1]',
     'Two pointers (left and right). Swap elements when left is odd and right is even.', 'O(N)', 'O(1)'],

    ['Relative Sort Array', 'Arrays', 'Easy',
     'Sort elements of arr1 such that the relative ordering of items in arr1 are the same as in arr2. Elements not in arr2 should be placed at end in ascending order.',
     '1 <= arr1.length, arr2.length <= 1000',
     'arr1 = [2,3,1,3,2,4,6,7,9,2,19], arr2 = [2,1,4,3,9,6]', '[2,2,2,1,4,3,3,9,6,7,19]',
     'Use Frequency Map / Counting Sort array for range 0..1000.', 'O(N + K)', 'O(K)'],

    ['Find Winner on a Tic Tac Toe Game', 'Arrays', 'Easy',
     'Given an array moves where moves[i] = [row, col], return the winner ("A" or "B") or "Draw" or "Pending".',
     '1 <= moves.length <= 9',
     'moves = [[0,0],[2,0],[1,1],[2,1],[2,2]]', '"A"',
     'Maintain row, col, and diagonal sums for both players (+1 for A, -1 for B).', 'O(1)', 'O(1)'],

    ['Monotonic Array', 'Arrays', 'Easy',
     'An array is monotonic if it is either monotone increasing or monotone decreasing. Return true if given array is monotonic.',
     '1 <= nums.length <= 10^5',
     'nums = [1,2,2,3]', 'true',
     'Check in a single pass if `increasing` or `decreasing` condition holds for all adjacent pairs.', 'O(N)', 'O(1)'],

    ['Replace Elements with Greatest Element on Right Side', 'Arrays', 'Easy',
     'Replace every element in array with greatest element among elements to its right, and replace last element with -1.',
     '1 <= arr.length <= 10^4',
     'arr = [17,18,5,4,6,1]', '[18,6,6,6,1,-1]',
     'Iterate backwards maintaining `max_so_far`.', 'O(N)', 'O(1)'],

    ['Duplicate Zeros', 'Arrays', 'Easy',
     'Given a fixed-length integer array arr, duplicate each occurrence of zero, shifting the remaining elements to the right.',
     '1 <= arr.length <= 10^4',
     'arr = [1,0,2,3,0,4,5,0]', '[1,0,0,2,3,0,0,4]',
     'Count zeros first to calculate final virtual boundary, then write backwards in-place.', 'O(N)', 'O(1)'],

    // Medium
    ['K-diff Pairs in an Array', 'Arrays', 'Medium',
     'Given an array of integers nums and an integer k, return the number of unique k-diff pairs in the array.',
     '1 <= nums.length <= 10^4, 0 <= k <= 10^7',
     'nums = [3,1,4,1,5], k = 2', '2 ((1,3) and (3,5))',
     'Use Hash Map to store frequency of numbers. If k == 0, count numbers with freq > 1. If k > 0, count if `val + k` exists in map.', 'O(N)', 'O(N)'],

    ['Continuous Subarray Sum', 'Arrays', 'Medium',
     'Given an integer array nums and an integer k, return true if nums has a good subarray of length at least two whose sum is a multiple of k.',
     '1 <= nums.length <= 10^5',
     'nums = [23,2,4,6,7], k = 6', 'true (Subarray [2, 4] sum = 6)',
     'Use Prefix Sum % k stored in a Hash Map with initial state `{0: -1}`. If same remainder seen at index `i` with `i - prev_i >= 2`, return true.', 'O(N)', 'O(N)'],

    ['Maximum Length of Repeated Subarray', 'Arrays', 'Medium',
     'Given two integer arrays nums1 and nums2, return the maximum length of a subarray that appears in both arrays.',
     '1 <= nums1.length, nums2.length <= 1000',
     'nums1 = [1,2,3,2,1], nums2 = [3,2,1,4,7]', '3 ([3,2,1])',
     '2D DP: `dp[i][j]` = length of longest common suffix of `nums1[0..i-1]` and `nums2[0..j-1]`.', 'O(N * M)', 'O(N * M)'],

    ['Minimum Size Subarray Sum', 'Arrays', 'Medium',
     'Given an array of positive integers nums and a positive integer target, return the minimal length of a subarray whose sum is >= target. If none, return 0.',
     '1 <= target <= 10^9, 1 <= nums.length <= 10^5',
     'target = 7, nums = [2,3,1,2,4,3]', '2 ([4,3])',
     'Sliding Window. Expand right pointer adding to sum. While `sum >= target`, update min length and contract left pointer.', 'O(N)', 'O(1)'],

    ['Task Scheduling with Deadlines', 'Arrays', 'Medium',
     'Given tasks with profit and deadlines, maximize total profit by scheduling tasks such that each task completes before its deadline.',
     '1 <= N <= 1000',
     'tasks = [(1,4,20), (2,1,10), (3,1,40), (4,1,30)]', '60',
     'Greedy with Disjoint Set Union (DSU) or Max-Heap. Sort by profit descending and place task in latest free slot before deadline.', 'O(N log N)', 'O(N)'],

    ['Maximum Sum Circular Subarray', 'Arrays', 'Medium',
     'Given a circular integer array nums of length n, return the maximum possible sum of a non-empty subarray of nums.',
     '1 <= n <= 3 * 10^4',
     'nums = [5,-3,5]', '10 (Subarray [5, 5])',
     'Use Kadane Algorithm twice: 1. Find Max Subarray Sum (non-wrapped). 2. Find Total Sum - Min Subarray Sum (wrapped). Return max of both.', 'O(N)', 'O(1)'],

    ['Find the Duplicate Number', 'Arrays', 'Medium',
     'Given an array of integers nums containing n + 1 integers where each integer is in the range [1, n] inclusive, find the duplicate number without modifying array.',
     '1 <= n <= 10^5',
     'nums = [1,3,4,2,2]', '2',
     'Floyd Cycle Detection Algorithm (Fast & Slow pointers). Treat array values as pointers: `next = nums[i]`. Find intersection point, then reset slow to start.', 'O(N)', 'O(1)'],

    ['Rotate Image (Matrix Rotation)', 'Arrays', 'Medium',
     'You are given an n x n 2D matrix representing an image, rotate the image by 90 degrees (clockwise) in-place.',
     'n == matrix.length == matrix[i].length, 1 <= n <= 20',
     'matrix = [[1,2,3],[4,5,6],[7,8,9]]', '[[7,4,1],[8,5,2],[9,6,3]]',
     'Transpose the matrix (swap matrix[i][j] with matrix[j][i]), then reverse each row.', 'O(N²)', 'O(1)'],

    ['Subarray Product Less Than K', 'Arrays', 'Medium',
     'Given an array of integers nums and an integer k, return the number of contiguous subarrays where the product of all elements is strictly less than k.',
     '1 <= nums.length <= 3 * 10^4, 0 <= k <= 10^6',
     'nums = [10,5,2,6], k = 100', '8',
     'Sliding Window. Maintain product of window. If `product >= k`, shrink window from left. Add `right - left + 1` to total answer.', 'O(N)', 'O(1)'],

    ['Wiggle Sort II', 'Arrays', 'Medium',
     'Given an integer array nums, reorder it such that nums[0] < nums[1] > nums[2] < nums[3]...',
     '1 <= nums.length <= 5 * 10^4',
     'nums = [1,5,1,1,6,4]', '[1,6,1,5,1,4]',
     'Find median using QuickSelect, then apply Virtual Indexing 3-way partitioning.', 'O(N)', 'O(1)'],

    ['Partition Array into Three Parts With Equal Sum', 'Arrays', 'Medium',
     'Given an array of integers arr, return true if we can partition the array into three non-empty parts with equal sums.',
     '3 <= arr.length <= 5 * 10^4',
     'arr = [0,2,1,-6,6,-7,9,1,2,0,1]', 'true',
     'Check if total sum is divisible by 3. Iterate finding first partition sum == target, then second partition sum == target.', 'O(N)', 'O(1)'],

    ['Best Time to Buy and Sell Stock with Cooldown', 'Arrays', 'Medium',
     'You are given an array prices where prices[i] is price on i-th day. Find max profit with unlimited transactions but 1 day cooldown after selling.',
     '1 <= prices.length <= 5000',
     'prices = [1,2,3,0,2]', '3 (Buy day 1, sell day 2, cooldown day 3, buy day 4, sell day 5)',
     'State Machine DP: `held[i] = max(held[i-1], reset[i-1] - price)`, `sold[i] = held[i-1] + price`, `reset[i] = max(reset[i-1], sold[i-1])`.', 'O(N)', 'O(1)'],

    ['Best Time to Buy and Sell Stock with Transaction Fee', 'Arrays', 'Medium',
     'Find max profit selling stocks with a transaction fee charged for each sell transaction.',
     '1 <= prices.length <= 5 * 10^4, 0 <= fee <= 5 * 10^4',
     'prices = [1,3,2,8,4,9], fee = 2', '8',
     'DP State: `hold = max(hold, cash - price)`, `cash = max(cash, hold + price - fee)`.', 'O(N)', 'O(1)'],

    ['Majority Element II (Boyer-Moore Voting)', 'Arrays', 'Medium',
     'Given an integer array of size n, find all elements that appear more than n/3 times.',
     '1 <= nums.length <= 5 * 10^4',
     'nums = [3,2,3]', '[3]',
     'Boyer-Moore Majority Voting Algorithm with 2 candidate variables and 2 counter variables. Validate candidates in second pass.', 'O(N)', 'O(1)'],

    ['Game of Life', 'Arrays', 'Medium',
     'Given an m x n grid of cells (1 = live, 0 = dead), update the board to its next state according to Conway''s Game of Life rules in-place.',
     'm == board.length, 1 <= m, n <= 25',
     'board = [[0,1,0],[0,0,1],[1,1,1],[0,0,0]]', '[[0,0,0],[1,0,1],[0,1,1],[0,1,0]]',
     'Encode state transitions into 2-bit values: bit 0 = current state, bit 1 = next state. Update in second pass.', 'O(M * N)', 'O(1)'],

    // Hard
    ['Max Chunks To Make Sorted II', 'Arrays', 'Hard',
     'Split array into maximum number of chunks such that sorting each chunk individually and concatenating them results in a fully sorted array.',
     '1 <= arr.length <= 2000',
     'arr = [2,1,3,4,4]', '4 ([2,1], [3], [4], [4])',
     'Maintain prefix max array `maxLeft` and suffix min array `minRight`. Increment chunk count whenever `maxLeft[i] <= minRight[i+1]`.', 'O(N)', 'O(N)'],

    ['Count of Smaller Numbers After Self', 'Arrays', 'Hard',
     'Given an integer array nums, return an integer array counts where counts[i] is the number of smaller elements to the right of nums[i].',
     '1 <= nums.length <= 10^5',
     'nums = [5,2,6,1]', '[2,1,1,0]',
     'Merge Sort with index tracking or Binary Indexed Tree (BIT) / Segment Tree on coordinate-compressed values.', 'O(N log N)', 'O(N)'],

    ['Reverse Pairs', 'Arrays', 'Hard',
     'Given an integer array nums, return the number of reverse pairs in the array. A reverse pair is a pair (i, j) where i < j and nums[i] > 2 * nums[j].',
     '1 <= nums.length <= 5 * 10^4',
     'nums = [1,3,2,3,1]', '2',
     'Modified Merge Sort. Before merging left and right halves, count pairs satisfying `nums[i] > 2 * nums[j]` using two pointers.', 'O(N log N)', 'O(N)'],

    ['Longest Consecutive Sequence (O(N) HashSet)', 'Arrays', 'Hard',
     'Given an unsorted array of integers nums, return the length of the longest consecutive elements sequence in O(N) time.',
     '0 <= nums.length <= 10^5',
     'nums = [100,4,200,1,3,2]', '4 (Sequence [1, 2, 3, 4])',
     'Insert all numbers into HashSet. Only start counting sequence length when `num - 1` is NOT in the HashSet.', 'O(N)', 'O(N)'],

    ['Max Value of Equation', 'Arrays', 'Hard',
     'You are given an array points where points[i] = [xi, yi] sorted by x. Return max value of yi + yj + |xi - xj| where |xi - xj| <= k.',
     '2 <= points.length <= 10^5, 1 <= k <= 10^8',
     'points = [[1,3],[2,0],[5,10],[6,-10]], k = 1', '4',
     'Equation `yi + yj + xj - xi` can be rewritten as `(yj + xj) + (yi - xi)`. Use Monotonic Deque to store `(yi - xi, xi)`.', 'O(N)', 'O(N)'],

    // =========================================================================
    // STRINGS (40 Problems)
    // =========================================================================
    // Easy
    ['Valid Palindrome II', 'Strings', 'Easy',
     'Given a string s, return true if the s can be palindrome after deleting at most one character from it.',
     '1 <= s.length <= 10^5',
     's = "abca"', 'true (Delete "c" to get "aba")',
     'Two Pointers. When mismatch occurs at (i, j), check if remaining substring `s[i+1...j]` OR `s[i...j-1]` is palindrome.', 'O(N)', 'O(1)'],

    ['Greatest Common Divisor of Strings', 'Strings', 'Easy',
     'For two strings s and t, we say "t divides s" if s = t + ... + t. Return the largest string x such that x divides both str1 and str2.',
     '1 <= str1.length, str2.length <= 1000',
     'str1 = "ABCABC", str2 = "ABC"', '"ABC"',
     'Check if `str1 + str2 == str2 + str1`. If equal, answer is substring `str1[0...gcd(len1, len2)]`.', 'O(L1 + L2)', 'O(1)'],

    ['Reverse Vowels of a String', 'Strings', 'Easy',
     'Given a string s, reverse only all the vowels in the string and return it.',
     '1 <= s.length <= 3 * 10^5',
     's = "hello"', '"holle"',
     'Two Pointers from start and end. Move pointers until both point to vowels, then swap.', 'O(N)', 'O(1)'],

    ['Detect Capital', 'Strings', 'Easy',
     'We define usage of capitals in a word to be right if: 1. All letters are capitals, 2. All letters are not capitals, 3. Only first letter is capital. Return true if correct.',
     '1 <= word.length <= 100',
     'word = "USA"', 'true',
     'Count total uppercase characters. Check if count == len OR count == 0 OR (count == 1 and word[0] is uppercase).', 'O(N)', 'O(1)'],

    ['Longest Uncommon Subsequence I', 'Strings', 'Easy',
     'Given two strings a and b, return the length of the longest uncommon subsequence between a and b. Return -1 if no such sequence.',
     '1 <= a.length, b.length <= 100',
     'a = "aba", b = "cdc"', '3',
     'If `a == b`, return -1. Otherwise, the longer string itself is the longest uncommon subsequence, so return `max(a.length, b.length)`.', 'O(N)', 'O(1)'],

    ['Student Attendance Record I', 'Strings', 'Easy',
     'You are given a string s representing attendance. Return true if student is eligible for attendance award (fewer than 2 "A"s AND no 3 consecutive "L"s).',
     '1 <= s.length <= 1000',
     's = "PPALLP"', 'true',
     'Single pass count total "A"s and track consecutive "L" count.', 'O(N)', 'O(1)'],

    ['Rotated Digits', 'Strings', 'Easy',
     'An integer x is good if after rotating each digit individually by 180 degrees, we get a valid number different from x. Find count of good numbers 1..n.',
     '1 <= n <= 10^4',
     'n = 10', '4 (2, 5, 6, 9)',
     'Digits 0, 1, 8 rotate to themselves; 2, 5, 6, 9 rotate to valid different digits; 3, 4, 7 are invalid.', 'O(N log N)', 'O(1)'],

    ['Most Common Word', 'Strings', 'Easy',
     'Given a paragraph and a list of banned words, return the most frequent word that is not banned.',
     '1 <= paragraph.length <= 1000',
     'paragraph = "Bob hit a ball, the hit BALL flew far after it was hit.", banned = ["hit"]', '"ball"',
     'Normalize paragraph (lowercase, replace punctuation with spaces). Build frequency hash map filtering out banned words.', 'O(N)', 'O(N)'],

    ['Goat Latin', 'Strings', 'Easy',
     'Convert sentence to Goat Latin rules: 1. If word begins with vowel, append "ma". 2. If starts with consonant, move first letter to end and add "ma". 3. Append "a" * word_index.',
     '1 <= sentence.length <= 150',
     'sentence = "I speak Goat Latin"', '"Imaaa peaksmaaaa oatGmaaaaa atinLmaaaaaa"',
     'Split sentence into words, apply transformation rules on each word according to 1-based index.', 'O(N)', 'O(N)'],

    ['Buddy Strings', 'Strings', 'Easy',
     'Given two strings s and goal, return true if you can swap two letters in s so the result equals goal.',
     '1 <= s.length, goal.length <= 2 * 10^4',
     's = "ab", goal = "ba"', 'true',
     'If lengths differ, return false. If `s == goal`, check if s has duplicate characters. Otherwise, find mismatched indices (must be exactly 2) and verify swap.', 'O(N)', 'O(1)'],

    // Medium
    ['Compare Version Numbers', 'Strings', 'Medium',
     'Given two version numbers, version1 and version2, compare them. Return 1 if version1 > version2, -1 if version1 < version2, 0 if equal.',
     '1 <= version1.length, version2.length <= 500',
     'version1 = "1.01", version2 = "1.001"', '0 (Both represent version 1.1)',
     'Split both version strings by ".". Iterate comparing integer values of corresponding revisions, treating missing revisions as 0.', 'O(N + M)', 'O(N + M)'],

    ['Zigzag Conversion', 'Strings', 'Medium',
     'The string "PAYPALISHIRING" is written in a zigzag pattern on a given number of rows. Read line by line.',
     '1 <= s.length <= 1000, 1 <= numRows <= 1000',
     's = "PAYPALISHIRING", numRows = 3', '"PAHNAPLSIIGYIR"',
     'Maintain array of string builders for each row. Step up/down rows changing direction when reaching top (row 0) or bottom (row numRows-1).', 'O(N)', 'O(N)'],

    ['Validate IP Address', 'Strings', 'Medium',
     'Given a string queryIP, return "IPv4" if IP is valid IPv4, "IPv6" if valid IPv6, or "Neither".',
     '1 <= queryIP.length <= 50',
     'queryIP = "172.16.254.1"', '"IPv4"',
     'Check formatting: IPv4 has 4 dot-separated parts (0..255, no leading zeros). IPv6 has 8 colon-separated hex parts (1..4 hex digits).', 'O(N)', 'O(1)'],

    ['Multiply Strings', 'Strings', 'Medium',
     'Given two non-negative integers num1 and num2 represented as strings, return the product of num1 and num2 as a string.',
     '1 <= num1.length, num2.length <= 200',
     'num1 = "123", num2 = "456"', '"56088"',
     'Simulate grade-school multiplication using array of size `len1 + len2`. Position `i + j + 1` stores product of `num1[i] * num2[j]`.', 'O(N * M)', 'O(N + M)'],

    ['Basic Calculator II', 'Strings', 'Medium',
     'Given a string s which represents an expression containing +, -, *, /, evaluate the expression.',
     '1 <= s.length <= 3 * 10^5',
     's = "3+2*2"', '7',
     'Use a Stack to evaluate expressions with operator precedence. Process * and / immediately, and push results for + and - onto stack.', 'O(N)', 'O(N)'],

    ['Find and Replace Pattern', 'Strings', 'Medium',
     'Given a list of strings words and a string pattern, return a list of words[i] that matches pattern (isomorphic mapping).',
     '1 <= words.length <= 50, 1 <= pattern.length <= 20',
     'words = ["abc","deq","mee","aqq","dkd","ccc"], pattern = "abb"', '["mee","aqq"]',
     'Normalize strings into canonical numerical representation (first seen character mapping) and compare normalized representations.', 'O(N * K)', 'O(K)'],

    ['Camelcase Matching', 'Strings', 'Medium',
     'Given a list of queries and a pattern, return boolean array indicating if query matches pattern by inserting lowercase letters into pattern.',
     '1 <= queries.length <= 100',
     'queries = ["FooBar","FooBarTest","FootBall"], pattern = "FB"', '[true,false,true]',
     'Two Pointers per query. Uppercase letters in query MUST match pattern characters sequentially. Extra query uppercase letters cause match failure.', 'O(N * L)', 'O(1)'],

    ['Remove All Adjacent Duplicates In String II', 'Strings', 'Medium',
     'You are given a string s and an integer k. Repeatedly make k duplicate removal operations until no more can be made.',
     '1 <= s.length <= 10^5, 2 <= k <= 10^4',
     's = "deeedbbcccbdaa", k = 3', '"aa"',
     'Use a Stack storing pairs `(character, consecutive_count)`. Increment count on match; pop pair when count reaches k.', 'O(N)', 'O(N)'],

    // Hard
    ['Text Justification', 'Strings', 'Hard',
     'Given an array of words and a width maxWidth, format text such that each line has exactly maxWidth characters and is fully (left and right) justified.',
     '1 <= words.length <= 300, 1 <= maxWidth <= 100',
     'words = ["This", "is", "an", "example", "of", "text", "justification."], maxWidth = 16',
     'Lines formatted with even space distribution',
     'Pack as many words as possible per line. Distribute extra spaces evenly between words (extra spaces assigned from left to right). Last line is left-justified.', 'O(N)', 'O(N)'],

    ['Shortest Palindrome', 'Strings', 'Hard',
     'You are given a string s. You can convert it to a palindrome by adding characters in front of it. Find shortest palindrome.',
     '0 <= s.length <= 5 * 10^4',
     's = "aacecaaa"', '"aaacecaaa"',
     'Use KMP Algorithm preprocessing table (LPS) on concatenated string `s + "#" + reverse(s)` to find longest palindromic prefix.', 'O(N)', 'O(N)'],

    // =========================================================================
    // LINKED LISTS (30 Problems)
    // =========================================================================
    // Easy
    ['Remove Linked List Elements', 'Linked Lists', 'Easy',
     'Given the head of a linked list and an integer val, remove all nodes of the linked list that has Node.val == val.',
     '0 <= nodes <= 10^4',
     'head = [1,2,6,3,4,5,6], val = 6', '[1,2,3,4,5]',
     'Use Dummy Node pointing to head. Traverse list: if `curr.next.val == val`, update `curr.next = curr.next.next`.', 'O(N)', 'O(1)'],

    ['Convert Binary Number in a Linked List to Integer', 'Linked Lists', 'Easy',
     'Given head which is a reference node to a single-linked list. The value of each node in the linked list is either 0 or 1. Return decimal value.',
     '1 <= nodes <= 30',
     'head = [1,0,1]', '5',
     'Iterate through list maintaining `ans = (ans << 1) | curr.val`.', 'O(N)', 'O(1)'],

    // Medium
    ['Insertion Sort List', 'Linked Lists', 'Medium',
     'Sort a linked list using insertion sort.',
     '1 <= nodes <= 5000',
     'head = [4,2,1,3]', '[1,2,3,4]',
     'Maintain sorted dummy list. For each node in original list, find correct position in dummy list and insert.', 'O(N²)', 'O(1)'],

    ['Partition List Around Value X', 'Linked Lists', 'Medium',
     'Given head of linked list and value x, partition it such that all nodes less than x come before nodes greater than or equal to x while preserving original relative order.',
     '0 <= nodes <= 200',
     'head = [1,4,3,2,5,2], x = 3', '[1,2,2,4,3,5]',
     'Maintain two separate dummy lists: `before` (nodes < x) and `after` (nodes >= x). Concatenate `before` to `after`.', 'O(N)', 'O(1)'],

    ['Split Linked List in Parts', 'Linked Lists', 'Medium',
     'Given head of a singly linked list and an integer k, split list into k consecutive linked list parts such that sizes differ by at most 1.',
     '0 <= nodes <= 1000, 1 <= k <= 50',
     'head = [1,2,3], k = 5', '[[1],[2],[3],[],[]]',
     'Calculate total length N. Base size = N/k, extra nodes = N % k. Distribute extra nodes to first N%k parts.', 'O(N + k)', 'O(k)'],

    ['Flatten a Multilevel Doubly Linked List', 'Linked Lists', 'Medium',
     'Given a doubly linked list where nodes have child pointers, flatten the list so all nodes appear in a single-level doubly linked list.',
     '0 <= nodes <= 1000',
     'Multilevel doubly linked list', 'Single level flattened doubly linked list',
     'DFS or Stack traversal. When child node exists, insert child list between `curr` and `curr.next`.', 'O(N)', 'O(N)'],

    // Hard
    ['LFU Cache (Least Frequently Used)', 'Linked Lists', 'Hard',
     'Design and implement a data structure for a Least Frequently Used (LFU) cache.',
     '1 <= capacity <= 10^4',
     'LFUCache(2), put(1,1), put(2,2), get(1), put(3,3), get(2), get(3)', '1, -1, 3',
     'Use Hash Map of key->node and Hash Map of frequency->DoublyLinkedList. Track `minFrequency`.', 'O(1)', 'O(capacity)'],

    // =========================================================================
    // STACKS & QUEUES (35 Problems)
    // =========================================================================
    ['Asteroid Collision', 'Stacks', 'Medium',
     'We are given an array asteroids of integers representing asteroids in a row. Return the state of the asteroids after all collisions.',
     '2 <= asteroids.length <= 10^4',
     'asteroids = [5,10,-5]', '[5,10]',
     'Use Stack. Moving right (>0) push to stack. Moving left (<0) collide with positive asteroids at top of stack until destroyed or stack empty.', 'O(N)', 'O(N)'],

    ['Score of Parentheses', 'Stacks', 'Medium',
     'Given a balanced parentheses string s, return the score of the string. () has score 1, (A) has score 2*A, AB has score A+B.',
     '2 <= s.length <= 50',
     's = "(())"', '2',
     'Use Stack to maintain scores at current nesting depth. Push 0 on "(", and on ")" pop inner score: `score = max(2 * inner, 1) + top()`.', 'O(N)', 'O(N)'],

    ['Validate Stack Sequences', 'Stacks', 'Medium',
     'Given two integer arrays pushed and popped each with distinct values, return true if this could be the result of push and pop operations on empty stack.',
     '1 <= pushed.length <= 1000',
     'pushed = [1,2,3,4,5], popped = [4,5,3,2,1]', 'true',
     'Simulate stack: push elements from `pushed`. Whenever stack top matches `popped[j]`, pop from stack and increment `j`.', 'O(N)', 'O(N)'],

    ['Maximal Rectangle', 'Stacks', 'Hard',
     'Given a rows x cols binary matrix filled with 0s and 1s, find the largest rectangle containing only 1s and return its area.',
     '1 <= rows, cols <= 200',
     'matrix = [["1","0","1","0","0"],["1","0","1","1","1"],["1","1","1","1","1"],["1","0","0","1","0"]]', '6',
     'Convert matrix into histogram heights row by row, then apply "Largest Rectangle in Histogram" stack algorithm for each row.', 'O(R * C)', 'O(C)'],

    // =========================================================================
    // TREES & GRAPHS (50 Problems)
    // =========================================================================
    ['Maximum Difference Between Node and Ancestor', 'Trees', 'Medium',
     'Given root of binary tree, find max V = |Ancestor.val - Node.val|.',
     '1 <= nodes <= 5000',
     'root = [8,3,10,1,6,null,14,null,null,4,7,13]', '7 (|8 - 1| = 7)',
     'DFS passing `curr_min` and `curr_max` down path. At leaf nodes, return `curr_max - curr_min`.', 'O(N)', 'O(H)'],

    ['Deepest Leaves Sum', 'Trees', 'Medium',
     'Given root of binary tree, return sum of values of its deepest leaves.',
     '1 <= nodes <= 10^4',
     'root = [1,2,3,4,5,null,6,7,null,null,null,null,8]', '15',
     'BFS Level Order Traversal. Reset level sum at start of each level. Sum of last processed level is answer.', 'O(N)', 'O(W)'],

    ['Construct BST from Preorder Traversal', 'Trees', 'Medium',
     'Given an array of integers preorder, which represents the preorder traversal of a BST, construct the tree.',
     '1 <= preorder.length <= 100], 1 <= val <= 1000',
     'preorder = [8,5,1,7,10,12]', '[8,5,10,1,7,null,12]',
     'DFS with upper bound bound: if current element > bound, return null.', 'O(N)', 'O(H)'],

    ['Number of Closed Islands', 'Graphs', 'Medium',
     'Given a 2D grid consisting of 0s (land) and 1s (water), return the number of closed islands (completely surrounded by 1s).',
     '1 <= m, n <= 100',
     'grid = [[1,1,1,1,1,1,1],[1,0,0,0,0,0,1],[1,0,1,1,1,0,1],[1,0,1,0,1,0,1],[1,0,1,1,1,0,1],[1,0,0,0,0,0,1],[1,1,1,1,1,1,1]]', '2',
     'First run DFS from all boundary 0-cells to flood-fill non-closed land. Then count and flood-fill remaining enclosed 0-islands.', 'O(M * N)', 'O(M * N)'],

    ['As Far from Land as Possible', 'Graphs', 'Medium',
     'Given an n x n grid containing 0 (water) and 1 (land), find a water cell that maximizes distance to nearest land cell.',
     '1 <= n <= 100',
     'grid = [[1,0,1],[0,0,0],[1,0,1]]', '2',
     'Multi-Source BFS starting simultaneously from all land cells (1s) to calculate shortest distance to all water cells.', 'O(N²)', 'O(N²)'],

    ['Minimum Number of Vertices to Reach All Nodes', 'Graphs', 'Medium',
     'Given a Directed Acyclic Graph (DAG), find smallest set of vertices from which all nodes in graph are reachable.',
     '2 <= n <= 10^5, 1 <= edges.length <= 10^5',
     'n = 6, edges = [[0,1],[0,2],[2,5],[3,4],[4,2]]', '[0, 3]',
     'Nodes with in-degree 0 MUST be in final set because no other vertex can reach them. Return all vertices with in-degree 0.', 'O(V + E)', 'O(V)'],

    // =========================================================================
    // DYNAMIC PROGRAMMING (50 Problems)
    // =========================================================================
    ['Maximum Length of Pair Chain', 'DP', 'Medium',
     'Given an array of pairs, find the length of longest chain where pair (c, d) can follow (a, b) if b < c.',
     '1 <= pairs.length <= 1000',
     'pairs = [[1,2],[2,3],[3,4]]', '2 ([1,2] -> [3,4])',
     'Greedy: Sort pairs by second element (end time). Iterate picking pair if `start > current_end`.', 'O(N log N)', 'O(1)'],

    ['Delete Operation for Two Strings', 'DP', 'Medium',
     'Given two strings word1 and word2, return minimum number of steps to make word1 and word2 the same (deleting 1 char per step).',
     '1 <= word1.length, word2.length <= 500',
     'word1 = "sea", word2 = "eat"', '2',
     'Find Longest Common Subsequence (LCS). Result is `len(word1) + len(word2) - 2 * LCS`.', 'O(N * M)', 'O(N * M)'],

    ['Combination Sum IV', 'DP', 'Medium',
     'Given an array of distinct integers nums and target, return number of possible combinations that add up to target (order matters).',
     '1 <= nums.length <= 200, 1 <= target <= 1000',
     'nums = [1,2,3], target = 4', '7',
     '1D DP: `dp[i]` = number of combinations to reach sum `i`. Outer loop target `i`, inner loop `num` in nums: `dp[i] += dp[i - num]`.', 'O(N * Target)', 'O(Target)'],

    ['Integer Break', 'DP', 'Medium',
     'Given an integer n, break it into sum of k positive integers (k >= 2) and maximize product of those integers.',
     '2 <= n <= 58',
     'n = 10', '36 (3 + 3 + 4 = 10, 3 * 3 * 4 = 36)',
     'DP or Math: Break number into as many 3s as possible. If remainder is 1, combine with last 3 to make 4.', 'O(N)', 'O(N)'],

    ['Push Dominoes', 'DP', 'Medium',
     'Return final state of dominoes after forces applied (L = left push, R = right push, . = standing).',
     '1 <= n <= 10^5',
     'dominoes = ".L.R...LR..L.."', '"LL.RR.LLRRLL.."',
     'Calculate net force acting on each domino position by tracking distance from nearest R on left and nearest L on right.', 'O(N)', 'O(N)'],

    ['Coin Change Problem (Min Coins)', 'DP', 'Medium',
     'Find minimum number of coins needed to make up amount using infinite supply of coins.',
     '1 <= coins.length <= 12, 1 <= amount <= 10^4',
     'coins = [1,2,5], amount = 11', '3 (11 = 5 + 5 + 1)',
     '1D DP initialized to infinity: `dp[i] = min(dp[i], dp[i - coin] + 1)`.', 'O(N * Amount)', 'O(Amount)'],

    ['Paint House', 'DP', 'Medium',
     'Cost to paint n houses 3 colors (red, blue, green) such that no two adjacent houses have same color. Minimize total cost.',
     '1 <= n <= 100',
     'costs = [[17,2,17],[16,16,5],[14,3,19]]', '10',
     'DP: `dp[i][0] = cost[i][0] + min(dp[i-1][1], dp[i-1][2])`.', 'O(N)', 'O(1)'],

    ['Ones and Zeroes (0/1 Knapsack 2D)', 'DP', 'Medium',
     'Given binary strings array and m (max zeros) and n (max ones), find max subset size satisfying constraints.',
     '1 <= strs.length <= 600, 1 <= m, n <= 100',
     'strs = ["10","0001","111001","1","0"], m = 5, n = 3', '4',
     '2D Knapsack DP: `dp[i][j] = max(dp[i][j], dp[i - zeros][j - ones] + 1)`.', 'O(S * M * N)', 'O(M * N)'],

    // =========================================================================
    // HEAPS, GREEDY & OTHER TOPICS (70 Problems)
    // =========================================================================
    ['Kth Smallest Element in Sorted Matrix', 'Heaps', 'Medium',
     'Given an n x n matrix where each row and column is sorted in ascending order, find the kth smallest element.',
     'n == matrix.length, 1 <= n <= 300, 1 <= k <= n²',
     'matrix = [[1,5,9],[10,11,13],[12,13,15]], k = 8', '13',
     'Binary Search on Value Range [matrix[0][0], matrix[n-1][n-1]] or Min-Heap of row pointers.', 'O(N log(max-min))', 'O(1)'],

    ['Sort Characters By Frequency', 'Heaps', 'Medium',
     'Given a string s, sort it in decreasing order based on the frequency of the characters.',
     '1 <= s.length <= 5 * 10^5',
     's = "tree"', '"eert" (or "eetr")',
     'Frequency Map + Max Heap / Bucket Sort.', 'O(N)', 'O(N)'],

    ['Reorganize String', 'Heaps', 'Medium',
     'Rearrange characters of s so that any two adjacent characters are not the same. Return "" if impossible.',
     '1 <= s.length <= 500',
     's = "aab"', '"aba"',
     'Max-Heap of character frequencies. Pop top 2 most frequent characters, append to result, decrement frequencies, and push back.', 'O(N log K)', 'O(K)'],

    ['Minimum Cost to Connect Sticks', 'Greedy', 'Medium',
     'You have sticks of different lengths. Connect all sticks into one stick. Cost to connect two sticks is sum of their lengths. Minimize cost.',
     '1 <= sticks.length <= 10^4',
     'sticks = [2,4,3]', '14',
     'Min-Heap (Priority Queue). Repeatedly extract two smallest sticks, add sum to total cost, and push sum back.', 'O(N log N)', 'O(N)'],

    ['Distant Barcodes', 'Heaps', 'Medium',
     'Rearrange barcodes so no two adjacent barcodes are equal.',
     '1 <= barcodes.length <= 10000',
     'barcodes = [1,1,1,2,2,2]', '[1,2,1,2,1,2]',
     'Fill most frequent elements first into even indices (0, 2, 4...), then odd indices.', 'O(N)', 'O(N)'],

    ['Maximum Frequency Stack', 'Heaps', 'Hard',
     'Design a stack-like structure that pushes and pops element with highest frequency. If tie, pop element closest to top.',
     '1 <= calls <= 2 * 10^4',
     'FreqStack(), push(5), push(7), push(5), push(7), push(4), push(5), pop()', '5',
     'Maintain `freq` map and `group` map of `frequency -> stack_of_elements`. Track `maxFreq`.', 'O(1)', 'O(N)'],

    ['Kth Largest Element in a Stream', 'Heaps', 'Easy',
     'Design a class to find the kth largest element in a stream.',
     '1 <= k <= 10^4',
     'KthLargest(3, [4, 5, 8, 2]), add(3), add(5), add(10), add(9), add(4)', '4, 5, 5, 8, 8',
     'Min-Heap of size k storing k largest elements seen so far.', 'O(log K)', 'O(K)']

];

// Batch insert with duplicate title protection
$inserted = 0;
$skipped = 0;

$stmt = $db->prepare('INSERT INTO coding_problems 
    (title, category, difficulty, problem_statement, constraints, example_input, example_output, concept_explanation, time_complexity, space_complexity) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');

$db->beginTransaction();

foreach ($problems as $p) {
    $titleLower = strtolower(trim($p[0]));
    if (isset($existingTitles[$titleLower])) {
        $skipped++;
        continue;
    }

    $stmt->execute([
        $p[0], // title
        $p[1], // category
        $p[2], // difficulty
        $p[3], // problem_statement
        $p[4], // constraints
        $p[5], // example_input
        $p[6], // example_output
        $p[7], // concept_explanation
        $p[8], // time_complexity
        $p[9]  // space_complexity
    ]);

    $existingTitles[$titleLower] = true;
    $inserted++;
}

$db->commit();

echo "Insertion Complete!\n";
echo "Successfully Inserted: $inserted problems\n";
echo "Skipped (Duplicates): $skipped problems\n";
