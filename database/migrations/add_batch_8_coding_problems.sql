-- Batch 8: Advanced Algorithms & Dynamic Programming
-- Run this after previous migrations

INSERT INTO coding_problems (title, category, difficulty, problem_statement, constraints, example_input, example_output, concept_explanation, time_complexity, space_complexity) VALUES

-- DP (More)
('Longest Increasing Subsequence', 'DP', 'Medium',
'Given an integer array nums, return the length of the longest strictly increasing subsequence.',
'1 <= nums.length <= 2500
-10^4 <= nums[i] <= 10^4',
'nums = [10,9,2,5,3,7,101,18]',
'4 (The LIS is [2,3,7,101] or [2,5,7,101/18])',
'Standard DP approach: dp[i] is the length of LIS ending at index i. dp[i] = 1 + max(dp[j]) for all j < i and nums[j] < nums[i]. Optimization: Use binary search with a "tails" array (patience sorting) to achieve O(n log n).',
'O(n²)',
'O(n)'),

('Word Break', 'DP', 'Medium',
'Given a string s and a dictionary of strings wordDict, return true if s can be segmented into a space-separated sequence of one or more dictionary words.',
'1 <= s.length <= 300
1 <= wordDict.length <= 1000
1 <= wordDict[i].length <= 20',
's = "leetcode", wordDict = ["leet","code"]',
'true',
'1D Dynamic Programming. dp[i] is true if s[0...i] can be segmented. For each position i, check all previous positions j < i; if dp[j] is true and s[j...i] is in wordDict, then dp[i] is true. This builds the solution from small substrings.',
'O(n² * k) where k is max word length',
'O(n)'),

-- Backtracking (More)
('N-Queens', 'Backtracking', 'Hard',
'The n-queens puzzle is the problem of placing n queens on an n x n chessboard such that no two queens attack each other. Given an integer n, return all distinct solutions.',
'1 <= n <= 9',
'n = 4',
'[[".Q..","...Q","Q...","..Q."],["..Q.","Q...","...Q",".Q.."]]',
'Recursive backtracking row-by-row. Maintain the state of columns and diagonals (r-c and r+c values) that are currently "under attack". Try placing a queen in each column of the current row and proceed if valid. Backtrack by removing the queen and updating attack states.',
'O(n!)',
'O(n²)'),

('Permutations', 'Backtracking', 'Medium',
'Given an array nums of distinct integers, return all the possible permutations. You can return the answer in any order.',
'1 <= nums.length <= 6
-10 <= nums[i] <= 10',
'nums = [1,2,3]',
'[[1,2,3],[1,3,2],[2,1,3],[2,3,1],[3,1,2],[3,2,1]]',
'Recursive backtracking. In each step, pick an unused number from the input array, add it to the current permutation, and recurse. Maintain a "used" array/set or swap elements in-place to track selected numbers.',
'O(n * n!)',
'O(n!)'),

('Subsets', 'Backtracking', 'Medium',
'Given an integer array nums of unique elements, return all possible subsets (the power set). The solution set must not contain duplicate subsets.',
'1 <= nums.length <= 10
-10 <= nums[i] <= 10',
'nums = [1,2,3]',
'[[],[1],[2],[1,2],[3],[1,3],[2,3],[1,2,3]]',
'Recursive backtracking. At each index, you have two choices: either include the current number in the subset or exclude it. This explores all 2^n combinations. Alternatively, use bitmasking where bits 0 to n-1 represent selection.',
'O(n * 2^n)',
'O(n * 2^n)'),

('Combination Sum', 'Backtracking', 'Medium',
'Given an array of distinct integers candidates and a target integer target, return a list of all unique combinations of candidates where the chosen numbers sum to target. You may return the combinations in any order.',
'1 <= candidates.length <= 30
1 <= candidates[i] <= 200
1 <= target <= 500',
'candidates = [2,3,6,7], target = 7',
'[[2,2,3],[7]]',
'Backtracking with recursion. In each step, you can pick the current element again or move to the next. If the current sum exceeds target, stop. If equal, record solution. Sorting candidates can help with early pruning (stopping when current + next > target).',
'O(2^n)',
'O(target/min)'),

-- DP (More)
('Partition Equal Subset Sum', 'DP', 'Medium',
'Given a non-empty array nums containing only positive integers, find if the array can be partitioned into two subsets such that the sum of elements in both subsets is equal.',
'1 <= nums.length <= 200
1 <= nums[i] <= 100',
'nums = [1,5,11,5]',
'true (Sum is 22, need to find subset with sum 11: [1,5,5])',
'This is a variation of the 0/1 Knapsack problem. Calculate half-sum (total/2). If total is odd, return false. Use DP to check if any combination of numbers adds up to half-sum. Use a boolean array `dp[j]` to store if sum `j` is reachable.',
'O(n * sum)',
'O(sum)'),

-- Strings (More)
('Longest Palindromic Substring', 'Strings', 'Medium',
'Given a string s, return the longest palindromic substring in s.',
'1 <= s.length <= 1000
s consists of digits and English letters.',
's = "babad"',
'bab (or aba)',
'Expand around center: iterate through each character (and gaps between chars) treating them ascenters. Expand outwards as long as characters match. Alternatively, use 2D DP where dp[i][j] stores if s[i...j] is a palindrome.',
'O(n²)',
'O(1) (for center-expansion)'),

('Palindromic Substrings', 'Strings', 'Medium',
'Given a string s, return the number of palindromic substrings in it.',
'1 <= s.length <= 1000',
's = "aaa"',
'6 (a, a, a, aa, aa, aaa)',
'Similar to center expansion used in "Longest Palindromic Substring". For each possible center (n chars + n-1 gaps = 2n-1 centers), count how many valid expansions are possible. Each valid expansion is a unique palindromic substring.',
'O(n²)',
'O(1)'),

-- Greedy (More)
('Gas Station', 'Greedy', 'Medium',
'There are n gas stations along a circular route, where the amount of gas at station i is gas[i]. You have a car with an unlimited gas tank and it costs cost[i] of gas to travel from station i to its next station (i + 1). Find the starting gas station index.',
'gas.length == cost.length == n
1 <= n <= 10^5
0 <= gas[i], cost[i] <= 10^4',
'gas = [1,2,3,4,5], cost = [3,4,5,1,2]',
'3 (Station 3 gives 4 gas, cost 1 to reach 4. Then 5-2, 1-3, etc.)',
'Greedy logic: If the total gas is less than total cost, return -1. Otherwise, a solution must exist. Iterate through stations tracking "current tank". If it drops below zero, reset start to the next station. This works because a deficit at any point means no previous station could have been the start.',
'O(n)',
'O(1)');
