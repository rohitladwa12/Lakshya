-- Batch 6: Advanced Algorithms & Data Structures
-- Run this after previous migrations

INSERT INTO coding_problems (title, category, difficulty, problem_statement, constraints, example_input, example_output, concept_explanation, time_complexity, space_complexity) VALUES

-- Trees (More)
('Lowest Common Ancestor of a BST', 'Trees', 'Easy',
'Given a binary search tree (BST), find the lowest common ancestor (LCA) node of two given nodes in the BST.

Input: root = [6,2,8,0,4,7,9,null,null,3,5], p = 2, q = 8
Output: 6',
'The number of nodes in the tree is in the range [2, 10^5].
-10^9 <= Node.val <= 10^9
p and q will exist in the BST.',
'p = 2, q = 4',
'2',
'In a BST, the LCA is the first node whose value is between p and q. If both p and q are smaller than current node, look left. If both are larger, look right. If they are on opposite sides, the current node is the LCA.',
'O(h) where h is height',
'O(h) for recursion or O(1) for iterative'),

-- Matrices (More)
('Valid Sudoku', 'Matrices', 'Medium',
'Determine if a 9 x 9 Sudoku board is valid. Only the filled cells need to be validated according to the following rules:
1. Each row must contain the digits 1-9 without repetition.
2. Each column must contain the digits 1-9 without repetition.
3. Each of the nine 3 x 3 sub-boxes of the grid must contain the digits 1-9 without repetition.',
'board.length == 9, board[i].length == 9
board[i][j] is a digit 1-9 or \'.\'',
'Standard 9x9 Sudoku grid',
'true (if valid)',
'Use three sets or a bitmask to track seen numbers for each row, column, and 3x3 box. For a cell (r, c), it belongs to box index (r/3)*3 + c/3. This is an efficient way to validate constraints in a single pass.',
'O(1) (fixed 81 cells)',
'O(1) (fixed size storage)'),

-- Sorting (More)
('Sort Colors', 'Sorting', 'Medium',
'Given an array nums with n objects colored red, white, or blue, sort them in-place so that objects of the same color are adjacent, with the colors in the order red, white, and blue (0, 1, 2).

Input: nums = [2,0,2,1,1,0]
Output: [0,0,1,1,2,2]',
'n == nums.length
1 <= n <= 300
nums[i] is 0, 1, or 2.',
'nums = [2,0,1]',
'[0,1,2]',
'Dutch National Flag algorithm. Use three pointers: low, mid, and high. Move mid through the array: if 0, swap with low and move both. If 1, just move mid. If 2, swap with high and move high. This sorts in a single pass.',
'O(n)',
'O(1)'),

-- Backtracking (More)
('Word Search', 'Backtracking', 'Medium',
'Given an m x n grid of characters board and a string word, return true if word exists in the grid. The word can be constructed from letters of sequentially adjacent cells.

Input: board = [["A","B","C","E"],["S","F","C","S"],["A","D","E","E"]], word = "ABCCED"
Output: true',
'm == board.length, n == board[i].length
1 <= m, n <= 6
1 <= word.length <= 15',
'board = [["A","B","C","E"],["S","F","C","S"],["A","D","E","E"]], word = "SEE"',
'true',
'Use DFS from each cell. Mark the current cell as visited (e.g., by changing char) to avoid re-using it in the same word path, then backtrack by restoring it. Check all 4 directions for the next character.',
'O(N * 3^L) where N is cells, L is word length',
'O(L) recursion stack'),

-- Graphs (More)
('Course Schedule', 'Graphs', 'Medium',
'There are total numCourses tasks you have to take, labeled from 0 to numCourses - 1. You are given an array prerequisites where prerequisites[i] = [a, b] indicates that you must take b first if you want to take a. Return true if you can finish all courses.

Input: numCourses = 2, prerequisites = [[1,0]]
Output: true',
'1 <= numCourses <= 2000
0 <= prerequisites.length <= 5000',
'numCourses = 2, prerequisites = [[1,0],[0,1]]',
'false',
'This is a cycle detection problem in a directed graph. Use Kahn\'s algorithm (BFS-based topological sort) using in-degrees, or DFS to detect if a back-edge exists. If a cycle exists, you can\'t finish all courses.',
'O(V + E)',
'O(V + E)'),

