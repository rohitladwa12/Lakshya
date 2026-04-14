-- Batch 5: Backtracking, Greedy, and Advanced Data Structures
-- Run this after previous migrations

INSERT INTO coding_problems (title, category, difficulty, problem_statement, constraints, example_input, example_output, concept_explanation, time_complexity, space_complexity) VALUES

-- Backtracking (New Category)
('Generate Parentheses', 'Backtracking', 'Medium',
'Given n pairs of parentheses, write a function to generate all combinations of well-formed parentheses.

Input: n = 3
Output: ["((()))","(()())","(())()","()(())","()()()"]',
'1 <= n <= 8',
'n = 3',
'["((()))","(()())","(())()","()(())","()()()"]',
'Use recursion with backtracking. Keep track of the number of open and closed parentheses used. Add an open parenthesis if count < n, and add a closed one if closed < open. This ensures the parentheses remain well-formed.',
'O(4^n / √n) (Catalan number)',
'O(n)'),

-- Greedy (New Category)
('Jump Game', 'Greedy', 'Medium',
'You are given an integer array nums. You are initially positioned at the array\'s first index, and each element in the array represents your maximum jump length at that position. Return true if you can reach the last index.

Input: [2,3,1,1,4]
Output: true (Jump 1 step from index 0 to 1, then 3 steps to the last index)',
'1 <= nums.length <= 10^4
0 <= nums[i] <= 10^5',
'nums = [3,2,1,0,4]',
'false',
'Greedy approach: Keep track of the furthest reachable index. Iterate through the array; if current index is reachable and (index + nums[index]) is further than current max, update max. If max >= last index, return true.',
'O(n)',
'O(1)'),

-- Trees (More)
('Invert Binary Tree', 'Trees', 'Easy',
'Given the root of a binary tree, invert the tree, and return its root. (Swap left and right children for every node).

Input: [4,2,7,1,3,6,9]
Output: [4,7,2,9,6,3,1]',
'The number of nodes in the tree is in the range [0, 100].
-100 <= Node.val <= 100',
'root = [2,1,3]',
'[2,3,1]',
'Recursive approach: Swap the left and right children of the current node, then recursively call invert on the left and right subtrees. This is a classic tree manipulation problem.',
'O(n)',
'O(h) where h is height'),

-- Linked Lists (More)
('Merge Two Sorted Lists', 'Linked Lists', 'Easy',
'Merge two sorted linked lists and return it as a sorted list. The list should be made by splicing together the nodes of the first two lists.

Input: l1 = [1,2,4], l2 = [1,3,4]
Output: [1,1,2,3,4,4]',
'The number of nodes in both lists is in the range [0, 50].
-100 <= Node.val <= 100',
'l1 = [1,2,4], l2 = [1,3,4]',
'[1,1,2,3,4,4]',
'Use a dummy head node to simplify the logic. Iterate through both lists, comparing values and attaching the smaller node to the merged list. This teaches list merging and the use of dummy nodes.',
'O(n + m)',
'O(1)'),

-- Arrays (More)
('Move Zeroes', 'Arrays', 'Easy',
'Given an integer array nums, move all 0\'s to the end of it while maintaining the relative order of the non-zero elements. Perform this in-place.

Input: [0,1,0,3,12]
Output: [1,3,12,0,0]',
'1 <= nums.length <= 10^4
-2^31 <= nums[i] <= 2^31 - 1',
'nums = [0]',
'[0]',
'Use two pointers. One pointer tracks the position to place the next non-zero element. Iterate through the array; whenever you find a non-zero, swap it with the element at the tracking pointer and move the pointer forward.',
'O(n)',
'O(1)'),

-- Dynamic Programming (More)
('House Robber', 'DP', 'Medium',
'You are a professional robber planning to rob houses along a street. Each house has a certain amount of money stashed. You cannot rob two adjacent houses. Find the maximum money you can rob.

Input: [1,2,3,1]
Output: 4 (Rob house 1 and 3)',
'1 <= nums.length <= 100
0 <= nums[i] <= 400',
'nums = [2,7,9,3,1]',
'12',
'State transition: rob[i] = max(rob[i-1], nums[i] + rob[i-2]). The max money at house i is either the max at house i-1 (if we don\'t rob i) or the money at house i plus max at i-2. This is a classic 1D DP problem.',
'O(n)',
'O(1) with space optimization'),