-- Tries (New Category)
('Implement Trie (Prefix Tree)', 'Tries', 'Medium',
'A trie (pronounced as "try") or prefix tree is a tree data structure used to efficiently store and retrieve keys in a dataset of strings. Implement Insert, Search, and StartsWith.

Methods: insert(word), search(word), startsWith(prefix)',
'1 <= word.length, prefix.length <= 2000
word and prefix consist only of lowercase English letters.',
'["Trie", "insert", "search", "search", "startsWith", "insert", "search"]
[[], ["apple"], ["apple"], ["app"], ["app"], ["app"], ["app"]]',
'[null, null, true, false, true, null, true]',
'Each node in the trie represents a character and contains a map/array of its children. Use a boolean flag isEndOfWord to mark complete words. This enables O(L) search where L is the length of the string.',
'O(L) for each op',
'O(N * L) total storage'),

-- DP (Hard)
('Edit Distance', 'DP', 'Hard',
'Given two strings word1 and word2, return the minimum number of operations required to convert word1 to word2. You can Insert, Delete, or Replace a character.

Input: word1 = "horse", word2 = "ros"
Output: 3 (h->r, delete r, delete e)',
'0 <= word1.length, word2.length <= 500
Strings consist of lowercase English letters.',
'word1 = "intention", word2 = "execution"',
'5',
'2D Dynamic Programming. dp[i][j] is the min distance between word1[0...i] and word2[0...j]. If word1[i] == word2[j], distance is dp[i-1][j-1]. Otherwise, it\'s 1 + min(delete, insert, replace).',
'O(m * n)',
'O(m * n)'),

-- Heaps (More)
('K Closest Points to Origin', 'Heaps', 'Medium',
'Given an array of points where points[i] = [xi, yi] and an integer k, return the k closest points to the origin (0, 0). The distance is the Euclidean distance: √(x² + y²).

Input: points = [[1,3],[-2,2]], k = 1
Output: [[-2,2]]',
'1 <= k <= points.length <= 10^4
-10^4 <= xi, yi <= 10^4',
'points = [[3,3],[5,-1],[-2,4]], k = 2',
'[[-2,4],[3,3]]',
'Use a Max-Heap of size k to store the points with their distances. When adding a new point, if its distance is smaller than the heap root, replace the root. This is a classic top-K selection problem using distances.',
'O(n log k)',
'O(k)'),

-- Hash Maps (More)
('Subarray Sum Equals K', 'Hash Map', 'Medium',
'Given an array of integers nums and an integer k, return the total number of continuous subarrays whose sum equals to k.

Input: nums = [1,1,1], k = 2
Output: 2',
'1 <= nums.length <= 2 * 10^4
-1000 <= nums[i] <= 1000
-10^7 <= k <= 10^7',
'nums = [1,2,3], k = 3',
'2',
'Use a prefix sum approach with a Hash Map. Store the frequency of each prefix sum encountered. For each new prefix sum S, check if (S - k) has been seen before. If yes, add its frequency to the total count.',
'O(n)',
'O(n)'),

-- Arrays (More)
('Pascal\'s Triangle', 'Arrays', 'Easy',
'Given an integer numRows, return the first numRows of Pascal\'s triangle. In Pascal\'s triangle, each number is the sum of the two numbers directly above it.

Input: numRows = 5
Output: [[1],[1,1],[1,2,1],[1,3,3,1],[1,4,6,4,1]]',
'1 <= numRows <= 30',
'numRows = 1',
'[[1]]',
'Iterative construction row by row. Each row starts and ends with 1. For other elements, sum the two elements from the previous row at indices (j-1) and j. This is a basic demonstration of dynamic programming properties.',
'O(n^2)',
'O(n^2)');