-- Graphs (More)
('Number of Islands', 'Graphs', 'Medium',
'Given an m x n 2D binary grid which represents a map of \'1\'s (land) and \'0\'s (water), return the number of islands. An island is surrounded by water and is formed by connecting adjacent lands horizontally or vertically.

Input: grid = [
  ["1","1","1","1","0"],
  ["1","1","0","1","0"],
  ["1","1","0","0","0"],
  ["0","0","0","0","0"]
]
Output: 1',
'm == grid.length, n == grid[i].length
1 <= m, n <= 300',
'grid = [["1","1","0","0","0"],["1","1","0","0","0"],["0","0","1","0","0"],["0","0","0","1","1"]]',
'3',
'Iterate through every cell. When you find a \'1\', increment the island count and use DFS/BFS to "sink" (change to \'0\') all connected land for that island. This counts the number of disconnected components in a graph.',
'O(m * n)',
'O(m * n) for recursion stack'),

-- Hash Maps (New Category)
('Longest Consecutive Sequence', 'Hash Map', 'Medium',
'Given an unsorted array of integers nums, return the length of the longest consecutive elements sequence. You must write an algorithm that runs in O(n) time.

Input: [100, 4, 200, 1, 3, 2]
Output: 4 (The sequence is [1, 2, 3, 4])',
'0 <= nums.length <= 10^5
-10^9 <= nums[i] <= 10^9',
'nums = [0,3,7,2,5,8,4,6,0,1]',
'9',
'Add all numbers to a HashSet for O(1) lookups. For each number, if it\'s the start of a sequence (i.e., num-1 is not in the set), count how many consecutive numbers follow it. This ensures we only process each sequence once.',
'O(n)',
'O(n)'),

-- Heaps (More)
('Top K Frequent Elements', 'Heaps', 'Medium',
'Given an integer array nums and an integer k, return the k most frequent elements. You may return the answer in any order.

Input: nums = [1,1,1,2,2,3], k = 2
Output: [1,2]',
'1 <= nums.length <= 10^5
k is in the range [1, number of unique elements in the array]',
'nums = [1], k = 1',
'[1]',
'Step 1: Count frequencies using a Hash Map. Step 2: Use a Min-Heap of size k to store the most frequent elements, or use Bucket Sort for O(n). This combines frequency counting with top-K selection.',
'O(n log k) with heap, or O(n) with bucket sort',
'O(n)'),

-- Strings (More)
('Valid Anagram', 'Strings', 'Easy',
'Given two strings s and t, return true if t is an anagram of s, and false otherwise.

Input: s = "anagram", t = "nagaram"
Output: true',
'1 <= s.length, t.length <= 5 * 10^4
s and t consist of lowercase English letters',
's = "rat", t = "car"',
'false',
'Frequency counting approach. Use an array of size 26 to count occurrences of each character in s and decrement for t. If all counts are zero at the end, they are anagrams. Sorting both strings is also an option.',
'O(n)',
'O(1) since alphabet size is constant'),

-- Arrays (More)
('Contains Duplicate', 'Arrays', 'Easy',
'Given an integer array nums, return true if any value appears at least twice in the array, and return false if every element is distinct.

Input: [1,2,3,1]
Output: true',
'1 <= nums.length <= 10^5
-10^9 <= nums[i] <= 10^9',
'nums = [1,2,3,4]',
'false',
'Use a HashSet to keep track of elements you\'ve already seen. As you iterate through the array, if you encounter an element already in the set, return true. If you finish the loop, return false.',
'O(n)',
'O(n)'),

-- DP (More)
('Maximum Product Subarray', 'DP', 'Medium',
'Given an integer array nums, find a contiguous non-empty subarray within the array that has the largest product, and return the product.

Input: [2,3,-2,4]
Output: 6 (Subarray [2,3])',
'1 <= nums.length <= 2 * 10^4
-10 <= nums[i] <= 10',
'nums = [-2,0,-1]',
'0',
'Track both currentMax and currentMin at each step, because a large negative number multiplied by another negative can become the new maximum. Update: newMax = max(curr, curr*oldMax, curr*oldMin).',
'O(n)',
'O(1)');
